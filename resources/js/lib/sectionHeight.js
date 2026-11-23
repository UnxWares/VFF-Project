/**
 * Estimation de la hauteur d'une section de fiche (ligne, gare, matériel) avant sa mise en page.
 *
 * Les sections sous la carte sont en `content-visibility: auto` : tant qu'elles sont hors de l'écran,
 * le navigateur ne leur donne que la hauteur de `contain-intrinsic-size`. Une valeur unique (900 px)
 * est fausse de 40 % en médiane (matériel : 27 174 px à 390 px de large), et chaque section qui se
 * déploie décale la barre de défilement et les ancres. Ici la hauteur est estimée d'après le contenu
 * (nombre de caractères, de lignes, de fiches ou de lignes de tableau) et la largeur de l'écran,
 * puis posée en `--vff-section-h` ; `auto` laisse le navigateur retenir la vraie hauteur une fois
 * la section vue.
 *
 * Modèle : hauteur = base + a * caractères + b * éléments (li et tr), un jeu de coefficients par
 * section (identifiant) et par largeur d'écran, interpolé entre les largeurs ajustées. Les
 * coefficients viennent d'un ajustement sur 498 mesures (9 lignes et 3 gares, 3 largeurs, mise en page
 * réelle) ; erreur médiane 4 %, 90e centile 18 % en validation croisée (900 px : 43 % et 92 %).
 * Sections inconnues : jeu `*`, grossier. Aucune lecture de la mise en page : seulement le DOM.
 */

/** Largeurs d'écran (px) où les coefficients ont été ajustés. */
export const WIDTH_NODES = [390, 768, 1280];

/** Par section : [base px, px par caractère, px par élément] à chaque largeur de WIDTH_NODES. */
export const SECTION_MODEL = {
    trace: [
        [613.367, 0.779, 41.154],
        [524.453, 0.474, 46.065],
        [351.816, 0.66, 0],
    ],
    about: [
        [718.808, 0.665, 12.049],
        [418.138, 0.425, 4.628],
        [563.799, 0.082, 30.468],
    ],
    history: [
        [130.799, 0.663, 32.191],
        [122.453, 0.449, 33.544],
        [154.375, 0.328, 19.612],
    ],
    decades: [
        [235.841, 0.476, 27.611],
        [125.173, 0.536, 14.4],
        [115.579, 0.544, 14.11],
    ],
    stations: [
        [241.093, 0.206, 86.467],
        [313.932, 0.061, 61.936],
        [565.711, 0.084, 20.513],
    ],
    tech: [
        [129.332, 0.816, 45.774],
        [154.203, 0.531, 34.679],
        [175.509, 0.513, 14.594],
    ],
    profile: [
        [1360.958, 0.028, 0],
        [1098.731, 0.018, 0],
        [1083.628, 0.007, 0],
    ],
    'level-crossings': [
        [497.128, 0, 0.337],
        [453.803, 0, 0.335],
        [474.309, 0, 0.344],
    ],
    chronicle: [
        [1754.567, 0, 0],
        [1221.124, 0, 0],
        [1188.215, 0, 0],
    ],
    materials: [
        [81.414, 0.759, 296.489],
        [110.652, 0.567, 111.483],
        [299.892, 0.365, 43.931],
    ],
    accidents: [
        [0, 1.355, 9.826],
        [86.687, 0.859, 13.93],
        [102.46, 0.867, 14.726],
    ],
    archives: [
        [120.396, 0.634, 45.788],
        [88.256, 0.438, 31.29],
        [80.627, 0.468, 18.41],
    ],
    gallery: [
        [314.248, 0.513, 57.334],
        [365.692, 0.304, 43.879],
        [370.771, 0.246, 28.758],
    ],
    notes: [
        [107.129, 0, 70.779],
        [233.075, 0, 37.277],
        [194.837, 0, 29.279],
    ],
    eras: [
        [417.084, 0.036, 2.802],
        [316.644, 0.027, 2.157],
        [298.459, 0.017, 1.258],
    ],
    lines: [
        [200.329, 1.063, 0],
        [255.458, 0.007, 100.134],
        [85.6, 0.672, 10.839],
    ],
    location: [
        [383.929, 0.425, 45.202],
        [634.749, 0.059, 39.472],
        [464.536, 0.165, 12.419],
    ],
    sources: [
        [198.151, 1.49, 0],
        [16.232, 1.104, 6.272],
        [79.604, 0.919, 0],
    ],
    '*': [
        [535.222, 0.01, 1.028],
        [483.099, 0.016, 0.129],
        [484.906, 0.019, 0],
    ],
};

/** Hauteur de repli (px) quand rien n'est mesurable. */
export const FALLBACK_HEIGHT = 900;
const MIN_HEIGHT = 120;
const MAX_HEIGHT = 40000;

/**
 * Coefficients d'une section à une largeur : interpolation linéaire entre les largeurs ajustées,
 * valeurs des extrémités au-delà. Sous 390 px, le terme des caractères croît comme 390 / largeur
 * (le texte passe à la ligne plus souvent).
 *
 * @param {string} id
 * @param {number} width largeur de la fenêtre (px)
 * @returns {[number, number, number]}
 */
export function coefficients(id, width) {
    const nodes = SECTION_MODEL[id] ?? SECTION_MODEL['*'];
    const w = Number.isFinite(width) && width > 0 ? width : WIDTH_NODES[0];
    let out;
    if (w <= WIDTH_NODES[0]) out = [...nodes[0]];
    else if (w >= WIDTH_NODES[2]) out = [...nodes[2]];
    else {
        const i = w < WIDTH_NODES[1] ? 0 : 1;
        const t = (w - WIDTH_NODES[i]) / (WIDTH_NODES[i + 1] - WIDTH_NODES[i]);
        out = nodes[i].map((v, k) => v + (nodes[i + 1][k] - v) * t);
    }
    if (w < WIDTH_NODES[0]) out[1] *= WIDTH_NODES[0] / w;
    return /** @type {[number, number, number]} */ (out);
}

/**
 * @param {string|undefined} id identifiant de la section
 * @param {{ chars: number, items: number }} features
 * @param {number} width largeur de la fenêtre (px)
 * @returns {number} hauteur estimée (px, bords compris)
 */
export function estimateHeight(id, features, width) {
    const [base, perChar, perItem] = coefficients(id ?? '*', width);
    const h = base + perChar * features.chars + perItem * features.items;
    return Math.round(Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, h)));
}

/**
 * Taille du contenu d'une section d'après son DOM (aucune lecture de mise en page).
 *
 * @param {Element} el
 * @returns {{ chars: number, items: number }}
 */
export function sectionFeatures(el) {
    return {
        chars: (el.textContent ?? '').replace(/\s+/g, ' ').trim().length,
        items: el.querySelectorAll('li, tr').length,
    };
}
