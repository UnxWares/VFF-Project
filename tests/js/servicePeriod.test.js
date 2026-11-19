import { describe, expect, it } from 'vitest';
import { servicePeriod, servicePeriodText } from '../../resources/js/lib/servicePeriod.js';
import { formatStationDate } from '../../resources/js/lib/stationDates.js';

// Mêmes cas témoins que tests/Feature/StationServiceEndTest.php.
describe('servicePeriod', () => {
    it('ne montre pas une fin non datée comme une année', () => {
        expect(
            servicePeriod({ first_year: 1869, last_year: 2026, last_year_kind: 'undated' }),
        ).toEqual({ key: 'period_from_undated', params: { from: 1869 }, fromStation: false });
        expect(servicePeriod({ last_year: 2026, last_year_kind: 'undated' }).key).toBe(
            'period_undated',
        );
    });

    it('dit « au plus tard » pour une borne haute', () => {
        expect(
            servicePeriod({ first_year: 1872, last_year: 1989, last_year_kind: 'upper_bound' }),
        ).toEqual({
            key: 'period_from_to_bound',
            params: { from: 1872, to: 1989 },
            fromStation: false,
        });
        expect(servicePeriod({ last_year: 1989, last_year_kind: 'upper_bound' }).key).toBe(
            'period_to_bound',
        );
    });

    it('garde les années sûres et signale celles tirées des dates de la gare', () => {
        expect(
            servicePeriod({ first_year: 1872, last_year: 1970, last_year_kind: 'year' }),
        ).toEqual({
            key: 'period_from_to',
            params: { from: 1872, to: 1970 },
            fromStation: false,
        });
        expect(
            servicePeriod({ first_year: 1865, last_year: null, years_source: 'station' }),
        ).toEqual({ key: 'period_from', params: { from: 1865 }, fromStation: true });
        // Réponse antérieure à last_year_kind : année simple.
        expect(servicePeriod({ last_year: 1954 }).key).toBe('period_to');
    });

    it('garde « jamais desservie » et ne dit rien sans années', () => {
        expect(
            servicePeriod({ first_year: 1994, last_year: 1956, never_served: 'line_closed' }),
        ).toEqual({
            key: 'never_served.line_closed',
            params: { from: 1994, to: 1956 },
            fromStation: false,
        });
        expect(servicePeriod({ first_year: null, last_year: null })).toBeNull();
    });
});

// Textes français de lang/fr/stations.php (stations.lines.*) utiles aux cas ci-dessous.
const FR = {
    period_from_to: 'Desservie de :from à :to',
    period_from_to_bound: 'Desservie de :from à :to au plus tard',
    'never_served.line_closed':
        'Jamais desservie par cette ligne : ligne fermée en :to, gare ouverte en :from',
    'dated.from_to': 'Desservie :from :to',
    'dated.from': 'Desservie :since',
    'dated.to': 'Desservie :until',
    'dated.from_to_bound': 'Desservie :from à :to au plus tard',
    'dated.from_undated': 'Desservie :start, fin non datée',
    'bound.from.year': 'de :date',
    'bound.from.month': 'de :date',
    'bound.from.month_vowel': 'd’:date',
    'bound.from.day': 'du :date',
    'bound.to.year': 'à :date',
    'bound.to.month': 'à :date',
    'bound.to.month_vowel': 'à :date',
    'bound.to.day': 'au :date',
    'bound.since.day': 'depuis le :date',
    'bound.until.day': 'jusqu’au :date',
    'bound.start.month_vowel': 'à partir d’:date',
};
const t = (key, params = {}) =>
    (FR[key.replace('stations.lines.', '')] ?? key).replace(/:(\w+)/g, (_, k) => String(params[k]));
const fr = (iso, precision) => formatStationDate(iso, precision, null, t, 'fr');
/** Texte de la période, espaces insécables des dates rendues en espaces simples. */
const text = (line) => servicePeriodText(line, t, fr)?.text.replaceAll('\u00a0', ' ');
const day = (date) => ({ date, precision: 'day', event_id: 1 });

describe('servicePeriodText', () => {
    it('écrit les dates exactes quand l’API les donne au jour (Saint-Calais sur 507000)', () => {
        const line = {
            first_year: 1879,
            last_year: 1938,
            last_year_kind: 'year',
            service_start: day('1879-03-31'),
            service_end: day('1938-05-15'),
        };
        expect(servicePeriodText(line, t, fr)).toEqual({
            text: 'Desservie du 31\u00a0mars\u00a01879 au 15\u00a0mai\u00a01938',
            fromStation: false,
        });
    });

    it('mélange une date au jour et une année, et écrit le premier du mois « 1er »', () => {
        const line = { first_year: 1873, last_year: 1965, service_start: day('1873-02-01') };
        expect(text(line)).toBe('Desservie du 1er février 1873 à 1965');
        expect(text({ first_year: 1873, service_start: day('1873-02-20') })).toBe(
            'Desservie depuis le 20 février 1873',
        );
        expect(text({ last_year: 1938, service_end: day('1938-05-15') })).toBe(
            'Desservie jusqu’au 15 mai 1938',
        );
    });

    it('garde une date au mois au mois, avec l’élision devant une voyelle', () => {
        const line = {
            first_year: 1869,
            last_year: 2026,
            last_year_kind: 'undated',
            service_start: { date: '1869-04-01', precision: 'month', event_id: 2 },
        };
        expect(text(line)).toBe('Desservie à partir d’avril 1869, fin non datée');
    });

    it('reste en années sans date exacte, pour une date à l’année et pour « jamais desservie »', () => {
        expect(text({ first_year: 1872, last_year: 1970 })).toBe('Desservie de 1872 à 1970');
        const yearOnly = { date: '1872-01-01', precision: 'year', event_id: 3 };
        expect(text({ first_year: 1872, last_year: 1970, service_start: yearOnly })).toBe(
            'Desservie de 1872 à 1970',
        );
        expect(
            text({
                first_year: 1857,
                last_year: 1857,
                never_served: 'line_closed',
                service_start: day('1857-11-24'),
            }),
        ).toBe('Jamais desservie par cette ligne : ligne fermée en 1857, gare ouverte en 1857');
        expect(servicePeriodText({}, t, fr)).toBeNull();
    });

    it('garde la borne « au plus tard » en année', () => {
        const line = {
            first_year: 1872,
            last_year: 1989,
            last_year_kind: 'upper_bound',
            service_start: day('1872-06-10'),
        };
        expect(text(line)).toBe('Desservie du 10 juin 1872 à 1989 au plus tard');
    });
});
