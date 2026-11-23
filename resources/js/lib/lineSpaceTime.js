// Diagramme « où et quand » de la fiche ligne : la ligne déroulée de bout en bout (portions de
// LinePhases dans leur ordre, le long de la ligne), une bande par phase. Fonctions pures, testées par
// Vitest (tests/js/lineSpaceTime.test.js). Données : phases de la page (App\Services\LinePhases) et
// portions lues à part (lib/linePortions.js).

/** Rayon de la Terre (m), pour les distances approchées entre une gare et le tracé. */
const EARTH_M = 6_371_000;

/** Une gare plus loin que ceci du tracé n'est pas placée sur l'axe (m). */
export const STATION_MAX_DISTANCE_M = 1500;

/** Deux extrémités de portions plus proches que ceci se touchent (m). */
export const TOUCH_M = 30;

/** Première et dernière coordonnée d'une géométrie (LineString ou MultiLineString), ou null. */
function endsOf(geometry) {
    const lines = linesOf(geometry).filter((l) => l.length);
    if (!lines.length) return null;
    return [lines[0][0], lines.at(-1).at(-1)];
}

/**
 * Ordre des portions le long de la ligne, d'après leur géométrie : on part de l'extrémité libre la
 * plus au nord-ouest (même règle que le tri du serveur quand les points kilométriques manquent), on
 * suit les portions qui se touchent, et à un embranchement on suit d'abord la plus longue suite (la
 * ligne principale), puis on revient aux branches. Les portions isolées viennent ensuite, dans leur
 * ordre d'origine (celui du serveur, par point kilométrique). Sans géométrie : l'ordre d'origine.
 *
 * @param {Array<{ length_m?: number, geometry?: any }>} portions
 * @returns {number[]} index d'origine, dans l'ordre de l'axe
 */
export function chainOrder(portions, touch = TOUCH_M) {
    return chainGroups(portions, touch).flat();
}

/**
 * Comme chainOrder, découpé en suites continues sur l'axe : une nouvelle suite commence à chaque
 * retour à un embranchement et à chaque morceau isolé (la première part de l'extrémité nord-ouest).
 *
 * @param {Array<{ length_m?: number, geometry?: any }>} portions
 * @returns {number[][]}
 */
export function chainGroups(portions, touch = TOUCH_M) {
    const list = portions ?? [];
    const ends = list.map((p) => endsOf(p?.geometry));
    if (ends.some((e) => !e)) return list.length ? [list.map((_, i) => i)] : [];
    const n = list.length;
    // links[i][k] : portions touchant l'extrémité k (0 début, 1 fin) de i, avec l'extrémité touchée.
    const links = ends.map(() => [[], []]);
    for (let i = 0; i < n; i++) {
        for (let j = i + 1; j < n; j++) {
            for (let a = 0; a < 2; a++) {
                for (let b = 0; b < 2; b++) {
                    if (distanceM(ends[i][a], ends[j][b]) <= touch) {
                        links[i][a].push({ j, end: b });
                        links[j][b].push({ j: i, end: a });
                    }
                }
            }
        }
    }
    const lengthOf = (i) => Math.max(0, Number(list[i]?.length_m) || 0);
    const seen = new Set();
    // Longueur joignable depuis une portion sans repasser par les portions vues (choix de la branche).
    function reach(start, blocked) {
        const stack = [start];
        const met = new Set([start]);
        let total = 0;
        while (stack.length) {
            const i = stack.pop();
            total += lengthOf(i);
            for (const side of links[i])
                for (const { j } of side)
                    if (!met.has(j) && !blocked.has(j)) {
                        met.add(j);
                        stack.push(j);
                    }
        }
        return total;
    }
    const order = [];
    /** @type {number[][]} */
    const groups = [];
    function walk(i, entry, parent = null, ahead = false) {
        seen.add(i);
        // Suite continue seulement par l'extrémité de sortie de la portion qui précède sur l'axe ;
        // sinon (branche prise par l'entrée, retour en arrière) une nouvelle suite commence.
        if (!ahead || parent !== order.at(-1)) groups.push([]);
        groups.at(-1).push(i);
        order.push(i);
        const exit = entry === 0 ? 1 : 0;
        // Suite par l'extrémité de sortie d'abord, puis par l'entrée (branches) ; la plus longue d'abord.
        for (const side of [exit, entry]) {
            const candidates = links[i][side].filter(({ j }) => !seen.has(j));
            // Longueur de chaque suite sans passer par les autres portions du même nœud.
            const next = candidates
                .map((l) => ({
                    ...l,
                    len: reach(
                        l.j,
                        new Set([...seen, ...candidates.map((c) => c.j).filter((j) => j !== l.j)]),
                    ),
                }))
                .sort((x, y) => y.len - x.len || x.j - y.j);
            for (const l of next) if (!seen.has(l.j)) walk(l.j, l.end, i, side === exit);
        }
    }
    // Repère nord-ouest de l'emprise.
    let w = Infinity;
    let north = -Infinity;
    for (const e of ends)
        for (const [x, y] of e) {
            if (x < w) w = x;
            if (y > north) north = y;
        }
    const corner = [w, north];
    while (seen.size < n) {
        /** @type {{ i: number, k: number, d?: number } | null} */
        let best = null;
        for (let i = 0; i < n; i++) {
            if (seen.has(i)) continue;
            for (let k = 0; k < 2; k++) {
                const free = links[i][k].every(({ j }) => seen.has(j));
                if (!free) continue;
                const d = distanceM(ends[i][k], corner);
                if (!best || d < best.d) best = { i, k, d };
            }
        }
        // Boucle fermée sans extrémité libre : première portion restante.
        if (!best) {
            const i = [...Array(n).keys()].find((x) => !seen.has(x));
            best = { i, k: 0 };
        }
        walk(best.i, best.k);
    }
    return groups;
}

/**
 * Axe du diagramme : ordre des portions le long de la ligne (chainOrder), début et fin de chaque
 * portion (m depuis le début de la ligne déroulée, indexés par portion d'origine), longueur totale.
 *
 * @param {Array<{ length_m?: number, geometry?: any }>} portions
 * `main` : portions de la plus longue suite continue (les gares ne sont placées que là : une branche ou
 * un bout de tracé isolé, mis en fin d'axe, ne doit pas y attirer une gare du milieu de la ligne).
 *
 * @returns {{ order: number[], spans: Array<{ start: number, end: number }>, total: number, main: Set<number> }}
 */
export function portionSpans(portions) {
    const list = portions ?? [];
    const groups = chainGroups(list);
    const order = groups.flat();
    const len = (g) => g.reduce((a, i) => a + Math.max(0, Number(list[i]?.length_m) || 0), 0);
    const main = new Set(groups.reduce((a, g) => (len(g) > len(a) ? g : a), []));
    const spans = list.map(() => ({ start: 0, end: 0 }));
    let at = 0;
    for (const i of order) {
        const len = Math.max(0, Number(list[i]?.length_m) || 0);
        spans[i] = { start: at, end: at + len };
        at += len;
    }
    return { order, spans, total: at, main };
}

/**
 * Bandes d'une phase : portions voisines sur l'axe et de même catégorie fusionnées, en pour cent de la
 * longueur totale. Les portions pas encore construites (« none ») ne sont pas dessinées. `changed` : la
 * phase modifie les portions de la bande (portions changées et inchangées ne sont pas fusionnées).
 *
 * @param {{ state?: string[], changes?: Array<{ portions: number[], minor?: boolean }> } | null} phase
 * @param {{ order: number[], spans: Array<{ start: number, end: number }>, total: number }} axis
 * @returns {Array<{ cat: string, from: number, to: number, left: number, width: number, changed: boolean, first: number, members: number[] }>}
 */
export function phaseBands(phase, axis) {
    const changed = changedPortions(phase);
    const bands = [];
    if (!axis?.total) return bands;
    for (const i of axis.order) {
        const span = axis.spans[i];
        const cat = phase?.state?.[i] ?? 'none';
        if (cat === 'none' || span.end <= span.start) continue;
        const isChanged = changed.has(i);
        const prev = bands.at(-1);
        if (prev && prev.cat === cat && prev.changed === isChanged && prev.to === span.start) {
            prev.to = span.end;
            prev.members.push(i);
            continue;
        }
        bands.push({
            cat,
            from: span.start,
            to: span.end,
            left: 0,
            width: 0,
            changed: isChanged,
            first: i,
            members: [i],
        });
    }
    for (const b of bands) {
        b.left = round((b.from / axis.total) * 100);
        b.width = round(((b.to - b.from) / axis.total) * 100);
    }
    return bands;
}

/** Portions que la phase modifie (changements mineurs compris : la portion change bien d'état). */
function changedPortions(phase) {
    const set = new Set();
    for (const c of phase?.changes ?? []) for (const i of c.portions ?? []) set.add(i);
    return set;
}

/**
 * Portion sous une position de l'axe (m), ou null.
 *
 * @param {{ order: number[], spans: Array<{ start: number, end: number }> }} axis
 * @param {number} at
 */
export function portionAt(axis, at) {
    const order = axis?.order ?? [];
    for (const i of order) {
        const s = axis.spans[i];
        if (at >= s.start && at < s.end) return i;
    }
    if (order.length && at >= axis.spans[order.at(-1)].end) return order.at(-1);
    return null;
}

/**
 * Depuis quand une portion est dans l'état qu'elle a à la phase `index` : la dernière phase, jusqu'à
 * celle-ci, qui l'a modifiée, avec le changement en cause (son événement, s'il y en a un).
 *
 * @param {Array<{ changes?: Array<{ portions: number[], event_id?: number|null, from: string, to: string }> }>} phases
 * @param {number} index
 * @param {number} portion
 * @returns {{ phase: number, change: any } | null}
 */
export function stateSince(phases, index, portion) {
    for (let j = Math.min(index, (phases?.length ?? 0) - 1); j >= 0; j--) {
        const change = (phases[j].changes ?? []).find((c) => (c.portions ?? []).includes(portion));
        if (change) return { phase: j, change };
    }
    return null;
}

/** Distance approchée (m) entre deux points [lng, lat] (projection équirectangulaire). */
function distanceM(a, b) {
    const lat = (((a[1] + b[1]) / 2) * Math.PI) / 180;
    const x = (((b[0] - a[0]) * Math.PI) / 180) * Math.cos(lat);
    const y = ((b[1] - a[1]) * Math.PI) / 180;
    return Math.hypot(x, y) * EARTH_M;
}

/** Lignes de coordonnées d'une géométrie GeoJSON (LineString ou MultiLineString). */
function linesOf(geometry) {
    if (geometry?.type === 'LineString') return [geometry.coordinates ?? []];
    if (geometry?.type === 'MultiLineString') return geometry.coordinates ?? [];
    return [];
}

/**
 * Position de chaque gare sur l'axe (m) : son projeté sur le tracé (segment le plus proche), ramené à
 * sa part de la longueur de sa portion. Les gares trop loin du tracé (ou sans coordonnées) sont omises.
 * Triées le long de l'axe.
 *
 * @param {Array<{ name: string, slug?: string, lng?: number|null, lat?: number|null }>} stations
 * @param {Array<{ geometry?: any }>} portions
 * @param {{ spans: Array<{ start: number, end: number }>, main?: Set<number> }} axis
 * @returns {Array<{ name: string, slug?: string, at: number }>}
 */
export function stationPositions(stations, portions, axis, maxDistance = STATION_MAX_DISTANCE_M) {
    // Segments de chaque portion, avec la distance parcourue au début de chacun et la longueur totale.
    const parts = (portions ?? []).map((p) => {
        const segs = [];
        let run = 0;
        for (const line of linesOf(p?.geometry)) {
            for (let k = 1; k < line.length; k++) {
                const len = distanceM(line[k - 1], line[k]);
                segs.push({ a: line[k - 1], b: line[k], run, len });
                run += len;
            }
        }
        return { segs, total: run };
    });
    const placed = [];
    for (const s of stations ?? []) {
        if (s?.lng == null || s?.lat == null) continue;
        const here = [Number(s.lng), Number(s.lat)];
        const cos = Math.cos((here[1] * Math.PI) / 180);
        /** @type {{ d: number, i: number, f: number } | null} */
        let best = null;
        parts.forEach((part, i) => {
            if (axis.main && !axis.main.has(i)) return;
            for (const seg of part.segs) {
                // Projection dans un plan local (longitudes ramenées à la latitude de la gare).
                const ax = (seg.a[0] - here[0]) * cos;
                const ay = seg.a[1] - here[1];
                const bx = (seg.b[0] - here[0]) * cos;
                const by = seg.b[1] - here[1];
                const dx = bx - ax;
                const dy = by - ay;
                const dd = dx * dx + dy * dy;
                const u = dd > 0 ? Math.min(1, Math.max(0, -(ax * dx + ay * dy) / dd)) : 0;
                const d = Math.hypot(ax + u * dx, ay + u * dy) * ((Math.PI / 180) * EARTH_M);
                if (!best || d < best.d) {
                    best = { d, i, f: part.total > 0 ? (seg.run + u * seg.len) / part.total : 0 };
                }
            }
        });
        if (!best || best.d > maxDistance) continue;
        const span = axis.spans[best.i];
        if (!span) continue;
        placed.push({
            name: s.name,
            slug: s.slug,
            at: span.start + (span.end - span.start) * best.f,
        });
    }
    return placed.sort((a, b) => a.at - b.at);
}

/**
 * Gares nommées sur l'axe : les deux extrémités, puis celles du milieu dont le nom tient sans toucher
 * ses voisins retenus. La place d'un nom est estimée d'après son nombre de caractères (`charPct` : la
 * largeur moyenne d'un caractère, en pour cent de l'axe) ; le nom de début est aligné à gauche de sa
 * gare, celui de fin à droite, les autres centrés. `gapPct` : écart minimal entre deux noms.
 *
 * @param {Array<{ name: string, at: number }>} positions triées
 * @param {number} total longueur de l'axe (m)
 * @param {number} charPct
 * @param {number} [gapPct]
 * @returns {Array<{ name: string, at: number, pct: number, edge: 'start'|'end'|null }>}
 */
export function axisLabels(positions, total, charPct, gapPct = 2) {
    if (!positions?.length || !total || !(charPct > 0)) return [];
    const pct = (p) => round((p.at / total) * 100);
    const width = (p) => String(p.name ?? '').length * charPct;
    /** Étendue [gauche, droite] d'un nom selon son alignement. */
    const extent = (p, edge) => {
        const at = pct(p);
        const w = width(p);
        if (edge === 'start') return [at, at + w];
        if (edge === 'end') return [at - w, at];
        return [at - w / 2, at + w / 2];
    };
    const first = positions[0];
    const last = positions.at(-1);
    const out = [{ ...first, pct: pct(first), edge: /** @type {'start'|'end'|null} */ ('start') }];
    if (positions.length === 1) return out;
    const lastBox = extent(last, 'end');
    let right = extent(first, 'start')[1];
    if (lastBox[0] - right < gapPct) return out;
    for (const p of positions.slice(1, -1)) {
        const [l, r] = extent(p, null);
        if (l < 0 || r > 100) continue;
        if (l - right >= gapPct && lastBox[0] - r >= gapPct) {
            out.push({ ...p, pct: pct(p), edge: null });
            right = r;
        }
    }
    out.push({ ...last, pct: pct(last), edge: 'end' });
    return out;
}

/**
 * Libellé court de la date d'une phase, pour la colonne du diagramme : l'année, ou les années d'un
 * intervalle (« 1941-1950 ») ; « avant 1900 » et « depuis 2021 » sont rédigés par `words`.
 *
 * @param {{ date?: string, precision?: string, from_year?: number|null, to_year?: number|null, is_current?: boolean }} phase
 * @param {{ before: (year: number) => string, since: (year: number) => string }} words
 */
export function shortPhaseDate(phase, words) {
    if (!phase) return '';
    if (phase.precision !== 'range') return String(phase.date ?? '').slice(0, 4);
    if (phase.from_year == null) return words.before(phase.to_year);
    if (phase.is_current) return words.since(phase.from_year);
    return `${phase.from_year}-${phase.to_year}`;
}

function round(n) {
    return Math.round(n * 100) / 100;
}
