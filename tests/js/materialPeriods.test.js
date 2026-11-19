import { describe, expect, it } from 'vitest';
import {
    mergedPeriods,
    periodText,
    periodYears,
    periodsSummary,
    sortByFirstPeriod,
} from '$lib/materialPeriods.js';

// Traduction de test : textes français des clés `materials.periods.*`.
const fr = {
    'materials.periods.from': 'à partir de :year',
    'materials.periods.until': 'jusqu’en :year',
    'materials.periods.start': 'début : :date',
    'materials.periods.end': 'fin : :date',
    'materials.periods.section': 'entre :from et :to',
};
const t = (key, opts = {}) =>
    Object.entries(opts).reduce((s, [k, v]) => s.replace(`:${k}`, String(v)), fr[key] ?? key);

const range = (start_date, end_date, start_precision = 'year', end_precision = 'year') => ({
    start_date,
    start_precision,
    end_date,
    end_precision,
});

describe('mergedPeriods', () => {
    it('réunit les intervalles qui se chevauchent ou se touchent', () => {
        const merged = mergedPeriods([
            range('1988-05-29', '2001-01-01', 'day'),
            range('1971-01-01', '1989-01-01'),
        ]);
        expect(merged).toEqual([range('1971-01-01', '2001-01-01')]);
    });

    it('garde le plus ancien début seul et la fin seule la plus récente, hors des intervalles', () => {
        const merged = mergedPeriods([
            range('2009-09-06', null, 'day', null),
            range('2009-06-01', null, 'month', null),
            range(null, '1972-01-01', null, 'year'),
            range('1953-01-01', '1972-01-01'),
            range(null, '2016-05-02', null, 'day'),
            range(null, '2010-01-01', null, 'year'),
        ]);
        expect(merged).toEqual([
            range('1953-01-01', '1972-01-01'),
            range('2009-06-01', null, 'month', null),
            range(null, '2016-05-02', null, 'day'),
        ]);
    });

    it('accepte une liste vide ou absente', () => {
        expect(mergedPeriods([])).toEqual([]);
        expect(mergedPeriods(undefined)).toEqual([]);
    });
});

describe('periodYears et periodsSummary', () => {
    it('écrit les années sans laisser croire que la série circule encore', () => {
        expect(periodYears(range('1971-01-01', '1989-01-01'), t)).toBe('1971-1989');
        expect(periodYears(range('1945-01-01', '1945-06-01'), t)).toBe('1945');
        expect(periodYears(range('1975-06-10', null, 'day', null), t)).toBe('à partir de 1975');
        expect(periodYears(range(null, '2009-09-05', null, 'day'), t)).toBe('jusqu’en 2009');
    });

    it('résume toutes les périodes d’une série', () => {
        expect(
            periodsSummary(
                [
                    range('1942-01-01', '1955-01-01'),
                    range('1924-01-01', '1942-01-01'),
                    range('1963-01-01', null, 'year', null),
                ],
                t,
            ),
        ).toBe('1924-1955, à partir de 1963');
    });
});

describe('periodText', () => {
    it('suit la précision de chaque date', () => {
        expect(periodText(range('1982-08-20', '1986-05-30', 'day', 'day'), t, 'fr')).toBe(
            '20 août 1982 - 30 mai 1986',
        );
        expect(periodText(range('2009-06-01', null, 'month', null), t, 'fr')).toBe(
            'début : juin 2009',
        );
        expect(periodText(range(null, '1972-01-01', null, 'year'), t, 'fr')).toBe('fin : 1972');
        expect(periodText(range('1982-08-20', null, 'day', null), t, 'en-GB')).toBe(
            'début : 20 August 1982',
        );
    });

    it('ajoute la portion quand l’article la nomme', () => {
        expect(
            periodText(
                {
                    ...range('1935-01-01', '1940-01-01'),
                    section_from: 'Saint-Just-en-Chaussée',
                    section_to: 'Cambrai',
                },
                t,
                'fr',
            ),
        ).toBe('1935 - 1940, entre Saint-Just-en-Chaussée et Cambrai');
    });
});

describe('sortByFirstPeriod', () => {
    it('met les séries datées d’abord, de la plus ancienne à la plus récente, puis les autres dans l’ordre reçu', () => {
        const sorted = sortByFirstPeriod([
            { slug: 'a', periods: [] },
            { slug: 'b', periods: [range('2009-09-06', null)] },
            { slug: 'c' },
            { slug: 'd', periods: [range(null, '1972-01-01'), range('1953-01-01', '1972-01-01')] },
        ]);
        expect(sorted.map((m) => m.slug)).toEqual(['d', 'b', 'a', 'c']);
    });
});
