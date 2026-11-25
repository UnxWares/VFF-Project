// Lisibilité du schéma des voies (lib/trackLayout.js), passe du 25/11/2026 : gorges de gare dilatées (14 px au moins
// entre deux aiguillages), voies qui ne flottent plus (raccord déduit vers la voie mère), graduations des PK sans
// chevauchement, bande sans cases illisibles (portions courtes regroupées), une marque par portion comprimée, pièces
// d'une même voie sur une seule rangée, tableau qui dit la voie d'une vitesse et nomme l'aiguillage par son genre.
import { describe, expect, it } from 'vitest';
import {
    AXIS,
    bandCells,
    buildAxis,
    clusterMarks,
    compactLanes,
    connections,
    flattenRanges,
    looseEnds,
    meanPermille,
    normalize,
    objectPks,
    plainPieceAt,
    routeRows,
    rowTitle,
    tickLabels,
    trackPaths,
} from '../../resources/js/lib/trackLayout.js';

const track = (id, kind, lane, from, to, extra = {}) => ({
    id,
    ref: null,
    kind,
    lane,
    pk_from_m: from,
    pk_to_m: to,
    source: 'osm',
    precision: 'derived',
    ...extra,
});
const sw = (id, pk, from, to, extra = {}) => ({
    id,
    ref: null,
    pk_m: pk,
    kind: 'simple',
    from_track: from,
    to_track: to,
    source: 'osm',
    precision: 'derived',
    ...extra,
});

describe('trackLayout : gorge de gare', () => {
    // Une gare au PK 10 000 dont l'entrée aligne quatre aiguillages à 10 m l'un de l'autre.
    const data = normalize({
        pk: { start_m: 0, end_m: 20000 },
        operational_points: [{ id: 'op', name: 'Gare', kind: 'station', pk_m: 10000 }],
        tracks: [track('v1', 'running', 0, 0, 20000, { ref: '1' })],
        switches: [9700, 9710, 9720, 9730].map((pk, i) => sw(`s${i}`, pk, 'v1', null)),
    });

    it('garde au moins 14 px entre deux aiguillages voisins, le reste de la zone à son échelle', () => {
        const plain = buildAxis(data);
        const axis = buildAxis(data, AXIS, objectPks(data));
        const xs = [9700, 9710, 9720, 9730].map(axis.x);
        for (let i = 1; i < xs.length; i++)
            expect(xs[i] - xs[i - 1]).toBeGreaterThanOrEqual(14 - 1e-9);
        // Sans dilatation, 10 m font 2,5 px.
        expect(plain.x(9710) - plain.x(9700)).toBeCloseTo(2.5);
        // Ailleurs dans la zone, la même échelle ; la zone ne compte qu'un premier morceau.
        expect(axis.x(10100) - axis.x(10000)).toBeCloseTo(100 * AXIS.zoneScale);
        expect(axis.pieces.filter((p) => p.kind === 'zone' && p.first)).toHaveLength(1);
        // PK et abscisse restent réciproques.
        for (const pk of [9705, 9725, 10000, 15000]) expect(axis.pk(axis.x(pk))).toBeCloseTo(pk);
    });

    it('ne compte que les aiguillages et traversées de voies', () => {
        expect(
            objectPks({
                switches: [{ pk_m: 5 }, { pk_m: 1 }, { pk_m: 5 }],
                crossings: [
                    { pk_m: 3, kind: 'railway_crossing' },
                    { pk_m: 4, kind: 'level_crossing' },
                ],
            }),
        ).toEqual([1, 3, 5]);
    });
});

describe('trackLayout : voies qui ne flottent plus', () => {
    const base = {
        pk: { start_m: 0, end_m: 20000 },
        operational_points: [
            { id: 'a', name: 'A', kind: 'station', pk_m: 0 },
            { id: 'b', name: 'B', kind: 'station', pk_m: 10000 },
            { id: 'c', name: 'C', kind: 'station', pk_m: 20000 },
        ],
    };

    it('raccorde une voie de gare sans aiguillage connu à sa voie mère, à chaque bout', () => {
        const data = normalize({
            ...base,
            tracks: [
                track('v1', 'running', 0, 0, 20000, { ref: '1' }),
                track('v2', 'running', 1, 0, 20000, { ref: '2' }),
                track('quai', 'loop', 2, 9800, 10200, { ref: '4' }),
                track('haut', 'siding', -2, 9850, 10100),
            ],
        });
        const loose = looseEnds(data, connections(data).ends);
        expect(loose.map((l) => [l.track.id, l.end, l.mother.id])).toEqual([
            ['quai', 'start', 'v2'],
            ['quai', 'end', 'v2'],
            ['haut', 'start', 'v1'],
            ['haut', 'end', 'v1'],
        ]);
        // Le dessin y met un court raccord, marqué déduit.
        const derived = trackPaths(
            data,
            (pk) => pk / 10,
            (lane) => lane * 34,
        ).filter((p) => p.track.kind === 'derived');
        expect(derived).toHaveLength(4);
        expect(derived[0].points).toEqual([
            [980, 68],
            [962, 34],
        ]);
    });

    it('ne raccorde pas un bout fermé par un aiguillage, un heurtoir, la pièce suivante ou le bout de la ligne', () => {
        const data = normalize({
            ...base,
            tracks: [
                track('v1', 'running', 0, 0, 20000, { ref: '1' }),
                track('evit', 'loop', -1, 9800, 10200),
                track('a', 'siding', -2, 9900, 10000),
                track('a2', 'siding', -2, 10000, 10100),
                track('term', 'loop', -1, 0, 300),
            ],
            switches: [
                sw('s1', 9800, 'evit', 'v1'),
                sw('s2', 10200, 'evit', 'v1'),
                sw('s3', 9900, 'a', 'evit'),
            ],
            buffer_stops: [
                { id: 'h', pk_m: 10100, track: 'a2', source: 'osm' },
                { id: 'h2', pk_m: 300, track: 'term', source: 'osm' },
            ],
        });
        expect(looseEnds(data, connections(data).ends)).toEqual([]);
    });
});

describe('trackLayout : bande et graduations', () => {
    const x = (pk) => pk / 10;

    it('regroupe les portions trop courtes en une case marquée, moyenne pondérée', () => {
        const g = (from, to, permille) => ({ pk_from_m: from, pk_to_m: to, permille });
        const ranges = [
            g(0, 1000, 2),
            g(1000, 1100, 4),
            g(1100, 1200, -2),
            g(1200, 1500, 6),
            g(1500, 3000, 0),
        ];
        const cells = bandCells(ranges, x, { minPx: 40 });
        expect(cells.map((c) => [c.pk_from_m, c.pk_to_m, c.merged])).toEqual([
            [0, 1000, false],
            [1000, 1500, true],
            [1500, 3000, false],
        ]);
        expect(meanPermille(cells[1].items)).toBe(4);
        // Un trou dans la bande coupe un regroupement.
        expect(
            bandCells([g(0, 100, 1), g(500, 600, 1)], x, { minPx: 40 }).map((c) => c.merged),
        ).toEqual([false, false]);
    });

    it('garde la vitesse des voies principales là où deux sections se recouvrent', () => {
        const v = (from, to, kmh, tracks) => ({ pk_from_m: from, pk_to_m: to, kmh, tracks });
        const flat = flattenRanges([
            v(0, 1363, 60, ['V1S', 'V2S']),
            v(0, 860, 90, ['V1', 'V2']),
            v(860, 1300, 90, ['V1']),
        ]);
        expect(flat.map((r) => [r.pk_from_m, r.pk_to_m, r.kmh])).toEqual([
            [0, 860, 90],
            [860, 1300, 90],
            [1300, 1363, 60],
        ]);
    });

    it('écrit une graduation sur deux quand les textes se toucheraient, garde tous les traits', () => {
        const items = [0, 30, 60, 200].map((xx, i) => ({
            x: xx,
            tx: xx,
            half: 20,
            rank: i === 1 ? 3 : 0,
        }));
        expect(tickLabels(items, 24)).toEqual([true, false, false, true]);
    });

    it('ne laisse qu’une marque par portion comprimée, une par objet dans une zone (sauf collées)', () => {
        const pieces = [
            { from_m: 0, to_m: 1000, kind: 'zone' },
            { from_m: 1000, to_m: 9000, kind: 'plain' },
            { from_m: 9000, to_m: 10000, kind: 'zone' },
        ];
        const marks = [100, 400, 1500, 2500, 8000, 9500, 9510].map((pk) => ({ pk, x: pk / 10 }));
        const out = clusterMarks(marks, (m) => plainPieceAt(pieces, m.pk));
        expect(out.map((m) => [m.pk, m.count])).toEqual([
            [100, 1],
            [400, 1],
            [1500, 3],
            [9500, 2],
        ]);
        expect(out[2].x).toBeCloseTo((150 + 250 + 800) / 3);
    });
});

describe('trackLayout : rangées et tableau', () => {
    it('garde sur une seule rangée les pièces d’une même voie coupée à chaque section', () => {
        const lanes = compactLanes([
            track('ter-a', 'running', -9, 0, 860, { ref: 'V1TER' }),
            track('ter-b', 'running', -9, 860, 1300, { ref: 'V1TER' }),
            track('st', 'running', -3, 0, 860, { ref: 'ST' }),
            track('bis', 'running', -4, 1000, 2000, { ref: 'V1BIS' }),
        ]);
        expect(lanes.get('ter-a')).toBe(lanes.get('ter-b'));
        expect(lanes.get('ter-a')).toBe(-2);
    });

    it('dit la voie de chacune de deux vitesses au même PK et nomme l’aiguillage par son genre', () => {
        const data = normalize({
            pk: { start_m: -200, end_m: 2000 },
            operational_points: [{ id: 'a', name: 'A', kind: 'station', pk_m: 0 }],
            strip: {
                speeds: [
                    { pk_from_m: 0, pk_to_m: 800, kmh: 90, tracks: ['V1', 'V2'], source: 'rinf' },
                    { pk_from_m: 0, pk_to_m: 1300, kmh: 60, tracks: ['V1S'], source: 'rinf' },
                    { pk_from_m: 800, pk_to_m: 2000, kmh: 140, tracks: ['V1'], source: 'rinf' },
                ],
                gradients: [
                    {
                        pk_from_m: -200,
                        pk_to_m: -100,
                        permille: 1.6,
                        source: 'vff',
                        precision: 'derived',
                    },
                ],
            },
            switches: [sw('s', 300, null, null, { ref: '12' })],
        });
        const rows = routeRows(data);
        // Le tableau ne commence pas par la déclivité d'avant la gare d'origine.
        expect(rows[0].kind).toBe('op');
        expect(rows.some((r) => r.kind === 'gradient')).toBe(false);
        const speeds = rows.filter((r) => r.kind === 'speed');
        // La troisième vitesse recouvre encore celle de la voie V1S (jusqu'au PK 1 300) : elle dit aussi sa voie.
        expect(speeds.map((r) => r.tracks ?? null)).toEqual([['V1', 'V2'], ['V1S'], ['V1']]);
        const t = (k, o) =>
            k === 'tracks.switch_kind.simple' ? 'aiguillage simple' : `${k}:${o?.ref ?? ''}`;
        expect(
            rowTitle(
                rows.find((r) => r.kind === 'switch'),
                t,
                'fr',
            ),
        ).toBe('Aiguillage simple 12');
    });
});
