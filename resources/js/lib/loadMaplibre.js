// Chargement différé de MapLibre GL (environ 1 Mo, 290 Ko compressés) pour les petites cartes des fiches
// (ligne, gare) : la page s'affiche sans l'attendre, la carte arrive juste après dans la place qui lui
// est réservée. Un seul téléchargement, partagé par toutes les cartes de la session. Le protocole
// `pmtiles://` du fond de carte libre (lib/pmtilesProtocol.js) est enregistré au passage ; s'il manque,
// les cartes s'affichent quand même (fond uni hors ligne, fond habituel en ligne).

/** @typedef {typeof import('maplibre-gl')} MapLibre */

/** @type {Promise<MapLibre> | null} */
let pending = null;

/**
 * @param {() => Promise<any>} [importer] chargeur du module (remplacé dans les tests)
 * @param {() => Promise<{ registerPmtilesProtocol: (maplibre: any) => boolean }>} [protocol] chargeur du
 *   protocole `pmtiles://` (remplacé dans les tests)
 * @returns {Promise<MapLibre>}
 */
export function loadMaplibre(
    importer = () => import('maplibre-gl'),
    protocol = () => import('./pmtilesProtocol.js'),
) {
    pending ??= Promise.all([importer(), protocol().catch(() => null)])
        // Le paquet est publié en CommonJS : l'objet MapLibre est l'export par défaut une fois empaqueté.
        .then(([module, pmtiles]) => {
            const maplibre = module.default ?? module;
            pmtiles?.registerPmtilesProtocol(maplibre);
            return maplibre;
        })
        .catch((error) => {
            // Échec réseau : la prochaine carte retentera le téléchargement.
            pending = null;
            throw error;
        });
    return pending;
}
