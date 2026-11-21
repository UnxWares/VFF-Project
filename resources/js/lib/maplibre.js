// MapLibre GL JS, prêt à l'emploi : toutes les cartes l'importent d'ici, jamais de « maplibre-gl » directement.
//
// MapLibre 6 est publié en module ES seulement, sans export par défaut, et cherche son worker
// (`maplibre-gl-worker.mjs`) à côté de son propre fichier. Une fois empaqueté par Vite, ce fichier
// voisin n'existe pas : sans l'adresse donnée ici, la carte s'arrête sur « Worker failed to load ».
// `?url` fait copier le worker (autonome, sans import) dans le build et en donne l'adresse.
//
// `Map` reçoit ici les textes de la langue de la page (boutons, message « deux doigts »…, clés
// `maps.controls.*`) : MapLibre les écrirait sinon en anglais sur les pages fr et de.
import i18next from 'i18next';
import * as maplibregl from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { withPageLocale } from './mapControls.js';

maplibregl.setWorkerUrl(workerUrl);

class LocalizedMap extends maplibregl.Map {
    /** @param {import('maplibre-gl').MapOptions} options */
    constructor(options) {
        super(withPageLocale(options, (key) => i18next.t(key)));
    }
}

export default /** @type {typeof maplibregl} */ ({ ...maplibregl, Map: LocalizedMap });
