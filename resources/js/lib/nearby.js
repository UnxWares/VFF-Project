// Utilitaires de la page « Autour de moi » (Pages/Network/Nearby.svelte) : arrondi de la position, adresse de
// l'API et de la carte, distance, état d'une ligne et phrase d'histoire. Sans accès au DOM.
import { NBSP, formatNumber } from './typo.js';

/** Rayons proposés, en mètres. */
export const RADII = [1000, 5000, 10000];

/** Rayon par défaut : 5 km (en campagne, 1 km est souvent vide). */
export const DEFAULT_RADIUS = 5000;

/** Zoom de la carte pour chaque rayon. */
const MAP_ZOOM = { 1000: 14, 5000: 12, 10000: 11 };

/**
 * Coordonnée arrondie à 0,001 degré (environ 100 m) : la seule précision qui quitte le navigateur.
 * @param {number} value
 */
export function roundCoord(value) {
    return Math.round(value * 1000) / 1000;
}

/**
 * Adresse de l'API : point arrondi, rayon, version courte, langue.
 * @param {number} lat
 * @param {number} lng
 * @param {number} radius
 * @param {string} [lang]
 */
export function nearbyUrl(lat, lng, radius, lang = 'fr') {
    const q = new URLSearchParams({
        lat: String(roundCoord(lat)),
        lng: String(roundCoord(lng)),
        radius_m: String(radius),
        summary: '1',
        lang,
    });

    return `/api/v1/what-was-here?${q}`;
}

/**
 * Adresse de la carte centrée sur le lieu (ère actuelle).
 * @param {number} lat
 * @param {number} lng
 * @param {number} radius
 */
export function mapHref(lat, lng, radius) {
    return `/maps?center=${roundCoord(lng)},${roundCoord(lat)}&zoom=${MAP_ZOOM[radius] ?? 12}`;
}

/**
 * Distance lisible : « 450 m » sous le kilomètre (à 10 m près), « 2,4 km » au-delà.
 * @param {number} meters
 * @param {string} [lang]
 */
export function formatDistance(meters, lang = 'fr') {
    if (meters < 1000) return `${formatNumber(Math.round(meters / 10) * 10, lang)}${NBSP}m`;

    return `${formatNumber(meters / 1000, lang, { maximumFractionDigits: 1 })}${NBSP}km`;
}

/**
 * Clé de traduction de l'état d'une ligne aujourd'hui.
 * @param {{ status: string, traffic?: string|null, heritage?: string|null }} line
 */
export function lineStateKey(line) {
    if (line.status === 'in_service') {
        if (line.heritage) return 'tourist';
        if (line.traffic === 'passenger' || line.traffic === 'mixed') return 'in_service_passenger';
        if (line.traffic === 'freight') return 'in_service_freight';

        return 'in_service';
    }

    return ['abandoned', 'dismantled', 'service_track'].includes(line.status)
        ? line.status
        : 'unknown';
}

/** Faits d'histoire d'une ligne dans l'ordre de la phrase. */
const HISTORY_ORDER = ['opened', 'closed_passenger', 'closed_freight', 'closed', 'dismantled'];

/**
 * Faits d'histoire d'une ligne à écrire, dans l'ordre : `[{ key, year }]`. Une fermeture complète de la même
 * année qu'une fermeture au fret ou aux voyageurs reste seule (« fermée en 1937 »).
 * @param {Record<string, any>|null|undefined} history
 */
export function historyFacts(history) {
    const h = history ?? {};
    const facts = HISTORY_ORDER.filter((k) => Number.isInteger(h[k])).map((key) => ({
        key,
        year: h[key],
    }));

    const whole = facts.filter(
        (f) => !(['closed_passenger', 'closed_freight'].includes(f.key) && h.closed === f.year),
    );
    // Un fait de portion se lit après ceux de la ligne : « une section déposée en 1962 ».
    const sections = (Array.isArray(h.sections) ? h.sections : []).map((x) => ({
        key: x.key,
        year: x.year,
        section: x.from && x.to ? { from: x.from, to: x.to } : {},
    }));

    return [...whole, ...sections];
}

/**
 * Faits d'histoire d'une gare : ouverture, fermeture.
 * @param {{ opened_year?: number|null, closed_year?: number|null }} station
 */
export function stationFacts(station) {
    return [
        ...(Number.isInteger(station.opened_year)
            ? [{ key: 'station_opened', year: station.opened_year }]
            : []),
        ...(Number.isInteger(station.closed_year)
            ? [{ key: 'station_closed', year: station.closed_year }]
            : []),
    ];
}

/**
 * Phrase d'histoire : « Ouverte en 1869, fermée aux voyageurs en 1938. »
 * @param {{ key: string, year: number }[]} facts
 * @param {(fact: { key: string, year: number, section?: { from?: string, to?: string } }) => string} write texte d'un fait
 * @param {string} unknown phrase quand aucun fait n'est connu
 */
export function historySentence(facts, write, unknown) {
    const text = facts.length === 0 ? unknown : facts.map((f) => write(f)).join(', ');

    return `${text.charAt(0).toUpperCase()}${text.slice(1)}.`;
}
