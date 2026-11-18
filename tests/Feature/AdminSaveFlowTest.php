<?php

namespace Tests\Feature;

use App\Filament\Resources\Sections\Pages\CreateSection;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Filament\Resources\Works\Pages\CreateWork;
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

class AdminSaveFlowTest extends TestCase
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

    // ════════════════════════════════════════════════════════════════════════
    // 1. REAL ADMIN SAVE FLOWS: OPEN → CHANGE → SAVE → RELOAD → PUBLIC OUTPUT
    // ════════════════════════════════════════════════════════════════════════

    public function test_hero_cta_destination_admin_save_flow_affects_public_output()
    {
        $this->actingAs($this->adminUser);

        $setting = SiteSetting::create([
            'brand_name' => 'Initial Brand',
            'hero_title' => 'Initial Title',
            'hero_cta_text' => 'INITIAL CTA',
            'hero_cta_url' => '#initial-anchor',
            'chatbot_enabled' => true,
        ]);

        Section::create([
            'key' => 'hero',
            'title' => 'Hero',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        // 1. Verify initial public output
        $this->get('/')->assertSee('href="#initial-anchor"', false);

        // 2. Open form → change value → save
        Livewire::test(ManageSiteSettings::class)
            ->callTableAction('edit', $setting, data: [
                'brand_name' => 'Updated Brand',
                'hero_title' => 'Updated Title',
                'hero_cta_text' => 'UPDATED CTA',
                'hero_cta_url' => '#updated-destination',
                'chatbot_enabled' => true,
            ])
            ->assertHasNoTableActionErrors();

        // 3. Reload from persistence
        $setting->refresh();
        $this->assertEquals('#updated-destination', $setting->hero_cta_url);
        $this->assertEquals('Updated Brand', $setting->brand_name);

        // 4. Request public page and confirm saved value affects output
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('href="#updated-destination"', false);
        $response->assertSee('Updated Brand');
        $response->assertDontSee('#initial-anchor');
    }

    public function test_section_publication_status_admin_save_flow_affects_public_output()
    {
        $this->actingAs($this->adminUser);

        $section = Section::create([
            'key' => 'marquee',
            'title' => 'Live Marquee',
            'sort_order' => 1,
            'enabled' => true,
            'data' => ['text' => 'ACTIVE RUNNING MARQUEE'],
        ]);

        // 1. Initially public page sees the marquee
        $this->get('/')->assertSee('ACTIVE RUNNING MARQUEE');

        // 2. Open form → change enabled to false → save
        Livewire::test(EditSection::class, ['record' => $section->id])
            ->fillForm([
                'title' => 'Live Marquee',
                'enabled' => false,
                'sort_order' => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // 3. Reload from persistence
        $section->refresh();
        $this->assertFalse($section->enabled);

        // 4. Request public page and confirm disabled section is excluded
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertDontSee('ACTIVE RUNNING MARQUEE');
    }

    public function test_layout_settings_admin_save_flow_affects_public_output()
    {
        $this->actingAs($this->adminUser);

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Featured Works',
            'sort_order' => 1,
            'enabled' => true,
            'layout' => [
                'columns' => 2,
                'gap' => '1',
                'padding' => 'py-20',
                'full_width' => false,
            ],
        ]);

        Work::create([
            'section_id' => $section->id,
            'title' => 'Work Item 1',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        // Initial public classes
        $this->get('/')
            ->assertSee('lg:grid-cols-2', false)
            ->assertSee('gap-1', false)
            ->assertSee('py-20', false);

        // Open EditSection form → change layout settings → save
        Livewire::test(EditSection::class, ['record' => $section->id])
            ->fillForm([
                'title' => 'Featured Works',
                'enabled' => true,
                'sort_order' => 1,
                'layout' => [
                    'columns' => 4,
                    'gap' => '8',
                    'padding' => 'py-60',
                    'full_width' => true,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Reload persistence
        $section->refresh();
        $this->assertEquals(4, $section->layout['columns']);
        $this->assertEquals('8', $section->layout['gap']);
        $this->assertEquals('py-60', $section->layout['padding']);
        $this->assertTrue((bool)$section->layout['full_width']);

        // Public page reflects the updated computed CSS classes
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('lg:grid-cols-4', false);
        $response->assertSee('gap-8', false);
        $response->assertSee('py-60', false);
        $response->assertSee('w-full px-6', false);
    }

    public function test_uploaded_media_admin_save_flow_affects_public_output()
    {
        $this->actingAs($this->adminUser);
        Storage::fake('public');

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $fakeImage = $this->createSyntheticPng('project_screenshot.png');

        // Exercise real Filament CreateWork form save flow with file upload
        Livewire::test(CreateWork::class)
            ->fillForm([
                'section_id' => $section->id,
                'title' => 'Uploaded Portfolio Project',
                'image_path' => $fakeImage,
                'sort_order' => 1,
                'enabled' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $work = Work::where('title', 'Uploaded Portfolio Project')->firstOrFail();
        $this->assertNotNull($work->image_path);
        Storage::disk('public')->assertExists($work->image_path);

        // Public page renders the saved image URL
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee(Storage::disk('public')->url($work->image_path), false);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2. VALIDATION ENFORCEMENT & UNSAFE VALUE REJECTION THROUGH ADMIN PATH
    // ════════════════════════════════════════════════════════════════════════

    public function test_unsupported_section_key_is_rejected_by_admin_form_validation()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(CreateSection::class)
            ->fillForm([
                'key' => 'unsupported_malicious_key',
                'title' => 'Injected Section',
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['key' => 'in']);

        $this->assertEquals(0, Section::where('key', 'unsupported_malicious_key')->count());
    }

    public function test_unsafe_url_in_work_is_rejected_by_admin_form_validation()
    {
        $this->actingAs($this->adminUser);

        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Works Section',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        Livewire::test(CreateWork::class)
            ->fillForm([
                'section_id' => $section->id,
                'title' => 'Project with Bad URL',
                'url' => 'javascript:alert(1)',
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);

        $this->assertEquals(0, Work::where('title', 'Project with Bad URL')->count());
    }

    public function test_unsafe_url_in_hero_cta_is_rejected_by_admin_form_validation()
    {
        $this->actingAs($this->adminUser);

        $setting = SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Title',
            'hero_cta_url' => '#selected-work',
            'chatbot_enabled' => true,
        ]);

        Livewire::test(ManageSiteSettings::class)
            ->callTableAction('edit', $setting, data: [
                'brand_name' => 'Portfolio',
                'hero_cta_url' => 'javascript:alert("XSS")',
            ])
            ->assertHasTableActionErrors(['hero_cta_url']);

        // Crucially: the dangerous URL must not have been persisted
        $setting->refresh();
        $this->assertEquals('#selected-work', $setting->hero_cta_url);
    }

    public function test_legacy_invalid_url_in_database_renders_safely_on_public_page()
    {
        // Simulate legacy unvalidated row existing directly in DB
        SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Hero Title',
            'hero_cta_text' => 'EXPLORE',
            'hero_cta_url' => 'javascript:alert(document.cookie)',
            'chatbot_enabled' => true,
        ]);

        Section::create([
            'key' => 'hero',
            'title' => 'Hero',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        // The dangerous javascript: url must NOT be rendered; safe fallback #selected-work is used instead
        $response->assertDontSee('javascript:alert');
        $response->assertSee('href="#selected-work"', false);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3. SINGLETON ACTION-LEVEL PROTECTION
    // ════════════════════════════════════════════════════════════════════════

    public function test_sitesetting_table_does_not_expose_delete_or_bulk_delete_actions()
    {
        $this->actingAs($this->adminUser);

        $setting = SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Title',
            'chatbot_enabled' => true,
        ]);

        $test = Livewire::test(ManageSiteSettings::class);

        // Edit action is available
        $test->assertTableActionVisible('edit', $setting);

        // Delete action is NOT registered on the table records
        $test->assertTableActionDoesNotExist('delete');

        // Delete bulk action is NOT registered on the table toolbar
        $test->assertTableBulkActionDoesNotExist('delete');
    }

    public function test_sitesetting_create_header_action_is_hidden_when_record_exists()
    {
        $this->actingAs($this->adminUser);

        SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Title',
            'chatbot_enabled' => true,
        ]);

        // When record exists, create action is hidden
        Livewire::test(ManageSiteSettings::class)
            ->assertActionHidden('create');
    }
}
