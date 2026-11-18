import { describe, expect, it } from 'vitest';
import { pageWindow } from '../../resources/js/lib/pagination.js';
import { linkLabel } from '../../resources/js/lib/linkLabel.js';

describe('pageWindow', () => {
    it('garde la première et la dernière page, avec des trous', () => {
        expect(pageWindow(6, 90)).toEqual([1, 'gap-4', 4, 5, 6, 7, 8, 'gap-90', 90]);
    });
    it('sans trou quand les pages se suivent', () => {
        expect(pageWindow(1, 4)).toEqual([1, 2, 3, 4]);
        expect(pageWindow(3, 5)).toEqual([1, 2, 3, 4, 5]);
    });
    it('une page de chaque côté sur téléphone', () => {
        expect(pageWindow(4, 10, 1)).toEqual([1, 'gap-3', 3, 4, 5, 'gap-10', 10]);
    });
    it('une seule page', () => {
        expect(pageWindow(1, 1)).toEqual([1]);
    });
    it('aucune page', () => {
        expect(pageWindow(1, 0)).toEqual([]);
    });
    it('clés uniques (rendu Svelte à clés)', () => {
        for (const [c, l] of [
            [1, 90],
            [45, 90],
            [90, 90],
            [2, 3],
        ]) {
            const w = pageWindow(c, l);
            expect(new Set(w).size).toBe(w.length);
        }
    });
});

describe('linkLabel', () => {
    it('titre d’article Wikipédia entre guillemets', () => {
        expect(linkLabel('https://fr.wikipedia.org/wiki/Ligne_de_Briouze_%C3%A0_Couterne')).toBe(
            '« Ligne de Briouze à Couterne »',
        );
        expect(
            linkLabel('https://fr.wikipedia.org/w/index.php?title=Ligne_de_Briouze_%C3%A0_Couterne&oldid=1'),
        ).toBe('« Ligne de Briouze à Couterne »');
    });
    it('domaine et chemin raccourcis avec points de suspension', () => {
        const l = linkLabel(
            'https://ressources.data.sncf.com/explore/dataset/liste-des-gares,lignes-par-type,lignes-lgv/',
            20,
        );
        expect(l).toBe('ressources.data.sncf.com/explore/dataset/lis…');
    });
    it('www retiré, chemin court gardé', () => {
        expect(linkLabel('https://www.wikidata.org/wiki/Q585219')).toBe('wikidata.org/wiki/Q585219');
    });
    it('adresse invalide rendue telle quelle', () => {
        expect(linkLabel('pas une adresse')).toBe('pas une adresse');
    });
});
