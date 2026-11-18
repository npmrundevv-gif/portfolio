@php
    $cols = $section->layout['columns'] ?? 3;
    $gridClass = match((int)$cols) {
        1 => 'lg:grid-cols-1',
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        4 => 'lg:grid-cols-4',
        default => 'lg:grid-cols-3',
    };
    $gapClass = match((string)($section->layout['gap'] ?? '1')) {
        '0' => 'gap-0',
        '1' => 'gap-1',
        '2' => 'gap-2',
        '4' => 'gap-4',
        '8' => 'gap-8',
        default => 'gap-1',
    };
    $paddingClass = match((string)($section->layout['padding'] ?? 'py-40')) {
        'py-20' => 'py-20',
        'py-40' => 'py-40',
        'py-60' => 'py-60',
        default => 'py-40',
    };
    $containerClass = !empty($section->layout['full_width']) ? 'w-full px-6' : 'container mx-auto px-6';
@endphp
<section id="{{ $section->getAnchorId() }}" class="{{ $paddingClass }} bg-black">
    <div class="{{ $containerClass }}">
        <div class="mb-32 flex flex-col md:flex-row justify-between items-end gap-10">
            <div>
                <h2 class="text-7xl md:text-8xl font-black tracking-tighter uppercase split-text">{{ $section->title }}</h2>
                <p class="text-green-400 tracking-widest uppercase mt-4 opacity-70">{{ $section->subtitle }}</p>
            </div>
            <div class="text-white/20 text-xs tracking-[0.5em] uppercase pb-2">Crafted with precision</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 {{ $gridClass }} {{ $gapClass }}">
            @foreach($section->works->where('enabled', true) as $work)
                @php
                    $hasWorkImage = !empty($work->image_path) && Storage::disk('public')->exists($work->image_path);
                @endphp
                <div class="work-item relative aspect-square overflow-hidden group bg-white/5 reveal-card">
                    @if($hasWorkImage)
                        <img src="{{ Storage::disk('public')->url($work->image_path) }}" alt="{{ $work->title }}"
                            class="w-full h-full object-cover grayscale group-hover:grayscale-0 scale-110 group-hover:scale-100 transition-all duration-1000"
                            @if($loop->iteration <= 2) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-white/[0.02] text-white/10 text-6xl font-black" aria-hidden="true">
                            {{ sprintf('%02d', $loop->iteration) }}
                        </div>
                    @endif
                    <div
                        class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-center items-center p-10 text-center">
                        <h3 class="text-2xl font-black tracking-tighter uppercase mb-2 transform translate-y-4 group-hover:translate-y-0 transition-transform duration-500">{{ $work->title }}</h3>

                        <div
                            class="flex gap-2 flex-wrap justify-center transform translate-y-4 group-hover:translate-y-0 transition-transform duration-500 delay-100">
                            @if($work->tags)
                                @foreach($work->tags as $tag)
                                    <span class="text-[0.6rem] tracking-widest uppercase opacity-60">{{ $tag }}</span>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>