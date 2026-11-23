import { describe, expect, it } from 'vitest';
import {
    chartGeometry,
    polyline,
    WIDTH,
    HEIGHT,
} from '../../resources/js/lib/stationAttendance.js';

const year = (y, passengers, series = '2015-2024') => ({ year: y, passengers, series });

describe('chartGeometry', () => {
    it('ne rend rien sans voyageurs', () => {
        expect(chartGeometry([])).toEqual({ segments: [], breakX: null, last: null, max: 0 });
        expect(chartGeometry([year(2020, null)]).segments).toEqual([]);
    });

    it('place la plus petite année à gauche et la plus grande à droite, le maximum en haut', () => {
        const g = chartGeometry([year(2015, 100), year(2016, 200), year(2017, 50)]);
        const pts = g.segments[0].points;
        expect(pts[0].x).toBeLessThan(pts[1].x);
        expect(pts[2].x).toBeCloseTo(WIDTH - 12, 0);
        expect(pts[1].y).toBeLessThan(pts[0].y);
        expect(pts[1].y).toBeLessThan(pts[2].y);
        expect(g.max).toBe(200);
        expect(g.breakX).toBeNull();
        expect(g.segments).toHaveLength(1);
    });

    it('ne relie pas 2024 à 2025 : deux tracés et la rupture entre eux', () => {
        const g = chartGeometry([year(2023, 10), year(2024, 12), year(2025, 11, '2025')]);
        expect(g.segments.map((s) => s.series)).toEqual(['2015-2024', '2025']);
        expect(g.segments[1].points).toHaveLength(1);
        const before = g.segments[0].points.at(-1).x;
        const after = g.segments[1].points[0].x;
        expect(g.breakX).toBeGreaterThan(before);
        expect(g.breakX).toBeLessThan(after);
    });

    it('garde les points dans le cadre, y compris une valeur nulle', () => {
        const g = chartGeometry([year(2015, 0), year(2016, 10)]);
        for (const p of g.segments[0].points) {
            expect(p.y).toBeGreaterThanOrEqual(0);
            expect(p.y).toBeLessThanOrEqual(HEIGHT);
        }
    });

    it('centre une année seule', () => {
        expect(chartGeometry([year(2025, 5, '2025')]).segments[0].points[0].x).toBe(WIDTH / 2);
    });

    it('écrit les points d’une polyligne', () => {
        expect(
            polyline([
                { x: 1, y: 2 },
                { x: 3.5, y: 4 },
            ]),
        ).toBe('1,2 3.5,4');
    });
});
