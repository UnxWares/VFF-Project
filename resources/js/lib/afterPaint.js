// Après la première peinture : pour un travail lourd qui ne doit pas retarder l'affichage de la page (la
// grande carte de /maps télécharge et évalue MapLibre, environ 1 Mo, puis crée la carte). Le rappel part
// dans une tâche à part, juste après l'image que le navigateur peint ensuite : le titre, la barre d'outils,
// la légende et la frise sont déjà à l'écran, et leur téléchargement ne partage plus le réseau avec MapLibre.

/**
 * @param {{ raf?: (cb: () => void) => unknown, defer?: (cb: () => void) => unknown }} [timers] fonctions du
 *   navigateur (remplacées dans les tests)
 * @returns {Promise<void>} résolue juste après la prochaine peinture
 */
export function afterPaint(timers = {}) {
    const {
        raf = globalThis.requestAnimationFrame?.bind(globalThis),
        defer = (cb) => setTimeout(cb, 0),
    } = timers;
    return new Promise((resolve) => {
        // Sans requestAnimationFrame (environnement sans affichage) : tâche suivante. Dans un onglet ouvert
        // en arrière-plan, le navigateur attend qu'il soit affiché : rien n'est chargé pour rien.
        if (typeof raf !== 'function') {
            defer(resolve);
            return;
        }
        // requestAnimationFrame passe juste avant la peinture : la tâche suivante vient juste après.
        raf(() => defer(resolve));
    });
}
