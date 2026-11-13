import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Sem CDN externos: tipos de letra (Fontsource, OFL) e bibliotecas são empacotados com o site.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        cssCodeSplit: true,
        assetsInlineLimit: 0,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
