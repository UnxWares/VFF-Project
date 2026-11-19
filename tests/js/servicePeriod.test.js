import { describe, expect, it } from 'vitest';
import { servicePeriod } from '../../resources/js/lib/servicePeriod.js';

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
