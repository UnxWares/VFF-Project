/**
 * Coloration des tracés de la carte /maps (« Colorer les tracés selon ») : adresse partageable
 * (`?color=speed`), colorations possibles à une époque, libellés écrits le long des tracés et classes de la
 * liste de ce qui est affiché. Logique pure, testée dans tests/js/lineColoring.test.js.
 *
 * Une coloration thématique n'est proposée que si la donnée datée existe à l'époque affichée
 * (`colorings` de chaque ère, App\Http\Controllers\MapController::coloringsByEra) ; sinon le choix est
 * grisé, une phrase dit pour quelles époques elle existe, et la carte garde la coloration par trafic.
 */
import {
    ELECTRIFICATION_STYLES,
    GAUGE_STYLES,
    MAP_COLORINGS,
    SPEED_STYLES,
    TRAFFIC_STYLES,
} from '$lib/railStyle.js';
import {
    SIGNALLING_FAMILIES,
    SIGNALLING_SOURCE_ID,
    SIGNALLING_SOURCE_LAYER,
} from '$lib/signallingMap.js';

/** Paramètre de l'adresse. */
export const COLOR_PARAM = 'color';
/** Coloration par défaut (absente de l'adresse). */
export const DEFAULT_COLORING = 'traffic';
/** Colorations qui dépendent d'une donnée datée (les autres existent à toutes les époques). */
export const DATED_COLORINGS = ['speed', 'electrification', 'signalling'];
/** Couche des libellés écrits le long des tracés (vitesse, tension, mode de cantonnement). */
export const COLORING_LABEL_LAYER_ID = 'vff-coloring-labels';
/** Zoom à partir duquel les libellés apparaissent (assez de place le long des tracés). */
export const COLORING_LABEL_MIN_ZOOM = 9;

/**
 * Coloration demandée par l'adresse ; la coloration par défaut si absente ou inconnue.
 * @param {string} search chaîne de requête
 */
export function readColoring(search) {
    const value = new URLSearchParams(search).get(COLOR_PARAM);
    return MAP_COLORINGS.includes(value) ? value : DEFAULT_COLORING;
}

/**
 * Adresse avec la coloration (retirée pour la coloration par défaut ; les autres paramètres sont gardés).
 * @param {string} href adresse complète
 * @param {string} coloring
 */
export function writeColoring(href, coloring) {
    const url = new URL(href);
    if (MAP_COLORINGS.includes(coloring) && coloring !== DEFAULT_COLORING) {
        url.searchParams.set(COLOR_PARAM, coloring);
    } else {
        url.searchParams.delete(COLOR_PARAM);
    }
    return url.toString();
}

/**
 * Années d'ère où une coloration existe, de la plus ancienne à la plus récente.
 * @param {string} coloring
 * @param {Array<{year: number, colorings?: string[], has_data?: boolean}> | null} eras ères de la page
 */
export function coloringYears(coloring, eras) {
    return (eras ?? [])
        .filter((e) =>
            DATED_COLORINGS.includes(coloring)
                ? Array.isArray(e.colorings) && e.colorings.includes(coloring)
                : e.has_data !== false,
        )
        .map((e) => e.year);
}

/**
 * Une coloration est-elle possible pour les époques affichées (toutes) ? Les colorations non datées le
 * sont toujours ; la signalisation n'est dessinée que sur une seule époque (`single`).
 * @param {string} coloring
 * @param {number[]} shown années affichées (une, ou deux avec le rideau ; aucune hors des cartes d'époque)
 * @param {Array<{year: number, colorings?: string[]}> | null} eras
 */
export function coloringAvailable(coloring, shown, eras) {
    if (!MAP_COLORINGS.includes(coloring)) return false;
    if (!DATED_COLORINGS.includes(coloring)) return true;
    if (shown.length === 0) return false;
    if (coloring === 'signalling' && shown.length !== 1) return false;
    const years = coloringYears(coloring, eras);
    return shown.every((y) => years.includes(y));
}

/**
 * Coloration dessinée : celle choisie si elle est possible, sinon la coloration par défaut.
 * @param {string} coloring
 * @param {number[]} shown
 * @param {Array<{year: number, colorings?: string[]}> | null} eras
 */
export function drawnColoring(coloring, shown, eras) {
    return coloringAvailable(coloring, shown, eras) ? coloring : DEFAULT_COLORING;
}

/**
 * Explication d'une coloration impossible aux époques affichées : clé de traduction et paramètres, ou
 * null si elle est possible. `years` : texte des années où elle existe (« 2020 et aujourd'hui »).
 * @param {string} coloring
 * @param {number[]} shown
 * @param {Array<{year: number, colorings?: string[]}> | null} eras
 * @param {(years: number[]) => string} formatYears mise en mots des années
 * @returns {{ key: string, params: Record<string, string> } | null}
 */
export function coloringNotice(coloring, shown, eras, formatYears) {
    if (coloringAvailable(coloring, shown, eras)) return null;
    if (coloring === 'signalling' && shown.length > 1) {
        return { key: 'maps.coloring.unavailable.one_era', params: {} };
    }
    const years = coloringYears(coloring, eras);
    if (years.length === 0)
        return { key: `maps.coloring.unavailable.${coloring}_none`, params: {} };
    return {
        key: `maps.coloring.unavailable.${coloring}`,
        params: { years: formatYears(years) },
    };
}

/**
 * Années en mots : « 2020 et aujourd'hui », « 1950, 1960 et 1970 », « de 1900 à aujourd'hui » au-delà
 * de trois années qui se suivent.
 * @param {number[]} years
 * @param {number} today ère d'aujourd'hui
 * @param {(key: string, params?: object) => string} t traduction
 */
export function formatColoringYears(years, today, t) {
    const word = (y) => (y === today ? t('maps.compare.today_inline') : String(y));
    if (years.length === 0) return '';
    if (years.length > 3) {
        return t('maps.coloring.years_range', {
            from: word(years[0]),
            to: word(years[years.length - 1]),
        });
    }
    if (years.length === 1) return word(years[0]);
    const head = years.slice(0, -1).map(word).join(', ');
    return t('maps.coloring.years_and', { head, last: word(years[years.length - 1]) });
}

/** Lignes de légende (et de la liste) d'une coloration : clé, couleur, clé de traduction. */
export function coloringStyles(coloring) {
    if (coloring === 'speed') return SPEED_STYLES;
    if (coloring === 'electrification') return ELECTRIFICATION_STYLES;
    if (coloring === 'gauge') return GAUGE_STYLES;
    if (coloring === 'signalling') {
        return SIGNALLING_FAMILIES.map((f) => ({
            key: f.key,
            color: f.color,
            dash: f.dash,
            labelKey: `maps.signalling.families.${f.key}`,
        }));
    }
    return TRAFFIC_STYLES.map((s) => ({ ...s, labelKey: `maps.traffic.${s.key}` }));
}

/**
 * Style d'une classe donnée par la liste de ce qui est affiché (`classes[].key`), ou null pour une clé
 * inconnue de la coloration.
 * @param {string} coloring
 * @param {string} key
 */
export function coloringClass(coloring, key) {
    return coloringStyles(coloring).find((s) => s.key === key) ?? null;
}

/** Tension en mots courts pour un libellé de carte (« 25 kV », « 1,5 kV », « 750 V »). */
export function voltageText(volts, lang = 'fr') {
    const v = Number(volts);
    if (!Number.isFinite(v) || v <= 0) return '';
    if (v < 1000) return `${v} V`;
    return `${(v / 1000).toLocaleString(lang, { maximumFractionDigits: 2 })} kV`;
}

// Caractères des plages de glyphes servies par le fond (public/basemap/fonts : pas de « ≤ », U+2264).
const SPEED_LABELS = { 1: '0-100 km/h', 2: '100-160 km/h', 3: '160-220 km/h', 4: '> 220 km/h' };
const LABEL_VOLTAGES = [25000, 15000, 3000, 1500, 850, 800, 750];

/**
 * Couche des libellés écrits le long des tracés : la classe de vitesse, la tension ou le mode de
 * cantonnement, en plus de la couleur (l'information n'est jamais portée par la seule couleur), à partir du
 * zoom COLORING_LABEL_MIN_ZOOM. null pour une coloration sans libellé (trafic, écartement).
 * @param {string} coloring coloration dessinée
 * @param {{ font: string[], lang?: string }} options police des libellés (celle des gares) et langue
 */
export function coloringLabelLayer(coloring, { font, lang = 'fr' }) {
    const common = {
        id: COLORING_LABEL_LAYER_ID,
        type: 'symbol',
        minzoom: COLORING_LABEL_MIN_ZOOM,
        layout: {
            'symbol-placement': 'line',
            'symbol-spacing': 250,
            'text-size': 13,
            'text-font': font,
            'text-max-angle': 40,
            'text-padding': 4,
        },
        paint: { 'text-color': '#1a1a1a', 'text-halo-color': '#ffffff', 'text-halo-width': 2 },
    };
    if (coloring === 'speed') {
        return {
            ...common,
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: ['all', ['has', 'speed_class'], ['!', ['to-boolean', ['get', 'covered']]]],
            layout: {
                ...common.layout,
                'text-field': [
                    'match',
                    ['to-number', ['get', 'speed_class']],
                    ...Object.entries(SPEED_LABELS).flatMap(([cls, text]) => [Number(cls), text]),
                    '',
                ],
            },
        };
    }
    if (coloring === 'electrification') {
        return {
            ...common,
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: [
                'all',
                [
                    'any',
                    ['==', ['get', 'electrified'], true],
                    ['==', ['get', 'electrified'], 'true'],
                ],
                ['!', ['to-boolean', ['get', 'covered']]],
            ],
            layout: {
                ...common.layout,
                'text-field': [
                    'match',
                    ['to-number', ['coalesce', ['get', 'voltage_v'], 0]],
                    ...LABEL_VOLTAGES.flatMap((v) => [v, voltageText(v, lang)]),
                    '',
                ],
            },
        };
    }
    if (coloring === 'signalling') {
        return {
            ...common,
            source: SIGNALLING_SOURCE_ID,
            'source-layer': SIGNALLING_SOURCE_LAYER,
            filter: ['has', 'family'],
            layout: { ...common.layout, 'text-field': ['coalesce', ['get', 'signalling'], ''] },
        };
    }
    return null;
}
