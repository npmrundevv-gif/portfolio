<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\SiteSetting;
use App\Models\Section;

class PortfolioController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::first() ?? new SiteSetting([
            'brand_name' => 'Portfolio',
            'hero_title' => 'Cinematic Portfolio',
            'hero_cta_text' => 'VIEW PROJECTS',
            'hero_cta_url' => '#selected-work',
            'chatbot_enabled' => true,
        ]);

        $sections = Section::with([
            'caseStudies' => fn($q) => $q->where('enabled', true),
            'works' => fn($q) => $q->where('enabled', true),
            'testimonials' => fn($q) => $q->where('enabled', true),
            'stats' => fn($q) => $q->where('enabled', true),
            'timelineEvents' => fn($q) => $q->where('enabled', true),
        ])
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->get();

        return view('portfolio', compact('settings', 'sections'));
    }
}
