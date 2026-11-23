// Dates d'une gare (resources/js/lib/stationDates.js) : même libellé sur la fiche gare, la fiche
// ligne et la carte ; précision « range » = date de fermeture retenue bornée par la frise
// (StationTimeline::resolveClosure).
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    directoryClosing,
    directoryOpening,
    formatStationDate,
    formatStationOpening,
} from '../../resources/js/lib/stationDates.js';

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

/** Traduction tirée de lang/{fr,en,de}/stations.php (clés stations.dates.*), pas d'une copie. */
const translator = (lang) => {
    const php = readFileSync(new URL(`../../lang/${lang}/stations.php`, import.meta.url), 'utf8');
    const dates = php.slice(php.indexOf("'dates' => ["));
    return (key, opts = {}) => {
        const name = key.replace('stations.dates.', '');
        const raw = new RegExp(`'${name}' => '([^']*)'`).exec(dates)?.[1] ?? key;
        return raw.replace(/:(\w+)/g, (_, k) => String(opts[k] ?? `:${k}`));
    };
};

describe('formatStationOpening', () => {
    it('ouverture encadrée : « entre A et B » dans les trois langues', () => {
        // opened_after = 1954 : ouverte entre 1955 (année de la ligne) et 1970 (première ère desservie).
        expect(
            formatStationOpening('1970-12-31', 'range', 1954, translator('fr'), 'fr', 2026),
        ).toBe('entre 1955 et 1970');
        expect(
            formatStationOpening('1970-12-31', 'range', 1954, translator('en'), 'en', 2026),
        ).toBe('between 1955 and 1970');
        expect(
            formatStationOpening('1970-12-31', 'range', 1954, translator('de'), 'de', 2026),
        ).toBe('zwischen 1955 und 1970');
    });

    it("la borne haute peut être l'année en cours (gare desservie à l'ère courante)", () => {
        // Une fermeture dirait « date inconnue, après … » ; une ouverture reste un intervalle.
        expect(
            formatStationOpening('2026-11-22', 'range', 2009, translator('fr'), 'fr', 2026),
        ).toBe('entre 2010 et 2026');
        expect(formatStationDate('2026-11-22', 'range', 2009, translator('fr'), 'fr', 2026)).toBe(
            'date inconnue, après 2009',
        );
    });

    it("bornes confondues : l'année seule", () => {
        expect(
            formatStationOpening('1955-12-31', 'range', 1954, translator('fr'), 'fr', 2026),
        ).toBe('1955');
    });

    it('les autres précisions ne changent pas', () => {
        const t = translator('fr');
        for (const [iso, precision, after] of [
            ['1954-11-12', 'day', null],
            ['1945-05-01', 'month', null],
            ['1992-01-01', 'year', null],
            ['1980-01-01', 'decade', null],
            ['1950-01-01', 'after', null],
            ['1984-01-01', 'before', null],
            // Un `after` envoyé avec une autre précision est ignoré, comme avant.
            ['1954-11-12', 'day', 1950],
            // Intervalle sans borne basse : « au plus tard », comme avant.
            ['1960-12-31', 'range', null],
        ]) {
            expect(formatStationOpening(iso, precision, after, t, 'fr', 2026)).toBe(
                formatStationDate(iso, precision, after, t, 'fr', 2026),
            );
        }
        expect(formatStationOpening('1960-12-31', 'range', null, t, 'fr', 2026)).toBe(
            'au plus tard en 1960',
        );
    });

    it('sans date', () => {
        expect(formatStationOpening(null, 'range', 1954)).toBeNull();
        expect(formatStationOpening('')).toBeNull();
    });
});

describe('directoryOpening (annuaire des gares)', () => {
    it('écrit « entre A et B » pour une ouverture encadrée, dans les trois langues', () => {
        const row = { opened_year: 1970, opened_precision: 'range', opened_after: 1954 };
        expect(directoryOpening(row, translator('fr'), 'fr')).toBe('entre 1955 et 1970');
        expect(directoryOpening(row, translator('en'), 'en')).toBe('between 1955 and 1970');
        expect(directoryOpening(row, translator('de'), 'de')).toBe('zwischen 1955 und 1970');
    });

    it('garde l année seule sans intervalle, sans borne basse ou avec des bornes confondues', () => {
        const t = translator('fr');
        expect(directoryOpening({ opened_year: 1970, opened_precision: 'day' }, t)).toBe('1970');
        expect(directoryOpening({ opened_year: 1970, opened_precision: 'range' }, t)).toBe('1970');
        expect(
            directoryOpening(
                { opened_year: 1970, opened_precision: 'range', opened_after: 1969 },
                t,
            ),
        ).toBe('1970');
    });

    it('rend une chaîne vide sans année', () => {
        expect(directoryOpening({ opened_year: null })).toBe('');
    });
});

describe('directoryClosing (annuaire des gares)', () => {
    it('ne présente jamais une borne comme une année de fermeture', () => {
        const t = translator('fr');
        const year = new Date().getFullYear();
        expect(
            directoryClosing(
                { closed_year: 1990, closed_precision: 'range', closed_after: 1970 },
                t,
            ),
        ).toBe('entre 1971 et 1990');
        expect(directoryClosing({ closed_year: 2014, closed_precision: 'range' }, t)).toBe(
            'au plus tard en 2014',
        );
        // Borne = année du calcul (fermeture non datée) : ni « 2026 » ni un jour.
        expect(
            directoryClosing(
                { closed_year: year, closed_precision: 'range', closed_after: 1970 },
                t,
            ),
        ).not.toContain(String(year));
    });

    it('garde l année d une fermeture datée et rend une chaîne vide sans année', () => {
        const t = translator('fr');
        expect(directoryClosing({ closed_year: 1938, closed_precision: 'day' }, t)).toBe('1938');
        expect(directoryClosing({ closed_year: null })).toBe('');
    });
});
