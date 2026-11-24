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

/** Voies repliées en un groupe par point d'exploitation : faisceaux, voies de service, embranchements. */
export const FOLD_KINDS = SERVICE_KINDS;

/** En dessous de ce nombre de voies de service, un point d'exploitation les montre telles quelles. */
export const FOLD_MIN = 3;

/** Rangées au plus de chaque côté des voies principales avant de replier aussi les autres voies d'une zone. */
export const ROW_BUDGET = 10;

/** Épaisseur (rangées) d'un côté une fois resserré (compactLanes). */
function sideDepth(tracks) {
    let depth = 0;
    for (const lane of compactLanes(tracks).values()) {
        depth = Math.max(depth, lane < 0 ? -lane : lane - 1);
    }
    return depth;
}

/** Index de la zone de l'axe qui contient un PK, -1 hors des zones. */
export function zoneIndexAt(zones, pk) {
    let lo = 0;
    let hi = zones.length - 1;
    while (lo <= hi) {
        const mid = (lo + hi) >> 1;
        if (pk < zones[mid].from_m) hi = mid - 1;
        else if (pk > zones[mid].to_m) lo = mid + 1;
        else return mid;
    }
    return -1;
}

/**
 * Clé stable d'une zone, pour se souvenir qu'on l'a dépliée : son premier point d'exploitation (gare d'abord), sinon
 * son début arrondi à l'hectomètre.
 */
export function zoneKey(zone, opsById) {
    const ops = zone.ops.map((id) => opsById.get(id)).filter(Boolean);
    const op = ops.find((o) => o.kind === 'station') ?? ops[0];
    return op ? `op:${op.id}` : `pk:${Math.round(zone.from_m / 100)}`;
}

/**
 * Rangées resserrées : de chaque côté des voies principales (rangées 0 et 1), chaque voie prend la rangée la plus
 * proche de la voie principale qui la laisse au-delà de toutes les voies qu'elle chevauche et que le serveur a rangées
 * plus près (ordre latéral du serveur gardé deux à deux). Une grande gare dont le serveur étale les voies sur les
 * rangées -57 à 21 ne garde que l'épaisseur qu'elle a vraiment à chaque endroit.
 *
 * @param {Array<any>} tracks voies sans les liaisons
 * @returns {Map<string, number>} nouvelle rangée par identifiant de voie (les voies des rangées 0 et 1 n'y sont pas)
 */
export function compactLanes(tracks) {
    const out = new Map();
    for (const side of [-1, 1]) {
        // Les plus proches des voies principales d'abord ; à rangée égale, par PK.
        const own = tracks
            .filter((t) => (side < 0 ? t.lane < 0 : t.lane > 1))
            .sort((a, b) => Math.abs(a.lane) - Math.abs(b.lane) || a.pk_from_m - b.pk_from_m);
        /** @type {Array<{ from: number, to: number, depth: number }>} */
        const placed = [];
        for (const t of own) {
            let depth = 0;
            for (const p of placed) {
                if (p.from < t.pk_to_m && t.pk_from_m < p.to && p.depth >= depth)
                    depth = p.depth + 1;
            }
            placed.push({ from: t.pk_from_m, to: t.pk_to_m, depth });
            out.set(t.id, side < 0 ? -1 - depth : 2 + depth);
        }
    }
    return out;
}

/**
 * Vue repliée du schéma : à chaque point d'exploitation (zone dilatée de l'axe) qui a au moins FOLD_MIN voies de
 * faisceau, de service ou d'embranchement, ces voies (et les liaisons qui y mènent) deviennent un groupe dessiné
 * comme une seule rangée (« 23 voies de service »), de leur côté des voies principales ; les aiguillages qui y
 * mènent pointent vers le groupe, ceux qui restent dedans, leurs signaux et leurs heurtoirs ne sont pas dessinés (le
 * tableau de parcours les garde tous). Les zones dont la clé est dans `expanded` restent dépliées. Puis les rangées
 * sont resserrées (compactLanes).
 *
 * @param {ReturnType<typeof normalize>} data
 * @param {Array<{ from_m: number, to_m: number, ops: string[] }>} zones zones de l'axe (buildAxis)
 * @param {Set<string>} [expanded] clés des zones dépliées (zoneKey)
 * @returns {{ data: ReturnType<typeof normalize>, groups: Array<{ id: string, key: string, zone: number,
 *   op: any, side: -1|1, count: number, pk_from_m: number, pk_to_m: number, lane: number, expanded: boolean }> }}
 */
export function foldLayout(data, zones, expanded = new Set()) {
    const opsById = new Map(data.operational_points.map((o) => [o.id, o]));
    const zoneOf = (t) => zoneIndexAt(zones, (t.pk_from_m + t.pk_to_m) / 2);
    const inZone = (t, z) => t.pk_from_m >= zones[z].from_m - 1 && t.pk_to_m <= zones[z].to_m + 1;

    // Voies voisines par les aiguillages (pour les liaisons).
    /** @type {Map<string, Set<string>>} */
    const near = new Map();
    const link = (a, b) => {
        if (!a || !b || a === b) return;
        if (!near.has(a)) near.set(a, new Set());
        near.get(a).add(b);
    };
    for (const sw of data.switches) {
        link(sw.from_track, sw.to_track);
        link(sw.to_track, sw.from_track);
    }

    /** @type {Map<number, any[]>} voies de chaque zone (sans les liaisons) */
    const byZone = new Map();
    for (const t of data.tracks) {
        if (t.kind === 'crossover') continue;
        const z = zoneOf(t);
        if (z < 0 || !inZone(t, z)) continue;
        if (!byZone.has(z)) byZone.set(z, []);
        byZone.get(z).push(t);
    }
    const isMain = (t) => t.lane === 0 || t.lane === 1;
    // Côté d'une voie : au-dessus (rangées négatives) ou au-dessous ; une voie non principale posée sur les rangées 0
    // ou 1 compte au-dessous.
    const onSide = (t, side) =>
        side < 0 ? t.lane < 0 : t.lane > 1 || (t.lane >= 0 && t.kind !== 'running');

    /** @type {Map<string, { zone: number, side: -1|1 }>} voie repliée -> son groupe */
    const folded = new Map();
    /** @type {Array<any>} */
    const groups = [];
    for (const [z, list] of byZone) {
        const key = zoneKey(zones[z], opsById);
        const open = expanded.has(key);
        const zoneOps = zones[z].ops.map((id) => opsById.get(id)).filter(Boolean);
        const op = zoneOps.find((o) => o.kind === 'station') ?? zoneOps[0] ?? null;
        // Voies qui touchent la zone (celles qui la traversent restent toujours dessinées).
        const around = data.tracks.filter(
            (t) =>
                t.kind !== 'crossover' &&
                t.pk_from_m < zones[z].to_m &&
                t.pk_to_m > zones[z].from_m,
        );
        for (const side of /** @type {const} */ ([-1, 1])) {
            const mine = list.filter((t) => onSide(t, side));
            // Par étapes, tant que le côté dépasse ROW_BUDGET rangées : voies de service, puis autres voies
            // principales, puis voies de gare (une très grande gare n'en garde que ses voies 1 et 2).
            const stages = [
                mine.filter((t) => FOLD_KINDS.has(t.kind)),
                mine.filter((t) => t.kind === 'running' && !isMain(t)),
                mine.filter((t) => !FOLD_KINDS.has(t.kind) && t.kind !== 'running'),
            ];
            let chosen = stages[0].length >= FOLD_MIN ? stages[0] : [];
            for (const more of stages.slice(1)) {
                const hidden = new Set(chosen.map((t) => t.id));
                const rest = around.filter((t) => !hidden.has(t.id) && onSide(t, side));
                if (sideDepth(rest) <= ROW_BUDGET) break;
                chosen = [...chosen, ...more];
            }
            if (chosen.length === 0) continue;
            groups.push({
                id: `fold:${key}:${side}`,
                key,
                zone: z,
                op,
                side,
                count: chosen.length,
                service: chosen.every((t) => FOLD_KINDS.has(t.kind)),
                pk_from_m: Math.min(...chosen.map((t) => t.pk_from_m)),
                pk_to_m: Math.max(...chosen.map((t) => t.pk_to_m)),
                lane: 0,
                expanded: open,
            });
            if (!open) for (const t of chosen) folded.set(t.id, { zone: z, side });
        }
    }
    // Liaisons qui mènent à une voie repliée de leur zone : repliées avec elle.
    for (const t of data.tracks) {
        if (t.kind !== 'crossover') continue;
        for (const other of near.get(t.id) ?? []) {
            const f = folded.get(other);
            if (f && zoneOf(t) === f.zone) {
                folded.set(t.id, f);
                break;
            }
        }
    }

    const visible = data.tracks.filter((t) => !folded.has(t.id));
    const lanes = compactLanes(visible.filter((t) => t.kind !== 'crossover'));
    const laneOf = (t) => lanes.get(t.id) ?? t.lane;
    const shown = visible
        .filter((t) => t.kind !== 'crossover')
        .map((t) => ({ ...t, lane: laneOf(t) }));

    const closed = groups.filter((g) => !g.expanded);
    for (const g of closed) {
        const here = shown
            .filter((t) => t.pk_from_m < g.pk_to_m && t.pk_to_m > g.pk_from_m)
            .map((t) => t.lane);
        g.lane = g.side < 0 ? Math.min(0, ...here) - 1 : Math.max(1, ...here) + 1;
    }
    for (const g of groups.filter((x) => x.expanded)) g.lane = g.side;

    const groupOf = (id) => {
        const f = folded.get(id);
        return f ? closed.find((g) => g.zone === f.zone && g.side === f.side) : null;
    };
    const mapTrack = (id) => (id == null ? id : (groupOf(id)?.id ?? id));
    const shownById = new Map(shown.map((t) => [t.id, t]));
    // Liaison gardée : rangée la plus basse des voies qu'elle relie (comme le serveur), sinon la sienne resserrée.
    const crossovers = visible
        .filter((t) => t.kind === 'crossover')
        .map((t) => {
            const linked = [...(near.get(t.id) ?? [])]
                .map((id) => shownById.get(id)?.lane)
                .filter((l) => l !== undefined);
            return { ...t, lane: linked.length ? Math.min(...linked) : laneOf(t) };
        });
    const groupTracks = closed.map((g) => ({
        id: g.id,
        ref: null,
        kind: 'group',
        pk_from_m: g.pk_from_m,
        pk_to_m: g.pk_to_m,
        lane: g.lane,
        count: g.count,
        source: null,
        precision: null,
    }));

    // Aiguillage dans le groupe (ses deux voies repliées, ou la seule connue) : pas dessiné.
    const inside = (sw) =>
        folded.has(sw.from_track) && (sw.to_track == null || folded.has(sw.to_track))
            ? mapTrack(sw.from_track) === mapTrack(sw.to_track ?? sw.from_track)
            : false;
    const switches = data.switches
        .filter((sw) => !inside(sw))
        .map((sw) => {
            const from = mapTrack(sw.from_track);
            const to = mapTrack(sw.to_track);
            // L'aiguillage est dessiné sur la voie qui reste visible, et pointe vers le groupe.
            return folded.has(sw.from_track) && to != null && !folded.has(sw.to_track)
                ? { ...sw, from_track: to, to_track: from }
                : { ...sw, from_track: from, to_track: to };
        });
    return {
        data: {
            ...data,
            tracks: [...shown, ...crossovers, ...groupTracks].sort(
                (a, b) => a.pk_from_m - b.pk_from_m || a.lane - b.lane,
            ),
            switches,
            signals: data.signals.filter((s) => !folded.has(s.track)),
            buffer_stops: data.buffer_stops.filter((b) => !folded.has(b.track)),
            // Traversée de voies entre voies repliées : dans le groupe.
            crossings: data.crossings.filter(
                (c) => !(c.tracks?.length && c.tracks.every((id) => folded.has(id))),
            ),
        },
        groups,
    };
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
 * Un aiguillage qui mène à un groupe replié (foldLayout) donne un court départ (`short`) vers la bande du groupe.
 *
 * @returns {{ ends: Map<string, { start: any, end: any }>,
 *   stubs: Array<{ sw: any, from: any, to: any, short?: boolean }> }}
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
        // Vers un groupe replié : un court départ de la voie dessinée, pas une diagonale jusqu'à la bande.
        if (a.kind === 'group' || b.kind === 'group') {
            const [from, to] = a.kind === 'group' ? [b, a] : [a, b];
            if (from.kind !== 'group') stubs.push({ sw, from, to, short: true });
            continue;
        }
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
        const full = y(s.to.lane);
        // Départ vers un groupe replié : une demi-rangée dans sa direction.
        const y1 = s.short ? y0 + Math.sign(full - y0) * (ROW_H / 2) : full;
        const dir = s.sw.facing === 'down' ? -1 : 1;
        out.push({
            track: {
                id: `stub-${s.sw.id}`,
                kind: s.short ? 'siding' : 'crossover',
                source: s.sw.source,
            },
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

/**
 * PK de ligne d'une ligne du tableau. Sur un livret de parcours (lib/routebook.js, asLayout), l'axe est le kilomètre
 * parcouru et chaque objet garde le PK de sa propre ligne (`line_pk_m`, `line_pk_from_m`) : c'est lui qu'on affiche.
 *
 * @param {{ pk_m: number, item?: any }} row
 */
export function linePk(row) {
    return row.item?.line_pk_m ?? row.item?.line_pk_from_m ?? row.pk_m;
}

/**
 * Graduation de l'axe : PK de la ligne (« 17+500 ») ; sur un livret de parcours (`route`), kilomètres parcourus
 * depuis le départ (« 17,5 »), l'axe n'étant pas un PK.
 *
 * @param {number} m mètres sur l'axe
 * @param {'line'|'route'} [mode]
 */
export function axisLabel(m, mode = 'line', lang = 'fr') {
    if (mode !== 'route') return pkLabel(m);
    return formatNumber(Math.max(0, m) / 1000, lang, { maximumFractionDigits: 1 });
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

/**
 * Adresse de l'API du schéma des voies d'une ligne, avec ses paramètres facultatifs (`from_pk_m`, `to_pk_m`, `kinds`).
 *
 * @param {string} slug
 * @param {{ from_pk_m?: number, to_pk_m?: number, kinds?: string[] }} [params]
 */
export function layoutUrl(slug, params = {}) {
    const qs = new URLSearchParams();
    if (Number.isFinite(params.from_pk_m))
        qs.set('from_pk_m', String(Math.floor(params.from_pk_m)));
    if (Number.isFinite(params.to_pk_m)) qs.set('to_pk_m', String(Math.ceil(params.to_pk_m)));
    if (params.kinds?.length) qs.set('kinds', params.kinds.join(','));
    const q = qs.toString();
    return `/api/v1/lines/${encodeURIComponent(slug)}/track-layout${q ? `?${q}` : ''}`;
}

/** Familles d'une portion lue au fil du défilement (les points d'exploitation viennent de l'aperçu). */
export const DETAIL_KINDS = [
    'tracks',
    'switches',
    'signals',
    'crossings',
    'buffer_stops',
    'milestones',
    'speeds',
    'gradients',
];

/** Longueur visée d'une portion lue au fil du défilement (m). */
export const CHUNK_M = 10000;

/** Au-delà de ce nombre d'objets (voies, aiguillages, signaux…), la ligne est lue par portions. */
export const FULL_LIMIT = 1500;

/** Nombre de portions lues au plus pour une vue : au-delà (ligne entière ajustée), le schéma attend un zoom. */
export const MAX_CHUNKS = 8;

/** Objets dessinés d'après `counts` (aperçu) : tout sauf les points d'exploitation. */
export function objectCount(counts) {
    if (!counts) return 0;
    return Object.entries(counts)
        .filter(([k]) => k !== 'operational_points')
        .reduce((sum, [, n]) => sum + (Number(n) || 0), 0);
}

/**
 * Portions de lecture d'une longue ligne : tous les `size` mètres environ, chaque coupure posée au milieu du plus
 * grand intervalle entre deux points d'exploitation voisins (à un quart de portion près) pour ne pas couper une gare
 * en deux : les voies d'une gare arrivent d'un bloc et leurs rangées restent celles d'une même réponse.
 *
 * @param {number} start
 * @param {number} end
 * @param {number[]} opPks PK des points d'exploitation, triés
 * @returns {Array<[number, number]>}
 */
export function chunkBounds(start, end, opPks = [], size = CHUNK_M) {
    if (!(end > start)) return [[start, end]];
    const cuts = [];
    for (let at = start + size; at < end - size / 2; at += size) {
        let best = at;
        let gap = -1;
        for (let i = 1; i < opPks.length; i++) {
            const a = opPks[i - 1];
            const b = opPks[i];
            const mid = (a + b) / 2;
            if (Math.abs(mid - at) <= size / 4 && b - a > gap) {
                gap = b - a;
                best = Math.round(mid);
            }
        }
        if (best > (cuts.at(-1) ?? start) && best < end) cuts.push(best);
    }
    const edges = [start, ...cuts, end];
    return edges.slice(1).map((b, i) => /** @type {[number, number]} */ ([edges[i], b]));
}

/** Portions qui touchent [from, to], de la plus proche du milieu à la plus éloignée. */
export function chunksFor(bounds, from, to) {
    const mid = (from + to) / 2;
    return bounds
        .map((b, i) => ({ i, d: b[1] < mid ? mid - b[1] : b[0] > mid ? b[0] - mid : 0, b }))
        .filter(({ b }) => b[1] >= from && b[0] <= to)
        .sort((p, q) => p.d - q.d)
        .map(({ i }) => i);
}

/** Tranches de bande (vitesses, déclivités) triées, recollées à la coupure de deux portions. */
export function joinRanges(ranges, same) {
    const out = [];
    for (const r of ranges.slice().sort((a, b) => a.pk_from_m - b.pk_from_m)) {
        const last = out.at(-1);
        if (last && r.pk_from_m <= last.pk_to_m && same(last, r)) {
            last.pk_to_m = Math.max(last.pk_to_m, r.pk_to_m);
        } else {
            out.push({ ...r });
        }
    }
    return out;
}

/**
 * Réponse unique faite de l'aperçu (étendue, sources, points d'exploitation, couverture, `counts`) et des portions
 * déjà lues : un objet lu dans deux portions (voie à cheval sur une coupure) n'est gardé qu'une fois, la première.
 *
 * @param {any} overview réponse `kinds=operational_points`
 * @param {any[]} parts réponses des portions (DETAIL_KINDS)
 */
export function mergeLayouts(overview, parts) {
    const unique = (family) => {
        const seen = new Set();
        const out = [];
        for (const p of parts) {
            for (const o of p?.[family] ?? []) {
                const id = o.id ?? `${o.pk_m}`;
                if (seen.has(id)) continue;
                seen.add(id);
                out.push(o);
            }
        }
        return out;
    };
    const strip = (key) => parts.flatMap((p) => p?.strip?.[key] ?? []);
    const sameSource = (a, b) => a.source === b.source && a.precision === b.precision;
    return {
        ...overview,
        tracks: unique('tracks'),
        switches: unique('switches'),
        signals: unique('signals'),
        crossings: unique('crossings'),
        buffer_stops: unique('buffer_stops'),
        milestones: unique('milestones'),
        strip: {
            speeds: joinRanges(strip('speeds'), (a, b) => a.kmh === b.kmh && sameSource(a, b)),
            gradients: joinRanges(
                strip('gradients'),
                (a, b) => a.permille === b.permille && sameSource(a, b),
            ),
        },
    };
}

/**
 * Lit le schéma des voies d'une ligne. Une ligne sans données répond 200 avec des listes vides ; une erreur du
 * serveur ou du réseau rejette la promesse.
 *
 * @param {string} slug
 * @param {typeof fetch} [fetchImpl]
 * @param {{ from_pk_m?: number, to_pk_m?: number, kinds?: string[] }} [params]
 */
export async function fetchTrackLayout(slug, fetchImpl = fetch, params = {}) {
    const res = await fetchImpl(layoutUrl(slug, params), {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(String(res.status));
    return res.json();
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

/**
 * Manques de la couverture résumés par famille, dans l'ordre des familles : longueur totale (m), nombre de portions,
 * et `all` quand la famille manque sur (presque) toute la longueur servie. Le bandeau au-dessus du tableau le dit en
 * une phrase courte plutôt qu'une ligne par portion.
 *
 * @param {Array<{ pk_from_m: number, pk_to_m: number, what: string }>} missing
 * @param {number} total longueur servie (m)
 * @returns {Array<{ what: string, metres: number, count: number, all: boolean }>}
 */
export function gapSummary(missing, total) {
    const order = [...MISSING_FAMILIES, 'other'];
    /** @type {Record<string, { what: string, metres: number, count: number, all: boolean }>} */
    const by = {};
    for (const m of missing) {
        const what = MISSING_FAMILIES.has(m.what) ? m.what : 'other';
        by[what] ??= { what, metres: 0, count: 0, all: false };
        by[what].metres += Math.max(0, m.pk_to_m - m.pk_from_m);
        by[what].count += 1;
    }
    return order
        .filter((w) => by[w])
        .map((w) => ({ ...by[w], all: total > 0 && by[w].metres >= 0.95 * total }));
}

/** Familles dont un manque de la couverture a sa phrase (tracks.row.missing.*). */
const MISSING_FAMILIES = new Set(['tracks', 'switches', 'signals', 'speeds', 'gradients']);

/** « Aiguillages absents du PK 17+012 au PK 17+919 » : manque de la couverture (`coverage.missing`). */
export function missingText(m, t) {
    const what = MISSING_FAMILIES.has(m.what) ? m.what : 'other';
    return t(`tracks.row.missing.${what}`, {
        // Livret de parcours : PK de la ligne de la portion (lib/routebook.js), pas les mètres parcourus de l'axe.
        from: pkLabel(m.line_pk_from_m ?? m.pk_from_m),
        to: pkLabel(m.line_pk_to_m ?? m.pk_to_m),
    });
}
