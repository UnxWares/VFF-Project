// Utilitaires de la page « Le réseau en chiffres » (Pages/Network/Figures.svelte) : lecture des
// agrégats de NetworkFigures (App\Services\NetworkFigures), sans accès au DOM.

/**
 * Ligne de l'ère la plus haute pour une mesure.
 * @param {Array<object>} decades
 * @param {(row: object) => number} pick
 */
export function peakOf(decades, pick) {
    let best = null;
    for (const row of decades) {
        if (best === null || pick(row) > pick(best)) best = row;
    }

    return best;
}

/**
 * Période que résument les flux d'une ligne : 'until' (jusqu'à la première ère), 'since' (ère courante,
 * jusqu'à aujourd'hui) ou 'range' (de from à year).
 * @param {{ year: number, from_year: number|null, current: boolean }} row
 * @returns {{ kind: 'until'|'since'|'range', from: number|null, to: number }}
 */
export function periodOf(row) {
    if (row.from_year === null || row.from_year === undefined)
        return { kind: 'until', from: null, to: row.year };
    if (row.current) return { kind: 'since', from: row.from_year, to: row.year };

    return { kind: 'range', from: row.from_year, to: row.year };
}

/**
 * Part entière en pourcentage, ou null si elle n'existe pas.
 * @param {number|null|undefined} share part entre 0 et 1
 */
export function percentOf(share) {
    return share === null || share === undefined ? null : Math.round(share * 100);
}

/**
 * Début de la ligne d'un groupe de valeurs : total des valeurs.
 * @param {number[]} values
 */
export function sum(values) {
    return values.reduce((a, b) => a + b, 0);
}
