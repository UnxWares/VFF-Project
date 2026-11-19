// Téléchargement du tracé d'une ligne (fiche ligne) : adresses des fichiers de l'API.
// Logique pure, testée par tests/js/traceDownload.test.js.

/** Formats proposés : clé, libellé et chemin sous `/api/v1/lines/{slug}/`. */
export const TRACE_FORMATS = [
    { key: 'gpx', label: 'GPX', path: 'gpx' },
    { key: 'kml', label: 'KML', path: 'kml' },
    { key: 'geojson', label: 'GeoJSON', path: 'geojson' },
];

/**
 * Ère proposée d'abord : l'ère courante si elle est exportable, sinon la plus récente.
 * @param {number[]} eras années exportables, dans l'ordre croissant
 * @param {number|null} currentYear année de l'ère courante
 * @returns {number|null}
 */
export function defaultEra(eras, currentYear) {
    if (!eras.length) return null;
    return eras.includes(currentYear) ? currentYear : eras[eras.length - 1];
}

/**
 * Adresse du fichier d'un format pour une ligne et une ère.
 * @param {string} slug identifiant de la ligne
 * @param {{ path: string }} format un élément de TRACE_FORMATS
 * @param {number} era année de l'ère
 */
export function traceUrl(slug, format, era) {
    return `/api/v1/lines/${encodeURIComponent(slug)}/${format.path}?era=${era}`;
}
