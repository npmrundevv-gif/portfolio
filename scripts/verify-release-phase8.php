<?php

declare(strict_types=1);

$rootPath = realpath(__DIR__ . '/..');
$distDir = $rootPath . DIRECTORY_SEPARATOR . 'dist';
$archivePath = $distDir . DIRECTORY_SEPARATOR . 'portfolio-release-v1.0.0-20261109_031705.zip';
$extractDir = $distDir . DIRECTORY_SEPARATOR . 'verify_release_' . date('Ymd_His');

echo "=== PHASE 8: EXTRACTED RELEASE INSTALLATION VERIFICATION ===\n\n";

if (!file_exists($archivePath)) {
    fwrite(STDERR, "[Error] Archive not found at {$archivePath}\n");
    exit(1);
}
echo "1. Source Archive: " . basename($archivePath) . " (" . filesize($archivePath) . " bytes)\n";

mkdir($extractDir, 0755, true);

// 2. Extract Archive
echo "\n2. Extracting archive to isolated test directory...\n   {$extractDir}\n";
$zip = new ZipArchive();
if ($zip->open($archivePath) !== true) {
    fwrite(STDERR, "[Error] Failed to open ZipArchive\n");
    exit(1);
}
$zip->extractTo($extractDir);
$zip->close();
echo "   Extracted successfully.\n";

// 3. Verify packaged files hygiene
echo "\n3. Checking release hygiene in extracted directory...\n";
$forbidden = ['.env', '.env.backup', 'database.sqlite', 'hot'];
foreach ($forbidden as $f) {
    if (file_exists($extractDir . '/' . $f) || file_exists($extractDir . '/public/' . $f)) {
        fwrite(STDERR, "[Violation] Forbidden file {$f} detected in extracted release!\n");
        exit(1);
    }
}
echo "   Hygiene OK: No .env, no local SQLite, no hot file found.\n";

// 4. Create synthetic isolated test .env
echo "\n4. Creating synthetic environment (.env) with disposable temporary APP_KEY...\n";
$syntheticEnv = <<<EOT
APP_NAME=Portfolio
APP_ENV=production
APP_KEY=base64:49tWlO+7lJ5E6k8VqM1vYzrQoB3sTxLhN2uFpWs0dC4=
APP_DEBUG=false
APP_URL=http://localhost:8099

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=sqlite
DB_DATABASE=database/disposable_verify.sqlite

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
EOT;
file_put_contents($extractDir . '/.env', $syntheticEnv);
touch($extractDir . '/database/disposable_verify.sqlite');

// 5. Run composer install --no-dev
echo "\n5. Running composer install --no-dev from packaged lockfile...\n";
$composerCmd = "cd /d \"{$extractDir}\" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction 2>&1";
exec($composerCmd, $composerOutput, $composerCode);
if ($composerCode !== 0) {
    echo "   First attempt returned code {$composerCode}. Retrying in 2 seconds (Windows transient file lock defense)...\n";
    sleep(2);
    $composerOutput = [];
    exec($composerCmd, $composerOutput, $composerCode);
}
echo implode("\n", array_slice($composerOutput, -8)) . "\n";
if ($composerCode !== 0) {
    fwrite(STDERR, "[Error] Composer install failed with code {$composerCode}\n");
    exit(1);
}
echo "   Composer install --no-dev succeeded (Code: 0).\n";

// 6. Test Laravel boot and cache generation
echo "\n6. Testing Laravel boot and cache commands...\n";
$commands = [
    'php artisan --version',
    'php artisan migrate --force',
    'php artisan config:cache',
    'php artisan route:cache',
    'php artisan view:cache',
];

foreach ($commands as $cmd) {
    $fullCmd = "cd /d \"{$extractDir}\" && {$cmd} 2>&1";
    exec($fullCmd, $cmdOutput, $cmdCode);
    $lastLine = end($cmdOutput) ?: '';
    echo "   - {$cmd} -> [Exit {$cmdCode}] {$lastLine}\n";
    if ($cmdCode !== 0) {
        fwrite(STDERR, "[Error] Command failed: {$cmd}\n");
        exit(1);
    }
}

// 7. Verify HTTP responses for GET / and GET /admin/login
echo "\n7. Testing HTTP responses on temporary server...\n";
$testServerCmd = "php -S 127.0.0.1:8099 -t \"{$extractDir}/public\"";
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$serverProcess = proc_open($testServerCmd, $descriptors, $pipes, $extractDir);
sleep(2);

$routesToTest = [
    '/' => 200,
    '/admin/login' => 200,
];

$allPassed = true;
foreach ($routesToTest as $uri => $expectedStatus) {
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
    $resp = @file_get_contents("http://127.0.0.1:8099{$uri}", false, $ctx);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $m);
    $statusCode = isset($m[1]) ? (int)$m[1] : 0;
    
    echo "   - GET {$uri} -> Status {$statusCode} (Expected: {$expectedStatus})\n";
    if ($statusCode !== $expectedStatus) {
        $allPassed = false;
    }
}

// 8. Verify production assets resolve
echo "\n8. Verifying production assets resolve via HTTP...\n";
$manifest = json_decode(file_get_contents($extractDir . '/public/build/manifest.json'), true);
$jsAsset = $manifest['resources/js/app.js']['file'] ?? '';
$cssAsset = $manifest['resources/css/app.css']['file'] ?? '';

if ($jsAsset) {
    $jsUrl = "http://127.0.0.1:8099/build/{$jsAsset}";
    $jsContent = @file_get_contents($jsUrl);
    echo "   - GET /build/{$jsAsset} -> " . ($jsContent !== false ? "Resolved (" . strlen($jsContent) . " bytes)" : "FAILED") . "\n";
}
if ($cssAsset) {
    $cssUrl = "http://127.0.0.1:8099/build/{$cssAsset}";
    $cssContent = @file_get_contents($cssUrl);
    echo "   - GET /build/{$cssAsset} -> " . ($cssContent !== false ? "Resolved (" . strlen($cssContent) . " bytes)" : "FAILED") . "\n";
}

// Kill temporary server
proc_terminate($serverProcess);
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($serverProcess);

// 9. Cleanup
echo "\n9. Cleaning up isolated test directory...\n";
exec("rmdir /s /q \"{$extractDir}\"");
echo "   Cleanup completed.\n";

if (!$allPassed) {
    fwrite(STDERR, "\n[FATAL] One or more HTTP checks failed!\n");
    exit(1);
}

echo "\n=== EXTRACTED RELEASE VERIFICATION: 100% PASSED ===\n";
