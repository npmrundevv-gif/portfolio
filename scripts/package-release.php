<?php
/**
 * Repeatable Production Release Packaging Script
 * 
 * Creates an isolated, clean production distribution archive without secrets,
 * development caches, test databases, user media, or node_modules.
 * 
 * Usage:
 *   php scripts/package-release.php [--skip-build]
 */

declare(strict_types=1);

$rootPath = realpath(__DIR__ . '/..');
if (!$rootPath) {
    fwrite(STDERR, "[Error] Unable to resolve project root directory.\n");
    exit(1);
}

echo "========================================================\n";
echo "  Portfolio Application Release Packaging\n";
echo "========================================================\n\n";

$skipBuild = in_array('--skip-build', $argv, true);
$timestamp = date('Ymd_His');
$version = '1.0.0';
$distDir = $rootPath . DIRECTORY_SEPARATOR . 'dist';
$stagingDir = $distDir . DIRECTORY_SEPARATOR . "staging_{$timestamp}";
$archiveName = "portfolio-release-v{$version}-{$timestamp}.zip";
$archivePath = $distDir . DIRECTORY_SEPARATOR . $archiveName;
$checksumPath = $distDir . DIRECTORY_SEPARATOR . "{$archiveName}.sha256";

// Step 1: Frontend Build Verification
if (!$skipBuild) {
    echo "[1/6] Building fresh production frontend assets (npm run build)...\n";
    $buildCmd = 'npm run build';
    passthru($buildCmd, $buildExitCode);
    if ($buildExitCode !== 0) {
        fwrite(STDERR, "[Error] Frontend asset build failed with exit code {$buildExitCode}.\n");
        exit(1);
    }
} else {
    echo "[1/6] Skipping frontend build step (--skip-build requested)...\n";
}

$manifestPath = $rootPath . '/public/build/manifest.json';
if (!file_exists($manifestPath)) {
    fwrite(STDERR, "[Error] Production asset manifest not found at {$manifestPath}.\n");
    exit(1);
}
echo "      Found production manifest: " . basename($manifestPath) . "\n";

// Step 2: Prepare Isolated Staging Directory
echo "\n[2/6] Preparing isolated staging environment at:\n      {$stagingDir}\n";
if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}
if (is_dir($stagingDir)) {
    deleteDirectory($stagingDir);
}
mkdir($stagingDir, 0755, true);

// Step 3: Copy Whitelisted Application Files
echo "\n[3/6] Copying production application components...\n";

$directoriesToCopy = [
    'app',
    'config',
    'database/factories',
    'database/migrations',
    'database/seeders',
    'resources/views',
    'routes',
];

foreach ($directoriesToCopy as $dir) {
    $source = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    $dest = $stagingDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    if (is_dir($source)) {
        copyDirectory($source, $dest, function ($filePath) {
            $name = basename($filePath);
            return !str_ends_with($name, '.sqlite') && !str_ends_with($name, '.sql') && !str_ends_with($name, '.tmp');
        });
        echo "      + Copied {$dir}/\n";
    }
}

// Copy bootstrap essentials
mkdir($stagingDir . '/bootstrap/cache', 0755, true);
copy($rootPath . '/bootstrap/app.php', $stagingDir . '/bootstrap/app.php');
if (file_exists($rootPath . '/bootstrap/providers.php')) {
    copy($rootPath . '/bootstrap/providers.php', $stagingDir . '/bootstrap/providers.php');
}
file_put_contents($stagingDir . '/bootstrap/cache/.gitignore', "*\n!.gitignore\n");
echo "      + Copied bootstrap/ (fresh empty cache directory)\n";

// Copy public assets (strictly excluding user media, hot file, and storage symlinks)
mkdir($stagingDir . '/public', 0755, true);
$publicFiles = ['.htaccess', 'favicon.ico', 'index.php', 'robots.txt'];
foreach ($publicFiles as $pf) {
    $src = $rootPath . '/public/' . $pf;
    if (file_exists($src)) {
        copy($src, $stagingDir . '/public/' . $pf);
    }
}

$publicDirs = ['build', 'css', 'fonts', 'js'];
foreach ($publicDirs as $pd) {
    $src = $rootPath . '/public/' . $pd;
    $dst = $stagingDir . '/public/' . $pd;
    if (is_dir($src)) {
        copyDirectory($src, $dst);
    }
}
// Leave public/storage absent so storage:link can create the deployment-specific symlink.
echo "      + Copied public/ (production assets and build manifest included; hot/storage links excluded)\n";

// Create clean storage structure with .gitignore placeholders
$storageDirs = [
    'app/public',
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'logs',
];
foreach ($storageDirs as $sd) {
    $targetPath = $stagingDir . '/storage/' . $sd;
    mkdir($targetPath, 0755, true);
    file_put_contents($targetPath . '/.gitignore', "*\n!.gitignore\n");
}
echo "      + Initialized storage/ framework directory structure (no user media, no logs)\n";

// Copy root configuration and manifests
$rootFiles = [
    'artisan',
    'composer.json',
    'composer.lock',
    '.env.example',
    'README.md',
    'DEPLOYMENT.md',
];
foreach ($rootFiles as $rf) {
    $src = $rootPath . '/' . $rf;
    if (file_exists($src)) {
        copy($src, $stagingDir . '/' . $rf);
        echo "      + Copied {$rf}\n";
    }
}

// Step 4: Staging Hygiene Audit
echo "\n[4/6] Verifying release hygiene and safety constraints...\n";
$forbiddenPatterns = [
    '#\.env$#i',
    '#\.env\.backup$#i',
    '#\.env\.production$#i',
    '#\.key$#i',
    '#\.sqlite$#i',
    '#\.sql$#i',
    '#\.zip$#i',
    '#hot$#i',
    '#node_modules#i',
    '#storage[/\\\\]logs[/\\\\].+\.log$#i',
    '#bootstrap[/\\\\]cache[/\\\\](packages|services|config|routes)\.php$#i',
];

$allStagedFiles = getDirectoryFiles($stagingDir);
$violations = [];

foreach ($allStagedFiles as $file) {
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $file)) {
            $violations[] = $file;
        }
    }
}

if (!empty($violations)) {
    fwrite(STDERR, "[Error] Release staging hygiene check failed! Forbidden files detected:\n");
    foreach ($violations as $v) {
        fwrite(STDERR, "  - {$v}\n");
    }
    deleteDirectory($stagingDir);
    exit(1);
}
echo "      Hygiene check passed: 0 forbidden artifacts detected among " . count($allStagedFiles) . " staged files.\n";

// Step 5: Archive Generation
echo "\n[5/6] Creating ZIP release archive...\n      {$archivePath}\n";
if (file_exists($archivePath)) {
    unlink($archivePath);
}

$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "[Error] Failed to open ZipArchive at {$archivePath}\n");
    deleteDirectory($stagingDir);
    exit(1);
}

$stagingPrefixLen = strlen($stagingDir) + 1;
foreach ($allStagedFiles as $filePath) {
    $relative = substr($filePath, $stagingPrefixLen);
    // Use forward slashes in ZIP
    $zipRelative = str_replace('\\', '/', $relative);
    $zip->addFile($filePath, $zipRelative);
}

$zip->close();

// Step 6: Checksum & Verification
echo "\n[6/6] Computing SHA-256 integrity checksum...\n";
$sha256 = hash_file('sha256', $archivePath);
file_put_contents($checksumPath, "{$sha256}  {$archiveName}\n");

$archiveSize = filesize($archivePath);
$archiveSizeKb = round($archiveSize / 1024, 2);
$archiveSizeMb = round($archiveSize / (1024 * 1024), 2);

// Clean staging directory
deleteDirectory($stagingDir);

echo "\n========================================================\n";
echo "  RELEASE PACKAGING COMPLETE (SUCCESS)\n";
echo "========================================================\n";
echo "  Archive Path:    {$archivePath}\n";
echo "  Archive Size:    {$archiveSize} bytes ({$archiveSizeMb} MB / {$archiveSizeKb} KB)\n";
echo "  File Count:      " . count($allStagedFiles) . " files packaged\n";
echo "  SHA-256 Hash:    {$sha256}\n";
echo "  Checksum File:   {$checksumPath}\n";
echo "========================================================\n";

// Helper Functions
function copyDirectory(string $src, string $dst, ?callable $filter = null): void
{
    $dir = opendir($src);
    if (!$dir) return;
    @mkdir($dst, 0755, true);
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . DIRECTORY_SEPARATOR . $file;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
        if ($filter && !$filter($srcPath)) continue;
        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $dstPath, $filter);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

function deleteDirectory(string $dir): bool
{
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    if (!$items) return false;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

function getDirectoryFiles(string $dir): array
{
    $files = [];
    $items = scandir($dir);
    if (!$items) return [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $files = array_merge($files, getDirectoryFiles($path));
        } else {
            $files[] = $path;
        }
    }
    return $files;
}
