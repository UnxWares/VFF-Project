// Schéma de ligne (App\Services\Lines\LineSchematic, GET /api/v1/lines/{line}/schematic) : rangées du
// schéma, placement des bandes dans chaque rangée et clés de légende de la section de la fiche
// (Components/Lines/LineSchematic.svelte). Logique pure, testée par tests/js/lineSchematic.test.js.
//
// Le schéma est une liste de rangées dans le flux de la page (une par gare, bifurcation ou bout de
// ligne) : chaque rangée prend la hauteur de son texte, plus un écart qui grandit avec la distance
// jusqu'à la suivante. Une rangée couvre l'intervalle de PK [pk de son repère, pk du repère suivant] :
// les bandes, ouvrages et passages à niveau y sont posés en pourcentage de cet intervalle. Rien ne se
// chevauche, quel que soit le zoom ou la longueur des noms.

export { pkLabel } from '$lib/lineSpeeds.js';

/** Écart ajouté sous une rangée, en pixels par kilomètre jusqu'au repère suivant, et plafond. */
export const GAP_PER_KM = 3;
export const GAP_MAX = 72;

/** Bornes des classes de vitesse (km/h) : jusqu'à 60, 100, 140, 200, puis au-delà. */
export const SPEED_STEPS = [60, 100, 140, 200];

/** Écart (m) au-delà duquel un bout de ligne sans gare reçoit sa propre rangée. */
export const END_MIN_M = 500;

/**
 * Écart sous une rangée, selon la distance au repère suivant.
 * @param {number} deltaM
 * @returns {number}
 */
export function gapPx(deltaM) {
    if (!(deltaM > 0)) return 0;
    return Math.min(GAP_MAX, Math.round((deltaM / 1000) * GAP_PER_KM));
}

/**
 * Classe de vitesse (0 à 4).
 * @param {number} kmh
 * @returns {number}
 */
export function speedBucket(kmh) {
    const i = SPEED_STEPS.findIndex((s) => kmh <= s);
    return i === -1 ? SPEED_STEPS.length : i;
}

/**
 * Clé de légende d'une tranche de voies : single, double, multi, banalised ou other.
 * @param {{ tracks: number|null, banalised?: boolean }} r
 * @returns {string}
 */
export function tracksKey(r) {
    if (r.tracks === 1) return 'single';
    if (r.tracks === 2) return 'double';
    if (r.tracks !== null && r.tracks > 2) return 'multi';
    return r.banalised ? 'banalised' : 'other';
}

/**
 * Clé de légende d'une tranche électrifiée : ac25 (25 kV 50 Hz), dc1500 (1 500 V continu), third_rail,
 * other (autre tension), unknown (tension non précisée).
 * @param {{ voltage_v: number|null, system: string }} r
 * @returns {string}
 */
export function electrificationKey(r) {
    if (r.system === 'third_rail') return 'third_rail';
    if (r.voltage_v === null || r.system === 'unknown') return 'unknown';
    if (r.system === 'ac' && r.voltage_v === 25000) return 'ac25';
    if (r.system === 'dc' && r.voltage_v === 1500) return 'dc1500';
    return 'other';
}

/**
 * Clé de légende d'une tranche de signalisation : famille de cantonnement, ou none (sans cantonnement).
 * @param {{ family: string|null }} r
 * @returns {string}
 */
export function signallingKey(r) {
    return r.family ?? 'none';
}

/**
 * Clé de légende d'une tranche de vitesse.
 * @param {{ max_speed_kmh: number }} r
 * @returns {string}
 */
export function speedKey(r) {
    return `s${speedBucket(r.max_speed_kmh)}`;
}

/** Clé de légende de chaque bande. */
export const BAND_KEYS = {
    tracks: tracksKey,
    electrification: electrificationKey,
    signalling: signallingKey,
    speed: speedKey,
};

/** Ordre des clés dans la légende de chaque bande. */
export const LEGEND_ORDER = {
    tracks: ['single', 'double', 'multi', 'banalised', 'other'],
    electrification: ['dc1500', 'ac25', 'other', 'third_rail', 'unknown'],
    signalling: ['automatic_block', 'restricted_block', 'manual_block', 'telephone', 'cab', 'none'],
    speed: ['s0', 's1', 's2', 's3', 's4'],
};

/**
 * Clés présentes dans les tranches d'une bande, dans l'ordre de la légende.
 * @param {string} band
 * @param {Array<any>} ranges
 * @returns {string[]}
 */
export function legendKeys(band, ranges) {
    const fn = BAND_KEYS[band];
    const seen = new Set(ranges.map((r) => fn(r)));
    return LEGEND_ORDER[band].filter((k) => seen.has(k));
}

/**
 * Tranches voisines de même valeur réunies (pour les tableaux et les étiquettes) : la valeur est la
 * clé fine (`detail`), pas seulement la classe de légende (160 et 140 km/h restent deux tranches).
 * @param {Array<any>} ranges triées par PK de début
 * @param {(r: any) => string} detail
 * @param {number} [gapM]
 * @returns {Array<any>}
 */
export function mergeRuns(ranges, detail, gapM = 50) {
    const out = [];
    for (const r of ranges) {
        const last = out.at(-1);
        if (last && detail(last) === detail(r) && r.pk_start_m - last.pk_end_m <= gapM) {
            last.pk_end_m = Math.max(last.pk_end_m, r.pk_end_m);
            continue;
        }
        out.push({ ...r });
    }
    return out;
}

/**
 * Repères du schéma dans l'ordre des PK : gares (les fermées si `closed`), bifurcations (si
 * `junctions`), et les bouts de ligne quand les bandes vont au-delà de la première ou de la dernière
 * gare de plus de END_MIN_M.
 * @param {{ stations: Array<any>, junctions: Array<any>, extent: { pk_start_m: number, pk_end_m: number }|null }} data
 * @param {{ closed: boolean, junctions: boolean }} layers
 * @returns {Array<{ kind: string, pk_m: number, key: string, item: any }>} kind : station, junction ou end
 */
export function anchors(data, layers) {
    const list = [];
    data.stations.forEach((s, i) => {
        if (s.open || layers.closed)
            list.push({ kind: 'station', pk_m: s.pk_m, key: `s${s.id}`, item: s, i });
    });
    if (layers.junctions) {
        data.junctions.forEach((j, i) =>
            list.push({
                kind: 'junction',
                pk_m: j.pk_m,
                key: `j${j.pk_m}-${i}`,
                item: j,
                i: 10000 + i,
            }),
        );
    }
    list.sort((a, b) => a.pk_m - b.pk_m || a.i - b.i);
    const ext = data.extent;
    if (ext) {
        const first = list[0]?.pk_m ?? ext.pk_end_m;
        const last = list.at(-1)?.pk_m ?? ext.pk_start_m;
        if (first - ext.pk_start_m > END_MIN_M) {
            list.unshift({
                kind: 'end',
                pk_m: ext.pk_start_m,
                key: 'end-start',
                item: null,
                i: -1,
            });
        }
        if (ext.pk_end_m - last > END_MIN_M) {
            list.push({ kind: 'end', pk_m: ext.pk_end_m, key: 'end-end', item: null, i: 1e9 });
        }
    }
    return list.map(({ i: _i, ...a }) => a);
}

/**
 * Rangées : chaque repère avec l'intervalle de PK qu'il ouvre (jusqu'au repère suivant) et son écart.
 * @param {Array<{ pk_m: number }>} list
 * @returns {Array<any>}
 */
export function rows(list) {
    let seen = false;
    return list.map((a, i) => {
        const next = list[i + 1];
        const to = next ? next.pk_m : a.pk_m;
        // Première rangée qui couvre un intervalle : les tranches commencées avant y sont étiquetées.
        const first = !seen && to > a.pk_m;
        if (first) seen = true;
        return { ...a, from: a.pk_m, to, gap: gapPx(to - a.pk_m), last: !next, first };
    });
}

const pct = (n) => `${Math.round(n * 10000) / 100}%`;

/**
 * Morceaux des tranches dans l'intervalle d'une rangée, en pourcentage de l'intervalle (`a` : début,
 * `b` : longueur), et `starts` vrai quand la tranche commence dans la rangée (étiquette posée là) ou,
 * pour la première rangée (`first`), quand elle a commencé avant.
 * @param {Array<{ pk_start_m: number, pk_end_m: number }>} ranges
 * @param {number} from
 * @param {number} to
 * @param {boolean} [first]
 * @returns {Array<{ a: string, b: string, starts: boolean, range: any }>}
 */
export function slices(ranges, from, to, first = false) {
    if (!(to > from)) return [];
    const span = to - from;
    const out = [];
    for (const r of ranges) {
        const lo = Math.max(r.pk_start_m, from);
        const hi = Math.min(r.pk_end_m, to);
        if (hi <= lo) continue;
        out.push({
            a: pct((lo - from) / span),
            b: pct((hi - lo) / span),
            starts: (r.pk_start_m >= from || first) && r.pk_start_m < to,
            range: r,
        });
    }
    return out;
}

/**
 * Points (passages à niveau) dans l'intervalle [from, to[ d'une rangée, en pourcentage.
 * @param {Array<{ pk_m: number }>} items triés par PK
 * @param {number} from
 * @param {number} to
 * @returns {Array<{ a: string, item: any }>}
 */
export function pointsIn(items, from, to) {
    if (!(to > from)) return [];
    const span = to - from;
    return items
        .filter((p) => p.pk_m >= from && p.pk_m < to)
        .map((p) => ({ a: pct((p.pk_m - from) / span), item: p }));
}

/**
 * Ère proposée à l'ouverture : l'ère courante, sauf pour une ligne dont aucune gare n'est ouverte
 * aujourd'hui alors qu'elle en avait : la dernière ère où elle avait le plus de gares ouvertes.
 * @param {Array<{ year: number, is_current: boolean, open: number }>} eras
 * @returns {number|null}
 */
export function defaultEra(eras) {
    const current = eras.find((e) => e.is_current) ?? null;
    if (current && current.open > 0) return current.year;
    const best = Math.max(0, ...eras.map((e) => e.open));
    if (best === 0) return current?.year ?? null;
    return eras.filter((e) => e.open === best).at(-1).year;
}

/** Écart minimal (en pourcentage de la rangée) entre deux étiquettes de valeur d'une même bande. */
export const LABEL_MIN_PCT = 30;

/** Au-delà de cette position dans la rangée, une étiquette toucherait celle de la rangée suivante. */
export const LABEL_MAX_PCT = 80;

/**
 * Morceaux d'une rangée qui reçoivent une étiquette de valeur : ceux où la tranche commence, sans
 * deux étiquettes à moins de LABEL_MIN_PCT l'une de l'autre (la première gagne) ni plus bas que
 * LABEL_MAX_PCT (les tableaux sous le schéma donnent toutes les tranches).
 * @param {Array<{ a: string, starts: boolean }>} list
 * @returns {Array<any>}
 */
export function labelled(list) {
    const out = [];
    let last = -Infinity;
    for (const sl of list) {
        if (!sl.starts) continue;
        const at = parseFloat(sl.a);
        if (at - last < LABEL_MIN_PCT || at > LABEL_MAX_PCT) continue;
        out.push(sl);
        last = at;
    }
    return out;
}
