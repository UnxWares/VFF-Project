// Adresses venues d'une source extérieure (Wikimedia Commons, Wikidata…) rendues en lien ou en image.
// Svelte n'assainit pas les `href` : une licence Commons dont l'adresse serait `javascript:…` deviendrait
// un lien qui exécute du code. Même règle que le serveur (App\Support\WebUrl) : http, https ou adresse
// sans schéma (« //upload.wikimedia.org/… ») ; toute autre adresse est refusée, et le texte qui
// l'accompagne reste affiché sans lien.

// Le navigateur ignore de même les caractères de contrôle et les espaces autour d'une adresse.
const EDGES = /^[\u0000- ]+|[\u0000- ]+$/g; // eslint-disable-line no-control-regex
const CONTROL = /[\u0000-\u001f\u007f]/; // eslint-disable-line no-control-regex
const WEB = /^(?:https?:)?\/\/[^\s/?#@]/i;

/**
 * Adresse utilisable en `href` ou en `src`, sinon null.
 * @param {unknown} value
 * @returns {string|null}
 */
export function webHref(value) {
    if (typeof value !== 'string') return null;
    const url = value.replace(EDGES, '');
    return WEB.test(url) && !CONTROL.test(url) ? url : null;
}
