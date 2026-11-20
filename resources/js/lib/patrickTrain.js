// Passage de la rame TGV Sud-Est n° 01 « Patrick » en bas de l'écran, importé par patrick.js au déclenchement.
// Silhouette dessinée pour VFF (aucune image reprise) : motrices et voitures orange #ff6500, bandes blanches
// de la livrée d'origine, bogies partagés entre voitures. Une bulle mène à la fiche de la série.
// Mouvement réduit : pas de rame qui défile, la bulle seule. Le focus ne bouge pas ; Échap ou le bouton ferment.

/** Fiche de la série TGV Sud-Est. */
export const PATRICK_HREF = '/materials/tgv-sud-est';

/** Durée de la traversée de l'écran (ms). */
export const CROSSING_MS = 9000;

/** La bulle reste ce temps après la traversée, puis se retire d'elle-même (ms). */
export const BUBBLE_EXTRA_MS = 8000;

const ROOT_ID = 'vff-patrick';

/**
 * Ce qui est joué : la rame qui défile, ou la bulle seule quand le mouvement réduit est demandé.
 *
 * @param {boolean} reducedMotion
 * @returns {{ animate: boolean, lifetimeMs: number }}
 */
export function patrickPlan(reducedMotion) {
    return reducedMotion
        ? { animate: false, lifetimeMs: BUBBLE_EXTRA_MS * 2 }
        : { animate: true, lifetimeMs: CROSSING_MS + BUBBLE_EXTRA_MS };
}

/** Bandes de la livrée d'origine : une claire sous les baies, une fine plus bas. */
function stripes(x, width) {
    return (
        `<rect x="${x}" y="27" width="${width}" height="2.4" fill="#fff"/>` +
        `<rect x="${x}" y="33" width="${width}" height="1.2" fill="#fff"/>`
    );
}

/** Voiture remorque : caisse, bandeau de baies, bandes. */
function coach(x) {
    return (
        `<rect x="${x}" y="12" width="92" height="26" rx="5" fill="#ff6500"/>` +
        `<rect x="${x + 6}" y="17" width="80" height="6" rx="2" fill="#3a2e28"/>` +
        stripes(x + 2, 88)
    );
}

/** Motrice, nez profilé vers la gauche (sens de marche) ; `flip` la retourne pour l'arrière. */
function powerCar(x, flip = false) {
    const body =
        '<path d="M2 38 L2 30 C2 22 12 14 30 12 L104 12 Q108 12 108 16 L108 38 Z" fill="#ff6500"/>' +
        '<path d="M14 21 C18 17 24 15 32 15 L40 15 L40 22 L14 22 Z" fill="#3a2e28"/>' +
        '<path d="M6 30 L106 30" stroke="#fff" stroke-width="2.4"/>' +
        '<path d="M10 34.5 L106 34.5" stroke="#fff" stroke-width="1.2"/>' +
        '<circle cx="30" cy="40" r="4.2" fill="#2b2622"/><circle cx="84" cy="40" r="4.2" fill="#2b2622"/>' +
        '<path d="M70 12 L80 4 M80 4 L92 4" stroke="#3a2e28" stroke-width="1.4" fill="none"/>';
    const transform = flip ? `translate(${x + 110} 0) scale(-1 1)` : `translate(${x} 0)`;

    return `<g transform="${transform}">${body}</g>`;
}

/** Silhouette entière : motrice, quatre voitures sur bogies partagés, motrice arrière. */
export function patrickSvg() {
    const coaches = [116, 212, 308, 404];
    const bogies = [114, 210, 306, 402, 498]
        .map((x) => `<circle cx="${x}" cy="40" r="4.2" fill="#2b2622"/>`)
        .join('');

    return (
        '<svg class="vff-patrick-train" viewBox="0 0 616 46" width="616" height="46" aria-hidden="true" focusable="false">' +
        '<rect x="0" y="44" width="616" height="2" rx="1" fill="currentColor" opacity="0.35"/>' +
        powerCar(0) +
        coaches.map(coach).join('') +
        bogies +
        powerCar(502, true) +
        '</svg>'
    );
}

const STYLE = `
#${ROOT_ID} { position: fixed; inset: auto 0 0 0; z-index: 70; pointer-events: none; color: var(--vff-text-muted, #57524a); }
#${ROOT_ID} .vff-patrick-track { position: relative; height: 54px; overflow: hidden; }
#${ROOT_ID} .vff-patrick-train { position: absolute; left: 0; bottom: 4px; width: 616px; max-width: none; height: auto;
    animation: vff-patrick-run ${CROSSING_MS}ms linear forwards; }
@keyframes vff-patrick-run { from { transform: translateX(100vw); } to { transform: translateX(-640px); } }
#${ROOT_ID} .vff-patrick-bubble { pointer-events: auto; position: absolute; right: 16px; bottom: 64px; display: flex;
    align-items: center; gap: 8px; max-width: min(420px, calc(100vw - 32px)); padding: 10px 8px 10px 16px;
    border-radius: 16px; background: var(--vff-bg-elevated, #fcfbf8); color: var(--vff-text, #25221e);
    border: 1px solid var(--vff-border-strong, #cfc8bb); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.14);
    font-family: var(--vff-font-body, inherit); font-size: 17px; line-height: 1.4; }
#${ROOT_ID} .vff-patrick-bubble::before { content: ''; flex: none; width: 12px; height: 12px; border-radius: 50%; background: #ff6500; }
#${ROOT_ID} .vff-patrick-bubble a { color: var(--vff-link, #a94300); text-underline-offset: 3px; }
#${ROOT_ID} .vff-patrick-close { flex: none; display: grid; place-items: center; width: 40px; height: 40px; margin: 0;
    border: 0; border-radius: 50%; background: transparent; color: inherit; font-size: 22px; line-height: 1; cursor: pointer; }
#${ROOT_ID} .vff-patrick-close:hover { background: var(--vff-bg-muted, #e7e3da); }
#${ROOT_ID} .vff-patrick-close:focus-visible { outline: 3px solid var(--vff-accent, #ff6500); outline-offset: 2px; }
#${ROOT_ID} .vff-patrick-live { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
@media (prefers-reduced-motion: reduce) { #${ROOT_ID} .vff-patrick-train { animation: none; display: none; } }
`;

/**
 * Joue le passage. Un second déclenchement pendant le passage ne fait rien.
 *
 * @param {{ t: (key: string) => string, doc?: Document, win?: Window }} options
 * @returns {(() => void) | null} fonction de fermeture, ou null si déjà affiché
 */
export function showPatrick({ t, doc = document, win = window }) {
    if (doc.getElementById(ROOT_ID)) {
        return null;
    }
    const reduced = win.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const plan = patrickPlan(reduced);

    const style = doc.createElement('style');
    style.textContent = STYLE;

    const root = doc.createElement('div');
    root.id = ROOT_ID;
    root.setAttribute('role', 'region');
    root.setAttribute('aria-label', t('common.patrick.label'));

    // Annonce polie : posée après l'insertion de la région pour que les lecteurs d'écran la lisent.
    const live = doc.createElement('p');
    live.className = 'vff-patrick-live';
    live.setAttribute('aria-live', 'polite');

    const bubble = doc.createElement('p');
    bubble.className = 'vff-patrick-bubble';
    const link = doc.createElement('a');
    link.href = PATRICK_HREF;
    link.textContent = t('common.patrick.bubble');
    const close = doc.createElement('button');
    close.type = 'button';
    close.className = 'vff-patrick-close';
    close.setAttribute('aria-label', t('common.patrick.close'));
    close.textContent = '×';
    bubble.append(link, close);

    if (plan.animate) {
        const track = doc.createElement('div');
        track.className = 'vff-patrick-track';
        track.innerHTML = patrickSvg();
        root.append(track);
    }
    root.append(bubble, live);

    let timer = 0;
    const onKey = (/** @type {KeyboardEvent} */ e) => {
        if (e.key === 'Escape') {
            dismiss();
        }
    };
    function dismiss() {
        win.clearTimeout(timer);
        doc.removeEventListener('keydown', onKey);
        root.remove();
        style.remove();
    }

    close.addEventListener('click', dismiss);
    doc.addEventListener('keydown', onKey);
    doc.head.append(style);
    doc.body.append(root);
    win.setTimeout(() => {
        live.textContent = t('common.patrick.announce');
    }, 100);
    timer = win.setTimeout(dismiss, plan.lifetimeMs);

    return dismiss;
}
