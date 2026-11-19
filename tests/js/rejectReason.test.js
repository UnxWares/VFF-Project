import { describe, expect, it } from 'vitest';
import { REASON_MIN, isReasonMissing } from '$lib/rejectReason.js';

describe('isReasonMissing', () => {
    it('refuse un motif absent ou fait de blancs', () => {
        expect(isReasonMissing(undefined)).toBe(true);
        expect(isReasonMissing(null)).toBe(true);
        expect(isReasonMissing('')).toBe(true);
        expect(isReasonMissing('     ')).toBe(true);
    });

    it('compte les caractères une fois les blancs retirés', () => {
        expect(isReasonMissing('  Doub  ')).toBe(true);
        expect(isReasonMissing('  Doublon  ')).toBe(false);
        expect(isReasonMissing('x'.repeat(REASON_MIN))).toBe(false);
        expect(isReasonMissing('x'.repeat(REASON_MIN - 1))).toBe(true);
    });
});
