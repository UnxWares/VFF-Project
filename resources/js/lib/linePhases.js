// Phases d'une ligne (fiche ligne) : styles des catégories, état de la carte à une
// phase, ruban de composition, libellés. Fonctions pures, testées par Vitest
// (tests/js/linePhases.test.js). Données : App\Services\LinePhases (contrôleur).
//
// Catégorie d'une portion (même règle que la carte, cf. railStyle.js) :
//   en service : lgv | mixed | freight | unknown | heritage
//   hors service : closed | velorail | service_track | construction
//   voie disparue : dismantled | razed | gone
//   pas encore construite : none (jamais dessinée)

import {
    HERITAGE_COLOR,
    LGV_COLOR,
    STATUS_COLOR,
    TRAFFIC_COLOR,
    VELORAIL_DASH,
    swatchStyle,
} from './railStyle.js';

/** Catégories en service, dans l'ordre du ruban. */
export const ACTIVE_CATEGORIES = ['lgv', 'mixed', 'freight', 'unknown', 'heritage'];

/** Catégories de voie disparue. */
export const GONE_CATEGORIES = ['dismantled', 'razed', 'gone'];

/**
 * Style de chaque catégorie. `layer` = couche MapLibre (le motif de tirets ne peut
 * pas dépendre d'une propriété : une couche par motif).
 */
export const CATEGORY_STYLES = {
    lgv: {
        color: LGV_COLOR,
        dash: null,
        thick: true,
        layer: 'solid',
        labelKey: 'lines.categories.lgv',
    },
    mixed: {
        color: TRAFFIC_COLOR.mixed,
        dash: null,
        layer: 'solid',
        labelKey: 'lines.categories.mixed',
    },
    freight: {
        color: TRAFFIC_COLOR.freight,
        dash: null,
        layer: 'solid',
        labelKey: 'lines.categories.freight',
    },
    unknown: {
        color: TRAFFIC_COLOR.unknown,
        dash: null,
        layer: 'solid',
        labelKey: 'lines.categories.unknown',
    },
    heritage: {
        color: HERITAGE_COLOR,
        dash: null,
        layer: 'solid',
        labelKey: 'lines.categories.heritage',
    },
    velorail: {
        color: HERITAGE_COLOR,
        dash: VELORAIL_DASH,
        layer: 'velorail',
        labelKey: 'lines.categories.velorail',
    },
    service_track: {
        color: STATUS_COLOR.service_track,
        dash: null,
        layer: 'solid',
        labelKey: 'lines.categories.service_track',
    },
    construction: {
        color: STATUS_COLOR.construction,
        dash: [2, 1],
        layer: 'construction',
        labelKey: 'lines.categories.construction',
    },
    closed: {
        color: STATUS_COLOR.abandoned,
        dash: [2.5, 1.5],
        layer: 'closed',
        labelKey: 'lines.categories.closed',
    },
    dismantled: {
        color: STATUS_COLOR.dismantled,
        dash: [1, 1.5],
        layer: 'gone',
        labelKey: 'lines.categories.dismantled',
    },
    razed: {
        color: STATUS_COLOR.razed,
        dash: [0.6, 1.6],
        layer: 'gone',
        labelKey: 'lines.categories.razed',
    },
    gone: {
        color: STATUS_COLOR.razed,
        dash: [0.6, 1.6],
        layer: 'gone',
        labelKey: 'lines.categories.gone',
    },
};

/** Ordre du ruban : en service, puis hors service, puis disparu. */
export const STRIP_ORDER = [
    ...ACTIVE_CATEGORIES,
    'velorail',
    'service_track',
    'construction',
    'closed',
    'dismantled',
    'razed',
    'gone',
];

export function isActiveCategory(cat) {
    return ACTIVE_CATEGORIES.includes(cat);
}

export function categoryColor(cat) {
    return CATEGORY_STYLES[cat]?.color ?? TRAFFIC_COLOR.unknown;
}

/** Pastille de légende (trait plein, tirets ou pointillés). */
export function categorySwatch(cat) {
    const s = CATEGORY_STYLES[cat] ?? CATEGORY_STYLES.unknown;
    return swatchStyle(s.color, s.dash, !!s.thick);
}

/**
 * GeoJSON de la ligne à une phase : une entité par portion dessinée (les portions
 * pas encore construites sont omises), avec sa catégorie, sa couleur, sa couche et
 * `changed` si la phase la modifie.
 */
export function phaseFeatureCollection(portions, phase) {
    const changed = new Set();
    for (const c of phase?.changes ?? []) for (const i of c.portions) changed.add(i);
    const features = [];
    (portions ?? []).forEach((p, i) => {
        const cat = phase?.state?.[i] ?? 'none';
        if (cat === 'none' || !p?.geometry) return;
        const style = CATEGORY_STYLES[cat] ?? CATEGORY_STYLES.unknown;
        features.push({
            type: 'Feature',
            id: i,
            properties: {
                cat,
                color: style.color,
                layer: style.layer,
                thick: !!style.thick,
                changed: changed.has(i),
            },
            geometry: p.geometry,
        });
    });
    return { type: 'FeatureCollection', features };
}

/** Emprise [[ouest, sud], [est, nord]] de toutes les portions (vue stable d'une phase à l'autre). */
export function portionsBounds(portions) {
    let w = Infinity;
    let s = Infinity;
    let e = -Infinity;
    let n = -Infinity;
    for (const p of portions ?? []) {
        const g = p?.geometry;
        if (!g) continue;
        const lines =
            g.type === 'MultiLineString'
                ? g.coordinates
                : g.type === 'LineString'
                  ? [g.coordinates]
                  : [];
        for (const line of lines) {
            for (const [x, y] of line) {
                if (x < w) w = x;
                if (x > e) e = x;
                if (y < s) s = y;
                if (y > n) n = y;
            }
        }
    }
    return Number.isFinite(w)
        ? [
              [w, s],
              [e, n],
          ]
        : null;
}

/**
 * Ruban de composition d'une phase : segments { cat, m, pct } dans l'ordre STRIP_ORDER.
 * `scale` = longueur de référence (plus grande longueur connue de la ligne, toutes
 * phases) : le ruban s'allonge quand la ligne grandit.
 */
export function phaseStrip(phase, scale) {
    const byCat = phase?.length_by_category ?? {};
    const total = scale > 0 ? scale : Object.values(byCat).reduce((a, b) => a + b, 0);
    if (!total) return [];
    return STRIP_ORDER.filter((cat) => (byCat[cat] ?? 0) > 0).map((cat) => ({
        cat,
        m: byCat[cat],
        pct: Math.round((byCat[cat] / total) * 1000) / 10,
    }));
}

/** Plus grande longueur connue de la ligne (hors « none »), toutes phases. */
export function maxKnownLength(phases) {
    let max = 0;
    for (const p of phases ?? []) {
        const sum = Object.values(p.length_by_category ?? {}).reduce((a, b) => a + b, 0);
        if (sum > max) max = sum;
    }
    return max;
}

/** Index de la phase « au meilleur » : celui du serveur, sinon la plus grande longueur en service. */
export function bestPhaseIndex(phases, serverBest = null) {
    if (Number.isInteger(serverBest) && serverBest >= 0 && serverBest < (phases?.length ?? 0))
        return serverBest;
    let best = null;
    let max = -1;
    (phases ?? []).forEach((p, i) => {
        if ((p.active_m ?? 0) > max) {
            max = p.active_m ?? 0;
            best = i;
        }
    });
    return best;
}

/**
 * Kilomètres lisibles, à la même précision que le reste de la fiche (en-tête, histoire, tableaux) :
 * une décimale au plus, sans zéro inutile (« 13,8 km », « 124,7 km », « 125 km »). Avant : entier
 * au-delà de 100 km, d'où « 125 km » dans les phases contre « 124,7 » dans l'en-tête.
 */
export function formatKm(m, locale = 'fr') {
    const km = (m ?? 0) / 1000;
    return `${km.toLocaleString(locale, { maximumFractionDigits: 1 })} km`;
}

/**
 * Date d'un événement à sa précision (« 1938 », « mai 1938 », « 26 mai 1938 »),
 * formatée en UTC (sinon décalage d'un jour selon le fuseau du visiteur).
 */
export function formatEventDate(iso, precision = 'day', locale = 'fr') {
    const d = new Date(`${String(iso).slice(0, 10)}T00:00:00Z`);
    if (Number.isNaN(d.getTime())) return String(iso ?? '');
    if (precision === 'year') return String(d.getUTCFullYear());
    return d.toLocaleDateString(locale, {
        ...(precision === 'month' ? {} : { day: 'numeric' }),
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

/**
 * Libellé de date d'une phase : date de l'événement, ou intervalle pour un changement
 * non daté (« entre 1941 et 1950 », « depuis 2021 » pour l'ère courante).
 * `t` = fonction de traduction ($i18n.t).
 */
export function phaseDateLabel(phase, t, locale = 'fr') {
    if (!phase) return '';
    if (phase.precision !== 'range') return formatEventDate(phase.date, phase.precision, locale);
    if (phase.from_year == null) return t('lines.phases.before', { year: phase.to_year });
    if (phase.is_current) return t('lines.phases.between_today', { from: phase.from_year });
    return t('lines.phases.between', { from: phase.from_year, to: phase.to_year });
}

/** Année d'une phase (pour l'état des gares à l'ère correspondante). */
export function phaseEraYear(phase) {
    return phase?.to_year ?? null;
}

/**
 * Résumé d'une phase : changements regroupés par (avant → après), du plus long au
 * plus court, mineurs (ajustements de tracé) à part.
 */
export function summarizeChanges(phase) {
    const groups = new Map();
    let minorM = 0;
    for (const c of phase?.changes ?? []) {
        if (c.minor) {
            minorM += c.length_m;
            continue;
        }
        const key = `${c.from}>${c.to}`;
        const g = groups.get(key) ?? {
            from: c.from,
            to: c.to,
            length_m: 0,
            event_ids: [],
            modes: new Set(),
            mode_m: {},
            inconsistent: false,
        };
        g.length_m += c.length_m;
        const mode = c.mode ?? 'undated';
        g.mode_m[mode] = (g.mode_m[mode] ?? 0) + c.length_m;
        if (c.event_id != null && !g.event_ids.includes(c.event_id)) g.event_ids.push(c.event_id);
        if (c.mode) g.modes.add(c.mode);
        g.inconsistent ||= !!c.inconsistent;
        groups.set(key, g);
    }
    const list = [...groups.values()]
        .map((g) => ({ ...g, modes: [...g.modes] }))
        .sort((a, b) => b.length_m - a.length_m);
    return { changes: list, minor_m: minorM };
}

/**
 * Clé de traduction d'un changement : passage à l'état « to » depuis « from ».
 *   ouverture, fin du trafic voyageurs, fermeture, dépose, réouverture, patrimoine…
 */
export function changeVerbKey(from, to) {
    const fromActive = isActiveCategory(from);
    const toActive = isActiveCategory(to);
    if (to === 'heritage' || to === 'velorail') return 'lines.changes.heritage';
    if (toActive && from === 'none') return 'lines.changes.opening';
    if (toActive && !fromActive) return 'lines.changes.reopening';
    if (toActive && fromActive) {
        if (to === 'freight') return 'lines.changes.passenger_end';
        if (from === 'freight') return 'lines.changes.passenger_start';
        return 'lines.changes.traffic';
    }
    if (GONE_CATEGORIES.includes(to))
        return fromActive ? 'lines.changes.closure_removal' : 'lines.changes.removal';
    if (to === 'construction') return 'lines.changes.construction';
    if (from === 'none') return 'lines.changes.appears_closed';
    return 'lines.changes.closure';
}

/** Icône bootstrap-icons de la nature d'une phase (LinePhases::kind). */
export const PHASE_KIND_ICONS = {
    growth: 'bi-plus-circle-fill',
    traffic: 'bi-arrow-left-right',
    decline: 'bi-dash-circle-fill',
    removal: 'bi-x-circle',
    heritage: 'bi-train-front-fill',
    none: 'bi-circle',
};

/** Index de la phase suivante / précédente pour la navigation au clavier. */
export function stepPhase(index, delta, count) {
    if (!count) return null;
    const i = Number.isInteger(index) ? index : 0;
    return Math.min(count - 1, Math.max(0, i + delta));
}
