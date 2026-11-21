/**
 * Gares de la carte /maps : GeoJSON d'une ère (GET /api/v1/eras/{année}/stations.geojson, 2,4 Mo pour le
 * réseau actuel, 3,7 Mo pour 1930). Elles ne se dessinent qu'à partir du zoom STATIONS_MIN_ZOOM
 * (illisibles en vue d'ensemble) : le fichier n'est demandé qu'en approchant de ce zoom, un zoom plus tôt
 * pour être prêt à temps. Avant le 20/11/2026, il l'était à l'ouverture de la carte (vue de la France
 * entière, zoom 4) et à chaque changement d'époque, avant les tuiles des tracés.
 */

/** Premier zoom où les gares sont dessinées. */
export const STATIONS_MIN_ZOOM = 7;

/** Premier zoom où le fichier des gares est demandé. */
export const STATIONS_LOAD_ZOOM = STATIONS_MIN_ZOOM - 1;

/** Source vide, en attendant le fichier (nouvel objet à chaque appel : MapLibre garde celui qu'il reçoit). */
export function noStations() {
    return { type: 'FeatureCollection', features: [] };
}

/**
 * Adresse du fichier des gares à charger maintenant, ou null : carte trop éloignée, ou fichier déjà posé
 * sur la source (`loaded`, adresse du dernier chargement).
 *
 * @param {number} zoom zoom de la carte
 * @param {string} url fichier des gares de l'époque affichée
 * @param {string|null} loaded dernier fichier posé sur la source
 * @returns {string|null}
 */
export function stationsToLoad(zoom, url, loaded) {
    return zoom >= STATIONS_LOAD_ZOOM && url !== loaded ? url : null;
}
