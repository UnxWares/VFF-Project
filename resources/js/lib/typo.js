// Typographie dépendant de la langue : espace avant les deux-points, guillemets.

/** Deux-points avec ses espaces : espace avant en français seulement. */
export function colon(lang) {
    return lang === 'fr' ? ' : ' : ': ';
}

/** Entoure un texte des guillemets de la langue (« x », “x”, „x“). */
export function quoted(text, lang) {
    if (lang === 'en') return `“${text}”`;
    if (lang === 'de') return `„${text}“`;
    return `« ${text} »`;
}
