import { formatStationDate } from './stationDates.js';

/**
 * Audit des gares dans l'espace d'administration (/validate/audit/stations) : adresses de la
 * page, de la fiche et de l'export, lecture du formulaire de filtres, numérotation des positions
 * des sources, cadrage de la carte, dates à la précision près. Fonctions pures, testées
 * (tests/js/stationAudit.test.js).
 */

export const BASE_URL = '/validate/audit/stations';

/** Valeurs par défaut du contrôleur : inutile de les répéter dans l'adresse. */
export const DEFAULT_FILTERS = Object.freeze({ grade: 'disagree', sort: 'spread' });

/** Paramètres de filtre, dans l'ordre de l'adresse. */
export const FILTER_KEYS = Object.freeze([
    'grade',
    'region',
    'check',
    'opening',
    'closure',
    'line',
    'q',
    'sort',
]);

/** Ton du badge de chaque note (le texte dit la note, la couleur ne fait que la souligner). */
export const GRADE_TONES = Object.freeze({
    agree: 'success',
    close: 'info',
    isolated: 'muted',
    disagree: 'danger',
    none: 'warning',
});

/** Ton du badge de chaque note de date. */
export const DATE_GRADE_TONES = Object.freeze({
    sourced: 'success',
    estimated: 'warning',
    unknown: 'danger',
    not_applicable: 'muted',
});

/**
 * Paramètres de l'adresse : valeurs vides et valeurs par défaut retirées, page à partir de 2.
 *
 * @param {Record<string, unknown>} filters
 * @param {number} [page]
 * @returns {Record<string, string>}
 */
export function filterQuery(filters, page = 1) {
    /** @type {Record<string, string>} */
    const query = {};
    for (const key of FILTER_KEYS) {
        const raw = filters?.[key];
        const value = raw == null ? '' : String(raw).trim();
        if (value === '' || DEFAULT_FILTERS[key] === value) continue;
        query[key] = value;
    }
    if (Number.isInteger(page) && page > 1) query.page = String(page);
    return query;
}

/**
 * @param {string} path
 * @param {Record<string, string>} query
 */
function withQuery(path, query) {
    const search = new URLSearchParams(query).toString();
    return search ? `${path}?${search}` : path;
}

/**
 * Adresse de la file filtrée.
 *
 * @param {Record<string, unknown>} filters
 * @param {number} [page]
 */
export function pageUrl(filters, page = 1) {
    return withQuery(BASE_URL, filterQuery(filters, page));
}

/**
 * Export CSV de toute la file filtrée (sans la page).
 *
 * @param {Record<string, unknown>} filters
 */
export function exportUrl(filters) {
    return withQuery(`${BASE_URL}/export.csv`, filterQuery(filters));
}

/** @param {number} stationId */
export function detailUrl(stationId) {
    return `${BASE_URL}/${encodeURIComponent(String(stationId))}`;
}

/**
 * Filtres lus dans le formulaire (FormData ou liste de paires), textes rognés.
 *
 * @param {Iterable<[string, FormDataEntryValue]>} entries
 * @returns {Record<string, string>}
 */
export function formFilters(entries) {
    /** @type {Record<string, string>} */
    const out = {};
    for (const [key, value] of entries) {
        if (FILTER_KEYS.includes(key) && typeof value === 'string') out[key] = value.trim();
    }
    return out;
}

/**
 * Part d'un total (0 si le total est nul).
 *
 * @param {number | null | undefined} count
 * @param {number | null | undefined} total
 */
export function share(count, total) {
    const n = Number(count ?? 0);
    const d = Number(total ?? 0);
    return d > 0 && Number.isFinite(n) ? n / d : 0;
}

/**
 * @param {unknown} v
 * @returns {v is number}
 */
const isCoord = (v) => typeof v === 'number' && Number.isFinite(v);

/**
 * Positions des sources numérotées dans l'ordre reçu (1, 2, 3…) : le même numéro sert de
 * repère sur la carte et dans la liste. Une position sans coordonnées garde son numéro mais
 * n'a pas de repère sur la carte (`onMap` faux).
 *
 * @param {Array<Record<string, any>> | null | undefined} positions
 * @returns {Array<Record<string, any> & { n: number, onMap: boolean }>}
 */
export function numberedPositions(positions) {
    return (positions ?? []).map((p, i) => ({
        ...p,
        n: i + 1,
        onMap: isCoord(p?.lon) && isCoord(p?.lat),
    }));
}

/**
 * Cadre de la carte : position retenue et positions de toutes les sources, élargi à `minSpan`
 * degrés au moins (environ 200 m) pour ne pas zoomer à l'infini sur des points confondus.
 *
 * @param {{ lon?: number, lat?: number, positions?: Array<{ lon?: number, lat?: number }> } | null} station
 * @param {number} [minSpan]
 * @returns {[[number, number], [number, number]] | null}
 */
export function auditBounds(station, minSpan = 0.002) {
    const points = [
        [station?.lon, station?.lat],
        ...(station?.positions ?? []).map((p) => [p?.lon, p?.lat]),
    ].filter(([x, y]) => isCoord(x) && isCoord(y));
    if (!points.length) return null;
    let [w, s] = points[0];
    let [e, n] = points[0];
    for (const [x, y] of points) {
        w = Math.min(w, x);
        e = Math.max(e, x);
        s = Math.min(s, y);
        n = Math.max(n, y);
    }
    const padLng = Math.max(0, (minSpan - (e - w)) / 2);
    const padLat = Math.max(0, (minSpan - (n - s)) / 2);
    return [
        [w - padLng, s - padLat],
        [e + padLng, n + padLat],
    ];
}

/**
 * Tracés des lignes proches en GeoJSON (ceux sans géométrie sont écartés).
 *
 * @param {Array<{ code?: string, name?: string, geometry?: object | null }> | null | undefined} tracks
 */
export function trackCollection(tracks) {
    return {
        type: 'FeatureCollection',
        features: (tracks ?? [])
            .filter((tr) => tr?.geometry && typeof tr.geometry === 'object')
            .map((tr) => ({
                type: 'Feature',
                properties: { code: tr.code ?? '', name: tr.name ?? '' },
                geometry: tr.geometry,
            })),
    };
}

/**
 * Date à sa précision : jour, mois, année, décennie (comme les fiches des gares), siècle.
 *
 * @param {string | null | undefined} iso
 * @param {string | null | undefined} precision
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang
 * @returns {string}
 */
export function auditDate(iso, precision, t, lang) {
    if (!iso) return '';
    if (precision === 'century') {
        const year = Number(String(iso).slice(0, 4));
        const ordinal = centuryOrdinal(Math.floor((year - 1) / 100) + 1, lang);
        return t('audit_stations.dates.century', { nth: ordinal });
    }
    return formatStationDate(String(iso), precision || 'day', null, t, lang) ?? '';
}

/**
 * Numéro de siècle à la manière de chaque langue : « XIXe », « 19th », « 19. ».
 *
 * @param {number} century
 * @param {string} lang
 */
export function centuryOrdinal(century, lang) {
    const code = String(lang ?? 'fr').slice(0, 2);
    if (code === 'de') return `${century}.`;
    if (code === 'en') {
        const tens = century % 100;
        const suffix =
            tens >= 11 && tens <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[century % 10] ?? 'th');
        return `${century}${suffix}`;
    }
    /** @type {Array<[number, string]>} */
    const romans = [
        [10, 'X'],
        [9, 'IX'],
        [5, 'V'],
        [4, 'IV'],
        [1, 'I'],
    ];
    let rest = century;
    let roman = '';
    for (const [value, letters] of romans) {
        while (rest >= value) {
            roman += letters;
            rest -= value;
        }
    }
    return `${roman}e`;
}

/**
 * Écart entre deux dates en années entières (arrondi, signe ignoré) ; null sans écart connu.
 *
 * @param {number | null | undefined} days
 */
export function deltaYears(days) {
    if (days == null || !Number.isFinite(Number(days))) return null;
    return Math.round(Math.abs(Number(days)) / 365.25);
}

/**
 * Gare voisine dans la page de la file (précédente : -1, suivante : +1), ou null.
 *
 * @param {Array<{ station_id: number }>} items
 * @param {number | null | undefined} currentId
 * @param {-1 | 1} step
 */
export function neighbourItem(items, currentId, step) {
    const i = (items ?? []).findIndex((it) => it.station_id === currentId);
    if (i < 0) return null;
    return items[i + step] ?? null;
}

/** Contrôle à revoir en premier : la position retenue est contredite par deux sources d'accord. */
export const PRIORITY_CHECK = 'retained_outvoted';

/**
 * Contrôles dans l'ordre d'affichage : le contrôle prioritaire en tête, les autres dans l'ordre reçu.
 *
 * @template T
 * @param {T[] | null | undefined} checks
 * @param {(c: T) => string} [codeOf]
 * @returns {T[]}
 */
export function sortChecks(checks, codeOf = (c) => String(c)) {
    const list = [...(checks ?? [])];
    return [
        ...list.filter((c) => codeOf(c) === PRIORITY_CHECK),
        ...list.filter((c) => codeOf(c) !== PRIORITY_CHECK),
    ];
}

/**
 * Nom d'hôte d'une adresse de référence (« www.wikidata.org »), ou l'adresse telle quelle si elle
 * ne se lit pas.
 *
 * @param {string} url
 */
export function hostOf(url) {
    try {
        return new URL(url).hostname || url;
    } catch {
        return url;
    }
}

/**
 * Adresse web sûre à mettre en lien : seulement http ou https (une adresse de référence Wikidata,
 * P854, est saisie librement et peut porter `javascript:` ou `data:`). Rend null sinon.
 *
 * @param {unknown} url
 * @returns {string | null}
 */
export function webUrl(url) {
    if (typeof url !== 'string') return null;
    try {
        const parsed = new URL(url.trim());
        return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.href : null;
    } catch {
        return null;
    }
}

/**
 * Références d'une date candidate à afficher en lien : celles dont l'adresse est http ou https.
 *
 * @param {Array<{ url?: unknown }> | null | undefined} references
 * @returns {Array<{ url: string }>}
 */
export function linkedReferences(references) {
    return (references ?? []).flatMap((r) => {
        const url = webUrl(r?.url);
        return url ? [{ ...r, url }] : [];
    });
}

/**
 * Familles de sources d'une gare de la file, telles qu'affichées : la liste vient du serveur
 * (positions recopiées exclues) et le nombre suit la liste quand elle est fournie, pour que le
 * libellé « 2 familles de sources : SNCF, OpenStreetMap » ne se contredise jamais.
 *
 * @param {{ families?: number | null, sources?: string[] | null }} item
 * @returns {{ count: number, list: string[] }}
 */
export function sourceFamilies(item) {
    const list = [...new Set((item?.sources ?? []).filter(Boolean))];
    return { count: list.length || Number(item?.families ?? 0), list };
}
