import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/general.css', 'resources/js/app.js'],
            refresh: ['resources/views/**', 'routes/**'],
        }),
        svelte(),
    ],
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
