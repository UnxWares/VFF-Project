// Utilitaires de la page « Chemins de fer touristiques et vélorails » (Pages/Network/TouristRailways.svelte) :
// filtres, tri et mise en forme, sans accès au DOM. Les données viennent de App\Services\TouristRailways.

/** Minuscules sans accents ni ponctuation, pour la recherche par nom. */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/**
 * Exploitations qui répondent aux filtres : recherche dans le nom, l'exploitant, les lignes et les départements ;
 * type ; région.
 *
 * @param {Array<any>} railways
 * @param {{ q?: string, type?: string, region?: string }} filters
 */
export function filterRailways(railways, { q = '', type = '', region = '' } = {}) {
    const needle = fold(q);
    return railways.filter((r) => {
        if (type && r.type !== type) return false;
        if (region && !r.regions.includes(region)) return false;
        if (!needle) return true;
        const haystack = fold(
            [
                r.name,
                r.operator,
                ...r.departements,
                ...r.regions,
                ...r.lines.map((l) => l.name),
            ].join(' '),
        );
        return needle.split(' ').every((word) => haystack.includes(word));
    });
}

/** Voies vertes qui répondent à la recherche (nom, ligne) et à la région. */
export function filterGreenways(greenways, { q = '', region = '' } = {}) {
    const needle = fold(q);
    return greenways.filter((g) => {
        if (region && !g.regions.includes(region)) return false;
        if (!needle) return true;
        const haystack = fold(`${g.name} ${g.line.name} ${g.regions.join(' ')}`);
        return needle.split(' ').every((word) => haystack.includes(word));
    });
}

/**
 * Copie triée : `name` (alphabétique sans accents), `length` (la plus longue d'abord, longueurs inconnues en dernier),
 * `since` (la plus ancienne d'abord, années inconnues en dernier).
 *
 * @param {Array<any>} railways
 * @param {'name'|'length'|'since'} sort
 */
export function sortRailways(railways, sort = 'name') {
    const byName = (a, b) => fold(a.name).localeCompare(fold(b.name));
    const compare = {
        name: byName,
        length: (a, b) => (b.length_m ?? -1) - (a.length_m ?? -1) || byName(a, b),
        since: (a, b) => (a.start_year ?? Infinity) - (b.start_year ?? Infinity) || byName(a, b),
    }[sort];
    return [...railways].sort(compare ?? byName);
}

/** Nombre d'exploitations par type, dans l'ordre donné. */
export function countByType(railways, types) {
    return Object.fromEntries(types.map((t) => [t, railways.filter((r) => r.type === t).length]));
}

/** Kilomètres arrondis au dixième (null si inconnu). */
export function kmOf(meters) {
    return meters == null ? null : Math.round(meters / 100) / 10;
}

/** Points de la carte : exploitations qui ont des coordonnées, en GeoJSON. */
export function railwayPoints(railways) {
    return {
        type: 'FeatureCollection',
        features: railways
            .filter((r) => r.lat != null && r.lng != null)
            .map((r) => ({
                type: 'Feature',
                properties: { id: r.id, name: r.name, type: r.type },
                geometry: { type: 'Point', coordinates: [r.lng, r.lat] },
            })),
    };
}

/** Emprise [[ouest, sud], [est, nord]] des points, null s'il n'y en a pas. */
export function pointsBounds(collection) {
    const coords = collection.features.map((f) => f.geometry.coordinates);
    if (!coords.length) return null;
    const lngs = coords.map((c) => c[0]);
    const lats = coords.map((c) => c[1]);
    return [
        [Math.min(...lngs), Math.min(...lats)],
        [Math.max(...lngs), Math.max(...lats)],
    ];
}

/** Adresse de la fiche Wikidata d'un identifiant (Q…). */
export function wikidataUrl(qid) {
    return qid ? `https://www.wikidata.org/wiki/${qid}` : null;
}
