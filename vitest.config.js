// Tests JS (utilitaires purs de resources/js/lib) : `bin/pnpm test`.
// Configuration séparée de vite.config.js, qui charge laravel-vite-plugin
// (inutile ici) ; environnement Node, sans DOM. Le plugin Svelte compile les
// runes des modules `.svelte.js` (stores).
import { defineConfig } from 'vitest/config';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [svelte()],
    resolve: {
        alias: {
            $lib: fileURLToPath(new URL('./resources/js/lib', import.meta.url)),
            // Composants rendus côté serveur dans les tests (svelte/server), comme dans vite.config.js.
            $components: fileURLToPath(new URL('./resources/js/Components', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
    },
});
