// Stratégies du service worker, avec leurs dépendances injectées (cache, réseau, horloge, quota) : testables
// sans navigateur (tests/js/pwa-handlers.test.js).
import {
    fillOnceProps,
    hasEssentialOnceProps,
    htmlFromInertiaCopy,
    markHtmlCopy,
    markPage,
    missingOnceProps,
    readPageFromHtml,
} from './offlineCopy.js';
import {
    MAP_DATA_MAX_AGE_MS,
    MAX_MAP_DATA_ENTRIES,
    MAX_PAGE_ENTRIES,
    MAX_STATIC_ENTRIES,
    MAX_TILE_ENTRIES,
    OFFLINE_CACHE,
    OFFLINE_KEY,
    PAGE_MAX_AGE_MS,
    SAVED_AT_HEADER,
    TILE_MAX_AGE_MS,
    hasStorageRoom,
    isCacheable,
    isExpired,
    isOfflinePageRequest,
    isStorableMapData,
    isStorablePage,
    isStorableTile,
    pageCacheKey,
    shouldHardVisitOnFailure,
} from './rules.js';

/**
 * @typedef {object} Deps
 * @property {CacheStorage} caches
 * @property {typeof fetch} fetch
 * @property {string} cacheName cache des fichiers statiques
 * @property {string} [pagesCache] cache des pages
 * @property {string} [tilesCache] cache des tuiles
 * @property {string} [mapDataCache] cache des données GeoJSON de la carte
 * @property {(p: Promise<unknown>) => void} waitUntil
 * @property {() => number} [now]
 * @property {() => Promise<{usage?: number, quota?: number} | undefined>} [estimate]
 * @property {Promise<Response | undefined>} [preload]
 */

/** Supprime les entrées les plus anciennes au-delà du plafond (la Cache API garde l'ordre de rangement). */
export async function trimCache(cache, max = MAX_STATIC_ENTRIES) {
    const keys = await cache.keys();
    await Promise.all(
        keys.slice(0, Math.max(0, keys.length - max)).map((key) => cache.delete(key)),
    );
}

/** Supprime les copies trop anciennes d'un cache (à l'activation du service worker). */
export async function pruneExpired(cache, maxAge, now) {
    const keys = await cache.keys();
    for (const key of keys) {
        const response = await cache.match(key);
        if (!response || isExpired(response.headers.get(SAVED_AT_HEADER), now, maxAge)) {
            await cache.delete(key);
        }
    }
}

/**
 * Range une copie datée, en arrière-plan, si le quota le permet ; puis applique le plafond. Une erreur
 * (quota dépassé, cache indisponible) ne touche jamais la réponse déjà rendue à la page.
 *
 * @param {Deps} deps
 * @param {string} cacheName
 * @param {string} key
 * @param {Response} response copie (clone) de la réponse du réseau
 * @param {number} max
 */
function remember({ caches, waitUntil, now = Date.now, estimate }, cacheName, key, response, max) {
    waitUntil(
        (async () => {
            if (!hasStorageRoom(await estimate?.())) return;
            const headers = new Headers(response.headers);
            headers.set(SAVED_AT_HEADER, new Date(now()).toISOString());
            const copy = new Response(await response.arrayBuffer(), {
                status: response.status,
                statusText: response.statusText,
                headers,
            });
            const cache = await caches.open(cacheName);
            await cache.put(key, copy);
            await trimCache(cache, max);
        })().catch(() => {}),
    );
}

/** Copie encore valable, ou null (une copie périmée est supprimée au passage). */
async function readCopy(cache, key, now, maxAge) {
    const copy = await cache.match(key);
    if (!copy) return null;
    if (isExpired(copy.headers.get(SAVED_AT_HEADER), now, maxAge)) {
        await cache.delete(key);
        return null;
    }
    return copy;
}

/** En-têtes d'une copie servie hors ligne : jamais rangée ailleurs (no-store). */
function copyHeaders(copy, contentType) {
    const headers = new Headers(copy.headers);
    headers.set('Content-Type', contentType);
    headers.set('Cache-Control', 'no-store');
    return headers;
}

/**
 * « Cache d'abord » : fichier versionné déjà rangé, sinon réseau puis rangement (en arrière-plan).
 *
 * @param {Request} request
 * @param {Deps} deps
 */
export async function cacheFirst(request, { caches, fetch, cacheName, waitUntil }) {
    const cache = await caches.open(cacheName);
    const hit = await cache.match(request);
    if (hit) return hit;

    const response = await fetch(request);
    if (isCacheable(response)) {
        waitUntil(cache.put(request, response.clone()).then(() => trimCache(cache)));
    }
    return response;
}

/** La page « hors ligne » rangée par l'application (voir offlinePage.js), ou une erreur réseau native. */
export async function offlineResponse(caches) {
    const cache = await caches.open(OFFLINE_CACHE);
    const stored = await cache.match(OFFLINE_KEY);
    if (!stored) return Response.error();

    return new Response(await stored.text(), {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
    });
}

/**
 * Document HTML encore valable parmi les pages rangées, pour compléter une copie Inertia, la plus récente
 * d'abord : de préférence une page qui porte toutes les props « once » qui manquent à la copie
 * (traductions de la même langue, menus des accidents), sinon une page qui porte au moins les
 * indispensables ; null si aucune ne convient. `provided` : clés que le navigateur recollera lui-même.
 *
 * @param {Cache} cache
 * @param {number} now
 * @param {object} page copie Inertia
 * @param {string[]} [provided]
 * @returns {Promise<string | null>}
 */
async function findShell(cache, now, page, provided = []) {
    let fallback = null;
    const keys = await cache.keys();
    for (const key of [...keys].reverse()) {
        const url = typeof key === 'string' ? key : key.url;
        if (!url.endsWith('__vff-copy=html')) continue;
        const copy = await readCopy(cache, key, now, PAGE_MAX_AGE_MS);
        if (!copy) continue;
        const html = await copy.text();
        const complete = fillOnceProps(page, readPageFromHtml(html));
        if (missingOnceProps(complete).every((k) => provided.includes(k))) return html;
        if (fallback === null && hasEssentialOnceProps(complete, provided)) fallback = html;
    }
    return fallback;
}

/** Clés des props « once » que la page courante du navigateur porte déjà : Inertia les recolle à la réponse. */
function clientOnceProps(request) {
    return (request.headers.get('X-Inertia-Except-Once-Props') ?? '')
        .split(',')
        .map((key) => key.trim())
        .filter(Boolean);
}

/**
 * Copie Inertia complétée pour une visite hors ligne : les props « once » que le navigateur ne recollera
 * pas sont reprises d'une page rangée (findShell). Null s'il manque encore une prop indispensable : la
 * visite complète qui suit sert alors la copie HTML ou la page hors ligne.
 */
async function completeInertiaCopy(cache, now, page, request) {
    const provided = clientOnceProps(request);
    let complete = page;
    if (!missingOnceProps(page).every((key) => provided.includes(key))) {
        const shell = await findShell(cache, now, page, provided);
        if (shell) complete = fillOnceProps(page, readPageFromHtml(shell));
    }
    return hasEssentialOnceProps(complete, provided) ? complete : null;
}

/**
 * Document HTML d'une page à servir hors ligne : sa copie HTML, sinon sa copie Inertia glissée dans le
 * document d'une autre page rangée ; null si la page n'a jamais été consultée.
 */
async function offlinePageCopy(request, cache, now) {
    const html = await readCopy(cache, pageCacheKey(request.url, 'html'), now, PAGE_MAX_AGE_MS);
    if (html) {
        const body = markHtmlCopy(await html.text(), html.headers.get(SAVED_AT_HEADER));
        return new Response(body, {
            status: 200,
            headers: copyHeaders(html, 'text/html; charset=utf-8'),
        });
    }

    const data = await readCopy(cache, pageCacheKey(request.url, 'inertia'), now, PAGE_MAX_AGE_MS);
    if (!data) return null;

    let page;
    try {
        page = await data.json();
    } catch {
        return null;
    }
    const shell = await findShell(cache, now, page);
    if (!shell) return null;
    const body = htmlFromInertiaCopy(shell, page, data.headers.get(SAVED_AT_HEADER));
    if (!body || !readPageFromHtml(body)) return null;
    return new Response(body, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
    });
}

/**
 * « Réseau d'abord » pour une page HTML : toujours la réponse du serveur (même une erreur 404 ou 500), rangée
 * si le serveur l'a déclarée lisible hors ligne. Sans réseau : la copie de la page si elle a été consultée,
 * sinon la page hors ligne. `preload` : réponse de la précharge de navigation.
 *
 * @param {Request} request
 * @param {Deps} deps
 */
export async function navigation(request, deps) {
    const { caches, fetch, preload, pagesCache, now = Date.now } = deps;
    const offlineReady = Boolean(pagesCache) && isOfflinePageRequest(request);

    try {
        const response = (await preload) ?? (await fetch(request));
        if (offlineReady && isStorablePage(response)) {
            remember(
                deps,
                pagesCache,
                pageCacheKey(request.url, 'html'),
                response.clone(),
                MAX_PAGE_ENTRIES,
            );
        }
        return response;
    } catch {
        if (offlineReady) {
            const copy = await offlinePageCopy(request, await caches.open(pagesCache), now());
            if (copy) return copy;
        }
        return offlineResponse(caches);
    }
}

/**
 * « Réseau d'abord » pour une visite Inertia, rangée comme une page HTML. Sans réseau : la copie Inertia de
 * la page si elle existe, complétée des props « once » que le navigateur n'a pas (completeInertiaCopy) ;
 * sinon la réponse 409 + X-Inertia-Location demande à Inertia une visite complète de la même adresse, donc
 * une navigation (copie HTML ou page hors ligne).
 *
 * @param {Request} request
 * @param {Deps} deps
 */
export async function inertia(request, deps) {
    const { caches, fetch, pagesCache, now = Date.now } = deps;
    const offlineReady = Boolean(pagesCache) && isOfflinePageRequest(request);

    try {
        const response = await fetch(request);
        if (offlineReady && isStorablePage(response)) {
            remember(
                deps,
                pagesCache,
                pageCacheKey(request.url, 'inertia'),
                response.clone(),
                MAX_PAGE_ENTRIES,
            );
        }
        return response;
    } catch (error) {
        if (!shouldHardVisitOnFailure(request)) throw error;

        if (offlineReady) {
            const cache = await caches.open(pagesCache);
            const key = pageCacheKey(request.url, 'inertia');
            const copy = await readCopy(cache, key, now(), PAGE_MAX_AGE_MS);
            const page = copy ? await copy.json().catch(() => null) : null;
            const complete = page ? await completeInertiaCopy(cache, now(), page, request) : null;
            if (copy && complete) {
                return new Response(
                    JSON.stringify(markPage(complete, copy.headers.get(SAVED_AT_HEADER))),
                    { status: 200, headers: copyHeaders(copy, 'application/json') },
                );
            }
        }
        return new Response(null, { status: 409, headers: { 'X-Inertia-Location': request.url } });
    }
}

/**
 * « Réseau d'abord » avec copie : en ligne, rien ne change (la page reçoit la réponse du serveur, une copie
 * est rangée si elle convient) ; sans réseau, la copie d'une réponse déjà reçue et encore valable, sinon
 * l'erreur réseau d'origine.
 *
 * @param {Request} request
 * @param {Deps} deps
 * @param {{cacheName?: string, isStorable: (r: Response) => boolean, max: number, maxAge: number}} rule
 */
async function networkFirstCopy(request, deps, { cacheName, isStorable, max, maxAge }) {
    const { caches, fetch, now = Date.now } = deps;
    try {
        const response = await fetch(request);
        if (cacheName && isStorable(response)) {
            remember(deps, cacheName, request.url, response.clone(), max);
        }
        return response;
    } catch (error) {
        if (cacheName) {
            const copy = await readCopy(await caches.open(cacheName), request.url, now(), maxAge);
            if (copy) return copy;
        }
        throw error;
    }
}

/**
 * Tuile vectorielle de VFF : la copie d'une tuile déjà affichée sert sans réseau.
 *
 * @param {Request} request
 * @param {Deps} deps
 */
export function tile(request, deps) {
    return networkFirstCopy(request, deps, {
        cacheName: deps.tilesCache,
        isStorable: isStorableTile,
        max: MAX_TILE_ENTRIES,
        maxAge: TILE_MAX_AGE_MS,
    });
}

/**
 * Données GeoJSON de la carte (gares d'une ère, accidents) : la copie d'un fichier déjà affiché sert sans
 * réseau ; une ère jamais affichée reste sans gares (la carte le dit, OfflineCopyNotice).
 *
 * @param {Request} request
 * @param {Deps} deps
 */
export function mapData(request, deps) {
    return networkFirstCopy(request, deps, {
        cacheName: deps.mapDataCache,
        isStorable: isStorableMapData,
        max: MAX_MAP_DATA_ENTRIES,
        maxAge: MAP_DATA_MAX_AGE_MS,
    });
}
