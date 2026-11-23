// Diagramme « où et quand » de la fiche ligne : helpers purs de resources/js/lib/lineSpaceTime.js.
import { describe, expect, it } from 'vitest';
import {
    axisLabels,
    chainOrder,
    phaseBands,
    portionAt,
    portionSpans,
    shortPhaseDate,
    stateSince,
    stationPositions,
} from '$lib/lineSpaceTime.js';

const line = (coords) => ({ type: 'LineString', coordinates: coords });
// Trois portions bout à bout d'ouest en est (environ 7,3 km chacune à 45° N), données dans le désordre
// et la troisième dans le mauvais sens, comme le serveur les donne quand les PK manquent.
const PORTIONS = [
    {
        length_m: 7000,
        geometry: line([
            [0.2, 45],
            [0.1, 45],
        ]),
    }, // milieu, retournée
    {
        length_m: 8000,
        geometry: line([
            [0.2, 45],
            [0.3, 45],
        ]),
    }, // est
    {
        length_m: 5000,
        geometry: line([
            [0.0, 45],
            [0.1, 45],
        ]),
    }, // ouest
];

describe('chainOrder', () => {
    it('suit les portions qui se touchent depuis l’extrémité nord-ouest', () => {
        expect(chainOrder(PORTIONS)).toEqual([2, 0, 1]);
    });

    it('suit la ligne principale avant un embranchement court', () => {
        const portions = [
            {
                length_m: 5000,
                geometry: line([
                    [0, 45],
                    [0.1, 45],
                ]),
            },
            {
                length_m: 1000,
                geometry: line([
                    [0.1, 45],
                    [0.1, 44.99],
                ]),
            }, // branche courte
            {
                length_m: 9000,
                geometry: line([
                    [0.1, 45],
                    [0.3, 45],
                ]),
            }, // suite principale
        ];
        expect(chainOrder(portions)).toEqual([0, 2, 1]);
        // La branche, remise en fin d'axe, n'est pas dans la suite principale.
        expect([...portionSpans(portions).main].sort()).toEqual([0, 2]);
    });

    it('garde l’ordre d’origine sans géométrie', () => {
        expect(chainOrder([{ length_m: 1 }, { length_m: 2 }])).toEqual([0, 1]);
    });

    it('place les portions isolées après la chaîne', () => {
        const portions = [
            ...PORTIONS,
            {
                length_m: 500,
                geometry: line([
                    [1, 44],
                    [1.01, 44],
                ]),
            },
        ];
        expect(chainOrder(portions)).toEqual([2, 0, 1, 3]);
        // Les gares ne sont placées que sur le plus long morceau continu.
        const axis = portionSpans(portions);
        expect([...axis.main].sort()).toEqual([0, 1, 2]);
        const placed = stationPositions([{ name: 'Isolée', lng: 1.005, lat: 44 }], portions, axis);
        expect(placed).toEqual([]);
    });
});

describe('portionSpans et phaseBands', () => {
    const axis = portionSpans(PORTIONS);

    it('met les portions bout à bout dans l’ordre de la ligne', () => {
        expect(axis.total).toBe(20000);
        expect(axis.spans[2]).toEqual({ start: 0, end: 5000 });
        expect(axis.spans[0]).toEqual({ start: 5000, end: 12000 });
        expect(axis.spans[1]).toEqual({ start: 12000, end: 20000 });
    });

    it('fusionne les portions voisines de même état et omet les portions pas encore construites', () => {
        const bands = phaseBands(
            { state: ['mixed', 'none', 'mixed'], changes: [{ portions: [0, 2] }] },
            axis,
        );
        expect(bands).toHaveLength(1);
        expect(bands[0]).toMatchObject({ cat: 'mixed', left: 0, width: 60, changed: true });
        expect(bands[0].members).toEqual([2, 0]);
    });

    it('sépare ce qui change à la phase de ce qui ne change pas', () => {
        const bands = phaseBands(
            { state: ['closed', 'freight', 'freight'], changes: [{ portions: [0] }] },
            axis,
        );
        expect(bands.map((b) => [b.cat, b.changed, b.left, b.width])).toEqual([
            ['freight', false, 0, 25],
            ['closed', true, 25, 35],
            ['freight', false, 60, 40],
        ]);
    });

    it('retrouve la portion sous une position de l’axe', () => {
        expect(portionAt(axis, 0)).toBe(2);
        expect(portionAt(axis, 6000)).toBe(0);
        expect(portionAt(axis, 20000)).toBe(1);
        expect(portionAt({ order: [], spans: [] }, 10)).toBeNull();
    });
});

describe('stateSince', () => {
    const phases = [
        { changes: [{ portions: [0, 1], event_id: 1, from: 'none', to: 'mixed' }] },
        { changes: [{ portions: [1], event_id: 2, from: 'mixed', to: 'closed' }] },
        { changes: [{ portions: [2], event_id: null, from: 'none', to: 'mixed' }] },
    ];

    it('donne la dernière phase qui a modifié la portion', () => {
        expect(stateSince(phases, 2, 1)).toMatchObject({ phase: 1, change: { event_id: 2 } });
        expect(stateSince(phases, 2, 0)).toMatchObject({ phase: 0 });
        expect(stateSince(phases, 0, 2)).toBeNull();
    });
});

describe('stationPositions et axisLabels', () => {
    const axis = portionSpans(PORTIONS);
    const stations = [
        { name: 'Est', lng: 0.3, lat: 45 },
        { name: 'Ouest', lng: 0.0, lat: 45.001 },
        { name: 'Milieu', lng: 0.15, lat: 45 },
        { name: 'Loin', lng: 0.15, lat: 45.2 },
        { name: 'Sans coordonnées', lng: null, lat: null },
    ];

    it('place les gares proches du tracé le long de l’axe', () => {
        const placed = stationPositions(stations, PORTIONS, axis);
        expect(placed.map((p) => p.name)).toEqual(['Ouest', 'Milieu', 'Est']);
        expect(placed[0].at).toBe(0);
        expect(placed[2].at).toBe(20000);
        // Milieu : à mi-chemin de la portion retournée (5 à 12 km).
        expect(placed[1].at).toBeGreaterThan(5000);
        expect(placed[1].at).toBeLessThan(12000);
    });

    it('nomme les extrémités et les gares qui tiennent sans chevaucher', () => {
        const positions = [
            { name: 'Ouest', at: 0 },
            { name: 'Trop près', at: 500 },
            { name: 'Milieu', at: 10000 },
            { name: 'Est', at: 20000 },
        ];
        expect(axisLabels(positions, 20000, 1).map((l) => [l.name, l.edge])).toEqual([
            ['Ouest', 'start'],
            ['Milieu', null],
            ['Est', 'end'],
        ]);
        // Axe étroit : les extrémités seules, puis le début seul.
        expect(axisLabels(positions, 20000, 8).map((l) => l.name)).toEqual(['Ouest', 'Est']);
        expect(axisLabels(positions, 20000, 14).map((l) => l.name)).toEqual(['Ouest']);
        expect(axisLabels([], 20000, 1)).toEqual([]);
    });
});

describe('shortPhaseDate', () => {
    const words = { before: (y) => `Avant ${y}`, since: (y) => `Depuis ${y}` };

    it('donne l’année ou l’intervalle d’une phase', () => {
        expect(shortPhaseDate({ date: '1938-05-26', precision: 'day' }, words)).toBe('1938');
        expect(shortPhaseDate({ precision: 'range', from_year: 1941, to_year: 1950 }, words)).toBe(
            '1941-1950',
        );
        expect(shortPhaseDate({ precision: 'range', from_year: null, to_year: 1900 }, words)).toBe(
            'Avant 1900',
        );
        expect(
            shortPhaseDate(
                { precision: 'range', from_year: 2021, to_year: 2030, is_current: true },
                words,
            ),
        ).toBe('Depuis 2021');
        expect(shortPhaseDate(null, words)).toBe('');
    });
});
