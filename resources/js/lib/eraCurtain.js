/**
 * Rideau de comparaison de deux époques sur /maps : une seule carte MapLibre, l'époque la plus
 * ancienne à gauche du rideau, la plus récente à droite. Logique pure (adresse, position, boîtes de
 * découpe WebGL, copies des couches), testée dans tests/js/eraCurtain.test.js.
 *
 * Principe : les couches de VFF de l'époque de gauche restent celles de la carte simple ; leurs
 * copies (« jumelles ») lisent une seconde source de tuiles, celle de l'époque de droite. Trois
 * couches personnalisées sans dessin encadrent les deux groupes et ne font que régler le découpage
 * WebGL (scissor) : à gauche du rideau pour le premier groupe, à droite pour le second, puis plus
 * rien pour la suite (libellés du fond, accidents, itinéraire, qui couvrent toute la carte).
 */

/** Présentations de la vue « Comparer » : rideau (défaut) ou carte des différences. */
export const COMPARE_MODES = ['curtain', 'changes'];
export const DEFAULT_COMPARE_MODE = 'curtain';

/** Position du rideau en pour cent de la largeur de la carte (0 = bord gauche). */
export const CURTAIN_MIN = 0;
export const CURTAIN_MAX = 100;
/** @type {number} */
export const CURTAIN_DEFAULT = 50;
/** Pas des flèches du clavier (1 %) et des touches Page précédente / suivante (10 %). */
export const CURTAIN_STEP = 1;
export const CURTAIN_PAGE_STEP = 10;

/** Suffixe des couches et des sources jumelles (époque de droite). */
export const TWIN_SUFFIX = '-right';
/** Couches personnalisées du découpage, dans l'ordre de la pile. */
export const CLIP_LEFT_ID = 'vff-curtain-clip-left';
export const CLIP_RIGHT_ID = 'vff-curtain-clip-right';
export const CLIP_END_ID = 'vff-curtain-clip-end';

/** Identifiant de la copie d'une couche ou d'une source pour l'époque de droite. */
export const twinId = (id) => `${id}${TWIN_SUFFIX}`;

/**
 * Position lue dans une valeur d'adresse : entier de 0 à 100, ramené dans ces bornes ; null si
 * absente ou mal formée.
 * @param {string | null} raw
 * @returns {number | null}
 */
export function parseCurtain(raw) {
    if (typeof raw !== 'string' || !/^\s*\d+(\.\d+)?\s*$/.test(raw)) return null;
    return clampCurtain(Number(raw));
}

/** Position bornée et arrondie à l'entier (l'adresse et le curseur parlent en pour cent entiers). */
export function clampCurtain(value) {
    if (!Number.isFinite(value)) return CURTAIN_DEFAULT;
    return Math.min(CURTAIN_MAX, Math.max(CURTAIN_MIN, Math.round(value)));
}

/**
 * Présentation et position du rideau demandées par l'adresse : `?show=changes` pour la carte des
 * différences, sinon le rideau, à `?curtain=30` (pour cent) ou au milieu.
 * @param {string} search
 * @returns {{ mode: 'curtain' | 'changes', curtain: number }}
 */
export function readCompareParams(search) {
    const params = new URLSearchParams(search);
    const mode = params.get('show') === 'changes' ? 'changes' : DEFAULT_COMPARE_MODE;
    return { mode, curtain: parseCurtain(params.get('curtain')) ?? CURTAIN_DEFAULT };
}

/**
 * Écrit la présentation de la comparaison dans l'adresse (les autres paramètres sont gardés).
 * `state` null (autre vue que « Comparer ») retire `show` et `curtain`. Le rideau au milieu
 * n'est pas écrit : `?era=1930&compare=2030` suffit.
 * @param {string} href
 * @param {{ mode: string, curtain: number } | null} state
 * @returns {string}
 */
export function writeCompareParams(href, state) {
    const url = new URL(href);
    url.searchParams.delete('show');
    url.searchParams.delete('curtain');
    if (state?.mode === 'changes') {
        url.searchParams.set('show', 'changes');
    } else if (state) {
        const pos = clampCurtain(state.curtain);
        if (pos !== CURTAIN_DEFAULT) url.searchParams.set('curtain', String(pos));
    }
    return url.toString();
}

/**
 * Boîte de découpe WebGL d'un côté du rideau, en pixels du tampon de dessin (origine en bas à
 * gauche, comme `gl.scissor`).
 * @param {'left' | 'right'} side
 * @param {number} percent position du rideau
 * @param {number} width largeur du tampon (`gl.drawingBufferWidth`)
 * @param {number} height hauteur du tampon
 * @returns {[number, number, number, number]} x, y, largeur, hauteur
 */
export function clipBox(side, percent, width, height) {
    const x = Math.round((clampCurtain(percent) / 100) * width);
    return side === 'left' ? [0, 0, x, height] : [x, 0, width - x, height];
}

/**
 * Côté du rideau sous un point de la carte (pixels CSS depuis le bord gauche) : un clic à gauche
 * concerne l'époque de gauche, un clic à droite l'époque de droite.
 * @param {number} x
 * @param {number} percent
 * @param {number} width largeur de la carte en pixels CSS
 * @returns {'left' | 'right'}
 */
export function sideAt(x, percent, width) {
    return x < (clampCurtain(percent) / 100) * width ? 'left' : 'right';
}

/**
 * Copie d'une couche de VFF pour l'époque de droite : même dessin, même filtre, source jumelle.
 * Les libellés de la copie ne se disputent pas la place avec ceux de gauche (le même nom de gare
 * est souvent aux deux époques, au même endroit) : chacun reste visible de son côté.
 * @param {Record<string, any>} layer couche telle que `map.getStyle()` la rend
 * @returns {any} spécification à passer à `map.addLayer`
 */
export function twinLayer(layer) {
    const copy = structuredClone(layer);
    copy.id = twinId(layer.id);
    if (typeof layer.source === 'string') copy.source = twinId(layer.source);
    if (layer.type === 'symbol') {
        copy.layout = { ...copy.layout, 'text-allow-overlap': true, 'text-ignore-placement': true };
    }
    // Les copies ne sont visibles qu'avec le rideau : elles naissent visibles, le reste suit.
    if (copy.layout?.visibility) copy.layout = { ...copy.layout, visibility: 'visible' };
    return copy;
}

/**
 * Couche personnalisée MapLibre qui ne dessine rien : elle règle le découpage pour les couches
 * qui la suivent. `box(gl)` rend la boîte, ou null pour couper le découpage.
 * @param {string} id
 * @param {(gl: WebGLRenderingContext) => [number, number, number, number] | null} box
 * @returns {import('maplibre-gl').CustomLayerInterface}
 */
export function clipLayer(id, box) {
    return {
        id,
        type: 'custom',
        renderingMode: '2d',
        render(gl) {
            const b = box(gl);
            if (!b) {
                gl.disable(gl.SCISSOR_TEST);
                return;
            }
            gl.enable(gl.SCISSOR_TEST);
            gl.scissor(b[0], b[1], b[2], b[3]);
        },
    };
}

/**
 * Libellé visible d'une époque à côté du rideau : il ne s'affiche que s'il tient entre le rideau
 * et le bord de la carte (marge comprise) ; sinon il disparaît (la légende et le curseur le disent).
 * @param {'left' | 'right'} side
 * @param {number} percent
 * @param {number} width largeur de la carte (px)
 * @param {number} tagWidth largeur du libellé (px)
 * @param {number} gap écart entre le rideau, le libellé et le bord (px)
 * @param {number} [leftInset] place prise à gauche par un panneau (la légende ouverte), en px
 */
export function tagFits(side, percent, width, tagWidth, gap, leftInset = 0) {
    const x = (clampCurtain(percent) / 100) * width;
    const room = side === 'left' ? x - leftInset : width - x;
    return room >= tagWidth + 2 * gap;
}
