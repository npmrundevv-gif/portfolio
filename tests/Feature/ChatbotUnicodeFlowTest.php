<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ChatbotUnicodeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.key', 'fake-gemini-key');
        Config::set('services.gemini.keys', null);
        Config::set('services.groq.key', null);
        Config::set('services.groq.keys', null);
        RateLimiter::clear('chatbot:aggregate_upstream');
        Http::preventStrayRequests();
        \App\Http\Controllers\ChatController::$timeProvider = null;

        $settings = SiteSetting::instance();
        $settings->update([
            'chatbot_enabled' => true,
            'brand_name' => 'Idan Portfolio',
        ]);
    }

    protected function tearDown(): void
    {
        \App\Http\Controllers\ChatController::$timeProvider = null;
        parent::tearDown();
    }

    /**
     * Test AI response containing emojis, supplementary characters (surrogate pairs),
     * combining marks, and line breaks, followed by a user turn passing this in history.
     */
    public function test_unicode_rich_ai_response_and_follow_up_turn_in_history()
    {
        // Unicode elements:
        // Emojis: 🚀 🔥 ✨ 💡 👨‍💻
        // Supplementary ideograph: 𠜎 (U+2070E)
        // Musical symbol: 𝄞 (U+1D11E)
        // Combining character: e + \u{0301} (é)
        // Multi-line formatting
        $complexUnicode = "Hello! 🚀✨\nHere is some code & math:\n𝄞 Key of G\nRare glyph: 𠜎\nAccent: e\u{0301}\nAll clear! 🔥";

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $complexUnicode]]]]],
            ], 200),
        ]);

        // 1. Initial turn returns the Unicode response
        $response1 = $this->postJson('/chat/message', ['message' => 'Tell me something cool with emojis! 💡']);
        $response1->assertStatus(200);
        $this->assertEquals($complexUnicode, $response1->json('text'));

        // 2. Client submits follow-up turn with history containing this complex Unicode response
        $response2 = $this->postJson('/chat/message', [
            'message' => 'Thanks! Can you summarize that? 👨‍💻',
            'history' => [
                ['role' => 'user', 'text' => 'Tell me something cool with emojis! 💡'],
                ['role' => 'ai', 'text' => $complexUnicode],
            ],
        ]);

        $response2->assertStatus(200);
    }

    /**
     * Test history validation with 1000 multibyte characters (valid) vs 1001 characters (invalid).
     */
    public function test_history_length_validation_with_multibyte_characters()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Understood']]]]],
            ], 200),
        ]);

        // 1000 3-byte Japanese kanji characters (3000 bytes, exactly 1000 chars)
        $kanji1000 = str_repeat('漢', 1000);
        $this->assertEquals(1000, mb_strlen($kanji1000, 'UTF-8'));
        $this->assertEquals(3000, strlen($kanji1000));

        $validResponse = $this->postJson('/chat/message', [
            'message' => 'Valid multibyte history test',
            'history' => [
                ['role' => 'user', 'text' => 'First message'],
                ['role' => 'ai', 'text' => $kanji1000],
            ],
        ]);
        $validResponse->assertStatus(200);

        // 1001 characters: must fail validation
        $kanji1001 = str_repeat('漢', 1001);
        $invalidResponse = $this->postJson('/chat/message', [
            'message' => 'Invalid multibyte history test',
            'history' => [
                ['role' => 'user', 'text' => 'First message'],
                ['role' => 'ai', 'text' => $kanji1001],
            ],
        ]);
        $invalidResponse->assertStatus(422)
            ->assertJsonValidationErrors('history.1.text');
    }

    /**
     * Test history item count limit: max 30 items.
     */
    public function test_history_item_count_limit()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'OK']]]]],
            ], 200),
        ]);

        // Exactly 30 items should pass
        $history30 = [];
        for ($i = 0; $i < 30; $i++) {
            $history30[] = [
                'role' => ($i % 2 === 0) ? 'user' : 'ai',
                'text' => "Message item #{$i}",
            ];
        }

        $response30 = $this->postJson('/chat/message', [
            'message' => 'Turn 16',
            'history' => $history30,
        ]);
        $response30->assertStatus(200);

        // 31 items should be rejected with 422
        $history31 = $history30;
        $history31[] = ['role' => 'user', 'text' => 'Excess item'];

        $response31 = $this->postJson('/chat/message', [
            'message' => 'Turn 17',
            'history' => $history31,
        ]);
        $response31->assertStatus(422)
            ->assertJsonValidationErrors('history');
    }

    /**
     * Test history role validation: client roles allowed are user, ai, model.
     * System, developer, or injection roles must be rejected.
     */
    public function test_history_role_validation()
    {
        // Valid roles: user, ai, model
        foreach (['user', 'ai', 'model'] as $validRole) {
            Http::fake([
                'generativelanguage.googleapis.com/*' => Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => 'Valid role OK']]]]],
                ], 200),
            ]);

            $response = $this->postJson('/chat/message', [
                'message' => 'Role test',
                'history' => [
                    ['role' => $validRole, 'text' => 'Test message'],
                ],
            ]);
            $response->assertStatus(200);
        }

        // Invalid roles: system, developer, admin, <script>
        foreach (['system', 'developer', 'admin', '<script>alert(1)</script>', ''] as $invalidRole) {
            $response = $this->postJson('/chat/message', [
                'message' => 'Role test',
                'history' => [
                    ['role' => $invalidRole, 'text' => 'Test message'],
                ],
            ]);
            $response->assertStatus(422)
                ->assertJsonValidationErrors('history.0.role');
        }
    }

    /**
     * Test that disabled chatbot makes zero external provider calls.
     */
    public function test_disabled_chatbot_makes_zero_provider_calls()
    {
        $settings = SiteSetting::instance();
        $settings->update(['chatbot_enabled' => false]);

        $response = $this->postJson('/chat/message', [
            'message' => 'Hello while disabled',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Chatbot is disabled']);

        Http::assertNothingSent();
    }
}

