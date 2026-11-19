// Stratégies du service worker, avec leurs dépendances injectées (cache, réseau) : testables sans navigateur.
import {
    MAX_STATIC_ENTRIES,
    OFFLINE_CACHE,
    OFFLINE_KEY,
    isCacheable,
    shouldHardVisitOnFailure,
} from './rules.js';

/** Supprime les entrées les plus anciennes au-delà du plafond. */
export async function trimCache(cache, max = MAX_STATIC_ENTRIES) {
    const keys = await cache.keys();
    await Promise.all(
        keys.slice(0, Math.max(0, keys.length - max)).map((key) => cache.delete(key)),
    );
}

/**
 * « Cache d'abord » : fichier versionné déjà rangé, sinon réseau puis rangement (en arrière-plan).
 *
 * @param {Request} request
 * @param {{caches: CacheStorage, fetch: typeof fetch, cacheName: string, waitUntil: (p: Promise<unknown>) => void}} deps
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
 * « Réseau d'abord » pour une page HTML : toujours la réponse du serveur (même une erreur 404 ou 500) ;
 * seule une panne réseau donne la page hors ligne. `preload` : réponse de la précharge de navigation.
 */
export async function navigation(request, { caches, fetch, preload }) {
    try {
        return (await preload) ?? (await fetch(request));
    } catch {
        return offlineResponse(caches);
    }
}

/**
 * « Réseau d'abord » pour une visite Inertia. Sans réseau, la réponse 409 + X-Inertia-Location demande à
 * Inertia une visite complète de la même adresse : c'est alors une navigation, donc la page hors ligne.
 */
export async function inertia(request, { fetch }) {
    try {
        return await fetch(request);
    } catch (error) {
        if (!shouldHardVisitOnFailure(request)) throw error;
        return new Response(null, { status: 409, headers: { 'X-Inertia-Location': request.url } });
    }
}
