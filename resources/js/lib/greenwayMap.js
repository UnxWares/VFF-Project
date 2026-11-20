// Calque « Voies vertes » de la carte (/maps, /embed/maps) : voies vertes et chemins cyclables
// d'OpenStreetMap installés sur l'emprise d'une ligne disparue ou fermée (tuiles de
// GET /api/v1/greenways/tiles/{z}/{x}/{y}.mvt, GreenwayTiles côté serveur). Logique pure, testée par
// tests/js/greenwayMap.test.js ; RailMap pose la source et les couches au premier affichage.
//
// Rendu distinct des tracés ferroviaires : un trait plein rose foncé avec un liseré blanc (les
// lignes ont un liseré sombre, aucune n'est rose), jamais en tirets ni en pointillés (réservés aux
// lignes fermées et déposées), plus fin que le réseau. Le liseré blanc le détache aussi des chemins
// bleus pointillés du fond OpenStreetMap.
import { escapeHtml } from '../Components/Map/lineInfo.js';
import { colon, formatLocale } from './typo.js';
import { formatKm } from './linePhases.js';
import { OVERLAY_HALO } from './overlayPalette.js';

export const GREENWAY_SOURCE_ID = 'vff-greenways';
export const GREENWAY_SOURCE_LAYER = 'vff-greenways';
export const GREENWAY_CASING_LAYER_ID = 'vff-greenways-casing';
export const GREENWAY_LINE_LAYER_ID = 'vff-greenways-line';
/** Les deux couches du calque, de bas en haut (pour les masquer ou les interroger d'un coup). */
export const GREENWAY_LAYER_IDS = [GREENWAY_CASING_LAYER_ID, GREENWAY_LINE_LAYER_ID];

/** Rose foncé (6,4:1 sur blanc) : aucune couleur de tracé ni de calque ne s'en approche. */
export const GREENWAY_COLOR = '#be185d';

/** Zoom minimal des tuiles (GreenwayTiles::MIN_ZOOM côté serveur) : quelques milliers de tracés sur la France. */
export const GREENWAY_MIN_ZOOM = 4;

/** Origine du lien « voir sur OpenStreetMap » d'un way (identifiant numérique, jamais saisi par un visiteur). */
export const OSM_WAY_URL = 'https://www.openstreetmap.org/way/';

/** Adresse des tuiles (absolue : MapLibre la charge depuis un worker). */
export function greenwayTilesUrl(origin, bust = '') {
    return `${origin}/api/v1/greenways/tiles/{z}/{x}/{y}.mvt${bust}`;
}

const WIDTH = ['interpolate', ['linear'], ['zoom'], 4, 1.6, 9, 3, 13, 4.5];
const CASING_WIDTH = ['interpolate', ['linear'], ['zoom'], 4, 3, 9, 5, 13, 7];

/** Couches de bas en haut : liseré blanc, puis le trait rose. */
export function greenwayLayers() {
    const common = {
        type: 'line',
        source: GREENWAY_SOURCE_ID,
        'source-layer': GREENWAY_SOURCE_LAYER,
        minzoom: GREENWAY_MIN_ZOOM,
        layout: { 'line-join': 'round', 'line-cap': 'round' },
    };
    return [
        {
            ...common,
            id: GREENWAY_CASING_LAYER_ID,
            paint: { 'line-color': OVERLAY_HALO, 'line-width': CASING_WIDTH, 'line-opacity': 0.95 },
        },
        {
            ...common,
            id: GREENWAY_LINE_LAYER_ID,
            paint: { 'line-color': GREENWAY_COLOR, 'line-width': WIDTH },
        },
    ];
}

/**
 * Contenu de la bulle d'une voie verte : nom en titre, puis ligne, longueur sur l'ancienne emprise,
 * année d'ouverture si OpenStreetMap la donne, lien vers le way et mention de la source ; `link`
 * (HTML déjà sûr) mène à la fiche de la ligne.
 * @param {Record<string, any>} p propriétés de la tranche (way_id, name, shared_length_m, opened_year, line_name)
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang
 * @param {string} link
 */
export function greenwayPopupHtml(p, t, lang, link) {
    const sep = colon(lang);
    const row = (label, value) =>
        `<li><span class="rail-popup-label">${escapeHtml(label)}${sep}</span>${escapeHtml(value)}</li>`;
    const length = Number(p.shared_length_m);
    const year = Number(p.opened_year);
    const wayId = String(p.way_id ?? '');
    const rows = [
        p.line_name ? row(t('maps.greenways.line'), p.line_name) : '',
        Number.isFinite(length) && length > 0
            ? row(t('maps.greenways.length'), formatKm(length, formatLocale(lang)))
            : '',
        Number.isInteger(year) && year > 0 ? row(t('maps.greenways.opened'), String(year)) : '',
        /^\d+$/.test(wayId)
            ? `<li><a class="vff-link" href="${OSM_WAY_URL}${wayId}" target="_blank" rel="noopener">${escapeHtml(t('maps.greenways.view_osm'))}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span class="vff-sr-only"> (${escapeHtml(t('common.new_tab'))})</span></a></li>`
            : '',
        `<li class="rail-popup-note">${escapeHtml(t('maps.greenways.source'))}</li>`,
    ].join('');
    const title = p.name || t('maps.greenways.fallback_title');
    return `<div class="rail-popup"><p class="rail-popup-title">${escapeHtml(title)}</p><ul>${rows}</ul>${link}</div>`;
}
