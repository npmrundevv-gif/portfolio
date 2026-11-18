<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'key',
        'title',
        'subtitle',
        'enabled',
        'sort_order',
        'layout',
        'data',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'layout' => 'array',
        'data' => 'array',
    ];

    /**
     * Explicit allowlist mapping supported section keys to public Blade template views.
     */
    public const TEMPLATES = [
        'hero' => 'sections.hero',
        'introduction' => 'sections.introduction',
        'marquee' => 'sections.marquee',
        'case_studies' => 'sections.case-studies',
        'projects_pinned' => 'sections.projects-pinned',
        'selected_work' => 'sections.selected-work',
        'stats' => 'sections.stats',
        'stack3d' => 'sections.stack3d',
        'timeline' => 'sections.timeline',
        'testimonials' => 'sections.testimonials',
        'contact' => 'sections.contact',
    ];

    /**
     * Resolve the allowed Blade template view path for this section.
     */
    public function getTemplateView(): ?string
    {
        return self::TEMPLATES[$this->key] ?? null;
    }

    /**
     * Check if the section key maps to a supported template.
     */
    public function isSupportedTemplate(): bool
    {
        return isset(self::TEMPLATES[$this->key]);
    }

    /**
     * Get a safe, normalized HTML anchor ID for navigation.
     */
    public function getAnchorId(): string
    {
        $key = str_replace('_', '-', (string) $this->key);
        $cleaned = preg_replace('/[^a-zA-Z0-9\-_]/', '', $key);

        return $cleaned !== '' ? $cleaned : ('section-' . ($this->id ?? 'item'));
    }

    public function caseStudies()
    {
        return $this->hasMany(CaseStudy::class)->orderBy('sort_order');
    }

    public function works()
    {
        return $this->hasMany(Work::class)->orderBy('sort_order');
    }

    public function testimonials()
    {
        return $this->hasMany(Testimonial::class)->orderBy('sort_order');
    }

    public function stats()
    {
        return $this->hasMany(Stat::class)->orderBy('sort_order');
    }

    public function timelineEvents()
    {
        return $this->hasMany(TimelineEvent::class)->orderBy('sort_order');
    }
}
