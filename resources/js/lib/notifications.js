// Page /notifications : mise en forme des dates et du résumé, sans dépendance à Svelte (testée
// par tests/js/notifications.test.js).

import { formatLocale } from './typo.js';

/**
 * Date et heure d'une notification dans la langue de la page : « 19 novembre 2026 à 14:05 »,
 * « 19 November 2026 at 14:05 », « 19. November 2026 um 14:05 ». Chaîne vide si la date manque
 * ou n'est pas lisible.
 * @param {string|null|undefined} iso date ISO 8601 envoyée par le serveur
 * @param {string} lang langue de la page (fr, en, de)
 * @param {string} [timeZone] fuseau (par défaut celui du navigateur)
 */
export function notificationDate(iso, lang, timeZone = undefined) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(formatLocale(lang), {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone,
    }).format(date);
}

/**
 * Résumé sous le titre de la page : nombre de notifications non lues, ou « tout est lu ».
 * @param {number} unread
 * @param {(key: string, opts?: object) => any} t traduction (i18next)
 */
export function unreadSummary(unread, t) {
    return unread > 0
        ? t('notifications.unread_count', { count: unread })
        : t('notifications.all_read');
}
