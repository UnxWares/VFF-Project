// Recherche de tout le site (Components/Search) : logique pure, testée par tests/js/siteSearch.test.js.
// Les résultats viennent de GET /api/v1/search (lignes, gares, matériel roulant, accidents) ; s'y ajoutent les
// rubriques du site, le lien vers la page de résultats complète (/search) et deux liens vers les annuaires filtrés
// (trois quand des accidents répondent).

import { accidentDate } from './accidentText.js';

/** Rubriques du site proposées par la recherche (champ vide, ou nom qui correspond au texte). */
export const SITE_PAGES = [
    { href: '/maps', key: 'nav.maps', icon: 'bi-map' },
    { href: '/lines', key: 'nav.lines', icon: 'bi-bezier' },
    { href: '/stations', key: 'nav.stations', icon: 'bi-pin-map' },
    { href: '/materials', key: 'nav.materials', icon: 'bi-train-front' },
    { href: '/accidents', key: 'nav.accidents', icon: 'bi-journal-text' },
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
 * Date et lieu d'un accident trouvé : « 12 juillet 2013, Brétigny-sur-Orge (91) » ; la date suit sa
 * précision (« juillet 2013 », « 1891 »), le lieu manque quand la commune est inconnue.
 * @param {{ date: string, date_precision: 'day'|'month'|'year', commune?: string|null, department_code?: string|null }} accident
 * @param {string} [lang]
 */
export function accidentDetail(accident, lang = 'fr') {
    const dept = accident.department_code ? `(${accident.department_code})` : null;
    const place = [accident.commune, dept].filter(Boolean).join(' ');
    return [accidentDate(accident.date, accident.date_precision, lang), place]
        .filter(Boolean)
        .join(', ');
}

/**
 * Groupes de résultats, dans l'ordre affiché : rubriques, lignes, gares, matériel roulant, accidents, puis
 * « Tous les résultats » (page de résultats, puis annuaires filtrés, une fois la recherche faite). Les groupes vides sont
 * retirés ; chaque option reçoit un indice continu et un identifiant (aria-activedescendant).
 *
 * @param {object} p
 * @param {string} p.query texte saisi, sans espaces autour
 * @param {boolean} p.suggestions champ vide = toutes les rubriques
 * @param {{ lines: any[], stations: any[], materials: any[], accidents?: any[] }} p.results réponse de l'API
 * @param {boolean} p.searched la réponse correspond au texte saisi (ou l'appel a échoué)
 * @param {(key: string, params?: object) => string} p.t traduction
 * @param {string} p.idPrefix préfixe des identifiants
 * @param {string} [p.lang] langue de la page (nom et date des accidents)
 */
export function searchGroups({ query, suggestions, results, searched, t, idPrefix, lang = 'fr' }) {
    const pages =
        query.length === 0
            ? suggestions
                ? SITE_PAGES
                : []
            : SITE_PAGES.filter((p) => fold(t(p.key)).includes(fold(query)));
    const encoded = encodeURIComponent(query);
    const accidents = results.accidents ?? [];
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
                // Série trouvée par le surnom d'une rame remarquable : la rame plutôt que le constructeur.
                detail: m.notable_unit
                    ? t(
                          m.notable_unit.nickname
                              ? 'materials.notable.unit'
                              : 'materials.notable.unit_plain',
                          {
                              number: m.notable_unit.number,
                              nickname: m.notable_unit.nickname,
                          },
                      )
                    : (m.builder ?? null),
            })),
        },
        {
            type: 'accident',
            label: t('nav.search.accidents'),
            items: accidents.map((a) => ({
                href: `/accidents/${a.slug}`,
                name: a.names?.[lang] ?? a.name,
                icon: 'bi-exclamation-triangle',
                detail: accidentDetail(a, lang),
            })),
        },
        {
            type: 'more',
            label: t('nav.search.more'),
            items:
                searched && query.length >= 2
                    ? [
                          {
                              href: searchPageHref(query),
                              name: t('nav.search.all_results', { query }),
                              icon: 'bi-search',
                          },
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
                          ...(accidents.length
                              ? [
                                    {
                                        href: `/accidents?q=${encoded}`,
                                        name: t('nav.search.all_accidents', { query }),
                                        icon: 'bi-list-ul',
                                    },
                                ]
                              : []),
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

/** Adresse de la page de résultats complète d'une recherche (Entrée dans le champ, « Tous les résultats pour… »). */
export function searchPageHref(query) {
    return `/search?q=${encodeURIComponent(query)}`;
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
