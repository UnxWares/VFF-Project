// Textes et couleurs d'une portion cliquée sur la carte, partagés par la fiche latérale
// (LineDrawer) et la bulle de la carte intégrée (RailMap) : mêmes mots et mêmes couleurs que
// la légende (railStyle.js), pour qu'une tension ne s'écrive pas de trois façons.
import {
    ACTIVE_STATUSES,
    ELECTRIFICATION_STYLES,
    HERITAGE_COLOR,
    LGV_COLOR,
    STATUS_STYLES,
    TRAFFIC_COLOR,
} from '$lib/railStyle.js';
import { formatNumber } from '$lib/typo.js';

const NBSP = ' ';
const ELECTRIFICATION = Object.fromEntries(ELECTRIFICATION_STYLES.map((s) => [s.key, s]));
const STATUS = Object.fromEntries(STATUS_STYLES.map((s) => [s.key, s]));

/** Les propriétés des tuiles vectorielles arrivent parfois en texte (« true »). */
export function isTrue(value) {
    return value === true || value === 'true';
}

/** Catégorie d'électrification d'une tension (mêmes seuils que electrificationColorExpr). */
export function electrificationKey(voltage) {
    const v = Number(voltage) || 0;
    if (v === 0) return 'unknown_voltage';
    if (v === 25000) return 'ac25';
    if (v === 1500) return 'dc1500';
    if (v === 3000) return 'dc3000';
    if (v === 15000) return 'ac15';
    if (v <= 1000) return 'low';
    return 'other';
}

/**
 * Électrification lisible : libellé de la légende pour les tensions courantes
 * (« 25 kV 50 Hz (alternatif) »), tension formatée dans la langue sinon (« 750 V », « 12 kV »).
 * @param {number|string|null} voltage tension en volts
 * @param {(key: string) => string} t
 * @param {string} lang
 */
export function electrificationInfo(voltage, t, lang) {
    const key = electrificationKey(voltage);
    const v = Number(voltage) || 0;
    let label = t(ELECTRIFICATION[key].labelKey);
    if (key === 'low') label = `${formatNumber(v, lang)}${NBSP}V`;
    if (key === 'other') {
        label = `${formatNumber(v / 1000, lang, { maximumFractionDigits: 1 })}${NBSP}kV`;
    }
    return { key, label, color: ELECTRIFICATION[key].color };
}

/** Portion en service (trafic voyageurs ou fret) à la décennie affichée. */
export function isActive(status) {
    return ACTIVE_STATUSES.includes(status);
}

/**
 * Trafic d'une portion en service, ou exploitation associative : libellé et couleur de la
 * légende. null pour une portion fermée, déposée ou en travaux.
 */
export function trafficInfo(line, t) {
    if (line.heritage_kind) {
        return { label: t(`maps.heritage_kind.${line.heritage_kind}`), color: HERITAGE_COLOR };
    }
    if (!isActive(line.status)) return null;
    if (isTrue(line.is_lgv) || line.traffic_type === 'passenger') {
        return { label: t('maps.traffic.lgv'), color: LGV_COLOR };
    }
    const key = ['mixed', 'freight'].includes(line.traffic_type) ? line.traffic_type : 'unknown';
    return { label: t(`maps.traffic.${key}`), color: TRAFFIC_COLOR[key] };
}

/** Statut d'une portion avec le mot de la légende (« Fermée (voie en place) »). */
export function statusLabel(status, t) {
    if (!status) return '-';
    const key = `maps.status.${status}`;
    const label = t(key);
    return label === key ? status : label;
}

/** Trait de la légende pour un statut non actif (couleur et motif), null sinon. */
export function statusStroke(status) {
    const style = STATUS[status];
    return style ? { color: style.color, dash: style.dash } : null;
}

/** Échappe un texte inséré dans le HTML d'une bulle MapLibre (noms venant de la base). */
export function escapeHtml(text) {
    return String(text ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
    );
}
