import { describe, expect, it } from 'vitest';
import {
    axisOf,
    barOf,
    builderChoices,
    byDecade,
    decadeTicks,
    endKind,
    filterRows,
    groupDated,
    periodLabel,
    sortTable,
    stateIn,
    undatedRows,
    yearLeft,
    yearSummary,
} from '../../resources/js/lib/materialTimeline.js';

const row = (name, from, to, status = null, extra = {}) => ({
    slug: name.toLowerCase().replace(/\s+/g, '-'),
    name,
    group: 'electrique',
    builders: ['Alstom'],
    in_service_from: from,
    in_service_to: to,
    service_status: status,
    units_built: null,
    ...extra,
});

describe('stateIn', () => {
    it('lit les dates qui encadrent l’année', () => {
        const bb9200 = row('BB 9200', 1957, 2015);
        expect(stateIn(bb9200, 1956)).toBe('no');
        expect(stateIn(bb9200, 1957)).toBe('yes');
        expect(stateIn(bb9200, 2015)).toBe('yes');
        expect(stateIn(bb9200, 2016)).toBe('no');
    });
    it('sépare « en service » (déclaré) et « possible » (fin non renseignée)', () => {
        expect(stateIn(row('BB 15000', 1971, null, 'in_service'), 2026)).toBe('yes');
        expect(stateIn(row('TGV Sud-Est', 1981, null, 'withdrawn'), 1990)).toBe('maybe');
        expect(stateIn(row('X', 1950, null), 2000)).toBe('maybe');
    });
    it('exclut une série sans date de mise en service', () => {
        expect(stateIn(row('CC 72000', null, null, 'in_service'), 2000)).toBe('no');
        expect(stateIn(row('A', null, 1993), 1980)).toBe('no');
    });
});

describe('endKind et barOf', () => {
    const axis = { start: 1850, end: 2030, span: 180 };
    it('nomme la fin de chaque barre', () => {
        expect(endKind(row('a', 1900, 1950))).toBe('range');
        expect(endKind(row('a', 1900, null, 'in_service'))).toBe('ongoing');
        expect(endKind(row('a', 1900, null, 'withdrawn'))).toBe('withdrawn');
        expect(endKind(row('a', 1900, null))).toBe('unknown');
    });
    it('place 1957-2015 de 1957 à la fin de 2015, sans bord estompé', () => {
        const bar = barOf(row('BB 9200', 1957, 2015), axis, 2026);
        expect(bar.left).toBeCloseTo(((1957 - 1850) / 180) * 100);
        expect(bar.width).toBeCloseTo((59 / 180) * 100);
        expect(bar.fade).toBe(0);
    });
    it('estompe le bord d’une fin non renseignée', () => {
        expect(barOf(row('a', 1981, null, 'withdrawn'), axis, 2026).fade).toBeGreaterThan(0);
        expect(barOf(row('a', 1950, null), axis, 2026).width).toBeCloseTo((77 / 180) * 100);
    });
    it('ne place pas une série sans mise en service', () => {
        expect(barOf(row('a', null, 1993), axis, 2026)).toBeNull();
    });
    it('garde une largeur positive pour une série d’un an', () => {
        expect(barOf(row('a', 1900, 1900), axis, 2026).width).toBeGreaterThan(0);
    });
});

describe('axe et repères', () => {
    it('couvre de la décennie de la plus ancienne série à celle qui suit aujourd’hui', () => {
        const axis = axisOf([row('a', 1852, 1900), row('b', 1971, null)], 2026);
        expect(axis).toEqual({ start: 1850, end: 2030, span: 180 });
        const ticks = decadeTicks(axis);
        expect(ticks).toHaveLength(19);
        expect(ticks[0]).toMatchObject({ year: 1850, left: 0, major: true });
        expect(ticks.at(-1).left).toBe(100);
    });
    it('place une année au milieu de sa case', () => {
        expect(yearLeft(1850, { start: 1850, end: 2030, span: 180 })).toBeCloseTo(0.5 / 1.8);
    });
});

describe('filtres, regroupement, listes', () => {
    const rows = [
        row('B', 1960, null),
        row('A', 1950, 1980, null, { group: 'vapeur', builders: ['Baldwin Locomotive Works'] }),
        row('C', null, 1970),
        row('D', 1950, 1960),
    ];
    it('filtre par traction, constructeur sans accents et nom', () => {
        expect(filterRows(rows, { group: 'vapeur' }).map((r) => r.name)).toEqual(['A']);
        expect(filterRows(rows, { builder: 'bALDWIN' }).map((r) => r.name)).toEqual(['A']);
        expect(filterRows(rows, { q: 'c' }).map((r) => r.name)).toEqual(['C']);
    });
    it('regroupe les séries datées par traction, triées par mise en service', () => {
        const g = groupDated(rows, ['vapeur', 'electrique', 'diesel']);
        expect(g.map((x) => x.group)).toEqual(['vapeur', 'electrique']);
        expect(g[1].rows.map((r) => r.name)).toEqual(['D', 'B']);
    });
    it('met à part les séries sans date', () => {
        expect(undatedRows(rows).map((r) => r.name)).toEqual(['C']);
    });
    it('liste par décennie, séries sans date omises', () => {
        const d = byDecade(rows);
        expect(d.map((x) => x.decade)).toEqual([1950, 1960]);
        expect(d[0].rows.map((r) => r.name)).toEqual(['A', 'D']);
    });
});

describe('yearSummary', () => {
    it('compte les séries sûres, les possibles et les exemplaires connus', () => {
        const rows = [
            row('a', 1950, 1990, null, { units_built: 100 }),
            row('b', 1950, null, 'in_service', { units_built: 20 }),
            row('c', 1950, null, 'withdrawn', { units_built: 5 }),
            row('d', 1950, null, 'in_service'),
            row('e', 2000, null, 'in_service', { units_built: 9 }),
        ];
        expect(yearSummary(rows, 1960)).toEqual({ yes: 3, maybe: 1, units: 120 });
    });
});

describe('sortTable', () => {
    const rows = [row('B', 1960, null), row('A', null, 1970), row('C', 1950, 1960)];
    it('trie par année, valeurs absentes en dernier dans les deux sens', () => {
        expect(sortTable(rows, 'from').map((r) => r.name)).toEqual(['C', 'B', 'A']);
        expect(sortTable(rows, 'from', 'desc').map((r) => r.name)).toEqual(['B', 'C', 'A']);
    });
    it('trie par nom', () => {
        expect(sortTable(rows, 'name', 'desc').map((r) => r.name)).toEqual(['C', 'B', 'A']);
    });
});

describe('periodLabel', () => {
    const t = (key, opts) => `${key}:${opts?.year ?? ''}`;
    it('écrit la période', () => {
        expect(periodLabel(row('a', 1957, 2015), t)).toBe('1957-2015');
        expect(periodLabel(row('a', 1971, null, 'in_service'), t)).toBe(
            'materials_timeline.period.from:1971',
        );
        expect(periodLabel(row('a', 1981, null, 'withdrawn'), t)).toBe(
            'materials_timeline.period.withdrawn:1981',
        );
        expect(periodLabel(row('a', null, 1993), t)).toBe('materials_timeline.period.until:1993');
        expect(periodLabel(row('a', null, null), t)).toBe('materials_timeline.period.none:');
    });
});

describe('builderChoices', () => {
    it('range les constructeurs du plus fréquent au moins fréquent', () => {
        const rows = [
            row('a', 1900, null, null, { builders: ['Decauville', 'Renault'] }),
            row('b', 1900, null, null, { builders: ['Renault'] }),
            row('c', 1900, null, null, { builders: [] }),
        ];
        expect(builderChoices(rows)).toEqual(['Renault', 'Decauville']);
    });
});
