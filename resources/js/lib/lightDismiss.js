// Fermeture d'une fenêtre <dialog> modale par un clic hors de son cadre.

/** Le point (clientX, clientY) est-il hors du rectangle ? */
export function isOutside(rect, x, y) {
    return x < rect.left || x > rect.right || y < rect.top || y > rect.bottom;
}

/**
 * Attachement Svelte (`{@attach lightDismiss}` sur un <dialog>) : un clic pressé ET relâché hors du
 * cadre ferme la fenêtre. Repli de l'attribut `closedby="any"`, à poser aussi sur la fenêtre, pour
 * les navigateurs qui ne le connaissent pas encore ; sans effet dans les autres.
 *
 * @param {HTMLDialogElement} dialog
 */
export function lightDismiss(dialog) {
    if ('closedBy' in HTMLDialogElement.prototype) return undefined;
    let pressedOutside = false;
    const outside = (/** @type {PointerEvent} */ e) =>
        isOutside(dialog.getBoundingClientRect(), e.clientX, e.clientY);
    const onDown = (/** @type {PointerEvent} */ e) => {
        pressedOutside = outside(e);
    };
    const onUp = (/** @type {PointerEvent} */ e) => {
        if (pressedOutside && outside(e)) dialog.close();
        pressedOutside = false;
    };
    dialog.addEventListener('pointerdown', onDown);
    dialog.addEventListener('pointerup', onUp);
    return () => {
        dialog.removeEventListener('pointerdown', onDown);
        dialog.removeEventListener('pointerup', onUp);
    };
}
