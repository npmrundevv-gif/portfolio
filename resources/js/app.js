import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
import { initLenis } from './animations/lenis';
import { initGsapAnimations, restoreReadableContent } from './animations/gsap';
import { initSplitText } from './animations/splitText';
import { initNavbar } from './components/navbar';
import { initHeroInteractive } from './animations/hero-interactive';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { initChat } from './components/chat';
import { initStack3D } from './interactive/stack3d';

window.gsap = gsap;
window.ScrollTrigger = ScrollTrigger;
window.restoreReadableContent = restoreReadableContent;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Smooth Scroll
    let lenis = null;
    try {
        lenis = initLenis();
        window.lenis = lenis;
    } catch (e) {
        console.warn('Lenis init failed, using native scroll fallback:', e);
    }

    // 2. Initialize Text Splitting
    try {
        initSplitText();
    } catch (e) {
        console.warn('SplitText init skipped:', e);
    }

    // 3. Initialize GSAP Animations with graceful fallback
    try {
        initGsapAnimations(lenis);
    } catch (e) {
        console.error('GSAP Animations init failed, restoring readable content:', e);
        restoreReadableContent();
    }

    // 4. Initialize Navbar (essential navigation)
    try {
        initNavbar(lenis);
        document.documentElement.classList.add('js-nav-ready');
    } catch (e) {
        console.error('Navbar init failed, keeping fallback navigation links active:', e);
    }

    // 5. Initialize Hero Interactive Animation
    try {
        initHeroInteractive();
    } catch (e) {
        console.warn('Hero interactive init skipped:', e);
    }

    // 6. Initialize Chatbot if available
    try {
        const chatToggle = document.getElementById('chat-toggle');
        if (chatToggle) {
            const chatRoute = window.chatRoute || '/chat/message';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const brandName = window.brandName || 'Portfolio';
            initChat(chatRoute, csrfToken, brandName);
        }
    } catch (e) {
        console.warn('Chatbot init skipped:', e);
    }

    // 7. Initialize Stack3D
    try {
        initStack3D();
    } catch (e) {
        console.warn('Stack3D init skipped:', e);
    }

    console.log('Cinematic Portfolio Initialized');
});
