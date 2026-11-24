 <section id="{{ $section->getAnchorId() }}" class="py-40 bg-black overflow-hidden relative min-h-screen flex items-center">
    <div class="container mx-auto px-6 relative z-10">
        <div class="flex flex-col md:flex-row gap-20 items-center">
            <div class="w-full md:w-1/2">
                <h2 class="text-7xl md:text-8xl font-black tracking-tighter uppercase split-text mb-10">{{ $section->title }}</h2>
                <div class="space-y-6" id="stack-list">
                    @php 
                        $stack = \App\Models\TechStack::orderBy('sort_order')->get();
                    @endphp
                    @forelse($stack as $item)
                        @php
                            $hasItemImage = !empty($item->image_path) && Storage::disk('public')->exists($item->image_path);
                            $itemImageUrl = $hasItemImage ? Storage::disk('public')->url($item->image_path) : '';
                        @endphp
                        <div class="stack-row cursor-pointer relative z-20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-400 focus-visible:ring-offset-4 focus-visible:ring-offset-black rounded"
                             tabindex="0"
                             role="button"
                             aria-pressed="false"
                             aria-label="Select {{ $item->name }} technology"
                             data-name="{{ $item->name }}"
                             data-image="{{ $itemImageUrl }}">
                            <div class="stack-label text-4xl md:text-6xl font-black tracking-tighter opacity-20">
                                {{ $item->name }}
                            </div>
                            <div class="stack-underline w-0 h-[1px] bg-green-400 mt-2"></div>
                        </div>
                    @empty
                        <p class="text-white/40 text-sm tracking-wider uppercase font-mono">Technologies currently in development</p>
                    @endforelse
                </div>

            </div>
            
            <div class="w-full md:w-1/2 h-[500px] flex items-center justify-center relative perspective-2000">
                <div class="w-64 h-64 bg-white/5 border border-white/10 flex items-center justify-center transform rotate-x-12 rotate-y-12 rotate-z-3 shadow-[0_0_50px_rgba(255,255,255,0.05)]" id="stack-cube">
                    <!-- Default State -->
                    <img id="cube-image" src="" alt="Tech Stack" class="w-32 h-32 object-contain drop-shadow-[0_0_15px_rgba(74,222,128,0.5)] hidden">
                    <span id="cube-text" class="text-8xl font-black tracking-tighter opacity-20 text-green-400">{{ $section->data['cube_text'] ?? 'PHP' }}</span>
                </div>
            </div>
        </div>
    </div>
</section>



<style>
    .stack-label,
    .stack-underline {
        transition-property: none;
    }

    .stack-row.is-active .stack-label {
        opacity: 1;
        color: rgb(74 222 128);
        translate: 1rem;
    }

    .stack-row.is-active .stack-underline {
        width: 100%;
    }

    .perspective-2000 { 
        perspective: 2000px; 
    }
    
    #stack-cube {
        transform-style: preserve-3d;
        animation: greenGlitch 3s infinite;
        position: relative;
    }

    #stack-cube::before,
    #stack-cube::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border: inherit;
        background: inherit;
        opacity: 0;
        pointer-events: none;
    }

    #stack-cube::before {
        animation: glitchLayer1 2s infinite;
        border-color: rgba(74, 222, 128, 0.5);
        box-shadow: -2px 0 0 rgba(74, 222, 128, 0.7);
    }

    #stack-cube::after {
        animation: glitchLayer2 2.5s infinite;
        border-color: rgba(34, 197, 94, 0.5);
        box-shadow: 2px 0 0 rgba(34, 197, 94, 0.7);
    }

    @keyframes greenGlitch {
        0%, 90%, 100% {
            box-shadow: 0 0 50px rgba(74, 222, 128, 0.2);
        }
        92% {
            translate: -2px 1px;
            box-shadow: 0 0 80px rgba(74, 222, 128, 0.6), -3px 0 0 rgba(74, 222, 128, 0.8);
        }
        94% {
            translate: 2px -1px;
            box-shadow: 0 0 80px rgba(74, 222, 128, 0.6), 3px 0 0 rgba(34, 197, 94, 0.8);
        }
        96% {
            translate: -1px 2px;
            box-shadow: 0 0 60px rgba(74, 222, 128, 0.4);
        }
        98% {
            translate: 1px -1px;
        }
    }

    @keyframes glitchLayer1 {
        0%, 85%, 100% {
            opacity: 0;
            transform: translateX(0);
        }
        86%, 88% {
            opacity: 0.8;
            transform: translateX(-4px) skewX(-2deg);
        }
        89%, 91% {
            opacity: 0;
        }
        92%, 94% {
            opacity: 0.5;
            transform: translateX(3px) skewX(1deg);
        }
    }

    @keyframes glitchLayer2 {
        0%, 80%, 100% {
            opacity: 0;
            transform: translateX(0);
        }
        81%, 83% {
            opacity: 0.7;
            transform: translateX(4px) skewX(2deg);
        }
        84%, 87% {
            opacity: 0;
        }
        88%, 90% {
            opacity: 0.4;
            transform: translateX(-3px) skewX(-1deg);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #stack-cube,
        #stack-cube::before,
        #stack-cube::after {
            animation: none;
        }
    }

    /* Mobile Responsive - Cube as Background */
    @media (max-width: 768px) {
        .perspective-2000 {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #stack-cube {
            width: 200px;
            height: 200px;
            opacity: 0.3;
            pointer-events: none;
        }

        #cube-image {
            width: 100px;
            height: 100px;
        }

        #cube-text {
            font-size: 4rem;
        }

        /* Keep content above cube */
        #stack-list {
            position: relative;
            z-index: 10;
        }
    }
</style>

