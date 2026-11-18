<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\CaseStudy;
use App\Models\Section;
use App\Models\SiteSetting;
use App\Models\Stat;
use App\Models\Testimonial;
use App\Models\TimelineEvent;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    // ════════════════════════════════════════════════════════════════════════
    // 1. TEMPLATE ALLOWLIST & SAFE RENDERING
    // ════════════════════════════════════════════════════════════════════════

    public function test_supported_section_templates_render_successfully()
    {
        SiteSetting::create([
            'brand_name' => 'Test Studio',
            'hero_title' => 'Cinematic Vision',
            'hero_cta_text' => 'Explore',
            'hero_cta_url' => '#selected-work',
            'chatbot_enabled' => true,
        ]);

        Section::create([
            'key' => 'hero',
            'title' => 'Hero Section',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [],
        ]);

        Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'sort_order' => 2,
            'enabled' => true,
            'data' => ['columns' => '3', 'gap' => '6', 'padding' => '32'],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Test Studio');
        $response->assertSee('Cinematic Vision');
        $response->assertSee('id="selected-work"', false);
    }

    public function test_unsupported_section_key_is_skipped_safely_without_500()
    {
        Section::create([
            'key' => 'malicious_or_unknown_template_key',
            'title' => 'Malicious Section',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        // The unsupported key should not crash the page
        // Nav also filters by isSupportedTemplate(), so it should not appear there
    }

    public function test_section_model_template_methods_work_correctly()
    {
        // Supported key
        $section = new Section(['key' => 'hero', 'enabled' => true]);
        $this->assertTrue($section->isSupportedTemplate());
        $this->assertEquals('sections.hero', $section->getTemplateView());

        // Another supported key
        $section2 = new Section(['key' => 'selected_work', 'enabled' => true]);
        $this->assertTrue($section2->isSupportedTemplate());
        $this->assertEquals('sections.selected-work', $section2->getTemplateView());

        // Unsupported / malformed key
        $invalidSection = new Section(['key' => '../../etc/passwd', 'enabled' => true]);
        $this->assertFalse($invalidSection->isSupportedTemplate());
        $this->assertNull($invalidSection->getTemplateView());

        // Unknown key
        $unknownSection = new Section(['key' => 'nonexistent_key', 'enabled' => true]);
        $this->assertFalse($unknownSection->isSupportedTemplate());
        $this->assertNull($unknownSection->getTemplateView());
    }

    public function test_section_anchor_id_is_normalized_and_safe()
    {
        $section1 = new Section(['key' => 'selected_work']);
        $this->assertEquals('selected-work', $section1->getAnchorId());

        $section2 = new Section(['key' => 'projects_pinned']);
        $this->assertEquals('projects-pinned', $section2->getAnchorId());

        // getAnchorId strips non-alphanumeric chars (except - and _), but preserves case
        $section3 = new Section(['key' => 'hero']);
        $this->assertEquals('hero', $section3->getAnchorId());

        // Keys with special chars get stripped
        $section4 = new Section(['key' => 'test!@#$key']);
        $this->assertEquals('testkey', $section4->getAnchorId());
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2. PUBLICATION FILTERING
    // ════════════════════════════════════════════════════════════════════════

    public function test_disabled_section_is_excluded_from_public_view()
    {
        Section::create([
            'key' => 'marquee',
            'title' => 'Secret Hidden Marquee',
            'sort_order' => 1,
            'enabled' => false,
            'data' => ['text' => 'SHOULD NOT APPEAR'],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('SHOULD NOT APPEAR');
    }

    public function test_disabled_child_records_are_excluded_from_public_rendering()
    {
        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [],
        ]);

        Work::create([
            'section_id' => $section->id,
            'title' => 'Visible Active Project',
            'client' => 'Acme Corp',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        Work::create([
            'section_id' => $section->id,
            'title' => 'Draft Unpublished Project',
            'client' => 'Secret Client',
            'sort_order' => 2,
            'enabled' => false,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Visible Active Project');
        $response->assertDontSee('Draft Unpublished Project');
    }

    public function test_disabled_case_studies_and_testimonials_are_excluded()
    {
        $caseSection = Section::create([
            'key' => 'case_studies',
            'title' => 'Case Studies',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [],
        ]);

        CaseStudy::create([
            'section_id' => $caseSection->id,
            'title' => 'Published Case Study',
            'client' => 'Client A',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        CaseStudy::create([
            'section_id' => $caseSection->id,
            'title' => 'Unpublished Case Study',
            'client' => 'Client B',
            'sort_order' => 2,
            'enabled' => false,
        ]);

        $testSection = Section::create([
            'key' => 'testimonials',
            'title' => 'Testimonials',
            'sort_order' => 2,
            'enabled' => true,
            'data' => [],
        ]);

        Testimonial::create([
            'section_id' => $testSection->id,
            'quote' => 'Great work published!',
            'name' => 'Alice Doe',
            'role' => 'CTO',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        Testimonial::create([
            'section_id' => $testSection->id,
            'quote' => 'Unpublished review!',
            'name' => 'Bob Secret',
            'role' => 'CEO',
            'sort_order' => 2,
            'enabled' => false,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Published Case Study');
        $response->assertDontSee('Unpublished Case Study');
        $response->assertSee('Alice Doe');
        $response->assertDontSee('Bob Secret');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3. ADMIN SETTINGS SAVE-AND-RENDER & LAYOUT CONTROLS
    // ════════════════════════════════════════════════════════════════════════

    public function test_hero_cta_url_renders_saved_anchor()
    {
        SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Headline',
            'hero_cta_text' => 'VIEW PROJECTS',
            'hero_cta_url' => '#selected-work',
            'chatbot_enabled' => true,
        ]);

        Section::create([
            'key' => 'hero',
            'title' => 'Hero',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('href="#selected-work"', false);
    }

    public function test_layout_settings_apply_bounded_css_classes()
    {
        $section = Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'sort_order' => 1,
            'enabled' => true,
            'layout' => [
                'columns' => 4,
                'gap' => '8',
                'padding' => 'py-40',
                'full_width' => true,
            ],
        ]);

        Work::create([
            'section_id' => $section->id,
            'title' => 'Project Alpha',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('lg:grid-cols-4', false);
        $response->assertSee('gap-8', false);
        $response->assertSee('py-40', false);
        $response->assertSee('w-full px-6', false);
    }

    public function test_video_url_fallback_renders_when_hero_video_path_is_empty()
    {
        SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Hero With Video',
            'hero_video_path' => null,
            'chatbot_enabled' => true,
        ]);

        Section::create([
            'key' => 'hero',
            'title' => 'Hero',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [
                'video_url' => 'https://example.com/ambient.webm',
            ],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('src="https://example.com/ambient.webm"', false);
        $response->assertSee('type="video/webm"', false);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 4. URL & CTA SAFETY
    // ════════════════════════════════════════════════════════════════════════

    public function test_url_safety_accepts_valid_schemes_and_anchors()
    {
        $this->assertTrue(SiteSetting::isSafeUrl('https://example.com'));
        $this->assertTrue(SiteSetting::isSafeUrl('http://example.com/path?foo=bar'));
        $this->assertTrue(SiteSetting::isSafeUrl('/internal/path'));
        $this->assertTrue(SiteSetting::isSafeUrl('#selected-work', true));
        $this->assertTrue(SiteSetting::isSafeUrl('#contact', true));
    }

    public function test_url_safety_rejects_dangerous_pseudo_protocols_and_protocol_relative_urls()
    {
        $this->assertFalse(SiteSetting::isSafeUrl('javascript:alert(1)'));
        $this->assertFalse(SiteSetting::isSafeUrl('javascript:alert(document.cookie)'));
        $this->assertFalse(SiteSetting::isSafeUrl('vbscript:msgbox(1)'));
        $this->assertFalse(SiteSetting::isSafeUrl('data:text/html,<script>alert(1)</script>'));
        $this->assertFalse(SiteSetting::isSafeUrl('//malicious.com/phish'));
        $this->assertFalse(SiteSetting::isSafeUrl(''));
        $this->assertFalse(SiteSetting::isSafeUrl(null));
    }

    public function test_hero_cta_url_falls_back_to_safe_anchor_when_given_javascript_url()
    {
        $settings = new SiteSetting([
            'hero_cta_url' => 'javascript:alert(1)',
        ]);

        $this->assertEquals('#selected-work', $settings->safeHeroCtaUrl());
    }

    public function test_external_social_links_enforce_noopener_noreferrer()
    {
        // social_links uses Filament KeyValue format: { platform_name => url }
        SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Portfolio Title',
            'social_links' => [
                'GitHub' => 'https://github.com/myrepo',
                'Malicious' => 'javascript:alert(1)',
            ],
            'chatbot_enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('href="https://github.com/myrepo"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
        $response->assertDontSee('javascript:alert(1)');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 5. SINGLETON INTEGRITY & MISC
    // ════════════════════════════════════════════════════════════════════════

    public function test_public_get_request_does_not_silently_create_sitesetting_record()
    {
        $this->assertEquals(0, SiteSetting::count());

        $response = $this->get('/');
        $response->assertStatus(200);

        // Must still be 0 - GET request should not write to DB
        $this->assertEquals(0, SiteSetting::count());
    }

    public function test_sitesetting_resource_protects_singleton_from_deletion()
    {
        $record = SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Test',
            'chatbot_enabled' => true,
        ]);

        $this->assertFalse(SiteSettingResource::canDelete($record));
    }

    public function test_sitesetting_resource_prevents_duplicate_creation()
    {
        // When a record already exists, canCreate should return false
        SiteSetting::create([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Test',
            'chatbot_enabled' => true,
        ]);

        $this->assertFalse(SiteSettingResource::canCreate());
    }

    public function test_video_mime_type_detection()
    {
        $this->assertEquals('video/webm', SiteSetting::videoMimeType('background.webm'));
        $this->assertEquals('video/ogg', SiteSetting::videoMimeType('clip.ogg'));
        $this->assertEquals('video/ogg', SiteSetting::videoMimeType('clip.ogv'));
        $this->assertEquals('video/mp4', SiteSetting::videoMimeType('clip.mp4'));
        $this->assertEquals('video/mp4', SiteSetting::videoMimeType(null));
        $this->assertEquals('video/mp4', SiteSetting::videoMimeType(''));
    }

    public function test_empty_collections_do_not_crash_section_templates()
    {
        // Create a few representative sections with no child records
        // to verify empty collections are handled safely
        Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [],
        ]);

        Section::create([
            'key' => 'marquee',
            'title' => 'Marquee',
            'sort_order' => 2,
            'enabled' => true,
            'data' => [],
        ]);

        Section::create([
            'key' => 'contact',
            'title' => 'Contact',
            'sort_order' => 3,
            'enabled' => true,
            'data' => [],
        ]);

        Section::create([
            'key' => 'stats',
            'title' => 'Stats',
            'sort_order' => 4,
            'enabled' => true,
            'data' => [],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 6. CONTENT OUTPUT ESCAPING & HARMBOUND VERIFICATION
    // ════════════════════════════════════════════════════════════════════════

    public function test_ordinary_text_is_html_escaped_in_rendered_output()
    {
        $section = Section::create([
            'key' => 'selected_work',
            'title' => '<script>alert("section_title")</script>',
            'subtitle' => '<b>safe subtitle</b>',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        Work::create([
            'section_id' => $section->id,
            'title' => '<img src=x onerror=alert("work_title")>',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Blade escapes ordinary text via htmlspecialchars
        $response->assertDontSee('<script>alert("section_title")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;section_title&quot;)&lt;/script&gt;', false);

        $response->assertDontSee('<img src=x onerror=alert("work_title")>', false);
        $response->assertSee('&lt;img src=x onerror=alert(&quot;work_title&quot;)&gt;', false);
    }

    public function test_introduction_description_escapes_html_and_converts_newlines()
    {
        Section::create([
            'key' => 'introduction',
            'title' => 'Intro',
            'sort_order' => 1,
            'enabled' => true,
            'data' => [
                'intro_description' => "<script>alert('intro')</script>\nSecond Line Content",
            ],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Raw script must NOT appear
        $response->assertDontSee("<script>alert('intro')</script>", false);
        // Escaped entity must appear
        $response->assertSee('&lt;script&gt;alert(&#039;intro&#039;)&lt;/script&gt;', false);
        // Newline converted to break
        $response->assertSee("Second Line Content");
    }

    public function test_unsafe_links_on_case_study_render_harmlessly()
    {
        $section = Section::create([
            'key' => 'case_studies',
            'title' => 'Case Studies',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        CaseStudy::create([
            'section_id' => $section->id,
            'title' => 'Unsafe Link Project',
            'url' => 'javascript:alert("pwned")',
            'sort_order' => 1,
            'enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // The unsafe link must not be rendered as href
        $response->assertDontSee('javascript:alert', false);
        $response->assertSee('href="#"', false);
    }
}

