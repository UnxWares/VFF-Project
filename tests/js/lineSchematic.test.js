import { describe, expect, it } from 'vitest';
import {
    anchors,
    defaultEra,
    electrificationKey,
    gapPx,
    GAP_MAX,
    labelled,
    legendKeys,
    mergeRuns,
    pointsIn,
    rows,
    slices,
    speedBucket,
    tracksKey,
} from '../../resources/js/lib/lineSchematic.js';

const station = (id, pk, open = true) => ({ id, pk_m: pk, open });

describe('lineSchematic', () => {
    it('grows the gap with the distance, up to a ceiling', () => {
        expect(gapPx(0)).toBe(0);
        expect(gapPx(-5)).toBe(0);
        expect(gapPx(10000)).toBe(30);
        expect(gapPx(500000)).toBe(GAP_MAX);
    });

    it('sorts stations and junctions by PK, hides closed stations on demand, adds the ends', () => {
        const data = {
            stations: [station(1, 1000), station(2, 9000, false), station(3, 5000)],
            junctions: [{ pk_m: 5000, lines: [] }],
            extent: { pk_start_m: 0, pk_end_m: 9200 },
        };
        const all = anchors(data, { closed: true, junctions: true });
        expect(all.map((a) => a.key)).toEqual(['end-start', 's1', 's3', 'j5000-0', 's2']);
        const open = anchors(data, { closed: false, junctions: false });
        expect(open.map((a) => a.key)).toEqual(['end-start', 's1', 's3', 'end-end']);
    });

    it('gives each row the PK span up to the next anchor and flags the first real span', () => {
        const r = rows([
            { pk_m: 0, key: 'a' },
            { pk_m: 0, key: 'b' },
            { pk_m: 4000, key: 'c' },
        ]);
        expect(r.map((x) => [x.from, x.to, x.first, x.last])).toEqual([
            [0, 0, false, false],
            [0, 4000, true, false],
            [4000, 4000, false, true],
        ]);
    });

    it('cuts ranges into the row in percentages and knows where a range starts', () => {
        const ranges = [
            { pk_start_m: -500, pk_end_m: 2500 },
            { pk_start_m: 2500, pk_end_m: 9000 },
        ];
        expect(slices(ranges, 0, 10000)).toEqual([
            { a: '0%', b: '25%', starts: false, range: ranges[0] },
            { a: '25%', b: '65%', starts: true, range: ranges[1] },
        ]);
        expect(slices(ranges, 0, 10000, true)[0].starts).toBe(true);
        expect(slices(ranges, 5000, 5000)).toEqual([]);
        expect(pointsIn([{ pk_m: 2000 }, { pk_m: 10000 }], 0, 10000)).toEqual([
            { a: '20%', item: { pk_m: 2000 } },
        ]);
    });

    it('labels range starts without crowding the row or its bottom edge', () => {
        const list = [
            { a: '0%', starts: true },
            { a: '10%', starts: true },
            { a: '50%', starts: false },
            { a: '60%', starts: true },
            { a: '95%', starts: true },
        ];
        expect(labelled(list).map((s) => s.a)).toEqual(['0%', '60%']);
    });

    it('classifies tracks, power and speed for the legend', () => {
        expect(tracksKey({ tracks: 1 })).toBe('single');
        expect(tracksKey({ tracks: 2 })).toBe('double');
        expect(tracksKey({ tracks: 4 })).toBe('multi');
        expect(tracksKey({ tracks: null, banalised: true })).toBe('banalised');
        expect(tracksKey({ tracks: null, banalised: false })).toBe('other');
        expect(electrificationKey({ voltage_v: 25000, system: 'ac' })).toBe('ac25');
        expect(electrificationKey({ voltage_v: 1500, system: 'dc' })).toBe('dc1500');
        expect(electrificationKey({ voltage_v: 750, system: 'third_rail' })).toBe('third_rail');
        expect(electrificationKey({ voltage_v: 15000, system: 'ac' })).toBe('other');
        expect(electrificationKey({ voltage_v: null, system: 'unknown' })).toBe('unknown');
        expect([60, 61, 140, 200, 320].map(speedBucket)).toEqual([0, 1, 2, 3, 4]);
        expect(
            legendKeys('speed', [
                { max_speed_kmh: 300 },
                { max_speed_kmh: 40 },
                { max_speed_kmh: 50 },
            ]),
        ).toEqual(['s0', 's4']);
    });

    it('merges neighbouring ranges of the same value only', () => {
        const runs = mergeRuns(
            [
                { pk_start_m: 0, pk_end_m: 1000, v: 160 },
                { pk_start_m: 1020, pk_end_m: 2000, v: 160 },
                { pk_start_m: 2000, pk_end_m: 3000, v: 140 },
                { pk_start_m: 3500, pk_end_m: 4000, v: 140 },
            ],
            (r) => String(r.v),
        );
        expect(runs.map((r) => [r.pk_start_m, r.pk_end_m, r.v])).toEqual([
            [0, 2000, 160],
            [2000, 3000, 140],
            [3500, 4000, 140],
        ]);
    });

    it('opens on today, or on the heyday of a line closed everywhere', () => {
        expect(
            defaultEra([
                { year: 1930, is_current: false, open: 12 },
                { year: 2030, is_current: true, open: 2 },
            ]),
        ).toBe(2030);
        expect(
            defaultEra([
                { year: 1920, is_current: false, open: 12 },
                { year: 1930, is_current: false, open: 12 },
                { year: 1960, is_current: false, open: 3 },
                { year: 2030, is_current: true, open: 0 },
            ]),
        ).toBe(1930);
        expect(defaultEra([{ year: 2030, is_current: true, open: 0 }])).toBe(2030);
        expect(defaultEra([])).toBeNull();
    });
});
