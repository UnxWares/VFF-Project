// Page de résultats de la recherche (Pages/Search/Index.svelte) : logique pure, testée par tests/js/searchPage.test.js.
// Les résultats viennent de SearchPageController (App\Services\SearchResultsPage).

import { accidentDetail, stationDetail } from './siteSearch.js';

/** Types de résultats, dans l'ordre de la page ('' = tout). */
export const SEARCH_TYPES = ['', 'line', 'station', 'accident', 'material'];

/** Types qui ont les filtres d'état et de département. */
export const FILTERABLE_TYPES = ['line', 'station'];

/**
 * Paramètres de l'adresse, sans les valeurs par défaut : `state` et `dept` seulement pour les lignes et les
 * gares, `page` seulement au-delà de la première. L'adresse reste ainsi courte et partageable.
 *
 * @param {{ q?: string, type?: string, state?: string, dept?: string, page?: number }} p
 * @returns {Record<string, string|number>}
 */
export function searchParams({ q = '', type = '', state = '', dept = '', page = 1 }) {
    /** @type {Record<string, string|number>} */
    const out = {};
    const query = q.trim();
    if (query) out.q = query;
    if (type) out.type = type;
    if (FILTERABLE_TYPES.includes(type)) {
        if (state) out.state = state;
        if (dept) out.dept = dept;
    }
    if (page > 1) out.page = page;
    return out;
}

/** Adresse de la page de résultats pour ces paramètres. */
export function searchHref(p) {
    const query = new URLSearchParams(
        Object.entries(searchParams(p)).map(([k, v]) => [k, String(v)]),
    ).toString();
    return query ? `/search?${query}` : '/search';
}

/**
 * Onglets des types : « Tout » et chaque type qui a des résultats (ou qui est choisi), avec son nombre.
 * Passer d'un type de lignes ou de gares à l'autre garde l'état et le département ; les autres onglets les
 * retirent (ils ne s'y appliquent pas).
 *
 * @param {{ q: string, type: string, state: string, dept: string, counts: Record<string, number>, total: number }} p
 */
export function typeTabs({ q, type, state, dept, counts, total }) {
    return SEARCH_TYPES.filter((t) => t === '' || t === type || (counts[t] ?? 0) > 0).map((t) => ({
        type: t,
        count: t === '' ? total : (counts[t] ?? 0),
        current: t === type,
        href: searchHref({ q, type: t, state, dept }),
    }));
}

/**
 * Texte autour d'un emplacement : `template` est le texte traduit où `marker` remplace la valeur à insérer
 * (un lien). Renvoie [avant, après] ; sans emplacement, tout le texte est « avant ».
 *
 * @param {string} template
 * @param {string} marker
 * @returns {[string, string]}
 */
export function splitAround(template, marker) {
    const at = template.indexOf(marker);
    if (at < 0) return [template, ''];
    return [template.slice(0, at), template.slice(at + marker.length)];
}

/**
 * Ligne de détail d'un résultat : état d'une ligne ; commune, département et statut d'une gare ; date, lieu et
 * nature d'un accident ; famille et constructeur d'un matériel. Parties séparées par « · ».
 *
 * @param {'line'|'station'|'accident'|'material'} type
 * @param {any} item résultat de son groupe (SearchResultsPage::present)
 * @param {(key: string, params?: object) => string} t
 * @param {string} [lang]
 */
export function resultDetail(type, item, t, lang = 'fr') {
    const parts =
        type === 'line'
            ? [t(`lines.states.${item.state ?? 'unknown'}`)]
            : type === 'station'
              ? [stationDetail(item), item.status ? t(`stations.statuses.${item.status}`) : null]
              : type === 'accident'
                ? [accidentDetail(item, lang), item.kind ? t(`accidents.kinds.${item.kind}`) : null]
                : [item.kind ? t(`materials.types.${item.kind}`) : null, item.builder ?? null];
    return parts.filter(Boolean).join(' · ');
}
