// Tableau de parcours du schéma des voies (lib/trackLayout.js, 25/11/2026) : deux déclivités (ou vitesses) qui se
// recouvrent, venues de deux sections parallèles d'une ligne au RINF, disent chacune leurs voies (`tracks` de l'API).
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { normalize, overlapping, routeRows } from '$lib/trackLayout.js';
import TrackRouteTable from '$components/Lines/TrackRouteTable.svelte';

describe('trackLayout : voies des déclivités qui se recouvrent', () => {
    // Paris-Austerlitz au RINF (réponse du 25/11/2026) : la section des voies V1 et V2 et celle des voies V1S et V2S
    // (rampe de Tolbiac) partent toutes deux du PK 0.
    const g = (from, to, permille, tracks) => ({
        pk_from_m: from,
        pk_to_m: to,
        permille,
        tracks,
        source: 'rinf',
        precision: 'derived',
    });
    const data = normalize({
        pk: { start_m: 0, end_m: 3000 },
        operational_points: [{ id: 'a', name: 'Paris-Austerlitz', kind: 'station', pk_m: 0 }],
        strip: {
            speeds: [],
            gradients: [
                g(0, 357, 0, ['V1S', 'V2S']),
                g(0, 800, -1.4, ['V1', 'V2']),
                g(357, 628, -23.4, ['V1S', 'V2S']),
                g(628, 1202, 23.4, ['V1S']),
                g(800, 860, 5.5, ['V1', 'V2']),
                g(1363, 1470, -3.8, ['V1', 'V2']),
                {
                    pk_from_m: 2000,
                    pk_to_m: 3000,
                    permille: 1.2,
                    source: 'vff',
                    precision: 'derived',
                },
            ],
        },
    });

    it('ne cherche que les tranches qui en recouvrent une autre', () => {
        const hit = overlapping(data.strip.gradients);
        expect(data.strip.gradients.map((r) => hit.has(r))).toEqual([
            true,
            true,
            true,
            true,
            true,
            false,
            false,
        ]);
        expect(overlapping([g(0, 10, 1, null), g(10, 20, 2, null)]).size).toBe(0);
    });

    it('dit la voie de chaque déclivité d’une section parallèle, pas des autres', () => {
        const rows = routeRows(data).filter((r) => r.kind === 'gradient');
        expect(rows.map((r) => [r.pk_m, r.item.permille, r.tracks ?? null])).toEqual([
            [0, 0, ['V1S', 'V2S']],
            [0, -1.4, ['V1', 'V2']],
            [357, -23.4, ['V1S', 'V2S']],
            [628, 23.4, ['V1S']],
            [800, 5.5, ['V1', 'V2']],
            [1363, -3.8, null],
            [2000, 1.2, null],
        ]);
        const { body } = render(TrackRouteTable, {
            props: {
                rows,
                lang: 'fr',
                t: (k, o) => (o?.refs ? `${k}=${o.refs}` : o?.ref ? `${k}=${o.ref}` : k),
                onselect: () => {},
                tracksById: new Map(),
            },
        });
        expect(body).toContain('tracks.row.on_tracks=V1S, V2S');
        expect(body).toContain('tracks.row.on_tracks=V1, V2');
        expect(body).toContain('tracks.row.on_track=V1S');
        expect([...body.matchAll(/tracks\.row\.on_tracks=/g)]).toHaveLength(4);
    });
    it('donne à chaque ligne la déclivité de la bande (voies principales d’abord), et la sienne à une déclivité', () => {
        const rows = routeRows(data);
        // Paris-Austerlitz : la gare lit -1,4 ‰ (voies V1 et V2), pas le 0 ‰ de la rampe V1S, V2S qui commence au même PK.
        expect(rows.find((r) => r.kind === 'op').permille).toBe(-1.4);
        expect(
            rows.filter((r) => r.kind === 'gradient').map((r) => [r.item.permille, r.permille]),
        ).toEqual([
            [0, 0],
            [-1.4, -1.4],
            [-23.4, -23.4],
            [23.4, 23.4],
            [5.5, 5.5],
            [-3.8, -3.8],
            [1.2, 1.2],
        ]);
    });
});
