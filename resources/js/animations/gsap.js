import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import SplitType from 'split-type';

gsap.registerPlugin(ScrollTrigger);
// Safari's collapsing toolbar changes height without changing the page layout.
ScrollTrigger.config({ ignoreMobileResize: true });

export const restoreReadableContent = () => {
    document.documentElement.classList.remove('animations-ready');
    if (typeof ScrollTrigger !== 'undefined') {
        try {
            ScrollTrigger.getAll().forEach(st => st.kill());
        } catch (e) {}
    }
    document.querySelectorAll('.reveal-card, .border-glitch').forEach((el) => {
        el.classList.add('is-inview');
        gsap.set(el, { clipPath: 'none', clearProps: 'all' });
    });
    document.querySelectorAll('.split-text').forEach((el) => {
        gsap.set(el, { opacity: 1, y: 0, clearProps: 'all' });
    });
    document.querySelectorAll('[data-curtain-img]').forEach((el) => {
        gsap.set(el, { opacity: 1, scale: 1, filter: 'none', clearProps: 'all' });
    });
    document.querySelectorAll('[data-case-text]').forEach((el) => {
        gsap.set(el, { opacity: 1, y: 0, clearProps: 'all' });
    });
    document.querySelectorAll('.curtain-layer').forEach((el) => {
        el.style.display = 'none';
    });
    document.querySelectorAll('[data-title-reveal], [data-desc-reveal]').forEach((el) => {
        gsap.set(el, { opacity: 1, y: 0, filter: 'none', clearProps: 'all' });
    });
    document.querySelectorAll('[data-timeline-card]').forEach((card) => {
        gsap.set(card.querySelectorAll('[data-timeline-year], [data-timeline-card-body], [data-timeline-node]'), { opacity: 1, y: 0, scale: 1, clearProps: 'all' });
    });
    document.querySelectorAll('[data-testimonial-card]').forEach((card) => {
        gsap.set(card, { opacity: 1, y: 0, scale: 1, rotation: 0, clearProps: 'all' });
    });
    document.querySelectorAll('.counter').forEach((counter) => {
        const target = counter.getAttribute('data-target');
        if (target) counter.innerText = target;
    });
    document.querySelectorAll('.profile-image-reveal').forEach((el) => {
        el.style.transition = 'none';
        gsap.set(el, { scale: 1, opacity: 1 });
        el.style.opacity = '1';
        el.style.transform = 'none';
    });
};

export const initGsapAnimations = (lenis) => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    // Sync ScrollTrigger with Lenis
    if (lenis && typeof lenis.on === 'function') {
        lenis.on('scroll', ScrollTrigger.update);
    }

    // 1. Custom Cursor - ONLY for fine pointer devices without reduced motion
    const cursor = document.getElementById('custom-cursor');
    const isFinePointer = window.matchMedia('(pointer: fine) and (hover: hover)').matches;

    if (cursor && isFinePointer && !prefersReducedMotion.matches) {
        document.documentElement.classList.add('has-custom-cursor');
        let cursorActive = false;

        const onMouseMove = (e) => {
            gsap.to(cursor, {
                x: e.clientX,
                y: e.clientY,
                duration: 0.1,
                ease: 'power2.out',
                overwrite: 'auto'
            });
            if (!cursorActive) {
                cursor.style.display = 'block';
                cursor.classList.add('active');
                cursorActive = true;
            }
        };

        window.addEventListener('mousemove', onMouseMove, { passive: true });

        document.addEventListener('mouseleave', () => {
            cursor.style.display = 'none';
            cursor.classList.remove('active');
            cursorActive = false;
        });

        document.addEventListener('mouseenter', () => {
            cursor.style.display = 'block';
            cursor.classList.add('active');
            cursorActive = true;
        });

        window.addEventListener('blur', () => {
            cursor.style.display = 'none';
            cursor.classList.remove('active');
            cursorActive = false;
        });
    } else if (cursor) {
        cursor.style.display = 'none';
        document.documentElement.classList.remove('has-custom-cursor');
    }

    // Dynamic preference change listener for reduced motion
    prefersReducedMotion.addEventListener('change', (e) => {
        if (e.matches) {
            if (cursor) {
                cursor.style.display = 'none';
                cursor.classList.remove('active');
            }
            document.documentElement.classList.remove('has-custom-cursor');
            restoreReadableContent();
            if (typeof ScrollTrigger !== 'undefined') {
                ScrollTrigger.getAll().forEach(st => {
                    if (st.pin) {
                        try {
                            st.disable(false);
                            st.revert(false);
                        } catch (err) {}
                    }
                });
            }
        } else {
            if (cursor && isFinePointer) {
                document.documentElement.classList.add('has-custom-cursor');
            }
            document.documentElement.classList.add('animations-ready');
            if (typeof ScrollTrigger !== 'undefined') {
                try {
                    ScrollTrigger.refresh();
                } catch (err) {}
            }
        }
    });

    // If reduced motion is preferred, reveal all content immediately and bypass animations/pinning
    if (prefersReducedMotion.matches) {
        restoreReadableContent();
        return;
    }

    // Mark animation subsystem as safely ready to activate clipping transitions
    document.documentElement.classList.add('animations-ready');


    // 2. Hero Reveal
    const heroTimeline = gsap.timeline();
    heroTimeline.from('.split-text', {
        y: 100,
        opacity: 0,
        duration: 1.5,
        stagger: 0.2,
        ease: 'power4.out',
        delay: 0.5
    }).from('#hero-bg', {
        scale: 1.5,
        duration: 2.5,
        ease: 'power2.out'
    }, 0);

    // 3. Reveal Cards on Scroll
    const reveals = document.querySelectorAll('.reveal-card, .border-glitch');
    reveals.forEach((reveal) => {
        ScrollTrigger.create({
            trigger: reveal,
            start: 'top 80%',
            onEnter: () => reveal.classList.add('is-inview')
        });
    });

    // 4. Parallax Sections
    gsap.to('#hero-bg', {
        yPercent: 30,
        ease: 'none',
        scrollTrigger: {
            trigger: '#hero',
            start: 'top top',
            end: 'bottom top',
            scrub: true
        }
    });

    // 5. Counter Animations
    const counters = document.querySelectorAll('.counter');
    counters.forEach((counter) => {
        const target = parseInt(counter.getAttribute('data-target'));
        ScrollTrigger.create({
            trigger: counter,
            start: 'top 90%',
            onEnter: () => {
                let count = { value: 0 };
                gsap.to(count, {
                    value: target,
                    duration: 2,
                    ease: 'power2.out',
                    onUpdate: () => {
                        counter.innerText = Math.floor(count.value);
                    }
                });
            }
        });
    });

    // 6. Stack Cube Tilt
    const cube = document.getElementById('stack-cube');
    if (cube) {
        ScrollTrigger.create({
            trigger: '#stack-cube',
            start: 'top bottom',
            end: 'bottom top',
            onUpdate: (self) => {
                gsap.to(cube, {
                    rotateY: (self.progress - 0.5) * 60,
                    rotateX: (self.progress - 0.5) * -40,
                    duration: 0.5,
                    ease: 'power1.out'
                });
            }
        });
    }

    // 7. Navbar Hide/Show
    let lastScroll = 0;
    const nav = document.getElementById('main-nav');
    lenis.on('scroll', ({ scroll }) => {
        const scrollingDown = scroll > lastScroll;
        const pastThreshold = scroll > 60;

        if (scrollingDown && pastThreshold) {
            // Hide quickly when scrolling down
            gsap.to(nav, {
                y: -100,
                opacity: 0,
                duration: 0.35,
                ease: 'power2.in',
                overwrite: 'auto'
            });
        } else {
            // Show smoothly and quickly on any upward scroll
            gsap.to(nav, {
                y: 0,
                opacity: 1,
                duration: 0.7,
                ease: 'power3.out',
                overwrite: 'auto'
            });
        }
        lastScroll = scroll;
    });

    // 8. Pinned Horizontal Projects Scroller with Background Animation
    initProjectsBackgroundLines();
    initPinnedProjects();

    // 9. Interactive Word Proximity Effect
    initWordProximityEffect();

    // 10. Profile Image Mask Reveal
    initProfileMaskReveal();

    // 11. Introduction Text Reveal
    initIntroTextReveal();

    // 12. Case Studies Redesigned Animations
    initCurtainReveal();
    initFloatingNumbers();
    initCaseTextReveal();

    // 13. Pinned Testimonials Horizontal Scroll
    initPinnedTestimonials();

    // 14. Magnetic Buttons
    initMagneticButtons();

    // 15. Pinned Horizontal Timeline
    initPinnedTimeline();

    // ── CRITICAL: Sort ScrollTriggers by DOM order ──
    // Because sections are dynamic and we have multiple pinned sections 
    // that add pin-spacers, we must ensure they calculate their offsets top-to-bottom.
    ScrollTrigger.sort();
    ScrollTrigger.refresh();
    document.fonts?.ready.then(() => ScrollTrigger.refresh(true));
};

/**
 * Smooth reveal for introduction text
 * Title reveals word-by-word, description fades in smoothly
 */
function initIntroTextReveal() {
    const titleElements = document.querySelectorAll('[data-title-reveal]');
    const descElements = document.querySelectorAll('[data-desc-reveal]');

    titleElements.forEach(title => {
        const split = new SplitType(title, { types: 'words' });

        gsap.from(split.words, {
            y: 30,
            opacity: 0,
            duration: 1.5,
            stagger: 0.1,
            ease: 'power3.out',
            scrollTrigger: {
                trigger: title,
                start: 'top bottom-=100',
                toggleActions: 'play none none none'
            },
            onComplete: () => {
                // Return to static state for clean interaction
                gsap.set(split.words, { clearProps: 'all' });
            }
        });
    });

    descElements.forEach(desc => {
        gsap.from(desc, {
            opacity: 0,
            y: 20,
            filter: 'blur(10px)',
            duration: 2,
            delay: 0.3,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: desc,
                start: 'top bottom-=100',
                toggleActions: 'play none none none'
            }
        });
    });
}

/**
 * Geometric mask reveal for profile image
 * Triggers when entering viewport
 */
function initProfileMaskReveal() {
    const revealContainers = document.querySelectorAll('[data-profile-reveal]');
    if (revealContainers.length === 0) return;

    revealContainers.forEach(container => {
        const image = container.querySelector('.profile-image-reveal');
        if (!image) return;

        gsap.set(image, { scale: 0, opacity: 0 });

        gsap.to(image, {
            scale: 1,
            opacity: 1,
            rotate: 180,
            duration: 2,
            ease: 'power3.out',
            scrollTrigger: {
                trigger: container,
                start: 'top bottom+=300',
                toggleActions: 'play none none none'
            },
            onComplete: () => {
                // Ensure the transform is clean and return to a perfect circle
                gsap.set(image, { clearProps: 'transform,clip-path' });
            }
        });
    });
}

/**
 * Interactive proximity effect for text words
 * Words turn green when the mouse is nearby
 */
function initWordProximityEffect() {
    const textElements = document.querySelectorAll('[data-word-proximity]');
    if (textElements.length === 0) return;

    textElements.forEach(el => {
        const split = new SplitType(el, { types: 'words' });
        const words = split.words;

        const updateColor = (x, y) => {
            words.forEach(word => {
                const rect = word.getBoundingClientRect();
                const wordX = rect.left + rect.width / 2;
                const wordY = rect.top + rect.height / 2;

                const distance = Math.sqrt(
                    Math.pow(x - wordX, 2) + Math.pow(y - wordY, 2)
                );

                const threshold = 150;

                if (distance < threshold) {
                    const power = 1 - (distance / threshold);
                    gsap.to(word, {
                        color: gsap.utils.interpolate('#ffffffb3', '#00ff64', power),
                        duration: 0.3,
                        ease: 'power1.out'
                    });
                } else {
                    gsap.to(word, {
                        color: '#ffffffb3',
                        duration: 0.5,
                        ease: 'power1.out'
                    });
                }
            });
        };

        window.addEventListener('mousemove', (e) => updateColor(e.clientX, e.clientY));

        window.addEventListener('touchmove', (e) => {
            if (e.touches && e.touches[0]) {
                updateColor(e.touches[0].clientX, e.touches[0].clientY);
            }
        }, { passive: true });

        window.addEventListener('touchstart', (e) => {
            if (e.touches && e.touches[0]) {
                updateColor(e.touches[0].clientX, e.touches[0].clientY);
            }
        }, { passive: true });
    });
}

/**
 * Animated background lines for projects section
 * Creates multi-movement visual effect with lines moving vertically
 * while the horizontal scroll continues
 */
function initProjectsBackgroundLines() {
    const section = document.querySelector('[data-projects-section]');
    const bgLines = document.querySelector('[data-projects-bg-lines]');

    if (!section || !bgLines) return;

    const lines = bgLines.querySelectorAll('.projects-bg-line-h');
    const accentLines = bgLines.querySelectorAll('.projects-accent-line-h');

    // Horizontal lines moving UP at different speeds (parallax effect)
    lines.forEach((line, index) => {
        const speed = 0.8 + (index % 5) * 0.4;
        const direction = -1; // Move UP

        gsap.to(line, {
            y: `${direction * 200 * speed}px`,
            x: `${(index % 2 === 0 ? 1 : -1) * 150}px`, // Horizontal drift
            ease: 'none',
            scrollTrigger: {
                trigger: section,
                start: 'top bottom',
                end: 'bottom top',
                scrub: 1 + (index * 0.1),
            }
        });
    });

    // Accent lines
    accentLines.forEach((line, index) => {
        const delay = index * 0.2;

        ScrollTrigger.create({
            trigger: section,
            start: 'top 80%',
            onEnter: () => {
                gsap.to(line, {
                    opacity: 1,
                    duration: 0.8,
                    delay: delay,
                    ease: 'power2.out'
                });
                line.classList.add('is-visible');
            },
            onLeaveBack: () => {
                gsap.to(line, {
                    opacity: 0,
                    duration: 0.5,
                    ease: 'power2.out'
                });
                line.classList.remove('is-visible');
            }
        });

        const moveAmount = -400 - (index * 200);
        gsap.to(line, {
            y: moveAmount,
            ease: 'none',
            scrollTrigger: {
                trigger: section,
                start: 'top bottom',
                end: 'bottom top',
                scrub: 1.5
            }
        });
    });

    // Container rotation
    gsap.to(bgLines, {
        rotateZ: -1.5,
        ease: 'none',
        scrollTrigger: {
            trigger: section,
            start: 'top bottom',
            end: 'bottom top',
            scrub: 3
        }
    });
}

/**
 * Pinned horizontal scroll for projects section
 * Uses ScrollTrigger pin + scrub
 */
function initPinnedProjects() {
    const section = document.querySelector('[data-projects-section]');
    const viewport = document.querySelector('[data-projects-viewport]');
    const track = document.querySelector('[data-projects-track]');
    const cards = document.querySelectorAll('[data-project-card]');
    const progressBar = document.querySelector('.projects-progress');

    if (!section || !viewport || !track || cards.length === 0) return;

    const updateCardSpotlight = initCardSpotlight(cards, viewport);

    // Calculate horizontal scroll distance
    const getScrollDistance = () => track.scrollWidth - viewport.clientWidth;

    // Animate track horizontally - Works on ALL screen sizes
    const projectST = gsap.to(track, {
        x: () => -getScrollDistance(),
        ease: 'none',
        onUpdate: updateCardSpotlight,
        scrollTrigger: {
            trigger: viewport,
            start: 'top top',
            end: () => '+=' + getScrollDistance(),
            pin: true,
            anticipatePin: 1,
            scrub: 0.5,
            invalidateOnRefresh: true,
            onRefresh: updateCardSpotlight,
            onUpdate: (self) => {
                updateCardSpotlight();
                if (progressBar) {
                    gsap.set(progressBar, { width: `${self.progress * 100}%` });
                }

                // Skew track based on velocity
                const velocity = self.getVelocity();
                const skew = gsap.utils.clamp(-5, 5, velocity / 300);
                gsap.to(track, {
                    skewX: skew,
                    duration: 0.5,
                    ease: 'power2.out',
                    overwrite: 'auto'
                });
            }
        }
    });

    // 3D Tilt for cards
    initProjectCardsTilt(cards);

    // Let ScrollTrigger handle real resizes; defer late image measurements until scrolling settles.
    const images = track.querySelectorAll('img');
    images.forEach((img) => {
        if (!img.complete) {
            img.addEventListener('load', () => ScrollTrigger.refresh(true), { once: true });
        }
    });

}

/**
 * Premium hover/focus spotlight effect for project cards
 * Lifts target card, dims siblings
 */
function initCardSpotlight(cards, viewport) {
    const automaticSpotlight = window.matchMedia('(max-width: 1023px), (hover: none), (pointer: coarse)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let spotlightCard = null;

    const activateSpotlight = (targetCard) => {
        if (spotlightCard === targetCard) return;
        spotlightCard = targetCard;
        cards.forEach((card) => {
            if (card === targetCard) {
                card.classList.add('is-spotlight');
                card.classList.remove('is-dimmed');
            } else {
                card.classList.add('is-dimmed');
                card.classList.remove('is-spotlight');
            }
        });
    };

    const deactivateSpotlight = () => {
        spotlightCard = null;
        cards.forEach((card) => {
            card.classList.remove('is-spotlight', 'is-dimmed');
        });
    };

    const updateCenteredCard = () => {
        if (!automaticSpotlight.matches || reducedMotion.matches) return;

        const bounds = viewport.getBoundingClientRect();
        if (bounds.bottom <= 0 || bounds.top >= window.innerHeight) {
            deactivateSpotlight();
            return;
        }

        let centeredCard = null;
        let nearestDistance = Infinity;
        cards.forEach((card) => {
            const rect = card.getBoundingClientRect();
            if (rect.right <= 0 || rect.left >= window.innerWidth) return;
            const distance = Math.abs((rect.left + rect.right) / 2 - window.innerWidth / 2);
            if (distance < nearestDistance) {
                centeredCard = card;
                nearestDistance = distance;
            }
        });

        if (centeredCard) activateSpotlight(centeredCard);
        else deactivateSpotlight();
    };

    const refreshSpotlightMode = () => {
        deactivateSpotlight();
        updateCenteredCard();
    };
    automaticSpotlight.addEventListener('change', refreshSpotlightMode);
    reducedMotion.addEventListener('change', refreshSpotlightMode);
    const visibilityObserver = new IntersectionObserver(updateCenteredCard);
    visibilityObserver.observe(viewport);

    cards.forEach((card) => {
        // Mouse events
        card.addEventListener('mouseenter', () => {
            if (!automaticSpotlight.matches) activateSpotlight(card);
        });
        card.addEventListener('mouseleave', () => {
            if (!automaticSpotlight.matches) deactivateSpotlight();
        });

        // Focus events (keyboard accessibility)
        card.addEventListener('focusin', () => activateSpotlight(card));
        card.addEventListener('focusout', (e) => {
            // Only deactivate if focus leaves all cards
            const relatedTarget = e.relatedTarget;
            const isStillInCards = Array.from(cards).some((c) => c.contains(relatedTarget));
            if (!isStillInCards) {
                deactivateSpotlight();
                updateCenteredCard();
            }
        });
    });

    return updateCenteredCard;
}

/**
 * Multi-layer curtain reveal for case study images
 */
function initCurtainReveal() {
    const containers = document.querySelectorAll('[data-curtain-container]');
    containers.forEach(container => {
        const curtains = container.querySelectorAll('.curtain-layer');
        const img = container.querySelector('[data-curtain-img]');
        if (!img) return;

        gsap.set(img, { opacity: 0, scale: 1.25, filter: 'blur(16px)' });

        const tl = gsap.timeline({
            scrollTrigger: {
                trigger: container,
                start: 'top 75%',
                toggleActions: 'play none none none'
            }
        });

        // Triple sweep with high-contrast stagger
        tl.to(curtains, {
            x: '101%',
            duration: 1.1,
            stagger: 0.1,
            ease: 'expo.inOut'
        })
            .to(img, {
                opacity: 1,
                scale: 1,
                filter: 'blur(0px)',
                duration: 1.0,
                ease: 'power2.out'
            }, '-=1.2');
    });
}

/**
 * Floating parallax effect for large background numbers
 */
function initFloatingNumbers() {
    const numbers = document.querySelectorAll('[data-case-number]');
    numbers.forEach(number => {
        gsap.to(number, {
            y: -150,
            rotate: 8,
            scale: 1.1,
            ease: 'sine.inOut',
            scrollTrigger: {
                trigger: number,
                start: 'top bottom',
                end: 'bottom top',
                scrub: 1.5
            }
        });
    });
}

/**
 * Refined staggered text reveal for case studies
 * Sets initial state in JS so content is readable if JS fails
 */
function initCaseTextReveal() {
    const cards = document.querySelectorAll('[data-case-card]');
    cards.forEach(card => {
        const tags = card.querySelectorAll('.case-tag');
        const title = card.querySelector('h3');
        const desc = card.querySelector('p');
        const btn = card.querySelector('[data-magnetic]');

        const textElements = [];
        if (tags.length) textElements.push(...tags);
        if (title) textElements.push(title);
        if (desc) textElements.push(desc);
        if (btn) textElements.push(btn);

        if (textElements.length) {
            gsap.set(textElements, { yPercent: 110, opacity: 0 });
        }

        const tl = gsap.timeline({
            scrollTrigger: {
                trigger: card,
                start: 'top 70%', // Trigger slightly later for text
                toggleActions: 'play none none none'
            }
        });

        if (tags.length) {
            tl.to(tags, {
                yPercent: 0,
                opacity: 1,
                duration: 1,
                stagger: 0.1,
                ease: 'power3.out'
            });
        }

        if (title) {
            tl.to(title, {
                yPercent: 0,
                opacity: 1,
                duration: 1.2,
                ease: 'power4.out'
            }, '-=0.4');
        }

        if (desc || btn) {
            const targets = [];
            if (desc) targets.push(desc);
            if (btn) targets.push(btn);
            tl.to(targets, {
                yPercent: 0,
                opacity: 1,
                duration: 1.2,
                stagger: 0.1,
                ease: 'power3.out'
            }, '-=0.6');
        }
    });
}

/**
 * 3D Tilt effect for project cards
 */
function initProjectCardsTilt(cards) {
    cards.forEach(card => {
        const inner = card.querySelector('[data-tilt-inner]');
        if (!inner) return;

        // Set initial perspective
        gsap.set(card, { perspective: 1000 });
        gsap.set(inner, { transformStyle: 'preserve-3d' });

        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = ((y - centerY) / centerY) * -15; // Max 15 degrees
            const rotateY = ((x - centerX) / centerX) * 15;

            gsap.to(inner, {
                rotateX: rotateX,
                rotateY: rotateY,
                duration: 0.5,
                ease: 'power2.out',
                overwrite: 'auto'
            });
        });

        card.addEventListener('mouseleave', () => {
            gsap.to(inner, {
                rotateX: 0,
                rotateY: 0,
                duration: 0.8,
                ease: 'elastic.out(1, 0.3)'
            });
        });
    });
}

/**
 * Magnetic attraction effect for elements
 */
function initMagneticButtons() {
    const magneticElements = document.querySelectorAll('[data-magnetic]');

    magneticElements.forEach(el => {
        const inner = el.querySelector('span');

        el.addEventListener('mousemove', (e) => {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;

            // Move the container slightly
            gsap.to(el, {
                x: x * 0.3,
                y: y * 0.3,
                duration: 0.4,
                ease: 'power2.out'
            });

            // Move the inner span more for a "pull" effect
            if (inner) {
                gsap.to(inner, {
                    x: x * 0.5,
                    y: y * 0.5,
                    duration: 0.4,
                    ease: 'power2.out'
                });
            }
        });

        el.addEventListener('mouseleave', () => {
            gsap.to([el, inner], {
                x: 0,
                y: 0,
                duration: 0.7,
                ease: 'elastic.out(1, 0.3)'
            });
        });
    });
}

/**
 * Pinned stacked-card testimonials – Polished Version
 *
 * Timeline structure per card (except last):
 *   [REST 0.5] → [PEEL 0.5]
 *
 * Enhancements:
 *  - Ambient glow fades in when section is pinned
 *  - Cards peel with slight 3D rotateX for a page-turning feel
 *  - Rising card has a subtle spring overshoot
 *  - Accent line pulses on each new top card
 */
function initPinnedTestimonials() {
    const section = document.querySelector('[data-testimonials-section]');
    const viewport = document.querySelector('[data-testimonials-viewport]');
    const stack = document.querySelector('[data-testimonials-stack]');
    const cards = document.querySelectorAll('[data-testimonial-card]');
    const progressBar = document.querySelector('[data-testimonials-progress]');
    const counterCurrent = document.querySelector('[data-testimonials-current]');
    const ambientGlow = document.querySelector('[data-testimonials-glow]');

    if (!section || !viewport || !stack || cards.length === 0) return;

    const totalCards = cards.length;

    // ── Initial state ──────────────────────────────────
    cards.forEach((card, i) => {
        if (i === 0) {
            gsap.set(card, { y: 0, scale: 1, opacity: 1, rotation: 0 });
        } else if (i === 1) {
            gsap.set(card, { y: 14, scale: 0.96, opacity: 0.35, rotation: 0.6 });
        } else {
            gsap.set(card, { y: 24, scale: 0.92, opacity: 0, rotation: 0 });
        }
    });

    // ── Ambient glow entrance ──────────────────────────
    if (ambientGlow) {
        gsap.to(ambientGlow, {
            opacity: 1,
            duration: 1.5,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: viewport,
                start: 'top 60%',
                toggleActions: 'play none none reverse',
            }
        });
    }

    // ── Timeline math ──────────────────────────────────
    const REST = 0.3;
    const PEEL = 0.5;
    const SEGMENT = REST + PEEL;
    const totalDuration = (totalCards - 1) * SEGMENT + REST;

    const scrollPerCard = 220;
    const totalScrollDistance = scrollPerCard * totalCards;

    // Track for counter highlighting
    let lastIdx = -1;
    const progressGlow = document.querySelector('.testimonials-progress-glow');
    let glowTimeout = null;

    const tl = gsap.timeline({
        scrollTrigger: {
            trigger: viewport,
            start: 'top top',
            end: () => '+=' + totalScrollDistance,
            pin: true,
            anticipatePin: 1,
            scrub: 0.8,
            invalidateOnRefresh: true,
            onUpdate: (self) => {
                // Progress bar
                if (progressBar) {
                    gsap.set(progressBar, { width: `${self.progress * 100}%` });
                }

                // Counter
                if (counterCurrent) {
                    const raw = self.progress * totalDuration;
                    const idx = Math.min(Math.floor(raw / SEGMENT), totalCards - 1);

                    if (idx !== lastIdx) {
                        counterCurrent.textContent = idx + 1;
                        // Quick pop animation on counter change
                        gsap.fromTo(counterCurrent, {
                            scale: 1.4,
                            opacity: 0.5,
                        }, {
                            scale: 1,
                            opacity: 1,
                            duration: 0.35,
                            ease: 'back.out(2)',
                            overwrite: 'auto',
                        });
                        lastIdx = idx;
                    }
                }

                // Glow dot: shine on scroll, dim immediately after
                if (progressGlow) {
                    gsap.set(progressGlow, {
                        opacity: 1,
                        scale: 1.5,
                        boxShadow: '0 0 12px 3px rgba(0,255,100,1), 0 0 30px 6px rgba(0,255,100,0.5), 0 0 50px 10px rgba(0,255,100,0.2)',
                    });
                    clearTimeout(glowTimeout);
                    glowTimeout = setTimeout(() => {
                        gsap.set(progressGlow, {
                            opacity: 0.4,
                            scale: 1,
                            boxShadow: '0 0 6px 1px rgba(0,255,100,0.4), 0 0 12px 2px rgba(0,255,100,0.15)',
                        });
                    }, 80);
                }
            }
        }
    });

    // ── Build the timeline ─────────────────────────────
    for (let i = 0; i < totalCards - 1; i++) {
        const peelStart = i * SEGMENT + REST;

        // Card i: peel away with page-turn feel
        tl.to(cards[i], {
            y: 180,
            opacity: 0,
            scale: 0.78,
            rotation: -5,
            duration: PEEL,
            ease: 'power3.in',
        }, peelStart);

        // Card i+1: rise into top position with overshoot
        tl.to(cards[i + 1], {
            y: 0,
            scale: 1,
            opacity: 1,
            rotation: 0,
            duration: PEEL,
            ease: 'back.out(1.2)',
        }, peelStart);

        // Card i+2 (if exists): slide into peek position
        if (i + 2 < totalCards) {
            tl.to(cards[i + 2], {
                y: 14,
                scale: 0.96,
                opacity: 0.35,
                rotation: 0.6,
                duration: PEEL,
                ease: 'power2.out',
            }, peelStart);
        }

        // Animate accent line on the new top card
        const accentLine = cards[i + 1]?.querySelector('.testimonial-accent-line');
        if (accentLine) {
            tl.fromTo(accentLine,
                { opacity: 0, scaleX: 0 },
                { opacity: 1, scaleX: 1, duration: PEEL * 0.8, ease: 'power2.out' },
                peelStart + PEEL * 0.3
            );
        }
    }

    // Set total timeline duration
    tl.totalDuration(totalDuration);
}

/**
 * Pinned horizontal-scroll timeline section
 *
 * Uses ScrollTrigger pin + scrub to scroll timeline cards horizontally.
 * Includes progress bar, counter, card entrance animations, and
 * connector line that fills progressively.
 */
function initPinnedTimeline() {
    const section = document.querySelector('[data-timeline-section]');
    const viewport = document.querySelector('[data-timeline-viewport]');
    const track = document.querySelector('[data-timeline-track]');
    const cards = document.querySelectorAll('[data-timeline-card]');
    const progressBar = document.querySelector('[data-timeline-progress]');
    const counterCurrent = document.querySelector('[data-timeline-current]');
    const ambientGlow = document.querySelector('[data-timeline-glow]');
    const connectorProgress = document.querySelector('[data-timeline-connector-progress]');

    if (!section || !viewport || !track || cards.length === 0) return;

    const totalCards = cards.length;

    // ── Calculate horizontal scroll distance ──────────
    // Ensure minimum distance so GSAP always has a timeline to scrub, even with 1 card
    const getScrollDistance = () => Math.max(viewport.clientWidth * 0.5, track.scrollWidth - viewport.clientWidth + 150);

    // ── Ambient glow entrance ─────────────────────────
    if (ambientGlow) {
        gsap.to(ambientGlow, {
            opacity: 1,
            duration: 1.5,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: viewport,
                start: 'top 60%',
                toggleActions: 'play none none reverse',
            }
        });
    }

    // ── Initial card state (staggered hidden) ─────────
    cards.forEach((card, i) => {
        const yearEl = card.querySelector('[data-timeline-year]');
        const body = card.querySelector('[data-timeline-card-body]');
        const node = card.querySelector('[data-timeline-node]');

        gsap.set(yearEl, { y: -30, opacity: 0 });
        gsap.set(body, { y: 40, opacity: 0 });
        gsap.set(node, { scale: 0, opacity: 0 });
    });

    // ── Card entrance animations (viewport trigger) ──
    ScrollTrigger.create({
        trigger: viewport,
        start: 'top 60%',
        onEnter: () => {
            cards.forEach((card, i) => {
                const yearEl = card.querySelector('[data-timeline-year]');
                const body = card.querySelector('[data-timeline-card-body]');
                const node = card.querySelector('[data-timeline-node]');
                
                // Cap delay so off-screen cards don't animate 10 seconds later
                const delay = Math.min(i * 0.15, 1.5);

                gsap.to(yearEl, { y: 0, opacity: 1, duration: 0.8, delay: delay, ease: 'power3.out' });
                gsap.to(node, { scale: 1, opacity: 1, duration: 0.6, delay: delay + 0.1, ease: 'back.out(2)' });
                gsap.to(body, { y: 0, opacity: 1, duration: 1, delay: delay + 0.15, ease: 'power3.out' });
            });
        }
    });

    // ── Track for counter + progress glow ─────────────
    let lastIdx = -1;
    const progressGlow = document.querySelector('.timeline-progress-glow');
    let glowTimeout = null;

    // ── Horizontal scroll animation ──────────────────
    const timelineST = gsap.to(track, {
        x: () => {
            // Only translate if track is wider than viewport
            const maxScroll = track.scrollWidth - viewport.clientWidth + 100;
            return maxScroll > 0 ? -maxScroll : 0;
        },
        ease: 'none',
        scrollTrigger: {
            trigger: viewport,
            start: 'top top',
            end: () => '+=' + getScrollDistance(),
            pin: true,
            anticipatePin: 1,
            scrub: 0.5,
            invalidateOnRefresh: true,
            onUpdate: (self) => {
                // Progress bar
                if (progressBar) {
                    gsap.set(progressBar, { width: `${self.progress * 100}%` });
                }

                // Connector line fill and active states
                if (connectorProgress) {
                    gsap.set(connectorProgress, { width: `${self.progress * 100}%` });

                    // Highlight cards when the connector line reaches them
                    const trackRect = track.getBoundingClientRect();
                    const lineRightEdge = trackRect.left + trackRect.width * self.progress;

                    cards.forEach(card => {
                        const node = card.querySelector('[data-timeline-node]');
                        if (node) {
                            const nodeRect = node.getBoundingClientRect();
                            const nodeCenter = nodeRect.left + nodeRect.width / 2;
                            
                            // Add a small buffer (e.g. 10px) so it lights up just as it touches
                            if (nodeCenter <= lineRightEdge + 10) {
                                card.classList.add('is-reached');
                            } else {
                                card.classList.remove('is-reached');
                            }
                        }
                    });
                }

                // Counter
                if (counterCurrent && totalCards > 0) {
                    const idx = Math.min(
                        Math.floor(self.progress * totalCards),
                        totalCards - 1
                    );

                    if (idx !== lastIdx) {
                        counterCurrent.textContent = idx + 1;
                        gsap.fromTo(counterCurrent, {
                            scale: 1.4,
                            opacity: 0.5,
                        }, {
                            scale: 1,
                            opacity: 1,
                            duration: 0.35,
                            ease: 'back.out(2)',
                            overwrite: 'auto',
                        });
                        lastIdx = idx;
                    }
                }

                // Velocity skew on track
                const velocity = self.getVelocity();
                const skew = gsap.utils.clamp(-5, 5, velocity / 300);
                gsap.to(track, {
                    skewX: skew,
                    duration: 0.5,
                    ease: 'power2.out',
                    overwrite: 'auto'
                });

                // Glow dot: shine on scroll
                if (progressGlow) {
                    gsap.set(progressGlow, {
                        opacity: 1,
                        scale: 1.5,
                        boxShadow: '0 0 12px 3px rgba(0,255,100,1), 0 0 30px 6px rgba(0,255,100,0.5), 0 0 50px 10px rgba(0,255,100,0.2)',
                    });
                    clearTimeout(glowTimeout);
                    glowTimeout = setTimeout(() => {
                        gsap.set(progressGlow, {
                            opacity: 0.4,
                            scale: 1,
                            boxShadow: '0 0 6px 1px rgba(0,255,100,0.4), 0 0 12px 2px rgba(0,255,100,0.15)',
                        });
                    }, 80);
                }
            }
        }
    });

    // Let ScrollTrigger handle real resizes; defer late image measurements until scrolling settles.
    const images = track.querySelectorAll('img');
    images.forEach((img) => {
        if (!img.complete) {
            img.addEventListener('load', () => ScrollTrigger.refresh(true), { once: true });
        }
    });

}
