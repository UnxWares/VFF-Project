import { describe, expect, it } from 'vitest';
import {
    LINE_STATES,
    MAX_LAT_SPAN,
    MAX_LON_SPAN,
    PAGE_SIZE,
    STATION_STATES,
    bboxFromBounds,
    createListingCache,
    isTooWide,
    lineStateOf,
    listingUrl,
    pageCount,
    paginate,
    stationStateOf,
    visibleLines,
    visibleStations,
} from '$lib/mapListing.js';
import { TOGGLE_KEYS } from '$lib/railStyle.js';

const bounds = (w, s, e, n) => ({
    getWest: () => w,
    getSouth: () => s,
    getEast: () => e,
    getNorth: () => n,
});

describe('états des lignes', () => {
    it('reprennent les catégories de la légende', () => {
        const keys = LINE_STATES.map((s) => s.key);
        for (const key of TOGGLE_KEYS) expect(keys).toContain(key);
        expect(keys).toContain('lgv');
    });

    it('ont chacun une couleur et une clé de traduction', () => {
        for (const s of [...LINE_STATES, ...STATION_STATES]) {
            expect(s.color).toMatch(/^#/);
            expect(s.labelKey).toMatch(/^maps\./);
        }
    });

    it('retombent sur un état connu pour une clé inconnue', () => {
        expect(lineStateOf('???').key).toBe('unknown');
        expect(stationStateOf('???').key).toBe('declassified');
        expect(lineStateOf('mixed').key).toBe('mixed');
    });
});

describe('emprise', () => {
    it('est arrondie à 0,001 degré', () => {
        expect(bboxFromBounds(bounds(2.123456, 48.7, 2.5, 48.98765))).toEqual([
            2.123, 48.7, 2.5, 48.988,
        ]);
    });

    it('est ramenée au monde plat et refusée si elle est vide ou invalide', () => {
        expect(bboxFromBounds(bounds(-200, -90, 200, 90))).toEqual([-180, -85, 180, 85]);
        expect(bboxFromBounds(bounds(2, 48, 2, 49))).toBeNull();
        expect(bboxFromBounds(bounds(NaN, 48, 3, 49))).toBeNull();
        expect(bboxFromBounds(null)).toBeNull();
    });

    it('est trop large au-delà des bornes du serveur', () => {
        expect(isTooWide([0, 0, MAX_LON_SPAN, MAX_LAT_SPAN])).toBe(false);
        expect(isTooWide([0, 0, MAX_LON_SPAN + 0.01, 1])).toBe(true);
        expect(isTooWide([0, 0, 1, MAX_LAT_SPAN + 0.01])).toBe(true);
    });

    it("donne l'adresse de la liste, avec la version si elle est connue", () => {
        expect(listingUrl(1930, [2, 48, 3, 49])).toBe(
            '/api/v1/eras/1930/in-view?bbox=2%2C48%2C3%2C49',
        );
        expect(listingUrl(2030, [2, 48, 3, 49], 'abc')).toContain('&v=abc');
    });
});

describe('lignes et gares affichées', () => {
    const items = [
        { slug: 'a', name: 'Béziers', state: 'mixed', km: 4 },
        { slug: 'b', name: 'Albi', state: 'abandoned', km: 9.5 },
        { slug: 'c', name: 'Carmaux', state: 'mixed', km: 9.5 },
        { slug: 'd', name: 'Dax', state: 'lgv', km: 1 },
    ];

    it('trient par kilomètres décroissants puis par nom', () => {
        expect(visibleLines([...items]).map((l) => l.slug)).toEqual(['b', 'c', 'a', 'd']);
    });

    it('trient par nom à la demande', () => {
        expect(visibleLines([...items], { sort: 'name' }).map((l) => l.slug)).toEqual([
            'b',
            'a',
            'c',
            'd',
        ]);
    });

    it('retirent les états masqués dans la légende', () => {
        expect(visibleLines([...items], { hidden: ['mixed', 'lgv'] }).map((l) => l.slug)).toEqual([
            'b',
        ]);
    });

    it("filtrent sur l'état choisi, sans toucher à la liste d'origine", () => {
        const copy = [...items];
        expect(visibleLines(copy, { only: 'mixed' }).map((l) => l.slug)).toEqual(['c', 'a']);
        expect(visibleLines(copy, { only: 'mixed', hidden: ['mixed'] })).toEqual([]);
        expect(items).toHaveLength(4);
    });

    it("filtrent les gares sur l'état choisi", () => {
        const stations = [
            { slug: 'x', name: 'X', state: 'active' },
            { slug: 'y', name: 'Y', state: 'declassified' },
        ];
        expect(visibleStations(stations)).toHaveLength(2);
        expect(visibleStations(stations, { only: 'declassified' }).map((s) => s.slug)).toEqual([
            'y',
        ]);
    });
});

describe('pagination', () => {
    const rows = Array.from({ length: 60 }, (_, i) => i);

    it('découpe en pages et borne la page demandée', () => {
        expect(pageCount(0)).toBe(1);
        expect(pageCount(PAGE_SIZE)).toBe(1);
        expect(pageCount(PAGE_SIZE + 1)).toBe(2);
        expect(paginate(rows, 1).rows).toHaveLength(PAGE_SIZE);
        expect(paginate(rows, 3).rows).toHaveLength(10);
        expect(paginate(rows, 99).page).toBe(3);
        expect(paginate(rows, -4).page).toBe(1);
        expect(paginate([], 5)).toEqual({ page: 1, pages: 1, rows: [] });
    });
});

describe('cache des réponses', () => {
    it('garde les dernières entrées et oublie la plus ancienne', () => {
        const cache = createListingCache(2);
        cache.set('a', 1);
        cache.set('b', 2);
        expect(cache.get('a')).toBe(1); // « a » redevient la plus récente
        cache.set('c', 3);
        expect(cache.get('b')).toBeUndefined();
        expect(cache.get('a')).toBe(1);
        expect(cache.size).toBe(2);
    });
});
