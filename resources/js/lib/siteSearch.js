// Recherche de tout le site (Components/Search) : logique pure, testée par tests/js/siteSearch.test.js.
// Les résultats viennent de GET /api/v1/search (lignes, gares, matériel roulant) ; s'y ajoutent les
// rubriques du site et deux liens vers les annuaires filtrés.

/** Rubriques du site proposées par la recherche (champ vide, ou nom qui correspond au texte). */
export const SITE_PAGES = [
    { href: '/maps', key: 'nav.maps', icon: 'bi-map' },
    { href: '/lines', key: 'nav.lines', icon: 'bi-bezier' },
    { href: '/stations', key: 'nav.stations', icon: 'bi-pin-map' },
    { href: '/materials', key: 'nav.materials', icon: 'bi-train-front' },
    { href: '/api', key: 'nav.api', icon: 'bi-code-slash' },
    { href: '/contribute', key: 'nav.contribute', icon: 'bi-pencil-square' },
    { href: '/whoarewe', key: 'nav.whoarewe', icon: 'bi-people' },
    { href: '/donate', key: 'nav.donate', icon: 'bi-heart' },
];

/** Texte sans casse ni accents : « gare » trouve « Gares », « materiel » « Matériel roulant ». */
export function fold(text) {
    return String(text)
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

/** Ville et département d'une gare, sans répéter la ville quand elle porte le nom de la gare. */
export function stationDetail(station) {
    const place = station.city && station.city !== station.name ? station.city : null;
    const dept = station.department_code ? `(${station.department_code})` : null;
    return [place, dept].filter(Boolean).join(' ') || null;
}

/**
 * Groupes de résultats, dans l'ordre affiché : rubriques, lignes, gares, matériel roulant, puis
 * « Tous les résultats » (annuaires filtrés, une fois la recherche faite). Les groupes vides sont
 * retirés ; chaque option reçoit un indice continu et un identifiant (aria-activedescendant).
 *
 * @param {object} p
 * @param {string} p.query texte saisi, sans espaces autour
 * @param {boolean} p.suggestions champ vide = toutes les rubriques
 * @param {{ lines: any[], stations: any[], materials: any[] }} p.results réponse de l'API
 * @param {boolean} p.searched la réponse correspond au texte saisi (ou l'appel a échoué)
 * @param {(key: string, params?: object) => string} p.t traduction
 * @param {string} p.idPrefix préfixe des identifiants
 */
export function searchGroups({ query, suggestions, results, searched, t, idPrefix }) {
    const pages =
        query.length === 0
            ? suggestions
                ? SITE_PAGES
                : []
            : SITE_PAGES.filter((p) => fold(t(p.key)).includes(fold(query)));
    const encoded = encodeURIComponent(query);
    const groups = [
        {
            type: 'page',
            label: t('nav.search.sections'),
            items: pages.map((p) => ({ href: p.href, name: t(p.key), icon: p.icon })),
        },
        {
            type: 'line',
            label: t('nav.search.lines'),
            items: results.lines.map((l) => ({
                href: `/lines/${l.slug}`,
                name: l.name,
                icon: 'bi-bezier',
                plate: l.code ?? null,
            })),
        },
        {
            type: 'station',
            label: t('nav.search.stations'),
            items: results.stations.map((s) => ({
                href: `/stations/${s.slug}`,
                name: s.name,
                icon: 'bi-pin-map-fill',
                detail: stationDetail(s),
            })),
        },
        {
            type: 'material',
            label: t('nav.search.materials'),
            items: results.materials.map((m) => ({
                href: `/materials/${m.slug}`,
                name: m.name,
                icon: 'bi-train-front-fill',
                detail: m.builder ?? null,
            })),
        },
        {
            type: 'more',
            label: t('nav.search.more'),
            items:
                searched && query.length >= 2
                    ? [
                          {
                              href: `/lines?q=${encoded}`,
                              name: t('nav.search.all_lines', { query }),
                              icon: 'bi-list-ul',
                          },
                          {
                              href: `/stations?q=${encoded}`,
                              name: t('nav.search.all_stations', { query }),
                              icon: 'bi-list-ul',
                          },
                      ]
                    : [],
        },
    ].filter((g) => g.items.length);

    let index = 0;
    return groups.map((g) => ({
        ...g,
        id: `${idPrefix}-group-${g.type}`,
        items: g.items.map((item) => ({ ...item, index, id: `${idPrefix}-opt-${index++}` })),
    }));
}

/** Option suivante ou précédente, en boucle ; sans option active, la première (ou la dernière). */
export function stepIndex(active, delta, length) {
    if (length === 0) return -1;
    if (active < 0) return delta > 0 ? 0 : length - 1;
    return (active + delta + length) % length;
}

/** Ctrl K (Cmd K sur Mac) : le raccourci de la recherche, sans Alt ni Maj. */
export function isSearchShortcut(event) {
    return (
        (event.ctrlKey || event.metaKey) &&
        !event.altKey &&
        !event.shiftKey &&
        event.key.toLowerCase() === 'k'
    );
}

/** Raccourci affiché selon le système (Cmd sur Mac, iPhone et iPad, Ctrl ailleurs). */
export function shortcutLabel(userAgent = globalThis.navigator?.userAgent ?? '') {
    return /Mac|iPhone|iPad/.test(userAgent) ? '⌘ K' : 'Ctrl K';
}
