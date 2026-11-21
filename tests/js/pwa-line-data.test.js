// Tracé de la carte d'une fiche de ligne, lu à part (LinePortionsController) : chargement par la page
// (lib/linePortions.js) et copie gardée par le service worker pour la fiche relue hors ligne.
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { lineData } from '../../resources/js/pwa/handlers.js';
import {
    LINE_DATA_MAX_AGE_MS,
    MAX_LINE_DATA_ENTRIES,
    MAX_PAGE_ENTRIES,
    PAGE_MAX_AGE_MS,
    classify,
    isLineDataPath,
    isVffCache,
    lineDataCacheName,
    staleCacheNames,
} from '../../resources/js/pwa/rules.js';
import { forgetLinePortions, loadLinePortions } from '../../resources/js/lib/linePortions.js';
import { basicResponse, fakeCacheStorage, fakeRequest } from './support/pwaFakes.js';

const ORIGIN = 'https://vff-project.org';
const URL_405 = `${ORIGIN}/lines/rfn-405000/portions/0123456789ab.json`;
const NOW = Date.parse('2026-11-21T10:00:00Z');
const offline = () => vi.fn(async () => Promise.reject(new TypeError('offline')));
const json = (body) =>
    vi.fn(async () =>
        basicResponse(body, { headers: { 'Content-Type': 'application/json; charset=utf-8' } }),
    );

function deps(overrides = {}) {
    const pending = [];
    return {
        caches: fakeCacheStorage(),
        cacheName: 'vff-static-v1',
        lineDataCache: 'vff-linedata-v1',
        waitUntil: (p) => pending.push(p),
        pending,
        now: () => NOW,
        estimate: async () => ({ usage: 1e6, quota: 1e9 }),
        ...overrides,
    };
}

describe('règles : tracé d une fiche de ligne', () => {
    it('reconnaît l adresse du tracé, à son empreinte de version', () => {
        expect(classify(fakeRequest(URL_405), ORIGIN)).toBe('linedata');
        expect(isLineDataPath('/lines/rfn-752000/portions/abcdef012345.json')).toBe(true);
    });

    it('laisse la page de la fiche et toute autre adresse à leurs propres règles', () => {
        for (const path of [
            '/lines/rfn-405000',
            '/lines/rfn-405000/portions/xyz.json',
            '/lines/rfn-405000/portions/0123456789ab.json/x',
            '/lines/RFN-405000/portions/0123456789ab.json',
            '/api/v1/lines/rfn-405000',
        ]) {
            expect(isLineDataPath(path), path).toBe(false);
        }
        expect(
            classify(
                fakeRequest('https://autre.example/lines/a/portions/0123456789ab.json'),
                ORIGIN,
            ),
        ).toBe('network');
        expect(classify(fakeRequest(URL_405, { method: 'POST' }), ORIGIN)).toBe('network');
    });

    it('a son cache versionné, aussi durable que les pages et pas plus grand', () => {
        expect(lineDataCacheName('bbb')).toBe('vff-linedata-bbb');
        expect(isVffCache('vff-linedata-aaa')).toBe(true);
        expect(staleCacheNames(['vff-linedata-aaa', 'vff-linedata-bbb'], 'bbb')).toEqual([
            'vff-linedata-aaa',
        ]);
        expect(MAX_LINE_DATA_ENTRIES).toBe(MAX_PAGE_ENTRIES);
        expect(LINE_DATA_MAX_AGE_MS).toBe(PAGE_MAX_AGE_MS);
    });
});

describe('service worker : copie du tracé déjà affiché', () => {
    it('rend la réponse du réseau, en garde une copie et la sert sans réseau', async () => {
        const d = deps({ fetch: json('{"portions":[]}') });
        const request = fakeRequest(URL_405);
        expect(await (await lineData(request, d)).text()).toBe('{"portions":[]}');
        await Promise.all(d.pending);
        expect(await (await d.caches.open('vff-linedata-v1')).keys()).toEqual([URL_405]);

        const copy = await lineData(request, { ...d, fetch: offline() });
        expect(await copy.text()).toBe('{"portions":[]}');
    });

    it('sans réseau ni copie, l erreur reste (la carte le dit)', async () => {
        await expect(lineData(fakeRequest(URL_405), deps({ fetch: offline() }))).rejects.toThrow(
            'offline',
        );
    });

    it('ne range pas une page d erreur HTML', async () => {
        const html = vi.fn(async () =>
            basicResponse('<html>', { headers: { 'Content-Type': 'text/html' } }),
        );
        const d = deps({ fetch: html });
        await lineData(fakeRequest(URL_405), d);
        await Promise.all(d.pending);
        expect(await (await d.caches.open('vff-linedata-v1')).keys()).toEqual([]);
    });
});

describe('page : chargement du tracé', () => {
    beforeEach(() => forgetLinePortions());

    const ok = (portions) =>
        vi.fn(async () => new Response(JSON.stringify({ version: 'v', portions })));

    it('rend les portions et ne demande une même adresse qu une fois', async () => {
        const fetchImpl = ok([{ length_m: 10, geometry: { type: 'LineString', coordinates: [] } }]);
        const first = await loadLinePortions('/lines/a/portions/0123456789ab.json', fetchImpl);
        const again = await loadLinePortions('/lines/a/portions/0123456789ab.json', fetchImpl);
        expect(first).toHaveLength(1);
        expect(again).toBe(first);
        expect(fetchImpl).toHaveBeenCalledTimes(1);
    });

    it('n oublie pas un échec : la demande suivante réessaie', async () => {
        const failing = vi.fn(async () => new Response('', { status: 503 }));
        await expect(
            loadLinePortions('/lines/a/portions/0123456789ab.json', failing),
        ).rejects.toThrow('503');
        const fetchImpl = ok([]);
        expect(await loadLinePortions('/lines/a/portions/0123456789ab.json', fetchImpl)).toEqual(
            [],
        );
        expect(fetchImpl).toHaveBeenCalledTimes(1);
    });

    it('refuse une réponse sans liste de portions', async () => {
        const odd = vi.fn(async () => new Response('{"portions":null}'));
        await expect(loadLinePortions('/lines/b/portions/0123456789ab.json', odd)).rejects.toThrow(
            'réponse inattendue',
        );
    });

    it('ne garde en mémoire que les derniers tracés', async () => {
        const fetchImpl = ok([]);
        for (const slug of ['a', 'b', 'c', 'd', 'e']) {
            await loadLinePortions(`/lines/${slug}/portions/0123456789ab.json`, fetchImpl);
        }
        await loadLinePortions('/lines/e/portions/0123456789ab.json', fetchImpl);
        expect(fetchImpl).toHaveBeenCalledTimes(5);
        await loadLinePortions('/lines/a/portions/0123456789ab.json', fetchImpl);
        expect(fetchImpl).toHaveBeenCalledTimes(6);
    });
});
