// Phases d'une ligne (fiche ligne) : helpers purs de resources/js/lib/linePhases.js
// et rendu serveur du sélecteur de phases (LinePhaseSelector.svelte).
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    ACTIVE_CATEGORIES,
    CATEGORY_STYLES,
    STRIP_ORDER,
    bestPhaseIndex,
    categoryColor,
    categorySwatch,
    changeVerbKey,
    decadesLegend,
    formatEventDate,
    formatKm,
    maxKnownLength,
    phaseDateLabel,
    phaseFeatureCollection,
    phaseStrip,
    portionsBounds,
    stageNotes,
    stepPhase,
    summarizeChanges,
} from '$lib/linePhases.js';
import { HERITAGE_COLOR, LGV_COLOR, TRAFFIC_COLOR } from '$lib/railStyle.js';

const line = (coords) => ({ type: 'LineString', coordinates: coords });
const PORTIONS = [
    {
        length_m: 10000,
        geometry: line([
            [0, 45],
            [0.1, 45],
        ]),
    },
    {
        length_m: 5000,
        geometry: line([
            [0.1, 45],
            [0.2, 45.05],
        ]),
    },
    {
        length_m: 2000,
        geometry: line([
            [0.2, 45.05],
            [0.25, 45.1],
        ]),
    },
];
const PHASES = [
    {
        date: '1869-12-06',
        precision: 'day',
        from_year: 1861,
        to_year: 1870,
        is_current: false,
        kind: 'growth',
        changes: [
            {
                from: 'none',
                to: 'mixed',
                portions: [0, 1],
                length_m: 15000,
                event_id: 1,
                mode: 'localized',
                inconsistent: false,
            },
        ],
        state: ['mixed', 'mixed', 'none'],
        length_by_category: { mixed: 15000 },
        active_m: 15000,
    },
    {
        date: '1881-03-15',
        precision: 'day',
        from_year: 1881,
        to_year: 1890,
        is_current: false,
        kind: 'growth',
        changes: [
            {
                from: 'none',
                to: 'mixed',
                portions: [2],
                length_m: 2000,
                event_id: 2,
                mode: 'neighbor',
                inconsistent: false,
            },
        ],
        state: ['mixed', 'mixed', 'mixed'],
        length_by_category: { mixed: 17000 },
        active_m: 17000,
    },
    {
        date: '1950-12-31',
        precision: 'range',
        from_year: 1941,
        to_year: 1950,
        is_current: false,
        kind: 'decline',
        changes: [
            {
                from: 'mixed',
                to: 'closed',
                portions: [2],
                length_m: 2000,
                event_id: null,
                mode: null,
                inconsistent: false,
            },
            {
                from: 'mixed',
                to: 'freight',
                portions: [1],
                length_m: 5000,
                event_id: 3,
                mode: 'whole_line',
                inconsistent: false,
            },
            {
                from: 'mixed',
                to: 'mixed',
                portions: [],
                length_m: 150,
                event_id: null,
                mode: null,
                minor: true,
                inconsistent: false,
            },
        ],
        state: ['mixed', 'freight', 'closed'],
        length_by_category: { closed: 2000, freight: 5000, mixed: 10000 },
        active_m: 15000,
    },
    {
        date: '2026-10-19',
        precision: 'range',
        from_year: 2021,
        to_year: 2030,
        is_current: true,
        kind: 'removal',
        changes: [
            {
                from: 'closed',
                to: 'dismantled',
                portions: [2],
                length_m: 2000,
                event_id: null,
                mode: null,
                inconsistent: false,
            },
        ],
        state: ['mixed', 'freight', 'dismantled'],
        length_by_category: { dismantled: 2000, freight: 5000, mixed: 10000 },
        active_m: 15000,
    },
];
// Fonction de traduction minimale : renvoie la clé et ses paramètres.
const t = (key, params = {}) => `${key}${Object.keys(params).length ? JSON.stringify(params) : ''}`;

describe('catégories', () => {
    it('couleurs imposées : LGV violet, mixte orange, fret bleu, patrimoine vert', () => {
        expect(categoryColor('lgv')).toBe('#7c3aed');
        expect(categoryColor('lgv')).toBe(LGV_COLOR);
        expect(categoryColor('mixed')).toBe('#ff6500');
        expect(categoryColor('freight')).toBe('#0284c7');
        expect(categoryColor('heritage')).toBe('#16a34a');
        expect(categoryColor('heritage')).toBe(HERITAGE_COLOR);
        expect(categoryColor('inconnue')).toBe(TRAFFIC_COLOR.unknown);
    });

    it('chaque catégorie du moteur a un style, un libellé lines.categories.* et une place dans le ruban', () => {
        const php = [
            'lgv',
            'mixed',
            'freight',
            'unknown',
            'heritage',
            'velorail',
            'service_track',
            'construction',
            'closed',
            'dismantled',
            'razed',
            'gone',
        ];
        for (const cat of php) {
            expect(CATEGORY_STYLES[cat], cat).toBeDefined();
            expect(CATEGORY_STYLES[cat].labelKey).toBe(`lines.categories.${cat}`);
            expect(STRIP_ORDER).toContain(cat);
        }
        expect(ACTIVE_CATEGORIES).toEqual(['lgv', 'mixed', 'freight', 'unknown', 'heritage']);
    });

    it('mêmes catégories en service que le serveur (LinePhases::ACTIVE)', () => {
        const php = readFileSync(
            new URL('../../app/Services/LinePhases.php', import.meta.url),
            'utf8',
        );
        const active = [
            ...php.match(/const ACTIVE = \[([^\]]*)\]/)[1].matchAll(/'([a-z_]+)'/g),
        ].map((m) => m[1]);
        expect(ACTIVE_CATEGORIES).toEqual(active);
    });

    it('les libellés existent en fr, en et de', () => {
        for (const lang of ['fr', 'en', 'de']) {
            const php = readFileSync(
                new URL(`../../lang/${lang}/lines.php`, import.meta.url),
                'utf8',
            );
            const block = php.slice(php.indexOf("'categories' => ["));
            for (const cat of STRIP_ORDER) expect(block, `${lang}:${cat}`).toContain(`'${cat}' =>`);
        }
    });

    it('pastille : tirets pour fermé, trait épais pour la LGV', () => {
        expect(categorySwatch('closed')).toContain('repeating-linear-gradient');
        expect(categorySwatch('mixed')).not.toContain('repeating-linear-gradient');
        expect(categorySwatch('lgv')).toContain('height:6px');
    });
});

describe('carte d’une phase', () => {
    it('omet les portions pas encore construites et marque celles qui changent', () => {
        const fc = phaseFeatureCollection(PORTIONS, PHASES[0]);
        expect(fc.type).toBe('FeatureCollection');
        expect(fc.features.map((f) => f.id)).toEqual([0, 1]);
        expect(fc.features.every((f) => f.properties.changed)).toBe(true);
        expect(fc.features[0].properties).toMatchObject({
            cat: 'mixed',
            color: '#ff6500',
            layer: 'solid',
        });
    });

    it('fermé et déposé vont dans leurs couches (motifs de tirets)', () => {
        const fc = phaseFeatureCollection(PORTIONS, PHASES[2]);
        const byId = Object.fromEntries(fc.features.map((f) => [f.id, f.properties]));
        expect(byId[2]).toMatchObject({ cat: 'closed', layer: 'closed', changed: true });
        expect(byId[1]).toMatchObject({ cat: 'freight', layer: 'solid', changed: true });
        expect(byId[0].changed).toBe(false);
        const gone = phaseFeatureCollection(PORTIONS, PHASES[3]);
        expect(gone.features.find((f) => f.id === 2).properties.layer).toBe('gone');
    });

    it('sans phase : rien à dessiner', () => {
        expect(phaseFeatureCollection(PORTIONS, null).features).toEqual([]);
        expect(phaseFeatureCollection([], PHASES[0]).features).toEqual([]);
    });

    it('emprise de toute la ligne (stable d’une phase à l’autre)', () => {
        expect(portionsBounds(PORTIONS)).toEqual([
            [0, 45],
            [0.25, 45.1],
        ]);
        expect(portionsBounds([])).toBeNull();
        expect(
            portionsBounds([
                {
                    geometry: {
                        type: 'MultiLineString',
                        coordinates: [
                            [
                                [1, 2],
                                [3, 4],
                            ],
                        ],
                    },
                },
            ]),
        ).toEqual([
            [1, 2],
            [3, 4],
        ]);
    });
});

describe('ruban et phase « au meilleur »', () => {
    it('ruban proportionnel à la plus grande longueur connue', () => {
        const scale = maxKnownLength(PHASES);
        expect(scale).toBe(17000);
        const strip = phaseStrip(PHASES[0], scale);
        expect(strip).toEqual([{ cat: 'mixed', m: 15000, pct: 88.2 }]);
        const s3 = phaseStrip(PHASES[2], scale);
        expect(s3.map((s) => s.cat)).toEqual(['mixed', 'freight', 'closed']);
        expect(s3.reduce((a, s) => a + s.pct, 0)).toBeCloseTo(100, 0);
    });

    it('ruban vide sans longueur', () => {
        expect(phaseStrip({ length_by_category: {} }, 0)).toEqual([]);
    });

    it('phase au meilleur : celle du serveur, sinon la plus grande longueur en service (la première)', () => {
        expect(bestPhaseIndex(PHASES, 1)).toBe(1);
        expect(bestPhaseIndex(PHASES, null)).toBe(1);
        expect(bestPhaseIndex(PHASES, 99)).toBe(1);
        expect(bestPhaseIndex([], null)).toBeNull();
        expect(bestPhaseIndex([{ active_m: 5 }, { active_m: 5 }])).toBe(0);
    });
});

describe('libellés', () => {
    it('kilomètres lisibles selon la langue', () => {
        expect(formatKm(13800, 'fr')).toBe('13,8 km');
        expect(formatKm(13800, 'en')).toBe('13.8 km');
        expect(formatKm(715100, 'fr')).toBe('715,1 km'); // même précision que l'en-tête de la fiche
        expect(formatKm(125000, 'fr')).toBe('125 km');
        expect(formatKm(null)).toBe('0 km');
    });

    it('date à sa précision, sans décalage de fuseau', () => {
        expect(formatEventDate('1981-09-27', 'day', 'fr')).toBe('27 septembre 1981');
        expect(formatEventDate('1938-05-01', 'month', 'fr')).toBe('mai 1938');
        expect(formatEventDate('1940-01-01', 'year', 'fr')).toBe('1940');
        expect(formatEventDate('2001-06-10', 'day', 'en')).toBe('June 10, 2001');
        expect(formatEventDate('n/a')).toBe('n/a');
    });

    it('date d’une phase : événement daté, sinon intervalle de décennie', () => {
        expect(phaseDateLabel(PHASES[0], t, 'fr')).toBe('6 décembre 1869');
        expect(phaseDateLabel(PHASES[2], t, 'fr')).toBe(
            'lines.phases.between{"from":1941,"to":1950}',
        );
        expect(phaseDateLabel(PHASES[3], t, 'fr')).toBe('lines.phases.between_today{"from":2021}');
        expect(phaseDateLabel({ precision: 'range', from_year: null, to_year: 1830 }, t)).toBe(
            'lines.phases.before{"year":1830}',
        );
        expect(phaseDateLabel(null, t)).toBe('');
    });

    it('verbe du changement selon l’état avant et après', () => {
        expect(changeVerbKey('none', 'mixed')).toBe('lines.changes.opening');
        expect(changeVerbKey('none', 'lgv')).toBe('lines.changes.opening');
        expect(changeVerbKey('closed', 'mixed')).toBe('lines.changes.reopening');
        expect(changeVerbKey('mixed', 'freight')).toBe('lines.changes.passenger_end');
        expect(changeVerbKey('freight', 'mixed')).toBe('lines.changes.passenger_start');
        expect(changeVerbKey('mixed', 'closed')).toBe('lines.changes.closure');
        expect(changeVerbKey('closed', 'dismantled')).toBe('lines.changes.removal');
        expect(changeVerbKey('mixed', 'gone')).toBe('lines.changes.closure_removal');
        expect(changeVerbKey('closed', 'velorail')).toBe('lines.changes.heritage');
        expect(changeVerbKey('none', 'closed')).toBe('lines.changes.appears_closed');
    });

    it('les clés de changement existent en fr, en et de', () => {
        const keys = [
            'opening',
            'reopening',
            'passenger_end',
            'passenger_start',
            'traffic',
            'closure',
            'closure_removal',
            'removal',
            'heritage',
            'construction',
            'appears_closed',
        ];
        for (const lang of ['fr', 'en', 'de']) {
            const php = readFileSync(
                new URL(`../../lang/${lang}/lines.php`, import.meta.url),
                'utf8',
            );
            const block = php.slice(php.indexOf("'changes' => ["));
            for (const k of keys) expect(block, `${lang}:${k}`).toContain(`'${k}' =>`);
        }
    });

    it('aucun tiret long dans les textes de la fiche', () => {
        for (const lang of ['fr', 'en', 'de']) {
            const php = readFileSync(
                new URL(`../../lang/${lang}/lines.php`, import.meta.url),
                'utf8',
            );
            const strings = [...php.matchAll(/=> '((?:[^'\\]|\\.)*)'/g)].map((m) => m[1]);
            for (const s of strings) {
                expect(s, `${lang}: ${s}`).not.toMatch(/—|--/);
            }
        }
    });
});

describe('résumé d’une phase', () => {
    it('regroupe par changement, du plus long au plus court, ajustements à part', () => {
        const { changes, minor_m } = summarizeChanges(PHASES[2]);
        expect(changes.map((c) => `${c.from}>${c.to}`)).toEqual(['mixed>freight', 'mixed>closed']);
        expect(changes[0]).toMatchObject({ length_m: 5000, event_ids: [3], modes: ['whole_line'] });
        expect(changes[1]).toMatchObject({ length_m: 2000, event_ids: [], modes: [] });
        expect(minor_m).toBe(150);
    });

    it('cumule les portions d’un même changement datées par plusieurs événements', () => {
        const { changes } = summarizeChanges({
            changes: [
                {
                    from: 'none',
                    to: 'lgv',
                    length_m: 100,
                    event_id: 7,
                    mode: 'localized',
                    portions: [0],
                },
                {
                    from: 'none',
                    to: 'lgv',
                    length_m: 50,
                    event_id: 8,
                    mode: 'neighbor',
                    portions: [1],
                },
                {
                    from: 'none',
                    to: 'lgv',
                    length_m: 25,
                    event_id: 7,
                    mode: 'neighbor',
                    portions: [2],
                },
            ],
        });
        expect(changes).toHaveLength(1);
        expect(changes[0]).toMatchObject({ length_m: 175, event_ids: [7, 8] });
        expect(changes[0].modes.sort()).toEqual(['localized', 'neighbor']);
    });

    it('signale une incohérence du moteur', () => {
        const { changes } = summarizeChanges({
            changes: [
                {
                    from: 'dismantled',
                    to: 'closed',
                    length_m: 10,
                    event_id: null,
                    inconsistent: true,
                    portions: [0],
                },
            ],
        });
        expect(changes[0].inconsistent).toBe(true);
    });
});

describe('légende des décennies et notes des étapes', () => {
    it('légende : catégories présentes dans au moins une décennie, dans l’ordre du ruban', () => {
        const decades = [
            { year: 1990, length_by_category: { lgv: 382600 } },
            { year: 2000, length_by_category: { closed: 315, mixed: 0, dismantled: 13556 } },
            { year: 2030, length_by_category: { freight: 7300, lgv: 714200 } },
        ];
        expect(decadesLegend(decades)).toEqual(['lgv', 'freight', 'closed', 'dismantled']);
        expect(decadesLegend([])).toEqual([]);
        expect(decadesLegend(undefined)).toEqual([]);
    });

    it('notes d’une étape : numéros des sources de ses événements, sans doublon, triés', () => {
        const eventsById = { 13490: { note: 3 }, 13491: { note: 3 }, 11154: { note: 1 }, 99: {} };
        expect(stageNotes({ event_ids: [13490, 13491, 11154, 99, 404] }, eventsById)).toEqual([
            1, 3,
        ]);
        expect(stageNotes({ event_ids: [] }, eventsById)).toEqual([]);
        expect(stageNotes(null)).toEqual([]);
    });
});

describe('navigation au clavier', () => {
    it('reste dans les bornes', () => {
        expect(stepPhase(0, -1, 4)).toBe(0);
        expect(stepPhase(3, 1, 4)).toBe(3);
        expect(stepPhase(1, 1, 4)).toBe(2);
        expect(stepPhase(null, 1, 4)).toBe(1);
        expect(stepPhase(2, -1, 0)).toBeNull();
    });
});

describe('LinePhaseSelector (rendu serveur)', async () => {
    const { render } = await import('svelte/server');
    const { setupI18n } = await import('$lib/i18n.js');
    const LinePhaseSelector = (
        await import('../../resources/js/Components/Lines/LinePhaseSelector.svelte')
    ).default;
    setupI18n({
        locale: 'fr',
        translations: {
            'lines.phases.title': 'Phases de la ligne',
            'lines.sheet.phases.best_badge': 'Longueur maximale',
            'lines.sheet.phases.show_best': 'Longueur maximale',
            'lines.phases.between': 'Entre :from et :to',
            'lines.phases.between_today': 'Depuis :from',
            'lines.phases.and_more': '(+ :count)',
            'lines.changes.opening': 'Mise en service de :km',
            'lines.changes.passenger_end': 'Fin du trafic voyageurs sur :km',
            'lines.changes.removal': 'Dépose de la voie sur :km',
            'lines.categories.mixed': 'Voyageurs et fret',
        },
    });

    it('une option par phase, la phase choisie sélectionnée, la meilleure signalée', () => {
        const { body } = render(LinePhaseSelector, {
            props: { phases: PHASES, selected: 1, best: 1 },
        });
        expect((body.match(/role="option"/g) ?? []).length).toBe(4);
        expect((body.match(/aria-selected="true"/g) ?? []).length).toBe(1);
        expect(body).toContain('Longueur maximale');
        expect(body).toContain('6 décembre 1869');
        expect(body).toContain('Mise en service de 15\u00a0km'); // pas de « ,0 » : même précision que l'en-tête
        expect(body).toContain('Entre 1941 et 1950');
        expect(body).toContain('Depuis 2021');
        expect(body).toContain('Fin du trafic voyageurs sur 5\u00a0km (+ 1)');
        // Ruban : segments colorés aux couleurs de la carte.
        expect(body).toContain('--c: #ff6500');
        expect(body).toContain('--c: #0284c7');
    });

    it('sans phase : liste vide, sans erreur', () => {
        const { body } = render(LinePhaseSelector, {
            props: { phases: [], selected: null, best: null },
        });
        expect(body).not.toContain('role="option"');
    });
});
