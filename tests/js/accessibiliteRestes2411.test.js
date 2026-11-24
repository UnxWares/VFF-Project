import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// RGAA 4.1, restes de la passe du 23/11 (axe-core) corrigés le 24/11.
const read = (path) => readFileSync(path, 'utf8');

describe('accessibilité : restes de la passe axe', () => {
    it('les appels de note du journal sont soulignés (link-in-text-block)', () => {
        const src = read('resources/js/Pages/Journal/Show.svelte');
        const rule = src.match(/\.journal-prose :global\(\.footnote-ref\) \{[^}]*\}/)?.[0] ?? '';
        expect(rule).toContain('text-decoration: underline');
        expect(rule).not.toContain('text-decoration: none');
    });

    it('un tableau des pages légales ne porte pas le nom de sa section (landmark-unique)', () => {
        const src = read('resources/js/Components/LegalPage.svelte');
        expect(src).toContain("$i18n.t('common.table_of', { title })");
        for (const lang of ['fr', 'en', 'de'])
            expect(read(`lang/${lang}/common.php`)).toMatch(/'table_of' => '[^']*:title'/);
    });

    it('la barre des documentations tient à 320 px : l’icône du menu s’efface', () => {
        const css = read('resources/css/docs/docs.css');
        const block = css.match(/@media \(max-width: 380px\) \{[\s\S]*?\n\}/)?.[0] ?? '';
        expect(block).toContain('.vff-docs-menu-button svg');
        expect(block).toContain('display: none');
    });

    it('la licence d’un crédit technique passe à la ligne au lieu de déborder', () => {
        const src = read('resources/js/Pages/Credits/Tech.svelte');
        expect(src).toMatch(/\.head :global\(\.vff-badge\) \{[^}]*white-space: normal/);
    });
});
