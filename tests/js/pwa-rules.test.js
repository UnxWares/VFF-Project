// Règles de cache de la PWA (resources/js/pwa/rules.js) : ce qui est gardé hors ligne, ce qui ne l'est jamais.
import { describe, expect, it } from 'vitest';
import {
    MAP_DATA_MAX_AGE_MS,
    MAX_MAP_DATA_ENTRIES,
    MAX_PAGE_ENTRIES,
    MAX_TILE_ENTRIES,
    PAGE_MAX_AGE_MS,
    STORAGE_BUDGET_BYTES,
    TILE_MAX_AGE_MS,
    classify,
    hasSecretParam,
    hasStorageRoom,
    isCacheable,
    isExpired,
    isMapDataPath,
    isOfflinePageRequest,
    isStorableMapData,
    isStorablePage,
    isStorableTile,
    isVffCache,
    mapDataCacheName,
    matchesPath,
    pageCacheKey,
    pagesCacheName,
    shouldHardVisitOnFailure,
    staleCacheNames,
    staticCacheName,
    tilesCacheName,
} from '../../resources/js/pwa/rules.js';
import { basicResponse, fakeRequest } from './support/pwaFakes.js';

const ORIGIN = 'https://vff-project.org';
const get = (path, options) => fakeRequest(`${ORIGIN}${path}`, options);

describe('classify', () => {
    it('sert « cache d abord » les fichiers versionnés de Vite et les icônes', () => {
        expect(classify(get('/build/assets/app-Bx12.js'), ORIGIN)).toBe('static');
        expect(classify(get('/build/assets/general-9fA.css'), ORIGIN)).toBe('static');
        expect(classify(get('/icons/icon-192.png'), ORIGIN)).toBe('static');
    });

    it('garde « cache d abord » le dictionnaire de l interface (adresse à empreinte)', () => {
        expect(classify(get('/i18n/fr/0123456789ab.json'), ORIGIN)).toBe('static');
    });

    it('traite une navigation HTML en « réseau d abord » avec repli hors ligne', () => {
        const page = get('/lines/paris-lyon', { mode: 'navigate', destination: 'document' });
        expect(classify(page, ORIGIN)).toBe('navigation');
    });

    it('traite une visite Inertia à part de la navigation', () => {
        const visit = get('/lines', { headers: { 'X-Inertia': 'true' } });
        expect(classify(visit, ORIGIN)).toBe('inertia');
    });

    it('ne touche jamais aux tuiles cartographiques ni à aucune autre origine', () => {
        expect(classify(fakeRequest('https://tile.openstreetmap.org/6/33/22.png'), ORIGIN)).toBe(
            'network',
        );
        expect(classify(fakeRequest('https://tile.openstreetmap.fr/hot/6/33/22.png'), ORIGIN)).toBe(
            'network',
        );
        expect(classify(fakeRequest('https://fonts.example/font.woff2'), ORIGIN)).toBe('network');
    });

    it('ne touche pas à l API, au GraphQL, à la documentation ni aux médias déposés', () => {
        for (const path of [
            '/api/lines',
            '/api/v1/lines',
            '/api/v1/eras/2030/lines.geojson',
            '/api/eras/2030/tiles/6/33/22.mvt',
            '/api/v1/eras/2030/tiles/6/33/22.png',
            '/graphql',
            '/graphiql',
            '/docs/api',
            '/storage/media/a.jpg',
            '/mcp',
            '/up',
        ]) {
            expect(classify(get(path), ORIGIN), path).toBe('network');
            expect(
                classify(get(path, { mode: 'navigate', destination: 'document' }), ORIGIN),
                path,
            ).toBe('network');
        }
    });

    it('ne touche pas aux écritures (formulaires, contributions, connexion)', () => {
        for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
            expect(classify(get('/login', { method }), ORIGIN)).toBe('network');
            expect(classify(get('/build/assets/app.js', { method }), ORIGIN)).toBe('network');
        }
    });

    it('ne touche pas aux requêtes authentifiées par en-tête ni aux reprises partielles', () => {
        expect(
            classify(
                get('/build/assets/app.js', { headers: { Authorization: 'Bearer x' } }),
                ORIGIN,
            ),
        ).toBe('network');
        expect(
            classify(get('/build/assets/video.mp4', { headers: { Range: 'bytes=0-1' } }), ORIGIN),
        ).toBe('network');
    });

    it('laisse le navigateur gérer le service worker et le manifeste eux-mêmes', () => {
        expect(classify(get('/sw.js'), ORIGIN)).toBe('network');
        expect(classify(get('/manifest.webmanifest'), ORIGIN)).toBe('network');
    });

    it('ne confond pas /api avec /apiary ni /up avec /upload', () => {
        expect(matchesPath('/api', '/api')).toBe(true);
        expect(matchesPath('/api/lines', '/api')).toBe(true);
        expect(matchesPath('/apiary', '/api')).toBe(false);
        expect(
            classify(get('/upload', { mode: 'navigate', destination: 'document' }), ORIGIN),
        ).toBe('navigation');
    });

    it('ignore une requête de sous-ressource ordinaire (image, script d une autre page)', () => {
        expect(classify(get('/lines/photo.jpg', { destination: 'image' }), ORIGIN)).toBe('network');
    });
});

describe('shouldHardVisitOnFailure', () => {
    it('autorise la bascule pour une visite demandée par l utilisateur', () => {
        expect(shouldHardVisitOnFailure(get('/lines', { headers: { 'X-Inertia': 'true' } }))).toBe(
            true,
        );
    });

    it('l interdit pour un préchargement ou un rechargement partiel', () => {
        expect(
            shouldHardVisitOnFailure(
                get('/lines', { headers: { 'X-Inertia': 'true', Purpose: 'prefetch' } }),
            ),
        ).toBe(false);
        expect(
            shouldHardVisitOnFailure(
                get('/lines', {
                    headers: { 'X-Inertia': 'true', 'X-Inertia-Partial-Data': 'lines' },
                }),
            ),
        ).toBe(false);
    });
});

describe('isCacheable', () => {
    it('accepte une réponse complète de notre origine', () => {
        expect(isCacheable(basicResponse())).toBe(true);
        expect(
            isCacheable(
                basicResponse('x', {
                    headers: { 'Cache-Control': 'public, max-age=31536000, immutable' },
                }),
            ),
        ).toBe(true);
    });

    it('refuse les erreurs, les contenus partiels et les réponses opaques ou d une autre origine', () => {
        expect(isCacheable(basicResponse('x', { status: 404 }))).toBe(false);
        expect(isCacheable(basicResponse('x', { status: 500 }))).toBe(false);
        expect(isCacheable(new Response('x'))).toBe(false); // type « default », pas « basic »
        const cors = new Response('x');
        Object.defineProperty(cors, 'type', { value: 'cors' });
        expect(isCacheable(cors)).toBe(false);
    });

    it('respecte no-store et private', () => {
        expect(isCacheable(basicResponse('x', { headers: { 'Cache-Control': 'no-store' } }))).toBe(
            false,
        );
        expect(
            isCacheable(basicResponse('x', { headers: { 'Cache-Control': 'private, max-age=0' } })),
        ).toBe(false);
    });
});

describe('caches versionnés', () => {
    it('supprime les caches d une autre version, jamais la page hors ligne ni ceux d un autre outil', () => {
        const names = [
            'vff-static-aaa',
            'vff-static-bbb',
            'vff-pages-aaa',
            'vff-pages-bbb',
            'vff-tiles-aaa',
            'vff-tiles-bbb',
            'vff-offline',
            'cache-d-un-autre-outil',
        ];
        expect(staleCacheNames(names, 'bbb')).toEqual([
            'vff-static-aaa',
            'vff-pages-aaa',
            'vff-tiles-aaa',
        ]);
        expect(staticCacheName('bbb')).toBe('vff-static-bbb');
        expect(pagesCacheName('bbb')).toBe('vff-pages-bbb');
        expect(tilesCacheName('bbb')).toBe('vff-tiles-bbb');
    });

    it('reconnaît les caches de VFF', () => {
        expect(isVffCache('vff-pages-1')).toBe(true);
        expect(isVffCache('vff-offline')).toBe(true);
        expect(isVffCache('tuiles-osm')).toBe(false);
    });
});

describe('tuiles', () => {
    it('reconnaît les tuiles vectorielles de VFF sous /api/v1, avec ou sans version des données', () => {
        for (const path of [
            '/api/v1/eras/2030/tiles/6/33/22.mvt',
            '/api/v1/eras/2030/tiles/14/8301/5636.mvt?v=42',
            '/api/v1/eras/1930/compare/2030/tiles/8/129/88.mvt',
            '/api/v1/eras/2030/signalling/tiles/9/259/176.mvt',
            '/api/v1/eras/stacked/tiles/7/64/44.mvt',
            '/api/v1/greenways/tiles/10/518/352.mvt',
            '/api/v1/level-crossings/tiles/12/2075/1409.mvt',
        ]) {
            expect(classify(get(path), ORIGIN), path).toBe('tile');
        }
    });

    it('laisse au navigateur les tuiles des autres serveurs (OpenStreetMap, OpenRailwayMap)', () => {
        for (const url of [
            'https://tile.openstreetmap.org/6/33/22.png',
            'https://a.tiles.openrailwaymap.org/standard/6/33/22.png',
            'https://autre.example/api/v1/eras/2030/tiles/6/33/22.mvt',
        ]) {
            expect(classify(fakeRequest(url), ORIGIN), url).toBe('network');
        }
    });

    it('ne range qu une tuile complète, sans no-store ni redirection', () => {
        expect(isStorableTile(basicResponse())).toBe(true);
        expect(isStorableTile(basicResponse(null, { status: 204 }))).toBe(false);
        expect(isStorableTile(basicResponse('x', { status: 404 }))).toBe(false);
        expect(isStorableTile(basicResponse('x', { redirected: true }))).toBe(false);
        expect(
            isStorableTile(basicResponse('x', { headers: { 'Cache-Control': 'no-store' } })),
        ).toBe(false);
    });
});

describe('jetons et secrets', () => {
    it('ne touche à aucune requête dont l adresse porte un jeton ou une signature', () => {
        expect(hasSecretParam(`${ORIGIN}/lines?token=abc`)).toBe(true);
        expect(hasSecretParam(`${ORIGIN}/lines?Signature=abc&expires=1`)).toBe(true);
        expect(hasSecretParam(`${ORIGIN}/lines?page=2&q=paris`)).toBe(false);
        for (const path of [
            '/lines?token=abc',
            '/reset-password/x?signature=y',
            '/api/v1/eras/2030/tiles/6/33/22.mvt?api_token=abc',
            '/build/assets/app.js?access_token=abc',
        ]) {
            expect(
                classify(get(path, { mode: 'navigate', destination: 'document' }), ORIGIN),
                path,
            ).toBe('network');
        }
    });
});

describe('pages gardées hors ligne', () => {
    const allowed = { 'X-VFF-Offline': 'allow', 'Cache-Control': 'no-cache, private' };

    it('accepte une page complète hors des chemins privés', () => {
        for (const path of ['/', '/lines', '/lines/paris-lyon', '/stations?page=2', '/privacy']) {
            expect(isOfflinePageRequest(get(path)), path).toBe(true);
            expect(
                isOfflinePageRequest(get(path, { headers: { 'X-Inertia': 'true' } })),
                path,
            ).toBe(true);
        }
    });

    it('refuse les pages de compte, de contribution, de validation et d administration', () => {
        for (const path of [
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password/abc',
            '/profile',
            '/profile/data',
            '/notifications',
            '/contribute',
            '/contribute/mine',
            '/contribute/trace',
            '/validate',
            '/validate/reports/3',
            '/admin',
            '/reports/token',
            '/locale/en',
        ]) {
            expect(isOfflinePageRequest(get(path)), path).toBe(false);
        }
    });

    it('refuse les préchargements et les rechargements partiels', () => {
        for (const headers of [
            { Purpose: 'prefetch' },
            { 'X-Inertia-Partial-Data': 'lines' },
            { 'X-Inertia-Partial-Component': 'Lines/Index' },
            { 'X-Inertia-Partial-Except': 'lines' },
        ]) {
            expect(isOfflinePageRequest(get('/lines', { headers })), JSON.stringify(headers)).toBe(
                false,
            );
        }
    });

    it('ne range une page que si le serveur l a déclarée lisible hors ligne', () => {
        expect(isStorablePage(basicResponse('x', { headers: allowed }))).toBe(true);
        expect(isStorablePage(basicResponse('x'))).toBe(false);
        expect(isStorablePage(basicResponse('x', { headers: { 'X-VFF-Offline': 'non' } }))).toBe(
            false,
        );
    });

    it('refuse une page en erreur, redirigée, opaque ou marquée no-store', () => {
        expect(isStorablePage(basicResponse('x', { status: 404, headers: allowed }))).toBe(false);
        expect(isStorablePage(basicResponse('x', { headers: allowed, redirected: true }))).toBe(
            false,
        );
        expect(isStorablePage(new Response('x', { headers: allowed }))).toBe(false);
        expect(
            isStorablePage(
                basicResponse('x', { headers: { ...allowed, 'Cache-Control': 'no-store' } }),
            ),
        ).toBe(false);
    });

    it('distingue la copie HTML et la copie Inertia d une même adresse, sans l ancre', () => {
        expect(pageCacheKey(`${ORIGIN}/lines#carte`, 'html')).toBe(
            `${ORIGIN}/lines?__vff-copy=html`,
        );
        expect(pageCacheKey(`${ORIGIN}/lines?page=2`, 'inertia')).toBe(
            `${ORIGIN}/lines?page=2&__vff-copy=inertia`,
        );
    });
});

describe('plafonds, durées de vie et quota', () => {
    const now = Date.parse('2026-11-19T12:00:00Z');

    it('fixe des plafonds raisonnables', () => {
        expect(MAX_PAGE_ENTRIES).toBeLessThanOrEqual(100);
        expect(MAX_TILE_ENTRIES).toBeLessThanOrEqual(1000);
        expect(PAGE_MAX_AGE_MS).toBe(30 * 24 * 3600 * 1000);
        expect(TILE_MAX_AGE_MS).toBe(7 * 24 * 3600 * 1000);
    });

    it('périme une copie trop ancienne ou sans date', () => {
        expect(isExpired('2026-11-18T12:00:00Z', now, TILE_MAX_AGE_MS)).toBe(false);
        expect(isExpired('2026-11-01T12:00:00Z', now, TILE_MAX_AGE_MS)).toBe(true);
        expect(isExpired(null, now, TILE_MAX_AGE_MS)).toBe(true);
        expect(isExpired('pas une date', now, TILE_MAX_AGE_MS)).toBe(true);
    });

    it('cesse de ranger au-delà de la moitié du quota ou du budget de 100 Mo', () => {
        expect(hasStorageRoom(undefined)).toBe(true);
        expect(hasStorageRoom({ usage: 10, quota: 0 })).toBe(true);
        expect(hasStorageRoom({ usage: 10e6, quota: 1e9 })).toBe(true);
        expect(hasStorageRoom({ usage: 30e6, quota: 50e6 })).toBe(false);
        expect(hasStorageRoom({ usage: STORAGE_BUDGET_BYTES, quota: 1e12 })).toBe(false);
    });
});

describe('données de la carte', () => {
    it('reconnaît les gares d une ère et les points des accidents, avec ou sans version des données', () => {
        for (const path of [
            '/api/v1/eras/2030/stations.geojson',
            '/api/v1/eras/1930/stations.geojson?v=42',
            '/api/v1/accidents.geojson',
        ]) {
            expect(classify(get(path), ORIGIN), path).toBe('mapdata');
        }
    });

    it('laisse passer le reste de l API, dont les téléchargements de tracés', () => {
        for (const path of [
            '/api/v1/eras/2030/lines.geojson',
            '/api/v1/lines/paris-lyon/geojson',
            '/api/v1/eras/2030/gtfs.zip',
            '/api/v1/eras/2030/compare/1930',
            '/api/v1/accidents',
            '/api/v1/eras/20300/stations.geojson',
            '/api/v1/eras/2030/stations.geojson/x',
        ]) {
            expect(isMapDataPath(new URL(`${ORIGIN}${path}`).pathname), path).toBe(false);
            expect(classify(get(path), ORIGIN), path).toBe('network');
        }
    });

    it('ne touche jamais aux données d un autre serveur ni à une requête avec jeton', () => {
        for (const request of [
            fakeRequest('https://autre.example/api/v1/eras/2030/stations.geojson'),
            get('/api/v1/eras/2030/stations.geojson?api_token=abc'),
            get('/api/v1/accidents.geojson?token=abc'),
            get('/api/v1/accidents.geojson', { headers: { Authorization: 'Bearer abc' } }),
            get('/api/v1/accidents.geojson', { method: 'POST' }),
        ]) {
            expect(classify(request, ORIGIN), request.url).toBe('network');
        }
    });

    it('ne range que du JSON complet, sans no-store ni redirection', () => {
        const json = { 'Content-Type': 'application/json' };
        expect(isStorableMapData(basicResponse('{}', { headers: json }))).toBe(true);
        expect(
            isStorableMapData(
                basicResponse('{}', { headers: { 'Content-Type': 'application/geo+json' } }),
            ),
        ).toBe(true);
        expect(
            isStorableMapData(
                basicResponse('<html>', { headers: { 'Content-Type': 'text/html' } }),
            ),
        ).toBe(false);
        expect(isStorableMapData(basicResponse('{}', { status: 500, headers: json }))).toBe(false);
        expect(isStorableMapData(basicResponse('{}', { redirected: true, headers: json }))).toBe(
            false,
        );
        expect(
            isStorableMapData(
                basicResponse('{}', {
                    headers: { ...json, 'Cache-Control': 'no-store' },
                }),
            ),
        ).toBe(false);
    });

    it('a son cache versionné, supprimé avec les autres à l activation d une nouvelle version', () => {
        expect(mapDataCacheName('bbb')).toBe('vff-mapdata-bbb');
        expect(isVffCache('vff-mapdata-aaa')).toBe(true);
        expect(staleCacheNames(['vff-mapdata-aaa', 'vff-mapdata-bbb'], 'bbb')).toEqual([
            'vff-mapdata-aaa',
        ]);
    });

    it('garde peu de fichiers (quelques Mo chacun), sept jours au plus', () => {
        expect(MAX_MAP_DATA_ENTRIES).toBeLessThanOrEqual(10);
        expect(MAP_DATA_MAX_AGE_MS).toBe(7 * 24 * 3600_000);
    });
});
