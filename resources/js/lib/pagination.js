/**
 * Pages à afficher dans une pagination : la première, la dernière et `span` pages de chaque
 * côté de la page courante ; un trou entre deux numéros non consécutifs devient « gap-N ».
 *   pageWindow(6, 90) -> [1, 'gap-4', 4, 5, 6, 7, 8, 'gap-90', 90]
 * @param {number} current page courante (1..last)
 * @param {number} last dernière page
 * @param {number} [span] pages de chaque côté de la page courante
 * @returns {Array<number|string>}
 */
export function pageWindow(current, last, span = 2) {
    if (!(last >= 1)) return [];
    const set = new Set([1, last]);
    for (let p = current - span; p <= current + span; p++) if (p >= 1 && p <= last) set.add(p);
    const sorted = [...set].sort((a, b) => a - b);
    const out = [];
    sorted.forEach((p, i) => {
        if (i && p - sorted[i - 1] > 1) out.push(`gap-${p}`);
        out.push(p);
    });
    return out;
}
