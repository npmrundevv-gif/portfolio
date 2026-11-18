<?php

declare(strict_types=1);

/**
 * Worker script for multi-process concurrency testing of ChatController.
 * Simulates genuinely concurrent client requests competing for upstream quota.
 */

// Parse CLI options
$options = getopt('', [
    'db:',
    'worker-id:',
    'barrier-file:',
    'ready-file:',
    'result-file:',
    'upstream-counter-file:',
    'fail-first',
]);

$dbPath = $options['db'] ?? null;
$workerId = $options['worker-id'] ?? '1';
$barrierFile = $options['barrier-file'] ?? null;
$readyFile = $options['ready-file'] ?? null;
$resultFile = $options['result-file'] ?? null;
$upstreamCounterFile = $options['upstream-counter-file'] ?? null;
$failFirst = isset($options['fail-first']);

if (!$dbPath || !$barrierFile || !$readyFile || !$resultFile) {
    fwrite(STDERR, "Missing required arguments.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__, 2);

// 1. Bootstrap Laravel with database cache
require $projectRoot . '/vendor/autoload.php';
$app = require_once $projectRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $dbPath,
    'database.connections.sqlite.busy_timeout' => 5000,
    'database.connections.sqlite.journal_mode' => 'WAL',
    'database.connections.sqlite.transaction_mode' => 'IMMEDIATE',
    'cache.default' => 'database',
    'cache.stores.database.connection' => 'sqlite',
    'cache.stores.database.table' => 'cache',
    'cache.stores.database.lock_table' => 'cache_locks',
    'services.gemini.key' => 'test_gemini_key_1',
    'services.groq.key' => 'test_groq_key_1',
    'session.driver' => 'array',
]);

Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::reconnect('sqlite');
Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL;');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 5000;');

// Configure mock keys so ChatController builds a key pool
putenv('GEMINI_API_KEY=test_gemini_key_1');
putenv('GROQ_API_KEY=test_groq_key_1');
$_ENV['GEMINI_API_KEY'] = 'test_gemini_key_1';
$_ENV['GROQ_API_KEY'] = 'test_groq_key_1';

// Setup fake upstream HTTP that counts calls atomically
Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) use ($upstreamCounterFile, $failFirst, $workerId) {
    // Increment upstream counter file safely with flock
    if ($upstreamCounterFile) {
        $fp = fopen($upstreamCounterFile, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $count = (int) stream_get_contents($fp);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) ($count + 1));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    if ($failFirst) {
        // Return 429 to trigger fallback and consume a 2nd quota slot
        return Illuminate\Support\Facades\Http::response(['error' => 'rate limit'], 429);
    }

    // Success response
    if (str_contains($request->url(), 'generativelanguage.googleapis.com')) {
        return Illuminate\Support\Facades\Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Gemini response for worker ' . $workerId]]]],
            ],
        ], 200);
    }

    return Illuminate\Support\Facades\Http::response([
        'choices' => [
            ['message' => ['content' => 'Groq response for worker ' . $workerId]],
        ],
    ], 200);
});

// Signal that worker is ready and waiting at the barrier
file_put_contents($readyFile, 'READY');

// Busy-wait on barrier file (up to 10 seconds timeout)
$startTime = microtime(true);
while (!file_exists($barrierFile)) {
    usleep(500); // 0.5 ms sleep for tight synchronization
    if (microtime(true) - $startTime > 10.0) {
        file_put_contents($resultFile, json_encode(['error' => 'barrier_timeout', 'status' => 0]));
        exit(1);
    }
}

// Barrier released! Execute request immediately
$dispatchStart = microtime(true);
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Initialize session with valid CSRF token
$sessionManager = $app->make('session');
$session = $sessionManager->driver('array');
$session->setId('worker_session_' . $workerId);
$session->start();
$csrfToken = $session->token();

$httpRequest = Illuminate\Http\Request::create(
    '/chat/message',
    'POST',
    ['message' => 'Hello from concurrent worker ' . $workerId],
    [],
    [],
    [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        'HTTP_X_CSRF_TOKEN' => $csrfToken,
        'REMOTE_ADDR' => '127.0.0.' . $workerId, // Different client IPs so per-client rate limiter doesn't block
    ]
);
$httpRequest->setLaravelSession($session);

$response = $httpKernel->handle($httpRequest);
$dispatchElapsed = microtime(true) - $dispatchStart;

$resultData = [
    'worker_id' => $workerId,
    'status' => $response->getStatusCode(),
    'elapsed' => $dispatchElapsed,
    'body' => json_decode($response->getContent(), true),
];

file_put_contents($resultFile, json_encode($resultData));
exit(0);
