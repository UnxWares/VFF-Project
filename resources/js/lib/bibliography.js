/**
 * Mise en forme d'une référence de la section « Pour aller plus loin » (App\Services\Bibliography\SheetBibliography).
 * Les données viennent des notices : auteurs, titre, éditeur, lieu, année, ISBN, revue.
 */

/** Fournisseurs possibles du lien d'une référence. */
export const SOURCES = ['bnf', 'wikipedia', 'wikidata'];

/**
 * Auteurs d'une référence, trois au plus : « A, B et C » ; au-delà, « A, B, C et al. ».
 * @param {Array<string>} authors
 * @param {string} locale
 */
export function authorLine(authors, locale = 'fr') {
    const names = (authors ?? []).filter(Boolean);
    if (names.length === 0) return '';
    const list = new Intl.ListFormat(locale, { style: 'long', type: 'conjunction' });
    return names.length > 3 ? `${names.slice(0, 3).join(', ')} et al.` : list.format(names);
}

/**
 * Mention d'édition : « Les éditions du Cabri, Breil-sur-Roya, 2025 » (éditeur, lieu, année).
 * Une revue passe devant, introduite par `inJournal`.
 * @param {{ publisher?: string|null, place?: string|null, year?: number|null, journal?: string|null }} item
 * @param {(journal: string) => string} [inJournal]
 */
export function imprintLine(item, inJournal = (j) => j) {
    return [item.journal ? inJournal(item.journal) : null, item.publisher, item.place, item.year]
        .filter((part) => part !== null && part !== undefined && part !== '')
        .join(', ');
}

/** Fournisseur du lien de la référence : `bnf`, `wikipedia` ou `wikidata` (jamais autre chose). */
export function linkKind(item) {
    return SOURCES.includes(item.source) ? item.source : 'wikipedia';
}

/** Un lien n'est servi que vers http(s) : jamais un `javascript:` venu d'une base. */
export function safeUrl(url) {
    return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null;
}

/**
 * Date de récupération la plus ancienne des notices de la BnF (la mention de source la demande), ou null.
 * @param {Array<{ source: string, fetched_on?: string|null }>} items
 */
export function oldestBnfDate(items) {
    const dates = (items ?? [])
        .filter((item) => item.source === 'bnf' && item.fetched_on)
        .map((item) => item.fetched_on)
        .sort();
    return dates[0] ?? null;
}
