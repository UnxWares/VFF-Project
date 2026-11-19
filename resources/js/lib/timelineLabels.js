// Étiquettes des décennies sur la frise de la carte (Components/Map/TimelineSlider.svelte) : lesquelles
// afficher pour qu'aucune ne touche sa voisine, de 320 à 2 560 px. Logique pure, testée par
// tests/js/timelineLabels.test.js ; les points de la frise restent tous affichés et cliquables.

/**
 * Décalage des étiquettes des deux bouts : celle de la première décennie part 10 px avant son point
 * (alignée à gauche dans un bouton de 28 px), celle de la dernière finit 10 px après le sien.
 */
export const EDGE_OFFSET = 10;

/** Espace minimal entre deux étiquettes, en px. */
export const LABEL_GAP = 10;

/** Largeur supposée d'un caractère tant que l'étiquette n'est pas mesurée (14 à 15 px, chiffres). */
const CHAR_PX = 9;

/**
 * @param {number[]} xs position de chaque point en px, croissante
 * @param {number[]} widths largeur de chaque étiquette en px (0 ou absente : pas encore mesurée)
 * @param {string[]} texts texte de chaque étiquette (largeur estimée tant qu'elle n'est pas mesurée)
 * @param {number} active indice de la décennie choisie (-1 : aucune)
 * @param {number} [gap] espace minimal entre deux étiquettes
 * @returns {boolean[]} pour chaque décennie, son étiquette est-elle affichée
 *
 * Priorités : la décennie choisie, puis la dernière (« Aujourd'hui »), puis la première ; ensuite une
 * étiquette sur k, avec le plus petit pas k qui les laisse toutes séparées (1, 2, 3…), moins celles qui
 * toucheraient une étiquette prioritaire. Le rythme reste régulier : 1900, 1920, 1940…
 */
export function visibleLabels(xs, widths, texts, active, gap = LABEL_GAP) {
    const n = xs.length;
    if (n === 0) return [];
    const width = (i) => (widths[i] > 0 ? widths[i] : String(texts[i] ?? '').length * CHAR_PX);
    const extent = (i) => {
        if (i === 0 && n > 1) return [xs[0] - EDGE_OFFSET, xs[0] - EDGE_OFFSET + width(0)];
        if (i === n - 1 && n > 1) return [xs[i] + EDGE_OFFSET - width(i), xs[i] + EDGE_OFFSET];
        return [xs[i] - width(i) / 2, xs[i] + width(i) / 2];
    };
    const touch = (a, b) => {
        const [a0, a1] = extent(a);
        const [b0, b1] = extent(b);
        return a0 < b1 + gap && b0 < a1 + gap;
    };

    /** @type {number[]} */
    const shown = [];
    const tryShow = (i) => {
        if (i < 0 || i >= n || shown.includes(i) || shown.some((j) => touch(i, j))) return;
        shown.push(i);
    };
    for (const i of [active, n - 1, 0]) tryShow(i);

    for (let k = 1; k <= n; k++) {
        const stride = [];
        for (let i = 0; i < n; i += k) stride.push(i);
        if (stride.every((i, j) => j === 0 || !touch(stride[j - 1], i))) {
            stride.forEach(tryShow);
            break;
        }
    }

    return Array.from({ length: n }, (_, i) => shown.includes(i));
}
