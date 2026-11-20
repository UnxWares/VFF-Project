import { describe, expect, it } from 'vitest';
import {
    countActiveSettings,
    isActiveSetting,
    REVEALED_CONTROL,
    revealedFocusTarget,
} from '$lib/directoryFilters.js';

describe('réglages actifs des annuaires', () => {
    it('liste hors première option, case cochée, champ rempli', () => {
        expect(isActiveSetting({ selectedIndex: 0 })).toBe(false);
        expect(isActiveSetting({ selectedIndex: 2 })).toBe(true);
        expect(isActiveSetting({ type: 'checkbox', checked: true })).toBe(true);
        expect(isActiveSetting({ type: 'checkbox', checked: false })).toBe(false);
        expect(isActiveSetting({ type: 'text', value: '' })).toBe(false);
        expect(isActiveSetting({ type: 'text', value: '14' })).toBe(true);
    });
    it('compte les réglages actifs', () => {
        expect(
            countActiveSettings([
                { selectedIndex: 1 },
                { selectedIndex: 0 },
                { type: 'checkbox', checked: true },
            ]),
        ).toBe(2);
    });

    it('ouverture au clavier : focus sur le premier filtre révélé, jamais au clic', () => {
        const target = { id: 'stations-status' };
        const fields = { querySelector: (sel) => (sel === REVEALED_CONTROL ? target : null) };
        expect(revealedFocusTarget(true, 0, fields)).toBe(target);
        expect(revealedFocusTarget(true, 1, fields)).toBeNull();
        expect(revealedFocusTarget(false, 0, fields)).toBeNull();
        expect(revealedFocusTarget(true, 0, null)).toBeNull();
        expect(revealedFocusTarget(true, 0, { querySelector: () => null })).toBeNull();
    });
});
