// Règles de cache de l'application web installable (PWA) : pures, sans accès au navigateur, donc
// testables (tests/js/pwa-rules.test.js) et partagées par le service worker (sw.js) et la page.
//
// Principe : on ne garde hors ligne QUE des fichiers publics et immuables. Aucune page HTML, aucune
// réponse Inertia (elles contiennent le compte connecté, les contributions, le jeton CSRF), aucune
// réponse d'API ni tuile cartographique (la politique d'usage des tuiles OpenStreetMap interdit
// leur mise en cache hors du cache HTTP normal du navigateur) n'entre dans un cache du service worker.

/** Adresse du service worker (à la racine : sa portée couvre tout le site). */
export const SW_URL = '/sw.js';

/** Les caches de fichiers statiques portent la version du déploiement : un nouveau déploiement repart à vide. */
export const STATIC_CACHE_PREFIX = 'vff-static-';

/** Page « hors ligne » traduite, rangée par la page elle-même (dans la langue de l'utilisateur). */
export const OFFLINE_CACHE = 'vff-offline';
export const OFFLINE_KEY = '/__hors-ligne';

/** Fichiers servis « cache d'abord » : empreinte de contenu dans le nom (Vite) ou icônes de l'application. */
export const STATIC_PATHS = ['/build/assets/', '/icons/'];

/** Rangé à l'installation du service worker : l'icône que la page hors ligne affiche. */
export const PRECACHE = ['/icons/icon-192.png'];

/**
 * Chemins que le service worker ne touche jamais : API et documentation, médias déposés, serveur MCP,
 * pages intégrées (iframe), sonde de santé, et ses propres fichiers.
 */
export const NEVER_INTERCEPT = [
    '/api',
    '/graphql',
    '/graphiql',
    '/docs',
    '/storage',
    '/mcp',
    '/embed',
    '/up',
    '/sw.js',
    '/manifest.webmanifest',
];

/** Plafond d'entrées du cache statique (les plus anciennes partent d'abord). */
export const MAX_STATIC_ENTRIES = 300;

/** `/api` correspond à `/api` et `/api/…`, pas à `/apiary`. */
export function matchesPath(pathname, prefix) {
    const base = prefix.endsWith('/') ? prefix.slice(0, -1) : prefix;
    return pathname === base || pathname.startsWith(`${base}/`);
}

/**
 * Stratégie pour une requête :
 *   - `static`     : fichier public versionné, « cache d'abord » ;
 *   - `navigation` : page HTML, « réseau d'abord », page hors ligne si le réseau manque ;
 *   - `inertia`    : visite Inertia, « réseau d'abord », bascule en visite complète si le réseau manque ;
 *   - `network`    : le service worker ne s'en mêle pas (navigateur et réseau, comme sans service worker).
 *
 * @param {Request} request
 * @param {string} origin origine du site (`self.location.origin`)
 * @returns {'static' | 'navigation' | 'inertia' | 'network'}
 */
export function classify(request, origin) {
    if (request.method !== 'GET') return 'network';

    const url = new URL(request.url);
    if (url.origin !== origin) return 'network'; // tuiles OSM, polices, API tierces

    // Requête authentifiée par en-tête ou reprise partielle d'un fichier : jamais mise en cache.
    if (request.headers.has('Authorization') || request.headers.has('Range')) return 'network';

    if (STATIC_PATHS.some((prefix) => url.pathname.startsWith(prefix))) return 'static';
    if (NEVER_INTERCEPT.some((prefix) => matchesPath(url.pathname, prefix))) return 'network';

    if (request.headers.has('X-Inertia')) return 'inertia';
    if (request.mode === 'navigate' && request.destination === 'document') return 'navigation';

    return 'network';
}

/**
 * Une visite Inertia qui échoue faute de réseau devient une visite complète (la page hors ligne s'affiche
 * alors), sauf pour les préchargements et les rechargements partiels (interrogation périodique, filtres) :
 * ceux-là ne doivent jamais arracher l'utilisateur à la page qu'il lit.
 */
export function shouldHardVisitOnFailure(request) {
    return (
        request.headers.get('Purpose') !== 'prefetch' &&
        !request.headers.has('X-Inertia-Partial-Data')
    );
}

/** Seules les réponses complètes, de notre origine et sans interdiction explicite, entrent dans le cache. */
export function isCacheable(response) {
    if (response.status !== 200 || response.type !== 'basic') return false;
    return !/\b(no-store|private)\b/i.test(response.headers.get('Cache-Control') ?? '');
}

export function staticCacheName(version) {
    return `${STATIC_CACHE_PREFIX}${version}`;
}

/** Caches à supprimer à l'activation : les caches statiques d'une autre version (jamais la page hors ligne). */
export function staleCacheNames(names, version) {
    const current = staticCacheName(version);
    return names.filter((name) => name.startsWith(STATIC_CACHE_PREFIX) && name !== current);
}
