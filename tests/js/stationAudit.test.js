import { describe, expect, it } from 'vitest';
import {
    auditBounds,
    auditDate,
    centuryOrdinal,
    deltaYears,
    detailUrl,
    exportUrl,
    filterQuery,
    formFilters,
    GRADE_TONES,
    hostOf,
    neighbourItem,
    numberedPositions,
    pageUrl,
    share,
    sortChecks,
    sourceFamilies,
    trackCollection,
} from '../../resources/js/lib/stationAudit.js';

/** Traduction factice : la clé suivie des paramètres, pour vérifier ce qui est demandé. */
const t = (key, opts) => (opts ? `${key} ${JSON.stringify(opts)}` : key);

describe('adresses', () => {
    it('retire les filtres vides et les valeurs par défaut', () => {
        expect(pageUrl({ grade: 'disagree', sort: 'spread', q: '  ', region: null })).toBe(
            '/validate/audit/stations',
        );
        expect(
            pageUrl({ grade: 'all', region: '75', q: ' Angoulême ', sort: 'name', extra: 'x' }, 3),
        ).toBe('/validate/audit/stations?grade=all&region=75&q=Angoul%C3%AAme&sort=name&page=3');
    });

    it("n'ajoute la page qu'à partir de la deuxième", () => {
        expect(filterQuery({ check: 'far_from_track' }, 1)).toEqual({ check: 'far_from_track' });
        expect(filterQuery({}, 2)).toEqual({ page: '2' });
    });

    it("reprend les filtres dans l'export, sans la page", () => {
        expect(exportUrl({ grade: 'close', line: '610000' })).toBe(
            '/validate/audit/stations/export.csv?grade=close&line=610000',
        );
        expect(exportUrl({})).toBe('/validate/audit/stations/export.csv');
    });

    it('construit l’adresse de la fiche', () => {
        expect(detailUrl(42)).toBe('/validate/audit/stations/42');
    });
});

describe('formulaire', () => {
    it('lit les filtres connus et rogne les textes', () => {
        const data = [
            ['grade', 'all'],
            ['q', '  Limoges '],
            ['page', '4'],
            ['line', ''],
        ];
        expect(formFilters(data)).toEqual({ grade: 'all', q: 'Limoges', line: '' });
    });
});

describe('positions et carte', () => {
    const station = {
        lon: 0.15,
        lat: 45.65,
        positions: [
            { source: 'osm', lon: 0.151, lat: 45.651 },
            { source: 'wikidata', lon: null, lat: null },
            { source: 'bdtopo', lon: 0.149, lat: 45.648 },
        ],
    };

    it('numérote les positions et dit lesquelles ont un repère', () => {
        const list = numberedPositions(station.positions);
        expect(list.map((p) => p.n)).toEqual([1, 2, 3]);
        expect(list.map((p) => p.onMap)).toEqual([true, false, true]);
        expect(numberedPositions(null)).toEqual([]);
    });

    it('cadre la position retenue et toutes les sources', () => {
        const [[w, s], [e, n]] = auditBounds(station);
        expect(w).toBeCloseTo(0.149);
        expect(e).toBeCloseTo(0.151);
        expect(s).toBeCloseTo(45.648);
        expect(n).toBeCloseTo(45.651);
    });

    it('élargit un cadre réduit à un point', () => {
        const [[w, s], [e, n]] = auditBounds({ lon: 1, lat: 2, positions: [] }, 0.002);
        expect(e - w).toBeCloseTo(0.002);
        expect(n - s).toBeCloseTo(0.002);
        expect(auditBounds({ positions: [] })).toBeNull();
    });

    it('écarte les tracés sans géométrie', () => {
        const geometry = {
            type: 'LineString',
            coordinates: [
                [0, 0],
                [1, 1],
            ],
        };
        const fc = trackCollection([{ code: '610000', name: 'A', geometry }, { code: 'x' }]);
        expect(fc.features).toHaveLength(1);
        expect(fc.features[0].properties).toEqual({ code: '610000', name: 'A' });
    });
});

describe('dates et chiffres', () => {
    it('écrit une date à sa précision', () => {
        expect(auditDate('1875-06-14', 'year', t, 'fr')).toBe('1875');
        expect(auditDate('1875-06-14', 'day', t, 'fr')).toBe('14 juin 1875');
        expect(auditDate('1850-01-01', 'century', t, 'fr')).toBe(
            'audit_stations.dates.century {"nth":"XIXe"}',
        );
        expect(auditDate(null, 'day', t, 'fr')).toBe('');
    });

    it('écrit le siècle dans chaque langue', () => {
        expect(centuryOrdinal(19, 'fr')).toBe('XIXe');
        expect(centuryOrdinal(21, 'en')).toBe('21st');
        expect(centuryOrdinal(12, 'en')).toBe('12th');
        expect(centuryOrdinal(20, 'de')).toBe('20.');
    });

    it('donne l’écart en années et la part d’un total', () => {
        expect(deltaYears(-800)).toBe(2);
        expect(deltaYears(null)).toBeNull();
        expect(share(25, 100)).toBe(0.25);
        expect(share(3, 0)).toBe(0);
    });

    it('trouve la gare voisine dans la page', () => {
        const items = [{ station_id: 1 }, { station_id: 2 }, { station_id: 3 }];
        expect(neighbourItem(items, 2, 1)).toEqual({ station_id: 3 });
        expect(neighbourItem(items, 1, -1)).toBeNull();
        expect(neighbourItem(items, 9, 1)).toBeNull();
    });

    it('associe un ton à chaque note de placement', () => {
        expect(Object.keys(GRADE_TONES)).toEqual([
            'agree',
            'close',
            'isolated',
            'disagree',
            'none',
        ]);
    });

    it('met le contrôle prioritaire en tête', () => {
        expect(sortChecks(['far_from_track', 'retained_outvoted', 'wikidata_differs'])).toEqual([
            'retained_outvoted',
            'far_from_track',
            'wikidata_differs',
        ]);
        expect(sortChecks([{ code: 'a' }, { code: 'retained_outvoted' }], (c) => c.code)).toEqual([
            { code: 'retained_outvoted' },
            { code: 'a' },
        ]);
        expect(sortChecks(null)).toEqual([]);
    });

    it('lit le nom d’hôte d’une référence', () => {
        expect(hostOf('https://www.wikidata.org/wiki/Q90')).toBe('www.wikidata.org');
        expect(hostOf('pas une adresse')).toBe('pas une adresse');
    });

    it('accorde le nombre de familles avec la liste affichée', () => {
        expect(sourceFamilies({ families: 3, sources: ['sncf', 'osm', 'osm', ''] })).toEqual({
            count: 2,
            list: ['sncf', 'osm'],
        });
        expect(sourceFamilies({ families: 1, sources: [] })).toEqual({ count: 1, list: [] });
        expect(sourceFamilies({})).toEqual({ count: 0, list: [] });
    });
});
