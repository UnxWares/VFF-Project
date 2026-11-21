// Règles de cache de l'application web installable (PWA) : pures, sans accès au navigateur, donc
// testables (tests/js/pwa-rules.test.js) et partagées par le service worker (sw.js) et la page.
//
// Ce qui est gardé hors ligne, et seulement cela :
//   - les fichiers publics versionnés (build Vite, dictionnaire de l'interface, icônes), « cache d'abord » ;
//   - les pages déjà consultées (HTML et réponses Inertia), « réseau d'abord », quand le SERVEUR les a
//     déclarées lisibles hors ligne (en-tête X-VFF-Offline, MarkOfflineReadable) : pages publiques vues
//     par un visiteur non connecté, sans message de session. Jamais les pages de compte, de contribution,
//     de validation ou d'administration, même si l'en-tête apparaissait par erreur (liste PRIVATE_PATHS) ;
//   - les tuiles vectorielles de VFF déjà affichées par la carte, « réseau d'abord », avec un plafond et
//     une durée de vie. Aucune tuile d'un autre serveur : celles d'OpenStreetMap ou d'OpenRailwayMap
//     restent dans le cache HTTP du navigateur, selon leurs en-têtes, comme le demandent leurs règles
//     d'usage. Rien n'est jamais préchargé ;
//   - les données GeoJSON de la carte déjà affichées (gares d'une ère, points des accidents), « réseau
//     d'abord », avec un plafond plus bas (quelques Mo chacune) et la même durée de vie que les tuiles.
//     Le style de la carte est écrit dans le code (aucune requête) ; les glyphes viennent d'un tiers
//     (OpenFreeMap) et ne sont pas gardés : hors ligne, MapLibre dessine les noms avec une police locale.
// Les caches portent la version du déploiement : un nouveau déploiement repart à vide.

/** Adresse du service worker (à la racine : sa portée couvre tout le site). */
export const SW_URL = '/sw.js';

/** Fichiers versionnés (Vite, icônes). */
export const STATIC_CACHE_PREFIX = 'vff-static-';
/** Pages déjà consultées (HTML et réponses Inertia). */
export const PAGES_CACHE_PREFIX = 'vff-pages-';
/** Tuiles vectorielles de VFF déjà affichées. */
export const TILES_CACHE_PREFIX = 'vff-tiles-';
/** Données GeoJSON de la carte déjà affichées (gares d'une ère, accidents). */
export const MAP_DATA_CACHE_PREFIX = 'vff-mapdata-';
/** Caches qui portent la version du déploiement (supprimés à l'activation d'une autre version). */
export const VERSIONED_CACHE_PREFIXES = [
    STATIC_CACHE_PREFIX,
    PAGES_CACHE_PREFIX,
    TILES_CACHE_PREFIX,
    MAP_DATA_CACHE_PREFIX,
];

/** Page « hors ligne » traduite, rangée par la page elle-même (dans la langue de l'utilisateur). */
export const OFFLINE_CACHE = 'vff-offline';
export const OFFLINE_KEY = '/__hors-ligne';

/**
 * Fichiers servis « cache d'abord » : empreinte de contenu dans le nom (Vite, dictionnaire de l'interface
 * `/i18n/{langue}/{empreinte}.json`) ou icônes de l'application. Le dictionnaire gardé permet aux pages
 * rangées de s'afficher hors ligne avec leurs textes.
 */
export const STATIC_PATHS = ['/build/assets/', '/icons/', '/i18n/'];

/** Rangé à l'installation du service worker : l'icône que la page hors ligne affiche. */
export const PRECACHE = ['/icons/icon-192.png'];

/**
 * Chemins que le service worker ne touche jamais : API et documentation, médias déposés, serveur MCP,
 * pages intégrées (iframe), sonde de santé, et ses propres fichiers. Exceptions : les tuiles vectorielles
 * de VFF (TILE_PATH) et les données GeoJSON de la carte (MAP_DATA_PATH), sous /api/v1.
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

/**
 * Pages jamais gardées hors ligne, quoi que dise le serveur : compte, connexion, contribution, validation,
 * administration, notifications, signalements, changement de langue.
 */
export const PRIVATE_PATHS = [
    '/login',
    '/register',
    '/logout',
    '/forgot-password',
    '/reset-password',
    '/email',
    '/profile',
    '/notifications',
    '/contribute',
    '/validate',
    '/admin',
    '/reports',
    '/locale',
];

/** Paramètres d'adresse qui portent un secret (jeton, lien signé) : la requête n'est jamais mise en cache. */
export const SECRET_QUERY_PARAMS = [
    'token',
    'api_token',
    'access_token',
    'signature',
    'expires',
    'code',
    'key',
];

/** Tuile vectorielle de VFF : /api/v1/…/tiles/{z}/{x}/{y}.mvt (ères, comparaisons, signalisation, voies vertes…). */
const TILE_PATH = /^\/api\/v1\/(?:[a-z0-9-]+\/)*tiles\/\d{1,2}\/\d{1,7}\/\d{1,7}\.mvt$/;

/**
 * Données GeoJSON chargées par la carte /maps : gares d'une ère (/api/v1/eras/{année}/stations.geojson) et
 * points des accidents (/api/v1/accidents.geojson). Pas le tracé complet d'une ère (lines.geojson) ni
 * celui d'une ligne : ce sont des téléchargements, jamais affichés par la carte.
 */
const MAP_DATA_PATH = /^\/api\/v1\/(?:eras\/\d{1,4}\/stations|accidents)\.geojson$/;

/** En-tête posé par le serveur sur une page lisible hors ligne (App\Http\Middleware\MarkOfflineReadable). */
export const OFFLINE_HEADER = 'X-VFF-Offline';
export const OFFLINE_HEADER_VALUE = 'allow';

/** Date de la copie, ajoutée par le service worker à chaque réponse qu'il range (ISO 8601). */
export const SAVED_AT_HEADER = 'X-VFF-Saved-At';

/** Propriété Inertia ajoutée à une copie servie hors ligne : `{ saved_at }` (lue par OfflineCopyNotice). */
export const OFFLINE_COPY_PROP = 'offline_copy';

const DAY_MS = 24 * 60 * 60 * 1000;

/** Plafonds et durées de vie, par cache (les entrées les plus anciennes partent d'abord). */
export const MAX_STATIC_ENTRIES = 300;
export const MAX_PAGE_ENTRIES = 60;
export const PAGE_MAX_AGE_MS = 30 * DAY_MS;
export const MAX_TILE_ENTRIES = 500;
export const TILE_MAX_AGE_MS = 7 * DAY_MS;
/** Un fichier de gares d'une ère pèse 2,5 à 4 Mo : huit fichiers au plus (une quarantaine de Mo). */
export const MAX_MAP_DATA_ENTRIES = 8;
export const MAP_DATA_MAX_AGE_MS = 7 * DAY_MS;

/**
 * Quota du navigateur : on ne range plus de page ni de tuile quand le site occupe déjà plus de la moitié
 * du quota accordé, ou plus de 100 Mo.
 */
export const STORAGE_QUOTA_SHARE = 0.5;
export const STORAGE_BUDGET_BYTES = 100 * 1024 * 1024;

/** `/api` correspond à `/api` et `/api/…`, pas à `/apiary`. */
export function matchesPath(pathname, prefix) {
    const base = prefix.endsWith('/') ? prefix.slice(0, -1) : prefix;
    return pathname === base || pathname.startsWith(`${base}/`);
}

/** Vrai si l'adresse porte un jeton ou une signature (nom de paramètre sans tenir compte de la casse). */
export function hasSecretParam(url) {
    for (const name of new URL(url).searchParams.keys()) {
        if (SECRET_QUERY_PARAMS.includes(name.toLowerCase())) return true;
    }
    return false;
}

export function isTilePath(pathname) {
    return TILE_PATH.test(pathname);
}

export function isMapDataPath(pathname) {
    return MAP_DATA_PATH.test(pathname);
}

/**
 * Stratégie pour une requête :
 *   - `static`     : fichier public versionné, « cache d'abord » ;
 *   - `tile`       : tuile vectorielle de VFF, « réseau d'abord », copie si le réseau manque ;
 *   - `mapdata`    : données GeoJSON de la carte, « réseau d'abord », copie si le réseau manque ;
 *   - `navigation` : page HTML, « réseau d'abord », copie ou page hors ligne si le réseau manque ;
 *   - `inertia`    : visite Inertia, « réseau d'abord », copie ou visite complète si le réseau manque ;
 *   - `network`    : le service worker ne s'en mêle pas (navigateur et réseau, comme sans service worker).
 *
 * @param {Request} request
 * @param {string} origin origine du site (`self.location.origin`)
 * @returns {'static' | 'tile' | 'mapdata' | 'navigation' | 'inertia' | 'network'}
 */
export function classify(request, origin) {
    if (request.method !== 'GET') return 'network';

    const url = new URL(request.url);
    if (url.origin !== origin) return 'network'; // tuiles OSM, polices, API tierces

    // Requête authentifiée par en-tête ou par l'adresse, reprise partielle d'un fichier : jamais mise en cache.
    if (request.headers.has('Authorization') || request.headers.has('Range')) return 'network';
    if (hasSecretParam(request.url)) return 'network';

    if (STATIC_PATHS.some((prefix) => url.pathname.startsWith(prefix))) return 'static';
    if (isTilePath(url.pathname)) return 'tile';
    if (isMapDataPath(url.pathname)) return 'mapdata';
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

/**
 * Une page peut-elle être rangée, ou servie depuis le cache ? Seulement une page complète (ni préchargement,
 * ni rechargement partiel) hors des chemins privés.
 *
 * @param {Request} request
 */
export function isOfflinePageRequest(request) {
    const { pathname } = new URL(request.url);
    return (
        !PRIVATE_PATHS.some((prefix) => matchesPath(pathname, prefix)) &&
        request.headers.get('Purpose') !== 'prefetch' &&
        !request.headers.has('X-Inertia-Partial-Data') &&
        !request.headers.has('X-Inertia-Partial-Component') &&
        !request.headers.has('X-Inertia-Partial-Except')
    );
}

function forbidsStorage(response) {
    return /\bno-store\b/i.test(response.headers.get('Cache-Control') ?? '');
}

/** Fichier statique : réponse complète, de notre origine, sans interdiction explicite. */
export function isCacheable(response) {
    if (response.status !== 200 || response.type !== 'basic') return false;
    return !/\b(no-store|private)\b/i.test(response.headers.get('Cache-Control') ?? '');
}

/**
 * Page : réponse complète de notre origine, non redirigée, que le serveur a explicitement déclarée lisible
 * hors ligne. `Cache-Control: private` (posé par Laravel sur toutes les pages) n'empêche pas ce rangement,
 * propre à ce navigateur ; `no-store` l'empêche.
 */
export function isStorablePage(response) {
    return (
        response.status === 200 &&
        response.type === 'basic' &&
        !response.redirected &&
        response.headers.get(OFFLINE_HEADER) === OFFLINE_HEADER_VALUE &&
        !forbidsStorage(response)
    );
}

/** Tuile : réponse complète de notre origine, non redirigée, sans `no-store`. */
export function isStorableTile(response) {
    return (
        response.status === 200 &&
        response.type === 'basic' &&
        !response.redirected &&
        !forbidsStorage(response)
    );
}

/** Données de la carte : comme une tuile, et seulement du JSON (jamais une page d'erreur HTML). */
export function isStorableMapData(response) {
    return (
        isStorableTile(response) &&
        /\b(?:geo\+)?json\b/i.test(response.headers.get('Content-Type') ?? '')
    );
}

/**
 * Clé de la copie d'une page : l'adresse sans ancre, marquée du type de réponse. Une même adresse a deux
 * copies distinctes, le document HTML et la réponse JSON d'Inertia.
 *
 * @param {string} requestUrl
 * @param {'html' | 'inertia'} kind
 */
export function pageCacheKey(requestUrl, kind) {
    const url = new URL(requestUrl);
    return `${url.origin}${url.pathname}${url.search}${url.search ? '&' : '?'}__vff-copy=${kind}`;
}

/** Vrai si la copie est trop ancienne (ou sans date lisible). */
export function isExpired(savedAt, now, maxAge) {
    const time = Date.parse(savedAt ?? '');
    return Number.isNaN(time) || now - time > maxAge;
}

/**
 * Reste-t-il de la place pour une nouvelle copie ? Sans estimation (navigateur ancien), le plafond
 * d'entrées de chaque cache suffit.
 *
 * @param {{usage?: number, quota?: number} | undefined} estimate
 */
export function hasStorageRoom(estimate) {
    if (!estimate || !estimate.quota) return true;
    const limit = Math.min(estimate.quota * STORAGE_QUOTA_SHARE, STORAGE_BUDGET_BYTES);
    return (estimate.usage ?? 0) < limit;
}

export function staticCacheName(version) {
    return `${STATIC_CACHE_PREFIX}${version}`;
}

export function pagesCacheName(version) {
    return `${PAGES_CACHE_PREFIX}${version}`;
}

export function tilesCacheName(version) {
    return `${TILES_CACHE_PREFIX}${version}`;
}

export function mapDataCacheName(version) {
    return `${MAP_DATA_CACHE_PREFIX}${version}`;
}

/** Vrai pour tout cache créé par le service worker de VFF (versionné ou page hors ligne). */
export function isVffCache(name) {
    return name === OFFLINE_CACHE || VERSIONED_CACHE_PREFIXES.some((p) => name.startsWith(p));
}

/** Caches à supprimer à l'activation : ceux d'une autre version (jamais la page hors ligne). */
export function staleCacheNames(names, version) {
    return names.filter((name) =>
        VERSIONED_CACHE_PREFIXES.some(
            (prefix) => name.startsWith(prefix) && name !== `${prefix}${version}`,
        ),
    );
}
