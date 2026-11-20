// Calque « Passages à niveau » de la carte (/maps) : tuiles, styles, bulle. Logique pure, testée par
// tests/js/levelCrossingMap.test.js ; RailMap pose la source et les couches au premier affichage.
import { escapeHtml } from '../Components/Map/lineInfo.js';
import { colon } from './typo.js';
import { OVERLAY_COLORS, OVERLAY_HALO } from './overlayPalette.js';

export const LEVEL_CROSSING_SOURCE_ID = 'vff-level-crossings';
export const LEVEL_CROSSING_LAYER_ID = 'vff-level-crossings-points';
/** Couche des tuiles (LevelCrossingTiles côté serveur). */
export const LEVEL_CROSSING_SOURCE_LAYER = 'vff-level-crossings';
/**
 * Zoom à partir duquel les passages à niveau se chargent : à l'échelle de la France, 16 800 points
 * ne se lisent pas. Le serveur renvoie des tuiles vides en dessous (LevelCrossingTiles::MIN_ZOOM).
 */
export const LEVEL_CROSSING_MIN_ZOOM = 9;

/** Groupes dessinés sur la carte, dans l'ordre de la légende (les PN secondaires sont omis). */
export const LEVEL_CROSSING_GROUPS = [
    'automatic',
    'guarded',
    'no_barrier',
    'pedestrian',
    'private',
].map((key, i) => ({ key, color: OVERLAY_COLORS[i] }));

/** Adresse des tuiles (absolue : MapLibre la charge depuis un worker). */
export function levelCrossingTilesUrl(origin, bust = '') {
    return `${origin}/api/v1/level-crossings/tiles/{z}/{x}/{y}.mvt${bust}`;
}

/** Couche des points : teinte du groupe, liseré clair, taille qui grandit avec le zoom. */
export function levelCrossingLayer() {
    return {
        id: LEVEL_CROSSING_LAYER_ID,
        type: 'circle',
        source: LEVEL_CROSSING_SOURCE_ID,
        'source-layer': LEVEL_CROSSING_SOURCE_LAYER,
        minzoom: LEVEL_CROSSING_MIN_ZOOM,
        paint: {
            'circle-radius': ['interpolate', ['linear'], ['zoom'], 9, 3.5, 12, 5, 15, 7],
            'circle-color': [
                'match',
                ['get', 'group'],
                ...LEVEL_CROSSING_GROUPS.flatMap((g) => [g.key, g.color]),
                '#64748b',
            ],
            'circle-stroke-color': OVERLAY_HALO,
            'circle-stroke-width': ['interpolate', ['linear'], ['zoom'], 9, 1, 13, 1.5],
        },
    };
}

/** PK en mètres → « 112+866 » (null si inconnu). */
export function pkLabel(meters) {
    if (meters === null || meters === undefined || meters === '' || Number.isNaN(Number(meters)))
        return null;
    const m = Number(meters);
    const abs = Math.abs(m);
    return `${m < 0 ? '-' : ''}${Math.floor(abs / 1000)}+${String(abs % 1000).padStart(3, '0')}`;
}

/**
 * Contenu de la bulle d'un passage à niveau : libellé en titre, puis type (définition de la
 * classe), PK et ligne, commune, voie franchie, source ; `link` (HTML déjà sûr) mène à la fiche
 * de la ligne.
 * @param {Record<string, any>} p propriétés du point
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang
 * @param {string} link
 */
export function levelCrossingPopupHtml(p, t, lang, link) {
    const sep = colon(lang);
    const row = (label, value) =>
        `<li><span class="rail-popup-label">${escapeHtml(label)}${sep}</span>${escapeHtml(value)}</li>`;
    const cls =
        p.class === undefined || p.class === null || p.class === '' ? null : Number(p.class);
    const pk = pkLabel(p.pk_m);
    const rows = [
        // `group` est absent des tuiles pour une classe hors de la liste officielle : pas de définition.
        cls !== null && p.group
            ? `<li>${escapeHtml(t(`level_crossings.classes.${cls}`))}</li>`
            : '',
        pk ? row(t('maps.level_crossings.pk'), pk) : '',
        p.line_name || p.line_code
            ? row(t('maps.level_crossings.line'), p.line_name ?? p.line_code)
            : '',
        p.commune ? row(t('level_crossings.line.commune'), p.commune) : '',
        p.crossed_way ? row(t('level_crossings.line.crossed_way'), p.crossed_way) : '',
        `<li class="rail-popup-note">${escapeHtml(t('maps.level_crossings.source'))}</li>`,
    ].join('');
    const title = p.label || t('maps.level_crossings.fallback_title');
    return `<div class="rail-popup"><p class="rail-popup-title">${escapeHtml(title)}</p><ul>${rows}</ul>${link}</div>`;
}
