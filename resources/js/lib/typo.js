// Typographie dépendant de la langue : espace avant les deux-points, guillemets, nombres, dates.

/** Espace insécable (U+00A0). */
export const NBSP = '\u00a0';

/** Deux-points avec ses espaces : espace avant en français seulement. */
export function colon(lang) {
    return lang === 'fr' ? `${NBSP}: ` : ': ';
}

/** Point-virgule séparateur : espace insécable avant en français seulement. */
export function semicolon(lang) {
    return lang === 'fr' ? `${NBSP}; ` : '; ';
}

/** Entoure un texte des guillemets de la langue (« x », “x”, „x“). */
export function quoted(text, lang) {
    if (lang === 'en') return `“${text}”`;
    if (lang === 'de') return `„${text}“`;
    return `«${NBSP}${text}${NBSP}»`;
}

/**
 * Locale des formats de date et de nombre : l'anglais du site est britannique
 * (« 26 April 1875 », pas « April 26, 1875 »).
 * @param {string} [lang]
 */
export function formatLocale(lang) {
    return lang === 'en' ? 'en-GB' : lang || 'fr';
}

/**
 * Nombre dans la langue de la page. En français, l'espace fine insécable des milliers
 * (U+202F) n'a presque pas de largeur en Lato : remplacée par une espace insécable.
 * @param {number|string|null|undefined} n
 * @param {string} [lang]
 * @param {Intl.NumberFormatOptions} [opts]
 */
export function formatNumber(n, lang = 'fr', opts = {}) {
    return Number(n ?? 0)
        .toLocaleString(formatLocale(lang), opts)
        .replaceAll('\u202f', NBSP);
}

/** Valeur et unité, jamais séparées en fin de ligne (« 1 435 mm », « 320 km/h »). */
export function withUnit(n, unit, lang = 'fr', opts = {}) {
    return `${formatNumber(n, lang, opts)}${NBSP}${unit}`;
}

/**
 * Lie un nombre à son unité par une espace insécable dans un texte déjà formaté
 * (« 49,8 km », « 320 km/h », « 1,5 kV », « 2 % ») et rend lisible l'espace fine des milliers.
 * @param {string} text
 */
export function tieUnits(text) {
    return String(text ?? '')
        .replaceAll('\u202f', NBSP)
        .replace(/(\d) (km\/h|km|mm|m|kV|V|%)(?![\p{L}\d])/gu, `$1${NBSP}$2`);
}

/**
 * Premier du mois en français : « 1er décembre 1950 » au lieu de « 1 décembre 1950 ».
 * S'applique à une date déjà formatée (texte libre accepté : seules les dates sont touchées).
 * @param {string} text
 * @param {string} [lang]
 */
export function firstOfMonth(text, lang = 'fr') {
    if (lang !== 'fr' || !text) return text;
    return String(text).replace(
        /(^|[\s(])1 (janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)\b/g,
        '$11er $2',
    );
}
