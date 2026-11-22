// PWA, fin du mode hors ligne : images des fiches déjà vues et fond de carte libre (archive PMTiles lue
// par plages d'octets). Règles (rules.js) et stratégies (handlers.js), avec réseau et caches simulés.
import { describe, expect, it, vi } from 'vitest';
import { basemap, image, trimCacheBytes } from '../../resources/js/pwa/handlers.js';
import {
    BASEMAP_CACHE,
    IMAGES_CACHE,
    MAX_IMAGE_ENTRY_BYTES,
    STORAGE_BUDGET_BYTES,
    allowsImageCopy,
    basemapCacheKey,
    classify,
    isOfflineImageUrl,
    isStorableBasemapRange,
    isStorableImage,
    isVffCache,
    parseByteRange,
    staleCacheNames,
} from '../../resources/js/pwa/rules.js';
import { basicResponse, fakeCache, fakeCacheStorage, fakeRequest } from './support/pwaFakes.js';

const ORIGIN = 'https://vff-project.org';
const NOW = Date.parse('2026-11-21T08:00:00Z');
const DAY = 24 * 3600_000;
const COMMONS = 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Gare.jpg/320px-Gare.jpg';
const MEDIA = `${ORIGIN}/storage/media/argentanwebferro/couterne.jpg`;
const ARCHIVE = `${ORIGIN}/basemap/france-20261120.pmtiles`;

const img = (url, options = {}) => fakeRequest(url, { destination: 'image', ...options });
const range = (start, end, url = ARCHIVE) =>
    fakeRequest(url, { headers: { Range: `bytes=${start}-${end}` } });
const offline = () => vi.fn(async () => Promise.reject(new TypeError('offline')));
const jpeg = (body = 'jpeg', options = {}) =>
    basicResponse(body, { headers: { 'Content-Type': 'image/jpeg' }, ...options });

function deps(overrides = {}) {
    const pending = [];
    return {
        pending,
        caches: fakeCacheStorage(),
        fetch: vi.fn(async () => jpeg()),
        cacheName: 'vff-static-v1',
        imagesCache: IMAGES_CACHE,
        basemapCache: BASEMAP_CACHE,
        origin: ORIGIN,
        clientUrl: async () => `${ORIGIN}/lines/paris-lyon`,
        waitUntil: (promise) => pending.push(promise),
        now: () => NOW,
        estimate: async () => ({ usage: 1e6, quota: 1e9 }),
        sizeCheckEvery: 1,
        ...overrides,
    };
}

async function storedKeys(d, name) {
    return (await d.caches.open(name)).keys();
}

describe('règles : images des fiches', () => {
    it('garde les vignettes de Commons et les médias publiés par VFF', () => {
        expect(classify(img(COMMONS), ORIGIN)).toBe('image');
        expect(
            classify(
                img('https://thumb.wikimedia.org/wikipedia/commons/thumb/a/a0/G.jpg/500px-G.jpg'),
                ORIGIN,
            ),
        ).toBe('image');
        expect(classify(img(MEDIA), ORIGIN)).toBe('image');
        expect(classify(img(`${ORIGIN}/storage/media/contributions/12/a.webp`), ORIGIN)).toBe(
            'image',
        );
    });

    it('ne garde ni Gallica, ni les tuiles d un autre serveur, ni les avatars, ni les fichiers privés', () => {
        for (const url of [
            'https://gallica.bnf.fr/ark:/12148/btv1b53/f1.highres',
            'https://tile.openstreetmap.org/6/33/22.png',
            'https://data.geopf.fr/wmts?layer=x',
            'https://commons.wikimedia.org/wiki/File:Gare.jpg',
            `${ORIGIN}/storage/avatars/7.webp`,
            `${ORIGIN}/storage/private/a.jpg`,
        ]) {
            expect(classify(img(url), ORIGIN), url).toBe('network');
        }
    });

    it('ne prend que les requêtes d image, sans plage, sans jeton', () => {
        expect(classify(fakeRequest(MEDIA), ORIGIN)).toBe('network');
        expect(classify(img(MEDIA, { headers: { Range: 'bytes=0-9' } }), ORIGIN)).toBe('network');
        expect(classify(img(`${MEDIA}?token=abc`), ORIGIN)).toBe('network');
        expect(classify(img(COMMONS, { headers: { Authorization: 'Bearer x' } }), ORIGIN)).toBe(
            'network',
        );
        expect(classify(img(COMMONS, { method: 'POST' }), ORIGIN)).toBe('network');
    });

    it('reconnaît les adresses d images gardables', () => {
        expect(isOfflineImageUrl(COMMONS, ORIGIN)).toBe(true);
        expect(isOfflineImageUrl(`${ORIGIN}/storage/mediatheque/a.jpg`, ORIGIN)).toBe(false);
        expect(isOfflineImageUrl('http://upload.wikimedia.org/a.jpg', ORIGIN)).toBe(false);
        // Vignette servie par le relais de VFF (VFF_COMMONS_PROXY=true).
        expect(isOfflineImageUrl(`${ORIGIN}/media/commons/7/79/CFA-Morlaix.jpg`, ORIGIN)).toBe(
            true,
        );
        expect(isOfflineImageUrl(`${ORIGIN}/media/autre/a.jpg`, ORIGIN)).toBe(false);
    });

    it('n autorise une copie que pour une page publique du site', () => {
        expect(allowsImageCopy(`${ORIGIN}/lines/paris-lyon`, ORIGIN)).toBe(true);
        expect(allowsImageCopy(`${ORIGIN}/materials/tgv-sud-est`, ORIGIN)).toBe(true);
        for (const path of [
            '/profile',
            '/contribute/media',
            '/validate',
            '/validate/media/3',
            '/admin/users',
            '/notifications',
            '/reports',
        ]) {
            expect(allowsImageCopy(`${ORIGIN}${path}`, ORIGIN), path).toBe(false);
        }
        expect(allowsImageCopy(undefined, ORIGIN)).toBe(false);
        expect(allowsImageCopy('https://ailleurs.example/lines', ORIGIN)).toBe(false);
    });

    it('ne range qu une vraie image complète, de notre origine ou en CORS', () => {
        expect(isStorableImage(jpeg())).toBe(true);
        expect(isStorableImage(jpeg('x', { type: 'cors' }))).toBe(true);
        expect(isStorableImage(jpeg('x', { type: 'opaque' }))).toBe(false);
        expect(isStorableImage(jpeg('x', { status: 404 }))).toBe(false);
        expect(isStorableImage(jpeg('x', { redirected: true }))).toBe(false);
        expect(
            isStorableImage(basicResponse('<html>', { headers: { 'Content-Type': 'text/html' } })),
        ).toBe(false);
        expect(
            isStorableImage(
                basicResponse('x', {
                    headers: { 'Content-Type': 'image/png', 'Cache-Control': 'no-store' },
                }),
            ),
        ).toBe(false);
    });
});

describe('règles : fond de carte libre (PMTiles)', () => {
    it('ne prend en charge que les plages explicites de l archive', () => {
        expect(classify(range(0, 16383), ORIGIN)).toBe('basemap');
        expect(classify(fakeRequest(ARCHIVE), ORIGIN)).toBe('network');
        expect(classify(fakeRequest(ARCHIVE, { headers: { Range: 'bytes=100-' } }), ORIGIN)).toBe(
            'network',
        );
        expect(classify(range(0, 9, `${ORIGIN}/basemap/../.env`), ORIGIN)).toBe('network');
        expect(classify(range(0, 9, `${ORIGIN}/storage/a.pmtiles`), ORIGIN)).toBe('network');
        expect(classify(range(0, 9, 'https://build.protomaps.com/20261120.pmtiles'), ORIGIN)).toBe(
            'network',
        );
    });

    it('sert ses polices « cache d abord »', () => {
        const glyphs = fakeRequest(`${ORIGIN}/basemap/fonts/Noto%20Sans%20Regular/0-255.pbf`);
        expect(classify(glyphs, ORIGIN)).toBe('static');
    });

    it('lit une plage d octets simple, et rien d autre', () => {
        expect(parseByteRange('bytes=0-16383')).toEqual({ start: 0, end: 16383 });
        expect(parseByteRange(' bytes=10-10 ')).toEqual({ start: 10, end: 10 });
        for (const header of [
            null,
            '',
            'bytes=10-',
            'bytes=-500',
            'bytes=0-1,5-9',
            'bytes=9-1',
            'items=0-1',
        ]) {
            expect(parseByteRange(header), String(header)).toBeNull();
        }
    });

    it('donne une clé par archive et par plage', () => {
        expect(basemapCacheKey(`${ARCHIVE}?x=1`, { start: 0, end: 99 })).toBe(
            `${ARCHIVE}?__vff-range=0-99`,
        );
    });

    it('ne range qu une réponse partielle de notre origine', () => {
        const partial = (options = {}) =>
            basicResponse('ab', {
                status: 206,
                headers: { 'Content-Range': 'bytes 0-1/6245910646' },
                ...options,
            });
        expect(isStorableBasemapRange(partial())).toBe(true);
        expect(isStorableBasemapRange(partial({ status: 200 }))).toBe(false);
        expect(isStorableBasemapRange(partial({ headers: {} }))).toBe(false);
        expect(isStorableBasemapRange(partial({ type: 'cors' }))).toBe(false);
    });

    it('garde les caches d images et du fond d une version à l autre', () => {
        expect(isVffCache(IMAGES_CACHE)).toBe(true);
        expect(isVffCache(BASEMAP_CACHE)).toBe(true);
        expect(staleCacheNames([IMAGES_CACHE, BASEMAP_CACHE, 'vff-tiles-old'], 'new')).toEqual([
            'vff-tiles-old',
        ]);
    });

    it('relève le budget de stockage du site à 200 Mo', () => {
        expect(STORAGE_BUDGET_BYTES).toBe(200 * 1024 * 1024);
    });
});

describe('image : copies des images déjà vues', () => {
    it('demande une vignette de Commons en CORS, la rend et la garde, puis la sert sans réseau', async () => {
        const d = deps({ fetch: vi.fn(async () => jpeg('vignette', { type: 'cors' })) });
        const response = await image(img(COMMONS, { mode: 'no-cors' }), d);
        expect(await response.text()).toBe('vignette');
        const sent = d.fetch.mock.calls[0][0];
        expect(sent.mode).toBe('cors');
        expect(sent.credentials).toBe('omit');
        await Promise.all(d.pending);

        const copy = await image(img(COMMONS, { mode: 'no-cors' }), { ...d, fetch: offline() });
        expect(await copy.text()).toBe('vignette');
        expect(copy.headers.get('X-VFF-Saved-At')).toBe(new Date(NOW).toISOString());
        expect(copy.headers.get('X-VFF-Size')).toBe('8');
    });

    it('garde un média publié par VFF tel quel (même origine)', async () => {
        const d = deps();
        await image(img(MEDIA, { mode: 'no-cors' }), d);
        expect(d.fetch.mock.calls[0][0].url).toBe(MEDIA);
        await Promise.all(d.pending);
        expect(await storedKeys(d, IMAGES_CACHE)).toEqual([MEDIA]);
    });

    it('ne garde rien et ne sert aucune copie pour une page de compte, de contribution ou de validation', async () => {
        for (const path of ['/profile', '/contribute/media', '/validate']) {
            const d = deps({ clientUrl: async () => `${ORIGIN}${path}` });
            await (
                await d.caches.open(IMAGES_CACHE)
            ).put(
                COMMONS,
                new Response('ancienne', {
                    headers: { 'X-VFF-Saved-At': new Date(NOW).toISOString() },
                }),
            );
            await image(img(COMMONS), d);
            await Promise.all(d.pending);
            expect((await d.caches.open(IMAGES_CACHE)).entries.size, path).toBe(1);
            await expect(image(img(COMMONS), { ...d, fetch: offline() })).rejects.toThrow(
                'offline',
            );
        }
    });

    it('ne garde rien quand la page qui demande l image est inconnue', async () => {
        const d = deps({ clientUrl: async () => undefined });
        await image(img(MEDIA), d);
        await Promise.all(d.pending);
        expect(await storedKeys(d, IMAGES_CACHE)).toEqual([]);
    });

    it('ne garde ni une image trop lourde, ni une réponse opaque, ni rien quand le quota est atteint', async () => {
        const heavy = deps({
            fetch: vi.fn(async () => jpeg('x'.repeat(MAX_IMAGE_ENTRY_BYTES + 1))),
        });
        await image(img(MEDIA), heavy);
        await Promise.all(heavy.pending);
        expect(await storedKeys(heavy, IMAGES_CACHE)).toEqual([]);

        const opaque = deps({ fetch: vi.fn(async () => jpeg('', { type: 'opaque' })) });
        await image(img(COMMONS), opaque);
        await Promise.all(opaque.pending);
        expect(await storedKeys(opaque, IMAGES_CACHE)).toEqual([]);

        const full = deps({ estimate: async () => ({ usage: STORAGE_BUDGET_BYTES, quota: 1e12 }) });
        await image(img(MEDIA), full);
        await Promise.all(full.pending);
        expect(await storedKeys(full, IMAGES_CACHE)).toEqual([]);
    });

    it('n utilise plus une copie de plus de 30 jours', async () => {
        const d = deps({ fetch: offline(), now: () => NOW + 31 * DAY });
        await (
            await d.caches.open(IMAGES_CACHE)
        ).put(
            MEDIA,
            new Response('vieille', { headers: { 'X-VFF-Saved-At': new Date(NOW).toISOString() } }),
        );
        await expect(image(img(MEDIA), d)).rejects.toThrow('offline');
    });

    it('si Commons refusait le CORS, rend l image comme sans service worker', async () => {
        const fetch = vi
            .fn()
            .mockRejectedValueOnce(new TypeError('CORS'))
            .mockResolvedValueOnce(jpeg('', { type: 'opaque' }));
        const d = deps({ fetch });
        const response = await image(img(COMMONS, { mode: 'no-cors' }), d);
        expect(response.type).toBe('opaque');
        expect(fetch.mock.calls[1][0].mode).toBe('no-cors');
    });

    it('tient le plafond en octets : les plus anciennes partent d abord', async () => {
        const d = deps();
        for (const n of [1, 2, 3]) {
            await image(img(`${ORIGIN}/storage/media/${n}.jpg`), {
                ...d,
                fetch: vi.fn(async () => jpeg('x'.repeat(10))),
            });
            await Promise.all(d.pending);
        }
        const cache = await d.caches.open(IMAGES_CACHE);
        await trimCacheBytes(cache, 25);
        expect(await cache.keys()).toEqual([
            `${ORIGIN}/storage/media/2.jpg`,
            `${ORIGIN}/storage/media/3.jpg`,
        ]);
    });
});

describe('trimCacheBytes', () => {
    it('supprime aussi une copie sans taille lisible', async () => {
        const cache = fakeCache();
        await cache.put('a', new Response('a'));
        await cache.put('b', new Response('b', { headers: { 'X-VFF-Size': '1' } }));
        await trimCacheBytes(cache, 100);
        expect(await cache.keys()).toEqual(['b']);
    });
});

describe('basemap : morceaux du fond de carte déjà vus', () => {
    const partial = (body, start, end) =>
        basicResponse(body, {
            status: 206,
            headers: {
                'Content-Range': `bytes ${start}-${end}/6245910646`,
                'Content-Type': 'application/octet-stream',
                ETag: '"france-20261120"',
            },
        });

    it('rend la plage du réseau, la garde, puis la rend en 206 sans réseau', async () => {
        const d = deps({ fetch: vi.fn(async () => partial('tuile', 100, 104)) });
        const online = await basemap(range(100, 104), d);
        expect(online.status).toBe(206);
        await Promise.all(d.pending);

        const key = `${ARCHIVE}?__vff-range=100-104`;
        expect(await storedKeys(d, BASEMAP_CACHE)).toEqual([key]);
        expect((await (await d.caches.open(BASEMAP_CACHE)).match(key)).status).toBe(200);

        const copy = await basemap(range(100, 104), { ...d, fetch: offline() });
        expect(copy.status).toBe(206);
        expect(await copy.text()).toBe('tuile');
        expect(copy.headers.get('Content-Range')).toBe('bytes 100-104/6245910646');
        expect(copy.headers.get('ETag')).toBe('"france-20261120"');
    });

    it('sans réseau, une plage jamais lue reste une erreur (la carte garde son fond uni)', async () => {
        const d = deps({ fetch: vi.fn(async () => partial('tuile', 100, 104)) });
        await basemap(range(100, 104), d);
        await Promise.all(d.pending);
        await expect(basemap(range(105, 200), { ...d, fetch: offline() })).rejects.toThrow(
            'offline',
        );
    });

    it('ne garde pas une réponse complète (200) ni une erreur', async () => {
        for (const response of [basicResponse('tout'), basicResponse('', { status: 416 })]) {
            const d = deps({ fetch: vi.fn(async () => response) });
            await basemap(range(0, 9), d);
            await Promise.all(d.pending);
            expect(await storedKeys(d, BASEMAP_CACHE)).toEqual([]);
        }
    });

    it('n utilise plus une plage de plus de 30 jours', async () => {
        const d = deps({ fetch: vi.fn(async () => partial('tuile', 0, 4)) });
        await basemap(range(0, 4), d);
        await Promise.all(d.pending);
        await expect(
            basemap(range(0, 4), { ...d, fetch: offline(), now: () => NOW + 31 * DAY }),
        ).rejects.toThrow('offline');
    });

    it('sans cache configuré, se contente du réseau', async () => {
        const d = deps({ basemapCache: undefined, fetch: offline() });
        await expect(basemap(range(0, 4), d)).rejects.toThrow('offline');
    });
});

describe('vite-plugin-sw : dossier du service worker', () => {
    it('range sw.js à côté du dossier de build, pour le dépôt comme pour un build d essai', async () => {
        const { serviceWorkerOutDir } = await import('../../resources/js/pwa/vite-plugin-sw.js');
        expect(serviceWorkerOutDir('/app', 'public/build')).toBe('/app/public');
        expect(serviceWorkerOutDir('/app', '/tmp/essai/public/build')).toBe('/tmp/essai/public');
    });
});
