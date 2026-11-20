// Enregistrement, mises à jour et désinscription du service worker (resources/js/pwa/register.js).
import { describe, expect, it, vi } from 'vitest';
import {
    shouldCheckForUpdate,
    startServiceWorker,
    unregisterServiceWorker,
    watchForUpdates,
} from '../../resources/js/pwa/register.js';
import { fakeCacheStorage } from './support/pwaFakes.js';

/** Petit émetteur d'événements : sert de ServiceWorkerContainer, de registration et de worker. */
function emitter(extra = {}) {
    const listeners = {};
    return Object.assign(
        {
            addEventListener: (type, fn) => (listeners[type] ??= []).push(fn),
            emit: (type) => (listeners[type] ?? []).forEach((fn) => fn()),
        },
        extra,
    );
}

function fakeServiceWorker({ controller = null, registrations = [] } = {}) {
    const registration = emitter({ waiting: null, installing: null, postMessage: undefined });
    return emitter({
        controller,
        registration,
        register: vi.fn(async () => registration),
        getRegistrations: async () => registrations,
    });
}

describe('startServiceWorker', () => {
    const base = {
        cacheStorage: undefined,
        dev: false,
        enabled: true,
        secure: true,
        handlers: { onUpdateReady: vi.fn(), reload: vi.fn() },
    };

    it('enregistre /sw.js à la racine, sans passer par le cache HTTP', async () => {
        const serviceWorker = fakeServiceWorker();
        const registration = await startServiceWorker({ ...base, serviceWorker });
        expect(serviceWorker.register).toHaveBeenCalledWith('/sw.js', {
            scope: '/',
            updateViaCache: 'none',
        });
        expect(registration).toBe(serviceWorker.registration);
    });

    it('ne fait rien sans service worker (navigateur ancien)', async () => {
        expect(await startServiceWorker({ ...base, serviceWorker: undefined })).toBeNull();
    });

    it('ne s enregistre pas hors contexte sécurisé', async () => {
        const serviceWorker = fakeServiceWorker();
        expect(await startServiceWorker({ ...base, serviceWorker, secure: false })).toBeNull();
        expect(serviceWorker.register).not.toHaveBeenCalled();
    });

    it.each([
        ['en développement (HMR)', { dev: true }],
        ['quand la PWA est coupée (VITE_PWA=false)', { enabled: false }],
    ])('ne s enregistre pas %s et désinscrit ce qui traîne', async (_label, flags) => {
        const registration = { unregister: vi.fn(async () => true) };
        const serviceWorker = fakeServiceWorker({ registrations: [registration] });
        const cacheStorage = fakeCacheStorage(['vff-static-abc', 'vff-offline', 'autre']);

        expect(
            await startServiceWorker({ ...base, ...flags, serviceWorker, cacheStorage }),
        ).toBeNull();
        expect(serviceWorker.register).not.toHaveBeenCalled();
        expect(registration.unregister).toHaveBeenCalledOnce();
        expect(await cacheStorage.keys()).toEqual(['autre']);
    });
});

describe('unregisterServiceWorker', () => {
    it('désinscrit toutes les inscriptions et ne vide que les caches de VFF', async () => {
        const a = { unregister: vi.fn(async () => true) };
        const b = { unregister: vi.fn(async () => true) };
        const cacheStorage = fakeCacheStorage([
            'vff-static-1',
            'vff-static-2',
            'vff-pages-2',
            'vff-tiles-2',
            'vff-offline',
            'tuiles-osm',
        ]);
        await unregisterServiceWorker(fakeServiceWorker({ registrations: [a, b] }), cacheStorage);
        expect(a.unregister).toHaveBeenCalled();
        expect(b.unregister).toHaveBeenCalled();
        expect(await cacheStorage.keys()).toEqual(['tuiles-osm']);
    });

    it('fonctionne sans Cache API', async () => {
        await expect(
            unregisterServiceWorker(fakeServiceWorker(), undefined),
        ).resolves.toBeUndefined();
    });
});

describe('watchForUpdates', () => {
    function setup(controller) {
        const serviceWorker = fakeServiceWorker({ controller });
        const handlers = { onUpdateReady: vi.fn(), reload: vi.fn() };
        const { registration } = serviceWorker;
        watchForUpdates(registration, serviceWorker, handlers);
        return { serviceWorker, registration, handlers };
    }

    function install(registration) {
        const worker = emitter({ state: 'installing' });
        registration.installing = worker;
        registration.emit('updatefound');
        worker.state = 'installed';
        worker.emit('statechange');
        return worker;
    }

    it('annonce une nouvelle version quand une page déjà contrôlée voit un worker installé', () => {
        const { registration, handlers } = setup({});
        install(registration);
        expect(handlers.onUpdateReady).toHaveBeenCalledOnce();
    });

    it('n annonce rien pour le tout premier service worker (page pas encore contrôlée)', () => {
        const { registration, handlers } = setup(null);
        install(registration);
        expect(handlers.onUpdateReady).not.toHaveBeenCalled();
    });

    it('annonce un worker déjà en attente au chargement, une seule fois', () => {
        const serviceWorker = fakeServiceWorker({ controller: {} });
        const waiting = emitter({ postMessage: vi.fn() });
        serviceWorker.registration.waiting = waiting;
        const handlers = { onUpdateReady: vi.fn(), reload: vi.fn() };
        watchForUpdates(serviceWorker.registration, serviceWorker, handlers);
        expect(handlers.onUpdateReady).toHaveBeenCalledOnce();
    });

    it('demande l activation puis recharge, seulement quand l utilisateur a accepté', () => {
        const { serviceWorker, registration, handlers } = setup({});
        const worker = install(registration);
        worker.postMessage = vi.fn();
        registration.waiting = worker;

        // Changement de contrôleur sans accord de l'utilisateur : pas de rechargement.
        serviceWorker.emit('controllerchange');
        expect(handlers.reload).not.toHaveBeenCalled();

        const apply = handlers.onUpdateReady.mock.calls[0][0];
        apply();
        expect(worker.postMessage).toHaveBeenCalledWith({ type: 'SKIP_WAITING' });
        serviceWorker.emit('controllerchange');
        expect(handlers.reload).toHaveBeenCalledOnce();
    });

    it('ne recharge pas à la prise de contrôle du premier service worker', () => {
        const { serviceWorker, handlers } = setup(null);
        serviceWorker.emit('controllerchange');
        expect(handlers.reload).not.toHaveBeenCalled();
    });
});

describe('shouldCheckForUpdate', () => {
    it('cherche une nouvelle version au plus une fois par heure', () => {
        expect(shouldCheckForUpdate(0, 30 * 60 * 1000)).toBe(false);
        expect(shouldCheckForUpdate(0, 60 * 60 * 1000)).toBe(true);
    });
});
