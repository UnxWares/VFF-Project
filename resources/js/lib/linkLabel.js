import { quoted } from './typo.js';

/**
 * Libellé lisible d'un lien de source : titre d'article Wikipédia entre guillemets,
 * sinon domaine et chemin raccourcis (« … » quand ils sont coupés).
 * @param {string} url
 * @param {number} [max] longueur maximale du chemin affiché
 * @param {string} [lang] langue des guillemets (français par défaut)
 * @returns {string}
 */
export function linkLabel(url, max = 40, lang = 'fr') {
    try {
        const u = new URL(url);
        const title =
            u.searchParams.get('title') ??
            (u.pathname.startsWith('/wiki/') ? u.pathname.slice(6) : null);
        if (u.hostname.endsWith('wikipedia.org') && title)
            return quoted(decodeURIComponent(title).replaceAll('_', ' '), lang);
        const path = decodeURIComponent(u.pathname).replace(/\/$/, '');
        return `${u.hostname.replace(/^www\./, '')}${path.length > max ? `${path.slice(0, max)}…` : path}`;
    } catch {
        return url;
    }
}
