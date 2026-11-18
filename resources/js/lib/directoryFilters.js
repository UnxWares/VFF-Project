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
