<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ChatController extends Controller
{
    /**
     * Chatbot request/response contract:
     *
     * Request:  POST /chat/message  (throttle:20,1 per client)
     *   message:  required string, 1-1000 chars, must contain non-whitespace
     *   history:  optional array, max 30 entries
     *     *.role: required string, in: user, ai, model
     *     *.text: required string, max 1000 chars
     *
     * Total history payload soft-limit: ~32KB (30 entries × 1000 chars + message)
     *
     * Response (success):  200 { text: string, provider: string }
     * Response (disabled): 403 { error: string }
     * Response (invalid):  422 { message: string, errors: {...} }
     * Response (throttle): 429 { error: string }
     * Response (failure):  503 { error: string }
     * Response (no keys):  200 { text: string, provider: "mock" }
     *
     * Supported client roles: user, ai, model
     *   - "ai" and "model" map to provider assistant/model role
     *   - "user" maps to provider user role
     *   - Clients cannot inject system/developer roles
     *
     * Timeouts: 5s connect, 15s request per attempt
     * Total budget: 30s across all attempts
     * Max attempts: 3 (across all providers/keys)
     * Aggregate limit: 100 upstream attempts per minute (server-wide)
     */

    /** Maximum upstream provider attempts per request */
    private const MAX_ATTEMPTS = 3;

    /** Total time budget in seconds for all provider attempts */
    private const TOTAL_BUDGET_SECONDS = 30;

    /** Per-attempt connection timeout */
    private const CONNECT_TIMEOUT = 5;

    /** Per-attempt request timeout */
    private const REQUEST_TIMEOUT = 15;

    /** Maximum context length sent to provider (chars) */
    private const MAX_CONTEXT_LENGTH = 4000;

    /** Aggregate upstream attempts per minute (server-wide) */
    private const AGGREGATE_LIMIT = 100;

    /** Aggregate limit window in seconds */
    private const AGGREGATE_WINDOW = 60;

    /** Aggregate limit atomic lock key */
    private const AGGREGATE_LOCK_KEY = 'chatbot:aggregate_upstream_lock';

    /** Overridable clock for deterministic tests */
    public static ?\Closure $timeProvider = null;

    /**
     * Get current timestamp (supports simulated time in tests).
     */
    protected function currentTime(): float
    {
        return self::$timeProvider ? (self::$timeProvider)() : microtime(true);
    }

    public function sendMessage(Request $request)
    {
        // ── 1. Check chatbot enabled ─────────────────────────────────────────
        $settings = SiteSetting::instance();
        if (!$settings->chatbot_enabled) {
            return response()->json(['error' => 'Chatbot is disabled'], 403);
        }

        // ── 2. Validate and normalize input ──────────────────────────────────
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:30'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,ai,model'],
            'history.*.text' => ['required_with:history', 'string', 'max:1000'],
        ]);

        $message = $validated['message'];

        // Reject whitespace-only messages after validation
        if (trim($message) === '') {
            return response()->json([
                'message' => 'The message field is required.',
                'errors' => ['message' => ['The message must contain non-whitespace content.']],
            ], 422);
        }

        // Normalize history: treat null/missing as empty array, select only validated fields
        $history = [];
        if (!empty($validated['history'])) {
            foreach ($validated['history'] as $entry) {
                $history[] = [
                    'role' => $entry['role'],
                    'text' => $entry['text'],
                ];
            }
        }

        // ── 3. Build key pool ────────────────────────────────────────────────
        $pool = $this->buildKeyPool();

        if (empty($pool)) {
            $brandName = $settings->brand_name ?? 'Portfolio';
            return response()->json([
                'text' => "I'm currently running in offline mode. Please configure API keys to enable AI responses. In the meantime, feel free to explore **{$brandName}**'s portfolio!",
                'provider' => 'mock',
            ]);
        }

        // ── 4. Shuffle key pool for load distribution ───────────────────────
        shuffle($pool);

        // ── 5. Build portfolio context ───────────────────────────────────────
        $systemInstruction = $this->buildPortfolioContext($settings);

        // ── 6. Try providers within budget ───────────────────────────────────
        $startTime = $this->currentTime();
        $attempts = 0;
        $lastErrorCategory = 'unknown';

        foreach ($pool as $entry) {
            if ($attempts >= self::MAX_ATTEMPTS) {
                break;
            }

            $elapsed = $this->currentTime() - $startTime;
            $remaining = self::TOTAL_BUDGET_SECONDS - $elapsed;

            // Check remaining budget before starting a new attempt
            if ($remaining < self::CONNECT_TIMEOUT) {
                $lastErrorCategory = 'budget_exhausted';
                Log::info('ChatController: time budget exhausted, stopping attempts.', [
                    'attempts' => $attempts,
                    'elapsed' => round($elapsed, 2),
                ]);
                break;
            }

            // Atomically check and reserve an aggregate quota slot for this attempt.
            // Protects against race conditions under concurrent requests.
            // Lock is released immediately upon reservation, BEFORE provider network call.
            try {
                $admitted = $this->reserveAggregateQuota($remaining);
                if (!$admitted) {
                    Log::warning('ChatController: aggregate upstream limit reached.', [
                        'attempt' => $attempts + 1,
                    ]);
                    return response()->json([
                        'error' => 'The assistant is experiencing high demand. Please try again shortly.',
                    ], 503);
                }
            } catch (\Throwable $e) {
                // If locking or cache store fails, fail closed safely
                Log::error('ChatController: aggregate admission lock/cache store error.', [
                    'exception_class' => get_class($e),
                ]);
                return response()->json([
                    'error' => 'The assistant is temporarily unavailable. Please try again shortly.',
                ], 503);
            }

            // Recalculate remaining budget after lock wait
            $elapsed = $this->currentTime() - $startTime;
            $remaining = self::TOTAL_BUDGET_SECONDS - $elapsed;
            if ($remaining < self::CONNECT_TIMEOUT) {
                $lastErrorCategory = 'budget_exhausted';
                Log::info('ChatController: time budget exhausted after lock wait.', [
                    'attempts' => $attempts,
                    'elapsed' => round($elapsed, 2),
                ]);
                break;
            }

            $attempts++;
            $provider = $entry['provider'];
            $key = $entry['key'];

            // Cap per-attempt request timeout and connection timeout to remaining budget
            $attemptTimeout = (int) min(self::REQUEST_TIMEOUT, floor($remaining));
            $connectTimeout = (int) min(self::CONNECT_TIMEOUT, $attemptTimeout);

            try {
                $response = $provider === 'groq'
                    ? $this->callGroq($key, $history, $message, $systemInstruction, $attemptTimeout, $connectTimeout)
                    : $this->callGemini($key, $history, $message, $systemInstruction, $attemptTimeout, $connectTimeout);

                if ($response->status() === 429) {
                    $lastErrorCategory = 'provider_rate_limit';
                    Log::info('ChatController: provider rate-limited.', [
                        'provider' => $provider,
                        'attempt' => $attempts,
                    ]);
                    continue;
                }

                if ($response->status() === 401 || $response->status() === 403) {
                    $lastErrorCategory = 'provider_auth_error';
                    Log::warning('ChatController: provider authentication error.', [
                        'provider' => $provider,
                        'status' => $response->status(),
                        'attempt' => $attempts,
                    ]);
                    // Auth errors are permanent for this key; skip to next
                    continue;
                }

                if ($response->serverError()) {
                    $lastErrorCategory = 'provider_server_error';
                    Log::warning('ChatController: provider server error.', [
                        'provider' => $provider,
                        'status' => $response->status(),
                        'attempt' => $attempts,
                    ]);
                    continue;
                }

                if ($response->failed()) {
                    $lastErrorCategory = 'provider_error';
                    Log::warning('ChatController: provider returned error.', [
                        'provider' => $provider,
                        'status' => $response->status(),
                        'attempt' => $attempts,
                    ]);
                    continue;
                }

                $text = $this->extractResponseText($provider, $response);

                if ($text === null || $text === '') {
                    $lastErrorCategory = 'empty_response';
                    Log::warning('ChatController: provider returned empty/blocked response.', [
                        'provider' => $provider,
                        'attempt' => $attempts,
                    ]);
                    continue;
                }

                return response()->json(['text' => $text, 'provider' => $provider]);

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastErrorCategory = 'connection_timeout';
                Log::warning('ChatController: connection/timeout failure.', [
                    'provider' => $provider,
                    'attempt' => $attempts,
                    'elapsed' => round($this->currentTime() - $startTime, 2),
                ]);
                continue;

            } catch (\Exception $e) {
                $lastErrorCategory = 'exception';
                // Log only safe metadata — never raw exception message which may contain credentials
                Log::error('ChatController: unexpected exception.', [
                    'provider' => $provider,
                    'attempt' => $attempts,
                    'exception_class' => get_class($e),
                    'elapsed' => round($this->currentTime() - $startTime, 2),
                ]);
                continue;
            }
        }

        return response()->json([
            'error' => 'The assistant is temporarily unavailable. Please try again shortly.',
        ], 503);
    }

    /**
     * Atomically check and reserve an upstream attempt quota slot.
     * Uses a short-lived atomic lock on the cache store to ensure check-and-hit
     * is mutually exclusive across concurrent processes/requests.
     *
     * The lock is released IMMEDIATELY after reserving the quota, before any
     * provider HTTP call is made.
     *
     * Bounded lock wait is accounted for against the remaining time budget.
     *
     * @return bool True if quota was reserved, false if quota exhausted
     * @throws \Throwable If cache/lock fails (fails closed safely)
     */
    protected function reserveAggregateQuota(float $remainingBudget): bool
    {
        $aggregateKey = 'chatbot:aggregate_upstream';

        // Bounded lock wait: at most 2 seconds, never exceeding remaining budget above CONNECT_TIMEOUT
        $maxLockWait = (int) max(1, min(2, floor($remainingBudget - self::CONNECT_TIMEOUT)));

        $lock = Cache::lock(self::AGGREGATE_LOCK_KEY, 5);

        try {
            return (bool) $lock->block($maxLockWait, function () use ($aggregateKey) {
                if (RateLimiter::tooManyAttempts($aggregateKey, self::AGGREGATE_LIMIT)) {
                    return false;
                }
                RateLimiter::hit($aggregateKey, self::AGGREGATE_WINDOW);
                return true;
            });
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Extract response text from provider JSON.
     */
    private function extractResponseText(string $provider, $response): ?string
    {
        try {
            $json = $response->json();

            if ($provider === 'groq') {
                return $json['choices'][0]['message']['content'] ?? null;
            }

            // Gemini
            return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Build bounded portfolio context from published content only.
     * Excludes: credentials, admin settings, image_path, internal metadata.
     */
    private function buildPortfolioContext(SiteSetting $settings): string
    {
        $brandName = $settings->brand_name ?? 'Portfolio';
        $heroTitle = $settings->hero_title ?? '';
        $heroSubtitle = $settings->hero_subtitle ?? '';

        // Load enabled sections with enabled child records, selecting only public fields
        $sections = Section::where('enabled', true)
            ->orderBy('sort_order')
            ->with([
                'works' => fn($q) => $q->where('enabled', true)->select('id', 'section_id', 'title', 'caption', 'tags', 'url'),
                'caseStudies' => fn($q) => $q->where('enabled', true)->select('id', 'section_id', 'title', 'caption', 'tags', 'url'),
                'testimonials' => fn($q) => $q->where('enabled', true)->select('id', 'section_id', 'quote', 'name', 'role'),
                'stats' => fn($q) => $q->where('enabled', true)->select('id', 'section_id', 'label', 'value', 'suffix'),
                'timelineEvents' => fn($q) => $q->where('enabled', true)->select('id', 'section_id', 'year', 'title', 'description', 'tags'),
            ])
            ->get(['id', 'key', 'title', 'subtitle']);

        $contextParts = [];

        foreach ($sections as $section) {
            $part = "{$section->title}";
            if ($section->subtitle) {
                $part .= ": {$section->subtitle}";
            }

            // Append child content summaries
            $children = [];

            foreach ($section->works as $w) {
                $children[] = "Work: {$w->title}" . ($w->caption ? " — {$w->caption}" : '');
            }
            foreach ($section->caseStudies as $cs) {
                $children[] = "Case Study: {$cs->title}" . ($cs->caption ? " — {$cs->caption}" : '');
            }
            foreach ($section->testimonials as $t) {
                $children[] = "Testimonial by {$t->name}" . ($t->role ? " ({$t->role})" : '') . ": \"{$t->quote}\"";
            }
            foreach ($section->stats as $s) {
                $children[] = "Stat: {$s->label} {$s->value}{$s->suffix}";
            }
            foreach ($section->timelineEvents as $te) {
                $children[] = "Timeline {$te->year}: {$te->title}" . ($te->description ? " — {$te->description}" : '');
            }

            if (!empty($children)) {
                $part .= '. Items: ' . implode('; ', $children);
            }

            $contextParts[] = $part;
        }

        $sectionContent = implode('. ', $contextParts);

        // Truncate context to bounded size
        if (mb_strlen($sectionContent) > self::MAX_CONTEXT_LENGTH) {
            $sectionContent = mb_substr($sectionContent, 0, self::MAX_CONTEXT_LENGTH) . '…';
        }

        return
            "You are a helpful AI assistant for {$brandName}.\n" .
            "FORMATTING: Use Markdown with headers, bullets, and bold for clarity.\n" .
            "KNOWLEDGE: Expert on this portfolio — Brand: {$brandName}" .
            ($heroTitle ? ", Hero: {$heroTitle}" : '') .
            ($heroSubtitle ? ", Subtitle: {$heroSubtitle}" : '') . ".\n" .
            "Content: {$sectionContent}\n" .
            "Be professional, polite, and enthusiastically helpful. " .
            "Only discuss topics related to this portfolio. " .
            "If you don't have specific information, say so honestly.";
    }

    // ── Gemini REST call ─────────────────────────────────────────────────────
    private function callGemini(string $key, array $history, string $message, string $systemInstruction, int $timeout, int $connectTimeout)
    {
        $contents = [];
        foreach ($history as $turn) {
            $role = ($turn['role'] === 'ai' || $turn['role'] === 'model') ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $turn['text']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $payload = [
            'contents' => $contents,
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 1000],
        ];

        $model = config('services.gemini.model', 'gemini-2.5-flash');

        // Gemini REST API supports x-goog-api-key header (standard Google API header).
        // This avoids passing secrets via URL query strings, preventing leak in access logs and referrers.
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        return Http::withoutRedirecting()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $key,
            ])
            ->post($url, $payload);
    }

    // ── Groq REST call (OpenAI-compatible) ───────────────────────────────────
    private function callGroq(string $key, array $history, string $message, string $systemInstruction, int $timeout, int $connectTimeout)
    {
        $messages = [['role' => 'system', 'content' => $systemInstruction]];

        foreach ($history as $turn) {
            $role = ($turn['role'] === 'ai' || $turn['role'] === 'model') ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => $turn['text']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $model = config('services.groq.model', 'llama-3.3-70b-versatile');
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ];

        return Http::withoutRedirecting()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$key}",
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', $payload);
    }

    // ── Build a unified pool of {provider, key} entries ──────────────────────
    private function buildKeyPool(): array
    {
        $pool = [];

        $geminiKeys = $this->parseKeys(
            config('services.gemini.key'),
            config('services.gemini.keys')
        );
        foreach ($geminiKeys as $k) {
            $pool[] = ['provider' => 'gemini', 'key' => $k];
        }

        $groqKeys = $this->parseKeys(
            config('services.groq.key'),
            config('services.groq.keys')
        );
        foreach ($groqKeys as $k) {
            $pool[] = ['provider' => 'groq', 'key' => $k];
        }

        return $pool;
    }

    // ── Parse single key + comma-separated keys into unique array ────────────
    private function parseKeys(?string $singleKey, ?string $multiKeys): array
    {
        $keys = [];
        if ($singleKey) {
            $keys[] = trim($singleKey);
        }
        if ($multiKeys) {
            foreach (explode(',', $multiKeys) as $k) {
                $k = trim($k);
                if ($k) {
                    $keys[] = $k;
                }
            }
        }
        return array_values(array_unique($keys));
    }
}
