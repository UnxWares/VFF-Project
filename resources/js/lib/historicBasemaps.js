// Fonds de carte historiques IGN (Géoplateforme, WMTS sans clé).
//
// Licence : Licence Ouverte Etalab 2.0 (données IGN). Mention obligatoire :
// « Source : IGN - <couche> ». Le SCAN 25 n'est PAS libre : ne pas l'ajouter.
// La carte de Cassini est diffusée par l'IGN à partir des planches de la BnF
// (mention « IGN / BnF »).
//
// Paramètres (FORMAT, STYLE, niveaux de zoom) vérifiés par GetCapabilities
// le 2026-10-15 : https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetCapabilities
// Le jeu de tuiles « PM » (Pseudo-Mercator) est accepté pour toutes ces couches.

import i18next from 'i18next';

const WMTS = 'https://data.geopf.fr/wmts';

/**
 * Couches historiques, de la plus ancienne à la plus récente.
 * `key` : identifiant interne (stable, stocké dans les contributions).
 * `layer` : identifiant WMTS IGN. `labelKey` : clé i18n (lang/*\/editor.php).
 */
export const HISTORIC_BASEMAPS = [
    {
        key: 'ign-cassini',
        layer: 'BNF-IGNF_GEOGRAPHICALGRIDSYSTEMS.CASSINI',
        bounds: [-6.4, 40.93, 9.97, 51.89], // WGS84BoundingBox (GetCapabilities) : pas de requêtes hors emprise
        style: 'normal',
        format: 'image/png',
        minzoom: 6,
        maxzoom: 14,
        period: [1756, 1815],
        provider: 'IGN / BnF',
        title: 'Carte de Cassini',
        labelKey: 'editor.basemaps.cassini',
        titleKey: 'editor.basemaps.title_cassini',
    },
    {
        key: 'ign-etatmajor40',
        layer: 'GEOGRAPHICALGRIDSYSTEMS.ETATMAJOR40',
        bounds: [-6.09, 41.18, 10.97, 51.28], // WGS84BoundingBox (GetCapabilities) : pas de requêtes hors emprise
        style: 'normal',
        format: 'image/jpeg',
        minzoom: 6,
        maxzoom: 15,
        period: [1820, 1866],
        provider: 'IGN',
        title: "Carte de l'état-major (1820-1866)",
        labelKey: 'editor.basemaps.etatmajor40',
        titleKey: 'editor.basemaps.title_etatmajor40',
    },
    {
        key: 'ign-scan50-1950',
        layer: 'GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN50.1950',
        style: 'SCAN50_1950',
        format: 'image/jpeg',
        minzoom: 3,
        maxzoom: 15,
        period: [1950, 1950],
        provider: 'IGN',
        title: 'Carte 1950 (SCAN 50 historique)',
        labelKey: 'editor.basemaps.scan50_1950',
        titleKey: 'editor.basemaps.title_scan50_1950',
    },
    {
        key: 'ign-ortho-1950-1965',
        layer: 'ORTHOIMAGERY.ORTHOPHOTOS.1950-1965',
        style: 'BDORTHOHISTORIQUE',
        format: 'image/png',
        minzoom: 0,
        maxzoom: 18,
        period: [1950, 1965],
        provider: 'IGN',
        title: 'Photographies aériennes 1950-1965',
        labelKey: 'editor.basemaps.ortho_1950_1965',
        titleKey: 'editor.basemaps.title_ortho_1950_1965',
    },
    {
        key: 'ign-ortho-1965-1980',
        layer: 'ORTHOIMAGERY.ORTHOPHOTOS.1965-1980',
        bounds: [-6, 40, 10, 52], // WGS84BoundingBox (GetCapabilities) : pas de requêtes hors emprise
        style: 'BDORTHOHISTORIQUE',
        format: 'image/png',
        minzoom: 3,
        maxzoom: 18,
        period: [1965, 1980],
        provider: 'IGN',
        title: 'Photographies aériennes 1965-1980',
        labelKey: 'editor.basemaps.ortho_1965_1980',
        titleKey: 'editor.basemaps.title_ortho_1965_1980',
    },
    {
        key: 'ign-ortho-1980-1995',
        layer: 'ORTHOIMAGERY.ORTHOPHOTOS.1980-1995',
        bounds: [-5, 40, 10, 52], // WGS84BoundingBox (GetCapabilities) : pas de requêtes hors emprise
        style: 'BDORTHOHISTORIQUE',
        format: 'image/png',
        minzoom: 3,
        maxzoom: 18,
        period: [1980, 1995],
        provider: 'IGN',
        title: 'Photographies aériennes 1980-1995',
        labelKey: 'editor.basemaps.ortho_1980_1995',
        titleKey: 'editor.basemaps.title_ortho_1980_1995',
    },
];

export const HISTORIC_KEYS = HISTORIC_BASEMAPS.map((b) => b.key);

export function findHistoricBasemap(key) {
    return HISTORIC_BASEMAPS.find((b) => b.key === key) ?? null;
}

export function historicTileUrl(def) {
    return (
        `${WMTS}?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=${encodeURIComponent(def.layer)}` +
        `&STYLE=${def.style}&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}` +
        `&FORMAT=${encodeURIComponent(def.format)}`
    );
}

/**
 * Traduit une clé si le bundle de la langue courante est chargé ; sinon (tests,
 * appel avant l'initialisation) retourne le repli français fourni.
 */
function tr(key, vars, fallback) {
    return i18next.isInitialized && i18next.exists(key) ? i18next.t(key, vars) : fallback;
}

/** Titre de la couche dans la langue de la page (repli : titre français du catalogue). */
export function historicTitle(def) {
    return tr(def.titleKey, undefined, def.title);
}

/** Mention de source affichée sous le sélecteur et dans l'attribution MapLibre. */
export function historicAttribution(def) {
    const title = historicTitle(def);
    return tr(
        'editor.basemaps.source',
        { provider: def.provider, title },
        `Source : ${def.provider} - ${title} (Licence Ouverte 2.0)`,
    );
}

/**
 * Définitions au format OVERLAY_DEFS de RailMap (tiles / attribution / zooms),
 * pour que `overlay="ign-etatmajor40"` fonctionne aussi via la liste d'overlays.
 */
export const HISTORIC_OVERLAY_DEFS = Object.fromEntries(
    HISTORIC_BASEMAPS.map((def) => [
        def.key,
        {
            tiles: [historicTileUrl(def)],
            // Évaluée à l'usage : la langue n'est pas encore connue au chargement du module.
            get attribution() {
                return historicAttribution(def);
            },
            minzoom: def.minzoom,
            maxzoom: def.maxzoom,
            bounds: def.bounds,
        },
    ]),
);

export const HISTORIC_SOURCE_ID = 'vff-historic';
export const HISTORIC_LAYER_ID = 'vff-historic-layer';

// Couches « métier » au-dessus desquelles le fond historique ne doit jamais passer.
const DATA_LAYER_PREFIXES = ['vff-', 'ref-', 'draft-', 'edit-', 'draw-', 'diff-', 'snap-', 'sel-'];

function firstDataLayerId(map) {
    const layers = map.getStyle()?.layers ?? [];
    const hit = layers.find(
        (l) => l.id !== HISTORIC_LAYER_ID && DATA_LAYER_PREFIXES.some((p) => l.id.startsWith(p)),
    );
    return hit?.id;
}

/**
 * Affiche (ou retire si key = 'none') le fond historique sur une carte MapLibre,
 * juste sous les tracés VFF. Idempotent ; à rappeler à chaque changement.
 */
export function applyHistoricBasemap(map, key, opacity = 0.8) {
    if (!map) return;
    const run = () => {
        const def = findHistoricBasemap(key);
        const src = map.getSource(HISTORIC_SOURCE_ID);
        const currentKey = src ? map.getLayer(HISTORIC_LAYER_ID)?.metadata?.['vff:key'] : null;

        if (!def) {
            if (map.getLayer(HISTORIC_LAYER_ID)) map.removeLayer(HISTORIC_LAYER_ID);
            if (src) map.removeSource(HISTORIC_SOURCE_ID);
            return;
        }
        if (currentKey !== def.key) {
            if (map.getLayer(HISTORIC_LAYER_ID)) map.removeLayer(HISTORIC_LAYER_ID);
            if (map.getSource(HISTORIC_SOURCE_ID)) map.removeSource(HISTORIC_SOURCE_ID);
            map.addSource(HISTORIC_SOURCE_ID, {
                type: 'raster',
                tiles: [historicTileUrl(def)],
                tileSize: 256,
                minzoom: def.minzoom,
                maxzoom: def.maxzoom,
                ...(def.bounds ? { bounds: def.bounds } : {}),
                attribution: historicAttribution(def),
            });
            map.addLayer(
                {
                    id: HISTORIC_LAYER_ID,
                    type: 'raster',
                    source: HISTORIC_SOURCE_ID,
                    metadata: { 'vff:key': def.key },
                    paint: { 'raster-opacity': opacity, 'raster-fade-duration': 150 },
                },
                firstDataLayerId(map),
            );
            return;
        }
        map.setPaintProperty(HISTORIC_LAYER_ID, 'raster-opacity', opacity);
    };
    // `isStyleLoaded()` vaut false tant que des tuiles chargent : on teste le
    // chargement du style lui-même (addSource/addLayer y sont permis).
    if (styleReady(map)) run();
    else map.once('load', run);
}

export function styleReady(map) {
    return Boolean(map?.style?._loaded) || Boolean(map?.isStyleLoaded?.());
}
