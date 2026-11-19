// Règles de cache de la PWA (resources/js/pwa/rules.js) : ce qui est gardé hors ligne, ce qui ne l'est jamais.
import { describe, expect, it } from 'vitest';
import {
    classify,
    isCacheable,
    matchesPath,
    shouldHardVisitOnFailure,
    staleCacheNames,
    staticCacheName,
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
            '/api/eras/2030/tiles/6/33/22.mvt',
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
    it('ne supprime que les caches statiques d une autre version, jamais la page hors ligne', () => {
        const names = ['vff-static-aaa', 'vff-static-bbb', 'vff-offline', 'cache-d-un-autre-outil'];
        expect(staleCacheNames(names, 'bbb')).toEqual(['vff-static-aaa']);
        expect(staticCacheName('bbb')).toBe('vff-static-bbb');
    });
});
