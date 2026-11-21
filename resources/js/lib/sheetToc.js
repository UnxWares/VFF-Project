// Sommaire collant des fiches (Components/Sheet/SheetToc.svelte) : logique pure, testée par
// tests/js/sheetToc.test.js.

/**
 * Section lue : la dernière dont le haut est passé au-dessus de la ligne de lecture. Arrivé en bas
 * de la page, la dernière section visible devient courante (une dernière section courte n'atteint
 * jamais la ligne de lecture). Avant la première section : aucune.
 *
 * @param {Array<{ id: string, top: number }>} sections haut de chaque section dans la fenêtre, dans
 *   l'ordre de la page
 * @param {{ line: number, atEnd: boolean, viewportHeight: number }} view
 * @returns {string | null}
 */
export function currentSection(sections, { line, atEnd, viewportHeight }) {
    let found = null;
    for (const { id, top } of sections) {
        if (top <= line || (atEnd && top < viewportHeight)) found = id;
    }
    return found;
}

/**
 * Défilement horizontal qui rend un arrêt visible dans la rangée du sommaire (téléphone), ou null
 * s'il l'est déjà.
 *
 * @param {{ left: number, width: number }} item position de l'arrêt dans la rangée
 * @param {{ scrollLeft: number, clientWidth: number }} row
 * @param {number} margin marge gardée de chaque côté
 * @returns {number | null}
 */
export function revealScrollLeft(item, row, margin = 24) {
    const start = item.left - margin;
    const end = item.left + item.width + margin;
    if (start >= row.scrollLeft && end <= row.scrollLeft + row.clientWidth) return null;
    return Math.max(0, start);
}

/**
 * Rangée qui défile : reste-t-il des arrêts cachés avant (à gauche) ou après (à droite) ? Sert aux
 * fondus qui l'indiquent. Une tolérance d'un pixel absorbe les arrondis du zoom.
 *
 * @param {{ scrollLeft: number, scrollWidth: number, clientWidth: number }} row
 * @returns {{ before: boolean, after: boolean }}
 */
export function hiddenEdges({ scrollLeft, scrollWidth, clientWidth }) {
    const left = Math.abs(scrollLeft);
    return {
        before: left > 1,
        after: left + clientWidth < scrollWidth - 1,
    };
}
