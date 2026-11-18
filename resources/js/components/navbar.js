import gsap from 'gsap';

export function initNavbar(lenis) {
    const nav = document.getElementById('main-nav');
    const toggleBtn = document.getElementById('nav-toggle');
    const closeBtn = document.getElementById('nav-close');
    const menuOverlay = document.getElementById('nav-overlay');
    const menuBackdrop = document.getElementById('menu-backdrop');
    const menuLinks = document.querySelectorAll('.nav-card');
    const toggleIcon = document.getElementById('nav-toggle-icon');

    // Debug: Log what elements were found
    console.log('Navbar elements:', {
        nav: !!nav,
        toggleBtn: !!toggleBtn,
        closeBtn: !!closeBtn,
        menuOverlay: !!menuOverlay,
        menuBackdrop: !!menuBackdrop,
        menuLinksCount: menuLinks.length,
        toggleIcon: !!toggleIcon
    });

    let isOpen = false;

    // Check if all required elements exist
    if (!toggleBtn || !menuOverlay) {
        console.error('Navbar: Required elements not found');
        return;
    }

    // Timeline for menu animation
    const menuTl = gsap.timeline({
        paused: true,
        onReverseComplete: () => {
            if (menuOverlay) {
                menuOverlay.style.display = 'none';
                menuOverlay.setAttribute('inert', '');
            }
        }
    });

    // Initial state: hidden and inert so closed menu controls are not focusable
    if (menuOverlay) {
        gsap.set(menuOverlay, { xPercent: 100 });
        menuOverlay.style.display = 'none';
        menuOverlay.setAttribute('inert', '');
        menuOverlay.setAttribute('aria-hidden', 'true');
    }
    if (menuBackdrop) {
        gsap.set(menuBackdrop, { opacity: 0 });
    }
    if (menuLinks.length > 0) {
        gsap.set(menuLinks, { y: 40, opacity: 0 });
    }

    // Build animation timeline
    if (menuBackdrop) {
        menuTl.to(menuBackdrop, {
            opacity: 1,
            pointerEvents: 'all',
            duration: 0.3,
            ease: 'power2.inOut'
        });
    }

    menuTl.to(menuOverlay, {
        xPercent: 0,
        duration: 0.4,
        ease: 'expo.out',
        pointerEvents: 'all'
    }, menuBackdrop ? '-=0.2' : 0);

    if (menuLinks.length > 0) {
        menuTl.to(menuLinks, {
            y: 0,
            opacity: 1,
            duration: 0.4,
            stagger: 0.05,
            ease: 'power4.out'
        }, '-=0.2');
    }

    if (closeBtn) {
        menuTl.to(closeBtn, {
            rotate: 90,
            duration: 0.3,
            ease: 'power2.inOut'
        }, '-=0.7');
    }

    // Background inert toggling for true modal containment
    const setBackgroundInert = (inert) => {
        const targets = [
            document.getElementById('main-content'),
            document.getElementById('chat-widget'),
            document.querySelector('footer'),
            toggleBtn
        ];
        targets.forEach(el => {
            if (el) {
                if (inert) {
                    el.setAttribute('inert', '');
                } else {
                    el.removeAttribute('inert');
                }
            }
        });
    };

    // Keyboard Tab / Shift+Tab focus trap inside menu overlay
    const handleFocusTrap = (e) => {
        if (!isOpen || e.key !== 'Tab') return;
        const focusableElements = menuOverlay.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    };

    menuOverlay.addEventListener('keydown', handleFocusTrap);

    // Function to toggle menu
    const toggleMenu = (open) => {
        if (isOpen === open) return;
        isOpen = open;
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (isOpen) {
            toggleBtn.setAttribute('aria-expanded', 'true');
            setBackgroundInert(true);

            if (menuOverlay) {
                menuOverlay.removeAttribute('inert');
                menuOverlay.setAttribute('aria-hidden', 'false');
                menuOverlay.style.display = 'flex';
            }
            if (prefersReduced) {
                menuTl.progress(1);
            } else {
                menuTl.timeScale(1).play();
            }
            if (toggleIcon) toggleIcon.classList.add('active');

            // Move focus into menu for keyboard accessibility
            setTimeout(() => {
                if (closeBtn) closeBtn.focus();
            }, 100);
        } else {
            toggleBtn.setAttribute('aria-expanded', 'false');
            setBackgroundInert(false);

            if (menuOverlay) {
                menuOverlay.setAttribute('aria-hidden', 'true');
                menuOverlay.setAttribute('inert', '');
            }

            if (prefersReduced) {
                menuTl.progress(0);
                if (menuOverlay) menuOverlay.style.display = 'none';
            } else {
                menuTl.timeScale(2).reverse();
            }
            if (toggleIcon) toggleIcon.classList.remove('active');

            // Restore focus back to the menu toggle button
            setTimeout(() => {
                if (toggleBtn) toggleBtn.focus();
            }, 100);
        }
    };

    // Event Listeners
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            toggleMenu(true);
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            toggleMenu(false);
        });
    }
    if (menuBackdrop) {
        menuBackdrop.addEventListener('click', () => {
            toggleMenu(false);
        });
    }

    // ESC Key Closure
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) {
            e.preventDefault();
            toggleMenu(false);
        }
    });

    // Adapt layout on window resize while open
    window.addEventListener('resize', () => {
        if (isOpen && menuOverlay) {
            menuOverlay.style.height = `${window.innerHeight}px`;
        }
    });

    // Handle Link Clicks
    menuLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const targetId = link.getAttribute('href');
            if (targetId && targetId.startsWith('#')) {
                e.preventDefault();
                toggleMenu(false);
                setTimeout(() => {
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        const activeLenis = window.lenis;
                        if (prefersReduced) {
                            targetElement.scrollIntoView({ behavior: 'instant', block: 'start' });
                        } else if (activeLenis && typeof activeLenis.scrollTo === 'function') {
                            activeLenis.scrollTo(targetElement, {
                                duration: 1.0,
                                offset: -80,
                                easing: (t) => t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t
                            });
                        } else {
                            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                }, 300);
            }
        });
    });
}
