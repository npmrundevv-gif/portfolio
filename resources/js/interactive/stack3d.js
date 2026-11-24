import gsap from 'gsap';

export const initStack3D = () => {
    const stackList = document.getElementById('stack-list');
    const cube = document.getElementById('stack-cube');
    const cubeImage = document.getElementById('cube-image');
    const cubeText = document.getElementById('cube-text');

    if (!stackList || !cube || !cubeImage || !cubeText) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeRow;
    let displayedRow;
    let previewAnimation;
    let revealAnimation;
    const cubeMotionProps = '--stack-tilt,--stack-turn,--stack-scale,--stack-glow,backgroundColor';

    const showPreview = () => {
        if (displayedRow === activeRow) return;
        displayedRow = activeRow;

        const { name, image } = displayedRow.dataset;
        if (image) {
            cubeImage.src = image;
            cubeImage.alt = name;
        } else {
            cubeText.textContent = name;
            cubeText.classList.remove('opacity-20');
        }
        cubeImage.classList.toggle('hidden', !image);
        cubeText.classList.toggle('hidden', Boolean(image));

        revealAnimation?.kill();
        gsap.set([cubeImage, cubeText], { clearProps: 'opacity,transform' });
        if (!reducedMotion.matches) {
            revealAnimation = gsap.from(image ? cubeImage : cubeText, {
                opacity: 0,
                scale: image ? 0.5 : 0.85,
                duration: 0.2,
                ease: 'power2.out',
                clearProps: 'opacity,transform',
            });
        }
    };

    const stopMotion = () => {
        previewAnimation?.kill();
        previewAnimation = undefined;
        revealAnimation?.kill();
        gsap.set(cube, { clearProps: cubeMotionProps });
        gsap.set([cubeImage, cubeText], { clearProps: 'opacity,transform' });
    };

    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches && activeRow) {
            stopMotion();
            showPreview();
        }
    });

    const selectRow = (row) => {
        if (activeRow === row) return;

        activeRow?.classList.remove('is-active');
        activeRow?.setAttribute('aria-pressed', 'false');
        activeRow = row;
        row.classList.add('is-active');
        row.setAttribute('aria-pressed', 'true');

        if (reducedMotion.matches) {
            showPreview();
            return;
        }

        // Let the current flip finish. The swap reads the latest selection.
        if (previewAnimation) {
            if (previewAnimation.time() >= previewAnimation.labels.swap) showPreview();
            return;
        }

        previewAnimation = gsap.timeline({
            onComplete: () => { previewAnimation = undefined; },
        })
            .to(cube, {
                '--stack-scale': 0.8,
                '--stack-tilt': '10deg',
                '--stack-turn': '360deg',
                duration: 0.6,
                ease: 'back.in(1.7)',
                '--stack-glow': 0.8,
                backgroundColor: 'rgba(74, 222, 128, 0.1)',
            })
            .addLabel('swap', '-=0.2')
            .call(showPreview, [], 'swap')
            .to(cube, {
                '--stack-scale': 1,
                '--stack-tilt': '0deg',
                duration: 0.5,
                ease: 'elastic.out(1, 0.5)',
                '--stack-glow': 0.2,
                backgroundColor: 'rgba(255, 255, 255, 0.05)',
                clearProps: cubeMotionProps,
            });
    };

    stackList.querySelectorAll('.stack-row').forEach((row) => {
        row.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse' || (event.pointerType === 'pen' && event.buttons === 0)) {
                selectRow(row);
            }
        });
        row.addEventListener('click', () => selectRow(row));
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectRow(row);
            }
        });
    });
};
