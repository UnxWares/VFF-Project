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
    linkedReferences,
    neighbourItem,
    numberedPositions,
    pageUrl,
    share,
    sortChecks,
    sourceFamilies,
    trackCollection,
    webUrl,
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

    it('ne met en lien que les adresses http ou https', () => {
        expect(webUrl('https://www.wikidata.org/wiki/Q90')).toBe(
            'https://www.wikidata.org/wiki/Q90',
        );
        expect(webUrl(' http://example.org/a ')).toBe('http://example.org/a');
        expect(webUrl('javascript:alert(1)')).toBeNull();
        expect(webUrl('JavaScript:alert(1)')).toBeNull();
        expect(webUrl('data:text/html,<b>x</b>')).toBeNull();
        expect(webUrl('//example.org')).toBeNull();
        expect(webUrl('pas une adresse')).toBeNull();
        expect(webUrl(null)).toBeNull();
        expect(
            linkedReferences([
                { url: 'https://gallica.bnf.fr/ark:/1', title: 'Gallica' },
                { url: 'javascript:alert(1)' },
                { url: '' },
                null,
            ]),
        ).toEqual([{ url: 'https://gallica.bnf.fr/ark:/1', title: 'Gallica' }]);
        expect(linkedReferences(null)).toEqual([]);
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

describe('cas limites', () => {
    it('siècle : la date que le serveur range au début du siècle (1801 pour le XIXe)', () => {
        // WikidataStationDates::time : « XIXe siècle » est enregistré au 1801-01-01.
        expect(auditDate('1801-01-01', 'century', t, 'fr')).toBe(
            'audit_stations.dates.century {"nth":"XIXe"}',
        );
        expect(auditDate('1901-01-01', 'century', t, 'en')).toBe(
            'audit_stations.dates.century {"nth":"20th"}',
        );
        expect(auditDate('1701-01-01', 'century', t, 'de')).toBe(
            'audit_stations.dates.century {"nth":"18."}',
        );
    });

    it('ordinaux anglais de 11 à 13 et chiffres romains', () => {
        expect(
            ['1', '2', '3', '4', '11', '13', '22', '23', '111'].map((c) =>
                centuryOrdinal(Number(c), 'en'),
            ),
        ).toEqual(['1st', '2nd', '3rd', '4th', '11th', '13th', '22nd', '23rd', '111th']);
        expect([4, 9, 14, 18, 20, 21].map((c) => centuryOrdinal(c, 'fr'))).toEqual([
            'IVe',
            'IXe',
            'XIVe',
            'XVIIIe',
            'XXe',
            'XXIe',
        ]);
        expect(centuryOrdinal(19, null)).toBe('XIXe');
        expect(centuryOrdinal(19, 'en-GB')).toBe('19th');
    });

    it('précision absente : la date au jour ; valeurs vides', () => {
        expect(auditDate('1875-06-14', null, t, 'fr')).toBe('14 juin 1875');
        expect(auditDate('', 'year', t, 'fr')).toBe('');
        expect(deltaYears(Number.NaN)).toBeNull();
        expect(deltaYears(0)).toBe(0);
        expect(deltaYears(182)).toBe(0);
        expect(deltaYears(183)).toBe(1);
        expect(share(null, 10)).toBe(0);
        expect(share(5, null)).toBe(0);
        expect(share(Number.NaN, 10)).toBe(0);
        expect(neighbourItem(null, 1, 1)).toBeNull();
    });

    it('le cadre ignore les coordonnées non finies et garde un écart déjà large', () => {
        expect(auditBounds(null)).toBeNull();
        expect(auditBounds({ lon: Number.NaN, lat: 45, positions: null })).toBeNull();
        const [[w, s], [e, n]] = auditBounds({
            lon: 0,
            lat: 45,
            positions: [{ lon: 0.01, lat: 45.01 }, null, { lon: '1', lat: 46 }],
        });
        expect([w, s, e, n]).toEqual([0, 45, 0.01, 45.01]);
    });

    it('tracés : code et nom vides par défaut, entrées nulles écartées', () => {
        const geometry = { type: 'LineString', coordinates: [] };
        expect(trackCollection([{ geometry }, null, { geometry: 'x' }]).features).toEqual([
            { type: 'Feature', properties: { code: '', name: '' }, geometry },
        ]);
        expect(trackCollection(undefined).features).toEqual([]);
    });

    it('filtres : la valeur par défaut retirée, zéro gardé, page invalide ignorée', () => {
        expect(filterQuery({ grade: ' disagree ', region: 0 }, 2.5)).toEqual({ region: '0' });
        expect(filterQuery(null, 0)).toEqual({});
        expect(
            formFilters([
                ['q', new Blob([])],
                ['sort', 'name'],
            ]),
        ).toEqual({ sort: 'name' });
        expect(detailUrl('a/b')).toBe('/validate/audit/stations/a%2Fb');
    });

    it('nom d’hôte vide : l’adresse telle quelle', () => {
        expect(hostOf('mailto:contact@example.org')).toBe('mailto:contact@example.org');
    });
});
