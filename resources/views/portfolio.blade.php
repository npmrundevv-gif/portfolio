<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $settings->brand_name }} | {{ $settings->hero_title }}</title>
    @php
        $seoDescription = (!empty($settings->hero_subtitle) ? trim($settings->hero_subtitle) . ' — ' : '') . "Official portfolio of {$settings->brand_name}. Specialist in custom jersey designs, apparel printing, and modern digital craft.";
        $seoImage = ($settings->hero_image_path && Storage::disk('public')->exists($settings->hero_image_path))
            ? url(Storage::disk('public')->url($settings->hero_image_path))
            : null;
    @endphp
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="theme-color" content="#0a0a0a">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- OpenGraph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $settings->brand_name }} | {{ $settings->hero_title }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:site_name" content="{{ $settings->brand_name }}">
    @if($seoImage)
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:alt" content="{{ $settings->brand_name }}">
    @endif

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $settings->brand_name }} | {{ $settings->hero_title }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    @if($seoImage)
        <meta name="twitter:image" content="{{ $seoImage }}">
    @endif

    <script>
        document.documentElement.classList.replace('no-js', 'js');
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;300;400;500;700;900&display=swap"
        rel="stylesheet">

    @if($settings->favicon_path && Storage::disk('public')->exists($settings->favicon_path))
        <link rel="icon" href="{{ Storage::disk('public')->url($settings->favicon_path) }}">
    @endif

    @if($settings->hero_image_path && Storage::disk('public')->exists($settings->hero_image_path))
        <link rel="preload" as="image" href="{{ Storage::disk('public')->url($settings->hero_image_path) }}" fetchpriority="high">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --bg-dark: #0a0a0a;
            --text-light: #f5f5f5;
            --accent: #e0e0e0;
        }

        html, body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: 'Outfit', sans-serif;
            overflow-x: hidden;
            max-width: 100%;
        }

        .lenis.lenis-smooth {
            height: auto;
        }

        .lenis.lenis-smooth [data-lenis-prevent] {
            overscroll-behavior: contain;
        }

        .lenis.lenis-stopped {
            overflow: hidden;
        }

        .lenis.lenis-scrolling iframe {
            pointer-events: none;
        }
    </style>
</head>

<body class="antialiased">
    <!-- Skip to Content Link -->
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[99999] focus:px-6 focus:py-3 focus:bg-green-400 focus:text-black focus:font-black focus:text-xs focus:tracking-widest focus:uppercase focus:outline-none focus:ring-4 focus:ring-green-400/50 focus:rounded-sm">
        Skip to content
    </a>

    <!-- Custom Cursor -->
    <div id="custom-cursor"
        class="fixed top-0 left-0 w-4 h-4 bg-white rounded-full pointer-events-none z-[11000] mix-blend-difference hidden md:block"
        aria-hidden="true">
    </div>

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 w-full z-50 p-6 flex justify-between items-center transition-all duration-500"
        id="main-nav" aria-label="Main Navigation">
        <a href="#hero"
            aria-label="{{ $settings->brand_name }} - Return to top"
            class="hidden md:block text-xl md:text-3xl font-black tracking-tighter mix-blend-difference relative z-50">{{ $settings->brand_name }}</a>

        <!-- Fallback Navigation Links (Accessible when JS/navbar is not initialized) -->
        <div id="nav-fallback-links" class="flex items-center gap-4 text-xs font-bold uppercase tracking-widest text-white/80 z-50">
            @foreach($sections->where('key', '!=', 'hero')->take(4) as $fallbackSec)
                <a href="#{{ $fallbackSec->getAnchorId() }}" class="hover:text-green-400 transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-green-400">{{ $fallbackSec->title }}</a>
            @endforeach
        </div>

        <!-- Hamburger Button with Label -->
        <button id="nav-toggle"
            aria-expanded="false"
            aria-controls="nav-overlay"
            aria-label="Toggle navigation menu"
            class="ml-auto md:ml-0 group relative z-50 flex items-center gap-3 cursor-pointer mix-blend-difference animate-float hover:scale-105 transition-transform duration-300">
            <span
                class="text-sm md:text-lg font-bold tracking-widest uppercase group-hover:tracking-[0.2em] transition-all duration-300">Menu</span>
            <div class="flex flex-col items-end gap-1.5 w-10 md:w-16">
                <span id="nav-toggle-icon"
                    class="w-8 h-0.5 md:w-12 md:h-1 bg-white transition-all duration-300 group-hover:w-10 md:group-hover:w-14"></span>
                <span
                    class="w-6 h-0.5 md:w-9 md:h-1 bg-white transition-all duration-300 group-hover:w-10 md:group-hover:w-14"></span>
            </div>
        </button>
    </nav>

    <!-- Menu Backdrop Blur -->
    <div id="menu-backdrop"
        aria-hidden="true"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[45] opacity-0 pointer-events-none transition-opacity duration-500">
    </div>

    <!-- Overlay Menu -->
    <div id="nav-overlay"
        role="dialog"
        aria-modal="true"
        aria-label="Navigation Menu"
        aria-hidden="true"
        inert
        class="fixed top-0 right-0 w-full md:w-[750px] bg-[#111] z-50 flex flex-col pointer-events-none shadow-2xl border-l border-white/10"
        style="display: none; height: 100vh; height: 100dvh;">

        <!-- Header: Brand + Close -->
        <div class="px-6 py-4 md:py-5 flex justify-between items-center bg-[#111] flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-green-400 shadow-[0_0_10px_rgba(74,222,128,0.5)]"></div>
                <span
                    class="text-sm font-bold tracking-widest uppercase text-white">{{ $settings->brand_name ?? "IDAN'S DESIGNS" }}</span>
            </div>
            <button id="nav-close"
                aria-label="Close navigation menu"
                class="w-12 h-12 rounded-full border border-white/20 flex items-center justify-center hover:bg-white/10 transition-colors group">
                <svg width="14" height="14" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"
                    class="text-white group-hover:scale-110 transition-transform" aria-hidden="true">
                    <path d="M1 1L11 11M1 11L11 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
            </button>
        </div>

        <!-- Menu Content -->
        <div class="flex-1 overflow-y-auto px-6 pb-6 pt-4 md:pt-6 md:pb-8 custom-scrollbar" style="min-height: 0;">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($sections->where('key', '!=', 'hero') as $index => $section)
                    @if($section->isSupportedTemplate())
                        @php
                            $anchor = $section->getAnchorId();
                        @endphp
                        <a href="#{{ $anchor }}" class="nav-card group">
                            <div class="flex items-start justify-between mb-2">
                                <span
                                    class="text-[10px] font-mono font-bold tracking-[0.3em] text-green-400/30 group-hover:text-green-400 transition-colors duration-500">
                                    {{ sprintf('%02d', $loop->iteration) }}
                                </span>
                                <div
                                    class="w-2 h-2 rounded-full bg-green-400/20 group-hover:bg-green-400 transition-colors duration-500 shadow-[0_0_8px_rgba(74,222,128,0)] group-hover:shadow-[0_0_8px_rgba(74,222,128,0.5)]">
                                </div>
                            </div>
                            <div class="text-[10px] font-bold tracking-widest text-gray-500 mb-1">
                                {{ $section->subtitle ?? 'Section' }}
                            </div>
                            <div class="flex justify-between items-end">
                                <div class="text-lg md:text-2xl font-black text-white tracking-tighter line-clamp-1">
                                    {{ $section->title ?? ucwords(str_replace('_', ' ', $section->key)) }}
                                </div>
                                <div
                                    class="w-10 h-10 rounded-full border border-white/10 flex items-center justify-center group-hover:bg-green-400 group-hover:border-green-400 group-hover:text-black transition-all duration-500 flex-shrink-0">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5" aria-hidden="true">
                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>

        </div>
    </div>

    <main id="main-content" tabindex="-1" class="outline-none">
        <div id="smooth-wrapper">
            <div id="smooth-content">
                @foreach($sections as $section)
                    @php
                        $templateView = $section->getTemplateView();
                    @endphp
                    @if($templateView && view()->exists($templateView))
                        @include($templateView, ['section' => $section])
                    @elseif($templateView)
                        @php
                            \Illuminate\Support\Facades\Log::warning("Configured section template view [{$templateView}] is missing from disk for section ID {$section->id}.");
                        @endphp
                    @else
                        @php
                            \Illuminate\Support\Facades\Log::info("Section ID {$section->id} skipped because key [{$section->key}] is not in the template allowlist.");
                        @endphp
                    @endif
                @endforeach
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer role="contentinfo" class="p-20 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-10">
        <div class="text-4xl font-black tracking-tighter">{{ $settings->brand_name }}</div>
        <div class="flex gap-6">
            @if($settings->social_links)
                @foreach($settings->social_links as $platform => $url)
                    @if(\App\Models\SiteSetting::isSafeUrl($url))
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                            class="hover:text-white/50 transition-colors uppercase text-sm tracking-widest">{{ e($platform) }}</a>
                    @endif
                @endforeach
            @endif
        </div>
        <div class="text-white/30 text-xs">© {{ date('Y') }} {{ $settings->brand_name }}. ALL RIGHTS RESERVED.</div>
    </footer>


    @if ($settings->chatbot_enabled)
        <!-- Futuristic Chatbot Widget - Responsive -->
        <button id="chat-toggle"
            aria-label="Open AI Assistant"
            aria-expanded="false"
            aria-controls="chat-window"
            class="fixed bottom-10 right-7.5 w-12 h-12 sm:bottom-8 sm:right-6 sm:w-14 sm:h-14 rounded-full border-none cursor-pointer flex items-center justify-center z-[10000] hover:scale-110 active:scale-95 transition-all duration-300 group"
            style="background: linear-gradient(135deg, #4ade80 0%, #22c55e 50%, #16a34a 100%); box-shadow: 0 0 20px rgba(74,222,128,0.4), 0 0 40px rgba(74,222,128,0.15);">

            <!-- Sparkle Icon SVG -->
            <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white drop-shadow-lg group-hover:scale-110 transition-transform duration-300"
                viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2L13.7 8.3L20 10L13.7 11.7L12 18L10.3 11.7L4 10L10.3 8.3L12 2Z" fill="white" />
                <path d="M12 5L12.85 8.15L16 9L12.85 9.85L12 13L11.15 9.85L8 9L11.15 8.15L12 5Z" fill="rgba(0,0,0,0.2)" />
                <circle cx="18" cy="5" r="1.2" fill="white" opacity="0.8" />
                <circle cx="6" cy="13" r="0.8" fill="white" opacity="0.6" />
            </svg>

            <!-- Animated pulse rings -->
            <div class="pulse-ring absolute inset-0 rounded-full bg-[#4ade80]/40" aria-hidden="true"></div>
            <div class="pulse-ring-delayed absolute inset-0 rounded-full bg-[#4ade80]/30" aria-hidden="true"></div>

            <!-- Outer glow ring -->
            <div
                class="absolute inset-[-2px] sm:inset-[-3px] rounded-full border border-[#4ade80]/30 sm:border-2 animate-pulse" aria-hidden="true">
            </div>
        </button>

        <!-- Chat Window - Fullscreen on mobile, floating on desktop -->
        <div id="chat-window"
            role="dialog"
            aria-label="AI Portfolio Assistant"
            aria-hidden="true"
            class="chat-window-responsive fixed z-[9999] opacity-0 translate-y-10 pointer-events-none origin-bottom-right flex flex-col"
            style="background: rgba(15, 15, 15, 0.95); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 50px rgba(0,0,0,0.5); overflow: hidden;">

            <!-- Glass Header -->
            <div class="p-4 flex justify-between items-center border-b border-white/10 bg-white/5">
                <div class="flex items-center gap-3">
                    <div class="relative" aria-hidden="true">
                        <div class="w-3 h-3 rounded-full bg-[#4ade80] shadow-[0_0_10px_rgba(74,222,128,0.8)]"></div>
                        <div class="absolute inset-0 w-3 h-3 rounded-full bg-[#4ade80] animate-ping opacity-75"></div>
                    </div>
                    <div>
                        <h3 class="text-white text-sm font-bold tracking-tight">AI Portfolio Assistant</h3>
                        <p class="text-[10px] text-white/40 uppercase tracking-widest font-mono">Live Session</p>
                    </div>
                </div>
                <button id="chat-close"
                    aria-label="Close chat window"
                    class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-white/10 text-white/40 hover:text-white transition-all cursor-pointer">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M1 1L11 11M1 11L11 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <!-- Accessible live region for incoming chat responses -->
            <div id="chat-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>

            <!-- Chat Area -->
            <div id="messages" data-lenis-prevent
                class="flex-1 overflow-y-auto p-6 flex flex-col gap-4 scroll-smooth custom-scrollbar"
                role="log" aria-label="Conversation messages">
                <!-- Messages will be injected here -->
            </div>

            <!-- Input Area -->
            <div class="p-4 border-t border-white/10 bg-black/20">
                <form id="chat-form" class="relative flex items-center gap-2" onsubmit="return false;">
                    <label for="chat-input" class="sr-only">Type a message for AI Assistant</label>
                    <input type="text" id="chat-input" placeholder="Ask me anything..."
                        class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-[#4ade80]/50 focus:bg-white/10 transition-all placeholder:text-white/50">
                    <button id="chat-send" type="button" aria-label="Send message"
                        class="w-10 h-10 rounded-xl bg-[#4ade80] text-black flex items-center justify-center hover:bg-[#22c55e] hover:shadow-[0_0_15px_rgba(74,222,128,0.4)] transition-all active:scale-90">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </form>
                <p class="text-[9px] text-center text-white/40 mt-3 uppercase tracking-tighter">Powered by Google Gemini 2.5
                    Flash</p>
            </div>
        </div>

        <noscript>
            <div class="fixed bottom-6 right-6 z-50 bg-black/90 border border-white/20 p-4 rounded-xl text-xs text-white max-w-xs shadow-2xl">
                Chatbot requires JavaScript. For inquiries, please reach out via the contact section below.
            </div>
        </noscript>

        <style>
            @keyframes pulse-ring {
                0% {
                    transform: scale(0.95);
                    opacity: 0.8;
                }

                100% {
                    transform: scale(1.5);
                    opacity: 0;
                }
            }

            .pulse-ring {
                animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }

            .pulse-ring-delayed {
                animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
                animation-delay: 0.5s;
            }

            /* Responsive Chat Window */
            .chat-window-responsive {
                /* Mobile: Fullscreen */
                inset: 0;
                border-radius: 0;
                height: 100dvh;
                width: 100vw;
                overscroll-behavior: contain;
            }

            body.chat-open {
                position: fixed !important;
                width: 100% !important;
                overflow-y: scroll !important;
            }

            /* Tablet and up: Floating window */
            @media (min-width: 640px) {
                .chat-window-responsive {
                    inset: auto;
                    bottom: 5rem;
                    right: 1.5rem;
                    width: 380px;
                    height: 520px;
                    max-height: calc(100vh - 7rem);
                    border-radius: 20px;
                }
            }

            /* Large screens */
            @media (min-width: 768px) {
                .chat-window-responsive {
                    width: 400px;
                    height: 560px;
                }
            }

            #chat-toggle {
                animation: float-btn 3s ease-in-out infinite;
            }

            @keyframes float-btn {

                0%,
                100% {
                    transform: translateY(0);
                }

                50% {
                    transform: translateY(-5px);
                }
            }

            #chat-toggle:hover {
                animation: none;
            }

            .chat-message {
                max-width: 85%;
                padding: 12px 16px;
                font-size: 14px;
                line-height: 1.6;
                position: relative;
            }

            .chat-user {
                background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
                color: black;
                align-self: flex-end;
                border-radius: 18px 18px 2px 18px;
                box-shadow: 0 4px 15px rgba(74, 222, 128, 0.2);
                font-weight: 600;
            }

            .chat-ai {
                background: rgba(255, 255, 255, 0.05);
                color: white;
                align-self: flex-start;
                border-radius: 18px 18px 18px 2px;
                border: 1px solid rgba(255, 255, 255, 0.05);
            }

            .typing-indicator {
                display: flex;
                gap: 5px;
                padding: 15px 20px;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 18px 18px 18px 2px;
                width: fit-content;
            }

            .typing-dot {
                width: 6px;
                height: 6px;
                background: #4ade80;
                border-radius: 50%;
                animation: bounce 1s infinite ease-in-out;
            }

            @keyframes bounce {

                0%,
                80%,
                100% {
                    transform: scale(0);
                    opacity: 0.3;
                }

                40% {
                    transform: scale(1);
                    opacity: 1;
                }
            }

            /* Typewriter cursor animation */
            .typewriter-cursor {
                display: inline-block;
                color: #4ade80;
                font-weight: 100;
                animation: blink 0.7s ease-in-out infinite;
                margin-left: 2px;
            }

            @keyframes blink {

                0%,
                50% {
                    opacity: 1;
                }

                51%,
                100% {
                    opacity: 0;
                }
            }

            .custom-scrollbar::-webkit-scrollbar {
                width: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.05);
                border-radius: 10px;
            }

            #messages .markdown-body h1,
            #messages .markdown-body h2,
            #messages .markdown-body h3 {
                color: #4ade80;
                font-weight: 700;
            }

            #messages .markdown-body strong {
                color: #4ade80;
            }

            #messages .markdown-body ul,
            #messages .markdown-body ol {
                margin-left: 1.25rem;
            }

            #messages .markdown-body code {
                background: rgba(255, 255, 255, 0.1);
                padding: 2px 4px;
                border-radius: 4px;
            }

            #messages .markdown-body pre {
                background: rgba(0, 0, 0, 0.3);
                padding: 1rem;
                border-radius: 8px;
            }

            .chat-toggle-hidden {
                opacity: 0 !important;
                pointer-events: none !important;
                transform: scale(0) !important;
                animation: none !important;
            }
        </style>

        <script>
            window.chatRoute = '{{ route('chat.message') }}';
            window.brandName = @json($settings->brand_name ?? 'Portfolio');
        </script>
    @endif
    <!-- ── Back-to-Top Button ───────────────────────────────────────────── -->
    <button id="back-to-top" aria-label="Back to top">
        <!-- Up arrow -->
        <svg id="btt-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="19" x2="12" y2="5"/>
            <polyline points="5 12 12 5 19 12"/>
        </svg>
        <span>Top</span>
        <!-- Pulse dot -->
        <span class="btt-dot"></span>
    </button>

    <style>
        #back-to-top {
            position: fixed;
            bottom: 5.5rem;
            left: 50%;
            z-index: 10001;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem 0.6rem 1rem;
            border-radius: 9999px;
            border: 1px solid rgba(74,222,128,0.35);
            cursor: pointer;
            background: linear-gradient(135deg, rgba(74,222,128,0.12) 0%, rgba(34,197,94,0.08) 100%);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 0 18px rgba(74,222,128,0.18), 0 4px 30px rgba(0,0,0,0.45);
            color: #4ade80;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            /* hidden by default — JS controls opacity / transform / pointer-events */
            opacity: 0;
            pointer-events: none;
            transform: translateX(-50%) translateY(100px);
            transition: box-shadow 0.3s ease, background 0.3s ease, opacity 0.4s ease, transform 0.5s cubic-bezier(0.34,1.56,0.64,1);
            will-change: transform, opacity;
        }
        #back-to-top:hover {
            background: linear-gradient(135deg, rgba(74,222,128,0.24) 0%, rgba(34,197,94,0.16) 100%);
            box-shadow: 0 0 30px rgba(74,222,128,0.45), 0 6px 40px rgba(0,0,0,0.5);
        }
        #back-to-top:hover #btt-arrow {
            transform: translateY(-3px);
        }
        #btt-arrow {
            transition: transform 0.3s ease;
        }
        .btt-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 8px rgba(74,222,128,0.9);
            animation: btt-dot-pulse 2s ease-in-out infinite;
            flex-shrink: 0;
        }
        @keyframes btt-dot-pulse {
            0%, 100% { transform: scale(1);   opacity: 1;   }
            50%       { transform: scale(1.8); opacity: 0.4; }
        }
    </style>

    <script>
        (function () {
            const btn = document.getElementById('back-to-top');
            let lastY   = window.scrollY;
            let visible = false;
            let ticking = false;

            function showBtn() {
                if (visible) return;
                visible = true;
                btn.style.opacity        = '1';
                btn.style.transform      = 'translateX(-50%) translateY(0)';
                btn.style.pointerEvents  = 'auto';
            }

            function hideBtn() {
                if (!visible) return;
                visible = false;
                btn.style.opacity        = '0';
                btn.style.transform      = 'translateX(-50%) translateY(100px)';
                btn.style.pointerEvents  = 'none';
            }

            window.addEventListener('scroll', function () {
                if (ticking) return;
                ticking = true;
                requestAnimationFrame(function () {
                    const currentY      = window.scrollY;
                    const scrolledEnough = currentY > 400;
                    const scrollingUp    = currentY < lastY;

                    if (scrollingUp && scrolledEnough) {
                        showBtn();
                    } else {
                        hideBtn();
                    }

                    lastY   = currentY;
                    ticking = false;
                });
            }, { passive: true });

            btn.addEventListener('click', function () {
                hideBtn();
                const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (prefersReduced) {
                    window.scrollTo({ top: 0, behavior: 'instant' });
                    return;
                }
                if (window.lenis) {
                    window.lenis.scrollTo(0, {
                        duration: 1.2,
                        easing: function (t) {
                            return t < 0.5
                                ? 4 * t * t * t
                                : 1 - Math.pow(-2 * t + 2, 3) / 2;
                        }
                    });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        })();
    </script>
</body>

</html>
