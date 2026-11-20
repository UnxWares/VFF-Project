// Visionneuse des galeries : quand Précédent ou Suivant se désactive alors qu'il a le focus, le focus
// passe à l'autre bouton, ou à l'image, jamais à la page (RGAA 12.8).
import { describe, expect, it } from 'vitest';
import { focusAfterStep, navDisabled } from '$lib/galleryNav.js';

describe('navDisabled', () => {
    it('désactive Précédent sur la première image, Suivant sur la dernière quand tout est chargé', () => {
        expect(navDisabled(0, 5, false)).toEqual({ previous: true, next: false });
        expect(navDisabled(2, 5, false)).toEqual({ previous: false, next: false });
        expect(navDisabled(4, 5, false)).toEqual({ previous: false, next: true });
        expect(navDisabled(4, 5, true)).toEqual({ previous: false, next: false });
    });
});

describe('focusAfterStep', () => {
    it('Précédent depuis la deuxième image : le focus va sur Suivant', () => {
        expect(focusAfterStep(-1, 0, 5, false)).toBe('next');
    });

    it('Suivant vers la dernière image sans page à charger : le focus va sur Précédent', () => {
        expect(focusAfterStep(1, 4, 5, false)).toBe('previous');
    });

    it('les deux boutons désactivés : le focus va sur l’image', () => {
        expect(focusAfterStep(-1, 0, 1, false)).toBe('image');
        expect(focusAfterStep(1, 0, 1, false)).toBe('image');
    });

    it('le bouton activé reste actif : le focus ne bouge pas', () => {
        expect(focusAfterStep(-1, 1, 5, false)).toBeNull();
        expect(focusAfterStep(1, 3, 5, false)).toBeNull();
        expect(focusAfterStep(1, 4, 5, true)).toBeNull();
        // Suivant à la première image : Précédent est désactivé, mais ce n'est pas lui qui a servi.
        expect(focusAfterStep(1, 0, 5, false)).toBeNull();
    });
});
