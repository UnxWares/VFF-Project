// Balises de partage écrites par le serveur (resources/views/partials/share-tags.blade.php), repérées
// par l'attribut data-vff-seo. Les robots qui n'exécutent pas le JavaScript (Facebook, Mastodon,
// Discord…) ne lisent que celles-là ; dans le navigateur, le composant SeoHead les reprend telles
// quelles tant que la page est celle du serveur et les retire dès qu'on navigue ailleurs : la page
// vivante n'a donc jamais deux fois la même balise.

const SELECTOR = '[data-vff-seo]';
const URL_SELECTOR = 'meta[property="og:url"][data-vff-seo]';
const DESCRIPTION_SELECTOR = 'meta[name="description"]';

/**
 * Vrai quand les balises du serveur décrivent déjà la page de ce chemin, dans cette langue (après un
 * changement de langue sans rechargement, elles ne sont plus à jour).
 * @param {string} path
 * @param {string} locale locale Open Graph attendue (« fr_FR »)
 */
export function serverSeoCovers(path, locale) {
    if (typeof document === 'undefined') return false;
    const url = document.head.querySelector(URL_SELECTOR)?.getAttribute('content');
    const served = document.head
        .querySelector('meta[property="og:locale"][data-vff-seo]')
        ?.getAttribute('content');
    if (!url || served !== locale) return false;
    try {
        return new URL(url).pathname === new URL(path, 'https://vff.invalid').pathname;
    } catch {
        return false;
    }
}

/**
 * Retire les balises du serveur qui ne décrivent plus la page courante (navigation interne,
 * changement de langue). La description n'est pas retirée : elle redevient la description générale
 * du site (celle que le gabarit donne aux pages sans aperçu), pour que les pages sans SeoHead en
 * gardent une. L'adresse canonique part avec les balises de partage : celle de la page précédente
 * serait fausse, et le gabarit n'en donne pas aux autres pages.
 * @param {string} path chemin de la page courante
 * @param {string} locale locale Open Graph courante
 * @param {string|null} [defaultDescription] description générale (`common.app_description`)
 */
export function dropStaleServerSeo(path, locale, defaultDescription = null) {
    if (typeof document === 'undefined' || serverSeoCovers(path, locale)) return;
    for (const el of document.head.querySelectorAll(SELECTOR)) {
        if (defaultDescription && el.matches(DESCRIPTION_SELECTOR)) {
            el.setAttribute('content', defaultDescription);
            el.removeAttribute('data-vff-seo');
        } else {
            el.remove();
        }
    }
}
