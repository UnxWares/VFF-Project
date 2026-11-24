import { describe, expect, it } from 'vitest';
import {
    asLayout,
    endName,
    missingSummary,
    routebookEndsOf,
    routebookHref,
    travelSense,
} from '../../resources/js/lib/routebook.js';
import { linePk } from '../../resources/js/lib/trackLayout.js';

const book = {
    from: { slug: 'd', name: 'Gare D' },
    to: { slug: 'a', name: 'Gare A' },
    distance_m: 29650,
    legs: [
        { index: 0, direction: 'down', line: { code: '200000', slug: 'rfn-200000' } },
        { index: 1, direction: 'up', line: { code: '100000', slug: 'rfn-100000' } },
    ],
    sources: [{ key: 'osm', label: 'OpenStreetMap' }],
    operational_points: [{ id: '0:op-b', pk_m: 50000, route_km: 22.2, leg: 0 }],
    tracks: [
        {
            id: '0:t-1',
            pk_from_m: 50000,
            pk_to_m: 72200,
            route_from_km: 0,
            route_to_km: 22.2,
            lane: 0,
            route_lane: 0,
            leg: 0,
        },
        {
            id: '1:t-2',
            pk_from_m: 100,
            pk_to_m: 900,
            route_from_km: 22.3,
            route_to_km: 23.1,
            lane: 1,
            route_lane: 0,
            leg: 1,
        },
    ],
    switches: [{ id: '0:sw', pk_m: 60000, route_km: 12.2, facing: 'up', leg: 0 }],
    signals: [{ id: '1:sig', pk_m: 500, route_km: 22.7, direction: 'up', leg: 1 }],
    crossings: [],
    strip: {
        speeds: [
            {
                pk_from_m: 50000,
                pk_to_m: 72200,
                route_from_km: 0,
                route_to_km: 22.2,
                kmh: 120,
                leg: 0,
            },
        ],
        gradients: [],
    },
    coverage: {
        total_m: 29650,
        missing: [
            {
                line_code: '100000',
                line_slug: 'rfn-100000',
                route_from_km: 22.2,
                route_to_km: 29.65,
                what: 'all',
            },
            {
                line_code: '200000',
                line_slug: 'rfn-200000',
                route_from_km: 0,
                route_to_km: 5,
                what: 'signals',
            },
        ],
    },
};

describe('routebookHref', () => {
    it('met les deux gares dans l’adresse, ou seulement celles qui sont connues', () => {
        expect(routebookHref('rennes', 'st-malo')).toBe('/routebook?from=rennes&to=st-malo');
        expect(routebookHref('rennes', '')).toBe('/routebook?from=rennes');
        expect(routebookHref('', '')).toBe('/routebook');
        expect(routebookHref('Saint Malo', 'a&b')).toBe('/routebook?from=Saint+Malo&to=a%26b');
    });
});

describe('asLayout', () => {
    it('place les objets sur l’axe des mètres parcourus en gardant le PK de leur ligne', () => {
        const layout = asLayout(book);
        expect(layout.pk).toEqual({ start_m: 0, end_m: 29650 });
        expect(layout.operational_points[0]).toMatchObject({ pk_m: 22200, line_pk_m: 50000 });
        expect(layout.tracks[1]).toMatchObject({
            pk_from_m: 22300,
            pk_to_m: 23100,
            line_pk_from_m: 100,
            lane: 0,
        });
        // Portion 0 parcourue à PK décroissants : l'étendue commence au plus grand PK de la ligne.
        expect(layout.strip.speeds[0]).toMatchObject({
            pk_from_m: 0,
            pk_to_m: 22200,
            kmh: 120,
            line_pk_from_m: 72200,
            line_pk_to_m: 50000,
        });
        expect(layout.coverage.missing[0]).toMatchObject({ pk_from_m: 22200, pk_to_m: 29650 });
    });

    it('donne les sens « up » et « down » dans le sens de marche', () => {
        const layout = asLayout(book);
        // Portion 0 parcourue à PK décroissants : un aiguillage pris en pointe à PK croissants est en talon.
        expect(layout.switches[0].facing).toBe('down');
        expect(layout.signals[0].direction).toBe('up');
        expect(asLayout(null)).toBeNull();
    });

    it('travelSense', () => {
        expect(travelSense('up', 'down')).toBe('down');
        expect(travelSense('down', 'down')).toBe('up');
        expect(travelSense('up', 'up')).toBe('up');
        expect(travelSense(null, 'down')).toBeNull();
    });
});

describe('missingSummary', () => {
    it('compte les seules portions sans aucun schéma', () => {
        expect(missingSummary(book)).toEqual({ metres: 7450, lines: ['100000'], complete: false });
        const none = {
            distance_m: 1000,
            coverage: {
                missing: [
                    {
                        route_from_km: 0,
                        route_to_km: 1,
                        what: 'all',
                        line_code: null,
                        line_slug: 'x',
                    },
                ],
            },
        };
        expect(missingSummary(none)).toEqual({ metres: 1000, lines: ['x'], complete: true });
        expect(missingSummary({ distance_m: 0, coverage: { missing: [] } }).complete).toBe(false);
    });
});

describe('endName et routebookEndsOf', () => {
    it('nomme une gare, pas un changement de ligne en pleine voie', () => {
        expect(endName({ kind: 'station', name: 'Rennes' })).toBe('Rennes');
        expect(endName({ kind: 'junction', name: null })).toBeNull();
    });

    it('prend la première et la dernière gare ouverte de la ligne', () => {
        const stations = [
            { slug: 'x', name: 'X', status_today: 'closed' },
            { slug: 'a', name: 'A', status_today: 'active' },
            { slug: 'b', name: 'B', status_today: 'active' },
            { slug: 'c', name: 'C', status_today: 'active' },
        ];
        expect(routebookEndsOf(stations)).toEqual({ from: stations[1], to: stations[3] });
        expect(routebookEndsOf(stations.slice(0, 2))).toBeNull();
        expect(routebookEndsOf(undefined)).toBeNull();
    });
});

describe('linePk (tableau du schéma des voies)', () => {
    it('affiche le PK de la ligne de l’objet sur un livret, le PK de l’axe sinon', () => {
        const layout = asLayout(book);
        expect(linePk({ pk_m: 22200, item: layout.operational_points[0] })).toBe(50000);
        expect(linePk({ pk_m: 22300, item: layout.tracks[1] })).toBe(100);
        expect(linePk({ pk_m: 1200, item: { pk_m: 1200 } })).toBe(1200);
    });
});
