<section id="{{ $section->getAnchorId() }}" class="py-40 bg-black min-h-screen">
    <div class="container mx-auto px-6">
        <div class="mb-32">
            <h2 class="text-7xl md:text-9xl font-black tracking-tighter uppercase split-text">{{ $section->title }}</h2>
            <p class="text-green-400 tracking-widest uppercase mt-4">{{ $section->subtitle }}</p>
        </div>

        <div class="flex flex-col gap-64 md:gap-96">
            @foreach($section->caseStudies->where('enabled', true) as $case)
                <div class="case-card relative flex flex-col {{ $loop->even ? 'md:flex-row-reverse' : 'md:flex-row' }} gap-16 md:gap-32 items-center group"
                    data-case-card>

                    {{-- Massive Background Number --}}
                    <div class="absolute -top-20 {{ $loop->even ? '-right-10' : '-left-10' }} text-[15rem] md:text-[25rem] font-black text-white/[0.03] select-none pointer-events-none z-0 leading-none"
                        data-case-number aria-hidden="true">
                        {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </div>

                    {{-- Image Container with Curtain Reveal --}}
                    <div class="w-full md:w-3/5 relative overflow-hidden aspect-[16/10] bg-white/5 z-10"
                        data-curtain-container>

                        {{-- Curtain Layers --}}
                        <div
                            class="curtain-layer absolute inset-0 bg-green-400 z-30 translate-x-[-100%] will-change-transform" aria-hidden="true">
                        </div>
                        <div class="curtain-layer absolute inset-0 bg-white z-25 translate-x-[-100%] will-change-transform" aria-hidden="true">
                        </div>
                        <div
                            class="curtain-layer absolute inset-0 bg-[#111] z-20 translate-x-[-100%] will-change-transform" aria-hidden="true">
                        </div>

                        @php
                            $hasCaseImage = !empty($case->image_path) && Storage::disk('public')->exists($case->image_path);
                        @endphp
                        @if($hasCaseImage)
                            <img src="{{ Storage::disk('public')->url($case->image_path) }}" alt="{{ $case->title }}"
                                class="w-full h-full object-cover grayscale group-hover:grayscale-0 scale-125 transition-all duration-1000"
                                data-curtain-img @if($loop->first) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                        @else
                            <div class="w-full h-full flex items-center justify-center text-white/5 text-9xl font-black"
                                data-curtain-img aria-hidden="true">
                                {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                            </div>
                        @endif
                    </div>

                    {{-- Content Section --}}
                    <div class="w-full md:w-2/5 flex flex-col gap-8 z-10 {{ $loop->even ? 'md:pr-10' : 'md:pl-10' }}">
                        <div class="flex gap-4 overflow-hidden">
                            @if($case->tags)
                                @foreach($case->tags as $tag)
                                    <span
                                        class="case-tag text-[0.6rem] tracking-[0.3em] uppercase border border-green-400/20 px-4 py-2 text-green-400/60 bg-green-400/5 inline-block"
                                        data-case-text>{{ $tag }}</span>
                                @endforeach
                            @endif
                        </div>

                        <div class="overflow-hidden">
                            <h3 class="text-5xl md:text-8xl font-black tracking-tighter uppercase leading-[0.9] group-hover:text-green-400 transition-colors duration-500"
                                data-case-text>
                                {{ $case->title }}
                            </h3>
                        </div>

                        <div class="overflow-hidden">
                            <p class="text-white/40 text-lg md:text-xl leading-relaxed font-light max-w-md"
                                data-case-text>
                                {{ $case->caption }}
                            </p>
                        </div>

                        <div class="overflow-hidden pt-4">
                            @php
                                $caseUrl = (!empty($case->url) && \App\Models\SiteSetting::isSafeUrl($case->url, true)) ? $case->url : '#';
                                $isCaseExternal = str_starts_with($caseUrl, 'http://') || str_starts_with($caseUrl, 'https://');
                            @endphp
                            <a href="{{ $caseUrl }}"
                                @if($isCaseExternal) target="_blank" rel="noopener noreferrer" @endif
                                aria-label="View project details: {{ $case->title }}"
                                class="inline-flex items-center text-xs tracking-[0.4em] uppercase gap-6 group/btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-400 focus-visible:ring-offset-4 focus-visible:ring-offset-black"
                                data-magnetic data-case-text>

                                <span class="relative z-10 flex items-center gap-6">
                                    Project Details
                                    <span class="relative w-12 h-[1px] bg-white/20 overflow-hidden">
                                        <span
                                            class="absolute inset-0 bg-green-400 translate-x-[-100%] group-hover/btn:translate-x-0 transition-transform duration-500"></span>
                                    </span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>