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

/** Département d'une commune trouvée : « Charente (16) », le code seul sans nom. */
export function communeDetail(commune) {
    if (commune.department_name) return `${commune.department_name} (${commune.department_code})`;
    return commune.department_code ? `(${commune.department_code})` : null;
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

/** PK en kilomètres avec les mètres : « 120,500 » (« 120.500 » en anglais). */
export function pkText(meters, lang = 'fr') {
    return (meters / 1000).toLocaleString(lang, {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

/** Fiche de la ligne à ce PK : le profil en long y place son curseur (paramètre `pk`, en mètres). */
export function positionHref(position) {
    return `/lines/${position.line.slug}?pk=${position.pk_m}#profile`;
}

/** Carte centrée sur le point (absent quand le tracé n'est pas redistribuable et qu'aucune gare n'encadre le PK). */
export function positionMapHref(position) {
    if (position.lat == null || position.lng == null) return null;
    return `/maps?center=${position.lng},${position.lat}&zoom=14`;
}

/**
 * Résultats d'une requête qui est une position sur une ligne (« 570000 PK 120 ») : le texte « Ligne 570000, PK
 * 120,500 : entre Gare A et Gare B (commune) » qui mène à la fiche de ligne à ce PK, et la carte centrée sur le
 * point. PK hors de la ligne : un seul résultat, qui dit les bornes de la ligne.
 *
 * @param {any[]} positions `pk_positions` de GET /api/v1/search
 * @param {(key: string, params?: object) => string} t
 * @param {string} [lang]
 */
export function positionItems(positions, t, lang = 'fr') {
    return positions.flatMap((p) => {
        const line = p.line.code ?? p.line.name;
        const pk = pkText(p.pk_m, lang);
        const href = positionHref(p);
        if (!p.in_range) {
            return [
                {
                    href: `/lines/${p.line.slug}`,
                    name: t('nav.search.position_out', {
                        line,
                        pk,
                        from: pkText(p.bounds.from_m, lang),
                        to: pkText(p.bounds.to_m, lang),
                    }),
                    icon: 'bi-bezier',
                    detail: p.line.name,
                },
            ];
        }
        const place = p.commune ? ` (${p.commune})` : '';
        const name = p.at_station
            ? t('nav.search.position_at', { line, pk, station: p.at_station.name + place })
            : t('nav.search.position_between', {
                  line,
                  pk,
                  from: p.between.from.name,
                  to: p.between.to.name + place,
              });
        const map = positionMapHref(p);
        return [
            { href, name, icon: 'bi-bezier', detail: p.line.name },
            ...(map
                ? [{ href: map, name: t('nav.search.position_map', { line, pk }), icon: 'bi-map' }]
                : []),
        ];
    });
}

/**
 * Une option de la liste : `plate` (code de la ligne) ou `detail` (ligne de précision) quand son groupe en a.
 *
 * @typedef {object} SearchItem
 * @property {string} href
 * @property {string} name
 * @property {string} [icon]
 * @property {string | null} [plate]
 * @property {string | null} [detail]
 */

/**
 * Groupes de résultats, dans l'ordre affiché : position sur une ligne (PK), rubriques, lignes, gares, matériel roulant, accidents,
 * termes du glossaire, communes, puis
 * « Tous les résultats » (page de résultats, puis annuaires filtrés, une fois la recherche faite). Les groupes vides sont
 * retirés ; chaque option reçoit un indice continu et un identifiant (aria-activedescendant).
 *
 * @param {object} p
 * @param {string} p.query texte saisi, sans espaces autour
 * @param {boolean} p.suggestions champ vide = toutes les rubriques
 * @param {{ lines: any[], stations: any[], materials: any[], accidents?: any[], glossary?: any[], communes?: any[], pk_positions?: any[] }} p.results réponse de l'API
 * @param {boolean} p.searched la réponse correspond au texte saisi (ou l'appel a échoué)
 * @param {(key: string, params?: object) => string} p.t traduction
 * @param {string} p.idPrefix préfixe des identifiants
 * @param {string} [p.lang] langue de la page (nom et date des accidents, nom des termes du glossaire)
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
    const communes = results.communes ?? [];
    const glossary = results.glossary ?? [];
    /** @type {{ type: string, label: string, items: SearchItem[] }[]} */
    const groups = [
        {
            type: 'position',
            label: t('nav.search.positions'),
            items: positionItems(results.pk_positions ?? [], t, lang),
        },
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
            type: 'glossary',
            label: t('nav.search.glossary'),
            items: glossary.map((g) => ({
                href: `/glossary#${g.slug}`,
                name: g.names?.[lang] ?? g.name,
                icon: 'bi-book',
                detail: null,
            })),
        },
        {
            type: 'commune',
            label: t('nav.search.communes'),
            items: communes.map((c) => ({
                href: `/communes/${c.slug}`,
                name: c.name,
                icon: 'bi-geo-alt',
                detail: communeDetail(c),
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
