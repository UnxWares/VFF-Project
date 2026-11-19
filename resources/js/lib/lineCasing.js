// Liseré foncé sous les tracés pleins de la carte (« casing » MapLibre).
//
// Pourquoi : sur le fond clair d'OpenStreetMap, l'orange du réseau mixte (#ff6500) et le vert
// patrimoine n'atteignent pas 3:1 (critère WCAG 1.4.11), même en 2030 : 1,3 à 3:1 selon les aplats.
// Les couleurs des lignes ne changent pas. Un filet foncé et fin posé de chaque côté du trait donne au
// tracé un bord qui contraste (plus de 5:1 avec tous les aplats du fond, à toutes les époques) et aux
// couleurs claires au moins 3:1 contre ce bord. La mesure est dans tests/js/lineCasing.test.js.
//
// Rendu : une seule couche par source, jamais une par ligne. Le liseré n'est pas un trait plus large
// posé dessous (il assombrirait les tracés translucides de la vue « toutes les époques ») mais un
// anneau : `line-gap-width` laisse vide la largeur du tracé et `line-width` donne l'épaisseur du
// filet de chaque côté. Les couleurs des tracés ne sont donc jamais recouvertes.
//
// Le fond d'OpenStreetMap de /maps est clair dans les deux thèmes (le thème sombre n'assombrit que
// l'interface), le liseré est donc le même en clair et en sombre.
//
// Les tracés en tirets ou en pointillés (lignes fermées, vélorail, déposées, changements « fermée »
// et « supprimée » de la comparaison) n'ont pas de liseré : MapLibre mesure le motif en largeurs du
// trait de la couche, et un liseré d'une autre largeur ne resterait pas aligné sur les tirets.

import { contrastRatio } from './eraTint.js';

/** Brun-noir chaud : même famille que le texte du thème clair, jamais du noir pur. */
export const CASING_COLOR = '#0c0a09';

/**
 * Opacité du liseré. Mesurée : en dessous de 0,85 le vert patrimoine passe sous 3:1 contre le liseré
 * sur les aplats clairs ; 0,9 donne 3:1 au moins aux couleurs claires (orange 5,4:1, vert 4,8:1, bleu
 * du fret 3,9:1, bleu des ouvertures de la comparaison 3,2:1).
 */
export const CASING_OPACITY = 0.9;

/**
 * Épaisseur du filet de chaque côté du tracé (px) selon le zoom : 1 px dès le zoom 12, où les tracés
 * font 4 px. Plus loin, il garde le quart de la largeur du trait (0,25 px au zoom 4, 0,5 px au zoom 8) :
 * sur la France entière, où les tracés se touchent, un filet plus épais ferait un réseau brun.
 */
export const CASING_WIDTH_STOPS = [
    [4, 0.25],
    [8, 0.5],
    [12, 1],
];

/** Expression MapLibre : épaisseur du filet selon le zoom. */
export function casingWidthExpr() {
    return ['interpolate', ['linear'], ['zoom'], ...CASING_WIDTH_STOPS.flat()];
}

/**
 * Peinture d'un liseré.
 *
 * @param {unknown} gapWidth `line-width` du tracé qu'il borde (nombre ou expression), laissé vide
 * @param {{ opacityTransitionMs?: number }} [options]
 */
export function casingPaint(gapWidth, { opacityTransitionMs = 0 } = {}) {
    return {
        'line-color': CASING_COLOR,
        'line-gap-width': gapWidth,
        'line-width': casingWidthExpr(),
        'line-opacity': CASING_OPACITY,
        ...(opacityTransitionMs > 0
            ? { 'line-opacity-transition': { duration: opacityTransitionMs } }
            : {}),
    };
}

/**
 * Couche de liseré d'une source de tracés.
 *
 * @param {{ id: string, source: string, sourceLayer: string, filter?: unknown, sortKey?: unknown,
 *   width: unknown, minzoom?: number | null, opacityTransitionMs?: number }} spec
 */
export function casingLayer({
    id,
    source,
    sourceLayer,
    filter = null,
    sortKey = null,
    width,
    minzoom = null,
    opacityTransitionMs = 0,
}) {
    return {
        id,
        type: 'line',
        source,
        'source-layer': sourceLayer,
        ...(minzoom === null ? {} : { minzoom }),
        ...(filter ? { filter } : {}),
        layout: {
            'line-cap': 'round',
            'line-join': 'round',
            ...(sortKey ? { 'line-sort-key': sortKey } : {}),
        },
        paint: casingPaint(width, { opacityTransitionMs }),
    };
}

const channels = (hex) => [1, 3, 5].map((i) => Number.parseInt(hex.slice(i, i + 2), 16));

/** Couleur du liseré une fois posé sur un fond (mélange à l'opacité du liseré). */
export function casingOver(background) {
    const casing = channels(CASING_COLOR);
    const back = channels(background);
    const mixed = casing.map((c, i) =>
        Math.round(c * CASING_OPACITY + back[i] * (1 - CASING_OPACITY)),
    );
    return `#${mixed.map((c) => c.toString(16).padStart(2, '0')).join('')}`;
}

/**
 * Contrastes effectifs d'un tracé sur un fond : du tracé seul contre le fond, du liseré contre le
 * fond (bord extérieur de l'objet) et du tracé contre son liseré (bord intérieur).
 *
 * @param {string} line couleur du tracé (#rrggbb)
 * @param {string} background couleur du fond (#rrggbb)
 */
export function casedContrast(line, background) {
    const ring = casingOver(background);
    return {
        lineOnBackground: contrastRatio(line, background),
        ringOnBackground: contrastRatio(ring, background),
        lineOnRing: contrastRatio(line, ring),
    };
}
