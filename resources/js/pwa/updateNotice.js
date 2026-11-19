// Message discret « Nouvelle version disponible » : bandeau en bas de page, hors de l'application Svelte
// (il survit aux navigations Inertia). Le texte est dans une zone `role="status"` insérée vide puis remplie,
// ce qui la fait annoncer par les lecteurs d'écran ; le focus n'est jamais déplacé.
import './pwa.css';

/**
 * @param {Document} doc
 * @param {{message: string, apply: string, dismiss: string, onApply: () => void, defer?: (fn: () => void) => void}} options
 * @returns {() => void} fonction qui retire le bandeau
 */
export function showUpdateNotice(
    doc,
    { message, apply, dismiss, onApply, defer = (fn) => setTimeout(fn, 150) },
) {
    const root = doc.createElement('div');
    root.className = 'vff-pwa-notice';

    const status = doc.createElement('p');
    status.className = 'vff-pwa-notice-text';
    status.setAttribute('role', 'status');

    const actions = doc.createElement('div');
    actions.className = 'vff-pwa-notice-actions';

    const applyButton = doc.createElement('button');
    applyButton.type = 'button';
    applyButton.className = 'vff-btn primary';
    applyButton.textContent = apply;

    const dismissButton = doc.createElement('button');
    dismissButton.type = 'button';
    dismissButton.className = 'vff-btn quiet';
    dismissButton.textContent = dismiss;

    actions.append(applyButton, dismissButton);
    root.append(status, actions);
    doc.body.append(root);

    const remove = () => root.remove();
    applyButton.addEventListener('click', () => {
        applyButton.disabled = true;
        onApply();
    });
    dismissButton.addEventListener('click', remove);

    defer(() => {
        status.textContent = message;
    });

    return remove;
}
