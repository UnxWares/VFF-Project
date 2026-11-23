import { describe, expect, it } from 'vitest';
import {
    countByType,
    filterVenues,
    fold,
    isProtected,
    longDate,
    protectionText,
} from '../../resources/js/lib/preservedStock.js';

const t = (key, opts = {}) =>
    `${key}${opts.date ? `|${opts.date}` : ''}${opts.year ? `|${opts.year}` : ''}`;

const piece = (number, extra = {}) => ({
    number,
    title: null,
    series: [{ slug: 'x', name: 'X 2800' }],
    protection: null,
    ...extra,
});
const venue = (name, type, pieces, commune = null) => ({
    slug: name,
    name,
    type,
    commune,
    count: pieces.length,
    pieces,
});

describe('fold', () => {
    it('ôte accents et capitales', () => {
        expect(fold('Cité du Train')).toBe('cite du train');
        expect(fold(null)).toBe('');
    });
});

describe('longDate', () => {
    it('écrit la date de la notice sans glisser d’un jour', () => {
        expect(longDate('1990-03-27', 'fr')).toBe('27 mars 1990');
        expect(longDate('2000-01-01', 'en')).toContain('2000');
    });
    it('rend le texte tel quel si la date est illisible', () => {
        expect(longDate('nope', 'fr')).toBe('nope');
    });
});

describe('protectionText', () => {
    it('donne la date quand Palissy la donne', () => {
        expect(
            protectionText({ kind: 'classified', date: '1990-03-27', year: 1990 }, t, 'fr'),
        ).toBe('materials_preserved.protection.classified_date|27 mars 1990');
    });
    it('se rabat sur l’année seule, puis sur la nature seule', () => {
        expect(protectionText({ kind: 'listed', date: null, year: 2019 }, t, 'fr')).toBe(
            'materials_preserved.protection.listed_year|2019',
        );
        expect(protectionText({ kind: 'listed', date: null, year: null }, t, 'fr')).toBe(
            'materials_preserved.protection.listed',
        );
    });
    it('ne dit rien quand la nature est inconnue', () => {
        expect(protectionText({ kind: null, date: null, year: null }, t, 'fr')).toBeNull();
        expect(protectionText(null, t, 'fr')).toBeNull();
    });
    it('dit une protection levée sans année seule', () => {
        expect(protectionText({ kind: 'declassified', date: null, year: 2006 }, t, 'fr')).toBe(
            'materials_preserved.protection.declassified',
        );
    });
});

describe('isProtected', () => {
    it('ne compte ni les pièces sans notice ni les déclassées', () => {
        expect(isProtected(piece('A 1', { protection: { kind: 'classified' } }))).toBe(true);
        expect(isProtected(piece('A 2', { protection: { kind: 'declassified' } }))).toBe(false);
        expect(isProtected(piece('A 3'))).toBe(false);
    });
});

describe('filterVenues', () => {
    const venues = [
        venue(
            'Cité du train',
            'museum',
            [piece('CC 7107'), piece('BB 9004', { protection: { kind: 'classified' } })],
            'Mulhouse',
        ),
        venue('AAATV', 'association', [
            piece('141 R 1199', { protection: { kind: 'classified' } }),
        ]),
        venue('Gare', 'site', [piece('X 2804')], 'Vitré'),
    ];
    it('garde tout sans filtre', () => {
        expect(filterVenues(venues, {})).toHaveLength(3);
    });
    it('cherche le lieu ou la commune et garde toutes ses pièces', () => {
        const r = filterVenues(venues, { q: 'mulhouse' });
        expect(r).toHaveLength(1);
        expect(r[0].count).toBe(2);
    });
    it('cherche un numéro et ne garde que les pièces qui répondent', () => {
        const r = filterVenues(venues, { q: '9004' });
        expect(r.map((v) => v.name)).toEqual(['Cité du train']);
        expect(r[0].pieces.map((p) => p.number)).toEqual(['BB 9004']);
        expect(r[0].count).toBe(1);
    });
    it('filtre par type et par protection', () => {
        expect(filterVenues(venues, { type: 'association' })).toHaveLength(1);
        const r = filterVenues(venues, { protectedOnly: true });
        expect(r.map((v) => v.name)).toEqual(['Cité du train', 'AAATV']);
        expect(r[0].count).toBe(1);
    });
    it('cherche aussi le nom de la série', () => {
        expect(filterVenues(venues, { q: 'x 2800' })).toHaveLength(3);
    });
});

describe('countByType', () => {
    it('compte les lieux de chaque type, zéro compris', () => {
        const v = [venue('a', 'museum', []), venue('b', 'museum', []), venue('c', 'site', [])];
        expect(countByType(v, ['museum', 'association', 'site'])).toEqual({
            museum: 2,
            association: 0,
            site: 1,
        });
    });
});
