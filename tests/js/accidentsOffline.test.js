// Page des accidents rendue depuis une copie hors ligne qui n'a pas ses menus de filtres : `facets` est une
// prop « once » d'Inertia, absente d'une visite partie de /accidents (revue des PR #19 et #20, point 1).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { render } from 'svelte/server';
import { describe, expect, it, vi } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import AccidentsIndex from '../../resources/js/Pages/Accidents/Index.svelte';

// Le gabarit (en-tête, pied de page, images) n'a rien à voir ici : la page est rendue sans lui.
vi.mock('$components/Layout.svelte', async () => ({
    default: (await import('./support/LayoutStub.svelte')).default,
}));

// Vraie réponse Inertia du serveur de développement (réduite), rangée sans `facets` ni `translations`.
const copy = JSON.parse(
    readFileSync(
        fileURLToPath(
            new URL('./fixtures/offline/accidents-decade-1950.inertia.json', import.meta.url),
        ),
        'utf8',
    ),
);

describe('page des accidents sans ses menus de filtres', () => {
    it('affiche la liste au lieu de s arrêter sur une erreur', () => {
        setupI18n({ locale: 'fr', translations: {} });
        expect(copy.props).not.toHaveProperty('facets');

        const { body } = render(AccidentsIndex, {
            props: { accidents: copy.props.accidents, filters: copy.props.filters },
        });
        for (const accident of copy.props.accidents.data) {
            expect(body).toContain(accident.name);
        }
    });
});
