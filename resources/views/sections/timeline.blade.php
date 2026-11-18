<section id="{{ $section->getAnchorId() }}"
    class="bg-black overflow-hidden section-timeline relative" data-timeline-section>

    {{-- Section Header --}}
    <div class="container mx-auto px-6 pt-40 pb-16 text-center">
        <h2 class="text-7xl md:text-9xl font-black tracking-tighter uppercase split-text">{{ $section->title }}</h2>
        @if($section->subtitle)
            <p class="mt-6 text-sm md:text-base tracking-[0.3em] uppercase text-[#00ff64] font-light">{{ $section->subtitle }}</p>
        @endif
    </div>

    {{-- Pinned Horizontal Scroll Viewport --}}
    <div class="timeline-viewport" data-timeline-viewport>

        {{-- Ambient glow --}}
        <div class="timeline-ambient-glow" data-timeline-glow></div>

        {{-- The horizontally scrolling track --}}
        <div class="timeline-track" data-timeline-track>

            {{-- Connector line that runs across all cards --}}
            <div class="timeline-connector" data-timeline-connector>
                <div class="timeline-connector-line"></div>
                <div class="timeline-connector-progress" data-timeline-connector-progress></div>
            </div>

            @forelse($section->timelineEvents->where('enabled', true) as $index => $event)
                <div class="timeline-card" data-timeline-card data-card-index="{{ $index }}">

                    {{-- Year Badge --}}
                    <div class="timeline-year-badge" data-timeline-year>
                        <span class="timeline-year-text">{{ $event->year }}</span>
                        <div class="timeline-year-glow"></div>
                    </div>

                    {{-- Node dot on the connector line --}}
                    <div class="timeline-node" data-timeline-node>
                        <div class="timeline-node-dot"></div>
                        <div class="timeline-node-ring"></div>
                    </div>

                    {{-- Card Body --}}
                    <div class="timeline-card-body" data-timeline-card-body>
                        {{-- Decorative accent line at top --}}
                        <div class="timeline-card-accent"></div>

                        {{-- Optional Image --}}
                        @php
                            $hasEventImage = !empty($event->image_path) && Storage::disk('public')->exists($event->image_path);
                        @endphp
                        @if($hasEventImage)
                            <div class="timeline-card-image">
                                <img src="{{ Storage::disk('public')->url($event->image_path) }}" alt="{{ $event->title }}"
                                    class="w-full h-full object-cover" loading="lazy" decoding="async">
                            </div>
                        @endif

                        {{-- Title --}}
                        <h3 class="text-lg md:text-xl lg:text-2xl 2xl:text-3xl font-black tracking-tighter text-white uppercase mt-3">{{ $event->title }}</h3>

                        {{-- Description --}}
                        @if($event->description)
                            <p class="text-sm lg:text-[0.95rem] 2xl:text-base text-white/50 font-light leading-relaxed mt-2 lg:mt-4">{{ $event->description }}</p>
                        @endif

                        {{-- Tags --}}
                        @if($event->tags && count($event->tags) > 0)
                            <div class="flex flex-wrap gap-2 mt-4 lg:mt-6">
                                @foreach($event->tags as $tag)
                                    <span class="timeline-tag lg:text-sm lg:px-4 lg:py-1.5">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Optional URL --}}
                        @if(!empty($event->url) && \App\Models\SiteSetting::isSafeUrl($event->url))
                            <a href="{{ $event->url }}" target="_blank" rel="noopener noreferrer"
                                class="timeline-card-link lg:mt-6 lg:text-sm lg:px-6 lg:py-3" data-magnetic
                                aria-label="View {{ $event->title }}">
                                <span>View</span>
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" aria-hidden="true">
                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-12 px-6 text-center text-white/40 font-mono text-sm uppercase tracking-widest">
                    No timeline events available.
                </div>
            @endforelse

        </div>

    </div>
</section>
