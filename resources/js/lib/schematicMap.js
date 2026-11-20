// Vue ferroviaire de /maps : plan schématique d'une époque, calculé par le serveur
// (GET /api/v1/eras/{year}/schematic.geojson, App\Services\Schematic) et dessiné par
// Components/Map/SchematicMap.svelte. Logique pure (couleurs, couches MapLibre, filtres, fiche de la
// ligne cliquée, résumé lu par les lecteurs d'écran), testée dans tests/js/schematicMap.test.js.
//
// Les couleurs des axes sont celles de la carte (railStyle.js : trafic de la portion, violet de la
// grande vitesse, vert des lignes associatives). Sur le fond sombre du plan, le gris du trafic
// inconnu (#475569, 2,4:1) est éclairci, seule couleur qui n'atteignait pas 3:1 ; la légende de la
// vue reprend les mêmes valeurs (categoryColors).

import { CASING_COLOR, CASING_OPACITY } from './lineCasing.js';
import { HERITAGE_COLOR, LGV_COLOR, TRAFFIC_COLOR } from './railStyle.js';

/** Valeur de `?view=` de la vue ferroviaire. */
export const SCHEMA_VIEW = 'schema';

/** Catégories des axes, dans l'ordre de la légende (mêmes clés que les filtres de la carte). */
export const SCHEMA_CATEGORIES = ['lgv', 'mixed', 'freight', 'unknown', 'heritage'];

/** Gris du trafic inconnu sur le fond sombre du plan (7,4:1). */
const UNKNOWN_ON_DARK = '#94a3b8';

/**
 * Fond, encre (gares, noms) et liseré des axes du plan, par thème. Clair : fond « papier » du site et
 * liseré foncé de la carte (lineCasing.js), qui donne 3:1 au moins à l'orange et au vert. Sombre : fond
 * de page du thème sombre ; le liseré prend la couleur du fond et ne sert qu'à détacher les axes qui se
 * croisent.
 */
export const SCHEMA_PALETTES = {
    light: {
        background: '#fcfbf8',
        ink: '#25221e',
        casing: CASING_COLOR,
        casingOpacity: CASING_OPACITY,
    },
    dark: {
        background: '#0f1419',
        ink: '#e8ecec',
        casing: '#0f1419',
        casingOpacity: 1,
    },
};

/**
 * Zoom à partir duquel chaque niveau d'étiquette (`tier`, SchematicMap::LABEL_TIERS) apparaît : les
 * grandes villes dès la France entière.
 */
export const LABEL_MIN_ZOOM = [0, 6.3, 7.6, 9.2];

/**
 * Plan étroit (téléphone, moins de NARROW_PX de large) : la France entière y tient en 300 px, trop peu
 * pour les noms des grandes villes, coupés au bord ; ils viennent au premier zoom (NARROW_LABEL_ZOOM).
 */
export const NARROW_PX = 600;
export const NARROW_LABEL_ZOOM = 4.6;

/** Zoom à partir duquel les noms des grandes villes apparaissent, selon la largeur du plan. */
export function cityLabelMinZoom(width) {
    return width < NARROW_PX ? NARROW_LABEL_ZOOM : LABEL_MIN_ZOOM[0];
}

/**
 * Zoom à partir duquel les nœuds (cercles) de chaque niveau apparaissent : sur la France entière, seuls
 * ceux des grandes villes ; les gares intermédiaires (traits) seulement en zoomant.
 */
export const NODE_MIN_ZOOM = [0, 6, 7.2];
export const STOP_MIN_ZOOM = 7.4;

/** Identifiants des couches et de l'image du trait de gare. */
export const SCHEMA_SOURCE = 'vff-schema';
export const SCHEMA_TICK_IMAGE = 'vff-schema-tick';
export const SCHEMA_LAYERS = {
    casing: 'vff-schema-casing',
    selected: 'vff-schema-selected',
    axes: 'vff-schema-axes',
    stops: 'vff-schema-stops',
};
export const SCHEMA_NODE_LAYERS = NODE_MIN_ZOOM.map((_, tier) => `vff-schema-nodes-${tier}`);
export const SCHEMA_LABEL_LAYERS = LABEL_MIN_ZOOM.map((_, tier) => `vff-schema-labels-${tier}`);

/** Couches cliquables : les axes ouvrent la fiche de la ligne, les gares leur bulle. */
export const SCHEMA_AXIS_LAYERS = [SCHEMA_LAYERS.axes];
export const SCHEMA_STATION_LAYERS = [...SCHEMA_NODE_LAYERS, SCHEMA_LAYERS.stops];

/** @param {boolean} dark */
export function paletteFor(dark) {
    return dark ? SCHEMA_PALETTES.dark : SCHEMA_PALETTES.light;
}

/**
 * Couleur de chaque catégorie d'axe pour un thème.
 *
 * @param {boolean} dark
 * @returns {Record<string, string>}
 */
export function categoryColors(dark) {
    return {
        lgv: LGV_COLOR,
        mixed: TRAFFIC_COLOR.mixed,
        freight: TRAFFIC_COLOR.freight,
        unknown: dark ? UNKNOWN_ON_DARK : TRAFFIC_COLOR.unknown,
        heritage: HERITAGE_COLOR,
    };
}

/** Adresse du plan d'une époque. */
export function schematicUrl(year) {
    return `/api/v1/eras/${encodeURIComponent(year)}/schematic.geojson`;
}

/** Expression MapLibre : couleur d'un axe selon sa catégorie. */
export function axisColorExpr(dark) {
    const colors = categoryColors(dark);
    return [
        'match',
        ['get', 'category'],
        ...SCHEMA_CATEGORIES.flatMap((key) => [key, colors[key]]),
        colors.unknown,
    ];
}

/** Épaisseur des axes selon le zoom (plus nette que la carte : le plan n'a pas de fond à lire). */
export function axisWidthExpr(extra = 0) {
    return [
        'interpolate',
        ['linear'],
        ['zoom'],
        4,
        1.2 + extra,
        5.5,
        1.6 + extra,
        7,
        2.6 + extra,
        9,
        4 + extra,
        11,
        6 + extra,
        14,
        9 + extra,
    ];
}

/**
 * Filtre des axes : catégories masquées par la légende (`hidden`, mêmes clés que la carte).
 *
 * @param {string[]} hidden
 */
export function axisFilter(hidden = []) {
    const masked = SCHEMA_CATEGORIES.filter((key) => hidden.includes(key));
    const isAxis = ['==', ['get', 'kind'], 'axis'];
    if (!masked.length) return isAxis;
    return ['all', isAxis, ['!', ['in', ['get', 'category'], ['literal', masked]]]];
}

/** Filtre de la ligne mise en évidence (fiche latérale ouverte). */
export function selectedFilter(lineId) {
    return ['all', ['==', ['get', 'kind'], 'axis'], ['==', ['get', 'line_id'], lineId ?? -1]];
}

/**
 * Couches MapLibre du plan, de bas en haut : liseré, ligne choisie, axes, traits des gares
 * intermédiaires, nœuds (cercles), noms par niveau d'importance.
 *
 * @param {{ dark: boolean, hidden?: string[], selected?: number | null, width?: number }} options
 *   `width` : largeur du plan en pixels (noms des grandes villes, cityLabelMinZoom)
 */
export function schematicLayers({ dark, hidden = [], selected = null, width = 1280 }) {
    const palette = paletteFor(dark);
    const isStation = ['==', ['get', 'kind'], 'station'];
    const nodePaint = {
        'circle-radius': ['interpolate', ['linear'], ['zoom'], 4, 2, 6, 2.8, 8, 4, 11, 6, 14, 8],
        'circle-color': palette.background,
        'circle-stroke-color': palette.ink,
        'circle-stroke-width': ['interpolate', ['linear'], ['zoom'], 4, 1.2, 9, 2],
    };
    // Un calque de nœuds par niveau : tier 0 (grandes villes) partout, les suivants en zoomant ; le
    // dernier regroupe tous les autres nœuds (étiquettes de niveau 2 et 3).
    const nodeLayers = NODE_MIN_ZOOM.map((minzoom, level) => ({
        id: SCHEMA_NODE_LAYERS[level],
        type: 'circle',
        source: SCHEMA_SOURCE,
        minzoom,
        filter: [
            'all',
            isStation,
            ['!=', ['get', 'role'], 'stop'],
            ['get', 'main'],
            level < NODE_MIN_ZOOM.length - 1
                ? ['==', ['get', 'tier'], level]
                : ['>=', ['get', 'tier'], level],
        ],
        paint: nodePaint,
    }));
    const labelLayers = LABEL_MIN_ZOOM.map((minzoom, tier) => ({
        id: SCHEMA_LABEL_LAYERS[tier],
        type: 'symbol',
        source: SCHEMA_SOURCE,
        minzoom: tier === 0 ? cityLabelMinZoom(width) : minzoom,
        filter: ['all', isStation, ['==', ['get', 'tier'], tier]],
        layout: {
            'text-field': ['get', 'name'],
            'text-font': [tier < 2 ? 'Noto Sans Bold' : 'Noto Sans Regular'],
            'text-size': tier < 2 ? 14 : 13,
            'text-variable-anchor': ['left', 'right', 'top', 'bottom'],
            'text-radial-offset': 0.7,
            'text-justify': 'auto',
            'text-max-width': 12,
            'symbol-sort-key': ['-', 0, ['get', 'rank']],
            'text-padding': 3,
        },
        paint: {
            'text-color': palette.ink,
            'text-halo-color': palette.background,
            'text-halo-width': 2,
        },
    }));

    return [
        {
            id: SCHEMA_LAYERS.casing,
            type: 'line',
            source: SCHEMA_SOURCE,
            filter: axisFilter(hidden),
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': palette.casing,
                'line-opacity': palette.casingOpacity,
                'line-gap-width': axisWidthExpr(),
                'line-width': ['interpolate', ['linear'], ['zoom'], 4, 0.25, 8, 0.5, 11, 1],
            },
        },
        {
            id: SCHEMA_LAYERS.selected,
            type: 'line',
            source: SCHEMA_SOURCE,
            filter: selectedFilter(selected),
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': palette.ink, 'line-width': axisWidthExpr(5) },
        },
        {
            id: SCHEMA_LAYERS.axes,
            type: 'line',
            source: SCHEMA_SOURCE,
            filter: axisFilter(hidden),
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': axisColorExpr(dark), 'line-width': axisWidthExpr() },
        },
        {
            id: SCHEMA_LAYERS.stops,
            type: 'symbol',
            source: SCHEMA_SOURCE,
            minzoom: STOP_MIN_ZOOM,
            filter: ['all', isStation, ['==', ['get', 'role'], 'stop'], ['get', 'main']],
            layout: {
                'icon-image': SCHEMA_TICK_IMAGE,
                // Trait perpendiculaire à l'axe : `angle` est compté depuis l'est, sens direct ;
                // MapLibre tourne les icônes dans le sens des aiguilles d'une montre.
                'icon-rotate': ['-', 0, ['get', 'angle']],
                'icon-rotation-alignment': 'map',
                'icon-size': [
                    'interpolate',
                    ['linear'],
                    ['zoom'],
                    STOP_MIN_ZOOM,
                    0.75,
                    11,
                    1,
                    14,
                    1.3,
                ],
                'icon-allow-overlap': true,
                'icon-ignore-placement': true,
            },
        },
        ...nodeLayers,
        ...labelLayers,
    ];
}

/**
 * Trait d'une gare intermédiaire (image RGBA pour `map.addImage`) : barre verticale de l'encre du
 * thème, tournée ensuite selon l'axe. Taille en pixels physiques (pixelRatio 2).
 *
 * @param {string} ink couleur #rrggbb
 * @returns {{ width: number, height: number, data: Uint8Array }}
 */
export function tickImage(ink, { width = 4, height = 24 } = {}) {
    const [r, g, b] = [1, 3, 5].map((i) => Number.parseInt(ink.slice(i, i + 2), 16));
    const data = new Uint8Array(width * height * 4);
    for (let i = 0; i < width * height; i++) data.set([r, g, b, 255], i * 4);
    return { width, height, data };
}

/**
 * Ligne cliquée sur le plan, sous la forme qu'attend la fiche latérale (LineDrawer) : les champs de
 * la ligne viennent du membre `lines` du plan, le trafic de la catégorie de l'axe.
 *
 * @param {{ line_id?: number | null, category?: string }} props propriétés de l'axe
 * @param {Record<string, any> | undefined} lines membre `lines` de la réponse
 * @param {number} era
 */
export function drawerLine(props, lines, era) {
    const info = (props.line_id != null && lines?.[props.line_id]) || {};
    const category = props.category ?? 'unknown';
    return {
        slug: info.slug ?? null,
        name: info.name ?? null,
        code: info.code ?? null,
        status: 'in_service',
        traffic_type:
            category === 'lgv'
                ? 'passenger'
                : ['mixed', 'freight'].includes(category)
                  ? category
                  : 'unknown',
        is_lgv: category === 'lgv' || info.is_lgv === true,
        network: info.network ?? null,
        heritage_kind: category === 'heritage' ? 'tourist_train' : null,
        era,
        max_speed_kmh: info.max_speed_kmh ?? null,
        electrified: info.electrified ?? null,
        voltage_v: info.voltage_v ?? null,
    };
}

/** Identifiant de ligne d'un slug (ligne mise en évidence), ou null. */
export function lineIdOf(slug, lines) {
    if (!slug || !lines) return null;
    const entry = Object.entries(lines).find(([, info]) => info?.slug === slug);
    return entry ? Number(entry[0]) : null;
}

/** Position d'une gare sur le plan (recherche d'une gare), ou null si elle n'y figure pas. */
export function stationPosition(data, slug) {
    if (!slug) return null;
    const feature = data?.features?.find(
        (f) => f.properties?.kind === 'station' && f.properties.slug === slug,
    );
    return feature ? feature.geometry.coordinates : null;
}

/**
 * Résumé du plan pour les lecteurs d'écran (équivalent textuel de l'image) : longueur en service par
 * catégorie, de la plus longue à la plus courte, et nombre de gares.
 *
 * @param {{ summary?: { km?: Record<string, number>, stations?: number } } | null} data
 * @returns {{ total: number, stations: number, parts: { key: string, km: number }[] } | null}
 */
export function summaryParts(data) {
    const km = data?.summary?.km;
    if (!km) return null;
    const parts = SCHEMA_CATEGORIES.filter((key) => (km[key] ?? 0) > 0)
        .map((key) => ({ key, km: km[key] }))
        .sort((a, b) => b.km - a.km);
    return {
        total: parts.reduce((sum, p) => sum + p.km, 0),
        stations: data.summary.stations ?? 0,
        parts,
    };
}
