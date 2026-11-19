// Fermeture d'un <dialog> par un clic hors de son cadre : géométrie (isOutside) et attachement
// (lightDismiss) avec un <dialog> simulé. Pas de DOM complet : `HTMLDialogElement` est un
// constructeur factice dont le prototype dit si le navigateur gère déjà `closedby`.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { isOutside, lightDismiss } from '$lib/lightDismiss.js';

const RECT = { left: 100, right: 300, top: 50, bottom: 250 };

describe('isOutside', () => {
    it('un point à l’intérieur du cadre n’est pas dehors', () => {
        expect(isOutside(RECT, 200, 150)).toBe(false);
    });
    it('les bords du cadre comptent comme dedans', () => {
        expect(isOutside(RECT, 100, 50)).toBe(false);
        expect(isOutside(RECT, 300, 250)).toBe(false);
    });
    it.each([
        ['à gauche', 99, 150],
        ['à droite', 301, 150],
        ['au-dessus', 200, 49],
        ['au-dessous', 200, 251],
    ])('un point %s est dehors', (_nom, x, y) => {
        expect(isOutside(RECT, x, y)).toBe(true);
    });
});

/** <dialog> simulé : garde ses écouteurs pour rejouer les événements. */
function fakeDialog() {
    const listeners = {};
    return {
        close: vi.fn(),
        getBoundingClientRect: () => RECT,
        addEventListener: vi.fn((type, fn) => {
            listeners[type] = fn;
        }),
        removeEventListener: vi.fn((type) => {
            delete listeners[type];
        }),
        listeners,
    };
}

describe('lightDismiss', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('ne fait rien quand le navigateur gère déjà closedby', () => {
        const NativeDialog = class {};
        NativeDialog.prototype.closedBy = 'any';
        vi.stubGlobal('HTMLDialogElement', NativeDialog);
        const dialog = fakeDialog();
        expect(lightDismiss(dialog)).toBeUndefined();
        expect(dialog.addEventListener).not.toHaveBeenCalled();
    });

    describe('sans closedby natif', () => {
        const attach = () => {
            vi.stubGlobal('HTMLDialogElement', class {});
            const dialog = fakeDialog();
            const cleanup = lightDismiss(dialog);
            return { dialog, cleanup };
        };
        const press = (dialog, x, y) => dialog.listeners.pointerdown({ clientX: x, clientY: y });
        const release = (dialog, x, y) => dialog.listeners.pointerup({ clientX: x, clientY: y });

        it('ferme quand le clic est pressé et relâché hors du cadre', () => {
            const { dialog } = attach();
            press(dialog, 10, 10);
            release(dialog, 12, 14);
            expect(dialog.close).toHaveBeenCalledTimes(1);
        });
        it('ne ferme pas un clic dans le cadre', () => {
            const { dialog } = attach();
            press(dialog, 200, 150);
            release(dialog, 200, 150);
            expect(dialog.close).not.toHaveBeenCalled();
        });
        it('ne ferme pas une sélection de texte qui sort du cadre (pressé dedans, relâché dehors)', () => {
            const { dialog } = attach();
            press(dialog, 200, 150);
            release(dialog, 500, 500);
            expect(dialog.close).not.toHaveBeenCalled();
        });
        it('ne ferme pas un glissé entré par l’extérieur (pressé dehors, relâché dedans)', () => {
            const { dialog } = attach();
            press(dialog, 10, 10);
            release(dialog, 200, 150);
            expect(dialog.close).not.toHaveBeenCalled();
        });
        it('oublie le clic précédent après chaque relâchement', () => {
            const { dialog } = attach();
            press(dialog, 10, 10);
            release(dialog, 200, 150);
            release(dialog, 10, 10);
            expect(dialog.close).not.toHaveBeenCalled();
        });
        it('retire ses écouteurs au démontage', () => {
            const { dialog, cleanup } = attach();
            expect(Object.keys(dialog.listeners).sort()).toEqual(['pointerdown', 'pointerup']);
            cleanup();
            expect(dialog.listeners).toEqual({});
        });
    });
});
