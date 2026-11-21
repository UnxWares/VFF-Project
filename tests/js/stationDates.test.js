// Dates d'une gare (resources/js/lib/stationDates.js) : même libellé sur la fiche gare, la fiche
// ligne et la carte ; précision « range » = date de fermeture retenue bornée par la frise
// (StationTimeline::resolveClosure).
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { formatStationDate } from '../../resources/js/lib/stationDates.js';

const fr = {
    'stations.dates.range': 'entre :from et :to',
    'stations.dates.range_upper': 'au plus tard en :year',
    'stations.dates.unknown': 'date inconnue',
    'stations.dates.unknown_after': 'date inconnue, après :year',
    'stations.dates.decade': 'années :decade',
    'stations.dates.after': 'après :year',
    'stations.dates.before': 'avant :year',
};
const t = (key, opts = {}) =>
    (fr[key] ?? key).replace(/:(\w+)/g, (_, k) => String(opts[k] ?? `:${k}`));

describe('formatStationDate', () => {
    it('jour, mois, année', () => {
        expect(formatStationDate('1954-11-12', 'day', null, t, 'fr')).toBe('12 novembre 1954');
        expect(formatStationDate('1945-05-01', 'month', null, t, 'fr')).toBe('mai 1945');
        expect(formatStationDate('1992-01-01', 'year', null, t, 'fr')).toBe('1992');
    });

    it('le premier du mois prend « 1er » en français seulement', () => {
        expect(formatStationDate('1867-01-01', 'day', null, t, 'fr')).toBe('1er janvier 1867');
        expect(formatStationDate('1867-11-01', 'day', null, t, 'fr')).toBe('1er novembre 1867');
        expect(formatStationDate('1867-11-11', 'day', null, t, 'fr')).toBe('11 novembre 1867');
        expect(formatStationDate('1867-11-01', 'day', null, t, 'en')).not.toContain('1er');
    });

    it('intervalle borné par la frise', () => {
        expect(formatStationDate('1960-12-31', 'range', 1950, t)).toBe('entre 1951 et 1960');
        expect(formatStationDate('2010-12-31', 'range', 1890, t, 'fr', 2026)).toBe(
            'entre 1891 et 2010',
        );
        // Bornes confondues : l'année seule.
        expect(formatStationDate('1980-12-31', 'range', 1979, t)).toBe('1980');
        // Sans borne basse : au plus tard.
        expect(formatStationDate('1960-12-31', 'range', null, t)).toBe('au plus tard en 1960');
    });

    it('borne haute = année en cours : fermeture non datée, pas « entre 1871 et 2026 »', () => {
        expect(formatStationDate('2026-11-19', 'range', 1870, t, 'fr', 2026)).toBe(
            'date inconnue, après 1870',
        );
        expect(formatStationDate('2026-11-19', 'range', null, t, 'fr', 2026)).toBe('date inconnue');
        // L'année d'avant reste un intervalle borné.
        expect(formatStationDate('2025-12-31', 'range', 1870, t, 'fr', 2026)).toBe(
            'entre 1871 et 2025',
        );
    });

    it('fermetures imprécises des sources', () => {
        expect(formatStationDate('1980-01-01', 'decade', null, t)).toBe('années 1980');
        expect(formatStationDate('1950-01-01', 'after', null, t)).toBe('après 1950');
        expect(formatStationDate('1984-01-01', 'before', null, t)).toBe('avant 1984');
    });

    it('décennie formée selon la langue (« the 1980s », « 1980er-Jahre »)', () => {
        const en = (key, opts = {}) =>
            key === 'stations.dates.decade' ? `the ${opts.decade}` : key;
        const de = (key, opts = {}) =>
            key === 'stations.dates.decade' ? String(opts.decade) : key;
        expect(formatStationDate('1980-01-01', 'decade', null, en, 'en')).toBe('the 1980s');
        expect(formatStationDate('1980-01-01', 'decade', null, en, 'en-GB')).toBe('the 1980s');
        expect(formatStationDate('1980-01-01', 'decade', null, de, 'de')).toBe('1980er-Jahre');
    });

    it('anglais britannique : le jour avant le mois', () => {
        expect(formatStationDate('1981-09-27', 'day', null, t, 'en')).toBe('27 September 1981');
        expect(formatStationDate('1981-09-27', 'day', null, t, 'en-GB')).toBe('27 September 1981');
    });

    it('cas limites : traduction par défaut, mois seul, horodatage, date partielle', () => {
        // Sans traduction fournie : la clé (jamais une erreur).
        expect(formatStationDate('1960-12-31', 'range', null)).toBe('stations.dates.range_upper');
        // Le mois seul ne prend jamais « 1er ».
        expect(formatStationDate('1945-05-01', 'month', null, t, 'fr')).toBe('mai 1945');
        expect(formatStationDate('1867-11-01', 'day', null, t, 'de')).toBe('1. November 1867');
        // Horodatage complet : seule la date compte, quel que soit le fuseau.
        expect(formatStationDate('1954-11-12T23:30:00+01:00', 'day', null, t, 'fr')).toBe(
            '12 novembre 1954',
        );
        expect(formatStationDate('1954', 'day', null, t, 'fr')).toBe('1er janvier 1954');
        // Intervalle dont la borne basse dépasse la borne haute (données incohérentes) : l'année seule.
        expect(formatStationDate('1950-12-31', 'range', 1960, t, 'fr', 2026)).toBe('1950');
    });

    it('sans date', () => {
        expect(formatStationDate(null)).toBeNull();
        expect(formatStationDate('')).toBeNull();
    });

    it('jamais de tiret long dans les libellés', () => {
        for (const lang of ['fr', 'en', 'de']) {
            const php = readFileSync(
                new URL(`../../lang/${lang}/stations.php`, import.meta.url),
                'utf8',
            );
            expect(php).toMatch(/'range' =>/);
            expect(php).toMatch(/'range_upper' =>/);
            expect(php).not.toMatch(/—|--/);
        }
    });
});
