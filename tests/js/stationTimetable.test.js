import { describe, expect, it } from 'vitest';
import {
    directionPlaces,
    longDate,
    mainFeed,
    placeParts,
    typeCounts,
} from '../../resources/js/lib/stationTimetable.js';

describe('typeCounts', () => {
    it('garde les types présents dans l ordre fixe, sans les types nuls', () => {
        expect(typeCounts({ ter: 124, intercites: 20, tgv: 0 })).toEqual([
            { type: 'intercites', trains: 20 },
            { type: 'ter', trains: 124 },
        ]);
    });

    it('rend une liste vide sans données', () => {
        expect(typeCounts(null)).toEqual([]);
        expect(typeCounts(undefined)).toEqual([]);
    });
});

describe('directionPlaces', () => {
    const a = { name: 'Paris', slug: 'paris', trains: 3 };

    it('nomme les destinations des trains qui repartent', () => {
        expect(directionPlaces({ destinations: [a], origins: [] })).toEqual({
            places: [a],
            arrivals: false,
        });
    });

    it('à défaut nomme les provenances (terminus)', () => {
        expect(directionPlaces({ destinations: [], origins: [a] })).toEqual({
            places: [a],
            arrivals: true,
        });
    });

    it('supporte un sens vide', () => {
        expect(directionPlaces({})).toEqual({ places: [], arrivals: true });
    });
});

describe('longDate', () => {
    it('met une date ISO en toutes lettres dans la langue', () => {
        expect(longDate('2026-12-08', 'fr')).toBe('8 décembre 2026');
        expect(longDate('2026-12-08', 'en')).toBe('8 December 2026');
    });

    it('rend une chaîne vide pour une valeur invalide', () => {
        expect(longDate(null, 'fr')).toBe('');
        expect(longDate('demain', 'fr')).toBe('');
    });
});

describe('placeParts', () => {
    it('coupe la liste en morceaux et garde la gare de chaque nom', () => {
        const places = [
            { name: 'Brive', slug: 'brive' },
            { name: 'Tulle', slug: 'tulle' },
            { name: 'Ussel', slug: null },
        ];
        const parts = placeParts(places, 'fr');

        expect(parts.map((p) => p.text).join('')).toBe('Brive, Tulle et Ussel');
        expect(parts.filter((p) => p.place).map((p) => p.place.name)).toEqual([
            'Brive',
            'Tulle',
            'Ussel',
        ]);
    });

    it('rend rien sans lieu', () => {
        expect(placeParts([], 'fr')).toEqual([]);
    });
});

describe('mainFeed', () => {
    it('prend le premier flux', () => {
        expect(mainFeed([{ feed: 'sncf' }, { feed: 'transilien' }])).toEqual({ feed: 'sncf' });
        expect(mainFeed([])).toBeNull();
    });
});
