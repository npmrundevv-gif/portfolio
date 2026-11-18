<?php
/**
 * Test Seeder for Chrome CDP Browser Runtime Automation
 * Seeds representative sections, media references, and works into an isolated test SQLite DB.
 * 
 * STRICT MULTI-TIER SAFETY ENFORCEMENT:
 * 1. Requires an explicit disposable test target (TEST_DB_PATH or DB_DATABASE).
 * 2. Rejects any non-sqlite target.
 * 3. Enforces that the target SQLite path resolves inside an approved disposable test directory.
 * 4. Strictly forbids path traversal escaping (e.g. '..') and reserved application DB names.
 * 5. Rejects any path matching the normal application database configured in .env.
 * 6. After Laravel boot, validates the effective active DB connection and driver.
 * 7. Isolates cache and session drivers to in-memory 'array' to prevent cache poisoning.
 */

declare(strict_types=1);

$rawTarget = getenv('TEST_DB_PATH') ?: getenv('DB_DATABASE');

if (!$rawTarget || !is_string($rawTarget) || trim($rawTarget) === '') {
    fwrite(STDERR, "FATAL [SAFETY GATE]: No test database specified.\n");
    fwrite(STDERR, "REFUSING TO RUN: seed_test_data.php requires an explicit TEST_DB_PATH or DB_DATABASE environment variable pointing to an approved disposable test SQLite file.\n");
    exit(1);
}

$rawTarget = trim($rawTarget);

// Disallow path traversal characters before resolution
if (str_contains($rawTarget, '..')) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Path traversal sequence '..' detected in target: '{$rawTarget}'.\n");
    fwrite(STDERR, "REFUSING TO RUN: Escaping the approved test directory is strictly forbidden.\n");
    exit(1);
}

// Target must have .sqlite extension
if (!str_ends_with(strtolower($rawTarget), '.sqlite')) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Target database must be a .sqlite file: '{$rawTarget}'.\n");
    exit(1);
}

// Approved test directories
$projectRoot = realpath(__DIR__ . '/../..');
if (!$projectRoot) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Could not resolve project root directory.\n");
    exit(1);
}

$approvedDirectories = [
    $projectRoot . DIRECTORY_SEPARATOR . 'database',
    $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'test_isolation',
    $projectRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Browser' . DIRECTORY_SEPARATOR . 'storage',
];

// Normalize target path
$targetDir = dirname($rawTarget);
if (!is_dir($targetDir)) {
    // If it's relative to project root
    $relDir = $projectRoot . DIRECTORY_SEPARATOR . $targetDir;
    if (is_dir($relDir)) {
        $targetDir = $relDir;
        $rawTarget = $projectRoot . DIRECTORY_SEPARATOR . $rawTarget;
    } else {
        @mkdir($targetDir, 0755, true);
    }
}

$realTargetDir = realpath($targetDir);
if (!$realTargetDir) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Target directory cannot be resolved: '{$targetDir}'.\n");
    exit(1);
}

$isApprovedDir = false;
foreach ($approvedDirectories as $appDir) {
    $realAppDir = realpath($appDir);
    if ($realAppDir && ($realTargetDir === $realAppDir || str_starts_with($realTargetDir, $realAppDir . DIRECTORY_SEPARATOR))) {
        $isApprovedDir = true;
        break;
    }
}

if (!$isApprovedDir) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Target directory '{$realTargetDir}' is NOT an approved disposable test directory.\n");
    fwrite(STDERR, "Approved directories are:\n");
    foreach ($approvedDirectories as $appDir) {
        fwrite(STDERR, " - {$appDir}\n");
    }
    exit(1);
}

$basename = strtolower(basename($rawTarget));

// Strict naming rule: must start with test_ or phase6_test or browser_test
if (!preg_match('/^(test_|phase6_test|browser_test).+\.sqlite$|^phase6_test\.sqlite$/i', $basename)) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Invalid test database filename: '{$basename}'.\n");
    fwrite(STDERR, "Filename must match approved pattern: test_*.sqlite, browser_test*.sqlite, or phase6_test.sqlite.\n");
    exit(1);
}

// Explicitly forbidden filenames
$forbiddenNames = [
    'database.sqlite',
    'portfolio.sqlite',
    'production.sqlite',
    'laravel.sqlite',
    'main.sqlite',
    'app.sqlite',
    'staging.sqlite',
];

if (in_array($basename, $forbiddenNames, true)) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: '{$basename}' is a reserved application database name. REFUSING TO RUN.\n");
    exit(1);
}

// Ensure file exists before realpath resolution
if (!file_exists($rawTarget)) {
    touch($rawTarget);
}
$resolvedTestDb = realpath($rawTarget);
if (!$resolvedTestDb) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Unable to resolve realpath for '{$rawTarget}'.\n");
    exit(1);
}

// Check against normal database in .env if present
$normalDb = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';
if (file_exists($normalDb) && realpath($normalDb) === $resolvedTestDb) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Target matches normal application database: '{$resolvedTestDb}'. REFUSING TO RUN.\n");
    exit(1);
}

// 2. Bootstrap Laravel in isolated mode
require $projectRoot . '/vendor/autoload.php';
$app = require_once $projectRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Force the configuration to disposable target and in-memory cache/sessions
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $resolvedTestDb,
    'cache.default' => 'array',
    'session.driver' => 'array',
]);

Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::reconnect('sqlite');

// 3. Post-boot verification of effective connection
$effectiveConnection = Illuminate\Support\Facades\DB::connection();
if ($effectiveConnection->getDriverName() !== 'sqlite') {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Active connection driver is '{$effectiveConnection->getDriverName()}', expected 'sqlite'.\n");
    exit(1);
}

$effectiveDb = realpath((string)$effectiveConnection->getDatabaseName());
if (!$effectiveDb || $effectiveDb !== $resolvedTestDb) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Active database '{$effectiveDb}' does not match authorized target '{$resolvedTestDb}'.\n");
    exit(1);
}

// Check again that effective connection is not application's normal DB
if (file_exists($normalDb) && realpath($normalDb) === $effectiveDb) {
    fwrite(STDERR, "FATAL [SAFETY GATE]: Post-boot verification caught active connection pointing to normal DB: '{$effectiveDb}'.\n");
    exit(1);
}

// Run migrations on isolated test database
Illuminate\Support\Facades\Artisan::call('migrate', [
    '--database' => 'sqlite',
    '--force' => true,
]);

use App\Models\Section;
use App\Models\SiteSetting;
use App\Models\Work;
use App\Models\CaseStudy;
use App\Models\TechStack;
use App\Models\TimelineEvent;

SiteSetting::truncate();
Section::truncate();
Work::truncate();
CaseStudy::truncate();
TechStack::truncate();
TimelineEvent::truncate();

$works = glob($projectRoot . '/storage/app/public/works/*.{jpg,png,jpeg}', GLOB_BRACE) ?: [];
$caseStudies = glob($projectRoot . '/storage/app/public/case-studies/*.{jpg,png,jpeg}', GLOB_BRACE) ?: [];
$work1 = !empty($works[0]) ? str_replace($projectRoot . '/storage/app/public/', '', $works[0]) : 'works/sample.jpg';
$work2 = !empty($works[1]) ? str_replace($projectRoot . '/storage/app/public/', '', $works[1]) : 'works/sample2.jpg';
$case1 = !empty($caseStudies[0]) ? str_replace($projectRoot . '/storage/app/public/', '', $caseStudies[0]) : 'case-studies/sample.jpg';
$case2 = !empty($caseStudies[1]) ? str_replace($projectRoot . '/storage/app/public/', '', $caseStudies[1]) : 'case-studies/sample2.jpg';

SiteSetting::create([
    'brand_name' => 'Idan Designs',
    'hero_title' => 'Creative Full Stack Developer',
    'hero_subtitle' => 'Building fast, accessible digital experiences',
    'hero_cta_text' => 'Explore Work',
    'hero_cta_url' => '#selected-work',
    'hero_image_path' => $work1,
    'chatbot_enabled' => true,
]);

$hero = Section::create(['key' => 'hero', 'title' => 'Hero', 'enabled' => true, 'sort_order' => 1]);
$intro = Section::create([
    'key' => 'introduction',
    'title' => 'Crafting Digital Systems',
    'subtitle' => 'About Me',
    'enabled' => true,
    'sort_order' => 2,
    'data' => [
        'intro_description' => "I build modern web systems with deep attention to typography, performance, and accessibility.\nBridging engineering precision with fluid aesthetics.",
        'intro_image' => $case1,
    ]
]);

$workSec = Section::create([
    'key' => 'selected_work',
    'title' => 'Selected Works',
    'subtitle' => 'Featured Projects',
    'enabled' => true,
    'sort_order' => 3,
    'layout' => ['columns' => 2, 'gap' => '4', 'padding' => 'py-40', 'full_width' => false]
]);

Work::create(['section_id' => $workSec->id, 'title' => 'Fintech Analytics Cloud', 'image_path' => $work1, 'tags' => ['Laravel', 'Vue', 'Tailwind'], 'enabled' => true, 'sort_order' => 1]);
Work::create(['section_id' => $workSec->id, 'title' => 'Luxury E-Commerce', 'image_path' => $work2, 'tags' => ['Next.js', 'Stripe'], 'enabled' => true, 'sort_order' => 2]);
Work::create(['section_id' => $workSec->id, 'title' => 'AI Prompt Studio', 'image_path' => $work1, 'tags' => ['Python', 'FastAPI'], 'enabled' => true, 'sort_order' => 3]);
Work::create(['section_id' => $workSec->id, 'title' => 'Design System Tokens', 'image_path' => $work2, 'tags' => ['TypeScript'], 'enabled' => true, 'sort_order' => 4]);

$caseSec = Section::create([
    'key' => 'case_studies',
    'title' => 'Case Studies',
    'subtitle' => 'Deep Dives',
    'enabled' => true,
    'sort_order' => 4,
]);

CaseStudy::create(['section_id' => $caseSec->id, 'title' => 'Scaling Financial Infrastructure', 'caption' => 'A sub-50ms trading execution engine with resilient failover.', 'image_path' => $case1, 'tags' => ['Architecture', 'Kafka', 'Go'], 'enabled' => true, 'sort_order' => 1]);
CaseStudy::create(['section_id' => $caseSec->id, 'title' => 'Interactive 3D Showroom', 'caption' => 'WebXR experience with real-time ray-traced materials.', 'image_path' => $case2, 'tags' => ['Three.js', 'WebGL'], 'enabled' => true, 'sort_order' => 2]);

$stackSec = Section::create([
    'key' => 'stack3d',
    'title' => 'Technology Stack',
    'subtitle' => 'Interactive Cube',
    'enabled' => true,
    'sort_order' => 5,
]);

TechStack::create(['section_id' => $stackSec->id, 'name' => 'Laravel 12', 'category' => 'Backend', 'enabled' => true, 'sort_order' => 1]);
TechStack::create(['section_id' => $stackSec->id, 'name' => 'Tailwind CSS', 'category' => 'Frontend', 'enabled' => true, 'sort_order' => 2]);
TechStack::create(['section_id' => $stackSec->id, 'name' => 'GSAP & Lenis', 'category' => 'Motion', 'enabled' => true, 'sort_order' => 3]);

$timelineSec = Section::create([
    'key' => 'timeline',
    'title' => 'Career Journey',
    'subtitle' => 'Milestones',
    'enabled' => true,
    'sort_order' => 6,
]);

TimelineEvent::create(['section_id' => $timelineSec->id, 'year' => '2026', 'title' => 'Principal Architect', 'description' => 'Leading high performance distributed architectures.', 'enabled' => true, 'sort_order' => 1]);
TimelineEvent::create(['section_id' => $timelineSec->id, 'year' => '2024', 'title' => 'Senior Full Stack Engineer', 'description' => 'Specializing in reactive microfrontends and design systems.', 'enabled' => true, 'sort_order' => 2]);

echo "TEST_DATA_SEEDED_OK target=" . basename($resolvedTestDb) . " sections=" . Section::count() . " works=" . Work::count() . "\n";
