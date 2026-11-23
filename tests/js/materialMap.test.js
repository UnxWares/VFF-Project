// Carte des lignes d'une série : décennies proposées, état d'une ligne à une décennie, cadre de la carte.
import { describe, expect, it } from 'vitest';
import { decadeRange, decadesOf, lineState, linesBounds } from '$lib/materialMap.js';

const material = { in_service_from: 1971, in_service_to: null };
const dated = { periods: [{ start_date: '1982-08-20', end_date: '1986-05-30' }] };
const undated = { periods: [] };

describe('decadesOf', () => {
    it('va de la décennie de la mise en service à celle de l’année en cours', () => {
        expect(decadesOf(material, [dated], 2026)).toEqual([1970, 1980, 1990, 2000, 2010, 2020]);
    });
    it('s’arrête au retrait quand il est connu et prend en compte les périodes plus anciennes', () => {
        const lines = [{ periods: [{ start_date: '1948-01-01', end_date: null }] }];
        expect(decadesOf({ in_service_from: 1950, in_service_to: 1988 }, lines, 2026)).toEqual([
            1940, 1950, 1960, 1970, 1980,
        ]);
    });
    it('ne propose rien sans aucune date', () => {
        expect(decadesOf({}, [undated], 2026)).toEqual([]);
    });
});

describe('lineState', () => {
    it('allume toutes les lignes quand aucune date n’est choisie', () => {
        expect(lineState(dated, material, null)).toBe('dated');
        expect(lineState(undated, material, null)).toBe('undated');
    });
    it('allume une ligne dont une période recouvre la décennie, même en partie', () => {
        expect(lineState(dated, material, decadeRange(1980))).toBe('dated');
        expect(lineState(dated, material, decadeRange(1990))).toBe('off');
        expect(lineState(dated, material, decadeRange(1970))).toBe('off');
    });
    it('compte une période sans fin à partir de son début, et sans début jusqu’à sa fin', () => {
        const open = { periods: [{ start_date: '1995-01-01', end_date: null }] };
        const closed = { periods: [{ start_date: null, end_date: '1975-01-01' }] };
        expect(lineState(open, material, decadeRange(2010))).toBe('dated');
        expect(lineState(open, material, decadeRange(1980))).toBe('off');
        expect(lineState(closed, material, decadeRange(1970))).toBe('dated');
        expect(lineState(closed, material, decadeRange(1980))).toBe('off');
    });
    it('traite une ligne sans date comme attestée seulement quand la série existait', () => {
        expect(lineState(undated, material, decadeRange(1990))).toBe('undated');
        expect(lineState(undated, material, decadeRange(1950))).toBe('off');
        expect(
            lineState(undated, { in_service_from: 1950, in_service_to: 1988 }, decadeRange(2000)),
        ).toBe('off');
    });
});

describe('linesBounds', () => {
    it('englobe tous les tracés et ignore les lignes sans géométrie', () => {
        const lines = [
            {
                geometry: {
                    type: 'MultiLineString',
                    coordinates: [
                        [
                            [2, 48],
                            [3, 49],
                        ],
                        [[1.5, 47]],
                    ],
                },
            },
            { geometry: null },
        ];
        expect(linesBounds(lines)).toEqual([
            [1.5, 47],
            [3, 49],
        ]);
        expect(linesBounds([{ geometry: null }])).toBeNull();
    });
});
