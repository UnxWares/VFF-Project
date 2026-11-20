// Stratégies du service worker (resources/js/pwa/handlers.js) avec réseau et caches simulés.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it, vi } from 'vitest';
import {
    cacheFirst,
    inertia,
    mapData,
    navigation,
    offlineResponse,
    pruneExpired,
    tile,
    trimCache,
} from '../../resources/js/pwa/handlers.js';
import {
    MAX_MAP_DATA_ENTRIES,
    MAX_PAGE_ENTRIES,
    MAX_TILE_ENTRIES,
    OFFLINE_CACHE,
    OFFLINE_KEY,
} from '../../resources/js/pwa/rules.js';
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

// ─── Copies des pages et des tuiles (mode hors ligne) ─────────────────────────────────────────────

const NOW = Date.parse('2026-11-19T12:00:00Z');
const ALLOWED = { 'X-VFF-Offline': 'allow', 'Content-Type': 'text/html; charset=utf-8' };
const offline = () => vi.fn(async () => Promise.reject(new TypeError('offline')));

function pageHtml(props) {
    const page = JSON.stringify({ component: 'Lines/Show', props, url: '/lines/a', version: '1' });
    return `<!DOCTYPE html><html lang="fr"><head><title>A</title></head><body><script data-page="app" type="application/json">${page}</script><div id="app"></div></body></html>`;
}

function offlineDeps(overrides = {}) {
    return deps({
        pagesCache: 'vff-pages-v1',
        tilesCache: 'vff-tiles-v1',
        mapDataCache: 'vff-mapdata-v1',
        now: () => NOW,
        estimate: async () => ({ usage: 1e6, quota: 1e9 }),
        ...overrides,
    });
}

async function storedKeys(d, name) {
    return (await d.caches.open(name)).keys();
}

describe('navigation : copies des pages consultées', () => {
    const doc = (path, headers = {}) =>
        fakeRequest(`https://vff-project.org${path}`, {
            mode: 'navigate',
            destination: 'document',
            headers,
        });

    it('range une page déclarée lisible hors ligne, puis la sert, datée, sans réseau', async () => {
        const online = offlineDeps({
            fetch: vi.fn(async () =>
                basicResponse(pageHtml({ title: 'Ligne A' }), { headers: ALLOWED }),
            ),
        });
        await navigation(doc('/lines/a'), online);
        await Promise.all(online.pending);
        expect(await storedKeys(online, 'vff-pages-v1')).toEqual([
            'https://vff-project.org/lines/a?__vff-copy=html',
        ]);

        const response = await navigation(doc('/lines/a'), {
            ...online,
            fetch: offline(),
            now: () => NOW + 3600_000,
        });
        expect(response.status).toBe(200);
        expect(response.headers.get('Cache-Control')).toBe('no-store');
        const html = await response.text();
        expect(html).toContain('"title":"Ligne A"');
        expect(html).toContain('"offline_copy":{"saved_at":"2026-11-19T12:00:00.000Z"}');
    });

    it('ne range pas une page sans l accord du serveur, ni une page privée', async () => {
        const d = offlineDeps({ fetch: vi.fn(async () => basicResponse(pageHtml({}))) });
        await navigation(doc('/lines/a'), d);

        const p = offlineDeps({
            fetch: vi.fn(async () => basicResponse(pageHtml({}), { headers: ALLOWED })),
        });
        await navigation(doc('/profile'), p);
        await navigation(doc('/contribute/mine'), p);
        await navigation(doc('/validate'), p);
        await Promise.all([...d.pending, ...p.pending]);

        expect(await storedKeys(d, 'vff-pages-v1')).toEqual([]);
        expect(await storedKeys(p, 'vff-pages-v1')).toEqual([]);
    });

    it('ne sert jamais la copie d une page privée, même rangée par erreur', async () => {
        const d = offlineDeps({ fetch: offline() });
        const cache = await d.caches.open('vff-pages-v1');
        await cache.put(
            'https://vff-project.org/profile?__vff-copy=html',
            new Response(pageHtml({ auth: { user: { name: 'Autre' } } }), {
                headers: { 'X-VFF-Saved-At': new Date(NOW).toISOString() },
            }),
        );
        const response = await navigation(doc('/profile'), d);
        expect(response.type).toBe('error'); // page hors ligne absente : erreur réseau native
    });

    it('sert la page hors ligne pour une page jamais consultée', async () => {
        const d = offlineDeps({ fetch: offline() });
        const cache = await d.caches.open(OFFLINE_CACHE);
        await cache.put(OFFLINE_KEY, new Response('<h1>Hors ligne</h1>'));
        const response = await navigation(doc('/lines/jamais-vue'), d);
        expect(response.status).toBe(503);
        expect(await response.text()).toContain('Hors ligne');
    });

    it('n utilise plus une copie de plus de 30 jours, et la supprime', async () => {
        const d = offlineDeps({ fetch: offline(), now: () => NOW + 31 * 24 * 3600_000 });
        const cache = await d.caches.open('vff-pages-v1');
        const key = 'https://vff-project.org/lines/a?__vff-copy=html';
        await cache.put(
            key,
            new Response(pageHtml({}), {
                headers: { 'X-VFF-Saved-At': new Date(NOW).toISOString() },
            }),
        );
        expect((await navigation(doc('/lines/a'), d)).type).toBe('error');
        expect(await cache.keys()).toEqual([]);
    });

    it('reconstruit une page connue seulement par sa réponse Inertia dans le document d une autre', async () => {
        const d = offlineDeps({ fetch: offline() });
        const cache = await d.caches.open('vff-pages-v1');
        const saved = { 'X-VFF-Saved-At': new Date(NOW).toISOString() };
        await cache.put(
            'https://vff-project.org/lines?__vff-copy=html',
            new Response(pageHtml({ title: 'Annuaire' }), { headers: saved }),
        );
        await cache.put(
            'https://vff-project.org/lines/b?__vff-copy=inertia',
            new Response(
                JSON.stringify({
                    component: 'Lines/Show',
                    props: { title: 'Ligne B' },
                    url: '/lines/b',
                }),
                { headers: saved },
            ),
        );
        const html = await (await navigation(doc('/lines/b'), d)).text();
        expect(html).toContain('"title":"Ligne B"');
        expect(html).not.toContain('Annuaire');
        expect(html).toContain('"offline_copy"');
    });

    it('ne range rien quand le site occupe déjà trop de place', async () => {
        const d = offlineDeps({
            fetch: vi.fn(async () => basicResponse(pageHtml({}), { headers: ALLOWED })),
            estimate: async () => ({ usage: 900e6, quota: 1e9 }),
        });
        await navigation(doc('/lines/a'), d);
        await Promise.all(d.pending);
        expect(await storedKeys(d, 'vff-pages-v1')).toEqual([]);
    });

    it('garde au plus MAX_PAGE_ENTRIES copies, les plus anciennes partent d abord', async () => {
        const d = offlineDeps({
            fetch: vi.fn(async () => basicResponse(pageHtml({}), { headers: ALLOWED })),
        });
        for (let n = 0; n <= MAX_PAGE_ENTRIES; n += 1) {
            await navigation(doc(`/lines/l${n}`), d);
            await Promise.all(d.pending);
        }
        const keys = await storedKeys(d, 'vff-pages-v1');
        expect(keys).toHaveLength(MAX_PAGE_ENTRIES);
        expect(keys[0]).toBe('https://vff-project.org/lines/l1?__vff-copy=html');
    });
});

describe('inertia : copies des visites', () => {
    const visit = (path, headers = {}) =>
        fakeRequest(`https://vff-project.org${path}`, {
            headers: { 'X-Inertia': 'true', ...headers },
        });
    const json = (props) =>
        basicResponse(JSON.stringify({ component: 'Lines/Show', props, url: '/lines/a' }), {
            headers: {
                'X-VFF-Offline': 'allow',
                'X-Inertia': 'true',
                'Content-Type': 'application/json',
            },
        });

    it('range la réponse Inertia et la sert, datée, sans réseau', async () => {
        const d = offlineDeps({ fetch: vi.fn(async () => json({ title: 'Ligne A' })) });
        await inertia(visit('/lines/a'), d);
        await Promise.all(d.pending);

        const response = await inertia(visit('/lines/a'), { ...d, fetch: offline() });
        expect(response.status).toBe(200);
        expect(response.headers.get('X-Inertia')).toBe('true');
        const page = await response.json();
        expect(page.props.title).toBe('Ligne A');
        expect(page.props.offline_copy).toEqual({ saved_at: '2026-11-19T12:00:00.000Z' });
    });

    it('ne range ni un préchargement ni un rechargement partiel', async () => {
        const d = offlineDeps({ fetch: vi.fn(async () => json({})) });
        await inertia(visit('/lines/a', { Purpose: 'prefetch' }), d);
        await inertia(visit('/lines/a', { 'X-Inertia-Partial-Data': 'x' }), d);
        await Promise.all(d.pending);
        expect(await storedKeys(d, 'vff-pages-v1')).toEqual([]);
    });

    it('sans copie, demande toujours une visite complète', async () => {
        const response = await inertia(visit('/lines/z'), offlineDeps({ fetch: offline() }));
        expect(response.status).toBe(409);
    });
});

// Vraies réponses du serveur de développement, réduites (voir tests/js/pwa-offline-copy.test.js).
describe('hors ligne : props « once » absentes des copies Inertia', () => {
    const fixture = (name) =>
        readFileSync(fileURLToPath(new URL(`./fixtures/offline/${name}`, import.meta.url)), 'utf8');
    const saved = { 'X-VFF-Saved-At': new Date(NOW).toISOString() };
    const doc = (path) =>
        fakeRequest(`https://vff-project.org${path}`, {
            mode: 'navigate',
            destination: 'document',
        });
    const visit = (path, once) =>
        fakeRequest(`https://vff-project.org${path}`, {
            headers: { 'X-Inertia': 'true', 'X-Inertia-Except-Once-Props': once },
        });

    /** Cache des pages : copies HTML (les plus anciennes d'abord), puis copies Inertia. */
    async function pages(d, html, inertiaCopies) {
        const cache = await d.caches.open('vff-pages-v1');
        for (const [path, name] of html) {
            await cache.put(
                `https://vff-project.org${path}?__vff-copy=html`,
                new Response(fixture(name), { headers: saved }),
            );
        }
        for (const [path, name] of inertiaCopies) {
            const url = new URL(`https://vff-project.org${path}`);
            await cache.put(
                `${url.origin}${url.pathname}${url.search}${url.search ? '&' : '?'}__vff-copy=inertia`,
                new Response(fixture(name), { headers: saved }),
            );
        }
    }

    const pageOf = (html) =>
        JSON.parse(/<script\b[^>]*data-page="app"[^>]*>([\s\S]*?)<\/script>/.exec(html)[1]);

    it('rouvre /lines depuis un favori avec ses traductions', async () => {
        const d = offlineDeps({ fetch: offline() });
        await pages(d, [['/', 'home-fr.html']], [['/lines', 'lines.inertia.json']]);
        const response = await navigation(doc('/lines'), d);
        expect(response.status).toBe(200);
        const page = pageOf(await response.text());
        expect(page.component).toBe('Lines/Index');
        expect(page.props.translations['common.app_description']).toBeTruthy();
    });

    it('préfère l enveloppe qui porte les menus des accidents, même plus ancienne', async () => {
        const d = offlineDeps({ fetch: offline() });
        await pages(
            d,
            [
                ['/accidents', 'accidents-fr.html'],
                ['/', 'home-fr.html'],
            ],
            [['/accidents?decade=1950', 'accidents-decade-1950.inertia.json']],
        );
        const page = pageOf(await (await navigation(doc('/accidents?decade=1950'), d)).text());
        expect(page.props.filters.decade).toBe(1950);
        expect(page.props.facets.decades.length).toBeGreaterThan(0);
        expect(page.props.translations['common.app_description']).toBeTruthy();
    });

    it('sert la page hors ligne plutôt qu une page sans texte (enveloppe d une autre langue)', async () => {
        const d = offlineDeps({ fetch: offline() });
        await pages(d, [['/', 'home-en.html']], [['/lines', 'lines.inertia.json']]);
        await (
            await d.caches.open(OFFLINE_CACHE)
        ).put(OFFLINE_KEY, new Response('<h1>Hors ligne</h1>'));
        const response = await navigation(doc('/lines'), d);
        expect(response.status).toBe(503);
    });

    it('complète une visite Inertia des menus qui manquent au navigateur', async () => {
        const d = offlineDeps({ fetch: offline() });
        await pages(
            d,
            [['/accidents', 'accidents-fr.html']],
            [['/accidents?decade=1950', 'accidents-decade-1950.inertia.json']],
        );
        // Depuis la carte : le navigateur a les traductions, pas les menus.
        const response = await inertia(visit('/accidents?decade=1950', 'translations.fr'), d);
        expect(response.status).toBe(200);
        const page = await response.json();
        expect(page.props.facets.decades.length).toBeGreaterThan(0);
        expect(page.props.offline_copy).toEqual({ saved_at: '2026-11-19T12:00:00.000Z' });
    });

    it('laisse le navigateur recoller ses traductions, sinon demande une visite complète', async () => {
        const d = offlineDeps({ fetch: offline() });
        await pages(d, [['/', 'home-en.html']], [['/lines', 'lines.inertia.json']]);
        const same = await inertia(visit('/lines', 'translations.fr'), d);
        expect(same.status).toBe(200);
        expect((await same.json()).props).not.toHaveProperty('translations'); // Inertia les recolle
        // Le navigateur est passé en anglais depuis la copie : rien ne fournit les traductions françaises.
        const other = await inertia(visit('/lines', 'translations.en'), d);
        expect(other.status).toBe(409);
    });
});

describe('tuiles : seulement celles déjà affichées', () => {
    const TILE = fakeRequest('https://vff-project.org/api/v1/eras/2030/tiles/8/129/88.mvt?v=3');

    it('rend la tuile du réseau et en garde une copie, servie ensuite sans réseau', async () => {
        const d = offlineDeps({ fetch: vi.fn(async () => basicResponse('pbf')) });
        expect(await (await tile(TILE, d)).text()).toBe('pbf');
        await Promise.all(d.pending);

        const copy = await tile(TILE, { ...d, fetch: offline() });
        expect(await copy.text()).toBe('pbf');
        expect(copy.headers.get('X-VFF-Saved-At')).toBe('2026-11-19T12:00:00.000Z');
    });

    it('préfère toujours le réseau quand il répond (aucune tuile servie périmée en ligne)', async () => {
        const d = offlineDeps({ fetch: vi.fn(async () => basicResponse('nouvelle')) });
        await (await d.caches.open('vff-tiles-v1')).put(TILE.url, new Response('ancienne'));
        expect(await (await tile(TILE, d)).text()).toBe('nouvelle');
    });

    it('ne sert plus une tuile de plus de 7 jours et laisse remonter l erreur', async () => {
        const d = offlineDeps({ fetch: offline(), now: () => NOW + 8 * 24 * 3600_000 });
        await (
            await d.caches.open('vff-tiles-v1')
        ).put(
            TILE.url,
            new Response('pbf', { headers: { 'X-VFF-Saved-At': new Date(NOW).toISOString() } }),
        );
        await expect(tile(TILE, d)).rejects.toThrow('offline');
    });

    it('garde au plus MAX_TILE_ENTRIES tuiles', async () => {
        const d = offlineDeps({ fetch: vi.fn(async () => basicResponse('pbf')) });
        for (let n = 0; n <= MAX_TILE_ENTRIES; n += 1) {
            await tile(
                fakeRequest(`https://vff-project.org/api/v1/eras/2030/tiles/14/${n}/1.mvt`),
                d,
            );
            await Promise.all(d.pending);
        }
        const keys = await storedKeys(d, 'vff-tiles-v1');
        expect(keys).toHaveLength(MAX_TILE_ENTRIES);
        expect(keys).not.toContain('https://vff-project.org/api/v1/eras/2030/tiles/14/0/1.mvt');
    });
});

describe('pruneExpired', () => {
    it('supprime les copies trop anciennes ou sans date', async () => {
        const cache = fakeCache();
        const at = (ms) => ({ headers: { 'X-VFF-Saved-At': new Date(ms).toISOString() } });
        await cache.put('/recente', new Response('x', at(NOW - 1000)));
        await cache.put('/ancienne', new Response('x', at(NOW - 40 * 24 * 3600_000)));
        await cache.put('/sans-date', new Response('x'));
        await pruneExpired(cache, 30 * 24 * 3600_000, NOW);
        expect(await cache.keys()).toEqual(['/recente']);
    });
});

describe('données de la carte : seulement celles déjà affichées', () => {
    const STATIONS = fakeRequest('https://vff-project.org/api/v1/eras/1930/stations.geojson?v=3');
    const json = (body) =>
        vi.fn(async () => basicResponse(body, { headers: { 'Content-Type': 'application/json' } }));

    it('rend la réponse du réseau et en garde une copie datée, servie ensuite sans réseau', async () => {
        const d = offlineDeps({ fetch: json('{"type":"FeatureCollection"}') });
        expect(await (await mapData(STATIONS, d)).text()).toBe('{"type":"FeatureCollection"}');
        await Promise.all(d.pending);
        expect(await storedKeys(d, 'vff-mapdata-v1')).toEqual([STATIONS.url]);

        const copy = await mapData(STATIONS, { ...d, fetch: offline() });
        expect(await copy.text()).toBe('{"type":"FeatureCollection"}');
        expect(copy.headers.get('X-VFF-Saved-At')).toBe('2026-11-19T12:00:00.000Z');
    });

    it('préfère toujours le réseau quand il répond', async () => {
        const d = offlineDeps({ fetch: json('nouvelle') });
        await (await d.caches.open('vff-mapdata-v1')).put(STATIONS.url, new Response('ancienne'));
        expect(await (await mapData(STATIONS, d)).text()).toBe('nouvelle');
    });

    it('sans réseau, une ère jamais affichée reste une erreur (rien n est inventé)', async () => {
        const d = offlineDeps({ fetch: offline() });
        await expect(mapData(STATIONS, d)).rejects.toThrow('offline');
    });

    it('ne range ni une erreur ni une page HTML, ni rien quand le quota est atteint', async () => {
        const html = vi.fn(async () =>
            basicResponse('<html>', { headers: { 'Content-Type': 'text/html' } }),
        );
        const d = offlineDeps({ fetch: html });
        await mapData(STATIONS, d);
        await Promise.all(d.pending);
        expect(await storedKeys(d, 'vff-mapdata-v1')).toEqual([]);

        const full = offlineDeps({
            fetch: json('{}'),
            estimate: async () => ({ usage: 200 * 1024 * 1024, quota: 1e10 }),
        });
        await mapData(STATIONS, full);
        await Promise.all(full.pending);
        expect(await storedKeys(full, 'vff-mapdata-v1')).toEqual([]);
    });

    it('ne sert plus une copie de plus de 7 jours', async () => {
        const d = offlineDeps({ fetch: offline(), now: () => NOW + 8 * 24 * 3600_000 });
        await (
            await d.caches.open('vff-mapdata-v1')
        ).put(
            STATIONS.url,
            new Response('{}', { headers: { 'X-VFF-Saved-At': new Date(NOW).toISOString() } }),
        );
        await expect(mapData(STATIONS, d)).rejects.toThrow('offline');
        expect(await storedKeys(d, 'vff-mapdata-v1')).toEqual([]);
    });

    it('garde au plus MAX_MAP_DATA_ENTRIES fichiers, les plus anciens partent d abord', async () => {
        const d = offlineDeps({ fetch: json('{}') });
        const url = (year) => `https://vff-project.org/api/v1/eras/${year}/stations.geojson`;
        for (let n = 0; n <= MAX_MAP_DATA_ENTRIES; n += 1) {
            await mapData(fakeRequest(url(1900 + n * 10)), d);
            await Promise.all(d.pending);
        }
        const keys = await storedKeys(d, 'vff-mapdata-v1');
        expect(keys).toHaveLength(MAX_MAP_DATA_ENTRIES);
        expect(keys).not.toContain(url(1900));
    });

    it('sans cache configuré, se contente du réseau', async () => {
        const d = deps({ fetch: offline() });
        await expect(mapData(STATIONS, d)).rejects.toThrow('offline');
    });
});
