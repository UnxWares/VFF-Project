// Signal de copie hors ligne (resources/js/lib/offlineCopy.js) : date lue dans les propriétés Inertia.
import { describe, expect, it } from 'vitest';
import { formatOfflineCopyDate, offlineCopyDate } from '$lib/offlineCopy.js';
import { formatLocale } from '$lib/typo.js';

describe('offlineCopyDate', () => {
    it('lit la date ajoutée par le service worker', () => {
        const date = offlineCopyDate({ offline_copy: { saved_at: '2026-11-19T13:05:00.000Z' } });
        expect(date?.toISOString()).toBe('2026-11-19T13:05:00.000Z');
    });

    it('ne signale rien pour une page venue du réseau ou une date illisible', () => {
        expect(offlineCopyDate(undefined)).toBeNull();
        expect(offlineCopyDate({})).toBeNull();
        expect(offlineCopyDate({ offline_copy: { saved_at: null } })).toBeNull();
        expect(offlineCopyDate({ offline_copy: { saved_at: 'hier' } })).toBeNull();
    });
});

describe('formatOfflineCopyDate', () => {
    const date = new Date('2026-11-19T13:05:00Z');

    it('écrit la date et l heure dans la langue de la page (anglais britannique)', () => {
        expect(formatOfflineCopyDate(date, formatLocale('fr'), 'Europe/Paris')).toBe(
            '19 novembre 2026 à 14:05',
        );
        expect(formatOfflineCopyDate(date, formatLocale('en'), 'Europe/Paris')).toBe(
            '19 November 2026 at 14:05',
        );
        expect(formatOfflineCopyDate(date, formatLocale('de'), 'Europe/Paris')).toBe(
            '19. November 2026 um 14:05',
        );
    });
});
