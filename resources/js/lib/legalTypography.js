// Typographie des textes de lang/ rendus en HTML (pages légales, textes longs) : espaces insécables et
// codes de licence insécables, sans toucher aux balises ni aux attributs. Mise en forme seulement :
// le texte lu est le même, il ne se coupe plus au mauvais endroit.

const NBSP = ' ';

/** Ponctuation française : espace insécable avant « : ; ? ! % € » », après « « », et dans les nombres. */
function frenchSpacing(text) {
    return text
        .replace(/ +([:;?!%€»])(?![\p{L}\p{N}])/gu, `${NBSP}$1`)
        .replace(/(«) +/g, `$1${NBSP}`)
        .replace(/(\d) (?=\d{3}(?!\d))/g, `$1${NBSP}`);
}

/** « CC BY-SA 4.0 », « CC BY 3.0 », « CC0 » : jamais coupés à leur trait d'union ni à leur espace. */
function keepLicencesTogether(text) {
    return text.replace(/\bCC (?:BY|0)(?:-(?:SA|NC|ND))*(?: \d\.\d)?|\bCC0\b/g, (m) => {
        return `<span class="nowrap">${m.replace(/ /g, NBSP)}</span>`;
    });
}

/** Lien qui s'ouvre dans un nouvel onglet (`target="_blank"`, guillemets simples ou doubles). */
const NEW_TAB_LINK = /^<a\b[^>]*\btarget=(["'])_blank\1/i;

/** Échappe un texte pour le poser dans du HTML. */
const escapeHtml = (text) =>
    text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/**
 * @param {string} html HTML de confiance (fichiers lang/)
 * @param {string} [language] code de langue de la page ; les espaces français ne s'appliquent qu'à `fr`
 * @param {string} [newTab] mention « s'ouvre dans un nouvel onglet » (`common.new_tab`) : un lien
 *   `target="_blank"` reçoit alors, avant sa fermeture, l'icône et cette mention pour les lecteurs
 *   d'écran, comme les liens externes des composants (guide, section 5.6)
 */
export function legalTypography(html, language = 'fr', newTab = '') {
    const marker = newTab
        ? `<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span class="vff-sr-only"> (${escapeHtml(newTab)})</span>`
        : '';
    let inNewTabLink = false;

    return html
        .split(/(<[^>]*>)/)
        .map((chunk) => {
            if (chunk.startsWith('<')) {
                if (marker && NEW_TAB_LINK.test(chunk)) inNewTabLink = true;
                if (inNewTabLink && /^<\/a\s*>$/i.test(chunk)) {
                    inNewTabLink = false;
                    return marker + chunk;
                }
                return chunk;
            }
            const spaced = language.startsWith('fr') ? frenchSpacing(chunk) : chunk;
            return keepLicencesTogether(spaced);
        })
        .join('');
}
