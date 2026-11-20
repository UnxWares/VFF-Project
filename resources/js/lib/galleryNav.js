// Visionneuse des galeries (Components/Sheet/SheetGallery.svelte) : état des boutons Précédent et
// Suivant, et place du focus quand le bouton qui vient d'être activé se désactive. Fonctions pures,
// testées par tests/js/galleryNav.test.js.

/**
 * Boutons désactivés pour l'image `index` sur `count` images chargées.
 * @param {number} index
 * @param {number} count
 * @param {boolean} hasMore il reste des pages à charger
 * @returns {{ previous: boolean, next: boolean }}
 */
export function navDisabled(index, count, hasMore) {
    return { previous: index <= 0, next: index >= count - 1 && !hasMore };
}

/**
 * Élément qui reçoit le focus après un pas de `delta` (−1 ou 1) quand le bouton activé se désactive
 * (première ou dernière image) : l'autre bouton, ou l'image s'il est désactivé lui aussi. Sans cela, le
 * focus retomberait sur `body` et la tabulation repartirait du début de la page. null : le bouton
 * activé reste actif, le focus ne bouge pas.
 * @param {number} delta
 * @param {number} index image affichée après le pas
 * @param {number} count
 * @param {boolean} hasMore
 * @returns {'previous'|'next'|'image'|null}
 */
export function focusAfterStep(delta, index, count, hasMore) {
    const disabled = navDisabled(index, count, hasMore);
    if (delta < 0 && disabled.previous) return disabled.next ? 'image' : 'next';
    if (delta > 0 && disabled.next) return disabled.previous ? 'image' : 'previous';
    return null;
}
