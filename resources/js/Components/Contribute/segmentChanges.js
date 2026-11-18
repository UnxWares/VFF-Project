// Mise en forme des contributions « modification d'une portion par PK » pour l'affichage :
// changements proposés en libellés lisibles (pas les noms de colonnes ni le JSON de la base),
// points kilométriques, époque de la carte, dates. Utilisé par Mes contributions, Merci et
// Validation (Pages/Contribute/*, Pages/Validate/Index.svelte).

import { formatLocale, formatNumber } from '$lib/typo.js';

/** Ère de la carte qui représente le réseau d'aujourd'hui (voir la carte historique). */
export const CURRENT_ERA = 2030;

/** Ordre d'affichage des changements (les autres clés suivent, par ordre alphabétique). */
const ORDER = [
    'status',
    'traffic_type',
    'electrified',
    'voltage_v',
    'max_speed_kmh',
    'is_heritage',
];

/** Point kilométrique en mètres → « 12+345 ». */
export function formatPk(m) {
    const v = Math.max(0, Math.round(Number(m) || 0));
    return `${Math.floor(v / 1000)}+${(v % 1000).toString().padStart(3, '0')}`;
}

/**
 * Libellé de l'époque de la carte : « 1950 », ou « 2030 (aujourd'hui) » pour l'ère courante.
 * @param {number|string} year
 * @param {(key: string, opts?: object) => any} t
 */
export function eraLabel(year, t) {
    return Number(year) === CURRENT_ERA
        ? t('editor.segment.era_today', { year })
        : String(year ?? '');
}

function translated(t, key, fallback) {
    const label = t(key);
    return label === key ? fallback : label;
}

/**
 * Valeur lisible d'un changement.
 * @param {string} field
 * @param {unknown} value
 * @param {(key: string, opts?: object) => any} t
 * @param {string} lang
 */
export function changeValue(field, value, t, lang) {
    if (value === null || value === undefined || value === '') return '';
    switch (field) {
        case 'status':
            return translated(t, `editor.segment.statuses.${value}`, String(value));
        case 'traffic_type':
            return translated(t, `editor.segment.traffics.${value}`, String(value));
        case 'electrified':
            return t(
                value
                    ? 'editor.segment.values.electrified_yes'
                    : 'editor.segment.values.electrified_no',
            );
        case 'is_heritage':
            return t(value ? 'editor.segment.values.yes' : 'editor.segment.values.no');
        case 'max_speed_kmh':
            return t('editor.segment.values.speed', { value: formatNumber(Number(value), lang) });
        case 'voltage_v':
            return t('editor.segment.values.voltage', { value: formatNumber(Number(value), lang) });
        default:
            return typeof value === 'object' ? JSON.stringify(value) : String(value);
    }
}

/**
 * Changements proposés, triés, avec libellé du champ et valeur lisible.
 * @param {Record<string, unknown>|null|undefined} changes
 * @param {(key: string, opts?: object) => any} t
 * @param {string} lang
 * @returns {{ field: string, label: string, value: string }[]}
 */
export function changeEntries(changes, t, lang) {
    const keys = Object.keys(changes ?? {}).filter((k) => {
        const v = changes[k];
        return v !== null && v !== undefined && v !== '';
    });
    keys.sort((a, b) => {
        const ia = ORDER.indexOf(a);
        const ib = ORDER.indexOf(b);
        if (ia === -1 && ib === -1) return a.localeCompare(b);
        if (ia === -1) return 1;
        if (ib === -1) return -1;
        return ia - ib;
    });
    return keys.map((field) => ({
        field,
        label: translated(t, `editor.segment.fields.${field}`, field),
        value: changeValue(field, changes[field], t, lang),
    }));
}

/**
 * Date « 2026-10-20 11:21 » (format du serveur) → « 20 octobre 2026 » dans la langue de la page.
 * Une valeur non reconnue est rendue telle quelle.
 * @param {string|null|undefined} value
 * @param {string} lang
 */
export function formatDay(value, lang) {
    if (!value) return '';
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
    if (!m) return String(value);
    const d = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));
    return d.toLocaleDateString(formatLocale(lang), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

/**
 * Adresse lisible d'une source : décodée (%C3%A9 → é) quand c'est possible.
 * @param {string} url
 */
export function readableUrl(url) {
    try {
        return decodeURI(String(url));
    } catch {
        return String(url);
    }
}

/** true si la chaîne est une adresse web cliquable. */
export function isHttpUrl(s) {
    return /^https?:\/\/\S+$/i.test(String(s ?? '').trim());
}
