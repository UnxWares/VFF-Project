import { describe, expect, it } from 'vitest';
import { fitWords } from '$lib/fitWords.js';

const measure = (text) => text.length * 10;
const TEXT = 'Nom de ligne ou de gare, code de ligne…';

describe('fitWords', () => {
    it('rend le texte tel quel s’il tient', () => {
        expect(fitWords(TEXT, 1000, measure)).toBe(TEXT);
    });

    it('coupe à la fin d’un mot, sans virgule avant les points', () => {
        expect(fitWords(TEXT, 280, measure)).toBe('Nom de ligne ou de gare…');
        expect(fitWords(TEXT, 110, measure)).toBe('Nom de…');
    });

    it('ne coupe jamais un mot : un premier mot trop large reste entier', () => {
        expect(fitWords(TEXT, 5, measure)).toBe(TEXT);
        expect(fitWords('', 100, measure)).toBe('');
        expect(fitWords(TEXT, 0, measure)).toBe(TEXT);
    });
});
