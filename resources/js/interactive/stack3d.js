import gsap from 'gsap';

export const initStack3D = () => {
    const stackList = document.getElementById('stack-list');
    const cube = document.getElementById('stack-cube');
    const cubeImage = document.getElementById('cube-image');
    const cubeText = document.getElementById('cube-text');

    if (!stackList || !cube || !cubeImage || !cubeText) return;

    const rows = stackList.querySelectorAll('.stack-row');
    let isAnimating = false;

    rows.forEach(row => {
        const handleSelect = () => {
            if (isAnimating) return;
            isAnimating = true;

            const name = row.getAttribute('data-name');
            const imageUrl = row.getAttribute('data-image');
            const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Highlight active row
            rows.forEach(r => r.classList.remove('is-active'));
            row.classList.add('is-active');

            if (prefersReduced) {
                if (imageUrl) {
                    cubeImage.src = imageUrl;
                    cubeImage.alt = name;
                    cubeImage.classList.remove('hidden');
                    cubeImage.style.display = 'block';
                    cubeText.classList.add('hidden');
                    cubeText.style.display = 'none';
                } else {
                    cubeText.textContent = name;
                    cubeText.classList.remove('hidden');
                    cubeText.style.display = 'block';
                    cubeImage.classList.add('hidden');
                    cubeImage.style.display = 'none';
                }
                isAnimating = false;
                return;
            }

            // --- Futuristic Animation Sequence ---
            const tl = gsap.timeline({
                onComplete: () => { isAnimating = false; }
            });

            // 1. Initial Glitch & Scale Down
            tl.to(cube, {
                scale: 0.8,
                rotationY: "+=360",
                rotationX: "+=10",
                duration: 0.6,
                ease: "back.in(1.7)",
                boxShadow: "0 0 100px rgba(74, 222, 128, 0.8)",
                backgroundColor: "rgba(74, 222, 128, 0.1)"
            });

            // 2. Content Swap
            tl.add(() => {
                if (imageUrl) {
                    cubeImage.src = imageUrl;
                    cubeImage.alt = name;
                    gsap.set(cubeImage, { display: 'block', opacity: 0, scale: 0.5 });
                    gsap.to(cubeImage, { opacity: 1, scale: 1, duration: 0.2, overwrite: true });
                    cubeImage.classList.remove('hidden');
                    cubeText.classList.add('hidden');
                    gsap.set(cubeText, { display: 'none' });
                } else {
                    cubeText.textContent = name;
                    gsap.set(cubeText, { display: 'block', opacity: 0 });
                    gsap.to(cubeText, { opacity: 1, duration: 0.2 });
                    cubeText.classList.remove('hidden');
                    cubeImage.classList.add('hidden');
                    gsap.set(cubeImage, { display: 'none' });
                }
            }, "-=0.2");

            // 3. Recovery & Settle
            tl.to(cube, {
                scale: 1,
                rotationX: 12,
                rotationY: 12,
                duration: 0.5,
                ease: "elastic.out(1, 0.5)",
                boxShadow: "0 0 50px rgba(74, 222, 128, 0.2)",
                backgroundColor: "rgba(255, 255, 255, 0.05)",
                clearProps: "rotationY,rotationX"
            });

            tl.set(cube, { clearProps: "all" });
        };

        row.addEventListener('click', handleSelect);
        row.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleSelect();
            }
        });
    });

};
