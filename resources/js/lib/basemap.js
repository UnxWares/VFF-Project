// Fond de carte libre auto-hébergé : archive PMTiles des données OpenStreetMap au schéma Protomaps (ODbL,
// « © OpenStreetMap »), servie par VFF (App\Http\Controllers\BasemapArchiveController) et décrite aux pages
// par la propriété partagée `basemap` ({ url, online, attribution } ou null).
//
// Style sobre, accordé au site : fond « papier » (le même que le fond neutre hors ligne), routes et noms
// discrets, voies ferrées actuelles en gris, jamais en orange (#ff6500 reste aux tracés de VFF). Ni icône
// ni pictogramme : aucune planche de sprites à charger. Polices Noto Sans (licence OFL) servies par VFF
// (public/basemap/fonts). Couches calculées par @protomaps/basemaps (BSD-3, dessin CC0) avec une palette VFF.
import { DARK, LIGHT, layers } from '@protomaps/basemaps';

/** Identifiant de la source vectorielle du fond libre. */
export const BASEMAP_SOURCE_ID = 'vff-basemap';

/**
 * Dernier niveau de zoom construit dans l'archive PMTiles (scripts/basemap-pmtiles.sh, zooms 0 à 15). Au-delà,
 * MapLibre agrandit les tuiles vectorielles du niveau 15 (aucune requête, aucun flou) : la source le déclare.
 */
export const BASEMAP_MAX_ZOOM = 15;

/** Niveau de zoom maximal des cartes de VFF (fond libre sur-zoomé de 15 à 19, comme les tuiles OpenStreetMap). */
export const MAP_MAX_ZOOM = 19;

/** Polices du fond libre, servies par VFF (gardées par le service worker, « cache d'abord »). */
export const BASEMAP_GLYPHS = '/basemap/fonts/{fontstack}/{range}.pbf';

/** Couches à pictogrammes, retirées : points d'intérêt, cartouches de routes, flèches de sens unique. */
const ICON_LAYERS = new Set(['pois', 'roads_shields', 'roads_oneway']);

/** Langues dont le fond libre affiche les noms (les autres prennent le français). */
const LANGS = ['fr', 'en', 'de'];

/** Fond « papier » de chaque thème : celui du fond neutre hors ligne (offlineMap.svelte.js). */
export const PAPER = { light: '#e7e3da', dark: '#1e2632' };

/** Palette claire : la base Protomaps « light », réchauffée et éteinte. */
export const VFF_LIGHT = {
    ...LIGHT,
    background: '#d5dde0',
    earth: PAPER.light,
    water: '#bfd0d6',
    park_a: '#dde2d3',
    park_b: '#cfd9c4',
    wood_a: '#dce1d1',
    wood_b: '#cdd8c2',
    scrub_a: '#dfe1d4',
    scrub_b: '#d3dac8',
    glacier: '#eeeeea',
    sand: '#e9e3d4',
    beach: '#ebe5d3',
    hospital: '#e6dfd8',
    industrial: '#dedcd6',
    school: '#e6e0d6',
    pedestrian: '#ebe7de',
    aerodrome: '#dddbd5',
    runway: '#eceae4',
    zoo: '#dbe0d4',
    military: '#dedbd5',
    pier: '#ebe7de',
    buildings: '#d8d3c8',
    railway: '#a29e96',
    boundaries: '#a8a39a',
    ocean_label: '#6d8590',
    city_label: '#4b4943',
    city_label_halo: PAPER.light,
    subplace_label: '#7d7970',
    subplace_label_halo: PAPER.light,
    state_label: '#a29d93',
    state_label_halo: PAPER.light,
    country_label: '#8f8a80',
    pois: undefined,
    landcover: {
        grassland: 'rgba(222, 226, 210, 1)',
        barren: 'rgba(233, 227, 212, 1)',
        urban_area: 'rgba(222, 218, 210, 1)',
        farmland: 'rgba(226, 226, 212, 1)',
        glacier: 'rgba(242, 242, 238, 1)',
        scrub: 'rgba(224, 226, 208, 1)',
        forest: 'rgba(212, 221, 202, 1)',
    },
};

/** Palette sombre : la base Protomaps « dark », bleutée comme le fond sombre du site. */
export const VFF_DARK = {
    ...DARK,
    background: '#151b24',
    earth: PAPER.dark,
    water: '#17202b',
    park_a: '#1f2b2f',
    park_b: '#21302f',
    wood_a: '#1f2a2f',
    wood_b: '#202d2e',
    scrub_a: '#202a31',
    scrub_b: '#212c30',
    glacier: '#253040',
    sand: '#232b35',
    beach: '#252d37',
    hospital: '#232a35',
    industrial: '#212833',
    school: '#232a35',
    pedestrian: '#222a35',
    aerodrome: '#222a35',
    runway: '#2c3542',
    zoo: '#202b31',
    military: '#222934',
    pier: '#2a3340',
    buildings: '#283140',
    railway: '#56606e',
    boundaries: '#5a6576',
    ocean_label: '#6f7f91',
    city_label: '#b9c0ca',
    city_label_halo: PAPER.dark,
    subplace_label: '#8a94a1',
    subplace_label_halo: PAPER.dark,
    state_label: '#5d6876',
    state_label_halo: PAPER.dark,
    country_label: '#7b8592',
    pois: undefined,
    landcover: {
        grassland: 'rgba(33, 44, 46, 1)',
        barren: 'rgba(36, 42, 50, 1)',
        urban_area: 'rgba(34, 40, 50, 1)',
        farmland: 'rgba(33, 42, 47, 1)',
        glacier: 'rgba(44, 52, 64, 1)',
        scrub: 'rgba(34, 43, 47, 1)',
        forest: 'rgba(31, 43, 45, 1)',
    },
};

/** Langue des noms du fond : celle de la page si le fond la connaît, sinon le français. */
export function basemapLang(lang) {
    const short = String(lang ?? '')
        .slice(0, 2)
        .toLowerCase();
    return LANGS.includes(short) ? short : 'fr';
}

/** Copie d'une couche sans ses propriétés de pictogramme ; null si elle ne dessinait qu'un pictogramme. */
function withoutIcons(layer) {
    if (ICON_LAYERS.has(layer.id)) return null;
    const entries = Object.entries(layer.layout ?? {});
    if (!entries.some(([key]) => key.startsWith('icon-'))) return layer;
    const layout = Object.fromEntries(entries.filter(([key]) => !key.startsWith('icon-')));
    if (layer.type === 'symbol' && !('text-field' in layout)) return null;
    return { ...layer, layout };
}

/**
 * Couches du fond libre pour un thème et une langue, sans aucun pictogramme.
 *
 * @param {{ dark?: boolean, lang?: string }} [options]
 * @returns {import('maplibre-gl').LayerSpecification[]}
 */
export function basemapLayers({ dark = false, lang = 'fr' } = {}) {
    const flavor = dark ? VFF_DARK : VFF_LIGHT;
    return /** @type {import('maplibre-gl').LayerSpecification[]} */ (
        layers(BASEMAP_SOURCE_ID, flavor, { lang: basemapLang(lang) })
            .map(withoutIcons)
            .filter(Boolean)
    );
}

/** Adresse absolue de l'archive, telle que le protocole `pmtiles://` l'attend. */
export function pmtilesUrl(url, origin = globalThis.location?.origin) {
    return `pmtiles://${origin ? new URL(url, origin).href : url}`;
}

/**
 * Style MapLibre complet du fond libre : la source PMTiles de VFF, ses couches, ses polices. Rien ne vient
 * d'un tiers. Le protocole `pmtiles://` doit être enregistré (lib/pmtilesProtocol.js) avant la carte.
 *
 * @param {{ url: string, attribution?: string, dark?: boolean, lang?: string, origin?: string }} options
 * @returns {import('maplibre-gl').StyleSpecification}
 */
export function basemapStyle({ url, attribution, dark = false, lang = 'fr', origin }) {
    return {
        version: 8,
        // Adresse absolue, gabarit {fontstack}/{range} intact (new URL encoderait les accolades).
        glyphs: `${origin ?? globalThis.location?.origin ?? ''}${BASEMAP_GLYPHS}`,
        sources: {
            [BASEMAP_SOURCE_ID]: {
                type: 'vector',
                url: pmtilesUrl(url, origin),
                maxzoom: BASEMAP_MAX_ZOOM,
                ...(attribution && { attribution }),
            },
        },
        layers: basemapLayers({ dark, lang }),
    };
}

/**
 * Change le thème d'une carte déjà dessinée sur le fond libre, sans recharger son style (les calques de
 * VFF restent en place) : seules les peintures des couches du fond changent.
 *
 * @param {{ getLayer(id: string): unknown, setPaintProperty(id: string, name: string, value: unknown): void }} map
 * @param {{ dark?: boolean, lang?: string }} options
 */
export function applyBasemapTheme(map, { dark = false, lang = 'fr' } = {}) {
    for (const layer of basemapLayers({ dark, lang })) {
        if (!map.getLayer(layer.id) || !('paint' in layer) || !layer.paint) continue;
        for (const [name, value] of Object.entries(layer.paint)) {
            map.setPaintProperty(layer.id, name, value);
        }
    }
}

/**
 * Le fond libre est-il à employer ? Hors ligne dès qu'une archive est servie ; en ligne seulement si
 * l'option `maps.basemap.online` est active.
 *
 * @param {{ url?: string, online?: boolean } | null | undefined} basemap propriété partagée `basemap`
 * @param {boolean} offline page lue sans réseau
 */
export function usesBasemap(basemap, offline) {
    return Boolean(basemap?.url) && (offline || basemap?.online === true);
}
