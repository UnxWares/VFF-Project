import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import laravel from 'laravel-vite-plugin';
import { serviceWorkerPlugin } from './resources/js/pwa/vite-plugin-sw.js';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/general.css',
                'resources/js/app.js',
                // Documentation de l'API (/docs/api Scalar, /docs/swagger Swagger UI, /graphiql GraphiQL).
                'resources/js/docs/scalar.js',
                'resources/js/docs/swagger.js',
                'resources/js/docs/graphiql.js',
            ],
            refresh: ['resources/views/**', 'routes/**'],
        }),
        svelte(),
        // Service worker de la PWA : public/sw.js à la fin d'un build (jamais en développement).
        serviceWorkerPlugin(),
    ],
    // Scalar (/docs/api) embarque Vue : indicateurs de compilation que le paquet attend (sans eux, un
    // avertissement apparaît dans la console de la page de documentation).
    define: {
        __VUE_OPTIONS_API__: 'true',
        __VUE_PROD_DEVTOOLS__: 'false',
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
    },
    server: {
        watch: {
            // Données volumineuses hors front (tuiles IGN, caches, rapports, ML) : sans
            // cette exclusion, Vite épuise les surveillances inotify (ENOSPC) et s'arrête.
            ignored: [
                '**/storage/**',
                '**/ml/**',
                '**/vendor/**',
                '**/.pnpm-store/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
                '**/public/docs/**',
            ],
        },
    },
    resolve: {
        alias: {
            $lib: '/resources/js/lib',
            $components: '/resources/js/Components',
            $pages: '/resources/js/Pages',
            $images: '/resources/images',
            $css: '/resources/css',
        },
    },
});
