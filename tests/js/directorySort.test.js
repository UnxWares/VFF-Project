import { describe, expect, it } from 'vitest';
import {
    defaultSort,
    effectiveSort,
    sortOptions,
    sortParam,
} from '../../resources/js/lib/directorySort.js';

describe('tri des pages Gares et Lignes', () => {
    it('trie par pertinence avec une recherche, par nom sans', () => {
        expect(defaultSort('st hilaire')).toBe('relevance');
        expect(defaultSort('  ')).toBe('name');
        expect(defaultSort('')).toBe('name');
    });

    it('garde le tri choisi par le visiteur', () => {
        expect(effectiveSort('city', 'vire')).toBe('city');
        expect(effectiveSort('name', 'vire')).toBe('name');
        expect(effectiveSort('', 'vire')).toBe('relevance');
        expect(effectiveSort('relevance', '')).toBe('name');
    });

    it("n'écrit pas le tri par défaut dans l'adresse", () => {
        expect(sortParam('', 'vire')).toBe('');
        expect(sortParam('relevance', 'vire')).toBe('');
        expect(sortParam('name', 'vire')).toBe('name');
        expect(sortParam('name', '')).toBe('');
        expect(sortParam('relevance', '')).toBe('');
        expect(sortParam('code', '')).toBe('code');
    });

    it('ne propose la pertinence qu’avec une recherche', () => {
        const options = [
            ['relevance', 'r'],
            ['name', 'n'],
        ];
        expect(sortOptions(options, 'vire')).toEqual(options);
        expect(sortOptions(options, '')).toEqual([['name', 'n']]);
    });
});
