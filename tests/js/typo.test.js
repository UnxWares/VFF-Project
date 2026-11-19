import { describe, expect, it } from 'vitest';
import {
    colon,
    decadeOf,
    firstOfMonth,
    formatLocale,
    formatNumber,
    quoted,
    semicolon,
    tieUnits,
    withUnit,
} from '../../resources/js/lib/typo.js';
import { cleanUrl, linkLabel } from '../../resources/js/lib/linkLabel.js';

const NBSP = ' ';

describe('typo', () => {
    it('ponctuation double : espace insécable avant en français seulement', () => {
        expect(colon('fr')).toBe(`${NBSP}: `);
        expect(colon('en')).toBe(': ');
        expect(semicolon('fr')).toBe(`${NBSP}; `);
        expect(semicolon('de')).toBe('; ');
    });

    it('guillemets de chaque langue', () => {
        expect(quoted('LGV Sud-Est', 'fr')).toBe(`«${NBSP}LGV Sud-Est${NBSP}»`);
        expect(quoted('LGV Sud-Est', 'en')).toBe('“LGV Sud-Est”');
        expect(quoted('LGV Sud-Est', 'de')).toBe('„LGV Sud-Est“');
    });

    it('anglais britannique pour les dates et nombres', () => {
        expect(formatLocale('en')).toBe('en-GB');
        expect(formatLocale('de')).toBe('de');
        expect(formatLocale(undefined)).toBe('fr');
    });

    it('milliers lisibles dans chaque langue, unité insécable', () => {
        expect(formatNumber(1435, 'fr')).toBe(`1${NBSP}435`);
        expect(formatNumber(1435, 'en')).toBe('1,435');
        expect(formatNumber(1435, 'de')).toBe('1.435');
        expect(withUnit(1435, 'mm', 'fr')).toBe(`1${NBSP}435${NBSP}mm`);
        expect(withUnit(5.4, 'km', 'fr', { maximumFractionDigits: 1 })).toBe(`5,4${NBSP}km`);
    });

    it('unités liées à leur nombre', () => {
        expect(tieUnits('Mise en service de 49,8 km')).toBe(`Mise en service de 49,8${NBSP}km`);
        expect(tieUnits('TVM 430 (jusqu’à 320 km/h)')).toBe(`TVM 430 (jusqu’à 320${NBSP}km/h)`);
        expect(tieUnits('1\u202f435 mm')).toBe(`1${NBSP}435${NBSP}mm`);
        expect(tieUnits('2 %')).toBe(`2${NBSP}%`);
        expect(tieUnits('12 mars')).toBe('12 mars');
    });

    it('« 1er » pour le premier du mois en français', () => {
        expect(firstOfMonth('1 décembre 1950', 'fr')).toBe('1er décembre 1950');
        expect(firstOfMonth('ouverte : 1 mai 1857', 'fr')).toBe('ouverte : 1er mai 1857');
        expect(firstOfMonth('11 décembre 1950', 'fr')).toBe('11 décembre 1950');
        expect(firstOfMonth('21 mai 1857', 'fr')).toBe('21 mai 1857');
        expect(firstOfMonth('1 December 1950', 'en')).toBe('1 December 1950');
    });

    it('décennie formée selon la langue', () => {
        expect(decadeOf(1930, 'fr')).toBe('1930');
        expect(decadeOf(1930, 'en')).toBe('1930s');
        expect(decadeOf(1930, 'en-GB')).toBe('1930s');
        expect(decadeOf(1930, 'de')).toBe('1930er-Jahre');
        expect(decadeOf(1930)).toBe('1930');
    });
});

describe('cleanUrl', () => {
    it('garde la seule adresse quand un commentaire la suit', () => {
        expect(
            cleanUrl('https://ressources.data.sncf.com/ (liste-des-gares, lignes-par-type)'),
        ).toBe('https://ressources.data.sncf.com/');
        expect(linkLabel('https://ressources.data.sncf.com/ (liste-des-gares)')).toBe(
            'ressources.data.sncf.com',
        );
    });
});
