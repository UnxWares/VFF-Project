// Chargement différé de MapLibre GL (environ 1 Mo, 290 Ko compressés) pour les petites cartes des fiches
// (ligne, gare) : la page s'affiche sans l'attendre, la carte arrive juste après dans la place qui lui
// est réservée. Un seul téléchargement, partagé par toutes les cartes de la session. Le protocole
// `pmtiles://` du fond de carte libre (lib/pmtilesProtocol.js) est enregistré au passage ; s'il manque,
// les cartes s'affichent quand même (fond uni hors ligne, fond habituel en ligne) : hasPmtilesProtocol le dit.

/** @typedef {typeof import('maplibre-gl')} MapLibre */

/** @type {Promise<MapLibre> | null} */
let pending = null;

/** Protocole `pmtiles://` enregistré sur le MapLibre rendu (fond de carte libre utilisable). */
let pmtilesReady = false;

/** Le fond de carte libre peut-il être lu par les cartes chargées par loadMaplibre ? */
export function hasPmtilesProtocol() {
    return pmtilesReady;
}

/**
 * Rend MapLibre réglé par `./maplibre.js` (adresse du worker comprise), comme `import maplibregl from
 * '$lib/maplibre.js'` dans les grandes cartes, avec le protocole `pmtiles://` enregistré.
 *
 * @param {() => Promise<MapLibre>} [importer] chargeur du module (remplacé dans les tests)
 * @param {() => Promise<{ registerPmtilesProtocol: (maplibre: any) => boolean }>} [protocol] chargeur du
 *   protocole `pmtiles://` (remplacé dans les tests)
 * @returns {Promise<MapLibre>}
 */
export function loadMaplibre(
    importer = () => import('./maplibre.js').then((module) => module.default),
    protocol = () => import('./pmtilesProtocol.js'),
) {
    pending ??= Promise.all([importer(), protocol().catch(() => null)])
        .then(([maplibre, pmtiles]) => {
            pmtilesReady = pmtiles?.registerPmtilesProtocol(maplibre) === true;
            return maplibre;
        })
        .catch((error) => {
            // Échec réseau : la prochaine carte retentera le téléchargement.
            pending = null;
            throw error;
        });
    return pending;
}
