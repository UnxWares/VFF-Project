// Utilitaires de la page « Ce jour-là » (Pages/Network/OnThisDay.svelte) : adresse d'un jour, libellé d'un jour
// du calendrier, jours d'un mois. Sans accès au DOM.
import { formatLocale } from './typo.js';

/** Dernier jour de chaque mois d'une année bissextile (le 29 février existe). */
const DAYS_IN_MONTH = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

/**
 * Nombre de jours d'un mois (29 février compris).
 * @param {number} month 1 à 12
 */
export function daysInMonth(month) {
    return DAYS_IN_MONTH[month - 1] ?? 31;
}

/**
 * Adresse d'un jour « MM-JJ », avec ou sans les accidents.
 * @param {string|null} key
 * @param {{ accidents?: boolean }} [options]
 */
export function onThisDayHref(key, { accidents = true } = {}) {
    const path = key ? `/on-this-day/${key}` : '/on-this-day';

    return accidents ? path : `${path}?accidents=0`;
}

/**
 * Libellé d'un jour sans l'année : « 1er novembre », « 23 novembre », « 23 November », « 23. November ».
 * @param {number} month 1 à 12
 * @param {number} day
 * @param {string} [lang]
 */
export function dayLabel(month, day, lang = 'fr') {
    const label = new Date(Date.UTC(2024, month - 1, day)).toLocaleDateString(formatLocale(lang), {
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });

    return lang === 'fr' && day === 1 ? label.replace(/^1(?=\s)/, '1er') : label;
}

/**
 * Libellé d'un jour donné par sa clé « MM-JJ ».
 * @param {string} key
 * @param {string} [lang]
 */
export function keyLabel(key, lang = 'fr') {
    const [month, day] = key.split('-').map(Number);

    return dayLabel(month, day, lang);
}

/**
 * Nom d'un mois dans la langue (pour la liste de choix).
 * @param {number} month 1 à 12
 * @param {string} [lang]
 */
export function monthName(month, lang = 'fr') {
    return new Date(Date.UTC(2024, month - 1, 1)).toLocaleDateString(formatLocale(lang), {
        month: 'long',
        timeZone: 'UTC',
    });
}
