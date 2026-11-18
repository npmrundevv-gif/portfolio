<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class ReleasePackagingTest extends TestCase
{
    public function test_extracted_release_can_link_and_serve_public_uploads(): void
    {
        $files = new Filesystem;
        $sandbox = sys_get_temp_dir().'/portfolio-release-'.bin2hex(random_bytes(8));
        $source = $sandbox.'/source';
        $release = $sandbox.'/release';

        try {
            foreach (['scripts', 'bootstrap', 'public/build', 'public/storage', 'storage/app/public'] as $directory) {
                $files->ensureDirectoryExists($source.'/'.$directory);
            }

            $files->copy(base_path('scripts/package-release.php'), $source.'/scripts/package-release.php');
            $files->copy(base_path('bootstrap/app.php'), $source.'/bootstrap/app.php');
            $files->put($source.'/public/build/manifest.json', '{}');
            $files->put($source.'/public/storage/source-upload.png', 'existing public upload');
            $files->put($source.'/storage/app/public/source-upload.png', 'existing storage upload');
            $files->put($source.'/public/hot', 'http://localhost:5173');
            $files->put($source.'/.env', 'APP_KEY=packaging-test-only');

            $process = new Process([PHP_BINARY, 'scripts/package-release.php', '--skip-build'], $source);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());

            $archives = $files->glob($source.'/dist/*.zip');
            $this->assertCount(1, $archives);
            $this->assertSame(
                hash_file('sha256', $archives[0]).'  '.basename($archives[0])."\n",
                $files->get($archives[0].'.sha256'),
            );

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($archives[0]));

            try {
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $this->assertFalse(str_starts_with($zip->getNameIndex($index), 'public/storage/'));
                }

                $this->assertTrue($zip->extractTo($release));
            } finally {
                $zip->close();
            }

            $this->assertFalse(file_exists($release.'/public/storage'));
            $this->assertDirectoryExists($release.'/storage/app/public');
            $this->assertFileExists($release.'/public/build/manifest.json');
            $this->assertFileDoesNotExist($release.'/storage/app/public/source-upload.png');
            $this->assertFileDoesNotExist($release.'/public/hot');
            $this->assertFileDoesNotExist($release.'/.env');

            config(['filesystems.links' => [
                $release.'/public/storage' => $release.'/storage/app/public',
            ]]);

            $this->artisan('storage:link')->assertSuccessful();
            $this->assertTrue(is_link($release.'/public/storage'));

            $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
            $files->put($release.'/storage/app/public/upload.png', $image);

            $this->assertSame($image, $files->get($release.'/public/storage/upload.png'));
        } finally {
            $files->deleteDirectory($sandbox);
        }
    }
}
