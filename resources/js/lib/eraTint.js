// Teinte d'époque du fond de carte (/maps, /embed/maps) : plus l'époque affichée est ancienne, plus
// le fond OpenStreetMap pâlit et se réchauffe, jusqu'à un sépia discret vers 1830-1860. Logique pure,
// testée par tests/js/eraTint.test.js.
//
// Rendu : propriétés de peinture raster de MapLibre sur la couche `osm` (saturation, luminosité
// minimale) et un voile couleur papier (couche `background` posée juste au-dessus du fond, sous les
// fonds historiques, OpenRailwayMap et les tracés). Sur un fond vectoriel (clé MapTiler), seul le
// voile s'applique, sous les libellés du fond.
//
// Les tracés sont tous plus sombres que les aplats d'OpenStreetMap : la teinte ne fait donc
// qu'ÉCLAIRCIR le fond (luminosité minimale relevée, voile clair), pour que le contraste des lignes
// ne baisse jamais. Le contraste du raster (`raster-contrast`) n'est pas employé : le baisser
// assombrirait les aplats clairs.
//
// Neutre (aucune teinte) : réseau d'aujourd'hui, réglage coupé par le visiteur, contraste renforcé
// demandé par le système, comparaison de deux époques et vue « toutes les époques » (deux époques ou
// plus à l'écran, et leurs couleurs propres), fond historique de l'IGN affiché (rendu d'origine).

/**
 * @typedef {object} TintSettings
 * @property {number} saturation `raster-saturation` (-1 à 1)
 * @property {number} contrast `raster-contrast` (-1 à 1)
 * @property {number} hueRotate `raster-hue-rotate` (degrés)
 * @property {number} brightnessMin `raster-brightness-min` (0 à 1)
 * @property {number} brightnessMax `raster-brightness-max` (0 à 1)
 * @property {number} wash opacité du voile couleur papier (0 à 1)
 */

/**
 * Réglages neutres : le fond tel qu'OpenStreetMap le dessine.
 * @type {Readonly<TintSettings>}
 */
export const NEUTRAL_TINT = Object.freeze({
    saturation: 0,
    contrast: 0,
    hueRotate: 0,
    brightnessMin: 0,
    brightnessMax: 1,
    wash: 0,
});

/** Couleur du voile : papier ancien, clair et chaud. */
export const WASH_COLOR = '#f9eed8';

/**
 * Réglages à pleine force (époques les plus anciennes).
 * @type {Readonly<TintSettings>}
 */
const FULL_TINT = Object.freeze({
    saturation: -0.55,
    contrast: 0,
    hueRotate: 0,
    brightnessMin: 0.15,
    brightnessMax: 1,
    wash: 0.32,
});

/**
 * Force de la teinte selon l'année (0 = aucune, 1 = sépia complet), interpolée entre ces repères :
 * rien aujourd'hui, une teinte légère vers 1950, le sépia de 1860 à 1830.
 */
export const TINT_STOPS = Object.freeze([
    [1860, 1],
    [1900, 0.7],
    [1950, 0.4],
    [1990, 0.15],
    [2030, 0],
]);

/** Paramètre d'adresse qui coupe la teinte (`?tint=off`). */
export const TINT_PARAM = 'tint';
const OFF_VALUES = ['off', '0', 'false', 'no'];

/**
 * Force de la teinte pour une année.
 * @param {number} year décennie affichée
 * @param {number} today décennie du réseau d'aujourd'hui (aucune teinte à partir d'elle)
 */
export function tintStrength(year, today = 2030) {
    if (!Number.isFinite(year) || year >= today) return 0;
    const [firstYear, firstValue] = TINT_STOPS[0];
    if (year <= firstYear) return firstValue;
    for (let i = 1; i < TINT_STOPS.length; i++) {
        const [y1, v1] = TINT_STOPS[i];
        if (year <= y1) {
            const [y0, v0] = TINT_STOPS[i - 1];
            return v0 + ((v1 - v0) * (year - y0)) / (y1 - y0);
        }
    }
    return 0;
}

/**
 * Réglages de teinte pour une ère.
 * @param {number} year décennie affichée
 * @param {{ enabled?: boolean, view?: string, today?: number }} [options]
 *   enabled : teinte voulue (réglage du visiteur, contraste renforcé, fond historique) ;
 *   view : 'era' (une décennie) ; 'compare' et 'stack' sont neutres ;
 *   today : décennie du réseau d'aujourd'hui.
 * @returns {TintSettings}
 */
export function eraTint(year, { enabled = true, view = 'era', today = 2030 } = {}) {
    const s = enabled && view === 'era' ? tintStrength(year, today) : 0;
    if (s === 0) return { ...NEUTRAL_TINT };
    /** @param {keyof TintSettings} key */
    const mix = (key) => NEUTRAL_TINT[key] + (FULL_TINT[key] - NEUTRAL_TINT[key]) * s;
    return {
        saturation: mix('saturation'),
        contrast: mix('contrast'),
        hueRotate: mix('hueRotate'),
        brightnessMin: mix('brightnessMin'),
        brightnessMax: mix('brightnessMax'),
        wash: mix('wash'),
    };
}

/**
 * Propriétés de peinture MapLibre de la couche raster du fond.
 * @param {TintSettings} tint
 */
export function rasterTintPaint(tint) {
    return {
        'raster-saturation': tint.saturation,
        'raster-contrast': tint.contrast,
        'raster-hue-rotate': tint.hueRotate,
        'raster-brightness-min': tint.brightnessMin,
        'raster-brightness-max': tint.brightnessMax,
    };
}

/** La teinte est-elle demandée par l'adresse ? (absente : oui) */
export function readTint(search) {
    const value = new URLSearchParams(search).get(TINT_PARAM);
    return value === null || !OFF_VALUES.includes(value.trim().toLowerCase());
}

/** Adresse avec le réglage de teinte (`?tint=off` seulement quand elle est coupée). */
export function writeTint(href, on) {
    const url = new URL(href);
    if (on) url.searchParams.delete(TINT_PARAM);
    else url.searchParams.set(TINT_PARAM, 'off');
    return url.toString();
}

// ── Mesure du contraste (tests et rapport) ──────────────────────────────────

const toRgb = (hex) => {
    const h = hex.replace('#', '');
    return [0, 2, 4].map((i) => Number.parseInt(h.slice(i, i + 2), 16) / 255);
};
const toHex = (rgb) =>
    `#${rgb
        .map((c) =>
            Math.round(Math.min(1, Math.max(0, c)) * 255)
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`;

const saturationFactor = (s) => (s > 0 ? 1 - 1 / (1.001 - s) : -s);
const contrastFactor = (c) => (c > 0 ? 1 / (1 - c) : 1 + c);

/**
 * Couleur d'un aplat du fond après la teinte, calculée comme le nuanceur raster de MapLibre
 * (saturation, contraste, luminosité ; rotation de teinte non employée) puis recouverte du voile.
 * @param {string} hex couleur d'origine (#rrggbb)
 * @param {TintSettings} tint
 */
export function tintedColor(hex, tint) {
    let rgb = toRgb(hex);
    const average = (rgb[0] + rgb[1] + rgb[2]) / 3;
    const sat = saturationFactor(tint.saturation);
    const con = contrastFactor(tint.contrast);
    rgb = rgb.map((c) => c + (average - c) * sat);
    rgb = rgb.map((c) => (c - 0.5) * con + 0.5);
    rgb = rgb.map((c) => tint.brightnessMin + (tint.brightnessMax - tint.brightnessMin) * c);
    const wash = toRgb(WASH_COLOR);
    rgb = rgb.map((c, i) => c * (1 - tint.wash) + wash[i] * tint.wash);
    return toHex(rgb);
}

const luminance = (hex) => {
    const [r, g, b] = toRgb(hex).map((c) =>
        c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4,
    );
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/** Rapport de contraste WCAG entre deux couleurs #rrggbb. */
export function contrastRatio(a, b) {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}
