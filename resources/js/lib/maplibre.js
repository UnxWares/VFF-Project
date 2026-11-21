// MapLibre GL JS, prêt à l'emploi : toutes les cartes l'importent d'ici, jamais de « maplibre-gl » directement.
//
// MapLibre 6 est publié en module ES seulement, sans export par défaut, et cherche son worker
// (`maplibre-gl-worker.mjs`) à côté de son propre fichier. Une fois empaqueté par Vite, ce fichier
// voisin n'existe pas : sans l'adresse donnée ici, la carte s'arrête sur « Worker failed to load ».
// `?url` fait copier le worker (autonome, sans import) dans le build et en donne l'adresse.
import * as maplibregl from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';

maplibregl.setWorkerUrl(workerUrl);

export default maplibregl;
