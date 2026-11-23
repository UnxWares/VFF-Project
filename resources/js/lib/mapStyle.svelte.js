// Fond de plan de toutes les cartes de VFF (carte /maps et petites cartes des fiches), choisi au même endroit :
//
// 1. page lue sans réseau : fond libre de VFF quand une archive est servie (morceaux déjà vus gardés par le
//    service worker), sinon fond uni (neutralMapStyle) ;
// 2. en ligne avec l'option `maps.basemap.online` : fond libre de VFF (lib/basemap.js), clair ou sombre ;
// 3. sinon le fond habituel : style MapTiler si une clé est configurée, tuiles raster d'OpenStreetMap.
//
// Le fond libre demande le protocole `pmtiles://` (lib/pmtilesProtocol.js) : sans lui, la carte garde le
// fond habituel en ligne et le fond uni hors ligne.
import { get } from 'svelte/store';
import { page } from '@inertiajs/svelte';
import { applyBasemapTheme, basemapStyle, usesBasemap } from './basemap.js';
import { i18n } from './i18n.js';
import { MAP_GLYPHS, neutralMapStyle } from './offlineMap.svelte.js';
import { theme } from './stores/theme.svelte.js';

/** Tuiles raster d'OpenStreetMap (fond habituel en ligne). */
export const OSM_TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

/** Crédit des tuiles raster d'OpenStreetMap. */
export const OSM_ATTRIBUTION =
    '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

/** Identifiant de la couche raster d'OpenStreetMap (les cartes peuvent changer sa peinture). */
export const OSM_LAYER_ID = 'osm';

/**
 * @typedef {object} MapStyleOptions
 * @property {boolean} offline page lue sans réseau
 * @property {{ url?: string, online?: boolean, attribution?: string } | null | undefined} basemap propriété
 *   partagée `basemap`
 * @property {boolean} [pmtiles] protocole `pmtiles://` enregistré sur MapLibre
 * @property {boolean} [dark] thème sombre
 * @property {string} [lang] langue des noms du fond libre
 * @property {string} [maptilerKey] clé MapTiler (style MapTiler à la place des tuiles OpenStreetMap)
 * @property {string} [osmAttribution] crédit affiché sous les tuiles OpenStreetMap
 * @property {Record<string, unknown>} [osmPaint] peinture de la couche raster d'OpenStreetMap
 */

/**
 * Style MapLibre du fond d'une carte, et s'il s'agit du fond libre de VFF (dont les couleurs suivent alors
 * le thème, voir followBasemapTheme).
 *
 * @param {MapStyleOptions} options
 * @returns {{ style: string | import('maplibre-gl').StyleSpecification, onBasemap: boolean }}
 */
export function mapStyle({
    offline,
    basemap,
    pmtiles = true,
    dark = false,
    lang = 'fr',
    maptilerKey,
    osmAttribution = OSM_ATTRIBUTION,
    osmPaint,
}) {
    const usable = pmtiles ? basemap : null;
    const onBasemap = usesBasemap(usable, offline);
    if (offline) {
        return { style: neutralMapStyle({ dark, lang, basemap: usable }), onBasemap };
    }
    if (onBasemap && usable?.url) {
        const style = basemapStyle({
            url: usable.url,
            attribution: usable.attribution,
            dark,
            lang,
        });
        return { style, onBasemap };
    }
    if (maptilerKey) {
        return {
            style: `https://api.maptiler.com/maps/basic-v2/style.json?key=${maptilerKey}`,
            onBasemap,
        };
    }
    return {
        style: {
            version: 8,
            sources: {
                'osm-raster': {
                    type: 'raster',
                    tiles: [OSM_TILES],
                    tileSize: 256,
                    // Sans `maxzoom: 19`, MapLibre demanderait des tuiles z20 et plus, absentes (404).
                    maxzoom: 19,
                    attribution: osmAttribution,
                },
            },
            layers: [
                {
                    id: OSM_LAYER_ID,
                    type: 'raster',
                    source: 'osm-raster',
                    ...(osmPaint && { paint: osmPaint }),
                },
            ],
            // OpenFreeMap sert des polices Noto Sans complètes (noms des gares de VFF).
            glyphs: MAP_GLYPHS,
        },
        onBasemap,
    };
}

/**
 * Le même choix pour la page courante : fond libre décrit par la propriété partagée `basemap`, thème et
 * langue du moment, clé MapTiler de la construction.
 *
 * @param {boolean} offline page lue sans réseau
 * @param {Pick<MapStyleOptions, 'pmtiles' | 'osmAttribution' | 'osmPaint'>} [options]
 */
export function pageMapStyle(offline, options = {}) {
    return mapStyle({
        offline,
        basemap: page?.props?.basemap,
        dark: theme.isDark,
        lang: get(i18n)?.language ?? 'fr',
        maptilerKey: import.meta.env.VITE_MAPTILER_KEY,
        ...options,
    });
}

/**
 * Couleurs du fond libre accordées au thème pendant la visite, sans recharger le style (les calques de VFF
 * restent en place). À appeler pendant l'initialisation du composant.
 *
 * @param {() => { getLayer(id: string): unknown, setPaintProperty(id: string, name: string, value: unknown): void } | null | undefined} target
 *   carte dessinée sur le fond libre et prête, sinon null
 */
export function followBasemapTheme(target) {
    $effect(() => {
        const dark = theme.isDark;
        const map = target();
        if (map) applyBasemapTheme(map, { dark, lang: get(i18n)?.language ?? 'fr' });
    });
}
