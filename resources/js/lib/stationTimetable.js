/**
 * Desserte d'une gare un jour ouvrable type (StationTimetable.svelte) : mise en forme pure, sans DOM.
 */

/** Ordre d'affichage des types de train. */
export const TYPE_ORDER = ['tgv', 'intercites', 'ter', 'transilien', 'other'];

/**
 * Types présents, dans l'ordre d'affichage, avec leur nombre de trains.
 * @param {Record<string, number>|null|undefined} byType
 * @returns {Array<{ type: string, trains: number }>}
 */
export function typeCounts(byType) {
    const counts = byType ?? {};
    return TYPE_ORDER.filter((type) => (counts[type] ?? 0) > 0).map((type) => ({
        type,
        trains: counts[type],
    }));
}

/**
 * Lieux à nommer pour un sens : destinations des trains qui repartent, à défaut provenances (terminus).
 * @param {{ destinations?: Array<{ name: string, slug: string|null, trains: number }>, origins?: Array<{ name: string, slug: string|null, trains: number }> }} direction
 * @returns {{ places: Array<{ name: string, slug: string|null, trains: number }>, arrivals: boolean }}
 */
export function directionPlaces(direction) {
    const destinations = direction?.destinations ?? [];
    if (destinations.length > 0) return { places: destinations, arrivals: false };
    return { places: direction?.origins ?? [], arrivals: true };
}

/**
 * Date « AAAA-MM-JJ » en toutes lettres, dans la langue de la page.
 * @param {string|null|undefined} iso
 * @param {string} lang
 * @returns {string}
 */
export function longDate(iso, lang) {
    if (typeof iso !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString(lang === 'en' ? 'en-GB' : lang, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

/**
 * Flux d'horaires à citer : celui qui porte le plus de trains de la gare d'abord (l'ordre reçu, le premier est le principal).
 * @param {Array<{ feed: string, version: string|null, valid_to: string|null, page: string|null, licence?: string|null }>|null|undefined} feeds
 */
export function mainFeed(feeds) {
    return (feeds ?? [])[0] ?? null;
}

/**
 * Lieux d'un sens en phrase (« A, B et C ») : morceaux de texte, chaque lieu gardant sa gare pour en faire un lien.
 * @param {Array<{ name: string, slug: string|null }>} places
 * @param {string} lang
 * @returns {Array<{ text: string, place: { name: string, slug: string|null }|null }>}
 */
export function placeParts(places, lang) {
    if (!places.length) return [];
    const parts = new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }).formatToParts(
        places.map((p) => p.name),
    );
    let index = 0;
    return parts.map((part) => ({
        text: part.value,
        place: part.type === 'element' ? places[index++] : null,
    }));
}
