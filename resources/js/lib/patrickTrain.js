// Passage de la rame TGV Sud-Est n° 01 « Patrick » en bas de l'écran, importé par patrick.js au déclenchement.
// Profil à l'échelle dessiné pour VFF (tgvSudEst.js, aucune image reprise) : deux motrices, huit remorques sur
// bogies Jacobs, livrée orange d'origine, pantographe de la motrice de queue levé. Une bulle mène à la fiche.
// Mouvement réduit : pas de rame qui défile, la bulle seule. Le focus ne bouge pas ; Échap ou le bouton ferment.
import { PROFILE_TOP, TRAIN_LENGTH, tgvSudEstSvg } from './tgvSudEst.js';

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

/** Rame entière, nez à gauche (sens de marche), à 1,2 px par décimètre : 2 402 × 64 px. */
export function patrickSvg() {
    return tgvSudEstSvg({ number: '01', raised: true, scale: 1.2, className: 'vff-patrick-train' });
}

const STYLE = `
#${ROOT_ID} { position: fixed; inset: auto 0 0 0; z-index: 70; pointer-events: none; color: var(--vff-text-muted, #57524a); }
#${ROOT_ID} .vff-patrick-track { position: relative; height: 76px; overflow: hidden; }
#${ROOT_ID} .vff-patrick-track::after { content: ''; position: absolute; inset: auto 0 4px; height: 2px;
    border-radius: 1px; background: currentColor; opacity: 0; animation: vff-patrick-rail ${CROSSING_MS}ms ease forwards; }
#${ROOT_ID} .vff-patrick-train { position: absolute; left: 0; bottom: 6px; width: auto; max-width: none;
    height: clamp(52px, 2vw + 46px, 66px); aspect-ratio: ${Math.round(TRAIN_LENGTH)} / ${PROFILE_TOP.raised};
    will-change: transform; animation: vff-patrick-run ${CROSSING_MS}ms linear forwards; }
@keyframes vff-patrick-run { from { transform: translateX(100vw); } to { transform: translateX(-100%); } }
@keyframes vff-patrick-rail { 8%, 88% { opacity: 0.35; } }
#${ROOT_ID} .vff-patrick-bubble { pointer-events: auto; position: absolute; right: 16px; bottom: 86px; display: flex;
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
