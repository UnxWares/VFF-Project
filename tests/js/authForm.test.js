import { describe, expect, it } from 'vitest';
import { PASSWORD_MIN, isEmail } from '../../resources/js/Pages/Auth/authForm.js';

describe('isEmail', () => {
    it('accepte une adresse ordinaire, avec espaces autour', () => {
        expect(isEmail('marie@example.org')).toBe(true);
        expect(isEmail('  marie.dupont+vff@sub.example.org ')).toBe(true);
    });
    it('refuse une adresse sans arobase, sans domaine ou avec une espace', () => {
        expect(isEmail('marie')).toBe(false);
        expect(isEmail('marie@')).toBe(false);
        expect(isEmail('marie@example')).toBe(false);
        expect(isEmail('ma rie@example.org')).toBe(false);
        expect(isEmail('')).toBe(false);
    });
});

describe('PASSWORD_MIN', () => {
    it('suit la règle du serveur (8 caractères)', () => {
        expect(PASSWORD_MIN).toBe(8);
    });
});
