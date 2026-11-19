// Service worker de VFF. Compilé par vite-plugin-sw.js vers public/sw.js (un seul fichier, sans module) ;
// __VFF_SW_VERSION__ change à chaque déploiement, donc le navigateur y voit une nouvelle version.
//
// Règles (rules.js) : cache d'abord pour les fichiers versionnés, réseau d'abord pour les pages, jamais
// de cache pour l'API, les tuiles, les pages ni les réponses Inertia.
import { cacheFirst, inertia, navigation } from './handlers.js';
import { PRECACHE, classify, staleCacheNames, staticCacheName } from './rules.js';

/* global __VFF_SW_VERSION__ */
const VERSION = typeof __VFF_SW_VERSION__ === 'string' ? __VFF_SW_VERSION__ : 'dev';
const CACHE = staticCacheName(VERSION);

self.addEventListener('install', (event) => {
    // Pas de skipWaiting automatique : la page propose le rechargement (message « Nouvelle version disponible »).
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const stale = staleCacheNames(await caches.keys(), VERSION);
            await Promise.all(stale.map((name) => caches.delete(name)));
            // Précharge de navigation : la requête réseau part pendant le démarrage du service worker.
            await self.registration.navigationPreload?.enable();
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const deps = {
        caches,
        fetch: (r) => fetch(r),
        cacheName: CACHE,
        waitUntil: (p) => event.waitUntil(p),
    };

    switch (classify(request, self.location.origin)) {
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
