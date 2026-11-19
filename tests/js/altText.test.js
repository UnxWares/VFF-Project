import { describe, expect, it } from 'vitest';
import { ALT_MAX, conciseAlt, figureLabel } from '../../resources/js/lib/altText.js';

describe('conciseAlt', () => {
    it('garde une légende courte telle quelle, espaces normalisés', () => {
        expect(conciseAlt('  AUBIN -  La Gare ')).toBe('AUBIN - La Gare');
    });

    it('renvoie une chaîne vide sans légende', () => {
        expect(conciseAlt(null)).toBe('');
        expect(conciseAlt(undefined)).toBe('');
    });

    it('coupe une longue légende à la fin d’un mot, sans ponctuation pendante', () => {
        const long =
            'Passage sur la LGV BPL pas encore en service commercial, au niveau de la commune de Chantenay-Villedieu, de la rame TGV n° 772 Ouigo lors d’une marche à vide.';
        const alt = conciseAlt(long);
        expect(alt.length).toBeLessThanOrEqual(ALT_MAX + 1);
        expect(alt.endsWith('…')).toBe(true);
        expect(alt).not.toMatch(/[\s,;:]…$/u);
        expect(long.startsWith(alt.slice(0, -1))).toBe(true);
    });

    it('coupe un mot unique trop long à la limite', () => {
        expect(conciseAlt('x'.repeat(200), 10)).toBe(`${'x'.repeat(10)}…`);
    });
});

describe('figureLabel', () => {
    it('joint les morceaux non vides par une virgule', () => {
        expect(figureLabel([1913, ' La gare ', null, '', 'CC BY-SA 4.0'])).toBe(
            '1913, La gare, CC BY-SA 4.0',
        );
    });
});
