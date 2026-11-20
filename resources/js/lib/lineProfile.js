// Calculs du graphique « Profil en long » de la fiche ligne (LineProfile.svelte) : graduations,
// tracé SVG coupé aux trous et aux lacunes du parcours, point le plus proche du pointeur,
// déclivité locale et choix des gares nommées sans chevauchement. Données : App\Services\Profiles\LineProfiles (points
// [distance_m, altitude_m|null] dans l'ordre du parcours).

/** Fenêtre de la déclivité locale, comme la déclivité maximale calculée côté serveur. */
export const GRADE_WINDOW_M = 1000;

/**
 * Pas « rond » (1, 2 ou 5 × 10^n), le plus proche de `span` / `count`.
 * @param {number} span
 * @param {number} count
 */
export function niceStep(span, count) {
    if (!(span > 0) || !(count > 0)) return 1;
    const raw = span / count;
    const power = 10 ** Math.floor(Math.log10(raw));
    const unit = raw / power;
    // Seuils arrondis : 2,0025 donne 2 (et non 5), donc des graduations assez nombreuses.
    const nice = unit < 1.5 ? 1 : unit < 3 ? 2 : unit < 7 ? 5 : 10;
    return nice * power;
}

/**
 * Graduations rondes entre deux bornes (incluses si elles tombent sur le pas).
 * @param {number} min
 * @param {number} max
 * @param {number} count nombre d'intervalles visé
 * @returns {number[]}
 */
export function ticks(min, max, count) {
    const step = niceStep(max - min, count);
    const out = [];
    for (let v = Math.ceil(min / step) * step; v <= max + step * 1e-9; v += step) {
        out.push(Math.round(v * 1e6) / 1e6);
    }
    return out;
}

/**
 * Bornes de l'axe des altitudes : altitudes extrêmes élargies au pas rond, avec au moins
 * 40 m d'amplitude (une ligne de plaine n'est pas dessinée comme une montagne).
 * @param {Array<[number, number|null]>} points
 * @param {number} [count]
 * @returns {[number, number]}
 */
export function altitudeDomain(points, count = 4) {
    const zs = points.map((p) => p[1]).filter((z) => z !== null);
    if (zs.length === 0) return [0, 100];
    let lo = Math.min(...zs);
    let hi = Math.max(...zs);
    if (hi - lo < 40) {
        const mid = (hi + lo) / 2;
        lo = mid - 20;
        hi = mid + 20;
    }
    const step = niceStep(hi - lo, count);
    return [Math.floor(lo / step) * step, Math.ceil(hi / step) * step];
}

/**
 * Vrai si une lacune du parcours (`profile.gaps`, saut entre deux morceaux du tracé, sans aucun
 * point) passe entre les distances `a` et `b`, à un demi-mètre près, comme côté serveur
 * (ProfileStats::crossesGap).
 * @param {Array<{ from_m: number, to_m: number }>} gaps
 * @param {number} a
 * @param {number} b
 */
export function crossesGap(gaps, a, b) {
    const lo = Math.min(a, b);
    const hi = Math.max(a, b);
    return gaps.some((g) => g.from_m < hi - 0.5 && g.to_m > lo + 0.5);
}

/**
 * Séries continues de la courbe : coupées là où l'altitude manque (trou, hors de France) et
 * là où une lacune du parcours sépare deux points voisins.
 * @param {Array<[number, number|null]>} points
 * @param {Array<{ from_m: number, to_m: number }>} gaps
 * @returns {Array<Array<[number, number]>>}
 */
export function runs(points, gaps = []) {
    const out = [];
    let run = [];
    let prev = null;
    for (const p of points) {
        if (p[1] === null || (prev !== null && crossesGap(gaps, prev[0], p[0]))) {
            if (run.length) out.push(run);
            run = [];
        }
        if (p[1] !== null) run.push(p);
        prev = p;
    }
    if (run.length) out.push(run);
    return out;
}

/**
 * Tracé SVG de la courbe, coupé aux trous et aux lacunes : un nouveau `M` repart après chacun.
 * @param {Array<[number, number|null]>} points
 * @param {(d: number) => number} x
 * @param {(z: number) => number} y
 * @param {Array<{ from_m: number, to_m: number }>} [gaps]
 */
export function linePath(points, x, y, gaps = []) {
    return runs(points, gaps)
        .map((run) =>
            run
                .map(([d, z], i) => `${i === 0 ? 'M' : 'L'}${x(d).toFixed(1)},${y(z).toFixed(1)}`)
                .join(''),
        )
        .join('');
}

/**
 * Surface sous la courbe jusqu'à la ligne de base, une forme fermée par série continue (une
 * série d'un seul point n'en a pas).
 * @param {Array<[number, number|null]>} points
 * @param {(d: number) => number} x
 * @param {(z: number) => number} y
 * @param {number} baseY ordonnée de la ligne de base, en pixels
 * @param {Array<{ from_m: number, to_m: number }>} [gaps]
 */
export function areaPath(points, x, y, baseY, gaps = []) {
    return runs(points, gaps)
        .filter((run) => run.length > 1)
        .map((run) => {
            const first = x(run[0][0]).toFixed(1);
            const last = x(run[run.length - 1][0]).toFixed(1);
            const top = run.map(([d, z]) => `L${x(d).toFixed(1)},${y(z).toFixed(1)}`).join('');
            return `M${first},${baseY.toFixed(1)}${top}L${last},${baseY.toFixed(1)}Z`;
        })
        .join('');
}

/**
 * Indice du point le plus proche d'une distance (recherche dichotomique, points triés).
 * @param {Array<[number, number|null]>} points
 * @param {number} d
 */
export function nearestIndex(points, d) {
    let lo = 0;
    let hi = points.length - 1;
    if (hi < 0) return -1;
    while (hi - lo > 1) {
        const mid = (lo + hi) >> 1;
        if (points[mid][0] <= d) lo = mid;
        else hi = mid;
    }
    return Math.abs(points[hi][0] - d) < Math.abs(d - points[lo][0]) ? hi : lo;
}

/**
 * Déclivité locale en ‰ autour d'une distance : pente de la droite des moindres carrés des
 * points d'une fenêtre de 1 km centrée (même méthode que la déclivité maximale du serveur).
 * Positive quand la ligne monte dans le sens du parcours. Null s'il manque une altitude dans
 * la fenêtre, si une lacune du parcours la traverse ou s'il y a moins de trois points.
 * @param {Array<[number, number|null]>} points
 * @param {number} d
 * @param {Array<{ from_m: number, to_m: number }>} [gaps]
 * @param {number} [window]
 * @returns {number|null}
 */
export function gradeAt(points, d, gaps = [], window = GRADE_WINDOW_M) {
    if (crossesGap(gaps, d - window / 2, d + window / 2)) return null;
    const inside = points.filter(([pd]) => pd >= d - window / 2 && pd <= d + window / 2);
    if (inside.length < 3 || inside.some(([, z]) => z === null)) return null;
    const n = inside.length;
    const md = inside.reduce((s, [pd]) => s + pd, 0) / n;
    const mz = inside.reduce((s, [, z]) => s + z, 0) / n;
    let num = 0;
    let den = 0;
    for (const [pd, z] of inside) {
        num += (pd - md) * (z - mz);
        den += (pd - md) ** 2;
    }
    return den > 0 ? (num / den) * 1000 : null;
}

/**
 * Gares dont le nom tient sur le graphique sans en chevaucher un autre : les deux extrémités
 * d'abord, puis les autres dans l'ordre du parcours. Chaque nom occupe une boîte de la largeur
 * donnée par `labelWidth`, centrée sur la gare, ou alignée sur elle (`start`, `end`) près des
 * bords du graphique ; une boîte doit rester à `gap` pixels des boîtes déjà posées. Les autres
 * gares restent marquées d'un point, et toutes sont dans le tableau.
 * @template {{ distance_m: number, name: string }} S
 * @param {S[]} stations triées par distance
 * @param {(d: number) => number} x
 * @param {{ left: number, right: number, labelWidth: (name: string) => number, gap?: number }} frame
 * @returns {Map<S, 'start'|'middle'|'end'>} gares nommées et alignement de leur nom
 */
export function labelledStations(stations, x, { left, right, labelWidth, gap = 12 }) {
    const kept = new Map();
    const boxes = [];
    const order =
        stations.length > 1
            ? [stations[0], stations[stations.length - 1], ...stations.slice(1, -1)]
            : stations;
    for (const s of order) {
        const px = x(s.distance_m);
        const w = labelWidth(s.name);
        const anchor = px - w / 2 < left ? 'start' : px + w / 2 > right ? 'end' : 'middle';
        const from = anchor === 'start' ? px : anchor === 'end' ? px - w : px - w / 2;
        const box = [from, from + w];
        if (boxes.every(([a, b]) => box[1] + gap <= a || box[0] >= b + gap)) {
            kept.set(s, anchor);
            boxes.push(box);
        }
    }
    return kept;
}
