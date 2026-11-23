// Calque « Signalisation » de la carte (/maps) : tuiles des tranches de cantonnement, styles par
// famille, bulle. Logique pure, testée par tests/js/signallingMap.test.js.
//
// Vocabulaire : le cantonnement espace les trains (BAL, BAPR, block manuel, cantonnement
// téléphonique, signalisation en cabine des LGV) ; le contrôle de vitesse (KVB, KVBP) est une autre
// notion, montrée dans la bulle seulement, jamais comme une famille de cantonnement.
import { escapeHtml } from '../Components/Map/lineInfo.js';
import { colon, formatLocale, tieUnits } from './typo.js';
import { OVERLAY_COLORS, OVERLAY_HALO } from './overlayPalette.js';
import { pkLabel } from './levelCrossingMap.js';
import { formatEventDate } from './linePhases.js';
import { systemLabel } from './signallingHistory.js';

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

/**
 * Commande centralisée (famille `centralized_control`, époques passées seulement) : elle commande les
 * postes depuis un poste central et se combine avec un cantonnement, qu'elle ne remplace pas. Ce n'est
 * donc pas une famille de cantonnement (elle reste hors de SIGNALLING_FAMILIES, de la coloration des
 * lignes et de la légende des tranches actuelles) : un trait fin sombre en pointillés, posé par-dessus
 * le trait du cantonnement.
 */
export const SIGNALLING_CONTROL = { key: 'centralized_control', color: '#1f2937', dash: [0.1, 2] };
export const SIGNALLING_CONTROL_LAYER_ID = 'vff-signalling-control';

/** Toutes les couches du calque (pour les masquer ou les interroger d'un coup). */
export const SIGNALLING_LAYER_IDS = [
    SIGNALLING_CASING_LAYER_ID,
    ...SIGNALLING_DASH_GROUPS.map((g) => g.id),
    SIGNALLING_CONTROL_LAYER_ID,
];

/** Couches de cantonnement qui répondent au clic ; la commande centralisée, par-dessus, s'ajoute à leur bulle. */
export const SIGNALLING_BLOCK_LAYER_IDS = SIGNALLING_DASH_GROUPS.map((g) => g.id);

/**
 * Adresse des tuiles de l'ère : l'état de SNCF Réseau pour l'ère courante, les mises en service datées
 * (historique lu dans Wikipédia, propriété `history`) pour une ère passée.
 */
export function signallingTilesUrl(origin, era, bust = '') {
    return `${origin}/api/v1/eras/${era}/signalling/tiles/{z}/{x}/{y}.mvt${bust}`;
}

// Historique d'une époque passée : un trait posé sur toute la ligne faute de portion connue
// (`location = unknown`) est plus fin et plus pâle que celui d'une portion placée.
const UNKNOWN = ['==', ['get', 'location'], 'unknown'];
const WIDTH = [
    'interpolate',
    ['linear'],
    ['zoom'],
    5,
    ['case', UNKNOWN, 1.4, 2],
    9,
    ['case', UNKNOWN, 2.2, 3.5],
    13,
    ['case', UNKNOWN, 3.5, 5.5],
];
const CASING_WIDTH = ['interpolate', ['linear'], ['zoom'], 5, 3.5, 9, 5.5, 13, 8];
/** Opacité d'un trait de portion inconnue (historique). */
export const SIGNALLING_UNKNOWN_OPACITY = 0.55;
// Faits récents et portions placées au-dessus (`sort` des tuiles historiques ; 0 pour l'état actuel).
const SORT_KEY = ['coalesce', ['get', 'sort'], 0];

/** Couches de bas en haut : liseré clair, une couche par motif de trait, puis la commande centralisée. */
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
            // Même filtre que les traits : sans famille reconnue, pas de liseré sans trait dessus.
            filter: ['in', ['get', 'family'], ['literal', SIGNALLING_FAMILIES.map((f) => f.key)]],
            layout: { 'line-join': 'round', 'line-cap': 'round', 'line-sort-key': SORT_KEY },
            paint: { 'line-color': OVERLAY_HALO, 'line-width': CASING_WIDTH, 'line-opacity': 0.9 },
        },
        ...SIGNALLING_DASH_GROUPS.map((g) => ({
            id: g.id,
            type: 'line',
            source: SIGNALLING_SOURCE_ID,
            'source-layer': SIGNALLING_SOURCE_LAYER,
            filter: ['in', ['get', 'family'], ['literal', g.keys]],
            layout: {
                'line-join': 'round',
                'line-sort-key': SORT_KEY,
                ...(g.dash ? {} : { 'line-cap': 'round' }),
            },
            paint: {
                'line-color': color,
                'line-width': WIDTH,
                'line-opacity': ['case', UNKNOWN, SIGNALLING_UNKNOWN_OPACITY, 1],
                ...(g.dash ? { 'line-dasharray': g.dash } : {}),
            },
        })),
        {
            id: SIGNALLING_CONTROL_LAYER_ID,
            type: 'line',
            source: SIGNALLING_SOURCE_ID,
            'source-layer': SIGNALLING_SOURCE_LAYER,
            filter: ['==', ['get', 'family'], SIGNALLING_CONTROL.key],
            layout: { 'line-join': 'round', 'line-cap': 'round', 'line-sort-key': SORT_KEY },
            paint: {
                'line-color': SIGNALLING_CONTROL.color,
                'line-width': ['interpolate', ['linear'], ['zoom'], 5, 1, 9, 1.6, 13, 2.4],
                'line-opacity': ['case', UNKNOWN, SIGNALLING_UNKNOWN_OPACITY, 1],
                'line-dasharray': SIGNALLING_CONTROL.dash,
            },
        },
    ];
}

/** Liste de codes : tableau (fiche ligne, API) ou chaîne séparée par des virgules (tuiles). */
function codeList(value) {
    const list = Array.isArray(value) ? value : String(value ?? '').split(',');
    return list.map((c) => String(c).trim()).filter(Boolean);
}

/**
 * Systèmes de cantonnement d'une tranche, code principal en premier : deux sur les LGV équipées à
 * la fois de l'ETCS niveau 2 et de la TVM (« ETCS_L2,TVM-300 »), un seul ailleurs.
 */
export function signallingSystems(p) {
    const list = codeList(p.signalling_systems);
    if (p.signalling && !list.includes(p.signalling)) list.unshift(p.signalling);
    return [...new Set(list)];
}

/** Libellé des systèmes d'une tranche : « ETCS niveau 2 et TVM 300 (jusqu’à 300 km/h) ». */
export function systemsLabel(p, t) {
    const labels = signallingSystems(p).map((c) => codeLabel(c, t));
    if (labels.length < 2) return labels[0] ?? '';
    return `${labels.slice(0, -1).join(', ')} ${t('lines.signalling.and')} ${labels.at(-1)}`;
}

/** Codes de contrôle de vitesse d'une tranche (« KVB,TVM-430 » → liste), sans le cantonnement lui-même. */
export function protectionCodes(p) {
    const systems = signallingSystems(p);
    const list = codeList(p.train_protection).filter((c) => !systems.includes(c));
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
 * @param {Record<string, any>|null} [control] commande centralisée sous le clic (époque passée)
 */
export function signallingPopupHtml(p, t, lang, link, control = null) {
    if (p.history === true || p.history === 'true')
        return signallingHistoryPopupHtml(p, t, lang, link, control);
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
            systemsLabel(p, t),
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

/**
 * Portion d'un trait historique : placée (lieux de l'article et PK), toute la ligne d'après l'article, ou
 * ligne entière faute de portion connue (en citant les lieux de l'article s'il en donne).
 * @param {Record<string, any>} p propriétés du trait
 * @param {(key: string, opts?: object) => string} t
 */
export function historyPortion(p, t) {
    const from = p.section_from || null;
    const to = p.section_to || null;
    if (p.location === 'line') return t('maps.signalling.history.whole_line');
    if (p.location === 'section' && from && to) {
        const a = pkLabel(p.pk_start_m);
        const b = pkLabel(p.pk_end_m);
        const places = t('signalling_history.section.between', { from, to });
        return a && b ? `${places} (${t('maps.signalling.pk_label')} ${a} - ${b})` : places;
    }
    return from && to
        ? t('maps.signalling.history.unplaced', { from, to })
        : t('maps.signalling.history.unknown');
}

/**
 * Bulle d'un trait de l'historique (époque passée) : système, date de mise en service selon sa
 * précision, portion, note sur une date d'ouverture de LGV, source Wikipédia (révision lue, CC BY-SA).
 * @param {Record<string, any>} p propriétés du trait (tuiles historiques)
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang
 * @param {string} link lien vers la fiche ligne
 * @param {Record<string, any>|null} [control] trait de commande centralisée sous le clic : une ligne en plus
 */
export function signallingHistoryPopupHtml(p, t, lang, link, control = null) {
    const sep = colon(lang);
    const row = (label, value) =>
        `<li><span class="rail-popup-label">${escapeHtml(label)}${sep}</span>${escapeHtml(value)}</li>`;
    const cab = p.family === 'cab';
    const remote = p.family === SIGNALLING_CONTROL.key;
    const when = (f) => formatEventDate(f.date, f.date_precision, formatLocale(lang));
    const source = p.source_url
        ? `<li class="rail-popup-note"><a href="${escapeHtml(p.source_url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(
              t('maps.signalling.history.source', { title: p.source_title ?? '' }),
          )}</a></li>`
        : '';
    const rows = [
        row(
            t(
                remote
                    ? 'maps.signalling.control_label'
                    : cab
                      ? 'maps.signalling.cab_label'
                      : 'maps.signalling.block_label',
            ),
            systemLabel(p.signalling, t),
        ),
        p.date ? row(t('maps.signalling.history.date_label'), when(p)) : '',
        row(t('maps.signalling.history.portion_label'), historyPortion(p, t)),
        control && !remote && control.date
            ? row(
                  t('maps.signalling.control_label'),
                  t('maps.signalling.history.control_since', { date: when(control) }),
              )
            : '',
        p.origin === 'opening'
            ? `<li class="rail-popup-note">${escapeHtml(t('maps.signalling.history.opening'))}</li>`
            : '',
        source,
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
