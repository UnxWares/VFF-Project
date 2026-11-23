import { describe, expect, it } from 'vitest';
import {
    builtRange,
    countBy,
    filterHeritage,
    franceBounds,
    fold,
    groupByArchitect,
    groupId,
    heritagePoints,
    sortHeritage,
} from '../../resources/js/lib/heritage.js';

const item = (over) => ({
    id: 'x',
    object: 'building',
    name: 'Gare',
    title: null,
    protection: 'registered',
    protection_date: null,
    architects: [],
    built: { from: null, to: null, period: null, year: null },
    era: 'unknown',
    style: null,
    commune: null,
    region: null,
    line: null,
    lat: 45,
    lng: 2,
    ...over,
});

const rows = [
    item({
        id: 'a',
        name: 'Limoges-Bénédictins',
        commune: 'Limoges',
        region: 'Nouvelle-Aquitaine',
        architects: ['Roger Gonthier'],
        protection_date: '1975-01-15',
        built: { from: 1925, to: null, period: null, year: 1925 },
        era: '1900_1944',
    }),
    item({
        id: 'b',
        name: 'Metz-Ville',
        commune: 'Metz',
        region: 'Grand Est',
        style: 'Art nouveau',
        architects: ['Jürgen Kröger', 'Roger Gonthier'],
        protection_date: '1975-02-03',
        built: { from: 1904, to: null, period: null, year: 1904 },
        era: '1900_1944',
    }),
    item({
        id: 'c',
        name: 'Chartres',
        protection: 'acr',
        commune: 'Chartres',
        region: 'Centre-Val de Loire',
    }),
    item({
        id: 'd',
        name: 'viaduc de Garabit',
        object: 'viaduct',
        protection: 'classified',
        line: { slug: 'rfn-722000', name: 'Ligne de Béziers à Neussargues' },
        built: { from: 1880, to: null, period: null, year: 1880 },
        era: '1850_1899',
    }),
];

describe('fold', () => {
    it('lowercases and drops accents and punctuation', () => {
        expect(fold('Île-de-France !')).toBe('ile de france');
        expect(fold(null)).toBe('');
    });
});

describe('filterHeritage', () => {
    it('returns everything without filters', () => {
        expect(filterHeritage(rows)).toHaveLength(4);
    });
    it('filters on each field', () => {
        expect(filterHeritage(rows, { protection: 'acr' }).map((r) => r.id)).toEqual(['c']);
        expect(filterHeritage(rows, { object: 'viaduct' }).map((r) => r.id)).toEqual(['d']);
        expect(filterHeritage(rows, { style: 'Art nouveau' }).map((r) => r.id)).toEqual(['b']);
        expect(filterHeritage(rows, { architect: 'Roger Gonthier' }).map((r) => r.id)).toEqual([
            'a',
            'b',
        ]);
        expect(filterHeritage(rows, { region: 'Grand Est' }).map((r) => r.id)).toEqual(['b']);
        expect(filterHeritage(rows, { era: '1900_1944' }).map((r) => r.id)).toEqual(['a', 'b']);
    });
    it('searches name, commune, architect and line without accents or case', () => {
        expect(filterHeritage(rows, { q: 'kroger' }).map((r) => r.id)).toEqual(['b']);
        expect(filterHeritage(rows, { q: 'BEZIERS' }).map((r) => r.id)).toEqual(['d']);
        expect(filterHeritage(rows, { q: 'limoges gonthier' }).map((r) => r.id)).toEqual(['a']);
        expect(filterHeritage(rows, { q: 'introuvable' })).toEqual([]);
    });
    it('combines filters', () => {
        expect(
            filterHeritage(rows, { architect: 'Roger Gonthier', region: 'Grand Est' }),
        ).toHaveLength(1);
    });
});

describe('sortHeritage', () => {
    it('sorts by name without accents, keeping the input intact', () => {
        const copy = [...rows];
        expect(sortHeritage(rows).map((r) => r.id)).toEqual(['c', 'a', 'b', 'd']);
        expect(rows).toEqual(copy);
    });
    it('puts the latest protection first and unknown dates last', () => {
        expect(sortHeritage(rows, 'protection_date').map((r) => r.id)).toEqual([
            'b',
            'a',
            'c',
            'd',
        ]);
    });
    it('puts the oldest construction first and unknown dates last', () => {
        expect(sortHeritage(rows, 'built').map((r) => r.id)).toEqual(['d', 'b', 'a', 'c']);
    });
});

describe('groupByArchitect', () => {
    it('groups by architect, the largest group first, a monument with two architects in both', () => {
        const groups = groupByArchitect(rows);
        expect(groups.map((g) => [g.name, g.items.length])).toEqual([
            ['Roger Gonthier', 2],
            ['Jürgen Kröger', 1],
            [null, 2],
        ]);
    });
    it('has no empty group when every monument has an architect', () => {
        expect(groupByArchitect([rows[0]]).map((g) => g.name)).toEqual(['Roger Gonthier']);
    });
    it('builds a stable anchor', () => {
        expect(groupId('Jürgen Kröger')).toBe('architecte-jurgen-kroger');
    });
});

describe('helpers', () => {
    it('counts by key in the given order', () => {
        expect(countBy(rows, 'protection', ['classified', 'registered', 'acr'])).toEqual({
            classified: 1,
            registered: 2,
            acr: 1,
        });
    });
    it('formats a construction range', () => {
        expect(builtRange({ from: 1914, to: 1985 })).toEqual([1914, 1985]);
        expect(builtRange({ from: 1925, to: null })).toEqual([1925]);
        expect(builtRange({ from: 1925, to: 1925 })).toEqual([1925]);
        expect(builtRange({ from: null, to: null })).toBeNull();
    });
    it('makes one point per located monument and fits the metropolitan bounds only', () => {
        const withReunion = [...rows, item({ id: 'r', lat: -21.1, lng: 55.5 })];
        const points = heritagePoints([...withReunion, item({ id: 'z', lat: NaN })]);
        expect(points.features).toHaveLength(5);
        const bounds = franceBounds(points);
        expect(bounds[0][1]).toBeGreaterThan(40);
        expect(franceBounds({ type: 'FeatureCollection', features: [] })).toBeNull();
    });
});
