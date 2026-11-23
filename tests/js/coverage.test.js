import { describe, expect, it } from 'vitest';
import { barWidth, formatShare, metricOf, ratio } from '../../resources/js/lib/coverage.js';

describe('formatShare', () => {
    it('écrit une décimale au plus, avec la virgule et l’espace insécable en français', () => {
        expect(formatShare(0.6294, 'fr')).toBe('62,9 %');
        expect(formatShare(1, 'fr')).toBe('100 %');
        expect(formatShare(0, 'de')).toBe('0 %');
    });
    it('écrit le point sans espace en anglais', () => {
        expect(formatShare(0.6294, 'en')).toBe('62.9%');
    });
    it('ne montre jamais 0 pour une part non nulle', () => {
        expect(formatShare(0.0001, 'fr')).toBe('0,1 %');
    });
    it('rend null sans part', () => {
        expect(formatShare(null)).toBeNull();
        expect(formatShare(undefined)).toBeNull();
    });
});

describe('barWidth', () => {
    it('borne la largeur entre 0 et 100', () => {
        expect(barWidth(0.5)).toBe(50);
        expect(barWidth(1.4)).toBe(100);
        expect(barWidth(-1)).toBe(0);
        expect(barWidth(null)).toBe(0);
        expect(barWidth(Number.NaN)).toBe(0);
    });
});

describe('metricOf', () => {
    it('trouve une mesure par sa clé', () => {
        const section = { metrics: [{ key: 'photo' }, { key: 'heritage' }] };
        expect(metricOf(section, 'heritage')).toEqual({ key: 'heritage' });
        expect(metricOf(section, 'absent')).toBeNull();
        expect(metricOf(undefined, 'photo')).toBeNull();
    });
});

describe('ratio', () => {
    it('rend null sans dénominateur', () => {
        expect(ratio(3, 4)).toBe(0.75);
        expect(ratio(3, 0)).toBeNull();
    });
});
