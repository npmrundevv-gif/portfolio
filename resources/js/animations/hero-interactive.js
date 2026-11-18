import gsap from 'gsap';

export function initHeroInteractive() {
    const heroSection = document.getElementById('hero');
    const heroTitle = document.getElementById('hero-title');

    if (!heroSection || !heroTitle) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }


    // Configuration
    const moveIntensity = 30; // Maximum pixels to move
    const tiltIntensity = 5;  // Maximum degrees to tilt (optional)

    // Events
    const handleMove = (x, y) => {
        const rect = heroSection.getBoundingClientRect();

        // Calculate relative position (-1 to 1) from center of hero section
        const relX = (x - rect.left - rect.width / 2) / (rect.width / 2);
        const relY = (y - rect.top - rect.height / 2) / (rect.height / 2);

        // Animate Title
        gsap.to(heroTitle, {
            x: relX * moveIntensity,
            y: relY * moveIntensity,
            rotationX: relY * -tiltIntensity,
            rotationY: relX * tiltIntensity,
            duration: 1,
            ease: 'power2.out'
        });
    };

    // Mouse Move
    heroSection.addEventListener('mousemove', (e) => {
        handleMove(e.clientX, e.clientY);
    });

    // Touch Move
    heroSection.addEventListener('touchmove', (e) => {
        // Prevent default only if necessary, but might interfere with scroll. 
        // For hero parallax, usually fine to let scroll happen unless we want true lock.
        // e.preventDefault(); 
        const touch = e.touches[0];
        handleMove(touch.clientX, touch.clientY);
    });

    // Reset on Leave
    const reset = () => {
        gsap.to(heroTitle, {
            x: 0,
            y: 0,
            rotationX: 0,
            rotationY: 0,
            duration: 1.5,
            ease: 'elastic.out(1, 0.3)'
        });
    };

    heroSection.addEventListener('mouseleave', reset);
    heroSection.addEventListener('touchend', reset);
}
