import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // The admin panel compiles its own stylesheet — see the header
                // comment in theme.css for why it cannot share app.css.
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
            // Two families only, as briefed: a grotesque for everything and a
            // mono for micro-labels. Served from our own origin at build time
            // rather than a font CDN — one less third-party round trip, which
            // matters for the Lighthouse ≥ 90 mobile target.
            fonts: [
                bunny('Inter', { weights: [400, 500, 600] }),
                bunny('JetBrains Mono', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
