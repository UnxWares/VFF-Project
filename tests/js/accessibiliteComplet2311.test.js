import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// RGAA 4.1, passe complète du 23/11 : axe signalait ces deux écarts sur l'accueil.
describe('accessibilité de l’accueil', () => {
    it('la figure d’archive garde son nom sans role="group" interdit sur figure', () => {
        const src = readFileSync('resources/js/Components/Sections/ArchiveFigure.svelte', 'utf8');
        expect(src).toContain('<figure class="vff-figure archive" aria-label={legend}>');
        expect(src).not.toContain('role="group"');
    });
    it('le tableau des chiffres est une région focalisable nommée', () => {
        const src = readFileSync('resources/js/Components/Sections/Timeline.svelte', 'utf8');
        expect(src).toMatch(/class="vff-table-wrap"\s+tabindex="0"\s+role="region"/);
    });
});
