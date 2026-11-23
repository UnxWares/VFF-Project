/**
 * Matériel préservé : textes et filtres de la section d'une fiche de série et de la page /materials/preserved.
 * Les données viennent de App\Services\Materials\PreservedRollingStock (mêmes valeurs que l'API REST).
 */

/** Texte sans accent ni capitale, pour comparer une recherche et un nom. */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

/**
 * Date d'une notice Palissy (AAAA-MM-JJ) écrite dans la langue de la page ; la date est lue en UTC pour ne pas glisser
 * d'un jour selon le fuseau.
 *
 * @param {string} iso
 * @param {string} lang
 */
export function longDate(iso, lang) {
    const d = new Date(`${iso}T00:00:00Z`);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString(lang, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC',
    });
}

/**
 * Phrase de protection d'un exemplaire : « Classé monument historique (objet) le 27 mars 1990 », l'année seule quand
 * Palissy ne donne pas le jour, rien quand la nature est inconnue.
 *
 * @param {{ kind: string|null, date: string|null, year: number|null }|null|undefined} protection
 * @param {(key: string, opts?: Record<string, unknown>) => string} t clés `materials_preserved.protection.*`
 * @param {string} lang
 * @returns {string|null}
 */
export function protectionText(protection, t, lang) {
    if (!protection?.kind) return null;
    const kind = protection.kind;
    if (protection.date) {
        const key = `materials_preserved.protection.${kind}_date`;
        if (kind === 'declassified' || kind === 'classified' || kind === 'listed') {
            return t(key, { date: longDate(protection.date, lang) });
        }
    }
    if (protection.year && kind !== 'declassified') {
        return t(`materials_preserved.protection.${kind}_year`, { year: protection.year });
    }
    return t(`materials_preserved.protection.${kind}`);
}

/** Une pièce est protégée si une notice la dit classée ou inscrite (pas déclassée). */
export function isProtected(piece) {
    const kind = piece?.protection?.kind;
    return kind === 'classified' || kind === 'listed';
}

/**
 * Lieux qui répondent aux filtres : recherche (nom du lieu, commune, numéro ou série d'une pièce), type de lieu,
 * pièces protégées seulement. Un lieu garde ses pièces qui répondent à la recherche seulement quand la recherche ne
 * porte pas sur le lieu lui-même ; `count` reste le nombre de pièces gardées.
 *
 * @param {Array<any>} venues
 * @param {{ q?: string, type?: string, protectedOnly?: boolean }} f
 */
export function filterVenues(venues, f = {}) {
    const needle = fold(f.q ?? '').trim();
    const out = [];
    for (const venue of venues) {
        if (f.type && venue.type !== f.type) continue;
        const venueHit =
            needle !== '' && fold(`${venue.name} ${venue.commune ?? ''}`).includes(needle);
        let pieces = venue.pieces;
        if (f.protectedOnly) pieces = pieces.filter(isProtected);
        if (needle !== '' && !venueHit) {
            pieces = pieces.filter((p) =>
                fold(
                    `${p.number ?? ''} ${p.title ?? ''} ${(p.series ?? []).map((s) => s.name).join(' ')}`,
                ).includes(needle),
            );
        }
        if (pieces.length === 0) continue;
        out.push({ ...venue, pieces, count: pieces.length });
    }
    return out;
}

/** Nombre de lieux par type, pour les puces du filtre. */
export function countByType(venues, types) {
    const counts = Object.fromEntries(types.map((t) => [t, 0]));
    for (const v of venues) counts[v.type] = (counts[v.type] ?? 0) + 1;
    return counts;
}
