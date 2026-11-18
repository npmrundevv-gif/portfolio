<section id="{{ $section->getAnchorId() }}"
    class="introduction-section relative min-h-[70vh] flex flex-col justify-center bg-black">
    {{-- Background Container --}}
    <div class="absolute inset-0 z-0">
        @php
            $bgType = $section->data['bg_type'] ?? 'default';
            $bgImage = $section->data['bg_image'] ?? null;
            $bgVideo = $section->data['bg_video'] ?? null;
            $hasBgImage = !empty($bgImage) && Storage::disk('public')->exists($bgImage);
            $hasBgVideo = !empty($bgVideo) && Storage::disk('public')->exists($bgVideo);
        @endphp

        @if($bgType === 'image' && $hasBgImage)
            <img src="{{ Storage::disk('public')->url($bgImage) }}" class="w-full h-full object-cover" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"></div>
        @elseif($bgType === 'video' && $hasBgVideo)
            <video autoplay muted loop playsinline preload="metadata" aria-hidden="true" class="w-full h-full object-cover">
                <source src="{{ Storage::disk('public')->url($bgVideo) }}" type="{{ \App\Models\SiteSetting::videoMimeType($bgVideo) }}">
            </video>
            <div class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"></div>
        @else
            {{-- Default Split Background Grid --}}
            <div class="grid grid-rows-[1fr_1fr] h-full w-full">
                <div class="bg-[#111] border-b border-white/5"></div>
                <div class="bg-black"></div>
            </div>
        @endif
    </div>

    {{-- Content Container --}}
    <div class="relative z-10 w-full h-full flex flex-col items-center">
        {{-- Profile Image --}}
        <div class="relative mb-12 {{ $bgType === 'default' ? 'mt-[-20px]' : 'mt-0' }}">
            {{-- Fantastic SVG Mask Definition (Petal/Bloom Shape) --}}
            <svg width="0" height="0" class="absolute" aria-hidden="true">
                <defs>
                    <clipPath id="fantastic-mask" clipPathUnits="objectBoundingBox">
                        <path
                            d="M0.5,0 C0.58,0 0.65,0.08 0.72,0.2 C0.85,0.22 1,0.35 1,0.5 C1,0.65 0.85,0.78 0.72,0.8 C0.65,0.92 0.58,1 0.5,1 C0.42,1 0.35,0.92 0.28,0.8 C0.15,0.78 0,0.65 0,0.5 C0,0.35 0.15,0.22 0.28,0.2 C0.35,0.08 0.42,0 0.5,0 Z" />
                    </clipPath>
                </defs>
            </svg>

            <div class="relative group border-glitch reveal-mask-container" data-profile-reveal>
                <div class="w-48 h-48 md:w-64 md:h-64 rounded-full border-[10px] border-[#0a0a0a] overflow-hidden shadow-[0_0_50px_rgba(74,222,128,0.15)] transition-all duration-700 group-hover:scale-[1.03] group-hover:shadow-[0_0_70px_rgba(74,222,128,0.25)] profile-image-reveal"
                    style="clip-path: url(#fantastic-mask);">
                    @php
                        $introImage = $section->data['intro_image'] ?? null;
                        $hasIntroImage = !empty($introImage) && Storage::disk('public')->exists($introImage);
                    @endphp
                    @if($hasIntroImage)
                        <img src="{{ Storage::disk('public')->url($introImage) }}" alt="{{ $section->title }}"
                            class="w-full h-full object-cover" loading="lazy" decoding="async">
                    @else
                        <div class="w-full h-full bg-[#1a1a1a] flex items-center justify-center">
                            <svg class="w-20 h-20 text-white/10" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.33-8 4v2h16v-2c0-2.67-5.33-4-8-4z" />
                            </svg>
                        </div>
                    @endif
                </div>
                {{-- Dynamic Green accent ring behind on hover --}}
                <div
                    class="absolute inset-0 rounded-full border-2 border-green-400/30 -m-4 scale-90 opacity-0 group-hover:scale-110 group-hover:opacity-100 transition-all duration-700 blur-[2px]">
                </div>
            </div>
        </div>

        {{-- Text Content --}}
        <div class="px-6 max-w-4xl mx-auto text-center space-y-10 group-content">
            <h2 class="text-5xl md:text-8xl font-black tracking-tighter uppercase text-white leading-none"
                data-title-reveal>
                {{ $section->title }}
            </h2>

            <div class="max-w-xl mx-auto">
                <p class="text-lg md:text-2xl text-white/70 leading-[1.6] font-light tracking-tight intro-description"
                    data-word-proximity data-desc-reveal>
                    {!! nl2br(e($section->data['intro_description'] ?? '')) !!}
                </p>
            </div>

            @if(isset($section->data['intro_attribution']))
                <div class="pt-4">
                    <span
                        class="inline-block px-5 py-2 bg-green-400/5 border border-green-400/20 rounded-full text-[10px] font-bold tracking-[0.3em] text-green-400 uppercase">
                        Images via {{ $section->data['intro_attribution'] }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    <style>
        .introduction-section {
            background-color: black;
        }
    </style>
</section>