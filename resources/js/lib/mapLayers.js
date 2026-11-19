// Calques facultatifs de la carte (/maps) : lesquels sont affichés, et leur écriture dans l'adresse.
// Logique pure, testée par tests/js/mapLayers.test.js.
//
// Deux sortes de calques, qui se superposent :
// - les calques de données de VFF (accidents, passages à niveau, signalisation, voies vertes), cochés librement ;
// - un fond OpenRailwayMap (tuiles d'images) au plus : deux fonds d'images empilés se masqueraient.
//
// Adresse : un paramètre `layer` par calque, dans l'ordre de la liste ci-dessous
// (`?layer=accidents&layer=signalling&layer=orm-maxspeed`). Les anciennes adresses à un seul calque
// (`?layer=signalling`, lien de la fiche ligne) restent valables ; une liste séparée par des virgules
// est aussi lue.

/** Calques de données, de haut en bas sur la carte (les points au-dessus des lignes). */
export const DATA_LAYERS = ['accidents', 'level-crossings', 'signalling', 'greenways'];

/**
 * Calques connus seulement pour le réseau d'aujourd'hui (données actuelles de SNCF Réseau). Les
 * voies vertes n'en font pas partie : elles montrent l'état actuel d'anciennes emprises, et se
 * superposent à toutes les époques (le réseau de l'époque est dessous). La signalisation non plus :
 * aujourd'hui l'état de SNCF Réseau, aux époques passées les mises en service datées relevées dans
 * Wikipédia ; elle se lit dans la vue « une époque » seulement (ni comparaison, ni ères empilées).
 */
export const TODAY_ONLY_LAYERS = ['level-crossings'];

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
 * Calques réellement dessinés pour la carte affichée. Les passages à niveau ne sont connus que pour
 * le réseau d'aujourd'hui : sur une autre époque ils ne sont pas dessinés et `todayOnly` les garde,
 * pour le message. La signalisation est dessinée dans la vue « une époque » : état de SNCF Réseau
 * aujourd'hui, historique daté (`signallingHistory`) aux époques passées ; hors de cette vue,
 * `eraViewOnly` la garde pour le message. `legendOverlays` : calques à décrire dans la légende.
 * @param {{ data: string[], orm: string }} layers calques demandés (readLayers)
 * @param {boolean} showingToday la carte montre le réseau d'aujourd'hui
 * @param {boolean} [eraView] la carte montre une seule époque (vue « une époque »)
 */
export function activeLayers({ data, orm }, showingToday, eraView = showingToday) {
    const todayOnly = orderedData(data).filter((key) => TODAY_ONLY_LAYERS.includes(key));
    const signalling = eraView && data.includes('signalling');
    return {
        accidents: data.includes('accidents'),
        levelCrossings: showingToday && data.includes('level-crossings'),
        signalling,
        signallingHistory: signalling && !showingToday,
        greenways: data.includes('greenways'),
        todayOnly,
        eraViewOnly: !eraView && data.includes('signalling') ? ['signalling'] : [],
        legendOverlays: DATA_LAYERS.filter(
            (key) =>
                (key === 'level-crossings' && showingToday && todayOnly.includes(key)) ||
                (key === 'signalling' && signalling) ||
                (key === 'greenways' && data.includes(key)),
        ),
        orm,
    };
}

/**
 * Messages posés sur la carte par les calques : échec du chargement des accidents, passages à niveau
 * connus seulement aujourd'hui, signalisation lisible seulement dans la vue « une époque », nature de
 * la signalisation d'une époque passée (historique daté, partiel), aide au zoom des passages à niveau.
 * `textKey` est une clé de traduction.
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
        out.push({ key: 'today', textKey: 'maps.level_crossings.current_only' });
    }
    if (active.eraViewOnly?.length) {
        out.push({ key: 'era-view', textKey: 'maps.signalling.era_view_only' });
    }
    if (active.signallingHistory) {
        out.push({
            key: 'signalling-history',
            textKey: 'maps.signalling.history_notice',
            icon: 'bi-clock-history',
        });
    }
    if (active.levelCrossings && zoom < minZoom) {
        out.push({ key: 'zoom', textKey: 'maps.level_crossings.zoom_hint', icon: 'bi-zoom-in' });
    }
    return out;
}
