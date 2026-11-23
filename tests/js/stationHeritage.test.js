import { describe, expect, it } from 'vitest';
import { builtText, designerNames, headline, visibleItems, yearOf } from '$lib/stationHeritage.js';

/** Traduction minimale : la clé, puis ses paramètres dans l'ordre alphabétique. */
const t = (key, opts = {}) =>
    `${key}${Object.keys(opts)
        .sort()
        .map((k) => `|${k}=${opts[k]}`)
        .join('')}`;

describe('designerNames', () => {
    it('keeps architects and engineers only, without duplicates', () => {
        const item = {
            architects: [
                { name: 'Victor Laloux', role: 'architecte' },
                { name: 'Jean Dupont', role: 'sculpteur' },
                { name: 'Gustave Eiffel', role: 'ingénieur des Ponts et Chaussées' },
                { name: 'Victor Laloux', role: 'architecte' },
                { name: 'Sans rôle', role: null },
            ],
        };
        expect(designerNames(item)).toEqual(['Victor Laloux', 'Gustave Eiffel']);
        expect(designerNames({})).toEqual([]);
    });
});

describe('yearOf', () => {
    it('reads the year of an ISO date', () => {
        expect(yearOf('1984-12-28')).toBe(1984);
        expect(yearOf(null)).toBeNull();
        expect(yearOf('sans date')).toBeNull();
    });
});

describe('visibleItems', () => {
    const building = { element: 'building', protection: 'registered' };
    const works = { element: 'works', protection: 'classified' };
    const inventory = { element: 'building', protection: 'inventory' };

    it('drops the inventory entry when something stronger exists', () => {
        expect(visibleItems({ items: [building, works, inventory] })).toEqual([building, works]);
    });

    it('keeps one inventory entry when nothing else is known', () => {
        expect(visibleItems({ items: [inventory] })).toEqual([inventory]);
    });

    it('is empty without heritage', () => {
        expect(visibleItems(null)).toEqual([]);
        expect(visibleItems({ items: [] })).toEqual([]);
    });
});

describe('builtText', () => {
    it('reads a year, a range or the century', () => {
        expect(builtText({ built: { from: 1925 } }, t)).toBe(
            'stations.heritage.built_year|year=1925',
        );
        expect(builtText({ built: { from: 1896, to: 1898 } }, t)).toBe(
            'stations.heritage.built_range|from=1896|to=1898',
        );
        expect(builtText({ built: { period: '1er quart 20e siècle' } }, t)).toBe(
            'stations.heritage.built_period|period=1er quart 20e siècle',
        );
        expect(builtText({ built: {} }, t)).toBeNull();
        expect(builtText({}, t)).toBeNull();
    });
});

describe('headline', () => {
    it('states the protection, its year and the architect for a station building', () => {
        const item = {
            element: 'building',
            protection: 'registered',
            protection_date: '1975-01-15',
            architects: [{ name: 'Roger Gonthier', role: 'architecte' }],
        };
        expect(headline(item, t)).toBe(
            'stations.heritage.with_year|text=stations.heritage.building.registered|year=1975, stations.heritage.architect_one|names=Roger Gonthier',
        );
    });

    it('says partial protections and uses the plural for several architects', () => {
        const item = {
            element: 'building',
            protection: 'classified',
            scope: 'partial',
            protection_date: '1972-09-28',
            architects: [
                { name: 'A', role: 'architecte' },
                { name: 'B', role: 'architecte' },
            ],
        };
        const text = headline(item, t);
        expect(text).toContain('building.classified_partial');
        expect(text).toContain('architect_other|names=A, B');
    });

    it('opens a works line on the title and has no architect clause', () => {
        const item = {
            element: 'works',
            protection: 'registered',
            protection_date: '2006-12-26',
            title: 'Dépôt SNCF de Mohon',
            architects: [{ name: 'X', role: 'architecte' }],
        };
        expect(headline(item, t)).toBe(
            'stations.heritage.with_year|text=stations.heritage.works_line|title=Dépôt SNCF de Mohon|what=stations.heritage.works.registered|year=2006',
        );
    });

    it('gives no year to an inventory entry and ignores the scope of a label', () => {
        const item = {
            element: 'building',
            protection: 'inventory',
            protection_date: '2001-01-01',
            scope: 'partial',
        };
        expect(headline(item, t)).toBe('stations.heritage.building.inventory');
        expect(headline({ element: 'building', protection: 'acr', scope: 'partial' }, t)).toBe(
            'stations.heritage.building.acr',
        );
    });
});
