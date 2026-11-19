import { describe, expect, it } from 'vitest';
import {
    DESCRIPTION_MAX,
    DESCRIPTION_MIN,
    isWebUrl,
    reportDate,
    reportErrors,
} from '../../resources/js/lib/errorReport.js';

describe('reportErrors', () => {
    const valid = { description: 'La ligne a fermé en 1938, pas en 1939.' };

    it('accepte un texte seul, sans source ni adresse', () => {
        expect(reportErrors(valid)).toEqual({});
    });

    it('exige un texte, d’au moins dix caractères et de 2 000 au plus', () => {
        expect(reportErrors({}).description.key).toBe('reports.errors.description_required');
        expect(reportErrors({ description: '   ' }).description.key).toBe(
            'reports.errors.description_required',
        );
        expect(reportErrors({ description: 'Faux.' }).description).toEqual({
            key: 'reports.errors.description_min',
            params: { min: DESCRIPTION_MIN },
        });
        expect(reportErrors({ description: 'x'.repeat(DESCRIPTION_MAX + 1) }).description).toEqual({
            key: 'reports.errors.description_max',
            params: { max: DESCRIPTION_MAX },
        });
        expect(reportErrors({ description: 'x'.repeat(DESCRIPTION_MAX) })).toEqual({});
    });

    it('vérifie la source et l’adresse seulement si elles sont remplies', () => {
        expect(reportErrors({ ...valid, source_url: 'gallica.bnf.fr' }).source_url.key).toBe(
            'reports.errors.source_url',
        );
        expect(reportErrors({ ...valid, source_url: 'https://gallica.bnf.fr/ark:/1' })).toEqual({});
        expect(reportErrors({ ...valid, contact_email: 'nom@' }).contact_email.key).toBe(
            'reports.errors.contact_email',
        );
        expect(reportErrors({ ...valid, contact_email: 'nom@exemple.fr' })).toEqual({});
    });
});

describe('isWebUrl', () => {
    it('n’accepte que les adresses http et https complètes', () => {
        expect(isWebUrl('https://fr.wikipedia.org/wiki/Ligne')).toBe(true);
        expect(isWebUrl('http://exemple.fr')).toBe(true);
        expect(isWebUrl('javascript:alert(1)')).toBe(false);
        expect(isWebUrl('ftp://exemple.fr')).toBe(false);
        expect(isWebUrl('exemple.fr')).toBe(false);
    });
});

describe('reportDate', () => {
    it('écrit la date dans la langue de la page', () => {
        const iso = '2026-11-19T23:30:00+00:00';
        expect(reportDate(iso, 'fr', 'Europe/Paris')).toBe('20 novembre 2026');
        expect(reportDate(iso, 'en', 'Europe/Paris')).toBe('20 November 2026');
        expect(reportDate(iso, 'de', 'Europe/Paris')).toBe('20. November 2026');
    });

    it('renvoie une chaîne vide pour une date absente ou illisible', () => {
        expect(reportDate(null, 'fr')).toBe('');
        expect(reportDate('pas une date', 'fr')).toBe('');
    });
});
