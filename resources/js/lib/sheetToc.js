// Sommaire collant des fiches (Components/Sheet/SheetToc.svelte) : logique pure, testée par
// tests/js/sheetToc.test.js.

/**
 * Section lue : la dernière dont le haut est passé au-dessus de la ligne de lecture. Arrivé en bas
 * de la page, la dernière section visible devient courante (une dernière section courte n'atteint
 * jamais la ligne de lecture). Avant la première section : aucune.
 *
 * @param {Array<{ id: string, top: number }>} sections haut de chaque section dans la fenêtre, dans
 *   l'ordre de la page
 * @param {{ line: number, atEnd: boolean, viewportHeight: number }} view
 * @returns {string | null}
 */
export function currentSection(sections, { line, atEnd, viewportHeight }) {
    let found = null;
    for (const { id, top } of sections) {
        if (top <= line || (atEnd && top < viewportHeight)) found = id;
    }
    return found;
}

/**
 * Défilement horizontal qui rend un arrêt visible dans la rangée du sommaire (téléphone), ou null
 * s'il l'est déjà.
 *
 * @param {{ left: number, width: number }} item position de l'arrêt dans la rangée
 * @param {{ scrollLeft: number, clientWidth: number }} row
 * @param {number} margin marge gardée de chaque côté
 * @returns {number | null}
 */
export function revealScrollLeft(item, row, margin = 24) {
    const start = item.left - margin;
    const end = item.left + item.width + margin;
    if (start >= row.scrollLeft && end <= row.scrollLeft + row.clientWidth) return null;
    return Math.max(0, start);
}

/**
 * Rangée qui défile : reste-t-il des arrêts cachés avant (à gauche) ou après (à droite) ? Sert aux
 * fondus qui l'indiquent. Une tolérance d'un pixel absorbe les arrondis du zoom.
 *
 * @param {{ scrollLeft: number, scrollWidth: number, clientWidth: number }} row
 * @returns {{ before: boolean, after: boolean }}
 */
export function hiddenEdges({ scrollLeft, scrollWidth, clientWidth }) {
    const left = Math.abs(scrollLeft);
    return {
        before: left > 1,
        after: left + clientWidth < scrollWidth - 1,
    };
}

/**
 * Remet la section visée à sa place après un saut d'ancre. Les sections hors écran sont en
 * `content-visibility: auto` : celles que le saut amène près de l'écran se mettent en page avec leur
 * vraie hauteur, et l'écart avec l'estimation décale la cible de quelques dizaines de pixels. Chaque
 * image, on mesure l'écart entre le haut de la section et sa marge de défilement et on le corrige,
 * jusqu'à ce que la position soit stable (10 images) ou au bout de 1 s. Un geste de l'utilisateur
 * (molette, toucher, clavier, souris) arrête la correction. Renvoie la fonction qui l'arrête.
 *
 * @param {string} id identifiant de la section visée
 * @param {{ document?: Document, requestAnimationFrame?: Function, cancelAnimationFrame?: Function,
 *   getComputedStyle?: Function, scrollBy?: Function, addEventListener?: Function,
 *   removeEventListener?: Function, performance?: { now: () => number }, scrollY?: number }} [env] fenêtre (injectable pour les tests)
 * @returns {() => void}
 */
export function settleOnSection(id, env = globalThis.window) {
    const gestures = ['wheel', 'touchstart', 'keydown', 'mousedown'];
    const started = env.performance?.now?.() ?? Date.now();
    let handle = 0;
    let stable = 0;
    let done = false;

    function stop() {
        if (done) return;
        done = true;
        env.cancelAnimationFrame(handle);
        for (const g of gestures) env.removeEventListener(g, stop);
    }

    function step() {
        const el = env.document.getElementById(id);
        if (!el) return stop();
        const margin = Number.parseFloat(env.getComputedStyle(el).scrollMarginTop) || 0;
        const diff = el.getBoundingClientRect().top - margin;
        if (Math.abs(diff) > 1.5) {
            const before = env.scrollY;
            env.scrollBy({ top: diff, behavior: 'instant' });
            // Fin de page : la position ne bouge plus, inutile d'insister.
            stable = env.scrollY === before ? stable + 1 : 0;
        } else stable += 1;
        const elapsed = (env.performance?.now?.() ?? Date.now()) - started;
        if (stable >= 10 || elapsed > 1000) return stop();
        handle = env.requestAnimationFrame(step);
    }

    for (const g of gestures) env.addEventListener(g, stop, { passive: true });
    handle = env.requestAnimationFrame(step);
    return stop;
}
