// Dates d'une gare (resources/js/lib/stationDates.js) : même libellé sur la fiche gare, la fiche
// ligne et la carte ; précision « range » = date de fermeture retenue bornée par la frise
// (StationTimeline::resolveClosure).
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { formatStationDate } from '../../resources/js/lib/stationDates.js';

const fr = {
    'stations.dates.range': 'entre :from et :to',
    'stations.dates.range_upper': 'au plus tard en :year',
    'stations.dates.decade': 'années :year',
    'stations.dates.after': 'après :year',
    'stations.dates.before': 'avant :year',
};
const t = (key, opts = {}) => (fr[key] ?? key).replace(/:(\w+)/g, (_, k) => String(opts[k] ?? `:${k}`));

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
        expect(formatStationDate('2026-10-19', 'range', 1890, t)).toBe('entre 1891 et 2026');
        // Bornes confondues : l'année seule.
        expect(formatStationDate('1980-12-31', 'range', 1979, t)).toBe('1980');
        // Sans borne basse : au plus tard.
        expect(formatStationDate('1960-12-31', 'range', null, t)).toBe('au plus tard en 1960');
    });

    it('fermetures imprécises des sources', () => {
        expect(formatStationDate('1980-01-01', 'decade', null, t)).toBe('années 1980');
        expect(formatStationDate('1950-01-01', 'after', null, t)).toBe('après 1950');
        expect(formatStationDate('1984-01-01', 'before', null, t)).toBe('avant 1984');
    });

    it('sans date', () => {
        expect(formatStationDate(null)).toBeNull();
        expect(formatStationDate('')).toBeNull();
    });

    it('jamais de tiret long dans les libellés', () => {
        for (const lang of ['fr', 'en', 'de']) {
            const php = readFileSync(new URL(`../../lang/${lang}/stations.php`, import.meta.url), 'utf8');
            expect(php).toMatch(/'range' =>/);
            expect(php).toMatch(/'range_upper' =>/);
            expect(php).not.toMatch(/—|--/);
        }
    });
});
