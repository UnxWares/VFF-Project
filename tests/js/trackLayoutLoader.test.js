// Lecture du schéma des voies sans bloquer la page (lib/trackLayoutLoader.svelte.js) : aperçu, puis la ligne entière
// si elle est courte, ou des portions au fil du défilement si elle est longue, jamais deux fois la même.
import { describe, expect, it } from 'vitest';
import { TrackLayoutLoader } from '$lib/trackLayoutLoader.svelte.js';
import { FULL_LIMIT, MAX_CHUNKS } from '$lib/trackLayout.js';

/** Faux `fetch` : réponse selon l'adresse, adresses demandées gardées. */
function fakeFetch(answer) {
    const urls = [];
    const fn = async (url) => {
        urls.push(String(url));
        const params = new URL(String(url), 'http://vff.test').searchParams;
        const body = answer(params);
        return { ok: body !== null, status: body === null ? 500 : 200, json: async () => body };
    };
    return { fn, urls };
}

const flush = () => new Promise((r) => setTimeout(r, 0));

function overview(objects, end = 100000) {
    return {
        pk: { start_m: 0, end_m: end },
        operational_points: [
            { id: 'a', name: 'A', pk_m: 0 },
            { id: 'b', name: 'B', pk_m: end },
        ],
        counts: { operational_points: 2, tracks: objects, switches: 0 },
        coverage: { total_m: end, missing: [] },
        tracks: [],
    };
}

describe('TrackLayoutLoader', () => {
    it('lit une ligne courte d’un coup après l’aperçu', async () => {
        const { fn, urls } = fakeFetch((p) =>
            p.get('kinds') === 'operational_points'
                ? overview(10, 5000)
                : {
                      ...overview(10, 5000),
                      tracks: [{ id: 't', kind: 'running', lane: 0, pk_from_m: 0, pk_to_m: 5000 }],
                  },
        );
        const loader = new TrackLayoutLoader('rfn-1', fn);
        await Promise.all([loader.loadOverview(), loader.loadOverview()]);
        expect(urls).toEqual(['/api/v1/lines/rfn-1/track-layout?kinds=operational_points']);
        expect(loader.chunked).toBe(false);
        await loader.loadDetail();
        expect(loader.detail).toBe('ready');
        expect(loader.layout.tracks).toHaveLength(1);
        expect(urls).toHaveLength(2);
        await loader.loadDetail();
        expect(urls).toHaveLength(2);
    });

    it('lit une longue ligne par portions, la plus proche du milieu de la vue d’abord, jamais deux fois', async () => {
        const { fn, urls } = fakeFetch((p) => {
            if (p.get('kinds') === 'operational_points') return overview(FULL_LIMIT + 1);
            const from = Number(p.get('from_pk_m'));
            return {
                tracks: [
                    {
                        id: `t${from}`,
                        kind: 'loop',
                        lane: -1,
                        pk_from_m: from,
                        pk_to_m: from + 100,
                    },
                ],
                strip: { speeds: [], gradients: [] },
            };
        });
        const loader = new TrackLayoutLoader('rfn-2', fn);
        await loader.loadDetail();
        expect(loader.chunked).toBe(true);
        expect(loader.detail).toBe('ready');
        expect(loader.bounds).toHaveLength(10);

        loader.ensure(25000, 38000);
        await flush();
        await flush();
        const parts = urls.slice(1);
        expect(parts[0]).toContain('from_pk_m=30000&to_pk_m=40000');
        expect(parts).toHaveLength(2);
        expect(loader.layout.tracks.map((t) => t.id).sort()).toEqual(['t20000', 't30000']);

        loader.ensure(25000, 38000);
        await flush();
        expect(urls).toHaveLength(3);
    });

    it('ne lit rien quand toute la ligne est à l’écran, et le dit', async () => {
        const { fn, urls } = fakeFetch((p) =>
            p.get('kinds') === 'operational_points'
                ? overview(FULL_LIMIT + 1, 200000)
                : { tracks: [] },
        );
        const loader = new TrackLayoutLoader('rfn-3', fn);
        await loader.loadOverview();
        loader.ensure(0, 200000);
        expect(loader.bounds.length).toBeGreaterThan(MAX_CHUNKS);
        expect(loader.tooWide).toBe(true);
        await flush();
        expect(urls).toHaveLength(1);
    });

    it('signale une portion illisible et une erreur de l’aperçu', async () => {
        const { fn } = fakeFetch((p) =>
            p.get('kinds') === 'operational_points' ? overview(FULL_LIMIT + 1) : null,
        );
        const loader = new TrackLayoutLoader('rfn-4', fn);
        await loader.loadOverview();
        loader.ensure(0, 5000);
        await flush();
        await flush();
        expect(loader.failed).toBe(true);

        const broken = new TrackLayoutLoader('rfn-5', fakeFetch(() => null).fn);
        await broken.loadOverview();
        expect(broken.status).toBe('error');
    });
});
