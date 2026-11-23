import { describe, expect, it } from 'vitest';
import {
    mergedPeriods,
    periodText,
    periodYears,
    periodsSummary,
    endText,
    relationNotes,
    relationsList,
    splitDoubtful,
    doubtText,
    earliestText,
} from '$lib/materialPeriods.js';

// Traduction de test : textes français des clés `materials.periods.*`.
const fr = {
    'materials.periods.from': 'à partir de :year',
    'materials.periods.until': 'jusqu’en :year',
    'materials.periods.start': 'début : :date',
    'materials.periods.end': 'fin : :date',
    'materials.periods.section': 'entre :from et :to',
    'materials.end.line': 'Sur la ligne jusqu’en :year',
    'materials.end.series_in_service': 'Série encore en service',
    'materials.end.series_withdrawn': 'Série retirée',
    'materials.end.series_withdrawn_year': 'Série retirée en :year',
    'materials.end.unknown': 'Fin inconnue sur la ligne',
    'materials.end.since': 'Sur la ligne à partir de :year',
    'materials.relations.notes.fuel': 'au biodiesel :fuel (:feedstock) depuis :since',
    'materials.relations.notes.reported': 'bilan publié en :date',
    'materials.relations.feedstock.rapeseed': 'colza',
    'materials.doubt.reasons.trial': 'essais',
    'materials.doubt.reasons.other': 'mention incertaine',
    'materials.earliest.electrification': 'Sur la ligne au plus tôt à l’électrification : :date',
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

describe('relationsList', () => {
    it('réunit les relations de la fiche et celles des périodes, une par libellé', () => {
        const relations = [
            { label: 'Paris-Montparnasse - Granville', service: 'TER', periods: [] },
            { label: 'Argentan - Granville', service: null },
        ];
        const periods = [
            {
                ...range('1990-01-01', '2009-01-01'),
                relation: { label: 'Paris-Montparnasse - Granville', service: 'TER' },
            },
            { ...range(null, '1977-01-01'), relation: { label: 'Dreux - Granville' } },
            range('1950-01-01', '1960-01-01'),
        ];
        expect(relationsList(relations, periods, t)).toEqual([
            'TER Paris-Montparnasse - Granville (1990-2009)',
            'Argentan - Granville',
            'Dreux - Granville (jusqu’en 1977)',
        ]);
    });

    it('ne renvoie rien sans relation', () => {
        expect(relationsList(undefined, [range('1950-01-01', '1960-01-01')], t)).toEqual([]);
    });
});

describe('endText', () => {
    it('dit la fin sur la ligne, sinon celle de la série, sans jamais inventer de date', () => {
        expect(endText({ kind: 'line', year: 2019 }, t)).toBe('Sur la ligne jusqu’en 2019');
        expect(endText({ kind: 'series_withdrawn', year: 2019 }, t)).toBe('Série retirée en 2019');
        expect(endText({ kind: 'series_withdrawn', year: null }, t)).toBe('Série retirée');
        expect(endText({ kind: 'series_in_service', year: null }, t)).toBe(
            'Série encore en service',
        );
        expect(endText({ kind: 'unknown', year: null }, t)).toBe('Fin inconnue sur la ligne');
        expect(endText(undefined, t)).toBe('Fin inconnue sur la ligne');
        expect(endText({ kind: 'series_in_service', year: null, since: 2014 }, t)).toBe(
            'Sur la ligne à partir de 2014. Série encore en service',
        );
        expect(endText({ kind: 'line', year: 2015, since: null }, t)).toBe(
            'Sur la ligne jusqu’en 2015',
        );
    });
});

describe('relationNotes', () => {
    const b100 = {
        kind: 'fuel',
        fuel: 'B100',
        feedstock: 'rapeseed',
        since: '2021-04-01',
        since_precision: 'month',
        reported: '2023-01-17',
        reported_precision: 'day',
        sources: [
            {
                title: 'Bilan',
                publisher: 'Région Normandie',
                date: '2023-01-17',
                url: 'https://oleo100.com/x.pdf',
            },
        ],
    };

    it('écrit le carburant, son début au mois et le mois du dernier bilan, une seule fois', () => {
        const notes = relationNotes(
            [
                { label: 'Paris-Montparnasse - Granville', notes: [b100] },
                { label: 'Paris - Granville', notes: [b100] },
                { label: 'Argentan - Granville', notes: [] },
            ],
            t,
            'fr',
        );
        expect(notes).toEqual([
            {
                label: 'Paris-Montparnasse - Granville',
                text: 'au biodiesel B100 (colza) depuis avril 2021, bilan publié en janvier 2023',
                sources: b100.sources,
            },
        ]);
    });

    it('ne renvoie rien sans note', () => {
        expect(relationNotes(undefined, t)).toEqual([]);
        expect(relationNotes([{ label: 'A - B' }], t)).toEqual([]);
    });
});

describe('séries citées sans service attesté', () => {
    it('range à part les séries dont le lien est mis en doute', () => {
        const a = { slug: 'a', link_doubt: null };
        const b = { slug: 'b', link_doubt: 'trial' };
        const c = { slug: 'c' };
        expect(splitDoubtful([a, b, c])).toEqual({ served: [a, c], doubtful: [b] });
        expect(splitDoubtful(undefined)).toEqual({ served: [], doubtful: [] });
    });

    it('dit la raison, ou une mention incertaine pour une raison inconnue', () => {
        expect(doubtText('trial', t)).toBe('essais');
        expect(doubtText('nouvelle_raison', t)).toBe('mention incertaine');
    });
});

describe('borne d’électrification', () => {
    it('dit la date la plus ancienne à la précision de la source', () => {
        expect(
            earliestText({ reason: 'electrification', date: '1996-06-01', precision: 'month' }, t),
        ).toBe('Sur la ligne au plus tôt à l’électrification : juin 1996');
        expect(
            earliestText({ reason: 'electrification', date: '1996-06-01', precision: 'year' }, t),
        ).toBe('Sur la ligne au plus tôt à l’électrification : 1996');
    });

    it('ne dit rien sans borne ni pour un motif inconnu', () => {
        expect(earliestText(null, t)).toBeNull();
        expect(earliestText({ reason: 'autre', date: '1996-06-01' }, t)).toBeNull();
    });
});
