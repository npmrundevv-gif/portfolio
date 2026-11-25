import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const port = parseInt(process.env.VITE_PORT || '5173');

    return {
    optimizeDeps: {
        noDiscovery: true,
        include: ['alpinejs', 'axios', 'gsap', 'gsap/ScrollTrigger', 'lenis', 'split-type', 'dompurify', 'marked'],
    },
    server: {
        host: '0.0.0.0',
        port,
        hmr: {
            host: 'localhost',
            clientPort: port,
        },
        watch: {
            usePolling: true,
            ignored: [/(^|[\\/])(vendor|storage|scratch|dist)([\\/]|$)/],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
};
});

