// Schéma des voies (lib/trackLayout.js) sur les deux jeux d'essai du contrat : une ligne à voie unique avec deux
// évitements, une grande gare de 20 voies.
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    AXIS,
    buildAxis,
    centerScroll,
    connections,
    coverageShare,
    DIAG,
    detailZones,
    fitZoom,
    hasTracks,
    laneRows,
    missingText,
    nearEnd,
    nearestRow,
    normalize,
    pathD,
    provenance,
    ROW_H,
    routeRows,
    rowAtX,
    scrollForPk,
    stackLabels,
    trackPaths,
    valueAt,
} from '../../resources/js/lib/trackLayout.js';

const fixture = (name) =>
    normalize(
        JSON.parse(
            readFileSync(new URL(`./fixtures/trackLayout/${name}.json`, import.meta.url), 'utf8'),
        ),
    );
const single = fixture('single-track');
const station = fixture('large-station');

describe('trackLayout : données', () => {
    it('complète une réponse sans données de listes vides, sans erreur', () => {
        const d = normalize({ line: { slug: 'x' }, pk: { start_m: 0, end_m: 5000 } });
        expect(d.tracks).toEqual([]);
        expect(d.strip.speeds).toEqual([]);
        expect(d.coverage.missing).toEqual([]);
        expect(d.coverage.total_m).toBe(5000);
        expect(hasTracks(d)).toBe(false);
        expect(hasTracks(single)).toBe(true);
        expect(normalize(null).pk).toEqual({ start_m: 0, end_m: 0 });
    });

    it('donne la lettre de la provenance, approchée ou absente', () => {
        expect(provenance({ source: 'rinf', precision: 'exact' })).toEqual({
            code: 'R',
            key: 'rinf',
            approx: false,
        });
        expect(provenance({ source: 'osm', precision: 'approximate' })).toMatchObject({
            code: 'O',
            approx: true,
        });
        expect(provenance({ source: 'vff', precision: 'derived' }).code).toBe('D');
        expect(provenance({ source: null }).code).toBe('!');
        expect(provenance({ source: 'inconnue' }).code).toBe('!');
        expect(provenance(undefined).code).toBe('!');
    });

    it('lit la tranche qui couvre un PK, bornes comprises en fin de ligne', () => {
        const speeds = single.strip.speeds;
        expect(valueAt(speeds, 0).kmh).toBe(60);
        expect(valueAt(speeds, 900).kmh).toBe(100);
        expect(valueAt(speeds, 32400).kmh).toBe(40);
        expect(valueAt(speeds, 40000)).toBeNull();
        expect(coverageShare(single.coverage, 'rinf')).toBeCloseTo(30000 / 32400);
        expect(coverageShare({ total_m: 0 }, 'rinf')).toBe(0);
    });
});

describe('trackLayout : compression de la pleine ligne', () => {
    it('dilate les zones des points d exploitation et borne la pleine ligne', () => {
        const axis = buildAxis(single);
        const zones = axis.pieces.filter((p) => p.kind === 'zone');
        const plains = axis.pieces.filter((p) => p.kind === 'plain');
        // Valmont, Les Granges, Saint-Pré, Col de Brame, Rochebrune : cinq zones séparées par la pleine ligne.
        expect(zones).toHaveLength(5);
        expect(plains).toHaveLength(4);
        for (const z of zones) {
            expect(z.x1 - z.x0).toBeCloseTo((z.to_m - z.from_m) * AXIS.zoneScale);
        }
        for (const p of plains) {
            const w = p.x1 - p.x0;
            expect(w).toBeGreaterThanOrEqual(AXIS.plainMin);
            expect(w).toBeLessThanOrEqual(AXIS.plainMax);
            // Un mètre de pleine ligne pèse bien moins qu'un mètre de gare.
            expect(w / (p.to_m - p.from_m)).toBeLessThan(AXIS.zoneScale / 10);
        }
        // Les morceaux se suivent sans trou ni recouvrement, du début à la fin de la ligne.
        axis.pieces.forEach((p, i) => {
            if (i) expect(p.from_m).toBe(axis.pieces[i - 1].to_m);
            if (i) expect(p.x0).toBe(axis.pieces[i - 1].x1);
        });
        expect(axis.pieces[0].from_m).toBe(0);
        expect(axis.pieces.at(-1).to_m).toBe(32400);
    });

    it('rend 32 km de voie unique bien plus courts qu une échelle uniforme', () => {
        const axis = buildAxis(single);
        expect(axis.width).toBeLessThan(32400 * AXIS.zoneScale * 0.25);
    });

    it('convertit PK et abscisse dans les deux sens, bornés aux extrémités', () => {
        const axis = buildAxis(single);
        for (const pk of [0, 150, 900, 5000, 9600, 21400, 32400]) {
            expect(axis.pk(axis.x(pk))).toBeCloseTo(pk, 3);
        }
        expect(axis.x(-500)).toBe(0);
        expect(axis.x(99999)).toBe(axis.width);
        // Monotone : l'ordre des PK est l'ordre des abscisses.
        const xs = [0, 100, 1000, 9000, 9100, 20000, 32000].map(axis.x);
        expect([...xs].sort((a, b) => a - b)).toEqual(xs);
    });

    it('fusionne les zones proches : la grande gare et ses communications font une seule zone', () => {
        const zones = detailZones(station);
        const gc = zones.find((z) => z.ops.includes('op-gc'));
        expect(gc.from_m).toBeLessThanOrEqual(4700 - AXIS.trackPad);
        expect(gc.to_m).toBeGreaterThanOrEqual(7500);
    });
});

describe('trackLayout : rangées et tracés', () => {
    it('ouvre une rangée par voie, la plus petite en haut, sans rangée pour une liaison', () => {
        const rows = laneRows(station.tracks);
        expect(rows.count).toBe(20);
        expect(rows.lanes[0]).toBe(-9);
        expect(rows.row(0)).toBe(9);
        expect(rows.row(1)).toBe(10);
        // Voie unique : la voie d'évitement des Granges au-dessus (-1), celles des gares au-dessous.
        const r1 = laneRows(single.tracks);
        expect(r1.lanes).toEqual([-1, 0, 1, 2]);
        expect(r1.row(0.5)).toBe(1);
    });

    it('raccorde chaque voie d évitement à sa voie mère par ses deux aiguillages', () => {
        const { ends } = connections(single);
        expect(ends.get('tr-gr-2').start.sw.id).toBe('sw-gr-1');
        expect(ends.get('tr-gr-2').end.sw.id).toBe('sw-gr-2');
        expect(ends.get('tr-gr-2').start.other.id).toBe('tr-gr-1');
        // Voie de service en impasse : raccordée d'un seul côté.
        expect(ends.get('tr-val-3').start).toBeNull();
        expect(ends.get('tr-val-3').end.sw.id).toBe('sw-val-3');
        expect(nearEnd({ pk_from_m: 0, pk_to_m: 10000 }, 5000)).toBeNull();
    });

    it('dessine la voie d évitement par deux diagonales au droit des aiguillages', () => {
        const axis = buildAxis(single);
        const rows = laneRows(single.tracks);
        const y = (lane) => rows.row(lane) * ROW_H;
        const loop = trackPaths(single, axis.x, y).find((p) => p.track.id === 'tr-gr-2');
        const [p0, p1, p2, p3] = loop.points;
        expect(loop.points).toHaveLength(4);
        expect(p0).toEqual([axis.x(9100), y(0)]);
        expect(p1[1]).toBe(y(-1));
        expect(p1[0] - p0[0]).toBeCloseTo(DIAG);
        expect(p2[1]).toBe(y(-1));
        expect(p3).toEqual([axis.x(10100), y(0)]);
        expect(pathD(loop.points)).toMatch(/^M[\d.]+ 34 L[\d.]+ 0 L[\d.]+ 0 L[\d.]+ 34$/);
    });

    it('dessine une communication comme une diagonale entre les voies 1 et 2', () => {
        const axis = buildAxis(station);
        const rows = laneRows(station.tracks);
        const y = (lane) => rows.row(lane) * ROW_H;
        const paths = trackPaths(station, axis.x, y);
        const x1 = paths.find((p) => p.track.id === 'x-1');
        expect(x1.points).toEqual([
            [axis.x(4700), y(1)],
            [axis.x(4770), y(0)],
        ]);
        // La traversée-jonction double entre deux voies qui continuent : une diagonale à elle.
        expect(paths.some((p) => p.track.id === 'stub-sw-tjd')).toBe(true);
        // Le faisceau s'écarte en échelle : chaque voie part plus loin que la précédente.
        const starts = [-1, -2, -3].map(
            (lane) =>
                paths.find((p) => p.track.lane === lane && p.track.op_id === 'op-gc').points[0][0],
        );
        expect(starts[0]).toBeLessThan(starts[1]);
        expect(starts[1]).toBeLessThan(starts[2]);
    });

    it('n étiquette pas deux noms au même endroit', () => {
        const placed = stackLabels(
            [
                { x: 100, w: 80 },
                { x: 130, w: 80 },
                { x: 400, w: 80 },
            ],
            { maxRows: 2 },
        );
        expect(placed).toEqual([0, 1, 0]);
        const one = stackLabels(
            [
                { x: 100, w: 80, rank: 1 },
                { x: 130, w: 80, rank: 0 },
            ],
            { maxRows: 1 },
        );
        // La priorité la plus forte garde sa place, l'autre n'est pas écrite.
        expect(one).toEqual([-1, 0]);
    });
});

describe('trackLayout : tableau de parcours et synchronisation', () => {
    const rows = routeRows(single);

    it('trie les objets par PK, avec la vitesse et la déclivité du point', () => {
        const pks = rows.map((r) => r.pk_m);
        expect([...pks].sort((a, b) => a - b)).toEqual(pks);
        const granges = rows.find((r) => r.key === 'op-op-gr');
        expect(granges).toMatchObject({ kind: 'op', kmh: 80, permille: 0, km: 9.6 });
        expect(granges.prov.code).toBe('R');
        const pre = rows.find((r) => r.key === 'op-op-sp');
        expect(pre.prov).toMatchObject({ code: 'O', approx: true });
        // Même PK : le point d'exploitation avant la vitesse et la déclivité.
        const zero = rows.filter((r) => r.pk_m === 0).map((r) => r.kind);
        expect(zero).toEqual(['speed', 'gradient']);
        // Le manque de la couverture devient une ligne « ! ».
        const miss = rows.find((r) => r.kind === 'missing');
        expect(miss).toMatchObject({ pk_m: 14000, prov: { code: '!' } });
        expect(rows.filter((r) => r.kind === 'level_crossing')).toHaveLength(3);
        expect(rows.filter((r) => r.kind === 'buffer_stop')).toHaveLength(5);
    });

    it('dit chaque manque par sa phrase (accord du français), une famille inconnue par la phrase générale', () => {
        const t = (k, o) => `${k} ${o.from} ${o.to}`;
        expect(missingText({ what: 'tracks', pk_from_m: 17012, pk_to_m: 17095 }, t)).toBe(
            'tracks.row.missing.tracks 17+012 17+095',
        );
        expect(missingText({ what: 'platforms', pk_from_m: 0, pk_to_m: 1 }, t)).toBe(
            'tracks.row.missing.other 0+000 0+001',
        );
    });

    it('trouve la ligne la plus proche d un PK', () => {
        expect(nearestRow([], 10)).toBe(-1);
        const i = nearestRow(rows, 9590);
        expect(rows[i].key).toBe('op-op-gr');
        expect(nearestRow(rows, -100)).toBe(0);
        expect(nearestRow(rows, 1e9)).toBe(rows.length - 1);
        // À égalité de PK, la première ligne du PK.
        expect(rows[nearestRow(rows, 0)].kind).toBe('speed');
    });

    it('centre le schéma sur la ligne choisie du tableau, et l inverse', () => {
        const axis = buildAxis(single);
        const zoom = 2;
        const pad = 40;
        const view = 600;
        const content = axis.width * zoom + 2 * pad;
        const row = rows.find((r) => r.key === 'op-op-cb');
        const left = scrollForPk(axis, zoom, pad, row.pk_m, view, content);
        // Le PK de la ligne est au milieu de la fenêtre.
        expect(pad + axis.x(row.pk_m) * zoom - left).toBeCloseTo(view / 2, 0);
        // Un clic au milieu de la fenêtre ainsi centrée retrouve la même ligne du tableau.
        expect(rows[rowAtX(axis, zoom, pad, rows, left + view / 2)].key).toBe('op-op-cb');
        // Au bord, le défilement reste dans le contenu.
        expect(scrollForPk(axis, zoom, pad, 0, view, content)).toBe(0);
        expect(scrollForPk(axis, zoom, pad, 32400, view, content)).toBe(content - view);
        expect(centerScroll(50, 600, 400)).toBe(0);
    });

    it('fait tenir toute la ligne dans la fenêtre avec « Ajuster »', () => {
        const axis = buildAxis(station);
        const z = fitZoom(axis.width, 1000, 40);
        expect(axis.width * z + 80).toBeCloseTo(1000);
        expect(fitZoom(0, 1000, 40)).toBe(1);
        expect(fitZoom(1e9, 1000, 40)).toBe(0.02);
    });
});
