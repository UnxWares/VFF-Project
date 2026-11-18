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

/**
 * @param {string} html HTML de confiance (fichiers lang/)
 * @param {string} [language] code de langue de la page ; les espaces français ne s'appliquent qu'à `fr`
 */
export function legalTypography(html, language = 'fr') {
    return html
        .split(/(<[^>]*>)/)
        .map((chunk) => {
            if (chunk.startsWith('<')) return chunk;
            const spaced = language.startsWith('fr') ? frenchSpacing(chunk) : chunk;
            return keepLicencesTogether(spaced);
        })
        .join('');
}
