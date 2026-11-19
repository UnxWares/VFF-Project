// Formulaire « Signaler une erreur » des fiches : contrôles faits avant l'envoi (les mêmes règles
// que StoreErrorReportRequest côté serveur, qui reste juge) et dates de la liste des validateurs.
// Sans dépendance à Svelte (testé par tests/js/errorReport.test.js).

import { isEmail } from '../Pages/Auth/authForm.js';
import { formatLocale } from './typo.js';

/** Longueurs admises du texte (caractères), comme StoreErrorReportRequest. */
export const DESCRIPTION_MIN = 10;
export const DESCRIPTION_MAX = 2000;

/** Adresse web absolue en http ou https. */
export function isWebUrl(value) {
    try {
        const url = new URL(value.trim());
        return (url.protocol === 'https:' || url.protocol === 'http:') && url.hostname !== '';
    } catch {
        return false;
    }
}

/**
 * Erreurs du formulaire avant l'envoi, par champ (clé de traduction `reports.errors.*` et ses
 * paramètres). Objet vide si tout va bien.
 * @param {{ description?: string, source_url?: string, contact_email?: string }} fields
 * @returns {Record<string, { key: string, params?: Record<string, number> }>}
 */
export function reportErrors({ description = '', source_url = '', contact_email = '' }) {
    /** @type {Record<string, { key: string, params?: Record<string, number> }>} */
    const errors = {};
    const text = description.trim();
    if (text === '') {
        errors.description = { key: 'reports.errors.description_required' };
    } else if (text.length < DESCRIPTION_MIN) {
        errors.description = {
            key: 'reports.errors.description_min',
            params: { min: DESCRIPTION_MIN },
        };
    } else if (text.length > DESCRIPTION_MAX) {
        errors.description = {
            key: 'reports.errors.description_max',
            params: { max: DESCRIPTION_MAX },
        };
    }
    if (source_url.trim() !== '' && !isWebUrl(source_url)) {
        errors.source_url = { key: 'reports.errors.source_url' };
    }
    if (contact_email.trim() !== '' && !isEmail(contact_email)) {
        errors.contact_email = { key: 'reports.errors.contact_email' };
    }
    return errors;
}

/**
 * Date d'un signalement dans la langue de la page : « 19 novembre 2026 ». Chaîne vide si la
 * date manque ou n'est pas lisible.
 * @param {string|null|undefined} iso date ISO 8601 envoyée par le serveur
 * @param {string} lang langue de la page (fr, en, de)
 * @param {string} [timeZone] fuseau (par défaut celui du navigateur)
 */
export function reportDate(iso, lang, timeZone = undefined) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(formatLocale(lang), { dateStyle: 'long', timeZone }).format(
        date,
    );
}
