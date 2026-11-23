import { describe, expect, it } from 'vitest';
import {
    eventKey,
    pkExtent,
    pkLabel,
    pkTicks,
    rangeAt,
    speedGaps,
    speedTicks,
    stepPath,
} from '$lib/lineSpeeds.js';

describe('lineSpeeds', () => {
    it('écrit les PK en km+mmm, négatifs compris', () => {
        expect(pkLabel(12345)).toBe('12+345');
        expect(pkLabel(-168)).toBe('-0+168');
        expect(pkLabel(null)).toBe('-');
    });

    it('borne les PK des tranches', () => {
        expect(pkExtent([])).toEqual([0, 1]);
        expect(
            pkExtent([
                { pk_start_m: 1000, pk_end_m: 5000 },
                { pk_start_m: 400, pk_end_m: 900 },
            ]),
        ).toEqual([400, 5000]);
    });

    it('gradue les vitesses de 0 à un pas rond au-dessus de la plus haute', () => {
        const t = speedTicks(320);
        expect(t[0]).toBe(0);
        expect(t.at(-1)).toBeGreaterThanOrEqual(320);
        expect(speedTicks(30).at(-1)).toBeGreaterThanOrEqual(40);
    });

    it('gradue les PK en kilomètres ronds, sans « -0 »', () => {
        expect(Object.is(pkTicks(-168, 711000, 3)[0], 0)).toBe(true);
        const t = pkTicks(34650, 61190, 4);
        expect(t.every((k) => k * 1000 >= 34650 && k * 1000 <= 61190)).toBe(true);
        expect(t.length).toBeGreaterThanOrEqual(2);
    });

    it('repère les trous entre les tranches', () => {
        const ranges = [
            { pk_start_m: 0, pk_end_m: 1000 },
            { pk_start_m: 1050, pk_end_m: 2000 },
            { pk_start_m: 5000, pk_end_m: 6000 },
        ];
        expect(speedGaps(ranges)).toEqual([{ from_m: 2000, to_m: 5000 }]);
    });

    it('choisit le libellé selon la nature et la vitesse d’avant', () => {
        expect(eventKey({ kind: 'raise', previous_speed_kmh: null })).toBe('speeds.kind.raise');
        expect(eventKey({ kind: 'lower', previous_speed_kmh: 80 })).toBe('speeds.kind.lower_from');
        expect(eventKey({ kind: 'state', previous_speed_kmh: 80 })).toBe('speeds.kind.state');
        expect(eventKey({ kind: 'bizarre', previous_speed_kmh: null })).toBe('speeds.kind.state');
    });
});

describe('lineSpeeds : tracé et lecture', () => {
    const ranges = [
        { pk_start_m: 0, pk_end_m: 1000, max_speed_kmh: 100 },
        { pk_start_m: 1000, pk_end_m: 2000, max_speed_kmh: 140 },
        { pk_start_m: 5000, pk_end_m: 6000, max_speed_kmh: 60 },
    ];
    const x = (pk) => pk / 100;
    const y = (v) => 200 - v;

    it('relie les paliers voisins et interrompt le tracé aux trous', () => {
        expect(stepPath(ranges, x, y)).toBe(
            'M0.0,100.0L10.0,100.0L10.0,60.0L20.0,60.0M50.0,140.0L60.0,140.0',
        );
        expect(stepPath([], x, y)).toBe('');
    });

    it('trouve la tranche sous un PK, ou la plus proche', () => {
        expect(rangeAt(ranges, 1500)).toBe(1);
        expect(rangeAt(ranges, 4500)).toBe(2);
        expect(rangeAt(ranges, -50)).toBe(0);
        expect(rangeAt([], 10)).toBe(-1);
    });
});
