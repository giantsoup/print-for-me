import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        viteStaticCopy({
            targets: [
                {
                    src: 'resources/images/website-logo.png',
                    dest: '../',
                    rename: 'favicon.png',
                },
                {
                    src: 'resources/images/website-logo.png',
                    dest: '../',
                    rename: 'apple-touch-icon.png',
                },
                {
                    src: 'resources/images/website-logo.png',
                    dest: '../',
                    rename: 'website-logo.png',
                },
            ],
        }),
    ],
});
