import { describe, expect, it } from 'vitest';
import {
    ROUTE_STYLES,
    defaultCompareYear,
    firstEra,
    lineStateKey,
    readRouteParams,
    sideLines,
    summarySentences,
    writeRouteParams,
} from '$lib/routeCompare.js';

const BASE = 'https://vff.test/maps';

// Traduction factice : la clé suivie des paramètres, pour vérifier ce qui est demandé.
const t = (key, params = {}) =>
    [key, ...Object.entries(params).map(([k, v]) => `${k}=${v}`)].join('|');
const km = (m) => String(Math.round(m / 1000));

const line = (slug, name, length_m) => ({
    line_slug: slug,
    line_name: name,
    line_code: null,
    length_m,
});

// Angoulême - Limoges-Bénédictins, 1930 et 2026 (valeurs réelles, abrégées).
const both = {
    from: { slug: 'angouleme', name: 'Angoulême' },
    to: { slug: 'limoges-benedictins', name: 'Limoges-Bénédictins' },
    earlier: {
        requested_year: 1930,
        era_year: 1930,
        era_is_current: false,
        route: {
            distance_m: 118722,
            lines: [line('rfn-610000', 'Angoulême - Limoges', 117471)],
        },
        unavailable: null,
    },
    later: {
        requested_year: 2026,
        era_year: 2030,
        era_is_current: true,
        route: {
            distance_m: 241065,
            lines: [
                line('rfn-570000', 'Paris - Bordeaux', 22203),
                line('rfn-566000', 'LGV SEA', 6238),
                line('rfn-570000', 'Paris - Bordeaux', 75586),
            ],
        },
        unavailable: null,
    },
    differences: {
        outcome: 'both',
        distance_change_m: 122343,
        distance_change_percent: 103.0,
        same_path: false,
        shared_lines: [],
        lines_only_earlier: [{ ...line('rfn-610000', '', 117471), other_era: 'partly_open' }],
        lines_only_later: [
            { ...line('rfn-570000', '', 97789), other_era: 'open' },
            { ...line('rfn-566000', '', 6238), other_era: 'none' },
        ],
        shared_stations: 2,
    },
};

describe('adresse partageable', () => {
    it('lit départ, arrivée, années et option', () => {
        expect(
            readRouteParams('?from=angouleme&to=limoges-benedictins&year=1930&compare_year=2026'),
        ).toEqual({
            from: 'angouleme',
            to: 'limoges-benedictins',
            year: 1930,
            compareYear: 2026,
            heritage: false,
        });
        expect(readRouteParams('?from=a&to=b&heritage=1').heritage).toBe(true);
        expect(readRouteParams('?from=a&to=b&compare_year=x').compareYear).toBeNull();
    });

    it('ne lit rien sans départ ou sans arrivée', () => {
        expect(readRouteParams('?from=a')).toBeNull();
        expect(readRouteParams('?from=%20&to=b')).toBeNull();
    });

    it('écrit l’itinéraire en gardant les autres paramètres, et le retire', () => {
        const href = writeRouteParams(`${BASE}?layer=accidents&era=1930`, {
            from: 'angouleme',
            to: 'limoges-benedictins',
            year: 1930,
            compareYear: 2026,
            heritage: false,
        });
        const params = new URL(href).searchParams;
        expect(params.get('layer')).toBe('accidents');
        expect(params.get('era')).toBe('1930');
        expect(params.get('compare_year')).toBe('2026');
        expect(params.has('heritage')).toBe(false);
        // Le paramètre `compare` reste celui de la comparaison de cartes : pas touché.
        expect(params.has('compare')).toBe(false);

        const single = writeRouteParams(href, { from: 'a', to: 'b', year: 1950, heritage: true });
        expect(new URL(single).searchParams.has('compare_year')).toBe(false);
        expect(new URL(single).searchParams.get('heritage')).toBe('1');

        const cleared = new URL(writeRouteParams(href, null)).searchParams;
        expect([...cleared.keys()].sort()).toEqual(['era', 'layer']);
    });
});

describe('choix des années', () => {
    it('propose aujourd’hui, ou 1930 si la première année est déjà aujourd’hui', () => {
        expect(defaultCompareYear(1930, 2026)).toBe(2026);
        expect(defaultCompareYear(2026, 2026)).toBe(1930);
    });

    it('garde la carte sur l’ère de la première année demandée', () => {
        expect(firstEra(both, 1930)).toBe(1930);
        expect(firstEra(both, 2026)).toBe(2030);
    });
});

describe('summarySentences', () => {
    it('compare les longueurs quand le trajet existe aux deux époques', () => {
        expect(summarySentences(both, t, km)).toEqual([
            'itinerary.compare.longer|earlier=itinerary.compare.in_year|year=1930|later=itinerary.compare.today|earlier_km=119|later_km=241|diff=122|percent=103',
        ]);
    });

    it('dit « plus court », « même longueur » et « mêmes gares »', () => {
        const shorter = {
            ...both,
            differences: {
                ...both.differences,
                distance_change_m: -20000,
                distance_change_percent: -9.6,
            },
        };
        expect(summarySentences(shorter, t, km)[0]).toMatch(
            /^itinerary\.compare\.shorter\|.*percent=10$/,
        );

        const same = {
            ...both,
            differences: { ...both.differences, distance_change_m: 0, same_path: true },
        };
        expect(summarySentences(same, t, km).map((s) => s.split('|')[0])).toEqual([
            'itinerary.compare.same_length',
            'itinerary.compare.same_path',
        ]);
    });

    it('explique un trajet devenu impossible', () => {
        const lost = {
            ...both,
            later: {
                ...both.later,
                route: null,
                unavailable: {
                    error: 'station_not_in_network',
                    station: { slug: 'bort-les-orgues', name: 'Bort-les-Orgues' },
                },
            },
            differences: { ...both.differences, outcome: 'lost', distance_change_m: null },
        };
        expect(summarySentences(lost, t, km)).toEqual([
            'itinerary.compare.lost|earlier=itinerary.compare.in_year|year=1930|later=itinerary.compare.today|km=119',
            'itinerary.compare.reason_station|station=Bort-les-Orgues|when=itinerary.compare.today',
        ]);
    });

    it('traite le trajet devenu possible et le trajet impossible aux deux époques', () => {
        const gained = {
            ...both,
            earlier: { ...both.earlier, route: null, unavailable: { error: 'no_path' } },
            differences: { ...both.differences, outcome: 'gained' },
        };
        expect(summarySentences(gained, t, km).map((s) => s.split('|')[0])).toEqual([
            'itinerary.compare.gained',
            'itinerary.compare.reason_no_path',
        ]);
        const neither = { ...both, differences: { ...both.differences, outcome: 'neither' } };
        expect(summarySentences(neither, t, km)).toHaveLength(1);
    });
});

describe('lignes de chaque trajet', () => {
    it('regroupe une ligne reprise et dit ce qu’elle était à l’autre époque', () => {
        expect(sideLines(both, 'later')).toEqual([
            {
                line_slug: 'rfn-570000',
                line_name: 'Paris - Bordeaux',
                line_code: null,
                length_m: 97789,
                shared: false,
                otherEra: 'open',
            },
            {
                line_slug: 'rfn-566000',
                line_name: 'LGV SEA',
                line_code: null,
                length_m: 6238,
                shared: false,
                otherEra: 'none',
            },
        ]);
        expect(sideLines(both, 'earlier')[0].otherEra).toBe('partly_open');
    });

    it('marque une ligne commune et ne liste rien sans trajet', () => {
        const shared = {
            ...both,
            differences: { ...both.differences, lines_only_earlier: [] },
        };
        expect(sideLines(shared, 'earlier')[0]).toMatchObject({ shared: true, otherEra: null });
        expect(sideLines({ ...both, later: { ...both.later, route: null } }, 'later')).toEqual([]);
    });

    it('nomme la clé de traduction de l’état d’une ligne', () => {
        expect(lineStateKey('earlier', 'closed')).toBe(
            'itinerary.compare.line_state.earlier_closed',
        );
        expect(lineStateKey('later', 'none')).toBe('itinerary.compare.line_state.later_none');
        expect(lineStateKey('later', null)).toBeNull();
    });
});

it('distingue les deux tracés par le motif autant que par la couleur', () => {
    expect(ROUTE_STYLES.earlier.dash).not.toBeNull();
    expect(ROUTE_STYLES.later.dash).toBeNull();
    expect(ROUTE_STYLES.earlier.casing).not.toBe(ROUTE_STYLES.later.casing);
    expect(ROUTE_STYLES.earlier.casingScale).toBeGreaterThan(ROUTE_STYLES.later.casingScale);
});
