import { describe, expect, it } from 'vitest';
import {
    altitudeDomain,
    areaPath,
    gradeAt,
    labelledStations,
    linePath,
    nearestIndex,
    niceStep,
    ticks,
} from '../../resources/js/lib/lineProfile.js';

/** Points tous les 100 m, altitude donnée par une fonction de la distance. */
const series = (length, z, step = 100) =>
    Array.from({ length: length / step + 1 }, (_, i) => [i * step, z(i * step)]);

describe('graduations', () => {
    it('choisit des pas ronds', () => {
        expect(niceStep(1000, 4)).toBe(200);
        expect(niceStep(801, 4)).toBe(200);
        expect(niceStep(37, 4)).toBe(10);
        expect(niceStep(0, 4)).toBe(1);
        expect(ticks(0, 36.7, 4)).toEqual([0, 10, 20, 30]);
        expect(ticks(578, 1379, 4)).toEqual([600, 800, 1000, 1200]);
    });

    it("élargit l'axe des altitudes au pas rond, avec au moins 40 m d'amplitude", () => {
        expect(
            altitudeDomain([
                [0, 578.3],
                [1, 1379.4],
            ]),
        ).toEqual([400, 1400]);
        // Ligne presque plate : pas de relief inventé par l'échelle.
        expect(
            altitudeDomain([
                [0, 109],
                [1, 112],
            ]),
        ).toEqual([90, 140]);
        expect(altitudeDomain([[0, null]])).toEqual([0, 100]);
    });
});

describe('tracé', () => {
    const x = (d) => d / 10;
    const y = (z) => 100 - z;

    it('coupe la courbe là où l’altitude manque', () => {
        const points = [
            [0, 10],
            [100, 20],
            [200, null],
            [300, 30],
            [400, 40],
        ];
        expect(linePath(points, x, y)).toBe('M0.0,90.0L10.0,80.0M30.0,70.0L40.0,60.0');
    });

    it('ferme une surface par série continue, une série d’un point est ignorée', () => {
        const points = [
            [0, 10],
            [100, 20],
            [200, null],
            [300, 30],
        ];
        expect(areaPath(points, x, y, 100)).toBe('M0.0,100.0L0.0,90.0L10.0,80.0L10.0,100.0Z');
    });
});

describe('lecture au survol', () => {
    it('trouve le point le plus proche', () => {
        const points = series(1000, () => 0);
        expect(nearestIndex(points, 0)).toBe(0);
        expect(nearestIndex(points, 149)).toBe(1);
        expect(nearestIndex(points, 151)).toBe(2);
        expect(nearestIndex(points, 5000)).toBe(10);
        expect(nearestIndex([], 10)).toBe(-1);
    });

    it('calcule la déclivité locale sur 1 km, positive en montée, insensible au bruit alterné', () => {
        const climb = series(3000, (d) => 500 + 0.025 * d + (d % 200 === 0 ? 4 : -4));
        expect(gradeAt(climb, 1500)).toBeCloseTo(25, 0);
        const descent = series(3000, (d) => 900 - 0.09 * d);
        expect(gradeAt(descent, 1500)).toBeCloseTo(-90, 6);
    });

    it('ne donne pas de déclivité sans trois points connus', () => {
        const holes = series(2000, (d) => (d === 1000 ? null : d / 10));
        expect(gradeAt(holes, 1000)).toBeNull();
        expect(
            gradeAt(
                [
                    [0, 1],
                    [1000, 2],
                ],
                500,
            ),
        ).toBeNull();
    });
});

describe('noms des gares', () => {
    const stations = [
        { distance_m: 0, name: 'St-Gervais-les-Bains-Le Fayet' },
        { distance_m: 2700, name: 'Chedde' },
        { distance_m: 6800, name: 'Servoz' },
        { distance_m: 20000, name: 'Chamonix-Mont-Blanc' },
        { distance_m: 34100, name: 'Vallorcine' },
    ];
    const x = (d) => 60 + (d / 36700) * 1000;
    const frame = { left: 0, right: 1080, labelWidth: (n) => n.length * 7.6 };

    it('nomme les extrémités d’abord et évite les chevauchements', () => {
        // Chedde et Servoz tombent sous le long nom de la gare de départ : seulement leur point.
        const named = labelledStations(stations, x, frame);
        expect([...named.keys()].map((s) => s.name)).toEqual([
            'St-Gervais-les-Bains-Le Fayet',
            'Vallorcine',
            'Chamonix-Mont-Blanc',
        ]);
    });

    it('aligne les noms sur la gare près des bords', () => {
        const named = labelledStations(stations, x, frame);
        expect(named.get(stations[0])).toBe('start');
        expect(named.get(stations[3])).toBe('middle');
        expect(
            labelledStations([{ distance_m: 36700, name: 'Le Châtelard' }], x, frame).get(
                stations[0],
            ),
        ).toBeUndefined();
    });
});
