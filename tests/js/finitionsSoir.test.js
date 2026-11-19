// Finitions du soir (19/11/2026) : constructeurs d'une série sur la fiche ligne (« et N autres »),
// étiquettes de la frise de la carte, liens qui s'ouvrent dans un nouvel onglet (notes de la fiche
// ligne, pages légales), note des voies vertes. Rendus côté serveur (svelte/server).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { BUILDERS_SHOWN, buildersText } from '$lib/materialFacts.js';
import { legalTypography } from '$lib/legalTypography.js';
import { visibleLabels } from '$lib/timelineLabels.js';
import LineMaterials from '../../resources/js/Components/Lines/LineMaterials.svelte';
import LineSourceNotes from '../../resources/js/Components/Lines/LineSourceNotes.svelte';
import LineGreenways from '../../resources/js/Components/Lines/LineGreenways.svelte';

setupI18n({
    locale: 'fr',
    translations: {
        'common.new_tab': 's’ouvre dans un nouvel onglet',
        'lines.section.materials': 'Matériel roulant',
        'materials.type.locomotive': 'Locomotive',
        'materials.facts.builders_more_one': ':list et :count autre',
        'materials.facts.builders_more_other': ':list et :count autres',
        'lines.sources.ohm': 'OpenHistoricalMap',
        'lines.sources.objects_other': ':count objets',
        'lines.sheet.notes.objects_show_other': 'Voir les :count identifiants',
        'greenways.line.title': 'Voie verte',
        'greenways.line.map_link': 'Voir les voies vertes sur la carte',
        'greenways.line.method': 'Méthode.',
        'greenways.line.source': 'Source : OpenStreetMap.',
    },
});

const t = (key, opts) =>
    key === 'materials.facts.builders_more' ? `${opts.list} et ${opts.count} autres` : key;
const source = (path) =>
    readFileSync(fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)), 'utf8');
const NB = '\u00a0';
const names = (n) => Array.from({ length: n }, (_, i) => `C${i + 1}`);

describe('constructeurs d’une série (fiche ligne)', () => {
    it('nomme tous les constructeurs jusqu’à quatre', () => {
        expect(buildersText(['Alstom'], 'Alstom', t)).toBe('Alstom');
        expect(buildersText(names(4), null, t)).toBe('C1, C2, C3, C4');
    });

    it('au-delà, trois noms puis le nombre des autres', () => {
        expect(BUILDERS_SHOWN).toBe(3);
        expect(buildersText(names(13), 'C1, C2, C3, C4, C5, C6, C7, C8…', t)).toBe(
            'C1, C2, C3 et 10 autres',
        );
    });

    it('reprend le texte joint de la base sans liste, et rien sans constructeur', () => {
        expect(buildersText(undefined, 'Henschel', t)).toBe('Henschel');
        expect(buildersText([], null, t)).toBeNull();
    });

    it('la carte de la série n’affiche plus le texte coupé par « … »', () => {
        const body = render(LineMaterials, {
            props: {
                materials: [
                    {
                        slug: 'br-52',
                        name: 'BR 52',
                        type: 'locomotive',
                        builder: 'C1, C2, C3, C4, C5, C6…',
                        builders: names(13),
                        in_service_from: null,
                        in_service_to: null,
                        periods: [],
                    },
                ],
            },
        }).body;
        expect(body).toContain('C1, C2, C3 et 10 autres');
        expect(body).not.toContain('…');
    });
});

describe('étiquettes de la frise de la carte', () => {
    // 1900-1990 : dix décennies régulièrement espacées sur une frise de `w` px, étiquettes de 36 px.
    const decades = (w) => Array.from({ length: 10 }, (_, i) => (i / 9) * w);
    const texts = Array.from({ length: 10 }, (_, i) => String(1900 + i * 10));
    const widths = texts.map(() => 36);
    const extent = (xs, i) =>
        i === 0
            ? [xs[0] - 10, xs[0] + 26]
            : i === 9
              ? [xs[9] - 26, xs[9] + 10]
              : [xs[i] - 18, xs[i] + 18];
    const noTouch = (xs, shown) => {
        const idx = shown.flatMap((v, i) => (v ? [i] : []));
        return idx.every((i, k) => k === 0 || extent(xs, idx[k - 1])[1] + 10 <= extent(xs, i)[0]);
    };

    it('montre toutes les étiquettes quand la place suffit (2 560 px)', () => {
        expect(visibleLabels(decades(1800), widths, texts, 9).every(Boolean)).toBe(true);
    });

    it('une sur deux à 1 280 px, sans que deux étiquettes se touchent', () => {
        const xs = decades(400);
        const shown = visibleLabels(xs, widths, texts, 9);
        expect(noTouch(xs, shown)).toBe(true);
        expect(shown.filter(Boolean).length).toBeGreaterThanOrEqual(5);
        expect(shown[0] && shown[9]).toBe(true);
    });

    it('garde la décennie choisie, même entre deux étiquettes affichées (320 px)', () => {
        const xs = decades(250);
        for (let active = 0; active < 10; active++) {
            const shown = visibleLabels(xs, widths, texts, active);
            expect(shown[active]).toBe(true);
            expect(noTouch(xs, shown)).toBe(true);
        }
    });

    it('estime la largeur d’une étiquette pas encore mesurée', () => {
        const xs = decades(250);
        const shown = visibleLabels(xs, [], texts, 9);
        expect(shown.filter(Boolean).length).toBeLessThan(10);
        expect(shown[9]).toBe(true);
    });

    it('le composant cache l’étiquette sans retirer le point ni sa largeur', () => {
        const css = source('Components/Map/TimelineSlider.svelte');
        expect(css).toMatch(/\.stop\.label-hidden \.stop-label \{\s*visibility: hidden;/);
        expect(css).not.toMatch(/\.stop\.odd/);
    });
});

describe('liens qui s’ouvrent dans un nouvel onglet', () => {
    const mention =
        '<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span class="vff-sr-only"> (s’ouvre dans un nouvel onglet)</span>';

    it('pages légales : icône et mention avant la fermeture du lien', () => {
        const html =
            '<p>Saisir la CNIL : <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</p>';
        expect(legalTypography(html, 'fr', 's’ouvre dans un nouvel onglet')).toBe(
            `<p>Saisir la CNIL${NB}: <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr${mention}</a>.</p>`,
        );
    });

    it('pages légales : un lien imbriqué ou dans le même onglet n’est pas touché', () => {
        const html =
            '<li><a href="/credits">Crédits</a>, <a href="https://x.org" target="_blank"><strong>CC BY-SA 4.0</strong></a></li>';
        const out = legalTypography(html, 'en', 's’ouvre dans un nouvel onglet');
        expect(out).toContain('<a href="/credits">Crédits</a>');
        expect(out).toContain(`</strong>${mention}</a>`);
        expect(out.split('vff-sr-only').length).toBe(2);
        // Sans mention fournie (autres textes), rien n'est ajouté.
        expect(legalTypography(html, 'en')).not.toContain('vff-sr-only');
    });

    it('notes de la fiche ligne : chaque identifiant OpenHistoricalMap l’annonce', () => {
        const urls = [
            'https://www.openhistoricalmap.org/way/1',
            'https://www.openhistoricalmap.org/way/2',
        ];
        const body = render(LineSourceNotes, {
            props: { notes: [{ n: 1, source: 'ohm', urls }] },
        }).body;
        const links = body.match(/<a [^>]*target="_blank"[^>]*>.*?<\/a>/gs) ?? [];
        expect(links).toHaveLength(2);
        for (const link of links) expect(link).toContain('nouvel onglet');
    });
});

describe('voies vertes de la fiche ligne', () => {
    const greenways = {
        total_length_m: 15200,
        osm_date: null,
        items: [
            {
                name: 'Voie verte',
                shared_length_m: 15200,
                way_count: 3,
                opened_year: null,
                osm_url: 'https://www.openstreetmap.org/relation/1',
            },
        ],
    };

    it('le lien vers la carte reste dans le site (Link d’Inertia, pas de nouvel onglet)', () => {
        const body = render(LineGreenways, { props: { greenways } }).body;
        const link = body.match(/<a [^>]*href="\/maps\?layer=greenways"[^>]*>/)?.[0] ?? '';
        expect(link).not.toBe('');
        expect(link).not.toContain('target=');
        expect(source('Components/Lines/LineGreenways.svelte')).toMatch(
            /<Link class="vff-link" href="\/maps\?layer=greenways">/,
        );
    });

    it('la note de méthode a la taille et l’interligne du guide', () => {
        const css = source('Components/Lines/LineGreenways.svelte');
        // Dernière règle `.note` (la première est partagée avec `.more`).
        const note = [...css.matchAll(/\n {4}\.note \{([^}]*)\}/g)].at(-1)?.[1] ?? '';
        expect(note).toContain('font-size: var(--vff-fs-sm)');
        expect(note).toContain('line-height: var(--vff-lh-body)');
    });
});
