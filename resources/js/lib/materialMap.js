// Carte des lignes d'une série de matériel : calculs purs (décennies, état d'une ligne à une décennie),
// séparés du composant pour être testés. Les données viennent de MaterialLinesMap (`/materials/{slug}/map.json`).

/** Première année proposée : les plus anciennes séries datent des années 1830. */
export const MIN_YEAR = 1830;

const yearOf = (iso) => (iso ? Number(String(iso).slice(0, 4)) : null);

/**
 * Décennies proposées au curseur : de la décennie de la première date connue (mise en service de la série ou
 * début de période) à celle de la dernière (retrait, fin de période, ou année en cours pour une série sans fin).
 *
 * @param {{in_service_from?: number|null, in_service_to?: number|null}} material
 * @param {Array<{periods?: Array<{start_date?: string|null, end_date?: string|null}>}>} lines
 * @param {number} [thisYear]
 * @returns {number[]} années de début de décennie (1950, 1960, ...)
 */
export function decadesOf(material, lines, thisYear = new Date().getFullYear()) {
    const years = [material?.in_service_from, material?.in_service_to].filter((y) => y != null);
    for (const line of lines ?? []) {
        for (const p of line.periods ?? []) {
            for (const y of [yearOf(p.start_date), yearOf(p.end_date)])
                if (y != null) years.push(y);
        }
    }
    if (!years.length) return [];
    const first = Math.max(MIN_YEAR, Math.min(...years));
    // Sans retrait connu, la série a pu circuler jusqu'à aujourd'hui : le curseur va jusqu'à l'année en cours.
    const open = material?.in_service_from != null && material?.in_service_to == null;
    const last = Math.min(thisYear, open ? thisYear : Math.max(...years));
    const out = [];
    for (
        let d = Math.floor(first / 10) * 10;
        d <= Math.floor(Math.max(first, last) / 10) * 10;
        d += 10
    )
        out.push(d);
    return out;
}

/**
 * État d'une ligne pour des années [from, to] :
 * - `dated` : une période datée par une source recouvre l'intervalle ;
 * - `undated` : aucune période connue, la série était en service et la source atteste la ligne sans la dater ;
 * - `off` : sinon (les périodes datées ne recouvrent pas l'intervalle, ou la série n'existait pas).
 * Une période sans fin n'est pas une preuve de circulation jusqu'à aujourd'hui, mais elle ne prouve pas non plus
 * le contraire : elle compte à partir de son début. Une période sans début compte jusqu'à sa fin.
 *
 * @param {{periods?: Array<{start_date?: string|null, end_date?: string|null}>}} line
 * @param {{in_service_from?: number|null, in_service_to?: number|null}} material
 * @param {[number, number] | null} range null : toutes les dates
 * @returns {'dated'|'undated'|'off'}
 */
export function lineState(line, material, range) {
    const periods = line.periods ?? [];
    if (!range) return periods.length ? 'dated' : 'undated';
    const [a, b] = range;
    if (periods.length) {
        const hit = periods.some((p) => {
            const s = yearOf(p.start_date) ?? -Infinity;
            const e = yearOf(p.end_date) ?? Infinity;
            return s <= b && e >= a;
        });
        return hit ? 'dated' : 'off';
    }
    const from = material?.in_service_from ?? -Infinity;
    const to = material?.in_service_to ?? Infinity;
    return from <= b && to >= a ? 'undated' : 'off';
}

/**
 * Intervalle d'années d'une décennie : 1950 donne [1950, 1959].
 *
 * @param {number} decade
 * @returns {[number, number]}
 */
export const decadeRange = (decade) => [decade, decade + 9];

/**
 * Cadre englobant (ouest, sud, est, nord) des tracés, pour cadrer la carte ; null sans tracé.
 *
 * @param {Array<{geometry: any}>} lines
 * @returns {[[number, number], [number, number]] | null}
 */
export function linesBounds(lines) {
    let w = Infinity;
    let s = Infinity;
    let e = -Infinity;
    let n = -Infinity;
    const walk = (c) => {
        if (typeof c[0] === 'number') {
            w = Math.min(w, c[0]);
            e = Math.max(e, c[0]);
            s = Math.min(s, c[1]);
            n = Math.max(n, c[1]);
        } else c.forEach(walk);
    };
    for (const l of lines ?? []) if (l.geometry?.coordinates) walk(l.geometry.coordinates);
    return Number.isFinite(w)
        ? [
              [w, s],
              [e, n],
          ]
        : null;
}
