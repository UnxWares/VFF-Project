// Carte hors ligne. En ligne, le fond de plan des cartes vient d'un tiers (tuiles OpenStreetMap, ou MapTiler
// quand une clé est configurée), que le service worker ne garde jamais, ou du fond de carte libre servi par
// VFF quand l'option `maps.basemap.online` est active (lib/basemap.js). Sur une page lue sans réseau (copie
// gardée par le service worker, ou navigateur hors ligne), les cartes partent donc d'un fond sans aucune
// ressource tierce : le fond libre de VFF quand une archive est servie (ses morceaux déjà vus sont gardés
// par le service worker, le reste reste uni), sinon un fond uni. Elles le signalent (lib/partialMaps), et
// OfflineCopyNotice ajoute une phrase à son message tant qu'une telle carte est affichée.
import { page } from '@inertiajs/svelte';
import { basemapStyle } from './basemap.js';
import { offlineCopyDate } from './offlineCopy.js';

export { partialMaps, reportPartialMap } from './partialMaps.svelte.js';

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

/** Fond libre décrit par la page courante (propriété partagée `basemap`), ou null. */
function currentBasemap() {
    return page?.props?.basemap ?? null;
}

/**
 * Style MapLibre d'une carte lue sans réseau, sans aucune ressource tierce : le fond de carte libre de VFF
 * quand la page en décrit un (`basemap`), sinon un fond uni. Pour le fond uni, les glyphes restent déclarés
 * (le style d'un calque de noms l'exige) ; leur requête échoue sans réseau et MapLibre se rabat sur une
 * police locale. Le fond libre demande le protocole `pmtiles://` (lib/pmtilesProtocol.js, enregistré par
 * loadMaplibre et par la carte /maps).
 *
 * @param {{ dark?: boolean, glyphs?: string, basemap?: { url?: string, attribution?: string } | null, lang?: string }} [options]
 * @returns {import('maplibre-gl').StyleSpecification}
 */
export function neutralMapStyle({
    dark = false,
    glyphs = MAP_GLYPHS,
    basemap = currentBasemap(),
    lang = globalThis.document?.documentElement?.lang,
} = {}) {
    if (basemap?.url) {
        return basemapStyle({ url: basemap.url, attribution: basemap.attribution, dark, lang });
    }
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
