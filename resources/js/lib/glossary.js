// Glossaire : filtrage et regroupement des termes de la page /glossary, et repérage des sigles dans un texte
// des fiches (GlossaryText). Fonctions pures, sans accès au DOM.

/** Texte sans accents ni casse, pour comparer « ere » à « Ère ». */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase();
}

/**
 * Un terme répond-il à la recherche ? Le nom, le développé du sigle et la définition sont lus sans tenir
 * compte des accents ni de la casse ; une recherche vide garde tout.
 * @param {{ name: string, full?: string|null, definition: string }} term
 * @param {string} query
 */
export function matchesTerm(term, query) {
    const needle = fold(query).trim();
    if (!needle) return true;
    return fold(`${term.name} ${term.full ?? ''} ${term.definition}`).includes(needle);
}

/**
 * Termes regroupés pour la page : par thème (dans l'ordre des thèmes) ou par lettre (les termes arrivent
 * déjà classés du A au Z). Les groupes vides n'existent pas.
 * @template {{ theme: string, letter: string }} T
 * @param {T[]} terms
 * @param {'theme'|'alpha'} view
 * @param {string[]} themes
 * @returns {Array<{ key: string, terms: T[] }>}
 */
export function groupTerms(terms, view, themes) {
    if (view === 'theme') {
        return themes
            .map((key) => ({ key, terms: terms.filter((term) => term.theme === key) }))
            .filter((group) => group.terms.length > 0);
    }
    /** @type {Array<{ key: string, terms: T[] }>} */
    const groups = [];
    for (const term of terms) {
        const last = groups[groups.length - 1];
        if (last && last.key === term.letter) last.terms.push(term);
        else groups.push({ key: term.letter, terms: [term] });
    }
    return groups;
}

/** Clé d'ancre d'un sigle : « GSM-R » devient « gsm-r » (celle du terme dans le glossaire). */
export const abbrSlug = (sigle) => sigle.toLowerCase();

/**
 * Découpe un texte en morceaux : du texte simple, et la première occurrence de chaque sigle connu
 * (`{ abbr: 'BAL' }`). Un sigle est repéré en majuscules, hors d'un mot (« KVB-P » compte comme KVB, « GSM-R »
 * est lu en entier avant « GSM »).
 * @param {string} text
 * @param {string[]} sigles
 * @returns {Array<{ text: string } | { abbr: string }>}
 */
export function splitAbbreviations(text, sigles) {
    const value = String(text ?? '');
    if (!value || sigles.length === 0) return [{ text: value }];
    const escaped = [...sigles]
        .sort((a, b) => b.length - a.length)
        .map((s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const pattern = new RegExp(
        `(?<![\\p{L}\\p{N}_-])(${escaped.join('|')})(?![\\p{L}\\p{N}])`,
        'gu',
    );
    /** @type {Array<{ text: string } | { abbr: string }>} */
    const parts = [];
    const seen = new Set();
    let last = 0;
    for (const match of value.matchAll(pattern)) {
        const sigle = match[1];
        if (seen.has(sigle)) continue;
        seen.add(sigle);
        if (match.index > last) parts.push({ text: value.slice(last, match.index) });
        parts.push({ abbr: sigle });
        last = match.index + sigle.length;
    }
    if (last < value.length) parts.push({ text: value.slice(last) });
    return parts;
}
