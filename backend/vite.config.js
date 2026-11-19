import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/storefront.css', 'resources/js/storefront.js', 'resources/images/logo-135.avif', 'resources/images/logo-206.avif'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        assetsInlineLimit: (file) => (file.endsWith('.avif') ? false : undefined), // Vite::asset() needs real files, not data URIs
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
