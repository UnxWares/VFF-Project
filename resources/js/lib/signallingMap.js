// Calque « Signalisation » de la carte (/maps) : tuiles des tranches de cantonnement, styles par
// famille, bulle. Logique pure, testée par tests/js/signallingMap.test.js.
//
// Vocabulaire : le cantonnement espace les trains (BAL, BAPR, block manuel, cantonnement
// téléphonique, signalisation en cabine des LGV) ; le contrôle de vitesse (KVB, KVBP) est une autre
// notion, montrée dans la bulle seulement, jamais comme une famille de cantonnement.
import { escapeHtml } from '../Components/Map/lineInfo.js';
import { colon, tieUnits } from './typo.js';
import { OVERLAY_COLORS, OVERLAY_HALO } from './overlayPalette.js';
import { pkLabel } from './levelCrossingMap.js';

export const SIGNALLING_SOURCE_ID = 'vff-signalling';
export const SIGNALLING_SOURCE_LAYER = 'vff-signalling';
export const SIGNALLING_CASING_LAYER_ID = 'vff-signalling-casing';

/**
 * Familles (SignallingFamily côté serveur), dans l'ordre de la légende. Le trait double la
 * couleur : plein pour les blocks automatiques et la cabine, tirets pour le block manuel,
 * pointillés pour le cantonnement téléphonique (information jamais portée par la seule couleur).
 */
export const SIGNALLING_FAMILIES = [
    { key: 'automatic_block', dash: null },
    { key: 'restricted_block', dash: null },
    { key: 'manual_block', dash: [3, 1.5] },
    { key: 'telephone', dash: [1, 1.2] },
    { key: 'cab', dash: null },
].map((f, i) => ({ ...f, color: OVERLAY_COLORS[i] }));

/** Une couche par motif de trait (`line-dasharray` n'accepte pas d'expression par entité). */
export const SIGNALLING_DASH_GROUPS = [
    ...new Set(SIGNALLING_FAMILIES.map((f) => JSON.stringify(f.dash))),
].map((key, i) => ({
    id: `vff-signalling-lines-${i}`,
    dash: JSON.parse(key),
    keys: SIGNALLING_FAMILIES.filter((f) => JSON.stringify(f.dash) === key).map((f) => f.key),
}));

/** Toutes les couches du calque (pour les masquer ou les interroger d'un coup). */
export const SIGNALLING_LAYER_IDS = [
    SIGNALLING_CASING_LAYER_ID,
    ...SIGNALLING_DASH_GROUPS.map((g) => g.id),
];

/** Adresse des tuiles de l'ère (seule l'ère courante est renseignée). */
export function signallingTilesUrl(origin, era, bust = '') {
    return `${origin}/api/v1/eras/${era}/signalling/tiles/{z}/{x}/{y}.mvt${bust}`;
}

const WIDTH = ['interpolate', ['linear'], ['zoom'], 5, 2, 9, 3.5, 13, 5.5];
const CASING_WIDTH = ['interpolate', ['linear'], ['zoom'], 5, 3.5, 9, 5.5, 13, 8];

/** Couches de bas en haut : liseré clair, puis une couche par motif de trait. */
export function signallingLayers() {
    const color = [
        'match',
        ['get', 'family'],
        ...SIGNALLING_FAMILIES.flatMap((f) => [f.key, f.color]),
        '#64748b',
    ];
    return [
        {
            id: SIGNALLING_CASING_LAYER_ID,
            type: 'line',
            source: SIGNALLING_SOURCE_ID,
            'source-layer': SIGNALLING_SOURCE_LAYER,
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': OVERLAY_HALO, 'line-width': CASING_WIDTH, 'line-opacity': 0.9 },
        },
        ...SIGNALLING_DASH_GROUPS.map((g) => ({
            id: g.id,
            type: 'line',
            source: SIGNALLING_SOURCE_ID,
            'source-layer': SIGNALLING_SOURCE_LAYER,
            filter: ['in', ['get', 'family'], ['literal', g.keys]],
            layout: { 'line-join': 'round', ...(g.dash ? {} : { 'line-cap': 'round' }) },
            paint: {
                'line-color': color,
                'line-width': WIDTH,
                ...(g.dash ? { 'line-dasharray': g.dash } : {}),
            },
        })),
    ];
}

/** Codes de contrôle de vitesse d'une tranche (« KVB,TVM-430 » → liste), sans le cantonnement lui-même. */
export function protectionCodes(p) {
    const list = String(p.train_protection ?? '')
        .split(',')
        .map((c) => c.trim())
        .filter((c) => c && c !== p.signalling);
    const kvb = p.kvb_equipped === true || p.kvb_equipped === 'true';
    if (kvb && !list.includes('KVB') && !list.includes('KVBP')) list.push('KVB');
    return list;
}

/** Libellé traduit d'un code (BAL, TVM-430, KVB…), sinon le code. */
export function codeLabel(code, t) {
    const key = `lines.signalling.codes.${code}`;
    const label = t(key);
    return tieUnits(label === key ? code : label);
}

/**
 * Contenu de la bulle d'une tranche : ligne en titre, cantonnement (ou signalisation en cabine),
 * contrôle de vitesse, PK de la tranche, note sur le placement ; `link` mène à la fiche.
 * @param {Record<string, any>} p propriétés de la tranche
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang
 * @param {string} link
 */
export function signallingPopupHtml(p, t, lang, link) {
    const sep = colon(lang);
    const row = (label, value) =>
        `<li><span class="rail-popup-label">${escapeHtml(label)}${sep}</span>${escapeHtml(value)}</li>`;
    const cab = p.family === 'cab';
    const protection = protectionCodes(p);
    const from = pkLabel(p.pk_start_m);
    const to = pkLabel(p.pk_end_m);
    const rows = [
        row(
            t(cab ? 'maps.signalling.cab_label' : 'maps.signalling.block_label'),
            codeLabel(p.signalling, t),
        ),
        protection.length
            ? row(
                  t('maps.signalling.protection_label'),
                  protection.map((c) => codeLabel(c, t)).join(', '),
              )
            : '',
        from && to ? row(t('maps.signalling.pk_label'), `${from} - ${to}`) : '',
        `<li class="rail-popup-note">${escapeHtml(t('maps.signalling.source'))}</li>`,
    ].join('');
    const title = p.line_name || t('maps.drawer.fallback_line_name');
    return `<div class="rail-popup"><p class="rail-popup-title">${escapeHtml(title)}</p><ul>${rows}</ul>${link}</div>`;
}

/** Écart minimal (m) entre deux tranches pour signaler un trou dans les données. */
export const SIGNALLING_GAP_MIN_M = 100;

/**
 * Tranches de la fiche ligne avec les trous intercalés : là où aucune tranche ne couvre les PK
 * (section sans cantonnement, absente des données SNCF Réseau, ou numérotation des PK
 * interrompue), une entrée `{ gap: true, from, to }` en notation km+mmm. Les tranches qui se
 * chevauchent (voies ou branches parallèles) ne créent pas de trou.
 * @template {{ pk_start_m: number, pk_end_m: number }} R
 * @param {R[]} ranges triées par PK de début
 * @returns {Array<R | { gap: true, from: string, to: string, key: string }>}
 */
export function signallingWithGaps(ranges) {
    /** @type {Array<R | { gap: true, from: string, to: string, key: string }>} */
    const out = [];
    let reached = null;
    for (const r of ranges) {
        if (reached !== null && r.pk_start_m - reached >= SIGNALLING_GAP_MIN_M) {
            out.push({
                gap: /** @type {const} */ (true),
                from: pkLabel(reached),
                to: pkLabel(r.pk_start_m),
                key: `gap-${reached}`,
            });
        }
        out.push(r);
        reached = reached === null ? r.pk_end_m : Math.max(reached, r.pk_end_m);
    }
    return out;
}
