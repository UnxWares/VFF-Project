import { describe, expect, it } from 'vitest';
import {
    chronologyHref,
    dateInYear,
    decadeShares,
    groupByKind,
    yearTotals,
} from '../../resources/js/lib/chronology.js';

describe('chronologyHref', () => {
    it('omet les filtres vides et garde les virgules lisibles', () => {
        expect(chronologyHref()).toBe('/chronology');
        expect(chronologyHref({ decade: 1930 })).toBe('/chronology?decade=1930');
        expect(chronologyHref({ decade: 1930, types: ['opening', 'closure'], region: '53' })).toBe(
            '/chronology?decade=1930&types=opening,closure&region=53',
        );
    });
});

describe('dateInYear', () => {
    it("retire l'année d'une date au jour ou au mois, et nomme l'année seule", () => {
        expect(
            dateInYear({ when: '27 septembre 1981', year: 1981, precision: 'day' }, 'année'),
        ).toBe('27 septembre');
        expect(dateInYear({ when: '9. Dezember 2018', year: 2018, precision: 'day' }, 'Jahr')).toBe(
            '9. Dezember',
        );
        expect(dateInYear({ when: 'juin 1938', year: 1938, precision: 'month' }, 'année')).toBe(
            'juin',
        );
        expect(dateInYear({ when: '1938', year: 1938, precision: 'year' }, 'dans l’année')).toBe(
            'dans l’année',
        );
    });
});

describe('yearTotals', () => {
    it('garde les types détaillés et additionne les faits seulement comptés', () => {
        const totals = yearTotals({
            closure: { shown: 3, hidden: 1, km: 40.5 },
            accident: { shown: 0, hidden: 2, km: 0 },
        });
        expect(totals.shown).toEqual([{ kind: 'closure', shown: 3, km: 40.5 }]);
        expect(totals.hidden).toBe(3);
        expect(yearTotals(undefined)).toEqual({ shown: [], hidden: 0 });
    });
});

describe('decadeShares', () => {
    it('rapporte chaque décennie à la plus fournie, avec un minimum visible', () => {
        const shares = decadeShares([
            { decade: 1820, shown: 1, hidden: 0 },
            { decade: 1880, shown: 400, hidden: 100 },
            { decade: 1890, shown: 0, hidden: 0 },
        ]);
        expect(shares).toEqual({ 1820: 4, 1880: 100, 1890: 0 });
        expect(decadeShares([{ decade: 1820, shown: 0, hidden: 0 }])).toEqual({ 1820: 0 });
    });
});

describe('groupByKind', () => {
    it("range les faits dans l'ordre des types, sans groupe vide", () => {
        const groups = groupByKind(
            [
                { id: 'a', kind: 'closure' },
                { id: 'b', kind: 'milestone' },
                { id: 'c', kind: 'closure' },
            ],
            ['milestone', 'opening', 'closure'],
        );
        expect(groups.map((g) => [g.kind, g.facts.length])).toEqual([
            ['milestone', 1],
            ['closure', 2],
        ]);
    });
});
