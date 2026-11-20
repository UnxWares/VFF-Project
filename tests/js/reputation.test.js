import { describe, expect, it } from 'vitest';
import { nextLevelSentence } from '../../resources/js/lib/reputation.js';

// Traduction de test : renvoie la clé et ses variables, pour vérifier ce que la fonction assemble.
const t = (key, options = {}) => `${key}${JSON.stringify(options)}`;

describe('nextLevelSentence', () => {
    it('renvoie null sans niveau suivant (dernier niveau ou compte suspendu)', () => {
        expect(nextLevelSentence(t, 'fr', null)).toBeNull();
    });

    it('renvoie null quand rien ne manque', () => {
        const next = { level: 'regular', missing: { applied: 0, reviews: 0, acceptance: null } };
        expect(nextLevelSentence(t, 'fr', next)).toBeNull();
    });

    it('liste seulement ce qui manque, dans l ordre contributions, relectures, taux', () => {
        const next = { level: 'experienced', missing: { applied: 3, reviews: 0, acceptance: 80 } };
        const sentence = nextLevelSentence(t, 'en', next);
        expect(sentence).toContain('reputation.profile.next');
        expect(sentence).toContain('reputation.profile.missing_applied{\\"count\\":3}');
        expect(sentence).toContain('reputation.profile.missing_acceptance{\\"count\\":80}');
        expect(sentence).not.toContain('missing_reviews');
        expect(sentence.indexOf('missing_applied')).toBeLessThan(
            sentence.indexOf('missing_acceptance'),
        );
    });

    it('nomme le niveau visé', () => {
        const next = { level: 'regular', missing: { applied: 2, reviews: 0, acceptance: null } };
        expect(nextLevelSentence(t, 'de', next)).toContain('reputation.levels.regular');
    });
});
