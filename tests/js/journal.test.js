import { describe, expect, it } from 'vitest';
import { formatJournalDate, isFallback, isSiteLink } from '../../resources/js/lib/journal.js';

describe('formatJournalDate', () => {
    it('formate la date dans la langue de la page, sans décalage de fuseau', () => {
        expect(formatJournalDate('2026-11-22', 'fr')).toBe('22 novembre 2026');
        expect(formatJournalDate('2026-11-22', 'en')).toBe('22 November 2026');
        expect(formatJournalDate('2026-11-22', 'de')).toBe('22. November 2026');
    });

    it('écrit « 1er » le premier du mois en français', () => {
        expect(formatJournalDate('1912-08-01', 'fr')).toBe('1er août 1912');
        expect(formatJournalDate('1912-08-01', 'en')).toBe('1 August 1912');
    });

    it('rend telle quelle une date illisible', () => {
        expect(formatJournalDate('bientôt', 'fr')).toBe('bientôt');
    });
});

describe('isSiteLink', () => {
    it('laisse le navigateur ouvrir les adresses qui ne sont pas des pages du site', () => {
        const click = { button: 0 };
        for (const href of [
            '/journal/atom.xml',
            '/journal/rss.xml?lang=fr',
            '/docs/api#x',
            '/api/v1/stats',
            '/graphiql',
            '/storage/a.pdf',
            '/api',
        ])
            expect(isSiteLink({ href }, click), href).toBe(false);
        expect(isSiteLink({ href: '/journal/mon-article#note' }, click)).toBe(true);
        expect(isSiteLink({ href: '/documents-x' }, click)).toBe(true);
    });

    const click = { button: 0 };

    it('garde la navigation du site pour une page interne', () => {
        expect(isSiteLink({ href: '/lines/rfn-878000' }, click)).toBe(true);
        expect(isSiteLink({ href: '/stations/morez', target: null }, click)).toBe(true);
    });

    it('laisse faire le navigateur pour les ancres, liens externes et nouveaux onglets', () => {
        expect(isSiteLink({ href: '#note-1' }, click)).toBe(false);
        expect(isSiteLink({ href: 'https://fr.wikipedia.org/' }, click)).toBe(false);
        expect(isSiteLink({ href: '//example.org/' }, click)).toBe(false);
        expect(isSiteLink({ href: '/lines/x', target: '_blank' }, click)).toBe(false);
        expect(isSiteLink({ href: '/lines/x' }, { button: 0, ctrlKey: true })).toBe(false);
        expect(isSiteLink({ href: '/lines/x' }, { button: 1 })).toBe(false);
        expect(isSiteLink({ href: '/lines/x' }, { button: 0, defaultPrevented: true })).toBe(false);
    });
});

describe('isFallback', () => {
    it('signale un texte servi dans une autre langue que la page', () => {
        expect(isFallback('fr', 'en')).toBe(true);
        expect(isFallback('fr', 'fr')).toBe(false);
        expect(isFallback('en', 'en')).toBe(false);
        expect(isFallback('fr', undefined)).toBe(false);
    });
});
