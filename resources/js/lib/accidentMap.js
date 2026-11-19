// Calque « Accidents » de la carte (/maps) : données, filtre par époque, styles et bulle.
// Logique pure, testée par tests/js/accidentMap.test.js ; RailMap pose les couches, la page
// charge les données au premier affichage du calque.
import { accidentDate, tollText } from './accidentText.js';
import { escapeHtml } from '../Components/Map/lineInfo.js';

/** Points des accidents localisés (GET /api/v1/accidents.geojson, en cache 10 min). */
export const ACCIDENTS_URL = '/api/v1/accidents.geojson';

export const ACCIDENT_SOURCE_ID = 'vff-accidents';
/** Zone pâle autour du centre de la commune (lieu exact inconnu) : décor, jamais cliquable. */
export const ACCIDENT_AREA_LAYER_ID = 'vff-accidents-area';
/** Point de chaque accident (plein si le lieu est connu, anneau si seule la commune l'est). */
export const ACCIDENT_POINT_LAYER_ID = 'vff-accidents-points';

/**
 * Teinte unique et neutre (ardoise) : un sujet grave se montre sans rouge ni pictogramme. Elle se
 * distingue des couleurs du réseau (orange, violet, bleu, vert de railStyle.js) sans les imiter.
 */
export const ACCIDENT_COLOR = '#334155';
/** Contour clair des points, lisible sur le fond OpenStreetMap comme sur les tracés. */
export const ACCIDENT_HALO = '#ffffff';

/** Lieu connu à la commune près seulement (centre de la commune) ? */
export function isApproximate(precision) {
    return precision === 'commune';
}

/** Expression MapLibre : accident connu seulement à la commune près. */
const APPROXIMATE = ['==', ['get', 'precision'], 'commune'];

/**
 * Années montrées selon la vue de la carte, bornes incluses (null = pas de borne) :
 *   - une époque : tous les accidents jusqu'à l'année de l'ère (la carte de 1950 montre le
 *     réseau au 31/12/1950, donc les accidents survenus jusque-là) ;
 *   - comparaison : ceux survenus entre les deux ères (après la première, jusqu'à la seconde) ;
 *   - toutes les époques : tous.
 * @param {string} view 'era', 'compare' ou 'stack'
 * @param {number|null} era
 * @param {number|null} compareEra
 * @returns {{ from: number|null, to: number|null }}
 */
export function accidentYears(view, era, compareEra) {
    if (view === 'stack' || era == null) return { from: null, to: null };
    if (view === 'compare' && compareEra != null) {
        const [a, b] = era < compareEra ? [era, compareEra] : [compareEra, era];
        return { from: a + 1, to: b };
    }
    return { from: null, to: era };
}

/** Filtre MapLibre des années (`year` de chaque point) ; vrai pour tous si aucune borne. */
export function accidentYearFilter({ from, to }) {
    const clauses = [];
    if (from != null) clauses.push(['>=', ['get', 'year'], from]);
    if (to != null) clauses.push(['<=', ['get', 'year'], to]);
    return clauses.length ? ['all', ...clauses] : ['has', 'year'];
}

/** Accidents de la collection compris dans les années, pour le compte de la légende. */
export function countAccidents(collection, { from, to }) {
    return (collection?.features ?? []).filter((f) => {
        const y = f.properties.year;
        return (from == null || y >= from) && (to == null || y <= to);
    }).length;
}

/**
 * Couches du calque, de bas en haut. Rayon de la zone pâle : environ 3 km autour du centre de la
 * commune (au moins 7 px pour rester visible de loin), pour ne pas laisser croire à un lieu exact.
 */
export function accidentLayers() {
    return [
        {
            id: ACCIDENT_AREA_LAYER_ID,
            type: 'circle',
            source: ACCIDENT_SOURCE_ID,
            filter: APPROXIMATE,
            paint: {
                'circle-radius': [
                    'interpolate',
                    ['exponential', 2],
                    ['zoom'],
                    5,
                    7,
                    8,
                    8,
                    10,
                    28,
                    14,
                    450,
                ],
                'circle-color': ACCIDENT_COLOR,
                'circle-opacity': 0.12,
                'circle-stroke-color': ACCIDENT_COLOR,
                'circle-stroke-opacity': 0.45,
                'circle-stroke-width': 1,
                'circle-pitch-alignment': 'map',
            },
        },
        {
            id: ACCIDENT_POINT_LAYER_ID,
            type: 'circle',
            source: ACCIDENT_SOURCE_ID,
            paint: {
                'circle-radius': ['interpolate', ['linear'], ['zoom'], 5, 3.5, 9, 5, 13, 7],
                'circle-color': ['case', APPROXIMATE, ACCIDENT_HALO, ACCIDENT_COLOR],
                'circle-stroke-color': ['case', APPROXIMATE, ACCIDENT_COLOR, ACCIDENT_HALO],
                'circle-stroke-width': ['case', APPROXIMATE, 2, 1.5],
            },
        },
    ];
}

/**
 * Contenu de la bulle d'un accident : lieu en titre, puis date, nature, bilan, précision du lieu
 * quand il est approché, mention « à vérifier », et le lien vers la fiche (`link`, HTML déjà sûr).
 * @param {Record<string, any>} p propriétés du point (voir ACCIDENTS_URL)
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang
 * @param {string} link
 */
export function accidentPopupHtml(p, t, lang, link) {
    const place = p.place
        ? `${p.place}${p.department ? ` (${p.department})` : ''}`
        : t('accidents.kinds.other');
    const date = accidentDate(p.date, p.date_precision ?? 'day', lang);
    const toll = tollText(nullable(p.deaths), nullable(p.injuries), t);
    const rows = [
        `<li>${escapeHtml(date)}</li>`,
        `<li>${escapeHtml(t(`accidents.kinds.${p.kind}`))}</li>`,
        `<li>${escapeHtml(toll)}</li>`,
        isApproximate(p.precision)
            ? `<li class="rail-popup-note">${escapeHtml(t('accidents.location_precision.commune'))}</li>`
            : '',
        isTrue(p.to_review)
            ? `<li class="rail-popup-note">${escapeHtml(t('accidents.index.review_badge'))}</li>`
            : '',
    ].join('');
    return `<div class="rail-popup"><p class="rail-popup-title">${escapeHtml(place)}</p><ul>${rows}</ul>${link}</div>`;
}

// MapLibre rend les propriétés des points telles quelles, mais une valeur nulle peut revenir
// absente ou en chaîne « null » selon la source : on la normalise.
function nullable(v) {
    return v === undefined || v === null || v === 'null' ? null : Number(v);
}

function isTrue(v) {
    return v === true || v === 'true';
}
