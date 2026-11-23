/**
 * « Liste de ce qui est affiché » : alternative textuelle à la carte de /maps (lignes et gares de la
 * zone visible, à l'époque choisie). Logique pure, testée dans tests/js/mapListing.test.js ; les données
 * viennent de `GET /api/v1/eras/{year}/in-view` (App\Services\Map\ViewListing).
 */
import {
    HERITAGE_COLOR,
    STATION_STYLES,
    STATUS_COLOR,
    TRAFFIC_COLOR,
    TRAFFIC_STYLES,
} from '$lib/railStyle.js';

/** Mêmes bornes que le serveur (ViewListing::MAX_LON_SPAN, MAX_LAT_SPAN, LIMIT). */
export const MAX_LON_SPAN = 4;
export const MAX_LAT_SPAN = 3;
export const SERVER_LIMIT = 300;
/** Entrées par page. */
export const PAGE_SIZE = 25;
/** Attente après la fin d'un déplacement avant de demander la liste (ms). */
export const FETCH_DELAY = 350;

/**
 * États des lignes, dans l'ordre de la légende : clé du serveur (`state`), clé de traduction et couleur.
 * Les clés sont celles de TOGGLE_KEYS (légende) : une catégorie masquée dans la légende n'est pas listée.
 */
export const LINE_STATES = [
    ...TRAFFIC_STYLES.map((t) => ({
        key: t.key,
        labelKey: `maps.traffic.${t.key}`,
        color: TRAFFIC_COLOR[t.key],
    })),
    { key: 'heritage', labelKey: 'maps.legend.heritage_train', color: HERITAGE_COLOR },
    { key: 'velorail', labelKey: 'maps.legend.heritage_velorail', color: HERITAGE_COLOR },
    {
        key: 'service_track',
        labelKey: 'maps.status.service_track',
        color: STATUS_COLOR.service_track,
    },
    { key: 'construction', labelKey: 'maps.status.construction', color: STATUS_COLOR.construction },
    { key: 'abandoned', labelKey: 'maps.status.abandoned', color: STATUS_COLOR.abandoned },
    { key: 'dismantled', labelKey: 'maps.status.gone', color: STATUS_COLOR.dismantled },
];

/** États des gares (statut de la carte), dans l'ordre de la légende. */
export const STATION_STATES = STATION_STYLES.map((s) => ({
    key: s.key,
    labelKey: s.labelKey,
    color: s.color,
}));

const lineState = Object.fromEntries(LINE_STATES.map((s) => [s.key, s]));
const stationState = Object.fromEntries(STATION_STATES.map((s) => [s.key, s]));

/** État d'une ligne, ou celui de « trafic non renseigné » pour une clé inconnue (jamais d'erreur). */
export const lineStateOf = (key) => lineState[key] ?? lineState.unknown;
export const stationStateOf = (key) => stationState[key] ?? stationState.declassified;

const round3 = (n) => Math.round(n * 1000) / 1000;

/**
 * Emprise `[ouest, sud, est, nord]` arrondie à 0,001 degré (environ 100 m : deux lecteurs qui regardent
 * à peu près la même zone partagent le cache), ou null si les limites sont invalides.
 * @param {{ getWest: Function, getSouth: Function, getEast: Function, getNorth: Function } | null | undefined} bounds limites MapLibre
 * @returns {[number, number, number, number] | null}
 */
export function bboxFromBounds(bounds) {
    if (!bounds) return null;
    const box = [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()];
    if (!box.every(Number.isFinite)) return null;
    // Une carte tournée ou sur le globe peut dépasser les limites : on garde le monde plat.
    const west = Math.max(-180, box[0]);
    const east = Math.min(180, box[2]);
    const south = Math.max(-85, box[1]);
    const north = Math.min(85, box[3]);
    if (west >= east || south >= north) return null;
    return [round3(west), round3(south), round3(east), round3(north)];
}

/** La zone est trop large pour être listée (le serveur ne la calcule pas non plus). */
export function isTooWide(bbox) {
    return bbox[2] - bbox[0] > MAX_LON_SPAN || bbox[3] - bbox[1] > MAX_LAT_SPAN;
}

/** Adresse de la liste d'une ère pour une emprise. */
export function listingUrl(year, bbox, version = null) {
    const query = new URLSearchParams({ bbox: bbox.join(',') });
    if (version) query.set('v', version);
    return `/api/v1/eras/${year}/in-view?${query}`;
}

/**
 * Lignes à lister : celles dont l'état n'est pas masqué dans la légende, puis celles de l'état choisi
 * (`only` vide : tous), triées par kilomètres décroissants ou par nom.
 * @param {Array<{slug: string, name: string, state: string, km: number}>} items
 * @param {{ hidden?: string[], only?: string, sort?: string }} options
 */
export function visibleLines(items, { hidden = [], only = '', sort = 'km' } = {}) {
    const rows = items.filter((l) => !hidden.includes(l.state) && (!only || l.state === only));
    const byName = (a, b) => a.name.localeCompare(b.name, 'fr') || a.slug.localeCompare(b.slug);
    return rows.sort(sort === 'name' ? byName : (a, b) => b.km - a.km || byName(a, b));
}

/** Gares de l'état choisi (`only` vide : toutes), dans l'ordre du serveur (par nom). */
export function visibleStations(items, { only = '' } = {}) {
    return only ? items.filter((s) => s.state === only) : items;
}

/** Nombre de pages d'une liste (au moins 1). */
export const pageCount = (length, size = PAGE_SIZE) => Math.max(1, Math.ceil(length / size));

/** Page donnée (à partir de 1) d'une liste ; la page est ramenée dans les bornes. */
export function paginate(rows, page, size = PAGE_SIZE) {
    const current = Math.min(Math.max(1, page), pageCount(rows.length, size));
    return {
        page: current,
        pages: pageCount(rows.length, size),
        rows: rows.slice((current - 1) * size, current * size),
    };
}

/**
 * Cache des réponses du navigateur (dernières emprises) : revenir sur une zone ou une époque déjà vue
 * n'appelle plus le serveur.
 */
export function createListingCache(max = 24) {
    const map = new Map();
    return {
        get(key) {
            if (!map.has(key)) return undefined;
            const value = map.get(key);
            map.delete(key);
            map.set(key, value);
            return value;
        },
        set(key, value) {
            map.delete(key);
            map.set(key, value);
            if (map.size > max) map.delete(map.keys().next().value);
        },
        get size() {
            return map.size;
        },
    };
}
