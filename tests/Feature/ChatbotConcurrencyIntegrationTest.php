<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatbotConcurrencyIntegrationTest extends TestCase
{
    private string $testDbPath;
    private string $scratchDir;
    private string $workerScript;
    private string $phpBinary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phpBinary = PHP_BINARY;
        $this->workerScript = base_path('tests/Feature/concurrency_worker.php');
        $this->scratchDir = base_path('database/test_isolation/concurrency_scratch');

        if (!is_dir($this->scratchDir)) {
            mkdir($this->scratchDir, 0755, true);
        }

        $this->testDbPath = $this->scratchDir . '/test_concurrency_shared.sqlite';
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath);
        }
        touch($this->testDbPath);

        // Configure database and run migrations on shared DB
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->testDbPath,
            'database.connections.sqlite.busy_timeout' => 5000,
            'database.connections.sqlite.journal_mode' => 'WAL',
            'database.connections.sqlite.transaction_mode' => 'IMMEDIATE',
            'cache.default' => 'database',
            'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.table' => 'cache',
            'cache.stores.database.lock_table' => 'cache_locks',
            'services.gemini.key' => 'test_gemini_key_1',
            'services.groq.key' => 'test_groq_key_1',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA journal_mode = WAL;');
        DB::statement('PRAGMA busy_timeout = 5000;');

        Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
        ]);

        // Ensure chatbot is enabled in DB
        SiteSetting::truncate();
        SiteSetting::create([
            'brand_name' => 'Concurrency Test Portfolio',
            'chatbot_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up scratch files
        if (is_dir($this->scratchDir)) {
            $files = glob($this->scratchDir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($this->scratchDir);
        }

        parent::tearDown();
    }

    /**
     * Helper to run multiple worker processes in parallel using barrier synchronization.
     */
    private function executeConcurrentWorkers(int $workerCount, ?string $upstreamCounterFile = null, bool $failFirst = false): array
    {
        $barrierFile = $this->scratchDir . '/barrier_' . uniqid() . '.signal';
        $readyFiles = [];
        $resultFiles = [];
        $processes = [];
        $pipes = [];

        // Launch worker processes
        for ($i = 1; $i <= $workerCount; $i++) {
            $readyFile = $this->scratchDir . "/ready_{$i}.txt";
            $resultFile = $this->scratchDir . "/result_{$i}.json";
            @unlink($readyFile);
            @unlink($resultFile);

            $readyFiles[$i] = $readyFile;
            $resultFiles[$i] = $resultFile;

            $cmd = escapeshellarg($this->phpBinary) . ' ' . escapeshellarg($this->workerScript) . ' '
                . '--db=' . escapeshellarg($this->testDbPath) . ' '
                . "--worker-id={$i} "
                . '--barrier-file=' . escapeshellarg($barrierFile) . ' '
                . '--ready-file=' . escapeshellarg($readyFile) . ' '
                . '--result-file=' . escapeshellarg($resultFile);

            if ($upstreamCounterFile) {
                $cmd .= ' --upstream-counter-file=' . escapeshellarg($upstreamCounterFile);
            }
            if ($failFirst) {
                $cmd .= ' --fail-first';
            }

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $baseEnv = [
                'PATH' => getenv('PATH') ?: '',
                'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            ];

            $proc = proc_open($cmd, $descriptors, $procPipes, base_path(), $baseEnv);
            $processes[$i] = $proc;
            $pipes[$i] = $procPipes;
        }

        // Wait for all workers to reach the barrier (up to 5s)
        $barrierWaitStart = microtime(true);
        $allReady = false;
        while (!$allReady && (microtime(true) - $barrierWaitStart < 5.0)) {
            $allReady = true;
            foreach ($readyFiles as $rf) {
                if (!file_exists($rf)) {
                    $allReady = false;
                    break;
                }
            }
            if (!$allReady) {
                usleep(1000); // 1ms
            }
        }

        // Release the barrier so all workers fire simultaneously!
        file_put_contents($barrierFile, 'GO');

        // Wait for all processes to finish and collect results
        $results = [];
        foreach ($processes as $i => $proc) {
            $stdout = stream_get_contents($pipes[$i][1]);
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][0]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $exitCode = proc_close($proc);

            $resultFile = $resultFiles[$i];
            if (file_exists($resultFile)) {
                $content = json_decode(file_get_contents($resultFile), true);
                $results[$i] = $content ?? ['status' => 0, 'error' => 'invalid_json', 'raw' => file_get_contents($resultFile)];
            } else {
                $results[$i] = [
                    'status' => 0,
                    'error' => 'no_result_file',
                    'exitCode' => $exitCode,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                ];
            }
        }

        @unlink($barrierFile);
        return $results;
    }

    /**
     * Test genuinely concurrent workers competing for the final remaining quota slot.
     */
    public function test_concurrent_workers_competing_for_final_quota_slot(): void
    {
        // Repeat the race 5 times to confirm consistency
        for ($iteration = 1; $iteration <= 5; $iteration++) {
            // Set up shared DB cache connection
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $this->testDbPath,
                'cache.default' => 'database',
            ]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            // Reset rate limiter & quota cache
            DB::table('cache')->truncate();
            DB::table('cache_locks')->truncate();

            // Prime aggregate quota in shared database cache so exactly 1 slot remains (99 out of 100 consumed)
            $aggregateKey = 'chatbot:aggregate_upstream';
            $dbCache = \Illuminate\Support\Facades\Cache::store('database');
            $rateLimiter = new \Illuminate\Cache\RateLimiter($dbCache);
            for ($k = 0; $k < 99; $k++) {
                $rateLimiter->hit($aggregateKey, 60);
            }

            // Upstream call counter file
            $upstreamCounterFile = $this->scratchDir . "/upstream_calls_{$iteration}.txt";
            file_put_contents($upstreamCounterFile, '0');

            // Launch 4 workers simultaneously racing for the 1 remaining slot
            $results = $this->executeConcurrentWorkers(4, $upstreamCounterFile);

            $statuses = array_column($results, 'status');
            $statusCounts = array_count_values($statuses);

            $successCount = $statusCounts[200] ?? 0;
            $throttleCount = $statusCounts[503] ?? 0;

            // Assert exactly 1 worker was admitted (200 OK)
            $this->assertSame(
                1,
                $successCount,
                "Iteration {$iteration}: Expected exactly 1 admitted worker, got {$successCount}. Statuses: " . json_encode($statuses)
            );

            // Assert exactly 3 workers were throttled (503 Service Unavailable)
            $this->assertSame(
                3,
                $throttleCount,
                "Iteration {$iteration}: Expected exactly 3 throttled workers, got {$throttleCount}. Statuses: " . json_encode($statuses)
            );

            // Verify upstream calls: exactly 1 upstream call was made
            $actualUpstreamCalls = (int) file_get_contents($upstreamCounterFile);
            $this->assertSame(
                1,
                $actualUpstreamCalls,
                "Iteration {$iteration}: Upstream service was called {$actualUpstreamCalls} times, expected exactly 1."
            );

            // Verify lock was released cleanly (cache_locks table is empty)
            $locksRemaining = DB::table('cache_locks')->count();
            $this->assertSame(0, $locksRemaining, "Iteration {$iteration}: Lock was not released cleanly.");

            @unlink($upstreamCounterFile);
        }
    }

    /**
     * Test that retries/fallbacks consume an additional quota slot according to application policy.
     */
    public function test_retries_consume_quota_and_stop_when_exhausted(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->testDbPath,
            'cache.default' => 'database',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        DB::table('cache')->truncate();
        DB::table('cache_locks')->truncate();

        // Prime aggregate quota in shared database cache so exactly 1 slot remains
        $aggregateKey = 'chatbot:aggregate_upstream';
        $dbCache = \Illuminate\Support\Facades\Cache::store('database');
        $rateLimiter = new \Illuminate\Cache\RateLimiter($dbCache);
        for ($k = 0; $k < 99; $k++) {
            $rateLimiter->hit($aggregateKey, 60);
        }

        $upstreamCounterFile = $this->scratchDir . '/upstream_retry_calls.txt';
        file_put_contents($upstreamCounterFile, '0');

        // Launch 1 worker with fail-first enabled (first provider returns 429)
        // Since only 1 quota slot remains:
        // - 1st attempt consumes slot 100 -> succeeds in reservation, hits upstream -> gets 429
        // - 2nd attempt tries to reserve slot 101 -> quota exhausted -> rejected with 503!
        // - Result: total upstream calls = exactly 1 (the 2nd attempt was blocked by quota)
        $results = $this->executeConcurrentWorkers(1, $upstreamCounterFile, failFirst: true);

        $result = $results[1];
        $this->assertSame(503, $result['status'], 'Worker should receive 503 when fallback quota is exhausted.');

        $actualCalls = (int) file_get_contents($upstreamCounterFile);
        $this->assertSame(1, $actualCalls, 'Fallback attempt should not reach upstream service when quota is exhausted.');

        @unlink($upstreamCounterFile);
    }
}
