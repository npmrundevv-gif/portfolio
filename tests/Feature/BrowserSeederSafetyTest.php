<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class BrowserSeederSafetyTest extends TestCase
{
    private string $seederScript;
    private string $phpBinary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seederScript = base_path('tests/Browser/seed_test_data.php');
        $this->phpBinary = PHP_BINARY;
        $isolationDir = base_path('database/test_isolation');
        if (!is_dir($isolationDir)) {
            mkdir($isolationDir, 0755, true);
        }
    }

    private function runSeederWithEnv(array $customEnv): array
    {
        $baseEnv = [
            'PATH' => getenv('PATH') ?: '',
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
        ];
        $env = array_merge($baseEnv, $customEnv);

        $cmd = escapeshellarg($this->phpBinary) . ' ' . escapeshellarg($this->seederScript) . ' 2>&1';
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output' => $stdout . $stderr,
        ];
    }

    public function test_seeder_refuses_when_no_target_specified(): void
    {
        $result = $this->runSeederWithEnv([
            'TEST_DB_PATH' => '',
            'DB_DATABASE' => '',
        ]);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('FATAL [SAFETY GATE]: No test database specified', $result['output']);
    }

    public function test_seeder_refuses_path_traversal(): void
    {
        $result = $this->runSeederWithEnv([
            'TEST_DB_PATH' => 'database/../database.sqlite',
        ]);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Path traversal sequence', $result['output']);
    }

    public function test_seeder_refuses_reserved_database_name(): void
    {
        $result = $this->runSeederWithEnv([
            'TEST_DB_PATH' => 'database/database.sqlite',
        ]);

        $this->assertSame(1, $result['exitCode']);
        $this->assertMatchesRegularExpression('/(reserved application database name|Invalid test database filename)/', $result['output']);
    }

    public function test_seeder_refuses_non_approved_directory(): void
    {
        $unsafeTarget = base_path('public/test_forbidden.sqlite');
        if (file_exists($unsafeTarget)) {
            unlink($unsafeTarget);
        }
        $result = $this->runSeederWithEnv([
            'TEST_DB_PATH' => $unsafeTarget,
        ]);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('is NOT an approved disposable test directory', $result['output']);
        if (file_exists($unsafeTarget)) {
            unlink($unsafeTarget);
        }
    }

    public function test_seeder_refuses_and_preserves_sentinel_data(): void
    {
        // Create a sentinel SQLite file in an unauthorized path
        $sentinelPath = base_path('database/sentinel_app_data.sqlite');
        if (file_exists($sentinelPath)) {
            unlink($sentinelPath);
        }
        $pdo = new \PDO('sqlite:' . $sentinelPath);
        $pdo->exec('CREATE TABLE sentinel (id INTEGER PRIMARY KEY, secret_value TEXT)');
        $pdo->exec("INSERT INTO sentinel (id, secret_value) VALUES (1, 'UNTOUCHED_SENTINEL_TOKEN_12345')");
        unset($pdo);

        $result = $this->runSeederWithEnv([
            'TEST_DB_PATH' => $sentinelPath,
        ]);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Invalid test database filename', $result['output']);

        // Verify sentinel data remains 100% untouched
        $pdoVerify = new \PDO('sqlite:' . $sentinelPath);
        $stmt = $pdoVerify->query('SELECT secret_value FROM sentinel WHERE id = 1');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        unset($stmt);
        unset($pdoVerify);
        @unlink($sentinelPath);

        $this->assertIsArray($row);
        $this->assertSame('UNTOUCHED_SENTINEL_TOKEN_12345', $row['secret_value']);
    }

    public function test_seeder_succeeds_on_approved_disposable_target(): void
    {
        $approvedTarget = base_path('database/test_isolation/test_valid_seed_run.sqlite');
        if (file_exists($approvedTarget)) {
            unlink($approvedTarget);
        }

        $result = $this->runSeederWithEnv([
            'TEST_DB_PATH' => $approvedTarget,
        ]);

        $this->assertSame(0, $result['exitCode'], "Seeder failed with output: {$result['output']}");
        $this->assertStringContainsString('TEST_DATA_SEEDED_OK', $result['output']);
        $this->assertStringContainsString('test_valid_seed_run.sqlite', $result['output']);

        // Verify tables exist and have data
        $pdo = new \PDO('sqlite:' . $approvedTarget);
        $count = $pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn();
        unset($pdo);
        unlink($approvedTarget);

        $this->assertGreaterThan(0, $count);
    }
}
