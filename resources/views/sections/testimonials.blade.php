<section id="{{ $section->getAnchorId() }}"
    class="bg-black overflow-hidden section-testimonials relative" data-testimonials-section>

    {{-- Section Header --}}
    <div class="container mx-auto px-6 pt-40 pb-16 text-center">
        <h2 class="text-7xl md:text-9xl font-black tracking-tighter uppercase split-text">{{ $section->title }}</h2>
    </div>

    {{-- Pinned Stacked Cards Viewport --}}
    <div class="testimonials-viewport" data-testimonials-viewport>

        {{-- Ambient glow behind stack --}}
        <div class="testimonials-ambient-glow" data-testimonials-glow></div>

        <div class="testimonials-stack" data-testimonials-stack>
            @php
                $activeTestimonials = $section->testimonials->where('enabled', true)->values();
                $totalCount = $activeTestimonials->count();
            @endphp
            @forelse($activeTestimonials as $index => $testimonial)
                <div class="testimonial-card" data-testimonial-card data-card-index="{{ $index }}"
                    style="z-index: {{ $totalCount - $index }};">
                    <div class="testimonial-card-inner">
                        {{-- Decorative accent line --}}
                        <div class="testimonial-accent-line"></div>

                        <div>
                            <div class="testimonial-quote-mark" aria-hidden="true">&ldquo;</div>
                            <blockquote class="text-lg md:text-xl lg:text-2xl font-light leading-relaxed mb-8 italic text-white/80">
                                {{ $testimonial->quote }}
                            </blockquote>
                        </div>

                        <div class="flex items-center gap-5">
                            @php
                                $hasAvatar = !empty($testimonial->avatar_path) && Storage::disk('public')->exists($testimonial->avatar_path);
                            @endphp
                            @if($hasAvatar)
                                <div class="testimonial-avatar">
                                    <img src="{{ Storage::disk('public')->url($testimonial->avatar_path) }}" alt="{{ $testimonial->name }}"
                                        class="w-full h-full object-cover" loading="lazy" decoding="async">
                                </div>
                            @else
                                <div class="testimonial-avatar testimonial-avatar-fallback" aria-hidden="true">
                                    {{ substr($testimonial->name, 0, 2) }}
                                </div>
                            @endif
                            <div>
                                <div class="font-black tracking-tighter uppercase text-sm text-white">{{ $testimonial->name }}</div>
                                <div class="text-[0.6rem] tracking-widest uppercase text-white/30 mt-1">{{ $testimonial->role }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-12 px-6 text-center text-white/40 font-mono text-sm uppercase tracking-widest">
                    No testimonials currently available.
                </div>
            @endforelse
        </div>

        {{-- Card Counter --}}
        <div class="testimonials-counter" data-testimonials-counter>
            <span data-testimonials-current>1</span>
            <span class="text-white/15">/</span>
            <span class="text-white/30">{{ count($section->testimonials) }}</span>
        </div>

        {{-- Premium Progress Bar --}}
        <div class="testimonials-progress-container">
            <div class="testimonials-progress-track"></div>
            <div class="testimonials-progress-bar" data-testimonials-progress>
                <div class="testimonials-progress-glow"></div>
            </div>
        </div>
    </div>
</section>