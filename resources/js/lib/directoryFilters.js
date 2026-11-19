// Filtres des annuaires (Components/Directory/DirectoryFilters.svelte) : logique pure, testée par
// tests/js/directoryFilters.test.js.

/**
 * Réglage différent du défaut : liste sur une autre option que la première (« Tous… », tri par
 * nom), case cochée, champ rempli.
 *
 * @param {{ selectedIndex?: number, type?: string, checked?: boolean, value?: string }} control
 */
export function isActiveSetting(control) {
    if ('selectedIndex' in control) return control.selectedIndex > 0;
    if (control.type === 'checkbox' || control.type === 'radio') return Boolean(control.checked);
    return Boolean(control.value);
}

/** Nombre de réglages actifs, affiché sur le bouton « Filtres (n) ». */
export function countActiveSettings(controls) {
    return [...controls].filter(isActiveSetting).length;
}

/** Premier contrôle des filtres repliables (tous les champs sauf le premier, la recherche). */
export const REVEALED_CONTROL = ':scope > :nth-child(2) :is(input, select, textarea, button)';

/**
 * Contrôle qui reçoit le focus quand les filtres s'ouvrent AU CLAVIER : les champs révélés sont
 * placés au-dessus du bouton « Filtres », le focus y va pour suivre l'ordre de lecture
 * (WCAG 2.4.3). Un clic (souris, toucher) laisse le focus en place : `detail` vaut 0 seulement
 * pour une activation au clavier (Entrée, Espace).
 *
 * @param {boolean} open état après la bascule
 * @param {number} detail `event.detail` du clic
 * @param {{ querySelector: (s: string) => any } | null | undefined} fields conteneur des champs
 */
export function revealedFocusTarget(open, detail, fields) {
    if (!open || detail !== 0 || !fields) return null;
    return fields.querySelector(REVEALED_CONTROL) ?? null;
}
