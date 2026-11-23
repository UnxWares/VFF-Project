/**
 * Chronologie de la fiche gare (App\Services\StationChronology) : place des vues aériennes de l'IGN
 * parmi les dates, et libellé de la précision de chaque date.
 */

/**
 * Année où se range une entrée : la borne basse d'un intervalle (« entre 1847 et 1860 » : 1847),
 * sinon l'année de sa date.
 *
 * @param {{date: string, precision?: string, after?: number|null}} entry
 * @returns {number}
 */
export function entryYear(entry) {
    if (entry.precision === 'range' && entry.after != null) return entry.after + 1;
    return Number(String(entry.date).slice(0, 4));
}

/**
 * Entrées et vues aériennes en une seule suite : chaque vue (période `from`-`to`) se place devant la
 * première entrée datée de `from` ou après, donc à côté des dates de sa période ; les vues
 * postérieures à toutes les dates ferment la liste. L'ordre des entrées ne change pas.
 *
 * @template {{date: string, precision?: string, after?: number|null}} E
 * @template {{period: string, from: number, to: number}} V
 * @param {E[]} entries  entrées triées (ordre du serveur)
 * @param {V[]} views    vues aériennes, de la plus ancienne à la plus récente
 * @returns {Array<{kind: 'entry', entry: E, index: number} | {kind: 'aerial', view: V}>}
 */
export function withAerialViews(entries, views) {
    /** @type {Array<{kind: 'entry', entry: E, index: number} | {kind: 'aerial', view: V}>} */
    const out = [];
    const pending = [...views].sort((a, b) => a.from - b.from);
    entries.forEach((entry, index) => {
        while (pending.length && pending[0].from <= entryYear(entry)) {
            out.push({ kind: 'aerial', view: /** @type {V} */ (pending.shift()) });
        }
        out.push({ kind: 'entry', entry, index });
    });
    for (const view of pending) out.push({ kind: 'aerial', view });
    return out;
}

/**
 * Clé de traduction de la précision d'une date (stations.chronology.precision.*) : au jour, au mois,
 * à l'année, à la décennie, borne seule ou date encadrée. Une date déduite garde sa précision ; le
 * badge « Date déduite » dit qu'elle est estimée.
 *
 * @param {{precision?: string}} entry
 * @returns {string}
 */
export function precisionKey(entry) {
    return `stations.chronology.precision.${entry.precision ?? 'day'}`;
}
