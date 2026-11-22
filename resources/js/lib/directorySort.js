// Tri des pages Gares et Lignes : sans choix du visiteur, pertinence avec une recherche, nom sans recherche
// (StationController et LineController appliquent la même règle). Un tri choisi reste prioritaire.

/** Tri appliqué quand le visiteur n'en a pas choisi. */
export function defaultSort(q) {
    return q && q.trim() ? 'relevance' : 'name';
}

/** Tri affiché : celui choisi (`chosen`, '' sinon), ou le tri par défaut ; « pertinence » n'existe qu'avec une recherche. */
export function effectiveSort(chosen, q) {
    if (!chosen || (chosen === 'relevance' && defaultSort(q) !== 'relevance'))
        return defaultSort(q);
    return chosen;
}

/** Valeur du paramètre `sort` de l'adresse : vide quand le tri est celui par défaut. */
export function sortParam(chosen, q) {
    const sort = effectiveSort(chosen, q);
    return sort === defaultSort(q) ? '' : sort;
}

/** Options du tri : « pertinence » en tête, seulement avec une recherche. */
export function sortOptions(options, q) {
    return defaultSort(q) === 'relevance'
        ? options
        : options.filter(([value]) => value !== 'relevance');
}
