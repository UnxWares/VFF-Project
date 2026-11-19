// Copie hors ligne d'une page : le service worker ajoute `offline_copy.saved_at` (ISO 8601) aux propriétés
// Inertia d'une page servie depuis le cache (resources/js/pwa/offlineCopy.js). Lu par OfflineCopyNotice.

/**
 * Date de la copie, ou null si la page vient du réseau (ou si la date est illisible).
 *
 * @param {Record<string, any> | undefined} props
 * @returns {Date | null}
 */
export function offlineCopyDate(props) {
    const savedAt = props?.offline_copy?.saved_at;
    if (typeof savedAt !== 'string') return null;
    const date = new Date(savedAt);
    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Date et heure de la copie dans la langue de la page (« 19 novembre 2026 à 14:05 »).
 *
 * @param {Date} date
 * @param {string} locale
 * @param {string} [timeZone] fuseau (celui du navigateur par défaut ; fixé dans les tests)
 */
export function formatOfflineCopyDate(date, locale, timeZone) {
    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'long',
        timeStyle: 'short',
        ...(timeZone ? { timeZone } : {}),
    }).format(date);
}
