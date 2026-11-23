// Audit RGAA du 23/11/2026 : étiquette dans le nom accessible (RGAA 6.1, WCAG 2.5.3) des liens et boutons
// dont le nom accessible est posé par aria-label.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const read = (path) =>
    readFileSync(fileURLToPath(new URL(`../../${path}`, import.meta.url)), 'utf8');

describe('lien « Corriger » de la chronique de ligne', () => {
    it.each([
        ['fr', 'Corriger'],
        ['en', 'Correct'],
        ['de', 'Korrigieren'],
    ])('le nom accessible commence par le texte visible (%s)', (lang, visible) => {
        const file = read(`lang/${lang}/lines.php`);
        expect(file).toContain(`'correct' => '${visible}'`);
        expect(file).toMatch(new RegExp(`'correct_label' => '${visible} ?: :event, :date'`));
    });
});

describe('diagramme espace-temps', () => {
    it("le nom accessible d'une ligne reprend le mois abrégé affiché", () => {
        const src = read('resources/js/Components/Lines/LineSpaceTime.svelte');
        expect(src).toContain('rowLabel(p, rows[i] ?? [], shortDates[i])');
        expect(src).toMatch(/`\$\{shown\} \(\$\{full\}\)`/);
    });
});
