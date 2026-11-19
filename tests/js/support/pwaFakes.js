// Doubles de test pour la PWA : requête, cache et stockage de caches en mémoire (Node n'a ni service worker ni Cache API).

/** Requête minimale (le constructeur Request refuse le mode « navigate »). */
export function fakeRequest(
    url,
    { method = 'GET', mode = 'cors', destination = '', headers = {} } = {},
) {
    return { method, url, mode, destination, headers: new Headers(headers) };
}

/**
 * Cache en mémoire, clé = URL (ou chemin) : même ordre que la vraie Cache API, où ranger à nouveau une
 * adresse la place en dernier.
 */
export function fakeCache() {
    const entries = new Map();
    const keyOf = (request) => (typeof request === 'string' ? request : request.url);
    return {
        entries,
        match: async (request) => entries.get(keyOf(request))?.clone(),
        put: async (request, response) => {
            entries.delete(keyOf(request));
            entries.set(keyOf(request), response);
        },
        delete: async (request) => entries.delete(keyOf(request)),
        keys: async () => [...entries.keys()],
        addAll: async (urls) => urls.forEach((url) => entries.set(url, new Response('x'))),
    };
}

export function fakeCacheStorage(initial = []) {
    const caches = new Map(initial.map((name) => [name, fakeCache()]));
    return {
        caches,
        open: async (name) => {
            if (!caches.has(name)) caches.set(name, fakeCache());
            return caches.get(name);
        },
        keys: async () => [...caches.keys()],
        delete: async (name) => caches.delete(name),
    };
}

/** Réponse « basic » 200 : `new Response` n'a ni type ni `redirected` modifiables, on les simule. */
export function basicResponse(
    body = 'ok',
    { status = 200, headers = {}, redirected = false } = {},
) {
    const response = new Response(body, { status, headers });
    Object.defineProperty(response, 'type', { value: 'basic' });
    Object.defineProperty(response, 'redirected', { value: redirected });
    return response;
}
