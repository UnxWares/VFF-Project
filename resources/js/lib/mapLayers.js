// Calques facultatifs de la carte (/maps) : lesquels sont affichés, et leur écriture dans l'adresse.
// Logique pure, testée par tests/js/mapLayers.test.js.
//
// Deux sortes de calques, qui se superposent :
// - les calques de données de VFF (accidents, passages à niveau, signalisation), cochés librement ;
// - un fond OpenRailwayMap (tuiles d'images) au plus : deux fonds d'images empilés se masqueraient.
//
// Adresse : un paramètre `layer` par calque, dans l'ordre de la liste ci-dessous
// (`?layer=accidents&layer=signalling&layer=orm-maxspeed`). Les anciennes adresses à un seul calque
// (`?layer=signalling`, lien de la fiche ligne) restent valables ; une liste séparée par des virgules
// est aussi lue.

/** Calques de données, de haut en bas sur la carte (les points au-dessus des lignes). */
export const DATA_LAYERS = ['accidents', 'level-crossings', 'signalling'];

/** Calques connus seulement pour le réseau d'aujourd'hui (données actuelles de SNCF Réseau). */
export const TODAY_ONLY_LAYERS = ['level-crossings', 'signalling'];

/** Fonds OpenRailwayMap (un seul à la fois). */
export const ORM_LAYERS = [
    'orm-standard',
    'orm-maxspeed',
    'orm-signals',
    'orm-electrification',
    'orm-gauge',
];

/** Aucun fond OpenRailwayMap. */
export const NO_ORM = 'none';

/** Clé de traduction du libellé d'un calque de données (`maps.overlay.*`). */
export const layerLabelKey = (value) => `maps.overlay.${value.replaceAll('-', '_')}`;

/** Calques de données dans l'ordre de DATA_LAYERS, sans doublon ni valeur inconnue. */
export function orderedData(values) {
    return DATA_LAYERS.filter((key) => values.includes(key));
}

/**
 * Calques demandés par l'adresse.
 * @param {string} search partie `?…` de l'adresse
 * @returns {{ data: string[], orm: string }}
 */
export function readLayers(search) {
    const values = new URLSearchParams(search)
        .getAll('layer')
        .flatMap((v) => v.split(','))
        .map((v) => v.trim());
    return {
        data: orderedData(values),
        orm: values.find((v) => ORM_LAYERS.includes(v)) ?? NO_ORM,
    };
}

/**
 * Adresse avec les calques donnés (les autres paramètres sont gardés).
 * @param {string} href adresse complète
 * @param {{ data: string[], orm: string }} layers
 */
export function writeLayers(href, { data, orm }) {
    const url = new URL(href);
    url.searchParams.delete('layer');
    for (const key of orderedData(data)) url.searchParams.append('layer', key);
    if (ORM_LAYERS.includes(orm)) url.searchParams.append('layer', orm);
    return url.toString();
}

/** Liste des calques de données après avoir coché (`on`) ou décoché un calque. */
export function toggleData(data, key, on) {
    const rest = data.filter((k) => k !== key);
    return orderedData(on ? [...rest, key] : rest);
}

/** Nombre de calques affichés (bouton « Calques »). */
export function layerCount({ data, orm }) {
    return orderedData(data).length + (ORM_LAYERS.includes(orm) ? 1 : 0);
}

/**
 * Calques réellement dessinés pour la carte affichée. Les passages à niveau et la signalisation ne
 * sont connus que pour le réseau d'aujourd'hui : sur une autre époque ils ne sont pas dessinés et
 * `todayOnly` garde ceux qui ont été demandés, pour le message et la légende.
 * @param {{ data: string[], orm: string }} layers calques demandés (readLayers)
 * @param {boolean} showingToday la carte montre le réseau d'aujourd'hui
 */
export function activeLayers({ data, orm }, showingToday) {
    const todayOnly = orderedData(data).filter((key) => TODAY_ONLY_LAYERS.includes(key));
    return {
        accidents: data.includes('accidents'),
        levelCrossings: showingToday && data.includes('level-crossings'),
        signalling: showingToday && data.includes('signalling'),
        todayOnly,
        legendOverlays: showingToday ? todayOnly : [],
        orm,
    };
}

/**
 * Messages posés sur la carte par les calques : échec du chargement des accidents, calques connus
 * seulement aujourd'hui (une phrase pour un calque, une autre pour les deux), aide au zoom des
 * passages à niveau. `textKey` est une clé de traduction.
 * @param {ReturnType<typeof activeLayers>} active
 * @param {{ showingToday: boolean, accidentsFailed: boolean, zoom: number, minZoom: number }} state
 * @returns {Array<{ key: string, textKey: string, icon?: string }>}
 */
export function layerNotices(active, { showingToday, accidentsFailed, zoom, minZoom }) {
    const out = [];
    if (active.accidents && accidentsFailed) {
        out.push({ key: 'accidents', textKey: 'maps.accidents.error' });
    }
    if (active.todayOnly.length && !showingToday) {
        const textKey =
            active.todayOnly.length > 1
                ? 'maps.layers.current_only_both'
                : active.todayOnly[0] === 'signalling'
                  ? 'maps.signalling.current_only'
                  : 'maps.level_crossings.current_only';
        out.push({ key: 'today', textKey });
    }
    if (active.levelCrossings && zoom < minZoom) {
        out.push({ key: 'zoom', textKey: 'maps.level_crossings.zoom_hint', icon: 'bi-zoom-in' });
    }
    return out;
}
