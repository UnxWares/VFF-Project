import { describe, expect, it } from 'vitest';
import {
    PORTION_COLORS,
    boundsOf,
    dedupe,
    distanceM,
    formatKm,
    lengthM,
    nearestOnLine,
    portionColor,
    projectOnSegment,
    round7,
    samePoint,
} from '$lib/traceGeometry.js';

const PARIS = [2.3522, 48.8566];
const LYON = [4.8357, 45.764];

// Projection « écran » triviale : 1 unité de coordonnée = 1 pixel.
const identity = ([x, y]) => ({ x, y });

describe('couleurs des portions', () => {
    it('cyclent sur la palette', () => {
        expect(portionColor(0)).toBe(PORTION_COLORS[0]);
        expect(portionColor(PORTION_COLORS.length)).toBe(PORTION_COLORS[0]);
        expect(portionColor(PORTION_COLORS.length + 3)).toBe(PORTION_COLORS[3]);
        expect(new Set(PORTION_COLORS).size).toBe(PORTION_COLORS.length);
    });
});

describe('distances', () => {
    it("haversine : Paris - Lyon à vol d'oiseau ≈ 392 km", () => {
        expect(distanceM(PARIS, LYON) / 1000).toBeCloseTo(391.5, 0);
    });

    it('un degré de latitude ≈ 111,2 km ; distance nulle et symétrique', () => {
        expect(distanceM([2, 48], [2, 49])).toBeCloseTo(111195, -2);
        expect(distanceM(PARIS, PARIS)).toBe(0);
        expect(distanceM(PARIS, LYON)).toBeCloseTo(distanceM(LYON, PARIS), 6);
    });

    it('antipodes : demi-circonférence, sans NaN (asin borné)', () => {
        const d = distanceM([0, 0], [180, 0]);
        expect(Number.isNaN(d)).toBe(false);
        expect(d).toBeCloseTo(Math.PI * 6371008.8, 0);
    });

    it("longueur d'une polyligne = somme de ses arêtes", () => {
        const mid = [3.5, 47.3];
        expect(lengthM([PARIS, mid, LYON])).toBeCloseTo(
            distanceM(PARIS, mid) + distanceM(mid, LYON),
            6,
        );
        expect(lengthM([PARIS])).toBe(0);
        expect(lengthM([])).toBe(0);
    });

    it.each([
        [0, '0 m'],
        [12.4, '12 m'],
        [999, '999 m'],
        [1000, '1.00 km'],
        [1500, '1.50 km'],
        [25_000, '25.0 km'],
        [392_456, '392.5 km'],
    ])('formatKm(%f) = %s', (m, label) => {
        expect(formatKm(m)).toBe(label);
    });
});

describe('projection sur un segment écran', () => {
    const a = { x: 0, y: 0 };
    const b = { x: 10, y: 0 };

    it('pied de la perpendiculaire', () => {
        expect(projectOnSegment({ x: 4, y: 3 }, a, b)).toEqual({ t: 0.4, x: 4, y: 0, d: 3 });
    });

    it('bornée aux extrémités', () => {
        expect(projectOnSegment({ x: -5, y: 0 }, a, b)).toMatchObject({ t: 0, x: 0, d: 5 });
        expect(projectOnSegment({ x: 13, y: 4 }, a, b)).toMatchObject({ t: 1, x: 10, d: 5 });
    });

    it('segment dégénéré (deux points confondus)', () => {
        expect(projectOnSegment({ x: 3, y: 4 }, a, a)).toEqual({ t: 0, x: 0, y: 0, d: 5 });
    });
});

describe('accrochage (nearestOnLine)', () => {
    const line = [
        [0, 0],
        [100, 0],
        [100, 100],
    ];

    it('accroche le sommet le plus proche dans le rayon', () => {
        expect(nearestOnLine(line, { x: 98, y: 3 }, identity, 10)).toMatchObject({
            type: 'vertex',
            index: 1,
            coord: [100, 0],
        });
    });

    it('accroche une arête, avec interpolation du point', () => {
        const hit = nearestOnLine(line, { x: 40, y: 4 }, identity, 10);
        expect(hit).toMatchObject({ type: 'edge', index: 0, t: 0.4, d: 4 });
        expect(hit.coord).toEqual([40, 0]);
    });

    it("un sommet un peu plus loin qu'une arête l'emporte quand même (gares, aiguilles)", () => {
        // Sommet à 6 px, arête à 2 px : 6 <= 2 + 10 × 0,5.
        const hit = nearestOnLine(line, { x: 94, y: 2 }, identity, 10);
        expect(hit.type).toBe('vertex');
        expect(hit.index).toBe(1);
    });

    it("arête nettement plus proche qu'un sommet : l'arête", () => {
        // Sommet (0,0) à 9 px, arête à 1 px : 9 > 1 + 10 × 0,5.
        const hit = nearestOnLine(line, { x: 9, y: 1 }, identity, 10);
        expect(hit.type).toBe('edge');
        expect(hit.coord).toEqual([9, 0]);
    });

    it('utilise unproject quand il est fourni', () => {
        const unproject = ({ x, y }) => [x / 2, y / 2];
        const hit = nearestOnLine(line, { x: 50, y: 2 }, identity, 10, unproject);
        expect(hit.coord).toEqual([25, 0]);
    });

    it('rien dans le rayon : null', () => {
        expect(nearestOnLine(line, { x: 50, y: 50 }, identity, 10)).toBeNull();
        expect(nearestOnLine([], { x: 0, y: 0 }, identity, 10)).toBeNull();
    });
});

describe('utilitaires de coordonnées', () => {
    it('boundsOf : emprise de plusieurs polylignes, null si vide', () => {
        expect(boundsOf([[PARIS, LYON], [[-1.55, 47.22]]])).toEqual([
            [-1.55, 45.764],
            [4.8357, 48.8566],
        ]);
        expect(boundsOf([])).toBeNull();
        expect(boundsOf([[]])).toBeNull();
    });

    it('dedupe : retire seulement les sommets CONSÉCUTIFS identiques', () => {
        expect(
            dedupe([
                [1, 1],
                [1, 1],
                [2, 2],
                [1, 1],
            ]),
        ).toEqual([
            [1, 1],
            [2, 2],
            [1, 1],
        ]);
        expect(dedupe([])).toEqual([]);
    });

    it('samePoint : tolérance de 1e-9 degré', () => {
        expect(samePoint([1, 2], [1 + 1e-10, 2])).toBe(true);
        expect(samePoint([1, 2], [1 + 1e-6, 2])).toBe(false);
        expect(samePoint(null, [1, 2])).toBeFalsy();
    });

    it('round7 : 7 décimales (≈ 1 cm)', () => {
        expect(round7([2.123456789, 48.987654321])).toEqual([2.1234568, 48.9876543]);
    });
});
