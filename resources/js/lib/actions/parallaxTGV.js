// Écartement des deux TGV du grand visuel de l'accueil au défilement (HomeHero.svelte).
//
// La géométrie est dans header.css (section « Héros de l'accueil ») : chaque rame est translatée de
// --tgv-progress (0 : contre le disque, 1 : écartée jusqu'au bord de l'écran) fois sa course.
// - Navigateurs qui lient une animation au défilement (TGV_TIMELINE) : header.css anime les rames
//   lui-même, hors du fil principal ; ce module ne fait rien.
// - Mouvement réduit demandé : rames immobiles, déjà écartées (header.css) ; rien n'est écrit.
// - Sinon : un écouteur passif demande une image (requestAnimationFrame) ; l'image lit le
//   défilement et écrit la variable seulement si la valeur a changé.

/** Condition CSS des animations liées au défilement, reprise dans le @supports de header.css. */
export const TGV_TIMELINE = '(animation-timeline: scroll()) and (animation-range: 0 100vh)';

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

/**
 * Avancement de l'écartement : 0 en haut de page, 1 après une hauteur d'écran de défilement.
 *
 * @param {number} scrollY défilement vertical, en px
 * @param {number} range défilement qui écarte complètement les rames, en px
 * @returns {number} entre 0 et 1, arrondi au millième (rien à redessiner en deçà)
 */
export function tgvProgress(scrollY, range) {
    if (!(range > 0)) return 1;
    const progress = Math.min(1, Math.max(0, scrollY / range));
    return Math.round(progress * 1000) / 1000;
}

/**
 * Attachement Svelte (`{@attach parallaxTGV}` sur le héros) : écrit --tgv-progress au défilement.
 *
 * @param {HTMLElement} node
 * @returns {(() => void) | undefined} nettoyage
 */
export function parallaxTGV(node) {
    if (globalThis.CSS?.supports(TGV_TIMELINE)) return undefined;

    const reduced = window.matchMedia(REDUCED_MOTION);
    let frame = 0;
    let last = Number.NaN;

    const render = () => {
        frame = 0;
        const progress = tgvProgress(window.scrollY, window.innerHeight);
        if (progress === last) return;
        last = progress;
        node.style.setProperty('--tgv-progress', String(progress));
    };
    const schedule = () => {
        if (!frame) frame = window.requestAnimationFrame(render);
    };
    const start = () => {
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        schedule();
    };
    const stop = () => {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        if (frame) window.cancelAnimationFrame(frame);
        frame = 0;
        last = Number.NaN;
        node.style.removeProperty('--tgv-progress');
    };
    // La préférence peut changer pendant la visite : on suit.
    const onPreferenceChange = () => (reduced.matches ? stop() : start());

    reduced.addEventListener('change', onPreferenceChange);
    if (!reduced.matches) start();

    return () => {
        reduced.removeEventListener('change', onPreferenceChange);
        stop();
    };
}
