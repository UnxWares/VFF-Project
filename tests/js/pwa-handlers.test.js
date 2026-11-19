// Stratégies du service worker (resources/js/pwa/handlers.js) avec réseau et caches simulés.
import { describe, expect, it, vi } from 'vitest';
import {
    cacheFirst,
    inertia,
    navigation,
    offlineResponse,
    trimCache,
} from '../../resources/js/pwa/handlers.js';
import { OFFLINE_CACHE, OFFLINE_KEY } from '../../resources/js/pwa/rules.js';
import { basicResponse, fakeCache, fakeCacheStorage, fakeRequest } from './support/pwaFakes.js';

const ASSET = fakeRequest('https://vff-project.org/build/assets/app-1.js');

function deps(overrides = {}) {
    const pending = [];
    return {
        pending,
        caches: fakeCacheStorage(),
        fetch: vi.fn(async () => basicResponse('réseau')),
        cacheName: 'vff-static-v1',
        waitUntil: (promise) => pending.push(promise),
        ...overrides,
    };
}

describe('cacheFirst', () => {
    it('va chercher le fichier une fois, puis le sert depuis le cache sans toucher au réseau', async () => {
        const d = deps();
        expect(await (await cacheFirst(ASSET, d)).text()).toBe('réseau');
        await Promise.all(d.pending);

        expect(await (await cacheFirst(ASSET, d)).text()).toBe('réseau');
        expect(d.fetch).toHaveBeenCalledTimes(1);
    });

    it('ne range pas une erreur du serveur', async () => {
        const d = deps({ fetch: vi.fn(async () => basicResponse('absent', { status: 404 })) });
        expect((await cacheFirst(ASSET, d)).status).toBe(404);
        await Promise.all(d.pending);
        expect((await (await d.caches.open('vff-static-v1')).keys()).length).toBe(0);
    });

    it('laisse remonter l erreur réseau quand le fichier n est pas rangé', async () => {
        const d = deps({
            fetch: vi.fn(async () => Promise.reject(new TypeError('Failed to fetch'))),
        });
        await expect(cacheFirst(ASSET, d)).rejects.toThrow('Failed to fetch');
    });
});

describe('trimCache', () => {
    it('supprime les entrées les plus anciennes au-delà du plafond', async () => {
        const cache = fakeCache();
        for (const n of [1, 2, 3, 4]) await cache.put(`/a${n}`, new Response('x'));
        await trimCache(cache, 2);
        expect(await cache.keys()).toEqual(['/a3', '/a4']);
    });
});

describe('navigation', () => {
    const page = fakeRequest('https://vff-project.org/lines', {
        mode: 'navigate',
        destination: 'document',
    });

    it('renvoie la réponse du serveur, même une erreur 500 (ce n est pas une panne réseau)', async () => {
        const d = deps({ fetch: vi.fn(async () => basicResponse('oups', { status: 500 })) });
        expect((await navigation(page, d)).status).toBe(500);
    });

    it('préfère la précharge de navigation au réseau', async () => {
        const d = deps();
        const response = await navigation(page, {
            ...d,
            preload: Promise.resolve(basicResponse('préchargée')),
        });
        expect(await response.text()).toBe('préchargée');
        expect(d.fetch).not.toHaveBeenCalled();
    });

    it('sert la page hors ligne rangée quand le réseau manque', async () => {
        const d = deps({ fetch: vi.fn(async () => Promise.reject(new TypeError('offline'))) });
        const cache = await d.caches.open(OFFLINE_CACHE);
        await cache.put(OFFLINE_KEY, new Response('<h1>Vous êtes hors ligne</h1>'));

        const response = await navigation(page, d);
        expect(response.status).toBe(503);
        expect(response.headers.get('Content-Type')).toContain('text/html');
        expect(await response.text()).toContain('Vous êtes hors ligne');
    });
});

describe('offlineResponse', () => {
    it('donne une erreur réseau native tant qu aucune page hors ligne n est rangée', async () => {
        const response = await offlineResponse(fakeCacheStorage());
        expect(response.type).toBe('error');
    });
});

describe('inertia', () => {
    const visit = fakeRequest('https://vff-project.org/lines', {
        headers: { 'X-Inertia': 'true' },
    });
    const down = { fetch: vi.fn(async () => Promise.reject(new TypeError('offline'))) };

    it('transmet la réponse du serveur', async () => {
        const response = await inertia(visit, {
            fetch: async () => basicResponse('{"component":"Lines"}'),
        });
        expect(await response.text()).toBe('{"component":"Lines"}');
    });

    it('demande à Inertia une visite complète de la même adresse quand le réseau manque', async () => {
        const response = await inertia(visit, down);
        expect(response.status).toBe(409);
        expect(response.headers.get('X-Inertia-Location')).toBe('https://vff-project.org/lines');
    });

    it('ne bascule pas pour un rechargement partiel ni un préchargement', async () => {
        const partial = fakeRequest('https://vff-project.org/lines', {
            headers: { 'X-Inertia': 'true', 'X-Inertia-Partial-Data': 'lines' },
        });
        const prefetch = fakeRequest('https://vff-project.org/lines', {
            headers: { 'X-Inertia': 'true', Purpose: 'prefetch' },
        });
        await expect(inertia(partial, down)).rejects.toThrow('offline');
        await expect(inertia(prefetch, down)).rejects.toThrow('offline');
    });
});
