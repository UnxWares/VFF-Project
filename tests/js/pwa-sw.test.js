// Câblage du service worker (resources/js/pwa/sw.js) : événements écoutés, pas de skipWaiting automatique,
// nettoyage des anciens caches, et aucune prise en charge des requêtes hors règles.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { basicResponse, fakeCacheStorage, fakeRequest } from './support/pwaFakes.js';

async function loadWorker(existingCaches = []) {
    vi.resetModules();
    const handlers = {};
    const self = {
        location: { origin: 'https://vff-project.org' },
        addEventListener: (type, fn) => (handlers[type] = fn),
        skipWaiting: vi.fn(),
        clients: { claim: vi.fn(async () => {}) },
        registration: { navigationPreload: { enable: vi.fn(async () => {}) } },
    };
    const cacheStorage = fakeCacheStorage(existingCaches);
    vi.stubGlobal('self', self);
    vi.stubGlobal('caches', cacheStorage);
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => basicResponse('réseau')),
    );
    await import('../../resources/js/pwa/sw.js');
    return { handlers, self, cacheStorage };
}

afterEach(() => vi.unstubAllGlobals());

describe('service worker', () => {
    it("range l'icône à l'installation, sans skipWaiting automatique", async () => {
        const { handlers, self, cacheStorage } = await loadWorker();
        let installed;
        handlers.install({ waitUntil: (p) => (installed = p) });
        await installed;
        expect(self.skipWaiting).not.toHaveBeenCalled();
        expect(await cacheStorage.keys()).toEqual(['vff-static-dev']);
    });

    it("ne s'active qu'à la demande de la page (message SKIP_WAITING)", async () => {
        const { handlers, self } = await loadWorker();
        handlers.message({ data: { type: 'AUTRE' } });
        expect(self.skipWaiting).not.toHaveBeenCalled();
        handlers.message({ data: { type: 'SKIP_WAITING' } });
        expect(self.skipWaiting).toHaveBeenCalledOnce();
    });

    it("à l'activation, supprime les caches des versions précédentes et garde la page hors ligne", async () => {
        const { handlers, self, cacheStorage } = await loadWorker([
            'vff-static-ancienne',
            'vff-static-dev',
            'vff-offline',
        ]);
        let activated;
        handlers.activate({ waitUntil: (p) => (activated = p) });
        await activated;
        expect(await cacheStorage.keys()).toEqual(['vff-static-dev', 'vff-offline']);
        expect(self.registration.navigationPreload.enable).toHaveBeenCalled();
        expect(self.clients.claim).toHaveBeenCalled();
    });

    it("prend en charge un fichier versionné mais laisse passer l'API et les tuiles", async () => {
        const { handlers } = await loadWorker();
        const respondWith = vi.fn();
        const event = (url) => ({ request: fakeRequest(url), respondWith, waitUntil: vi.fn() });

        handlers.fetch(event('https://vff-project.org/build/assets/app-1.js'));
        expect(respondWith).toHaveBeenCalledOnce();

        respondWith.mockClear();
        handlers.fetch(event('https://vff-project.org/api/lines'));
        handlers.fetch(event('https://tile.openstreetmap.org/6/33/22.png'));
        expect(respondWith).not.toHaveBeenCalled();
    });
});
