import { describe, expect, it } from 'vitest';
import { peakOf, percentOf, periodOf, sum } from '../../resources/js/lib/networkFigures.js';

describe('peakOf', () => {
    it("rend la ligne la plus haute, la première en cas d'égalité", () => {
        const rows = [
            { year: 1920, open_km: 59066 },
            { year: 1930, open_km: 60499 },
            { year: 1940, open_km: 51880 },
        ];
        expect(peakOf(rows, (r) => r.open_km).year).toBe(1930);
        expect(peakOf([], (r) => r.open_km)).toBeNull();
    });
});

describe('periodOf', () => {
    it("distingue la première ère, les ères passées et l'ère courante", () => {
        expect(periodOf({ year: 1830, from_year: null, current: false })).toEqual({
            kind: 'until',
            from: null,
            to: 1830,
        });
        expect(periodOf({ year: 1930, from_year: 1921, current: false })).toEqual({
            kind: 'range',
            from: 1921,
            to: 1930,
        });
        expect(periodOf({ year: 2030, from_year: 2021, current: true })).toEqual({
            kind: 'since',
            from: 2021,
            to: 2030,
        });
    });
});

describe('percentOf et sum', () => {
    it('arrondit une part et somme des valeurs', () => {
        expect(percentOf(0.5189)).toBe(52);
        expect(percentOf(null)).toBeNull();
        expect(sum([1, 2, 3])).toBe(6);
    });
});
