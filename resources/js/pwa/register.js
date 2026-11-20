// Enregistrement du service worker, suivi des mises à jour, désinscription. Toutes les dépendances du navigateur
// sont injectées : la logique se teste sans DOM (tests/js/pwa-register.test.js).
import { SW_URL, isVffCache } from './rules.js';

/** Au plus une recherche de nouvelle version par heure quand l'onglet redevient visible. */
export const UPDATE_CHECK_INTERVAL_MS = 60 * 60 * 1000;

/**
 * Désinscrit tous les service workers du site et vide leurs caches (fichiers, pages, tuiles, données de la carte, page hors ligne).
 * Appelée en développement (HMR : un service worker resté d'un essai de production fausserait tout) et quand
 * la PWA est coupée (VITE_PWA=false).
 *
 * @param {ServiceWorkerContainer} serviceWorker
 * @param {CacheStorage | undefined} cacheStorage
 */
export async function unregisterServiceWorker(serviceWorker, cacheStorage) {
    const registrations = await serviceWorker.getRegistrations();
    await Promise.all(registrations.map((registration) => registration.unregister()));

    if (cacheStorage) {
        const names = await cacheStorage.keys();
        await Promise.all(names.filter(isVffCache).map((name) => cacheStorage.delete(name)));
    }
}

/**
 * Prévient (une seule fois par version) qu'un service worker plus récent attend, puis recharge la page quand
 * l'utilisateur a demandé la mise à jour. Le tout premier service worker n'est pas une mise à jour : aucun
 * message, aucun rechargement.
 *
 * @param {ServiceWorkerRegistration} registration
 * @param {ServiceWorkerContainer} serviceWorker
 * @param {{onUpdateReady: (apply: () => void) => void, reload: () => void}} handlers
 */
export function watchForUpdates(registration, serviceWorker, { onUpdateReady, reload }) {
    let applying = false;
    let announced = null;

    const apply = () => {
        applying = true;
        registration.waiting?.postMessage({ type: 'SKIP_WAITING' });
    };
    const announce = (worker) => {
        if (!serviceWorker.controller || announced === worker) return;
        announced = worker;
        onUpdateReady(apply);
    };

    if (registration.waiting) announce(registration.waiting);

    registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        worker?.addEventListener('statechange', () => {
            if (worker.state === 'installed') announce(worker);
        });
    });

    serviceWorker.addEventListener('controllerchange', () => {
        if (applying) reload();
    });
}

/** Vrai si la dernière recherche de mise à jour date de plus d'une heure. */
export function shouldCheckForUpdate(lastCheck, now, interval = UPDATE_CHECK_INTERVAL_MS) {
    return now - lastCheck >= interval;
}

/**
 * Enregistre le service worker, sauf en développement, si la PWA est coupée, ou hors contexte sécurisé
 * (HTTPS ou localhost). Dans les deux premiers cas, désinscrit ce qui traîne.
 *
 * @returns {Promise<ServiceWorkerRegistration | null>}
 */
export async function startServiceWorker({
    serviceWorker,
    cacheStorage,
    dev,
    enabled,
    secure,
    handlers,
}) {
    if (!serviceWorker) return null;

    if (dev || !enabled) {
        await unregisterServiceWorker(serviceWorker, cacheStorage);
        return null;
    }
    if (!secure) return null;

    // updateViaCache: 'none' : le fichier du service worker n'est jamais pris dans le cache HTTP.
    const registration = await serviceWorker.register(SW_URL, {
        scope: '/',
        updateViaCache: 'none',
    });
    watchForUpdates(registration, serviceWorker, handlers);

    return registration;
}
