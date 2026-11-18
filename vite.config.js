import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/general.css',
                'resources/js/app.js',
                // Documentation de l'API REST (/docs/api Scalar, /docs/swagger Swagger UI).
                'resources/js/docs/scalar.js',
                'resources/js/docs/swagger.js',
            ],
            refresh: ['resources/views/**', 'routes/**'],
        }),
        svelte(),
    ],
    server: {
        watch: {
            // Données volumineuses hors front (tuiles IGN, caches, rapports, ML) : sans
            // cette exclusion, Vite épuise les surveillances inotify (ENOSPC) et s'arrête.
            ignored: ['**/storage/**', '**/ml/**', '**/vendor/**', '**/.pnpm-store/**', '**/bootstrap/cache/**', '**/public/build/**', '**/public/docs/**'],
        },
    },
    resolve: {
        alias: {
            '$lib': '/resources/js/lib',
            '$components': '/resources/js/Components',
            '$pages': '/resources/js/Pages',
            '$images': '/resources/images',
            '$css': '/resources/css',
        },
    },
});
