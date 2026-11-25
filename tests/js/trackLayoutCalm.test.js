// Schéma des voies plus calme dans les grandes gares (lib/trackLayout.js, passe du 25/11/2026) : un seul départ en
// pointillé vers un groupe replié à chaque bout du groupe, une gare repliée qui reprend la largeur de ses voies
// visibles, des gorges dilatées à 14 px à l'écran quel que soit le zoom (50 %, 100 %, 200 %) et « Ajuster » qui en
// tient compte, le numéro d'une voie en plusieurs pièces écrit une fois.
import { readFileSync } from 'node:fs';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import {
    AXIS,
    BAND_H,
    ROW_H,
    axisFromZones,
    buildAxis,
    connections,
    detailZones,
    fitZoom,
    fitZoomFor,
    foldLayout,
    normalize,
    objectPks,
    repeatedPieces,
    routeRows,
    trackPaths,
} from '$lib/trackLayout.js';
import TrackDiagram from '$components/Lines/TrackDiagram.svelte';

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
    facing: 'up',
    source: 'osm',
    precision: 'derived',
    ...extra,
});

/**
 * Une gare (PK 10 000) : voies 1 et 2, une voie à quai 4 sous la voie 2, et un faisceau de six voies au-dessous qui
 * s'étire jusqu'au PK 13 000 (dépôt) ; cinq aiguillages y mènent depuis la voie 2 et la voie à quai.
 */
function depotStation() {
    return normalize({
        pk: { start_m: 0, end_m: 20000 },
        operational_points: [{ id: 'g', name: 'Grand-Bourg', kind: 'station', pk_m: 10000 }],
        tracks: [
            track('v1', 'running', 0, 0, 20000, { ref: '1' }),
            track('v2', 'running', 1, 0, 20000, { ref: '2' }),
            track('q4', 'loop', 2, 9800, 10200, { ref: '4', op_id: 'g' }),
            ...[0, 1, 2, 3, 4, 5].map((i) =>
                track(`y${i}`, 'yard', 3 + i, 9850 + 10 * i, 13000, { op_id: 'g' }),
            ),
        ],
        switches: [
            sw('a', 9850, 'q4', 'y0'),
            sw('b', 9860, 'q4', 'y1'),
            sw('c', 9870, 'v2', 'y2'),
            sw('d', 9880, 'q4', 'y3'),
            sw('e', 12990, 'v2', 'y5', { facing: 'down' }),
            sw('f', 12995, 'v2', 'y4', { facing: 'down' }),
            sw('in', 9800, 'v2', 'q4'),
        ],
    });
}

describe('trackLayout : un départ par bout de groupe replié', () => {
    it('ne garde qu’un aiguillage vers le groupe à chaque bout, celui de la voie la plus proche de la bande', () => {
        const data = depotStation();
        const { data: view, groups } = foldLayout(data, buildAxis(data).zones);
        expect(groups).toHaveLength(1);
        const g = groups[0];
        const toGroup = view.switches.filter((s) => s.to_track === g.id).map((s) => s.id);
        // Entrée : la voie à quai 4 est la plus proche de la bande, son aiguillage le plus près du bout du groupe ;
        // sortie : la voie 2 est la seule, l'aiguillage le plus près du bout du dépôt.
        expect(toGroup).toEqual(['a', 'f']);
        // L'aiguillage d'entrée de la voie à quai reste dessiné, les autres départs non (le tableau les garde).
        expect(view.switches.map((s) => s.id)).toEqual(['in', 'a', 'f']);
        expect(
            routeRows(data)
                .filter((r) => r.kind === 'switch')
                .map((r) => r.item.id),
        ).toEqual(['in', 'a', 'b', 'c', 'd', 'e', 'f']);
        // Deux courts départs, pas un par aiguillage.
        expect(connections(view).stubs.filter((s) => s.short)).toHaveLength(2);
    });

    it('mène le départ jusqu’au bord de la bande quand elle est sur la rangée voisine', () => {
        const data = depotStation();
        const { data: view, groups } = foldLayout(data, buildAxis(data).zones);
        const y = (lane) => lane * ROW_H;
        const stub = trackPaths(view, (pk) => pk / 10, y).find((p) => p.track.id === 'stub-a');
        // Voie à quai 4 sur la rangée 2, bande du groupe sur la rangée 3 : le départ s'arrête à son bord.
        expect(groups[0].lane).toBe(3);
        expect(stub.points[0]).toEqual([985, y(2)]);
        expect(stub.points[1][1]).toBe(y(3) - BAND_H / 2);
    });
});

describe('trackLayout : largeur d’une gare repliée', () => {
    it('ne dilate l’axe que sur les voies dessinées, pas sur la bande du faisceau replié', () => {
        const data = depotStation();
        const all = detailZones(data);
        const { data: view } = foldLayout(data, buildAxis(data).zones);
        const drawn = detailZones(view);
        // Toutes les voies : la zone s'étire avec le dépôt jusqu'au PK 13 000 et au-delà de sa marge.
        expect(all.find((z) => z.ops.includes('g')).to_m).toBeGreaterThanOrEqual(13000);
        // Repliée : la gare garde l'étendue de sa voie à quai et de ses aiguillages dessinés (entrée et sortie du dépôt,
        // zones distinctes), la bande traverse la pleine ligne comprimée entre les deux.
        const station = drawn.find((z) => z.ops.includes('g'));
        expect(station.to_m).toBeLessThan(10500);
        expect(drawn.some((z) => z.from_m > 12000 && z.to_m < 13200)).toBe(true);
        const width = (zones) => axisFromZones(zones, data.pk, AXIS, objectPks(view), 1).width;
        expect(width(drawn)).toBeLessThan(width(all) - 500);
    });
});

/** Une gorge de gare : quatre aiguillages à 10 m l'un de l'autre au PK 9 700, dans la zone d'une gare au PK 10 000. */
const throat = normalize({
    pk: { start_m: 0, end_m: 20000 },
    operational_points: [{ id: 'op', name: 'Gare', kind: 'station', pk_m: 10000 }],
    tracks: [track('v1', 'running', 0, 0, 20000, { ref: '1' })],
    switches: [9700, 9710, 9720, 9730].map((pk, i) =>
        sw(`s${i}`, pk, 'v1', null, { ref: `${120 + i}a` }),
    ),
});

describe('trackLayout : gorges dilatées quel que soit le zoom', () => {
    const zones = detailZones(throat);
    const dense = objectPks(throat);

    for (const zoom of [0.5, 1, 2]) {
        it(`garde 14 px à l’écran entre deux aiguillages voisins à ${zoom * 100} %`, () => {
            const axis = axisFromZones(zones, throat.pk, AXIS, dense, zoom);
            const xs = dense.map((pk) => axis.x(pk) * zoom);
            for (let i = 1; i < xs.length; i++) {
                expect(xs[i] - xs[i - 1]).toBeGreaterThanOrEqual(AXIS.minObjectPx - 1e-9);
            }
            // Le reste de la zone garde son échelle, PK et abscisse restent réciproques.
            expect(axis.x(10100) - axis.x(10000)).toBeCloseTo(100 * AXIS.zoneScale);
            for (const pk of [9705, 9725, 10000, 15000])
                expect(axis.pk(axis.x(pk))).toBeCloseTo(pk);
        });
    }

    it('réduit l’écart avec le zoom sous stretchZoomMin (vue d’ensemble)', () => {
        const zoom = AXIS.stretchZoomMin / 2;
        const axis = axisFromZones(zones, throat.pk, AXIS, dense, zoom);
        expect((axis.x(9710) - axis.x(9700)) * zoom).toBeCloseTo(AXIS.minObjectPx / 2);
    });

    it('dessine les aiguillages à 14 px au moins et leurs repères sans chevauchement, à 50, 100 et 200 %', () => {
        for (const zoom of [0.5, 1, 2]) {
            const axis = axisFromZones(zones, throat.pk, AXIS, dense, zoom);
            const { body } = render(TrackDiagram, {
                props: {
                    data: throat,
                    axis,
                    rows: routeRows(throat),
                    zoom,
                    printing: true,
                    label: 'Schéma',
                    helpId: 'aide',
                    lang: 'fr',
                    t: (k) => k,
                    onpick: () => {},
                },
            });
            const cx = [...body.matchAll(/<circle class="switch[^"]*" cx="([\d.]+)"/g)].map(
                (m) => +m[1],
            );
            expect(cx).toHaveLength(4);
            for (let i = 1; i < cx.length; i++) {
                expect(cx[i] - cx[i - 1]).toBeGreaterThanOrEqual(AXIS.minObjectPx - 0.01);
            }
            // Repères écrits : leurs boîtes (largeur estimée à 14 px) ne se touchent pas.
            const refs = [
                ...body.matchAll(/<text class="sw-ref[^"]*" x="([\d.]+)"[^>]*>([^<]+)</g),
            ].map((m) => ({ x: +m[1], w: m[2].length * 14 * 0.56 }));
            expect(refs.length).toBeGreaterThan(0);
            for (let i = 1; i < refs.length; i++) {
                expect(refs[i].x - refs[i].w / 2).toBeGreaterThan(
                    refs[i - 1].x + refs[i - 1].w / 2,
                );
            }
        }
    });
});

describe('trackLayout : « Ajuster » avec un axe qui dépend du zoom', () => {
    it('trouve le plus grand zoom auquel la ligne tient, gorges comprises', () => {
        const zones = detailZones(throat);
        const dense = objectPks(throat);
        const widthAt = (z) => axisFromZones(zones, throat.pk, AXIS, dense, z).width;
        const room = 300 - 2 * 40;
        const z = fitZoomFor(widthAt, 300, 40, 0.02, 4);
        expect(z).toBeLessThan(1);
        expect(z * widthAt(z)).toBeLessThanOrEqual(room + 1e-6);
        expect(z * widthAt(z)).toBeGreaterThan(room - 1);
        // Une largeur prise au zoom 100 % ferait déborder la ligne au zoom trouvé : ses gorges y sont plus larges.
        const naive = fitZoom(widthAt(1), 300, 40, 0.02, 4);
        expect(naive * widthAt(naive)).toBeGreaterThan(room);
        // Bornes et ligne vide.
        expect(fitZoomFor(() => 1e9, 600, 40, 0.02, 4)).toBe(0.02);
        expect(fitZoomFor(() => 10, 600, 40, 0.02, 4)).toBe(4);
        expect(fitZoomFor(() => 0, 600, 40)).toBe(1);
    });
});

// Réponse de l'API du 25/11/2026 (rfn-570000, PK -0+103 à 2+500 ; RINF et OpenStreetMap), réduite aux champs du dessin.
describe('trackLayout : jeu d’essai réel de Paris-Austerlitz', () => {
    const raw = JSON.parse(
        readFileSync(
            new URL('./fixtures/trackLayout/paris-austerlitz.json', import.meta.url),
            'utf8',
        ),
    );
    const data = normalize(raw);
    const { data: view, groups } = foldLayout(data, buildAxis(data).zones);
    const closed = groups.filter((g) => !g.expanded);

    it('ne garde qu’un départ en pointillé par bout de chaque groupe replié', () => {
        expect(closed.length).toBeGreaterThanOrEqual(2);
        const groupIds = new Set(closed.map((g) => g.id));
        // Aiguillages d'une voie dessinée vers une voie repliée : un départ chacun jusqu'ici.
        const drawn = new Set(view.tracks.map((t) => t.id));
        const folded = new Set(data.tracks.filter((t) => !drawn.has(t.id)).map((t) => t.id));
        const leading = data.switches.filter(
            (s) =>
                (drawn.has(s.from_track) && folded.has(s.to_track)) ||
                (drawn.has(s.to_track) && folded.has(s.from_track)),
        );
        expect(leading.length).toBeGreaterThan(10);
        const stubs = connections(view).stubs.filter((s) => s.short);
        const perGroup = new Map();
        for (const s of stubs) perGroup.set(s.to.id, (perGroup.get(s.to.id) ?? 0) + 1);
        expect([...perGroup.keys()].every((id) => groupIds.has(id))).toBe(true);
        for (const n of perGroup.values()) expect(n).toBeLessThanOrEqual(2);
        expect(stubs.length).toBeLessThanOrEqual(2 * closed.length);
    });

    it('garde la largeur des seules voies dessinées et 14 px entre deux aiguillages dessinés, à 50, 100 et 200 %', () => {
        const zones = detailZones(view);
        const dense = objectPks(view);
        for (const zoom of [0.5, 1, 2]) {
            const axis = axisFromZones(zones, data.pk, AXIS, dense, zoom);
            for (let i = 1; i < dense.length; i++) {
                const inZone = zones.some((z) => dense[i - 1] > z.from_m && dense[i] < z.to_m);
                if (inZone) {
                    expect((axis.x(dense[i]) - axis.x(dense[i - 1])) * zoom).toBeGreaterThanOrEqual(
                        AXIS.minObjectPx - 1e-9,
                    );
                }
            }
        }
    });
});

describe('trackLayout : numéro d’une voie en plusieurs pièces', () => {
    it('ne l’écrit qu’une fois : sur la plus longue pièce d’une voie de gare, au seul début d’une voie de ligne', () => {
        const { hidden, continued } = repeatedPieces([
            track('f1', 'loop', -1, 1750, 1800, { ref: 'F' }),
            track('f2', 'loop', -1, 1800, 1950, { ref: 'F' }),
            track('f3', 'loop', -1, 1955, 2000, { ref: 'F' }),
            // Même numéro plus loin (autre gare) : sa propre voie.
            track('f4', 'loop', -1, 5000, 5100, { ref: 'F' }),
            // Même numéro sur une autre rangée.
            track('g1', 'loop', 2, 1750, 1800, { ref: 'F' }),
            // Voie 3 de Paris-Austerlitz : deux pièces d'OpenStreetMap à 10 m l'une de l'autre.
            track('t3a', 'running', -1, 201, 666, { ref: '3' }),
            track('t3b', 'running', -1, 676, 714, { ref: '3' }),
            track('v1a', 'running', 0, 0, 1000, { ref: 'V1' }),
            track('v1b', 'running', 0, 1000, 2000, { ref: 'V1' }),
            track('v1c', 'running', 0, 3000, 4000, { ref: 'V1' }),
        ]);
        expect([...hidden].sort()).toEqual(['f1', 'f3']);
        expect([...continued].sort()).toEqual(['t3b', 'v1b']);
    });
});
