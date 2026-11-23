/**
 * Position et zoom de la carte dans l'adresse : `?center=lon,lat&zoom=12.5`, le même format que la
 * carte intégrable (/embed/maps). Les valeurs sont validées et bornées ; une valeur absente ou
 * invalide est ignorée (la carte garde alors son cadrage d'ouverture).
 */

/** Zoom maximal de la carte (le fond est surzoomé de 15 à 19). */
export const VIEW_MAX_ZOOM = 19;
export const VIEW_MIN_ZOOM = 0;
/** Limites de la carte : le monde en projection Web Mercator (outre-mer compris). */
export const VIEW_MAX_LAT = 85;
export const VIEW_MAX_LON = 180;

const COORD_DECIMALS = 5;
const ZOOM_DECIMALS = 2;

const isNumber = (s) => typeof s === 'string' && /^[+-]?\d+(\.\d+)?$/.test(s.trim());

/**
 * Centre `lon,lat` lu dans une valeur d'adresse ; null si absent, mal formé ou hors des limites.
 * @param {string | null} raw
 * @returns {[number, number] | null}
 */
export function parseCenter(raw) {
    if (typeof raw !== 'string') return null;
    const parts = raw.split(',');
    if (parts.length !== 2 || !parts.every(isNumber)) return null;
    const [lon, lat] = parts.map(Number);
    if (Math.abs(lon) > VIEW_MAX_LON || Math.abs(lat) > VIEW_MAX_LAT) return null;
    return [lon, lat];
}

/**
 * Zoom lu dans une valeur d'adresse, ramené entre 0 et 19 ; null si absent ou mal formé.
 * @param {string | null} raw
 * @returns {number | null}
 */
export function parseZoom(raw) {
    if (!isNumber(raw)) return null;
    const z = Number(raw);
    return Math.min(VIEW_MAX_ZOOM, Math.max(VIEW_MIN_ZOOM, z));
}

/**
 * Vue demandée par l'adresse : `center` et `zoom` valent null quand ils ne sont pas donnés.
 * @param {string} search chaîne de requête (`window.location.search`)
 * @returns {{ center: [number, number] | null, zoom: number | null }}
 */
export function readMapView(search) {
    const params = new URLSearchParams(search);
    return { center: parseCenter(params.get('center')), zoom: parseZoom(params.get('zoom')) };
}

const round = (n, decimals) => Number(n.toFixed(decimals));

/**
 * Adresse avec la vue donnée (les autres paramètres sont gardés). `view` null ou invalide retire
 * `center` et `zoom`.
 * @param {string} href adresse complète
 * @param {{ center: [number, number], zoom: number } | null} view
 * @returns {string}
 */
export function writeMapView(href, view) {
    const url = new URL(href);
    url.searchParams.delete('center');
    url.searchParams.delete('zoom');
    const center = view && Array.isArray(view.center) ? view.center : null;
    if (center && Number.isFinite(view.zoom)) {
        const lon = round(center[0], COORD_DECIMALS);
        const lat = round(center[1], COORD_DECIMALS);
        if (Math.abs(lon) <= VIEW_MAX_LON && Math.abs(lat) <= VIEW_MAX_LAT) {
            const zoom = Math.min(VIEW_MAX_ZOOM, Math.max(VIEW_MIN_ZOOM, view.zoom));
            url.searchParams.set('center', `${lon},${lat}`);
            url.searchParams.set('zoom', String(round(zoom, ZOOM_DECIMALS)));
        }
    }
    // `,` reste lisible dans l'adresse (format identique à /embed/maps).
    return url.toString().replace(/center=([^&]*)/, (m) => m.replace(/%2C/gi, ','));
}
