// Carte hors ligne. Le fond de plan des cartes vient d'un tiers (tuiles OpenStreetMap, ou MapTiler quand une
// clé est configurée), que le service worker ne garde jamais. Sur une page lue sans réseau (copie gardée par
// le service worker, ou navigateur hors ligne), les cartes partent donc d'un fond neutre où ne s'affichent
// que les tracés et les gares de VFF déjà reçus ; elles le signalent ici, et OfflineCopyNotice ajoute une
// phrase à son message tant qu'une telle carte est affichée.
import { offlineCopyDate } from './offlineCopy.js';

/** Couleur du fond neutre (peinture MapLibre), proche du fond « papier » du site dans chaque thème. */
export const NEUTRAL_BACKGROUND = { light: '#e7e3da', dark: '#1e2632' };

/** Identifiant de la couche de fond neutre. */
export const NEUTRAL_LAYER_ID = 'vff-neutral-background';

/** Glyphes des noms de gares (OpenFreeMap). Hors ligne, MapLibre dessine les noms avec une police locale. */
export const MAP_GLYPHS = 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf';

/**
 * La page est-elle lue sans réseau ? Vrai pour une copie servie par le service worker (propriété
 * `offline_copy`) ou quand le navigateur se sait hors ligne.
 *
 * @param {Record<string, any> | undefined} props propriétés Inertia de la page
 * @param {{ onLine?: boolean } | undefined} [nav]
 */
export function isOfflineView(props, nav = globalThis.navigator) {
    return offlineCopyDate(props) !== null || nav?.onLine === false;
}

/**
 * Style MapLibre sans aucune ressource tierce à charger : un fond uni. Les glyphes restent déclarés (le
 * style d'un calque de noms l'exige) ; leur requête échoue sans réseau et MapLibre se rabat sur une police
 * locale.
 *
 * @param {{ dark?: boolean, glyphs?: string }} [options]
 * @returns {import('maplibre-gl').StyleSpecification}
 */
export function neutralMapStyle({ dark = false, glyphs = MAP_GLYPHS } = {}) {
    return {
        version: 8,
        sources: {},
        layers: [
            {
                id: NEUTRAL_LAYER_ID,
                type: 'background',
                paint: {
                    'background-color': dark ? NEUTRAL_BACKGROUND.dark : NEUTRAL_BACKGROUND.light,
                },
            },
        ],
        glyphs,
    };
}

const partial = $state({ count: 0 });

/** Nombre de cartes affichées sur un fond neutre, faute de réseau (lu par OfflineCopyNotice). */
export const partialMaps = {
    get count() {
        return partial.count;
    },
};

/**
 * Une carte s'affiche sur un fond neutre. Renvoie la fonction à appeler quand la carte disparaît (un
 * second appel ne fait rien).
 *
 * @returns {() => void}
 */
export function reportPartialMap() {
    partial.count += 1;
    let released = false;
    return () => {
        if (released) return;
        released = true;
        partial.count -= 1;
    };
}
