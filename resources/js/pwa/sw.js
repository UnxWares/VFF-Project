// Service worker de VFF. Compilé par vite-plugin-sw.js vers public/sw.js (un seul fichier, sans module) ;
// __VFF_SW_VERSION__ change à chaque déploiement, donc le navigateur y voit une nouvelle version.
//
// Règles (rules.js) : cache d'abord pour les fichiers versionnés, réseau d'abord pour les pages, jamais
// de cache pour l'API, les tuiles, les pages ni les réponses Inertia.
import { cacheFirst, inertia, navigation } from './handlers.js';
import { PRECACHE, classify, staleCacheNames, staticCacheName } from './rules.js';

/**
 * Le projet est vérifié avec la bibliothèque DOM, pas WebWorker (les deux se contredisent) : ces types décrivent
 * le peu d'API propres aux service workers dont ce fichier se sert.
 * @typedef {Event & { waitUntil(promise: Promise<unknown>): void }} SwExtendableEvent
 * @typedef {SwExtendableEvent & {
 *   request: Request,
 *   preloadResponse: Promise<Response | undefined>,
 *   respondWith(response: Promise<Response>): void,
 * }} SwFetchEvent
 * @typedef {SwExtendableEvent & { data: { type?: string } | null }} SwMessageEvent
 * @typedef {object} SwScope
 * @property {{ navigationPreload?: { enable(): Promise<void> } }} registration
 * @property {{ claim(): Promise<void> }} clients
 * @property {() => Promise<void>} skipWaiting
 * @property {Location} location
 * @property {(type: string, listener: (event: any) => void) => void} addEventListener
 */
const sw = /** @type {SwScope} */ (/** @type {unknown} */ (self));

/* global __VFF_SW_VERSION__ */
const VERSION = typeof __VFF_SW_VERSION__ === 'string' ? __VFF_SW_VERSION__ : 'dev';
const CACHE = staticCacheName(VERSION);

sw.addEventListener('install', (/** @type {SwExtendableEvent} */ event) => {
    // Pas de skipWaiting automatique : la page propose le rechargement (message « Nouvelle version disponible »).
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)));
});

sw.addEventListener('activate', (/** @type {SwExtendableEvent} */ event) => {
    event.waitUntil(
        (async () => {
            const stale = staleCacheNames(await caches.keys(), VERSION);
            await Promise.all(stale.map((name) => caches.delete(name)));
            // Précharge de navigation : la requête réseau part pendant le démarrage du service worker.
            await sw.registration.navigationPreload?.enable();
            await sw.clients.claim();
        })(),
    );
});

sw.addEventListener('message', (/** @type {SwMessageEvent} */ event) => {
    if (event.data?.type === 'SKIP_WAITING') sw.skipWaiting();
});

sw.addEventListener('fetch', (/** @type {SwFetchEvent} */ event) => {
    const { request } = event;
    const deps = {
        caches,
        fetch: (r) => fetch(r),
        cacheName: CACHE,
        waitUntil: (p) => event.waitUntil(p),
    };

    switch (classify(request, sw.location.origin)) {
        case 'static':
            event.respondWith(cacheFirst(request, deps));
            break;
        case 'navigation':
            event.respondWith(navigation(request, { ...deps, preload: event.preloadResponse }));
            break;
        case 'inertia':
            event.respondWith(inertia(request, deps));
            break;
        default:
            // Ni respondWith ni cache : le navigateur traite la requête comme sans service worker.
            break;
    }
});
