// ESLint (configuration plate) : règles qui trouvent des BUGS, pas de style.
// Le formatage est l'affaire de Prettier (`pnpm lint`) ; ici, variables non
// définies ou inutilisées, code mort, erreurs Svelte 5 (runes, clés {#each}…).
//
//   pnpm lint:js          # ou : make check-front
//   pnpm lint:js --fix    # corrige ce qui peut l'être sans risque
//
// Erreur = bloquant (CI). Avertissement = signalé, à traiter au fil de l'eau.
import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import svelte from 'eslint-plugin-svelte';
import globals from 'globals';

export default defineConfig([
    globalIgnores([
        'public/**',
        'vendor/**',
        'storage/**',
        'bootstrap/**',
        'ml/**',
        '.pnpm-store/**',
        '.claude/**',
        // Copie égarée d'un vendor/ Composer (ne fait pas partie du front).
        'resources/images/vendor/**',
    ]),

    js.configs.recommended,
    svelte.configs.recommended,

    {
        linterOptions: { reportUnusedDisableDirectives: 'warn' },
    },
    {
        files: ['resources/js/**/*.{js,svelte}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
        },
    },
    {
        // Configuration, scripts et tests (Vitest) : environnement Node.
        files: [
            '*.config.js',
            'scripts/**/*.{js,mjs}',
            'resources/docs/**/*.mjs',
            'tests/js/**/*.js',
        ],
        languageOptions: {
            globals: { ...globals.node },
        },
    },

    {
        rules: {
            // `_` en tête = paramètre ou variable volontairement ignoré(e).
            'no-unused-vars': [
                'error',
                {
                    args: 'after-used',
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                    caughtErrors: 'none',
                    ignoreRestSiblings: true,
                },
            ],
            // `catch (_) {}` : échec volontairement silencieux (réseau, JSON).
            'no-empty': ['error', { allowEmptyCatch: true }],

            // Pas une application SvelteKit : pas de resolve() de $app/paths ;
            // les liens passent par Inertia (<Link>, router).
            'svelte/no-navigation-without-resolve': 'off',

            // {@html} : signalé, pas bloquant (contenus de confiance : traductions,
            // textes éditoriaux du projet). À revoir pour tout contenu utilisateur.
            'svelte/no-at-html-tags': 'warn',

            // Idiomes Svelte 5 recommandés, sans bug avéré : avertissements.
            'svelte/require-each-key': 'warn',
            'svelte/prefer-svelte-reactivity': 'warn',
            'svelte/prefer-writable-derived': 'warn',
            'svelte/no-useless-mustaches': 'warn',
            'svelte/no-unused-props': 'warn',
        },
    },
]);
