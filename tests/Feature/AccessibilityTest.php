<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use App\Models\Section;
use App\Models\SiteSetting;
use App\Models\TechStack;
use App\Models\Testimonial;
use App\Models\TimelineEvent;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::create([
            'brand_name' => 'Accessible Dev',
            'hero_title' => 'Design & Code',
            'chatbot_enabled' => true,
        ]);
    }

    public function test_page_has_no_js_progressive_enhancement_and_inline_detection(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('<html lang="en" class="no-js">', false);
        $response->assertSee("document.documentElement.classList.replace('no-js', 'js')", false);
    }

    public function test_page_has_accessible_skip_link_targeting_main_landmark(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('href="#main-content"', false);
        $response->assertSee('Skip to content', false);
        $response->assertSee('<main id="main-content" tabindex="-1"', false);
    }

    public function test_navigation_and_overlay_have_proper_accessibility_semantics(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('aria-label="Main Navigation"', false);
        $response->assertSee('id="nav-toggle"', false);
        $response->assertSee('aria-expanded="false"', false);
        $response->assertSee('aria-controls="nav-overlay"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-label="Navigation Menu"', false);
        $response->assertSee('aria-label="Close navigation menu"', false);
    }

    public function test_chatbot_widget_has_dialog_semantics_and_live_announcer(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('id="chat-toggle"', false);
        $response->assertSee('aria-label="Open AI Assistant"', false);
        $response->assertSee('aria-controls="chat-window"', false);
        $response->assertSee('id="chat-window"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-label="AI Portfolio Assistant"', false);
        $response->assertSee('id="chat-close"', false);
        $response->assertSee('aria-label="Close chat window"', false);
        $response->assertSee('id="chat-announcer"', false);
        $response->assertSee('aria-live="polite"', false);
        $response->assertSee('role="log"', false);
        $response->assertSee('aria-label="Conversation messages"', false);
        $response->assertSee('for="chat-input"', false);
        $response->assertSee('aria-label="Send message"', false);
        $response->assertSee('<noscript>', false);
    }

    public function test_footer_has_contentinfo_landmark(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('role="contentinfo"', false);
        $response->assertSee('aria-label="Back to top"', false);
    }

    public function test_sections_have_accessible_controls_and_lazy_loading(): void
    {
        $section = Section::create([
            'key' => 'stack3d',
            'title' => 'Tech Stack',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        TechStack::create([
            'section_id' => $section->id,
            'name' => 'Laravel',
            'category' => 'Backend',
            'color' => '#FF2D20',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $selectedWorkSection = Section::create([
            'key' => 'selected_work',
            'title' => 'Featured Works',
            'enabled' => true,
            'sort_order' => 2,
        ]);

        Work::create([
            'section_id' => $selectedWorkSection->id,
            'title' => 'E-Commerce Platform',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        // Tech stack interactive button semantics
        $response->assertSee('role="button"', false);
        $response->assertSee('tabindex="0"', false);
        $response->assertSee('aria-label="Select Laravel technology"', false);
        // Selected work heading hierarchy (h3 under h2)
        $response->assertSee('Featured Works</h2>', false);
        $response->assertSee('E-Commerce Platform</h3>', false);
    }

    public function test_marquee_ticker_provides_screen_reader_summary_and_hides_duplications(): void
    {
        Section::create([
            'key' => 'marquee',
            'title' => 'Marquee',
            'enabled' => true,
            'sort_order' => 3,
            'data' => [
                'text' => 'AVAILABLE FOR HIRE • CREATIVE DEV • FULL STACK',
            ],
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('aria-label="Highlights Ticker"', false);
        $response->assertSee('class="sr-only">AVAILABLE FOR HIRE - CREATIVE DEV - FULL STACK</span>', false);
        $response->assertSee('id="marquee-text" aria-hidden="true"', false);
    }

    public function test_progressive_enhancement_navigation_fallback_and_inert_overlay(): void
    {
        Section::create([
            'key' => 'selected_work',
            'title' => 'Selected Work',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        // Fallback navigation exists for non-JS/blocked bundle scenarios
        $response->assertSee('id="nav-fallback-links"', false);
        $response->assertSee('href="#selected-work"', false);
        // Overlay is inert initially when closed
        $response->assertSee('id="nav-overlay"', false);
        $response->assertSee('inert', false);
    }

    public function test_media_loading_strategy_and_content_visibility_without_js(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('case-studies/test.jpg', 'fake content');

        $introSection = Section::create([
            'key' => 'introduction',
            'title' => 'Introduction',
            'enabled' => true,
            'sort_order' => 1,
            'data' => [
                'intro_description' => 'Creative Full Stack Developer',
            ],
        ]);

        $caseSection = Section::create([
            'key' => 'case_studies',
            'title' => 'Case Studies',
            'enabled' => true,
            'sort_order' => 2,
        ]);

        CaseStudy::create([
            'section_id' => $caseSection->id,
            'title' => 'Fintech Dashboard',
            'caption' => 'High throughput analytics platform',
            'image_path' => 'case-studies/test.jpg',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        // Profile image must not have inline transform:scale(0) or opacity:0
        $response->assertDontSee('transform: scale(0); opacity: 0;', false);
        // Case text must not be pushed out of container with translate-y-[110%] in markup
        $response->assertDontSee('data-case-text translate-y-[110%]', false);
        // Case image curtain must not have hardcoded opacity-0 blur-lg in markup
        $response->assertDontSee('data-curtain-img loading="lazy" decoding="async" class="w-full h-full object-cover grayscale group-hover:grayscale-0 scale-125 transition-all duration-1000 opacity-0 blur-lg', false);
        // First case study uses fetchpriority="high" instead of lazy
        $response->assertSee('fetchpriority="high"', false);
    }
}
