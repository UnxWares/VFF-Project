// Finitions du soir, deuxième passe (19/11/2026) : constructeurs de l'annuaire du matériel
// (« et N autres » comme la fiche ligne) et passe d'interface à 320 px (cibles tactiles de 44 px sur
// téléphone, périodes de la frise qui tiennent dans leur pilule, tailles de texte minimales).
// Contrôles sur les sources : ces règles CSS ne se vérifient qu'en largeur réelle, mesurée dans le
// navigateur (rapport storage/app/reports/agents/soir/finitions-2.md).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { buildersText } from '$lib/materialFacts.js';

const read = (path) =>
    readFileSync(fileURLToPath(new URL(`../../${path}`, import.meta.url)), 'utf8');
const source = (path) => read(`resources/js/${path}`);
/** Bloc `@media (max-width: 600px) { … }` qui suit `after` dans le texte (accolades équilibrées). */
const phoneBlockAfter = (text, after) => {
    const start = text.indexOf('@media (max-width: 600px)', text.indexOf(after));
    if (start < 0) return '';
    let depth = 0;
    for (let i = text.indexOf('{', start); i < text.length; i++) {
        if (text[i] === '{') depth++;
        if (text[i] === '}' && --depth === 0) return text.slice(start, i + 1);
    }
    return '';
};

describe('annuaire du matériel : constructeurs', () => {
    const page = source('Pages/Materials/Index.svelte');
    const t = (key, opts) =>
        key === 'materials.facts.builders_more' ? `${opts.list} et ${opts.count} autres` : key;

    it('tire le texte de la liste complète, comme la fiche ligne', () => {
        expect(page).toMatch(/buildersText\(m\.builders, m\.builder, t\)/);
        const names = Array.from({ length: 10 }, (_, i) => `C${i + 1}`);
        expect(buildersText(names, 'C1, C2, C3, C4, C5…', t)).toBe('C1, C2, C3 et 7 autres');
    });

    it('ne coupe plus la ligne du constructeur par « … »', () => {
        const rule = page.match(/\.builder \{[^}]*\}/)?.[0] ?? '';
        expect(rule).not.toBe('');
        expect(rule).not.toMatch(/text-overflow|nowrap/);
        expect(page).not.toMatch(/title=\{m\.builder\}/);
    });
});

describe('cibles tactiles de 44 px sur téléphone', () => {
    it('petits boutons (.vff-btn.small, .quiet) : 44 px sous 600 px, après leur règle de 36 px', () => {
        const css = read('resources/css/general.css');
        const block = phoneBlockAfter(css, '.vff-btn.small {');
        expect(block).toMatch(/\.vff-btn\.small,\s*\.vff-btn\.quiet \{\s*min-height: 44px;/);
        // Même spécificité : la règle du téléphone doit venir après celle de 36 px pour l'emporter.
        expect(css.indexOf(block)).toBeGreaterThan(css.indexOf('.vff-btn.small {'));
        expect(css.indexOf(block)).toBeGreaterThan(css.indexOf('.vff-btn.quiet {'));
    });

    it('périodes de la frise, outils des phases, catégories du matériel', () => {
        expect(
            phoneBlockAfter(source('Components/Map/TimelineSlider.svelte'), '.timeline-head'),
        ).toMatch(/\.period \{[^}]*min-height: 44px;/);
        expect(
            phoneBlockAfter(source('Components/Lines/LinePhaseSelector.svelte'), '.tool.best-jump'),
        ).toMatch(/\.tool \{[^}]*min-height: 44px;/);
        expect(phoneBlockAfter(source('Pages/Materials/Index.svelte'), '.categories ul')).toMatch(
            /\.chip\) \{\s*min-height: 44px;/,
        );
    });

    it('bouton « Légende », bouton « Carte » des décennies, chiffres de l’accueil', () => {
        expect(
            phoneBlockAfter(source('Components/Map/MapLegend.svelte'), '.legend-toggle {'),
        ).toMatch(/\.legend-toggle \{\s*min-height: 44px;/);
        expect(phoneBlockAfter(source('Components/Lines/LineDecades.svelte'), '.see {')).toMatch(
            /\.see \{\s*min-height: 44px;/,
        );
        // 24 px de ligne + 2 × 10 px : 44 px.
        expect(
            phoneBlockAfter(source('Components/Sections/Timeline.svelte'), '.figures summary {'),
        ).toMatch(/\.figures summary \{\s*padding-block: 10px;/);
    });
});

describe('périodes de la frise à 320 px', () => {
    it('chaque bouton prend la largeur de son libellé au lieu d’un tiers fixe', () => {
        const block = phoneBlockAfter(
            source('Components/Map/TimelineSlider.svelte'),
            '.timeline-head',
        );
        expect(block).toMatch(/\.period \{[^}]*flex: 1 1 auto;/);
        expect(block).not.toMatch(/\.period \{[^}]*flex: 1;/);
    });
});

describe('tailles de texte', () => {
    it('le code des pages légales ne descend pas sous 14 px', () => {
        expect(source('Components/LegalPage.svelte')).toMatch(
            /font-size: max\(0\.9em, var\(--vff-fs-xs\)\);/,
        );
    });

    it('texte courant en 17 px au moins (étapes de contribution, sources des crédits, accueil)', () => {
        expect(source('Pages/Contribute.svelte')).toMatch(/\.steps p \{[^}]*--vff-fs-body/);
        expect(source('Pages/Credits/Media.svelte')).toMatch(/\.sources p \{[^}]*--vff-fs-body/);
        expect(source('Components/Sections/Timeline.svelte')).toMatch(
            /\.milestones p \{[^}]*--vff-fs-body/,
        );
        expect(read('resources/css/index/main/maps.css')).toMatch(
            /\.maps-box p \{[^}]*font-size: var\(--vff-fs-body\)/,
        );
    });
});

describe('fil des décennies des gares (fiche ligne)', () => {
    it('les cases se resserrent sur une colonne étroite au lieu d’en sortir', () => {
        // Règle des cases du fil (la légende a sa propre règle `.tick`, plus grande).
        const rules =
            source('Components/Lines/LineStations.svelte').match(/\.tick \{[^}]*\}/g) ?? [];
        const rule = rules.find((r) => r.includes('height: 6px')) ?? '';
        expect(rule).toMatch(/flex: 0 1 9px;/);
        expect(rule).toMatch(/min-width: 5px;/);
        expect(rule).not.toMatch(/flex-shrink: 0/);
    });
});
