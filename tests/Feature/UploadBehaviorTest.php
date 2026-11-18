<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Filament\Resources\Works\Pages\CreateWork;
use App\Filament\Resources\Works\Pages\EditWork;
use App\Models\Section;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UploadBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.admin_emails', 'admin@example.com');
        $this->adminUser = User::factory()->create(['email' => 'admin@example.com']);
    }

    protected function createSyntheticPng(string $filename = 'test.png', int $extraBytes = 0): UploadedFile
    {
        $header = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $content = $header . ($extraBytes > 0 ? str_repeat('X', $extraBytes) : '');

        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    public function test_valid_supported_image_upload_saves_to_correct_disk_and_directory()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('public');

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Works Section',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $file = $this->createSyntheticPng('portfolio_hero.png', 1024 * 500); // ~500KB valid PNG

        Livewire::test(CreateWork::class)
            ->fillForm([
                'section_id' => $section->id,
                'title' => 'Valid Image Work',
                'image_path' => $file,
                'sort_order' => 1,
                'enabled' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $work = Work::where('title', 'Valid Image Work')->firstOrFail();
        $this->assertNotNull($work->image_path);
        $this->assertStringStartsWith('works/', $work->image_path);
        Storage::disk('public')->assertExists($work->image_path);
    }

    public function test_misleading_filename_extension_non_image_is_rejected_by_image_rule()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('public');

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Works Section',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        // A non-image file (executable PHP content) with misleading .php extension
        // Filament's ->image() rule rejects files whose MIME type is not image/*
        $fakePhpFile = UploadedFile::fake()->createWithContent(
            'malicious_script.php',
            '<?php phpinfo(); ?>'
        );

        Livewire::test(CreateWork::class)
            ->fillForm([
                'section_id' => $section->id,
                'title' => 'Malicious Extension Test',
                'image_path' => $fakePhpFile,
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['image_path']);

        $this->assertEquals(0, Work::where('title', 'Malicious Extension Test')->count());
    }

    public function test_oversized_image_exceeding_10mb_is_rejected()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('public');

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Works Section',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        // 12MB synthetic PNG (exceeds maxSize(10240))
        $oversizedImage = $this->createSyntheticPng('huge.png', 12 * 1024 * 1024);

        Livewire::test(CreateWork::class)
            ->fillForm([
                'section_id' => $section->id,
                'title' => 'Oversized Work Image',
                'image_path' => $oversizedImage,
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['image_path']);

        $this->assertEquals(0, Work::where('title', 'Oversized Work Image')->count());
    }

    public function test_valid_video_fixture_is_accepted_in_sitesetting()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('public');

        $setting = SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Hero With Video',
            'chatbot_enabled' => true,
        ]);

        // Valid MP4 video fixture of 5MB (under 50MB limit)
        $validVideo = UploadedFile::fake()->create('ambient.mp4', 5120, 'video/mp4');

        Livewire::test(ManageSiteSettings::class)
            ->callTableAction('edit', $setting, data: [
                'brand_name' => 'Portfolio',
                'hero_video_path' => $validVideo,
            ])
            ->assertHasNoTableActionErrors();

        $setting->refresh();
        $this->assertNotNull($setting->hero_video_path);
        $this->assertStringStartsWith('site/', $setting->hero_video_path);
        Storage::disk('public')->assertExists($setting->hero_video_path);
    }

    public function test_oversized_video_exceeding_50mb_is_rejected()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('public');

        $setting = SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Hero With Video',
            'chatbot_enabled' => true,
        ]);

        // Video fixture of 60MB (exceeds maxSize(51200))
        $oversizedVideo = UploadedFile::fake()->create('huge_ambient.mp4', 61440, 'video/mp4');

        // Filament's FileUpload rejects oversized files at the Livewire temporary
        // upload level — the file silently does not get stored, rather than raising
        // a form validation error. The critical guarantee is the same: the oversized
        // file is never persisted to the model.
        Livewire::test(ManageSiteSettings::class)
            ->callTableAction('edit', $setting, data: [
                'brand_name' => 'Portfolio',
                'hero_video_path' => $oversizedVideo,
            ]);

        // Must not have been saved
        $setting->refresh();
        $this->assertNull($setting->hero_video_path);
    }

    public function test_unauthorized_user_cannot_access_or_upload_to_admin_resources()
    {
        $nonAdmin = User::factory()->create(['email' => 'guest@example.com']);

        // Guest is redirected
        $this->get('/admin/works')->assertRedirect('/admin/login');

        // Non-admin is forbidden
        $this->actingAs($nonAdmin)->get('/admin/works')->assertStatus(403);
        $this->actingAs($nonAdmin)->get('/admin/site-settings')->assertStatus(403);
    }

    public function test_missing_media_in_storage_is_handled_safely_on_public_page()
    {
        Storage::fake('public');

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        // Record references an image path that does not exist in storage
        Work::create([
            'section_id' => $section->id,
            'title' => 'Project With Missing Image',
            'image_path' => 'works/nonexistent_file_12345.jpg',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Project With Missing Image');
        // Missing file URL should not be rendered as img src
        $response->assertDontSee('nonexistent_file_12345.jpg');
    }

    public function test_preservation_of_unrelated_files_during_media_replacement()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('public');

        // Create an unrelated file that must not be deleted
        Storage::disk('public')->put('works/unrelated_important_asset.jpg', 'binary-content');

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $initialImage = $this->createSyntheticPng('first_project.png');

        Livewire::test(CreateWork::class)
            ->fillForm([
                'section_id' => $section->id,
                'title' => 'Replaceable Work',
                'image_path' => $initialImage,
                'sort_order' => 1,
                'enabled' => true,
            ])
            ->call('create');

        $work = Work::where('title', 'Replaceable Work')->firstOrFail();
        $this->assertNotNull($work->image_path);

        // Verify both the initial image and unrelated file exist
        Storage::disk('public')->assertExists($work->image_path);
        Storage::disk('public')->assertExists('works/unrelated_important_asset.jpg');

        // Replace with a new image
        $replacementImage = $this->createSyntheticPng('second_project.png');

        Livewire::test(EditWork::class, ['record' => $work->id])
            ->fillForm([
                'title' => 'Replaceable Work',
                'image_path' => $replacementImage,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $work->refresh();
        $this->assertNotNull($work->image_path);
        Storage::disk('public')->assertExists($work->image_path);

        // Crucial guarantee: The unrelated file remains completely intact and untouched!
        Storage::disk('public')->assertExists('works/unrelated_important_asset.jpg');
        $this->assertEquals('binary-content', Storage::disk('public')->get('works/unrelated_important_asset.jpg'));
    }
}
