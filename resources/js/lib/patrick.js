// Clin d'œil : taper « patrick » au clavier (hors champ de saisie et hors page /embed) fait passer la rame
// TGV Sud-Est n° 01, surnommée « Patrick ». Ce module, chargé avec l'application, ne fait que guetter la
// séquence ; le dessin et son style (patrickTrain.js) ne sont téléchargés qu'au déclenchement.
// Rien n'est envoyé au serveur ni gardé dans le navigateur.

export const PATRICK_WORD = 'patrick';

/** Délai maximal entre deux touches de la séquence (ms) : au-delà, on recommence. */
export const PATRICK_KEY_GAP_MS = 1500;

/**
 * L'élément reçoit-il du texte (champ, zone de texte, liste, contenu éditable) ? Une frappe dans un champ
 * n'est jamais lue comme la séquence.
 *
 * @param {EventTarget | null} target
 */
export function isTypingTarget(target) {
    if (!target || typeof target !== 'object') {
        return false;
    }
    const el = /** @type {HTMLElement} */ (target);
    if (el.isContentEditable) {
        return true;
    }
    const tag = typeof el.tagName === 'string' ? el.tagName.toUpperCase() : '';

    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
}

/**
 * Avance dans la séquence : renvoie le nombre de lettres reconnues après cette touche.
 * Une lettre fausse fait repartir de zéro, ou de un si elle commence le mot.
 *
 * @param {number} progress lettres déjà reconnues
 * @param {string} key touche (KeyboardEvent.key)
 * @param {string} [word]
 */
export function nextProgress(progress, key, word = PATRICK_WORD) {
    const letter = typeof key === 'string' && key.length === 1 ? key.toLowerCase() : '';
    if (letter === word[progress]) {
        return progress + 1;
    }

    return letter === word[0] ? 1 : 0;
}

/**
 * Guetteur de séquence : renvoie le gestionnaire `keydown` à brancher sur le document.
 *
 * @param {{
 *   onMatch: () => void,
 *   isDisabled?: () => boolean,
 *   now?: () => number,
 *   word?: string,
 * }} options
 */
export function createSequenceWatcher({
    onMatch,
    isDisabled = () => false,
    now = () => Date.now(),
    word = PATRICK_WORD,
}) {
    let progress = 0;
    let last = 0;

    return (/** @type {KeyboardEvent} */ event) => {
        if (
            event.defaultPrevented ||
            event.ctrlKey ||
            event.metaKey ||
            event.altKey ||
            isTypingTarget(event.target) ||
            isDisabled()
        ) {
            progress = 0;
            return;
        }
        const time = now();
        if (time - last > PATRICK_KEY_GAP_MS) {
            progress = 0;
        }
        last = time;
        progress = nextProgress(progress, event.key, word);
        if (progress === word.length) {
            progress = 0;
            onMatch();
        }
    };
}

/** Page intégrée (iframe /embed) : le clin d'œil n'y est jamais joué. */
export function isEmbedPath(pathname) {
    return pathname === '/embed' || String(pathname).startsWith('/embed/');
}

/**
 * Branche le guetteur sur le document. Le module d'affichage est importé à la première séquence complète.
 *
 * @param {{ t: (key: string) => string }} i18n
 */
export function installPatrick(i18n) {
    if (typeof document === 'undefined') {
        return;
    }
    const handler = createSequenceWatcher({
        isDisabled: () => isEmbedPath(location.pathname),
        onMatch: () => {
            import('./patrickTrain.js').then(({ showPatrick }) => showPatrick({ t: i18n.t }));
        },
    });
    document.addEventListener('keydown', handler);
}
