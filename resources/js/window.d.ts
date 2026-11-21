// Globales ajoutées à `window` par le front.
import type { Map as MapLibreMap } from 'maplibre-gl';

declare global {
    interface Window {
        /** Carte de l'éditeur de tracé, exposée au serveur de dev Vite seulement (tests bout-en-bout). */
        __vffTraceMap?: MapLibreMap;
    }
}

export {};
