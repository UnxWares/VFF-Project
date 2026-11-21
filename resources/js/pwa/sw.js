// Service worker de VFF. Compilé par vite-plugin-sw.js vers public/sw.js (un seul fichier, sans module) ;
// __VFF_SW_VERSION__ change à chaque déploiement, donc le navigateur y voit une nouvelle version.
//
// Règles (rules.js) : cache d'abord pour les fichiers versionnés ; réseau d'abord pour les pages, avec une
// copie des pages publiques déjà consultées ; réseau d'abord pour les tuiles et les données GeoJSON de la
// carte déjà affichées, pour les morceaux du fond de carte libre (PMTiles) et pour les images des fiches
// déjà vues ; rien pour le reste de l'API, les écritures, les pages privées ni les autres serveurs.
import {
    basemap,
    cacheFirst,
    image,
    inertia,
    mapData,
    navigation,
    pruneExpired,
    tile,
    trimCacheBytes,
} from './handlers.js';
import {
    BASEMAP_CACHE,
    BASEMAP_MAX_AGE_MS,
    IMAGES_CACHE,
    IMAGE_MAX_AGE_MS,
    MAP_DATA_MAX_AGE_MS,
    MAX_BASEMAP_BYTES,
    MAX_IMAGE_BYTES,
    PAGE_MAX_AGE_MS,
    PRECACHE,
    TILE_MAX_AGE_MS,
    classify,
    mapDataCacheName,
    pagesCacheName,
    staleCacheNames,
    staticCacheName,
    tilesCacheName,
} from './rules.js';

/**
 * Le projet est vérifié avec la bibliothèque DOM, pas WebWorker (les deux se contredisent) : ces types décrivent
 * le peu d'API propres aux service workers dont ce fichier se sert.
 * @typedef {Event & { waitUntil(promise: Promise<unknown>): void }} SwExtendableEvent
 * @typedef {SwExtendableEvent & {
 *   request: Request,
 *   clientId: string,
 *   preloadResponse: Promise<Response | undefined>,
 *   respondWith(response: Promise<Response>): void,
 * }} SwFetchEvent
 * @typedef {SwExtendableEvent & { data: { type?: string } | null }} SwMessageEvent
 * @typedef {object} SwScope
 * @property {{ navigationPreload?: { enable(): Promise<void> } }} registration
 * @property {{ claim(): Promise<void>, get(id: string): Promise<{ url: string } | undefined> }} clients
 * @property {() => Promise<void>} skipWaiting
 * @property {Location} location
 * @property {{ storage?: { estimate?(): Promise<{usage?: number, quota?: number}> } }} navigator
 * @property {(type: string, listener: (event: any) => void) => void} addEventListener
 */
const sw = /** @type {SwScope} */ (/** @type {unknown} */ (self));

/* global __VFF_SW_VERSION__ */
const VERSION = typeof __VFF_SW_VERSION__ === 'string' ? __VFF_SW_VERSION__ : 'dev';
const CACHE = staticCacheName(VERSION);
const PAGES = pagesCacheName(VERSION);
const TILES = tilesCacheName(VERSION);
const MAP_DATA = mapDataCacheName(VERSION);

sw.addEventListener('install', (/** @type {SwExtendableEvent} */ event) => {
    // Pas de skipWaiting automatique : la page propose le rechargement (message « Nouvelle version disponible »).
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)));
});

sw.addEventListener('activate', (/** @type {SwExtendableEvent} */ event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();
            await Promise.all(staleCacheNames(names, VERSION).map((name) => caches.delete(name)));
            // Copies trop anciennes de la version courante (service worker resté actif longtemps).
            const now = Date.now();
            /** @type {Array<[string, number]>} */
            const expiring = [
                [PAGES, PAGE_MAX_AGE_MS],
                [TILES, TILE_MAX_AGE_MS],
                [MAP_DATA, MAP_DATA_MAX_AGE_MS],
                [IMAGES_CACHE, IMAGE_MAX_AGE_MS],
                [BASEMAP_CACHE, BASEMAP_MAX_AGE_MS],
            ];
            for (const [name, maxAge] of expiring) {
                if (names.includes(name)) await pruneExpired(await caches.open(name), maxAge, now);
            }
            // Plafonds en octets des caches gardés d'une version à l'autre.
            /** @type {Array<[string, number]>} */
            const budgets = [
                [IMAGES_CACHE, MAX_IMAGE_BYTES],
                [BASEMAP_CACHE, MAX_BASEMAP_BYTES],
            ];
            for (const [name, maxBytes] of budgets) {
                if (names.includes(name)) await trimCacheBytes(await caches.open(name), maxBytes);
            }
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
        pagesCache: PAGES,
        tilesCache: TILES,
        mapDataCache: MAP_DATA,
        imagesCache: IMAGES_CACHE,
        basemapCache: BASEMAP_CACHE,
        origin: sw.location.origin,
        // Page qui demande la ressource : une image n'est gardée que pour une page publique.
        clientUrl: async () =>
            event.clientId ? (await sw.clients.get(event.clientId))?.url : undefined,
        waitUntil: (p) => event.waitUntil(p),
        now: () => Date.now(),
        estimate: async () => sw.navigator?.storage?.estimate?.(),
    };

    switch (classify(request, sw.location.origin)) {
        case 'static':
            event.respondWith(cacheFirst(request, deps));
            break;
        case 'tile':
            event.respondWith(tile(request, deps));
            break;
        case 'mapdata':
            event.respondWith(mapData(request, deps));
            break;
        case 'basemap':
            event.respondWith(basemap(request, deps));
            break;
        case 'image':
            event.respondWith(image(request, deps));
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
