import { describe, expect, it } from 'vitest';
import {
    companiesOfKind,
    foldLines,
    projectMap,
    yearOf,
    KINDS,
} from '../../resources/js/lib/companySheet.js';

describe('companySheet', () => {
    it('groups companies of one kind by creation date', () => {
        const list = [
            { slug: 'b', kind: 'grande-compagnie', founded: { date: '1857-07-03' } },
            { slug: 'a', kind: 'grande-compagnie', founded: { date: '1838-08-13' } },
            { slug: 'c', kind: 'sncf', founded: { date: '1938-01-01' } },
        ];
        expect(companiesOfKind(list, 'grande-compagnie').map((c) => c.slug)).toEqual(['a', 'b']);
        expect(companiesOfKind(list, 'secondaire')).toEqual([]);
        expect(KINDS).toContain('infrastructure');
    });

    it('reads the year of a date', () => {
        expect(yearOf('1845-09-20')).toBe(1845);
    });

    it('projects a map with a corrected longitude and a downward latitude', () => {
        const projected = projectMap({
            bbox: [0, 40, 10, 50],
            paths: [
                [
                    [0, 50],
                    [10, 40],
                ],
            ],
        });
        expect(projected.d.startsWith('M15.0 15.0L')).toBe(true);
        expect(projected.height).toBeGreaterThan(projected.width);
        expect(projected.viewBox).toBe(
            `0 0 ${projected.width.toFixed(1)} ${projected.height.toFixed(1)}`,
        );
        expect(projectMap(null)).toBeNull();
        expect(projectMap({ bbox: [0, 0, 0, 0], paths: [] })).toBeNull();
    });

    it('folds a list', () => {
        expect(foldLines([1, 2, 3, 4], 3)).toEqual([[1, 2, 3], [4]]);
        expect(foldLines([1], 3)).toEqual([[1], []]);
    });
});
