// Point d'entrée côté page de la PWA, chargé à la demande par app.js (après le montage de l'application).
// Colle le navigateur (navigator, caches, i18next, DOM) à la logique testée de register.js.
import i18next from 'i18next';
import { renderOfflineHtml, storeOfflinePage } from './offlinePage.js';
import { UPDATE_CHECK_INTERVAL_MS, shouldCheckForUpdate, startServiceWorker } from './register.js';
import { showUpdateNotice } from './updateNotice.js';

/** Fabrique la page hors ligne dans la langue courante et la range pour le service worker. */
async function refreshOfflinePage() {
    const t = (key) => i18next.t(`pwa.${key}`);
    const html = renderOfflineHtml({
        lang: i18next.language,
        documentTitle: t('offline_document_title'),
        title: t('offline_title'),
        text: t('offline_text'),
        retry: t('offline_retry'),
    });
    await storeOfflinePage(caches, html);
}

function announceUpdate(apply) {
    const t = (key) => i18next.t(`pwa.${key}`);
    showUpdateNotice(document, {
        message: t('update_available'),
        apply: t('update_reload'),
        dismiss: t('update_later'),
        onApply: apply,
    });
}

async function start() {
    const registration = await startServiceWorker({
        serviceWorker: navigator.serviceWorker,
        cacheStorage: globalThis.caches,
        dev: import.meta.env.DEV,
        // Coupe-circuit : `VITE_PWA=false pnpm build` désinscrit les service workers déjà installés.
        enabled: import.meta.env.VITE_PWA !== 'false',
        secure: window.isSecureContext,
        handlers: { onUpdateReady: announceUpdate, reload: () => window.location.reload() },
    });
    if (!registration) return;

    refreshOfflinePage().catch(() => {});
    i18next.on('languageChanged', () => refreshOfflinePage().catch(() => {}));

    // Onglet resté ouvert longtemps (une application Inertia ne recharge presque jamais la page) : le
    // navigateur ne cherche une nouvelle version qu'à la navigation, on la cherche au retour sur l'onglet.
    let lastCheck = Date.now();
    document.addEventListener('visibilitychange', () => {
        if (
            document.visibilityState !== 'visible' ||
            !shouldCheckForUpdate(lastCheck, Date.now(), UPDATE_CHECK_INTERVAL_MS)
        )
            return;
        lastCheck = Date.now();
        registration.update().catch(() => {});
    });
}

export function initPwa() {
    if (!('serviceWorker' in navigator)) return;
    const run = () => start().catch(() => {});
    if (document.readyState === 'complete') run();
    else window.addEventListener('load', run, { once: true });
}
