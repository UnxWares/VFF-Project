import { describe, expect, it } from 'vitest';
import { notificationDate, unreadSummary } from '../../resources/js/lib/notifications.js';

/** Traduction factice : renvoie la clé et le nombre, pour vérifier ce qui est demandé. */
const t = (key, opts = {}) => (opts.count !== undefined ? `${key}:${opts.count}` : key);

describe('notificationDate', () => {
    const iso = '2026-11-19T13:05:00+00:00';

    it('écrit la date et l’heure dans la langue de la page', () => {
        expect(notificationDate(iso, 'fr', 'Europe/Paris')).toBe('19 novembre 2026 à 14:05');
        expect(notificationDate(iso, 'en', 'Europe/Paris')).toBe('19 November 2026 at 14:05');
        expect(notificationDate(iso, 'de', 'Europe/Paris')).toBe('19. November 2026 um 14:05');
    });

    it('renvoie une chaîne vide pour une date absente ou illisible', () => {
        expect(notificationDate(null, 'fr')).toBe('');
        expect(notificationDate('', 'fr')).toBe('');
        expect(notificationDate('pas une date', 'fr')).toBe('');
    });
});

describe('unreadSummary', () => {
    it('donne le nombre non lu, ou dit que tout est lu', () => {
        expect(unreadSummary(2, t)).toBe('notifications.unread_count:2');
        expect(unreadSummary(0, t)).toBe('notifications.all_read');
    });
});
