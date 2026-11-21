// Attente d'une carte de fiche : MapLibre (1 Mo à évaluer, puis plusieurs tâches longues pour créer la carte)
// ne démarre que lorsque son cadre entre à l'écran, ou dès la première interaction (défilement, toucher,
// clic, clavier), pour que la carte soit prête quand on y arrive. Une carte visible à l'ouverture (écran
// large) démarre tout de suite, comme avant ; une carte sous la ligne de flottaison (téléphone) ne bloque
// plus l'ouverture de la page.

/** Évènements qui comptent comme première interaction. */
export const INTERACTION_EVENTS = ['scroll', 'wheel', 'pointerdown', 'touchstart', 'keydown'];

/**
 * @param {Element | null | undefined} element cadre de la carte
 * @param {{ rootMargin?: string, events?: string[], target?: EventTarget,
 *   Observer?: typeof IntersectionObserver }} [options]
 * @returns {{ promise: Promise<void>, cancel: () => void }} `promise` se résout une seule fois ; `cancel`
 *   (composant détruit) retire l'observateur et les écouteurs, la promesse reste alors en attente.
 */
export function whenNear(element, options = {}) {
    const {
        rootMargin = '0px',
        events = INTERACTION_EVENTS,
        target = globalThis.window,
        Observer = globalThis.IntersectionObserver,
    } = options;
    let cancel = () => {};
    const promise = new Promise((resolve) => {
        // Sans observateur (vieux navigateur) ou sans cadre : démarrage immédiat, comme avant.
        if (!element || typeof Observer !== 'function') {
            resolve();
            return;
        }
        const listen = { capture: true, passive: true };
        let observer = null;
        const cleanup = () => {
            observer?.disconnect();
            observer = null;
            for (const name of events) target?.removeEventListener(name, done, listen);
        };
        function done() {
            cleanup();
            resolve();
        }
        observer = new Observer(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) done();
            },
            { rootMargin },
        );
        observer.observe(element);
        for (const name of events) target?.addEventListener(name, done, listen);
        cancel = cleanup;
    });
    return { promise, cancel: () => cancel() };
}
