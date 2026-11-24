import gsap from 'gsap';

export const initStack3D = () => {
    const stackList = document.getElementById('stack-list');
    const cube = document.getElementById('stack-cube');
    const cubeImage = document.getElementById('cube-image');
    const cubeText = document.getElementById('cube-text');

    if (!stackList || !cube || !cubeImage || !cubeText) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let activeRow;
    let previewAnimation;

    const selectRow = (row) => {
        if (activeRow === row) return;

        activeRow?.classList.remove('is-active');
        activeRow?.setAttribute('aria-pressed', 'false');
        activeRow = row;
        row.classList.add('is-active');
        row.setAttribute('aria-pressed', 'true');

        const { name, image } = row.dataset;
        if (image) {
            cubeImage.src = image;
            cubeImage.alt = name;
        } else {
            cubeText.textContent = name;
        }
        cubeImage.classList.toggle('hidden', !image);
        cubeText.classList.toggle('hidden', Boolean(image));

        previewAnimation?.kill();
        if (reducedMotion.matches) {
            gsap.set(cube, { clearProps: 'transform' });
            return;
        }

        previewAnimation = gsap.fromTo(cube, { rotationY: -20, scale: 0.93 }, {
            rotationY: 12,
            scale: 1,
            duration: 0.35,
            ease: 'power2.out',
            clearProps: 'transform',
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
