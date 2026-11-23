import { describe, expect, it } from 'vitest';
import { correctionValue, documentDate } from '../../resources/js/lib/accidentContext.js';

describe('documentDate', () => {
    it('garde la précision de la date du document', () => {
        expect(documentDate(null)).toBeNull();
        expect(documentDate('1917')).toBe('1917');
        expect(documentDate('1917-12', 'fr')).toMatch(/décembre 1917/);
        expect(documentDate('1917-12-14', 'fr')).toMatch(/14 décembre 1917/);
        expect(documentDate('1917-12-14', 'en')).toMatch(/14 December 1917/);
    });
});

describe('correctionValue', () => {
    it('met en forme selon le champ corrigé', () => {
        expect(correctionValue('deaths', null)).toBeNull();
        expect(correctionValue('occurred_at_time', '17:11', 'fr')).toBe('17 h 11');
        expect(correctionValue('occurred_at_time', '17:11', 'en')).toBe('17:11');
        expect(correctionValue('commune_insee', '42156', 'fr')).toBe('42156');
        expect(
            correctionValue('kind', 'collision', 'fr', (k) =>
                k === 'accidents.kinds.collision' ? 'Collision' : k,
            ),
        ).toBe('Collision');
        expect(correctionValue('kind', 'collision', 'fr')).toBe('collision');
        expect(correctionValue('occurred_on', '2013-07-12', 'fr')).toMatch(/12 juillet 2013/);
        expect(correctionValue('injuries', 1200, 'fr')).toMatch(/^1\s200$/u);
    });
});
