// Copies de pages servies hors ligne : le service worker ajoute aux propriétés Inertia de la page la date de
// la copie (`offline_copy.saved_at`), que le composant OfflineCopyNotice affiche. Fonctions pures, testées
// par tests/js/pwa-offline-copy.test.js.
import { OFFLINE_COPY_PROP } from './rules.js';

/** Balise où app.blade.php écrit l'objet page d'Inertia (JSON encodé avec JSON_HEX_TAG). */
const PAGE_SCRIPT = /(<script\b[^>]*\bdata-page="app"[^>]*>)([\s\S]*?)(<\/script>)/;

/** JSON sûr dans une balise <script> : `<` et `>` échappés, comme JSON_HEX_TAG côté serveur. */
function scriptSafeJson(value) {
    return JSON.stringify(value).replace(/</g, '\\u003C').replace(/>/g, '\\u003E');
}

/**
 * Ajoute la date de la copie aux propriétés d'un objet page d'Inertia.
 *
 * @param {{props?: Record<string, unknown>}} page
 * @param {string | null} savedAt
 */
export function markPage(page, savedAt) {
    return { ...page, props: { ...page.props, [OFFLINE_COPY_PROP]: { saved_at: savedAt } } };
}

/** Objet page contenu dans un document HTML, ou null s'il est introuvable ou illisible. */
export function readPageFromHtml(html) {
    const match = PAGE_SCRIPT.exec(html);
    if (!match) return null;
    try {
        return JSON.parse(match[2]);
    } catch {
        return null;
    }
}

/** Remplace l'objet page d'un document HTML ; null si la balise est introuvable. */
export function writePageToHtml(html, page) {
    if (!PAGE_SCRIPT.test(html)) return null;
    // Fonction de remplacement : un « $ » du JSON n'est pas interprété comme motif.
    return html.replace(
        PAGE_SCRIPT,
        (_all, open, _json, close) => open + scriptSafeJson(page) + close,
    );
}

/** Document HTML d'une copie, marqué de sa date ; inchangé si l'objet page est introuvable. */
export function markHtmlCopy(html, savedAt) {
    const page = readPageFromHtml(html);
    return page ? (writePageToHtml(html, markPage(page, savedAt)) ?? html) : html;
}

/**
 * Document HTML d'une page connue seulement par sa réponse Inertia : le document d'une autre page déjà
 * rangée sert d'enveloppe (mêmes fichiers du même déploiement), avec l'objet page de la copie. Inertia
 * remet ensuite le titre et la langue de la page. Null si l'enveloppe ne convient pas.
 *
 * @param {string} shellHtml
 * @param {object} page
 * @param {string | null} savedAt
 */
export function htmlFromInertiaCopy(shellHtml, page, savedAt) {
    return writePageToHtml(shellHtml, markPage(page, savedAt));
}
