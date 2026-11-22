// Journal de bord (Pages/Journal/Index.svelte et Show.svelte) : logique pure, testée par
// tests/js/journal.test.js.

import { firstOfMonth, formatLocale } from '$lib/typo.js';

/**
 * Date d'un article (« 22 novembre 2026 », « 1er août 1912 » en français, « 22 November 2026 »,
 * « 22. November 2026 »), lue comme une date du calendrier : aucun décalage de fuseau ne peut la
 * faire changer de jour.
 *
 * @param {string} iso date AAAA-MM-JJ
 * @param {string} lang langue de la page
 * @returns {string}
 */
export function formatJournalDate(iso, lang) {
    const [y, m, d] = String(iso).split('-').map(Number);
    if (!y || !m || !d) return String(iso ?? '');
    const text = new Intl.DateTimeFormat(formatLocale(lang), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(y, m - 1, d)));
    return firstOfMonth(text, lang);
}

/**
 * Faut-il ouvrir ce lien du texte d'un article par la navigation du site (Inertia) plutôt que par
 * un chargement complet ? Oui pour une page du site (`/lines/…`, `/stations/…`) cliquée sans touche
 * de modification ; non pour une ancre de la page (notes, sommaire), un lien externe, un nouvel
 * onglet demandé ou un clic autre que le gauche.
 *
 * @param {{ href: string|null, target?: string|null }} link attributs du lien
 * @param {{ button: number, metaKey?: boolean, ctrlKey?: boolean, shiftKey?: boolean, altKey?: boolean, defaultPrevented?: boolean }} event
 * @returns {boolean}
 */
export function isSiteLink(link, event) {
    const href = link?.href ?? '';
    if (event.defaultPrevented || event.button !== 0) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.target && link.target !== '_self') return false;
    return href.startsWith('/') && !href.startsWith('//');
}

/**
 * L'article est-il servi dans une autre langue que celle de la page (traduction absente) ?
 *
 * @param {string} articleLang langue du texte servi
 * @param {string} pageLang langue de l'interface
 */
export function isFallback(articleLang, pageLang) {
    return Boolean(articleLang) && articleLang !== (pageLang ?? 'fr');
}
