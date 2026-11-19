/**
 * Textes de remplacement des images (RGAA 1.3.9 : alternative courte et concise ; 1.9 : légende
 * reliée à l'image).
 */

/** Longueur au-delà de laquelle une alternative n'est plus « courte » (repère des audits RGAA). */
export const ALT_MAX = 125;

/**
 * Raccourcit une légende pour l'attribut alt : inchangée si elle tient en `max` caractères, sinon
 * coupée à la fin du dernier mot qui tient, ponctuation finale retirée, suivie de « … ». La légende
 * complète reste lue avec la figure (aria-label de <figure>, texte de <figcaption>).
 * @param {string|null|undefined} text
 * @param {number} [max]
 * @returns {string}
 */
export function conciseAlt(text, max = ALT_MAX) {
    const clean = (text ?? '').replace(/\s+/g, ' ').trim();
    if (clean.length <= max) return clean;
    const cut = clean.slice(0, max);
    const space = cut.lastIndexOf(' ');
    const head = (space > max / 2 ? cut.slice(0, space) : cut).replace(/[\s,;:.(-]+$/u, '');
    return `${head}…`;
}

/**
 * Texte de la légende d'une figure, tel qu'il est lu : morceaux non vides séparés par une virgule.
 * Sert à l'aria-label de <figure role="figure">, qui doit reprendre le contenu du <figcaption>.
 * @param {Array<string|number|null|undefined>} parts
 * @returns {string}
 */
export function figureLabel(parts) {
    return parts
        .map((p) => (p == null ? '' : String(p).replace(/\s+/g, ' ').trim()))
        .filter(Boolean)
        .join(', ');
}
