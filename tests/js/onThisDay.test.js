import { describe, expect, it } from 'vitest';
import {
    dayLabel,
    daysInMonth,
    keyLabel,
    monthName,
    onThisDayHref,
} from '../../resources/js/lib/onThisDay.js';

describe('onThisDayHref', () => {
    it("donne l'adresse d'un jour, avec ou sans les accidents", () => {
        expect(onThisDayHref(null)).toBe('/on-this-day');
        expect(onThisDayHref('11-23')).toBe('/on-this-day/11-23');
        expect(onThisDayHref('11-23', { accidents: false })).toBe('/on-this-day/11-23?accidents=0');
    });
});

describe('dayLabel', () => {
    it('écrit le jour dans la langue, 1er en français', () => {
        expect(dayLabel(11, 23, 'fr')).toBe('23 novembre');
        expect(dayLabel(11, 1, 'fr')).toBe('1er novembre');
        expect(dayLabel(2, 29, 'fr')).toBe('29 février');
        expect(dayLabel(11, 23, 'en')).toBe('23 November');
        expect(dayLabel(11, 23, 'de')).toBe('23. November');
    });
    it('lit une clé MM-JJ', () => {
        expect(keyLabel('07-01', 'fr')).toBe('1er juillet');
    });
});

describe('daysInMonth et monthName', () => {
    it('compte 29 jours en février et nomme les mois', () => {
        expect(daysInMonth(2)).toBe(29);
        expect(daysInMonth(4)).toBe(30);
        expect(daysInMonth(12)).toBe(31);
        expect(monthName(3, 'fr')).toBe('mars');
        expect(monthName(3, 'de')).toBe('März');
    });
});
