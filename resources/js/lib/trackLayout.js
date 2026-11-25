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
    // Écart minimal (px à l'écran, quel que soit le zoom) entre deux aiguillages ou traversées d'une zone : une gorge de
    // gare dense est dilatée localement plutôt que d'empiler ses objets.
    minObjectPx: 14,
    // En dessous de ce zoom (vue d'ensemble), l'écart garanti diminue avec le zoom : une ligne entière tient alors dans
    // la fenêtre au lieu d'être tenue large par ses gorges.
    stretchZoomMin: 0.4,
};

/** Voies du dessin replié qui ne sont pas des voies (foldLayout) : bande d'un groupe et rangée de son bouton. */
const BAND_KINDS = new Set(['group', 'group-label']);

/** Hauteur (px) de la bande d'un groupe replié. */
export const BAND_H = 22;

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
 * Sur la vue repliée (foldLayout), seules les voies et les aiguillages dessinés comptent : la bande d'un groupe replié
 * ne dilate rien, une gare repliée garde la largeur de ses voies visibles (Paris-Austerlitz, Brétigny).
 *
 * @returns {Array<{ from_m: number, to_m: number, ops: string[] }>}
 */
export function detailZones(data, opts = AXIS) {
    const extents = opExtents(data, opts);
    const intervals = [...extents].map(([id, e]) => ({ ...e, ops: [id] }));
    for (const t of data.tracks) {
        if (BAND_KINDS.has(t.kind)) continue;
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
 * PK des objets qui doivent rester séparés sur l'axe (aiguillages et traversées de voies dessinés), triés, sans
 * doublon.
 *
 * @param {{ switches: Array<{ pk_m: number }>, crossings: Array<{ pk_m: number, kind?: string }> }} data
 * @returns {number[]}
 */
export function objectPks(data) {
    const pks = [
        ...data.switches.map((s) => s.pk_m),
        ...data.crossings.filter((c) => c.kind !== 'level_crossing').map((c) => c.pk_m),
    ];
    return [...new Set(pks.filter(Number.isFinite))].sort((a, b) => a - b);
}

/**
 * Axe des PK : morceaux linéaires, dilatés dans les zones, comprimés en pleine ligne (largeur proportionnelle
 * à la longueur, bornée). `x(pk)` et `pk(x)` sont réciproques ; hors de l'étendue, ramenés au bord.
 *
 * `dense` (objectPks) : dans une zone, deux objets voisins gardent au moins `minObjectPx` pixels entre eux À L'ÉCRAN,
 * au zoom `zoom` (l'abscisse à l'écran est `x(pk) × zoom`) : l'intervalle qui les sépare est élargi, le reste de la zone
 * garde son échelle. Une zone est alors faite de plusieurs morceaux (`zone` : son index, `first` : son premier
 * morceau). Sous `stretchZoomMin`, l'écart est celui de ce zoom-là, réduit d'autant.
 *
 * @param {ReturnType<typeof normalize>} data
 * @param {typeof AXIS} [opts]
 * @param {number[]} [dense] PK triés des objets à séparer
 * @param {number} [zoom]
 */
export function buildAxis(data, opts = AXIS, dense = [], zoom = 1) {
    return axisFromZones(detailZones(data, opts), data.pk, opts, dense, zoom);
}

/**
 * Axe des PK à partir de zones déjà calculées (detailZones) : seul ce calcul dépend du zoom, il est rejoué à chaque
 * zoom et par « Ajuster » (fitZoomFor) sans recalculer les zones.
 *
 * @param {Array<{ from_m: number, to_m: number, ops: string[] }>} zones
 * @param {{ start_m: number, end_m: number }} extent étendue de la ligne (`data.pk`)
 * @param {typeof AXIS} [opts]
 * @param {number[]} [dense]
 * @param {number} [zoom]
 * @returns {{ pieces: Array<{ from_m: number, to_m: number, x0: number, x1: number, kind: 'zone'|'plain',
 *   zone?: number, first?: boolean }>,
 *   zones: Array<{ from_m: number, to_m: number, ops: string[] }>, width: number,
 *   x: (pk: number) => number, pk: (x: number) => number }}
 */
export function axisFromZones(zones, extent, opts = AXIS, dense = [], zoom = 1) {
    const { start_m, end_m } = extent;
    /** @type {Array<{ from_m: number, to_m: number, x0: number, x1: number, kind: 'zone'|'plain', zone?: number, first?: boolean }>} */
    const pieces = [];
    let x = 0;
    let at = start_m;
    const plain = (from, to) => {
        if (to <= from) return;
        const w = Math.min(opts.plainMax, Math.max(opts.plainMin, (to - from) * opts.plainScale));
        pieces.push({ from_m: from, to_m: to, x0: x, x1: x + w, kind: 'plain' });
        x += w;
    };
    // Écart à garder sur l'axe (avant zoom) pour en avoir `minObjectPx` à l'écran.
    const minPx = (opts.minObjectPx ?? 0) / Math.max(zoom, opts.stretchZoomMin ?? 0, 1e-6);
    const minGap = minPx / opts.zoneScale;
    let d = 0;
    zones.forEach((z, zi) => {
        plain(at, z.from_m);
        // Bornes de la zone et objets qu'elle contient ; un intervalle entre deux objets trop proches est élargi.
        while (d < dense.length && dense[d] <= z.from_m) d++;
        const inside = [];
        while (d < dense.length && dense[d] < z.to_m) inside.push(dense[d++]);
        const marks = [z.from_m, ...inside, z.to_m];
        let open = null;
        // Le dernier morceau est-il élargi (un morceau à l'échelle de la zone peut s'allonger) ?
        let openStretched = true;
        for (let i = 0; i + 1 < marks.length; i++) {
            const [a, b] = [marks[i], marks[i + 1]];
            if (b <= a) continue;
            const between = i > 0 && i + 2 < marks.length;
            const natural = (b - a) * opts.zoneScale;
            const w = between && b - a < minGap ? Math.max(natural, minPx) : natural;
            // Morceaux contigus à l'échelle de la zone : un seul.
            if (w === natural && open && !openStretched) {
                open.to_m = b;
                open.x1 += w;
            } else {
                open = {
                    from_m: a,
                    to_m: b,
                    x0: x,
                    x1: x + w,
                    kind: /** @type {const} */ ('zone'),
                    zone: zi,
                    first: false,
                };
                openStretched = w !== natural;
                pieces.push(open);
            }
            x += w;
        }
        at = z.to_m;
    });
    plain(at, end_m);
    for (let i = 0; i < pieces.length; i++) {
        if (pieces[i].kind === 'zone')
            pieces[i].first = i === 0 || pieces[i - 1].zone !== pieces[i].zone;
    }
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
        let lo = 0;
        let hi = pieces.length - 1;
        while (lo < hi) {
            const mid = (lo + hi) >> 1;
            if (pieces[mid].x1 < px) lo = mid + 1;
            else hi = mid;
        }
        const p = pieces[lo];
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
        // Pièces d'une même voie (même rangée du serveur, même numéro, qui se touchent) : une seule unité, qui garde
        // une seule rangée (une voie du RINF coupée à chaque section ne saute pas d'une rangée à l'autre).
        /** @type {Array<{ ids: string[], lane: number, from: number, to: number }>} */
        const units = [];
        /** @type {Map<string, { ids: string[], lane: number, from: number, to: number }>} */
        const open = new Map();
        const own = tracks
            .filter((t) => (side < 0 ? t.lane < 0 : t.lane > 1))
            .sort((a, b) => a.pk_from_m - b.pk_from_m);
        for (const t of own) {
            const key = t.ref ? `${t.lane}|${t.ref}` : null;
            const u = key ? open.get(key) : undefined;
            if (u && t.pk_from_m <= u.to + 10) {
                u.ids.push(t.id);
                u.to = Math.max(u.to, t.pk_to_m);
                continue;
            }
            const unit = { ids: [t.id], lane: t.lane, from: t.pk_from_m, to: t.pk_to_m };
            units.push(unit);
            if (key) open.set(key, unit);
        }
        // Les plus proches des voies principales d'abord ; à rangée égale, par PK.
        units.sort((a, b) => Math.abs(a.lane) - Math.abs(b.lane) || a.from - b.from);
        /** @type {Array<{ from: number, to: number, depth: number }>} */
        const placed = [];
        for (const u of units) {
            let depth = 0;
            for (const p of placed) {
                if (p.from < u.to && u.from < p.to && p.depth >= depth) depth = p.depth + 1;
            }
            placed.push({ from: u.from, to: u.to, depth });
            for (const id of u.ids) out.set(id, side < 0 ? -1 - depth : 2 + depth);
        }
    }
    return out;
}

/**
 * Vue repliée du schéma : à chaque point d'exploitation (zone dilatée de l'axe) qui a au moins FOLD_MIN voies de
 * faisceau, de service ou d'embranchement, ces voies (et les liaisons qui y mènent) deviennent un groupe dessiné
 * comme une seule rangée (« 23 voies de service »), de leur côté des voies principales ; les aiguillages qui y
 * mènent pointent vers le groupe, ceux qui restent dedans, leurs signaux et leurs heurtoirs ne sont pas dessinés (le
 * tableau de parcours les garde tous). Les zones dont la clé est dans `expanded` restent dépliées. Les voies de
 * `keep` ne sont jamais repliées (page des voies d'une gare : les voies à quai). Puis les rangées sont resserrées
 * (compactLanes).
 *
 * @param {ReturnType<typeof normalize>} data
 * @param {Array<{ from_m: number, to_m: number, ops: string[] }>} zones zones de l'axe (buildAxis)
 * @param {Set<string>} [expanded] clés des zones dépliées (zoneKey)
 * @param {Set<string>} [keep] identifiants des voies toujours dessinées
 * @returns {{ data: ReturnType<typeof normalize>, groups: Array<{ id: string, key: string, zone: number,
 *   op: any, side: -1|1, count: number, pk_from_m: number, pk_to_m: number, lane: number, expanded: boolean }> }}
 */
export function foldLayout(data, zones, expanded = new Set(), keep = new Set()) {
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
            const mine = list.filter((t) => onSide(t, side) && !keep.has(t.id));
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
    // Liaisons qui mènent à une voie repliée de leur zone, directement ou par d'autres liaisons (J0006C puis J0007 à
    // Paris-Austerlitz) : repliées avec elle.
    const links = data.tracks.filter((t) => t.kind === 'crossover');
    for (let changed = true; changed;) {
        changed = false;
        for (const t of links) {
            if (folded.has(t.id)) continue;
            for (const other of near.get(t.id) ?? []) {
                const f = folded.get(other);
                if (f && zoneOf(t) === f.zone) {
                    folded.set(t.id, f);
                    changed = true;
                    break;
                }
            }
        }
    }
    // Liaison qu'aucun aiguillage ne cite, du côté d'un groupe replié de sa zone : dans le groupe (sinon elle resterait
    // seule sur sa rangée du serveur, au-delà de la bande).
    for (const t of links) {
        if (folded.has(t.id) || near.has(t.id)) continue;
        const side = t.lane < 0 ? -1 : t.lane > 1 ? 1 : 0;
        const z = zoneOf(t);
        if (side && groups.some((g) => g.zone === z && g.side === side && !g.expanded)) {
            folded.set(t.id, { zone: z, side });
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
    // Chaque groupe replié : sa bande, et au-delà une rangée pour son bouton (« 23 voies de service »), dans la marge
    // de la zone : le bouton ne recouvre jamais le dessin.
    const groupTracks = closed.flatMap((g) => [
        {
            id: g.id,
            ref: null,
            kind: 'group',
            pk_from_m: g.pk_from_m,
            pk_to_m: g.pk_to_m,
            lane: g.lane,
            count: g.count,
            source: null,
            precision: null,
        },
        {
            id: `${g.id}:label`,
            ref: null,
            kind: 'group-label',
            pk_from_m: g.pk_from_m,
            pk_to_m: g.pk_to_m,
            lane: g.lane + g.side,
            source: null,
            precision: null,
        },
    ]);

    // Aiguillage dans le groupe (ses deux voies repliées, ou la seule connue) : pas dessiné.
    const inside = (sw) =>
        folded.has(sw.from_track) && (sw.to_track == null || folded.has(sw.to_track))
            ? mapTrack(sw.from_track) === mapTrack(sw.to_track ?? sw.from_track)
            : false;
    const mapped = data.switches
        .filter((sw) => !inside(sw))
        .map((sw) => {
            const from = mapTrack(sw.from_track);
            const to = mapTrack(sw.to_track);
            // L'aiguillage est dessiné sur la voie qui reste visible, et pointe vers le groupe.
            return folded.has(sw.from_track) && to != null && !folded.has(sw.to_track)
                ? { ...sw, from_track: to, to_track: from }
                : { ...sw, from_track: from, to_track: to };
        });
    const switches = oneDeparturePerEnd(mapped, closed, [...shown, ...crossovers]);
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

/**
 * Un seul départ vers un groupe replié à chaque bout du groupe : parmi les aiguillages qui mènent d'une voie dessinée à
 * la bande d'un groupe, on garde celui de la moitié d'entrée (PK avant le milieu du groupe) et celui de la moitié de
 * sortie qui partent de la voie la plus proche de la bande (à égalité, le plus près du bout du groupe). Les autres ne
 * sont pas dessinés, comme ceux du groupe : le tableau de parcours les garde tous. Paris-Austerlitz : 48 petits départs
 * en pointillé deviennent 5.
 *
 * @param {Array<any>} switches aiguillages de la vue repliée (`to_track` : identifiant du groupe s'il y mène)
 * @param {Array<{ id: string, lane: number, pk_from_m: number, pk_to_m: number }>} closed groupes repliés
 * @param {Array<{ id: string, lane: number }>} drawn voies dessinées (rangées resserrées)
 */
function oneDeparturePerEnd(switches, closed, drawn) {
    const groupById = new Map(closed.map((g) => [g.id, g]));
    const laneOf = new Map(drawn.map((t) => [t.id, t.lane]));
    /** @type {Map<string, { sw: any, rows: number, toEnd: number }>} */
    const best = new Map();
    for (const sw of switches) {
        const g = groupById.get(sw.to_track);
        if (!g || groupById.has(sw.from_track)) continue;
        const atStart = sw.pk_m < (g.pk_from_m + g.pk_to_m) / 2;
        const key = `${g.id}|${atStart ? 'start' : 'end'}`;
        const rows = Math.abs((laneOf.get(sw.from_track) ?? 0) - g.lane);
        const toEnd = atStart ? sw.pk_m - g.pk_from_m : g.pk_to_m - sw.pk_m;
        const cur = best.get(key);
        if (!cur || rows < cur.rows || (rows === cur.rows && toEnd < cur.toEnd)) {
            best.set(key, { sw, rows, toEnd });
        }
    }
    const kept = new Set([...best.values()].map((b) => b.sw));
    return switches.filter((sw) => !groupById.has(sw.to_track) || kept.has(sw));
}

/**
 * Vue d'ensemble d'une longue ligne entière à l'écran : la ligne d'un bout à l'autre et ses points d'exploitation, sans
 * voies, aiguillages, signaux ni groupes (quelques pixels par gare : rien n'y serait lisible). Les bandes de vitesse et
 * de déclivité restent.
 *
 * @param {ReturnType<typeof normalize>} data
 */
export function lineOverview(data) {
    return {
        ...data,
        tracks: [
            {
                id: 'overview',
                ref: null,
                kind: 'running',
                lane: 0,
                pk_from_m: data.pk.start_m,
                pk_to_m: data.pk.end_m,
                source: null,
                precision: null,
            },
        ],
        switches: [],
        signals: [],
        crossings: [],
        buffer_stops: [],
        milestones: [],
    };
}

/** Extrémité d'une voie la plus proche d'un PK : 'start', 'end', ou null si le PK est loin des deux. */
export function nearEnd(track, pk, maxTol = Infinity) {
    const len = Math.max(1, track.pk_to_m - track.pk_from_m);
    const tol = Math.min(maxTol, Math.max(60, Math.min(250, len * 0.2)));
    const dStart = Math.abs(pk - track.pk_from_m);
    const dEnd = Math.abs(pk - track.pk_to_m);
    if (Math.min(dStart, dEnd) > tol) return null;
    return dStart <= dEnd ? 'start' : 'end';
}

/**
 * Tolérance (m) d'une voie principale (rangées 0 et 1) pour finir à un aiguillage : une voie de ligne du RINF, coupée
 * à chaque section, ne se détourne pas vers sa voisine au premier aiguillage venu ; c'est la voie qui s'en écarte qui
 * prend la diagonale.
 */
const mainTol = (t) => (t.kind === 'running' && (t.lane === 0 || t.lane === 1) ? 15 : Infinity);

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
        const bEnd = nearEnd(b, sw.pk_m, mainTol(b));
        const aEnd = nearEnd(a, sw.pk_m, mainTol(a));
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

/** Distance (m) sous laquelle un heurtoir ou la pièce suivante d'une voie ferme son extrémité. */
const END_TOL_M = 40;

/**
 * Extrémités qui flotteraient : une voie d'évitement, de gare ou de service (hors voies principales des rangées 0 et
 * 1) dont un bout n'a ni aiguillage connu, ni heurtoir, ni pièce suivante sur sa rangée, et n'est pas au bout de la
 * ligne (premier ou dernier point d'exploitation, bord des données). Chacune
 * reçoit sa voie mère : la voie dessinée la plus proche, du côté des voies principales, présente à ce PK. Le schéma y
 * trace un raccord court et discret, marqué déduit (VFF) : la voie ne flotte plus, sans inventer d'aiguillage.
 *
 * @param {ReturnType<typeof normalize>} data
 * @param {Map<string, { start: any, end: any }>} ends extrémités raccordées (connections)
 * @returns {Array<{ track: any, end: 'start'|'end', pk: number, mother: any }>}
 */
export function looseEnds(data, ends) {
    const drawn = data.tracks.filter(
        (t) => t.kind !== 'crossover' && t.kind !== 'group' && t.kind !== 'group-label',
    );
    const buffers = data.buffer_stops ?? [];
    // Bouts de la ligne (gare origine ou terminus, bord des données) : une voie qui y finit n'a pas à se raccorder.
    const ops = data.operational_points;
    const lineFrom = Math.max(data.pk.start_m, ops[0]?.pk_m ?? -Infinity);
    const lineTo = Math.min(data.pk.end_m, ops.at(-1)?.pk_m ?? Infinity);
    const out = [];
    for (const t of drawn) {
        if (t.lane === 0 || t.lane === 1) continue;
        const c = ends.get(t.id);
        for (const end of /** @type {const} */ (['start', 'end'])) {
            if (c?.[end]) continue;
            const pk = end === 'start' ? t.pk_from_m : t.pk_to_m;
            if (pk <= lineFrom + 1 || pk >= lineTo - 1) continue;
            if (
                buffers.some(
                    (b) =>
                        Math.abs(b.pk_m - pk) <= END_TOL_M && (b.track == null || b.track === t.id),
                )
            )
                continue;
            const goesOn = drawn.some(
                (o) =>
                    o !== t &&
                    o.lane === t.lane &&
                    (end === 'start'
                        ? o.pk_from_m < pk && o.pk_to_m >= pk - END_TOL_M
                        : o.pk_to_m > pk && o.pk_from_m <= pk + END_TOL_M),
            );
            if (goesOn) continue;
            const towards = t.lane < 0 ? 1 : -1;
            const limit = t.lane < 0 ? 0 : 1;
            let mother = null;
            for (const o of drawn) {
                if (o === t || o.pk_from_m > pk + 1 || o.pk_to_m < pk - 1) continue;
                const between =
                    towards > 0
                        ? o.lane > t.lane && o.lane <= limit
                        : o.lane < t.lane && o.lane >= limit;
                if (
                    between &&
                    (!mother || Math.abs(o.lane - t.lane) < Math.abs(mother.lane - t.lane))
                )
                    mother = o;
            }
            if (mother) out.push({ track: t, end, pk, mother });
        }
    }
    return out;
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
        if (t.kind === 'group-label') continue;
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
    for (const l of looseEnds(data, ends)) {
        const y0 = y(l.track.lane);
        const y1 = y(l.mother.lane);
        const xe = x(l.pk);
        const dx =
            Math.min(2 * DIAG, (Math.abs(y1 - y0) / ROW_H) * DIAG) * (l.end === 'start' ? -1 : 1);
        out.push({
            track: { id: `loose-${l.track.id}-${l.end}`, kind: 'derived', source: 'vff' },
            points: [
                [xe, y0],
                [xe + dx, y1],
            ],
            label: null,
        });
    }
    for (const s of stubs) {
        const xs = x(s.sw.pk_m);
        const y0 = y(s.from.lane);
        const full = y(s.to.lane);
        // Départ vers un groupe replié : jusqu'au bord de sa bande quand elle est sur la rangée voisine, sinon une
        // demi-rangée dans sa direction (il ne traverse pas les voies d'entre les deux).
        const toward = Math.sign(full - y0);
        const y1 = !s.short
            ? full
            : Math.abs(full - y0) <= ROW_H
              ? full - toward * (BAND_H / 2)
              : y0 + toward * (ROW_H / 2);
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
 * d'abord, puis dans l'ordre de `order`, à défaut de `x`) reçoit la première rangée libre, jusqu'à `maxRows` ; au-delà
 * il n'est pas étiqueté (-1). L'élément reste lisible dans le tableau. Un nom ramené au bord du cadre garde son rang
 * d'origine (`order` : abscisse de son point) : le nom le plus court n'y passe pas devant la gare d'origine.
 *
 * @param {Array<{ x: number, w: number, rank?: number, order?: number }>} items
 * @param {{ maxRows?: number, gap?: number }} [opts]
 * @returns {number[]} rangée de chaque élément, dans l'ordre reçu
 */
export function stackLabels(items, { maxRows = 1, gap = 8 } = {}) {
    const order = items
        .map((it, i) => ({ it, i }))
        .sort(
            (p, q) =>
                (p.it.rank ?? 0) - (q.it.rank ?? 0) ||
                (p.it.order ?? p.it.x) - (q.it.order ?? q.it.x),
        );
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
 * (l'objet reste dans le tableau). Grille de 64 px en abscisse pour ne comparer que les voisines. Les boîtes d'un même
 * `group` sont des places possibles pour une même étiquette : seule la première placée est écrite.
 *
 * @param {Array<{ x0: number, y0: number, x1: number, y1: number, rank?: number, group?: string }>} boxes
 * @returns {boolean[]} écrite ou non, dans l'ordre reçu
 */
export function placeBoxes(boxes, gap = 3) {
    const CELL = 64;
    const done = new Set();
    /** @type {Map<number, Array<{ x0: number, y0: number, x1: number, y1: number }>>} */
    const grid = new Map();
    const shown = new Array(boxes.length).fill(false);
    const order = boxes
        .map((b, i) => ({ b, i }))
        .sort((p, q) => (p.b.rank ?? 0) - (q.b.rank ?? 0) || p.b.x0 - q.b.x0);
    for (const { b, i } of order) {
        if (b.group !== undefined && done.has(b.group)) continue;
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
        if (b.group !== undefined) done.add(b.group);
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

/**
 * Milieu de la vue pour la page (lecture par portions, `onview`) : le PK du milieu quand le défilement ou la largeur
 * de la vue ont changé depuis l'appel précédent, null quand seul l'axe a changé (des voies arrivent). Le défilement
 * d'avant lu sur le nouvel axe donne un autre PK, que la page prenait pour le nouveau milieu : ouverte sur `?pk=`,
 * une longue ligne recentrait sur lui et la gare partait de 400 px sur le côté (570000, Poitiers, 25/11/2026).
 *
 * @returns {(scroll: number, view: number, pk: () => number) => number|null}
 */
export function viewCenterTracker() {
    let last = { scroll: NaN, view: NaN };
    return (scroll, view, pk) => {
        const moved = scroll !== last.scroll || view !== last.view;
        last = { scroll, view };
        return moved ? pk() : null;
    };
}

/**
 * Pièces d'une même voie (même rangée, même numéro, bout à bout à 10 m près : voie d'OpenStreetMap coupée à chaque
 * aiguillage, voie du RINF coupée à chaque section) : son numéro est écrit une fois pour toute la voie.
 *
 * - voie de gare ou de service : seule la plus longue pièce porte le numéro (« F F F » sur trois pièces à
 *   Paris-Tolbiac) ; elle est dans `hidden` pour les autres ;
 * - voie de pleine ligne : le numéro de son début n'est pas répété au début d'une pièce qui la prolonge (`continued`) ;
 *   l'entrée de chaque zone garde le sien (refSpots).
 * `run` : la suite de pièces de chaque voie (clé commune), pour que le dessin écrive le numéro au début de la première
 * pièce assez longue pour lui (Paris-Nord : 4 m devant le heurtoir, puis la partie à quai).
 *
 * @param {Array<{ id: string, ref?: string|null, kind: string, lane: number, pk_from_m: number, pk_to_m: number }>} tracks
 * @returns {{ hidden: Set<string>, continued: Set<string>, run: Map<string, string> }}
 */
export function repeatedPieces(tracks) {
    /** @type {Map<string, Array<any>>} */
    const by = new Map();
    for (const t of tracks) {
        if (!t.ref || t.kind === 'crossover' || BAND_KINDS.has(t.kind)) continue;
        const key = `${t.kind === 'running' ? 'line' : 'local'}|${t.lane}|${t.ref}`;
        if (!by.has(key)) by.set(key, []);
        by.get(key).push(t);
    }
    const hidden = new Set();
    const continued = new Set();
    /** @type {Map<string, string>} */
    const run_ = new Map();
    const len = (t) => t.pk_to_m - t.pk_from_m;
    for (const [key, list] of by) {
        const line = key.startsWith('line|');
        list.sort((a, b) => a.pk_from_m - b.pk_from_m);
        /** @type {Array<any>} */
        let run = [];
        let to = -Infinity;
        const flush = () => {
            for (const t of run) run_.set(t.id, `${key}|${run[0].id}`);
            if (line) {
                for (const t of run.slice(1)) continued.add(t.id);
            } else {
                const keep = run.reduce((a, b) => (len(b) > len(a) ? b : a), run[0]);
                for (const t of run) if (t !== keep) hidden.add(t.id);
            }
            run = [];
        };
        for (const t of list) {
            if (run.length && t.pk_from_m > to + 10) flush();
            to = run.length ? Math.max(to, t.pk_to_m) : t.pk_to_m;
            run.push(t);
        }
        if (run.length) flush();
    }
    return { hidden, continued, run: run_ };
}

/** Références d'une voie principale (voie 1, voie 2, voie unique) : la vitesse de la bande est la leur. */
const MAIN_REF = /^(V?[12]|VU|U)$/i;

/**
 * Portions de la bande sans chevauchement : quand deux portions se recouvrent (deux sections parallèles d'une même
 * ligne au RINF), la première dans l'ordre de préférence garde l'endroit, l'autre n'en garde que ce qui reste. Pour
 * les vitesses, celles des voies principales passent d'abord (`tracks`).
 *
 * @template {{ pk_from_m: number, pk_to_m: number, tracks?: string[]|null }} T
 * @param {T[]} ranges
 * @returns {T[]} triées par PK
 */
export function flattenRanges(ranges) {
    const rank = (r) => (!r.tracks?.length || r.tracks.some((ref) => MAIN_REF.test(ref)) ? 0 : 1);
    const order = ranges
        .map((r, i) => ({ r, i }))
        .sort((a, b) => rank(a.r) - rank(b.r) || a.i - b.i);
    /** @type {Array<[number, number]>} */
    let taken = [];
    const out = [];
    for (const { r } of order) {
        let parts = [[r.pk_from_m, r.pk_to_m]];
        for (const [a, b] of taken) {
            parts = parts.flatMap(([x0, x1]) =>
                b <= x0 || a >= x1
                    ? [[x0, x1]]
                    : [...(a > x0 ? [[x0, a]] : []), ...(b < x1 ? [[b, x1]] : [])],
            );
        }
        for (const [x0, x1] of parts) {
            if (x1 > x0) out.push({ ...r, pk_from_m: x0, pk_to_m: x1 });
        }
        taken = [...taken, [r.pk_from_m, r.pk_to_m]];
    }
    return out.sort((a, b) => a.pk_from_m - b.pk_from_m);
}

/**
 * Cases de la bande (vitesses, déclivités) à l'échelle du dessin : une portion trop courte pour être lue (moins de
 * `minPx` pixels) est regroupée avec ses voisines trop courtes, jusqu'à la largeur lisible, en une case marquée
 * (`merged`) qui donne leur valeur résumée (`combine` : moyenne pondérée des déclivités, plus petite et plus grande
 * vitesse). Une portion assez large reste seule ; un trou dans la bande coupe un regroupement. Le curseur et le
 * tableau gardent les valeurs exactes.
 *
 * @template {{ pk_from_m: number, pk_to_m: number }} T
 * @param {T[]} ranges triées, sans chevauchement (flattenRanges)
 * @param {(pk: number) => number} x
 * @param {{ minPx?: number }} [opts]
 * @returns {Array<{ items: T[], pk_from_m: number, pk_to_m: number, x0: number, x1: number, merged: boolean }>}
 */
export function bandCells(ranges, x, { minPx = 36 } = {}) {
    const out = [];
    /** @type {T[]} */
    let group = [];
    const flush = () => {
        if (!group.length) return;
        const pk_from_m = group[0].pk_from_m;
        const pk_to_m = group.at(-1).pk_to_m;
        out.push({
            items: group,
            pk_from_m,
            pk_to_m,
            x0: x(pk_from_m),
            x1: x(pk_to_m),
            merged: group.length > 1,
        });
        group = [];
    };
    for (const r of ranges) {
        const w = x(r.pk_to_m) - x(r.pk_from_m);
        if (group.length && r.pk_from_m - group.at(-1).pk_to_m > 1) flush();
        if (w >= minPx) {
            flush();
            group = [r];
            flush();
            continue;
        }
        group.push(r);
        if (x(r.pk_to_m) - x(group[0].pk_from_m) >= minPx) flush();
    }
    flush();
    return out;
}

/** Déclivité moyenne d'une case regroupée, pondérée par la longueur de chaque portion (‰, au dixième). */
export function meanPermille(items) {
    let sum = 0;
    let len = 0;
    for (const r of items) {
        const l = Math.max(0, r.pk_to_m - r.pk_from_m);
        sum += r.permille * l;
        len += l;
    }
    return len > 0 ? Math.round((sum / len) * 10) / 10 : (items[0]?.permille ?? 0);
}

/**
 * Graduations de l'axe des PK : chaque candidat garde son trait ; seuls ceux dont le texte tient sans toucher un
 * voisin à `gap` pixels près (par priorité, `rank` le plus petit d'abord) sont écrits, les autres restent des
 * graduations sans texte.
 *
 * @param {Array<{ x: number, tx: number, half: number, rank: number }>} items
 * @param {number} [gap]
 * @returns {boolean[]} texte écrit ou non, dans l'ordre reçu
 */
export function tickLabels(items, gap = 24) {
    return placeBoxes(
        items.map((c) => ({ x0: c.tx - c.half, x1: c.tx + c.half, y0: 0, y1: 10, rank: c.rank })),
        gap,
    );
}

/**
 * Marques regroupées : une seule marque par portion comprimée de pleine ligne, et dans une zone dilatée une seule pour
 * des marques à moins de `minPx` pixels l'une de l'autre ; la marque restante est à l'abscisse moyenne de celles
 * qu'elle remplace et dit combien elle en porte (`count`). Le tableau de parcours les garde toutes.
 *
 * @template {{ x: number }} M
 * @param {M[]} marks triées par abscisse
 * @param {(m: M) => number} pieceOf index de la portion comprimée de la marque, -1 dans une zone dilatée
 * @param {number} [minPx]
 * @returns {Array<M & { count: number }>}
 */
export function clusterMarks(marks, pieceOf, minPx = 14) {
    /** @type {Array<any>} */
    const out = [];
    for (const m of marks) {
        const piece = pieceOf(m);
        const last = out.at(-1);
        const together = last && last.piece === piece && (piece >= 0 || m.x - last.lastX < minPx);
        if (last) last.lastX = m.x;
        if (together) {
            last.sum += m.x;
            last.count += 1;
            last.x = last.sum / last.count;
            continue;
        }
        out.push({ ...m, count: 1, piece, sum: m.x, lastX: m.x });
    }
    return out.map(({ sum: _sum, piece: _piece, lastX: _lastX, ...m }) => m);
}

/** Index de la portion comprimée (pleine ligne) qui contient un PK, -1 dans une zone dilatée ou hors de l'axe. */
export function plainPieceAt(pieces, pk) {
    let lo = 0;
    let hi = pieces.length - 1;
    while (lo <= hi) {
        const mid = (lo + hi) >> 1;
        if (pk < pieces[mid].from_m) hi = mid - 1;
        else if (pk > pieces[mid].to_m) lo = mid + 1;
        else return pieces[mid].kind === 'plain' ? mid : -1;
    }
    return -1;
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
    // La bande (vitesses, déclivités, manques) commence au premier point d'exploitation : une déclivité calculée avant
    // la gare d'origine n'ouvre pas le tableau ; une tranche qui le couvre est lue à son PK. Les objets (aiguillages,
    // signaux…) avant lui restent : le tableau nomme tout ce que le dessin montre.
    const first = data.operational_points[0]?.pk_m;
    const BAND = new Set(['speed', 'gradient', 'missing']);
    const kept = [];
    for (const r of rows) {
        if (first === undefined || !BAND.has(r.kind) || r.pk_m >= first) kept.push(r);
        else if (r.item.pk_to_m > first) kept.push({ ...r, pk_m: first });
    }
    const order = (k) => ROW_KINDS.indexOf(k);
    kept.sort((a, b) => a.pk_m - b.pk_m || order(a.kind) - order(b.kind));
    const start = data.pk.start_m;
    // Vitesses ou déclivités qui se recouvrent (sections parallèles d'une ligne : V1 et V2, V1S et V2S) : chacune dit à
    // quelles voies elle s'applique.
    const parallel = new Set([
        ...overlapping(data.strip.speeds),
        ...overlapping(data.strip.gradients),
    ]);
    // Vitesse et déclivité d'une ligne : celles de la bande (voies principales d'abord, flattenRanges), et pour une ligne
    // de vitesse ou de déclivité, la sienne (celle d'une voie V1S n'est pas remplacée par celle de la voie 1).
    const speedBand = flattenRanges(data.strip.speeds);
    const gradientBand = flattenRanges(data.strip.gradients);
    return kept.map((r) => ({
        ...r,
        km: (r.pk_m - start) / 1000,
        kmh: r.kind === 'speed' ? r.item.kmh : (valueAt(speedBand, r.pk_m)?.kmh ?? null),
        permille:
            r.kind === 'gradient'
                ? r.item.permille
                : (valueAt(gradientBand, r.pk_m)?.permille ?? null),
        prov: provenance(r.item),
        ...((r.kind === 'speed' || r.kind === 'gradient') &&
        parallel.has(r.item) &&
        r.item.tracks?.length
            ? { tracks: r.item.tracks }
            : {}),
    }));
}

/**
 * Tranches (vitesses ou déclivités) qui en recouvrent une autre sur une longueur non nulle : balayage par PK de début,
 * la fin la plus lointaine des précédentes et le début de la suivante suffisent.
 *
 * @template {{ pk_from_m: number, pk_to_m: number }} T
 * @param {T[]} ranges
 * @returns {Set<T>}
 */
export function overlapping(ranges) {
    const sorted = ranges.slice().sort((a, b) => a.pk_from_m - b.pk_from_m);
    const out = new Set();
    let reach = -Infinity;
    for (let i = 0; i < sorted.length; i++) {
        const r = sorted[i];
        const next = sorted[i + 1];
        if (
            reach > r.pk_from_m ||
            (next && next.pk_from_m < r.pk_to_m && r.pk_to_m > r.pk_from_m)
        ) {
            out.add(r);
        }
        reach = Math.max(reach, r.pk_to_m);
    }
    return out;
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

/**
 * « Ajuster » quand la largeur de l'axe dépend du zoom (gorges dilatées à 14 px à l'écran, axisFromZones) : le plus
 * grand zoom de [min, max] auquel toute la ligne tient dans `view`. La largeur à l'écran, `zoom × widthAt(zoom)`, croît
 * avec le zoom : une dichotomie suffit.
 *
 * @param {(zoom: number) => number} widthAt largeur de l'axe (avant zoom) à ce zoom
 * @param {number} view
 * @param {number} pad
 */
export function fitZoomFor(widthAt, view, pad, min = 0.02, max = 4) {
    const room = view - 2 * pad;
    if (room <= 0 || widthAt(1) <= 0) return 1;
    const fits = (z) => z * widthAt(z) <= room;
    if (!fits(min)) return min;
    if (fits(max)) return max;
    let lo = min;
    let hi = max;
    for (let i = 0; i < 40; i++) {
        const mid = (lo + hi) / 2;
        if (fits(mid)) lo = mid;
        else hi = mid;
    }
    return lo;
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
        case 'switch':
            // « Aiguillage simple 12 » : le genre suffit à dire que c'est un aiguillage, suivi de son repère.
            if (it.kind) {
                const kind = t(`tracks.switch_kind.${it.kind}`, { defaultValue: it.kind });
                const title = kind.charAt(0).toLocaleUpperCase(lang) + kind.slice(1);
                return it.ref ? `${title} ${it.ref}` : title;
            }
            return it.ref ? t('tracks.row.switch', { ref: it.ref }) : t('tracks.row.switch_noref');
        case 'signal':
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
