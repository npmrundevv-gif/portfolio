<section id="{{ $section->getAnchorId() }}"
    class="h-screen w-full flex flex-col justify-center items-center text-center p-6 relative isolate overflow-hidden">
    <!-- Background elements -->
    <div class="absolute inset-0 z-[1] bg-gradient-to-b from-black/30 via-black/50 to-black pointer-events-none"></div>
    @php
        $heroVideoPath = $settings->hero_video_path;
        $heroVideoUrl = $section->data['video_url'] ?? null;
        $hasVideoPath = !empty($heroVideoPath) && Storage::disk('public')->exists($heroVideoPath);
        $hasVideoUrl = !empty($heroVideoUrl) && \App\Models\SiteSetting::isSafeUrl($heroVideoUrl);
        $hasHeroImage = !empty($settings->hero_image_path) && Storage::disk('public')->exists($settings->hero_image_path);
        $ctaUrl = $settings->safeHeroCtaUrl();
        $isCtaExternal = str_starts_with($ctaUrl, 'http://') || str_starts_with($ctaUrl, 'https://');
    @endphp

    @if($hasVideoPath)
        <video autoplay muted loop playsinline preload="metadata"
            class="absolute inset-0 z-0 w-full h-full object-cover opacity-50 scale-110 transition-all duration-1000"
            id="hero-bg">
            <source src="{{ Storage::disk('public')->url($heroVideoPath) }}" type="{{ \App\Models\SiteSetting::videoMimeType($heroVideoPath) }}">
        </video>
    @elseif($hasVideoUrl)
        <video autoplay muted loop playsinline preload="metadata"
            class="absolute inset-0 z-0 w-full h-full object-cover opacity-50 scale-110 transition-all duration-1000"
            id="hero-bg">
            <source src="{{ $heroVideoUrl }}" type="{{ \App\Models\SiteSetting::videoMimeType($heroVideoUrl) }}">
        </video>
    @elseif($hasHeroImage)
        <img src="{{ Storage::disk('public')->url($settings->hero_image_path) }}"
            alt="{{ $settings->brand_name ?? 'Hero Background' }}"
            fetchpriority="high"
            decoding="async"
            class="absolute inset-0 z-0 w-full h-full object-cover opacity-50 scale-110 transition-all duration-1000"
            id="hero-bg">
    @endif

    <div class="z-10 container mx-auto" id="hero-reveal">
        <h1 class="text-[clamp(3rem,15vw,12rem)] font-black tracking-tighter leading-[0.85] uppercase split-text"
            id="hero-title">
            {{ $settings->hero_title ?? 'CRAFTING DIGITAL' }}
        </h1>
        <h2 class="text-[clamp(1rem,4vw,3rem)] font-light tracking-[0.4em] mt-8 uppercase split-text opacity-70 text-white"
            id="hero-subtitle">
            {{ $settings->hero_subtitle ?? 'YOUR JERSEY SPECIALIST' }}
        </h2>

        <div class="mt-20 overflow-hidden">
            <a href="{{ $ctaUrl }}"
                @if($isCtaExternal) target="_blank" rel="noopener noreferrer" @endif
                class="inline-block px-12 py-5 border border-white text-xs tracking-[0.5em] font-medium hover:bg-white hover:text-black focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-400 focus-visible:ring-offset-4 focus-visible:ring-offset-black transition-all duration-500 transform hover:scale-110"
                id="hero-cta">

                {{ !empty($settings->hero_cta_text) ? $settings->hero_cta_text : 'VIEW PROJECTS' }}
            </a>
        </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-10 left-1/2 -translate-x-1/2 flex flex-col items-center gap-4 opacity-70"
        id="hero-scroll">
        <span class="text-[0.6rem] tracking-[0.5em] uppercase text-green-400">Scroll</span>
        <div class="w-[1px] h-20 bg-gradient-to-b from-green-400 to-transparent origin-top" id="scroll-line"></div>
    </div>
</section>