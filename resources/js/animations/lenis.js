import Lenis from 'lenis';

export const initLenis = () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    // Fallback object for reduced-motion environments
    const createFallback = () => ({
        on: () => {},
        stop: () => {},
        start: () => {},
        destroy: () => {},
        scrollTo: (target) => {
            if (typeof target === 'string') {
                target = document.querySelector(target);
            }
            if (target instanceof HTMLElement) {
                target.scrollIntoView({ behavior: 'instant', block: 'start' });
            } else if (typeof target === 'number') {
                window.scrollTo({ top: target, behavior: 'instant' });
            }
        }
    });

    let currentInstance = null;
    let rafId = null;
    let isRunning = false;

    function startRaf(instance) {
        if (!instance) return;
        isRunning = true;
        function raf(time) {
            if (!isRunning) return;
            instance.raf(time);
            rafId = requestAnimationFrame(raf);
        }
        if (rafId) cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(raf);
    }

    function stopRaf() {
        isRunning = false;
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    function createRealLenis() {
        const instance = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            orientation: 'vertical',
            gestureOrientation: 'vertical',
            smoothWheel: true,
            wheelMultiplier: 1,
            smoothTouch: false,
            touchMultiplier: 2,
            infinite: false,
        });

        startRaf(instance);
        return instance;
    }

    if (prefersReducedMotion.matches) {
        currentInstance = createFallback();
    } else {
        currentInstance = createRealLenis();
    }

    // Pause RAF on hidden tab to save battery and CPU cycles
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopRaf();
        } else if (!prefersReducedMotion.matches && currentInstance && typeof currentInstance.raf === 'function') {
            startRaf(currentInstance);
        }
    });

    // Listen for runtime preference changes
    prefersReducedMotion.addEventListener('change', (e) => {
        if (e.matches) {
            stopRaf();
            if (currentInstance && typeof currentInstance.destroy === 'function') {
                try {
                    currentInstance.destroy();
                } catch (err) {
                    // ignore
                }
            }
            currentInstance = createFallback();
            window.lenis = currentInstance;

            // Pause decorative background video
            const heroVideo = document.getElementById('hero-bg');
            if (heroVideo && typeof heroVideo.pause === 'function') {
                heroVideo.pause();
            }

            // Cleanly disable pin-spacers so layout doesn't break
            if (window.ScrollTrigger) {
                window.ScrollTrigger.getAll().forEach(st => {
                    if (st.pin) {
                        try {
                            st.disable(false);
                            st.revert(false);
                        } catch (err) {
                            // ignore
                        }
                    }
                });
            }
        } else {
            // Re-instantiate Lenis when switching back to normal motion
            currentInstance = createRealLenis();
            window.lenis = currentInstance;

            if (window.ScrollTrigger) {
                try {
                    window.ScrollTrigger.refresh();
                } catch (err) {
                    // ignore
                }
            }

            const heroVideo = document.getElementById('hero-bg');
            if (heroVideo && typeof heroVideo.play === 'function') {
                heroVideo.play().catch(() => {});
            }
        }
    });

    return currentInstance;
};
