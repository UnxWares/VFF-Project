// Grandes gares et longues lignes du schéma des voies (lib/trackLayout.js) : faisceaux et voies de service repliés par
// point d'exploitation, rangées resserrées, portions de lecture d'une longue ligne recollées sans doublon, graduation
// du livret de parcours en kilomètres parcourus.
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    FOLD_MIN,
    ROW_BUDGET,
    axisLabel,
    buildAxis,
    chunkBounds,
    chunksFor,
    compactLanes,
    connections,
    foldLayout,
    gapSummary,
    joinRanges,
    laneRows,
    layoutUrl,
    mergeLayouts,
    normalize,
    objectCount,
    zoneKey,
} from '../../resources/js/lib/trackLayout.js';

const fixture = (name) =>
    normalize(
        JSON.parse(
            readFileSync(new URL(`./fixtures/trackLayout/${name}.json`, import.meta.url), 'utf8'),
        ),
    );

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

/** Une gare (PK 10 000) avec ses voies 1 et 2, deux voies à quai et un faisceau de six voies au-dessous. */
function yardStation() {
    const tracks = [
        track('v1', 'running', 0, 0, 20000, { ref: '1' }),
        track('v2', 'running', 1, 0, 20000, { ref: '2' }),
        track('q3', 'loop', -1, 9800, 10200, { ref: '3' }),
        track('q4', 'loop', 2, 9800, 10200, { ref: '4' }),
        // Rangées éparses du serveur : 7, 9, 11… au lieu de 3, 4, 5…
        ...[0, 1, 2, 3, 4, 5].map((i) => track(`y${i}`, 'yard', 7 + 2 * i, 9850, 10150)),
        track('x1', 'crossover', 2, 9840, 9860),
    ];
    return normalize({
        pk: { start_m: 0, end_m: 20000 },
        operational_points: [{ id: 'op-g', name: 'Grand-Bourg', kind: 'station', pk_m: 10000 }],
        tracks,
        switches: [
            { id: 'sw-a', pk_m: 9840, from_track: 'q4', to_track: 'x1', facing: 'up' },
            { id: 'sw-b', pk_m: 9860, from_track: 'y0', to_track: 'x1', facing: 'up' },
            { id: 'sw-c', pk_m: 9900, from_track: 'y0', to_track: 'y1', facing: 'up' },
            { id: 'sw-d', pk_m: 9800, from_track: 'v2', to_track: 'q4', facing: 'up' },
            { id: 'sw-e', pk_m: 10150, from_track: 'y5', to_track: 'v2', facing: 'down' },
        ],
        signals: [
            { id: 's-yard', pk_m: 9950, track: 'y2', direction: 'up' },
            { id: 's-main', pk_m: 9700, track: 'v1', direction: 'up' },
        ],
        buffer_stops: [{ id: 'b1', pk_m: 10150, track: 'y3' }],
    });
}

describe('trackLayout : voies repliées des grandes gares', () => {
    it('replie le faisceau d’une gare en un groupe, sous les voies à quai, et le déplie sur demande', () => {
        const data = yardStation();
        const axis = buildAxis(data);
        const { data: view, groups } = foldLayout(data, axis.zones);

        expect(groups).toHaveLength(1);
        const g = groups[0];
        expect(g).toMatchObject({
            key: 'op:op-g',
            side: 1,
            count: 6,
            service: true,
            expanded: false,
        });
        expect(g.op.name).toBe('Grand-Bourg');
        expect(zoneKey(axis.zones[0], new Map([['op-g', data.operational_points[0]]]))).toBe(
            'op:op-g',
        );
        // Les six voies du faisceau et la liaison qui y mène deviennent une rangée, juste sous la voie à quai 4.
        const ids = view.tracks.map((t) => t.id);
        for (const id of ['y0', 'y1', 'y2', 'y3', 'y4', 'y5', 'x1']) expect(ids).not.toContain(id);
        const band = view.tracks.find((t) => t.kind === 'group');
        expect(band).toMatchObject({
            id: g.id,
            lane: 3,
            count: 6,
            pk_from_m: 9850,
            pk_to_m: 10150,
        });
        expect(laneRows(view.tracks).lanes).toEqual([-1, 0, 1, 2, 3]);
        // Aiguillage dans le faisceau, son signal et son heurtoir : pas dessinés ; celui qui y mène depuis la voie 2
        // reste sur la voie 2 et pointe vers le groupe (court départ, pas une diagonale jusqu'à la bande).
        const sw = new Map(view.switches.map((s) => [s.id, s]));
        expect(sw.has('sw-c')).toBe(false);
        expect(sw.get('sw-e')).toMatchObject({ from_track: 'v2', to_track: g.id });
        expect(view.signals.map((s) => s.id)).toEqual(['s-main']);
        expect(view.buffer_stops).toEqual([]);
        const { stubs } = connections(view);
        expect(stubs.find((s) => s.sw.id === 'sw-e')).toMatchObject({ short: true });

        const open = foldLayout(data, axis.zones, new Set([g.key]));
        expect(open.groups[0].expanded).toBe(true);
        expect(open.data.tracks.some((t) => t.kind === 'group')).toBe(false);
        // Déplié : les voies du faisceau sur des rangées resserrées 3 à 8, dans l'ordre du serveur.
        const lanes = open.data.tracks
            .filter((t) => t.kind === 'yard')
            .sort((a, b) => a.id.localeCompare(b.id))
            .map((t) => t.lane);
        expect(lanes).toEqual([3, 4, 5, 6, 7, 8]);
    });

    it('laisse telles quelles moins de FOLD_MIN voies de service', () => {
        const data = normalize({
            pk: { start_m: 0, end_m: 5000 },
            operational_points: [{ id: 'h', name: 'Halte', kind: 'halt', pk_m: 2500 }],
            tracks: [
                track('u', 'running', 0, 0, 5000, { ref: 'U' }),
                ...Array.from({ length: FOLD_MIN - 1 }, (_, i) =>
                    track(`s${i}`, 'siding', -1 - i, 2400, 2600),
                ),
            ],
        });
        expect(foldLayout(data, buildAxis(data).zones).groups).toEqual([]);
    });

    it('replie aussi les voies de gare d’une très grande gare au-delà de ROW_BUDGET rangées', () => {
        const loops = Array.from({ length: ROW_BUDGET + 4 }, (_, i) =>
            track(`q${i}`, 'loop', -1 - i, 9800, 10200, { ref: String(3 + 2 * i) }),
        );
        const data = normalize({
            pk: { start_m: 0, end_m: 20000 },
            operational_points: [{ id: 'op-p', name: 'Paris-Est', kind: 'station', pk_m: 10000 }],
            tracks: [track('v1', 'running', 0, 0, 20000, { ref: '1' }), ...loops],
        });
        const { data: view, groups } = foldLayout(data, buildAxis(data).zones);
        expect(groups).toHaveLength(1);
        expect(groups[0]).toMatchObject({ side: -1, count: ROW_BUDGET + 4, service: false });
        expect(laneRows(view.tracks).count).toBe(2);
    });

    it('resserre les rangées en gardant l’ordre latéral des voies qui se chevauchent', () => {
        const lanes = compactLanes([
            track('a', 'loop', -3, 0, 100),
            track('b', 'loop', -9, 50, 150),
            track('c', 'loop', -20, 400, 500),
            track('d', 'yard', 12, 0, 100),
            track('e', 'yard', 40, 0, 100),
        ]);
        expect(Object.fromEntries(lanes)).toEqual({ a: -1, b: -2, c: -1, d: 2, e: 3 });
    });
});

describe('trackLayout : longues lignes lues par portions', () => {
    it('compte les objets d’un aperçu sans les points d’exploitation', () => {
        expect(objectCount({ operational_points: 500, tracks: 7000, switches: 4000 })).toBe(11000);
        expect(objectCount(null)).toBe(0);
    });

    it('coupe la ligne à peu près tous les 10 km, au milieu du plus grand intervalle entre deux points', () => {
        const bounds = chunkBounds(0, 32000, [500, 9000, 12500, 19000, 24000, 31000]);
        // Coupure de 10 km : entre 9 000 et 12 500 (10 750) ; de 20 km : entre 19 000 et 24 000 (21 500).
        expect(bounds).toEqual([
            [0, 10750],
            [10750, 21500],
            [21500, 32000],
        ]);
        expect(chunkBounds(0, 4000, [])).toEqual([[0, 4000]]);
        // Portions d'une vue, la plus proche de son milieu d'abord.
        expect(chunksFor(bounds, 20000, 23000)).toEqual([1, 2]);
        expect(chunksFor(bounds, 0, 32000)).toEqual([1, 0, 2]);
    });

    it('recolle les portions : un objet à cheval sur une coupure une seule fois, la bande sans couture', () => {
        const overview = {
            pk: { start_m: 0, end_m: 20000 },
            operational_points: [{ id: 'op', name: 'A', pk_m: 10000 }],
            counts: { tracks: 2 },
            coverage: { total_m: 20000, missing: [] },
        };
        const a = {
            tracks: [track('v1', 'running', 0, 0, 20000), track('q', 'loop', -1, 9000, 10100)],
            switches: [{ id: 'sw', pk_m: 10000 }],
            strip: {
                speeds: [{ pk_from_m: 0, pk_to_m: 10000, kmh: 160, source: 'rinf' }],
                gradients: [],
            },
        };
        const b = {
            tracks: [track('v1', 'running', 0, 0, 20000), track('q', 'loop', -1, 9000, 10100)],
            switches: [{ id: 'sw', pk_m: 10000 }],
            strip: {
                speeds: [
                    { pk_from_m: 10000, pk_to_m: 15000, kmh: 160, source: 'rinf' },
                    { pk_from_m: 15000, pk_to_m: 20000, kmh: 120, source: 'rinf' },
                ],
                gradients: [],
            },
        };
        const merged = mergeLayouts(overview, [a, b]);
        expect(merged.tracks.map((t) => t.id)).toEqual(['v1', 'q']);
        expect(merged.switches).toHaveLength(1);
        expect(merged.strip.speeds).toEqual([
            { pk_from_m: 0, pk_to_m: 15000, kmh: 160, source: 'rinf' },
            { pk_from_m: 15000, pk_to_m: 20000, kmh: 120, source: 'rinf' },
        ]);
        expect(merged.counts).toEqual({ tracks: 2 });
        expect(merged.operational_points).toHaveLength(1);
        expect(joinRanges([], () => true)).toEqual([]);
    });

    it('écrit l’adresse d’une portion', () => {
        expect(layoutUrl('rfn-830000')).toBe('/api/v1/lines/rfn-830000/track-layout');
        expect(
            layoutUrl('rfn-830000', {
                from_pk_m: 1000.4,
                to_pk_m: 2000.2,
                kinds: ['tracks', 'switches'],
            }),
        ).toBe(
            '/api/v1/lines/rfn-830000/track-layout?from_pk_m=1000&to_pk_m=2001&kinds=tracks%2Cswitches',
        );
    });
});

describe('trackLayout : bandeau des manques', () => {
    it('résume les manques par famille, « toute la longueur » quand la famille manque partout', () => {
        const gaps = gapSummary(
            [
                { pk_from_m: -210, pk_to_m: 0, what: 'tracks' },
                { pk_from_m: 5000, pk_to_m: 6000, what: 'tracks' },
                { pk_from_m: -210, pk_to_m: 10000, what: 'signals' },
                { pk_from_m: 0, pk_to_m: 100, what: 'bridges' },
            ],
            10210,
        );
        expect(gaps).toEqual([
            { what: 'tracks', metres: 1210, count: 2, all: false },
            { what: 'signals', metres: 10210, count: 1, all: true },
            { what: 'other', metres: 100, count: 1, all: false },
        ]);
    });
});

describe('trackLayout : livret de parcours', () => {
    it('gradue l’axe en kilomètres parcourus, pas en PK', () => {
        expect(axisLabel(17500)).toBe('17+500');
        expect(axisLabel(17500, 'route', 'fr')).toBe('17,5');
        expect(axisLabel(120000, 'route', 'en')).toBe('120');
    });
});

describe('trackLayout : jeu d’essai de la grande gare', () => {
    it('garde toutes les voies d’une gare de 20 voies qui tient dans ROW_BUDGET rangées de chaque côté', () => {
        const data = fixture('large-station');
        const { data: view, groups } = foldLayout(data, buildAxis(data).zones);
        // Deux voies de faisceau de chaque côté : moins de FOLD_MIN, rien n'est replié (comme Kongsvinger).
        expect(groups).toEqual([]);
        expect(
            view.tracks
                .filter((t) => t.kind !== 'crossover')
                .map((t) => t.id)
                .sort(),
        ).toEqual(
            data.tracks
                .filter((t) => t.kind !== 'crossover')
                .map((t) => t.id)
                .sort(),
        );
    });
});
