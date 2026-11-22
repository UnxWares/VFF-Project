import { estimateHeight, sectionFeatures } from '$lib/sectionHeight.js';

/**
 * Pose `--vff-section-h` (hauteur estimée de la section, en px) sur l'élément, recalculée quand la
 * largeur de la fenêtre change. Lue par `contain-intrinsic-block-size` des fiches (voir
 * lib/sectionHeight.js). Sans effet visible tant que `content-visibility` n'est pas actif.
 *
 * @param {HTMLElement} node
 */
export function sectionHeight(node) {
    let frame = 0;
    let lastWidth = -1;

    function apply() {
        frame = 0;
        const width = window.innerWidth;
        lastWidth = width;
        const h = estimateHeight(node.id || undefined, sectionFeatures(node), width);
        node.style.setProperty('--vff-section-h', `${h}px`);
    }

    function onResize() {
        if (window.innerWidth === lastWidth || frame) return;
        frame = requestAnimationFrame(apply);
    }

    apply();
    window.addEventListener('resize', onResize, { passive: true });
    return {
        destroy() {
            if (frame) cancelAnimationFrame(frame);
            window.removeEventListener('resize', onResize);
        },
    };
}
