// Schéma des voies d'une ligne (GET /api/v1/lines/{line}/track-layout, contrat
// agents-commun/contrat-schema-voies.md) : calculs purs du composant Components/Lines/TrackLayout.svelte.
//
// - axe des PK : les zones des points d'exploitation (gares, évitements, bifurcations) sont dilatées, la
//   pleine ligne entre elles est comprimée (largeur bornée), pour lire une grande gare et une ligne de
//   500 km dans le même schéma ;
// - rangées : une par valeur de `lane` (calculée par le serveur), la plus petite en haut (haut = gauche du
//   sens des PK croissants) ;
// - tracés des voies : horizontales dans leur rangée, raccordées à leur voie mère par une diagonale au droit
//   de l'aiguillage ;
// - tableau de parcours : un objet par ligne, trié par PK, avec la vitesse et la déclivité du point ;
// - provenance de chaque objet (RINF, OpenStreetMap, déduit par VFF, approché, absent).

import { pkLabel } from '$lib/lineSpeeds.js';
import { formatNumber } from '$lib/typo.js';

export { pkLabel };

/** Réglages de l'axe : pixels par mètre dans les zones et en pleine ligne, bornes de la pleine ligne. */
export const AXIS = {
    zoneScale: 0.25,
    plainScale: 0.012,
    plainMin: 56,
    plainMax: 200,
    // Demi-largeur de la zone d'un point d'exploitation sans voie détaillée (m).
    opHalf: 250,
    // Marge autour des voies d'un point d'exploitation (m).
    trackPad: 120,
    // Deux zones plus proches que cet écart (m) n'en font qu'une.
    mergeGap: 400,
};

/** Hauteur d'une rangée de voies, longueur horizontale d'une diagonale par rangée franchie (px). */
export const ROW_H = 34;
export const DIAG = 18;

/** Voies de service : dessinées en pointillé. */
export const SERVICE_KINDS = new Set(['siding', 'yard', 'spur']);

/** Traversées : une croix plutôt qu'un cercle. */
export const CROSSING_SWITCHES = new Set(['diamond', 'single_slip', 'double_slip']);

/** Ordre des objets d'un même PK dans le tableau. */
export const ROW_KINDS = [
    'op',
    'milestone',
    'speed',
    'gradient',
    'signal',
    'switch',
    'crossing',
    'level_crossing',
    'buffer_stop',
    'missing',
];

/** Lettre de la provenance (badge du tableau) par source. */
const SOURCE_CODE = { rinf: 'R', osm: 'O', vff: 'D' };

const list = (v) => (Array.isArray(v) ? v : []);
const byPk = (a, b) => a.pk_m - b.pk_m;

/**
 * Réponse de l'API complétée de listes vides et triée par PK : le composant lit toujours les mêmes clés,
 * quelle que soit la couverture de la ligne (réponse sans données : listes vides, jamais une erreur).
 *
 * @param {any} raw
 */
export function normalize(raw) {
    const d = raw ?? {};
    const tracks = list(d.tracks)
        .map((t) => ({ ...t, lane: Number.isFinite(t.lane) ? t.lane : 0 }))
        .sort((a, b) => a.pk_from_m - b.pk_from_m || a.lane - b.lane);
    const ops = list(d.operational_points).slice().sort(byPk);
    const extentFrom = [
        d.pk?.start_m,
        ops[0]?.pk_m,
        tracks.length ? Math.min(...tracks.map((t) => t.pk_from_m)) : undefined,
    ].filter(Number.isFinite);
    const extentTo = [
        d.pk?.end_m,
        ops.at(-1)?.pk_m,
        tracks.length ? Math.max(...tracks.map((t) => t.pk_to_m)) : undefined,
    ].filter(Number.isFinite);
    const start = Number.isFinite(d.pk?.start_m)
        ? d.pk.start_m
        : extentFrom.length
          ? Math.min(...extentFrom)
          : 0;
    const end = Number.isFinite(d.pk?.end_m)
        ? d.pk.end_m
        : extentTo.length
          ? Math.max(...extentTo)
          : start;
    return {
        line: d.line ?? null,
        pk: { start_m: start, end_m: Math.max(start, end) },
        sources: list(d.sources),
        operational_points: ops,
        tracks,
        switches: list(d.switches).slice().sort(byPk),
        signals: list(d.signals).slice().sort(byPk),
        crossings: list(d.crossings).slice().sort(byPk),
        buffer_stops: list(d.buffer_stops).slice().sort(byPk),
        milestones: list(d.milestones).slice().sort(byPk),
        strip: {
            speeds: list(d.strip?.speeds)
                .slice()
                .sort((a, b) => a.pk_from_m - b.pk_from_m),
            gradients: list(d.strip?.gradients)
                .slice()
                .sort((a, b) => a.pk_from_m - b.pk_from_m),
        },
        coverage: {
            total_m: d.coverage?.total_m ?? Math.max(0, end - start),
            rinf_m: d.coverage?.rinf_m ?? 0,
            osm_m: d.coverage?.osm_m ?? 0,
            vff_m: d.coverage?.vff_m ?? 0,
            missing: list(d.coverage?.missing),
        },
    };
}

/** La ligne a-t-elle un schéma des voies (au moins une voie) ? */
export function hasTracks(data) {
    return list(data?.tracks).length > 0;
}

/**
 * Provenance d'un objet : lettre du badge (R, O, D, ou « ! » quand la donnée manque) et position approchée.
 *
 * @param {{ source?: string|null, precision?: string|null }|null|undefined} item
 * @returns {{ code: string, key: string, approx: boolean }}
 */
export function provenance(item) {
    const source = item?.source ?? null;
    if (source === null || item?.precision === 'missing' || !(source in SOURCE_CODE)) {
        return { code: '!', key: 'missing', approx: false };
    }
    return { code: SOURCE_CODE[source], key: source, approx: item?.precision === 'approximate' };
}

/** Tranche (vitesse, déclivité) qui couvre un PK, ou null. */
export function valueAt(ranges, pk) {
    for (const r of ranges) {
        if (pk >= r.pk_from_m && pk < r.pk_to_m) return r;
    }
    const last = ranges.at(-1);
    return last && pk === last.pk_to_m ? last : null;
}

/** Fusionne des intervalles [from, to] distants de moins de `gap`. */
function mergeIntervals(intervals, gap) {
    const sorted = intervals.slice().sort((a, b) => a.from_m - b.from_m);
    const out = [];
    for (const iv of sorted) {
        const prev = out.at(-1);
        if (prev && iv.from_m - prev.to_m < gap) {
            prev.to_m = Math.max(prev.to_m, iv.to_m);
            prev.ops.push(...iv.ops);
        } else {
            out.push({ from_m: iv.from_m, to_m: iv.to_m, ops: [...iv.ops] });
        }
    }
    return out;
}

/** Voie qui ne court pas sur toute la ligne : voie d'évitement, de service, de gare. */
const isLocal = (t) => t.kind !== 'running';

/**
 * Étendue (PK) de chaque point d'exploitation : ses voies propres (`op_id`, voies principales de la gare
 * comprises) avec une marge, sinon une demi-largeur fixe autour de son PK. C'est la zone grisée dessinée
 * sous son nom.
 *
 * @returns {Map<string, { from_m: number, to_m: number }>}
 */
export function opExtents(data, opts = AXIS) {
    const out = new Map();
    for (const op of data.operational_points) {
        out.set(op.id, { from_m: op.pk_m - opts.opHalf, to_m: op.pk_m + opts.opHalf });
    }
    for (const t of data.tracks) {
        if (t.op_id == null || !out.has(t.op_id)) continue;
        const e = out.get(t.op_id);
        e.from_m = Math.min(e.from_m, t.pk_from_m - opts.trackPad);
        e.to_m = Math.max(e.to_m, t.pk_to_m + opts.trackPad);
    }
    return out;
}

/**
 * Zones dilatées de l'axe : étendues des points d'exploitation, voies locales sans point d'exploitation
 * et aiguillages isolés, fusionnées quand elles se touchent, bornées à l'étendue de la ligne.
 *
 * @returns {Array<{ from_m: number, to_m: number, ops: string[] }>}
 */
export function detailZones(data, opts = AXIS) {
    const extents = opExtents(data, opts);
    const intervals = [...extents].map(([id, e]) => ({ ...e, ops: [id] }));
    for (const t of data.tracks) {
        if (isLocal(t) && (t.op_id == null || !extents.has(t.op_id))) {
            intervals.push({
                from_m: t.pk_from_m - opts.trackPad,
                to_m: t.pk_to_m + opts.trackPad,
                ops: [],
            });
        }
    }
    for (const s of data.switches) {
        intervals.push({ from_m: s.pk_m - opts.trackPad, to_m: s.pk_m + opts.trackPad, ops: [] });
    }
    const { start_m, end_m } = data.pk;
    const zones = mergeIntervals(intervals, opts.mergeGap)
        .map((z) => ({ ...z, from_m: Math.max(start_m, z.from_m), to_m: Math.min(end_m, z.to_m) }))
        .filter((z) => z.to_m > z.from_m);
    // Un bout de pleine ligne plus court que l'écart de fusion, au début ou à la fin, rejoint sa zone.
    if (zones.length && zones[0].from_m - start_m < opts.mergeGap) zones[0].from_m = start_m;
    const last = zones.at(-1);
    if (last && end_m - last.to_m < opts.mergeGap) last.to_m = end_m;
    return zones;
}

/**
 * Axe des PK : morceaux linéaires, dilatés dans les zones, comprimés en pleine ligne (largeur proportionnelle
 * à la longueur, bornée). `x(pk)` et `pk(x)` sont réciproques ; hors de l'étendue, ramenés au bord.
 *
 * @returns {{ pieces: Array<{ from_m: number, to_m: number, x0: number, x1: number, kind: 'zone'|'plain' }>,
 *   zones: Array<{ from_m: number, to_m: number, ops: string[] }>, width: number,
 *   x: (pk: number) => number, pk: (x: number) => number }}
 */
export function buildAxis(data, opts = AXIS) {
    const { start_m, end_m } = data.pk;
    const zones = detailZones(data, opts);
    /** @type {Array<{ from_m: number, to_m: number, x0: number, x1: number, kind: 'zone'|'plain' }>} */
    const pieces = [];
    let x = 0;
    let at = start_m;
    const plain = (from, to) => {
        if (to <= from) return;
        const w = Math.min(opts.plainMax, Math.max(opts.plainMin, (to - from) * opts.plainScale));
        pieces.push({ from_m: from, to_m: to, x0: x, x1: x + w, kind: 'plain' });
        x += w;
    };
    for (const z of zones) {
        plain(at, z.from_m);
        const w = (z.to_m - z.from_m) * opts.zoneScale;
        pieces.push({ from_m: z.from_m, to_m: z.to_m, x0: x, x1: x + w, kind: 'zone' });
        x += w;
        at = z.to_m;
    }
    plain(at, end_m);
    if (pieces.length === 0) {
        pieces.push({ from_m: start_m, to_m: end_m, x0: 0, x1: 0, kind: 'plain' });
    }

    const toX = (pk) => {
        if (pk <= pieces[0].from_m) return pieces[0].x0;
        const last = pieces.at(-1);
        if (pk >= last.to_m) return last.x1;
        let lo = 0;
        let hi = pieces.length - 1;
        while (lo < hi) {
            const mid = (lo + hi) >> 1;
            if (pieces[mid].to_m < pk) lo = mid + 1;
            else hi = mid;
        }
        const p = pieces[lo];
        const span = p.to_m - p.from_m;
        return span > 0 ? p.x0 + ((pk - p.from_m) / span) * (p.x1 - p.x0) : p.x0;
    };
    const toPk = (px) => {
        if (px <= pieces[0].x0) return pieces[0].from_m;
        const last = pieces.at(-1);
        if (px >= last.x1) return last.to_m;
        const p = pieces.find((q) => px <= q.x1) ?? last;
        const w = p.x1 - p.x0;
        return w > 0 ? p.from_m + ((px - p.x0) / w) * (p.to_m - p.from_m) : p.from_m;
    };
    return { pieces, zones, width: x, x: toX, pk: toPk };
}

/**
 * Rangées du schéma : une par valeur de `lane`, la plus petite en haut. Les liaisons (`crossover`) sont
 * des diagonales entre deux voies : elles n'ouvrent pas de rangée à elles seules.
 *
 * @returns {{ lanes: number[], row: (lane: number) => number, count: number }}
 */
export function laneRows(tracks) {
    const own = tracks.filter((t) => t.kind !== 'crossover');
    const lanes = [...new Set((own.length ? own : tracks).map((t) => t.lane))].sort(
        (a, b) => a - b,
    );
    if (lanes.length === 0) lanes.push(0);
    const index = new Map(lanes.map((l, i) => [l, i]));
    const row = (lane) => {
        if (index.has(lane)) return index.get(lane);
        // Rangée la plus proche (liaison posée entre deux rangées).
        let best = 0;
        for (let i = 0; i < lanes.length; i++) {
            if (Math.abs(lanes[i] - lane) < Math.abs(lanes[best] - lane)) best = i;
        }
        return best;
    };
    return { lanes, row, count: lanes.length };
}

/** Extrémité d'une voie la plus proche d'un PK : 'start', 'end', ou null si le PK est loin des deux. */
export function nearEnd(track, pk) {
    const len = Math.max(1, track.pk_to_m - track.pk_from_m);
    const tol = Math.max(60, Math.min(250, len * 0.2));
    const dStart = Math.abs(pk - track.pk_from_m);
    const dEnd = Math.abs(pk - track.pk_to_m);
    if (Math.min(dStart, dEnd) > tol) return null;
    return dStart <= dEnd ? 'start' : 'end';
}

/**
 * Raccordements des voies : pour chaque aiguillage, la voie qui s'en écarte (celle qui commence ou finit à
 * l'aiguillage) et sa voie mère. Un aiguillage dont aucune des deux voies ne s'arrête là est la moitié d'une
 * communication entre deux voies qui continuent : il reçoit sa propre diagonale (`stubs`).
 *
 * @returns {{ ends: Map<string, { start: any, end: any }>, stubs: Array<{ sw: any, from: any, to: any }> }}
 */
export function connections(data) {
    const byId = new Map(data.tracks.map((t) => [t.id, t]));
    /** @type {Map<string, { start: any, end: any }>} */
    const ends = new Map(data.tracks.map((t) => [t.id, { start: null, end: null }]));
    const stubs = [];
    for (const sw of data.switches) {
        const a = byId.get(sw.from_track);
        const b = byId.get(sw.to_track);
        if (!a || !b) continue;
        const bEnd = nearEnd(b, sw.pk_m);
        const aEnd = nearEnd(a, sw.pk_m);
        if (bEnd && !ends.get(b.id)[bEnd]) {
            ends.get(b.id)[bEnd] = { sw, other: a };
        } else if (aEnd && !ends.get(a.id)[aEnd]) {
            ends.get(a.id)[aEnd] = { sw, other: b };
        } else if (a.lane !== b.lane) {
            stubs.push({ sw, from: a, to: b });
        }
    }
    return { ends, stubs };
}

/**
 * Tracé de chaque voie en coordonnées du schéma (avant zoom et marges) : points [x, y].
 *
 * @param {ReturnType<typeof normalize>} data
 * @param {(pk: number) => number} x PK vers abscisse (axe déjà multiplié par le zoom)
 * @param {(lane: number) => number} y rangée vers ordonnée
 * @returns {Array<{ track: any, points: number[][], label: { x: number, y: number, a: number, b: number }|null }>}
 */
export function trackPaths(data, x, y) {
    const { ends, stubs } = connections(data);
    const out = [];
    for (const t of data.tracks) {
        const c = ends.get(t.id);
        if (t.kind === 'crossover' && c.start && c.end) {
            out.push({
                track: t,
                points: [
                    [x(c.start.sw.pk_m), y(c.start.other.lane)],
                    [x(c.end.sw.pk_m), y(c.end.other.lane)],
                ],
                label: null,
            });
            continue;
        }
        const yt = y(t.lane);
        let x0 = x(t.pk_from_m);
        let x1 = x(t.pk_to_m);
        const points = [];
        let startDiag = null;
        let endDiag = null;
        if (c.start && y(c.start.other.lane) !== yt) {
            const xs = x(c.start.sw.pk_m);
            startDiag = [xs, y(c.start.other.lane)];
            x0 = Math.min(Math.max(x0, xs), x1);
        }
        if (c.end && y(c.end.other.lane) !== yt) {
            const xe = x(c.end.sw.pk_m);
            endDiag = [xe, y(c.end.other.lane)];
            x1 = Math.max(Math.min(x1, xe), x0);
        }
        // Diagonale : DIAG px par rangée franchie, jamais plus de la moitié de la voie.
        const span = Math.max(0, x1 - x0);
        const dx = (from) => Math.min((Math.abs(from[1] - yt) / ROW_H) * DIAG, span / 2);
        if (startDiag) {
            points.push(startDiag, [startDiag[0] + dx(startDiag), yt]);
        } else {
            points.push([x0, yt]);
        }
        if (endDiag) {
            points.push([endDiag[0] - dx(endDiag), yt], endDiag);
        } else {
            points.push([x1, yt]);
        }
        const flat = points.filter((p) => p[1] === yt);
        const a = flat[0]?.[0] ?? x0;
        const b = flat.at(-1)?.[0] ?? x1;
        out.push({ track: t, points, label: t.ref ? { x: (a + b) / 2, y: yt, a, b } : null });
    }
    for (const s of stubs) {
        const xs = x(s.sw.pk_m);
        const y0 = y(s.from.lane);
        const y1 = y(s.to.lane);
        const dir = s.sw.facing === 'down' ? -1 : 1;
        out.push({
            track: { id: `stub-${s.sw.id}`, kind: 'crossover', source: s.sw.source },
            points: [
                [xs, y0],
                [xs + dir * (Math.abs(y1 - y0) / ROW_H) * DIAG, y1],
            ],
            label: null,
        });
    }
    return out;
}

/** Chemin SVG d'une polyligne. */
export function pathD(points) {
    return points.map(([px, py], i) => `${i ? 'L' : 'M'}${round(px)} ${round(py)}`).join(' ');
}

const round = (n) => Math.round(n * 10) / 10;

/**
 * Étiquettes sans chevauchement : chaque élément (abscisse `x`, largeur `w`, priorité `rank`, la plus petite
 * d'abord) reçoit la première rangée libre, jusqu'à `maxRows` ; au-delà il n'est pas étiqueté (-1). L'élément
 * reste lisible dans le tableau.
 *
 * @param {Array<{ x: number, w: number, rank?: number }>} items
 * @param {{ maxRows?: number, gap?: number }} [opts]
 * @returns {number[]} rangée de chaque élément, dans l'ordre reçu
 */
export function stackLabels(items, { maxRows = 1, gap = 8 } = {}) {
    const order = items
        .map((it, i) => ({ it, i }))
        .sort((p, q) => (p.it.rank ?? 0) - (q.it.rank ?? 0) || p.it.x - q.it.x);
    /** @type {Array<Array<[number, number]>>} */
    const rows = Array.from({ length: maxRows }, () => []);
    const placed = new Array(items.length).fill(-1);
    for (const { it, i } of order) {
        const left = it.x - it.w / 2;
        const right = left + it.w;
        for (let r = 0; r < maxRows; r++) {
            if (rows[r].every(([l, rr]) => right + gap <= l || left >= rr + gap)) {
                rows[r].push([left, right]);
                placed[i] = r;
                break;
            }
        }
    }
    return placed;
}

/**
 * Étiquettes sans chevauchement, en deux dimensions : boîtes { x0, y0, x1, y1 } placées par priorité (`rank`, la
 * plus petite d'abord, puis de gauche à droite) ; une boîte qui en touche une déjà placée n'est pas écrite
 * (l'objet reste dans le tableau). Grille de 64 px en abscisse pour ne comparer que les voisines.
 *
 * @param {Array<{ x0: number, y0: number, x1: number, y1: number, rank?: number }>} boxes
 * @returns {boolean[]} écrite ou non, dans l'ordre reçu
 */
export function placeBoxes(boxes, gap = 3) {
    const CELL = 64;
    /** @type {Map<number, Array<{ x0: number, y0: number, x1: number, y1: number }>>} */
    const grid = new Map();
    const shown = new Array(boxes.length).fill(false);
    const order = boxes
        .map((b, i) => ({ b, i }))
        .sort((p, q) => (p.b.rank ?? 0) - (q.b.rank ?? 0) || p.b.x0 - q.b.x0);
    for (const { b, i } of order) {
        const c0 = Math.floor((b.x0 - gap) / CELL);
        const c1 = Math.floor((b.x1 + gap) / CELL);
        let free = true;
        for (let c = c0; c <= c1 && free; c++) {
            for (const o of grid.get(c) ?? []) {
                if (
                    b.x0 < o.x1 + gap &&
                    b.x1 + gap > o.x0 &&
                    b.y0 < o.y1 + gap &&
                    b.y1 + gap > o.y0
                ) {
                    free = false;
                    break;
                }
            }
        }
        if (!free) continue;
        shown[i] = true;
        for (let c = c0; c <= c1; c++) {
            if (!grid.has(c)) grid.set(c, []);
            grid.get(c).push(b);
        }
    }
    return shown;
}

/**
 * Où écrire le numéro d'une voie : au milieu d'une voie de gare ; au début d'une voie de pleine ligne et à
 * l'entrée de chaque zone qu'elle traverse (une voie de 50 km garde son numéro lisible dans chaque gare).
 *
 * @param {{ track: any, label: { a: number, b: number }|null }} path
 * @param {number[]} zoneStarts abscisses des débuts de zones
 * @returns {Array<{ x: number, anchor: 'start'|'middle' }>}
 */
export function refSpots(path, zoneStarts) {
    if (!path.label) return [];
    const { a, b } = path.label;
    if (path.track.kind !== 'running') return [{ x: (a + b) / 2, anchor: 'middle' }];
    const spots = [{ x: a + 8, anchor: /** @type {const} */ ('start') }];
    for (const z of zoneStarts) {
        if (z > a + 40 && z < b - 40) spots.push({ x: z + 8, anchor: 'start' });
    }
    return spots;
}

/** Largeur estimée d'un texte (px), sans mesurer le DOM : 0,56 em par caractère en moyenne (Inter). */
export function textWidth(text, size = 14) {
    return String(text ?? '').length * size * 0.56;
}

/**
 * Tableau de parcours : un objet par ligne, trié par PK (puis par genre), avec la distance parcourue depuis
 * le début de la ligne, la vitesse et la déclivité du point. Les manques de la couverture (`coverage.missing`)
 * sont des lignes « ! » à leur PK de début.
 *
 * @param {ReturnType<typeof normalize>} data
 * @returns {Array<{ key: string, kind: string, pk_m: number, km: number, item: any,
 *   kmh: number|null, permille: number|null, prov: { code: string, key: string, approx: boolean } }>}
 */
export function routeRows(data) {
    const rows = [];
    const add = (kind, key, item, pk) => rows.push({ kind, key, item, pk_m: pk });
    for (const op of data.operational_points) add('op', `op-${op.id}`, op, op.pk_m);
    for (const m of data.milestones) add('milestone', `km-${m.id ?? m.pk_m}`, m, m.pk_m);
    data.strip.speeds.forEach((r, i) => add('speed', `v-${i}`, r, r.pk_from_m));
    data.strip.gradients.forEach((r, i) => add('gradient', `g-${i}`, r, r.pk_from_m));
    for (const s of data.signals) add('signal', `sig-${s.id}`, s, s.pk_m);
    for (const s of data.switches) add('switch', `sw-${s.id}`, s, s.pk_m);
    for (const c of data.crossings) {
        add(c.kind === 'level_crossing' ? 'level_crossing' : 'crossing', `x-${c.id}`, c, c.pk_m);
    }
    for (const b of data.buffer_stops) add('buffer_stop', `bs-${b.id ?? b.pk_m}`, b, b.pk_m);
    data.coverage.missing.forEach((m, i) =>
        add('missing', `miss-${i}`, { ...m, source: null }, m.pk_from_m),
    );
    const order = (k) => ROW_KINDS.indexOf(k);
    rows.sort((a, b) => a.pk_m - b.pk_m || order(a.kind) - order(b.kind));
    const start = data.pk.start_m;
    return rows.map((r) => ({
        ...r,
        km: (r.pk_m - start) / 1000,
        kmh: valueAt(data.strip.speeds, r.pk_m)?.kmh ?? null,
        permille: valueAt(data.strip.gradients, r.pk_m)?.permille ?? null,
        prov: provenance(r.item),
    }));
}

/** Index de la ligne du tableau la plus proche d'un PK (la première à égalité), -1 sans ligne. */
export function nearestRow(rows, pk) {
    if (rows.length === 0) return -1;
    let lo = 0;
    let hi = rows.length - 1;
    while (lo < hi) {
        const mid = (lo + hi) >> 1;
        if (rows[mid].pk_m < pk) lo = mid + 1;
        else hi = mid;
    }
    if (lo > 0 && Math.abs(rows[lo - 1].pk_m - pk) <= Math.abs(rows[lo].pk_m - pk)) {
        lo -= 1;
        while (lo > 0 && rows[lo - 1].pk_m === rows[lo].pk_m) lo -= 1;
    }
    return lo;
}

/** Défilement horizontal qui centre l'abscisse `x` dans une fenêtre de largeur `view`, borné au contenu. */
export function centerScroll(x, view, content) {
    return Math.max(0, Math.min(Math.max(0, content - view), Math.round(x - view / 2)));
}

/**
 * Synchronisation tableau vers schéma : défilement horizontal qui amène le PK d'une ligne du tableau au
 * milieu de la fenêtre du schéma (abscisse à l'écran = marge + axe × zoom).
 */
export function scrollForPk(axis, zoom, pad, pk, view, content) {
    return centerScroll(pad + axis.x(pk) * zoom, view, content);
}

/** Synchronisation schéma vers tableau : ligne du tableau la plus proche d'une abscisse du schéma. */
export function rowAtX(axis, zoom, pad, rows, px) {
    return nearestRow(rows, axis.pk((px - pad) / zoom));
}

/** Zoom qui fait tenir toute la ligne dans la largeur `view`, borné à [min, max]. */
export function fitZoom(axisWidth, view, pad, min = 0.02, max = 4) {
    if (axisWidth <= 0 || view <= 2 * pad) return 1;
    return Math.min(max, Math.max(min, (view - 2 * pad) / axisWidth));
}

/** Pas de zoom des boutons « - » et « + ». */
export const ZOOM_STEP = 1.5;

/** Part (0 à 1) de la ligne couverte par une source, d'après `coverage`. */
export function coverageShare(coverage, key) {
    const total = coverage?.total_m ?? 0;
    return total > 0 ? Math.min(1, (coverage[`${key}_m`] ?? 0) / total) : 0;
}

/** Adresse de l'API du schéma des voies d'une ligne. */
export function layoutUrl(slug) {
    return `/api/v1/lines/${encodeURIComponent(slug)}/track-layout`;
}

/**
 * Lit le schéma des voies d'une ligne. Une ligne sans données répond 200 avec des listes vides ; une erreur du
 * serveur ou du réseau rejette la promesse.
 *
 * @param {string} slug
 * @param {typeof fetch} [fetchImpl]
 */
export async function fetchTrackLayout(slug, fetchImpl = fetch) {
    const res = await fetchImpl(layoutUrl(slug), { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(String(res.status));
    return res.json();
}

/** Résumé de la section repliée : points d'exploitation, aiguillages, signaux. */
export function layoutSummary(data) {
    return {
        ops: data.operational_points.length,
        switches: data.switches.length,
        signals: data.signals.length,
    };
}

/** Déclivité signée : « +2,5 », « -10,5 », « 0 ». */
export function formatPermille(v, lang = 'fr') {
    return formatNumber(v, lang, { maximumFractionDigits: 1, signDisplay: 'exceptZero' });
}

/**
 * Libellé d'une ligne du tableau de parcours (repris par l'annonce de la navigation au clavier dans le schéma).
 *
 * @param {{ kind: string, item: any }} row
 * @param {(key: string, options?: object) => string} t
 * @param {string} lang
 */
export function rowTitle(row, t, lang = 'fr') {
    const it = row.item;
    switch (row.kind) {
        case 'op':
            return it.name;
        case 'speed':
            return t('tracks.row.speed', { kmh: formatNumber(it.kmh, lang) });
        case 'gradient':
            return t('tracks.row.gradient', { value: formatPermille(it.permille, lang) });
        case 'signal':
        case 'switch':
        case 'level_crossing':
        case 'milestone':
            return it.ref
                ? t(`tracks.row.${row.kind}`, { ref: it.ref })
                : t(`tracks.row.${row.kind}_noref`);
        case 'missing':
            return missingText(it, t);
        default:
            return t(`tracks.row.${row.kind}`);
    }
}

/** Familles dont un manque de la couverture a sa phrase (tracks.row.missing.*). */
const MISSING_FAMILIES = new Set(['tracks', 'switches', 'signals', 'speeds', 'gradients']);

/** « Aiguillages absents du PK 17+012 au PK 17+919 » : manque de la couverture (`coverage.missing`). */
export function missingText(m, t) {
    const what = MISSING_FAMILIES.has(m.what) ? m.what : 'other';
    return t(`tracks.row.missing.${what}`, {
        from: pkLabel(m.pk_from_m),
        to: pkLabel(m.pk_to_m),
    });
}
