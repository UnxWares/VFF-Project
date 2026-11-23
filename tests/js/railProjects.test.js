import { describe, expect, it } from 'vitest';
import {
    countBy,
    countByRegion,
    filterProjects,
    formatStageDate,
    plannedCommissioning,
} from '../../resources/js/lib/railProjects.js';

const rows = [
    {
        id: 'a',
        name: 'Réouverture d’Angoulême à Limoges',
        state: 'study',
        kind: 'reopening',
        regions: ['Nouvelle-Aquitaine'],
        porteurs: [{ name: 'Région Nouvelle-Aquitaine' }],
        lines: [{ slug: 'rfn-610000', name: 'Ligne de Limoges-Bénédictins à Angoulême' }],
        planned_commissioning: null,
    },
    {
        id: 'b',
        name: 'Lyon - Turin',
        state: 'works',
        kind: 'lgv',
        regions: ['Auvergne-Rhône-Alpes', 'Provence-Alpes-Côte d’Azur'],
        porteurs: [{ name: 'TELT' }],
        lines: [],
        planned_commissioning: {
            date: '2033',
            source: { label: 'TELT', url: 'https://example.org/telt' },
        },
    },
];

describe('filterProjects', () => {
    it('returns everything without a filter', () => {
        expect(filterProjects(rows)).toHaveLength(2);
    });
    it('searches without accents in name, porteur, region and line', () => {
        expect(filterProjects(rows, { q: 'angouleme' }).map((p) => p.id)).toEqual(['a']);
        expect(filterProjects(rows, { q: 'telt' }).map((p) => p.id)).toEqual(['b']);
        expect(filterProjects(rows, { q: 'nouvelle aquitaine' }).map((p) => p.id)).toEqual(['a']);
        expect(filterProjects(rows, { q: 'benedictins' }).map((p) => p.id)).toEqual(['a']);
    });
    it('filters by state, kind and region, alone or together', () => {
        expect(filterProjects(rows, { state: 'works' }).map((p) => p.id)).toEqual(['b']);
        expect(filterProjects(rows, { kind: 'reopening' }).map((p) => p.id)).toEqual(['a']);
        expect(filterProjects(rows, { region: 'Auvergne-Rhône-Alpes' }).map((p) => p.id)).toEqual([
            'b',
        ]);
        expect(filterProjects(rows, { state: 'works', kind: 'reopening' })).toEqual([]);
    });
});

describe('counts', () => {
    it('counts by state in the given order, zero included', () => {
        expect(countBy(rows, 'state', ['study', 'debate', 'works'])).toEqual({
            study: 1,
            debate: 0,
            works: 1,
        });
    });
    it('counts a project in each of its regions', () => {
        expect(countByRegion(rows)).toEqual({
            'Nouvelle-Aquitaine': 1,
            'Auvergne-Rhône-Alpes': 1,
            'Provence-Alpes-Côte d’Azur': 1,
        });
    });
});

describe('formatStageDate', () => {
    it('keeps the precision of the source', () => {
        expect(formatStageDate('2028', 'fr')).toBe('2028');
        expect(formatStageDate('2028-03', 'fr')).toBe('mars 2028');
        expect(formatStageDate('2023-05-12', 'fr')).toBe('12 mai 2023');
        expect(formatStageDate('2023-05-12', 'en')).toBe('May 12, 2023');
    });
    it('does not shift the day with the time zone', () => {
        expect(formatStageDate('2024-01-01', 'fr')).toBe('1 janvier 2024');
    });
});

describe('plannedCommissioning', () => {
    it('is null without a planned commissioning', () => {
        expect(plannedCommissioning(rows[0])).toBeNull();
    });
    it('gives the date with its source, never a bare date', () => {
        expect(plannedCommissioning(rows[1], 'fr')).toEqual({
            date: '2033',
            source: 'TELT',
            url: 'https://example.org/telt',
        });
    });
});
