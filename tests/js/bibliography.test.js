import { describe, expect, it } from 'vitest';
import {
    authorLine,
    imprintLine,
    linkKind,
    oldestBnfDate,
    safeUrl,
} from '../../resources/js/lib/bibliography.js';

describe('authorLine', () => {
    it('joint un, deux ou trois auteurs', () => {
        expect(authorLine([], 'fr')).toBe('');
        expect(authorLine(['Jean Dupont'], 'fr')).toBe('Jean Dupont');
        expect(authorLine(['Jean Dupont', 'Marie Martin'], 'fr')).toBe(
            'Jean Dupont et Marie Martin',
        );
        expect(authorLine(['A B', 'C D', 'E F'], 'fr')).toBe('A B, C D et E F');
    });

    it('abrège au-delà de trois auteurs', () => {
        expect(authorLine(['A', 'B', 'C', 'D'], 'fr')).toBe('A, B, C et al.');
    });

    it('ignore les valeurs vides', () => {
        expect(authorLine(['', 'Jean Dupont', null], 'fr')).toBe('Jean Dupont');
    });
});

describe('imprintLine', () => {
    it('donne éditeur, lieu et année dans cet ordre', () => {
        expect(
            imprintLine({
                publisher: 'Les éditions du Cabri',
                place: 'Breil-sur-Roya',
                year: 2025,
            }),
        ).toBe('Les éditions du Cabri, Breil-sur-Roya, 2025');
    });

    it('saute ce qui manque', () => {
        expect(imprintLine({ publisher: null, place: null, year: 1992 })).toBe('1992');
        expect(imprintLine({})).toBe('');
    });

    it('place la revue devant', () => {
        expect(
            imprintLine({ journal: 'Connaissance du Rail', year: 1993 }, (j) => `dans ${j}`),
        ).toBe('dans Connaissance du Rail, 1993');
    });
});

describe('linkKind', () => {
    it('reconnaît les trois fournisseurs et se rabat sur Wikipédia', () => {
        expect(linkKind({ source: 'bnf' })).toBe('bnf');
        expect(linkKind({ source: 'wikidata' })).toBe('wikidata');
        expect(linkKind({ source: 'wikipedia' })).toBe('wikipedia');
        expect(linkKind({ source: 'autre' })).toBe('wikipedia');
    });
});

describe('safeUrl', () => {
    it('ne laisse passer que http et https', () => {
        expect(safeUrl('https://catalogue.bnf.fr/ark:/12148/cb1')).toBe(
            'https://catalogue.bnf.fr/ark:/12148/cb1',
        );
        expect(safeUrl('javascript:alert(1)')).toBeNull();
        expect(safeUrl(null)).toBeNull();
    });
});

describe('oldestBnfDate', () => {
    it('prend la plus ancienne date des notices de la BnF', () => {
        const items = [
            { source: 'bnf', fetched_on: '2026-11-23' },
            { source: 'wikipedia', fetched_on: '2026-01-01' },
            { source: 'bnf', fetched_on: '2026-11-20' },
        ];
        expect(oldestBnfDate(items)).toBe('2026-11-20');
        expect(oldestBnfDate([{ source: 'wikipedia' }])).toBeNull();
        expect(oldestBnfDate(undefined)).toBeNull();
    });
});
