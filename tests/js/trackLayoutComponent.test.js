// Schéma des voies rendu côté serveur (svelte/server) : le tableau de parcours est l'alternative textuelle du
// dessin (chaque objet dessiné y a sa ligne, avec sa provenance), le dessin est décoratif dans une région nommée
// atteinte au clavier, les onglets suivent le motif de l'ARIA.
import { readFileSync } from 'node:fs';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { normalize, routeRows } from '$lib/trackLayout.js';
import TrackLayout from '$components/Lines/TrackLayout.svelte';
import TrackRouteTable from '$components/Lines/TrackRouteTable.svelte';

const raw = (name) =>
    JSON.parse(
        readFileSync(new URL(`./fixtures/trackLayout/${name}.json`, import.meta.url), 'utf8'),
    );

setupI18n({
    locale: 'fr',
    translations: {
        'tracks.row.signal': 'Signal :ref',
        'tracks.row.switch': 'Aiguillage :ref',
        'tracks.source.rinf': 'RINF',
        'tracks.source.osm': 'OpenStreetMap',
        'tracks.source.vff': 'déduit par VFF',
        'tracks.source.missing': 'donnée absente',
        'tracks.source.approx': 'position approchée',
        'tracks.schema_label': 'Schéma des voies, du PK :from au PK :to',
        'tracks.route.schema_label': 'Schéma des voies du parcours, du km :from au km :to',
        'tracks.route.strip': 'km',
        'tracks.strip.pk': 'PK',
    },
});

describe('schéma des voies (rendu serveur)', () => {
    for (const name of ['single-track', 'large-station']) {
        it(`donne une ligne du tableau par objet (${name})`, () => {
            const layout = raw(name);
            const { body } = render(TrackLayout, {
                props: { layout, slug: 'essai', lineName: 'Ligne d’essai' },
            });
            // Par défaut (menu « Afficher ») : ni bornes, ni heurtoirs, ni passages à niveau, ni traversées ; les manques
            // de la couverture sont dans le bandeau au-dessus du tableau, pas dans ses lignes.
            const off = new Set([
                'milestone',
                'buffer_stop',
                'level_crossing',
                'crossing',
                'missing',
            ]);
            const expected = routeRows(normalize(layout)).filter((r) => !off.has(r.kind));
            const keys = [...body.matchAll(/<tr data-key="([^"]+)"/g)].map((m) => m[1]);
            expect(keys).toEqual(expected.map((r) => r.key));
            // Chaque signal et chaque aiguillage du dessin est nommé dans le tableau.
            for (const s of layout.signals) expect(keys).toContain(`sig-${s.id}`);
            for (const s of layout.switches) expect(keys).toContain(`sw-${s.id}`);
            for (const op of layout.operational_points) expect(body).toContain(op.name);
        });
    }

    it('nomme la région du dessin, cache le dessin aux lecteurs d écran et décrit le clavier', () => {
        const { body } = render(TrackLayout, {
            props: { layout: raw('single-track'), slug: 'essai', lineName: 'Ligne d’essai' },
        });
        expect(body).toMatch(
            /role="region"[^>]*aria-label="Schéma des voies, du PK 0\+000 au PK 32\+400"[^>]*aria-describedby="tl-help"[^>]*tabindex="0"/,
        );
        expect(body).toMatch(/<svg[^>]*aria-hidden="true"[^>]*>/);
        expect(body).toContain('id="tl-help"');
        // Onglets : un seul sélectionné et atteignable par Tab, les autres par les flèches.
        expect([...body.matchAll(/role="tab"/g)]).toHaveLength(3);
        expect([...body.matchAll(/aria-selected="true"/g)]).toHaveLength(1);
        expect(body).toMatch(/id="tl-tab-schema"[^>]*tabindex="0"/);
        expect(body).toMatch(/id="tl-tab-map"[^>]*tabindex="-1"/);
    });

    it('écrit la provenance en toutes lettres derrière chaque badge', () => {
        const data = normalize(raw('single-track'));
        const rows = routeRows(data).filter((r) =>
            ['op-op-sp', 'sw-sw-val-1', 'miss-0'].includes(r.key),
        );
        const { body } = render(TrackRouteTable, {
            props: {
                rows,
                lang: 'fr',
                t: (k, o) => (o?.ref ? `${k}:${o.ref}` : k),
                onselect: () => {},
                tracksById: new Map(data.tracks.map((tr) => [tr.id, tr])),
                selected: 'sw-sw-val-1',
            },
        });
        // Saint-Pré : OpenStreetMap, position approchée (badge O et badge ≈).
        expect(body).toContain('tracks.source.osm, tracks.source.approx');
        expect(body).toContain('>≈</span>');
        // Le manque de la couverture : « ! ».
        expect(body).toMatch(/b-missing[^>]*><span aria-hidden="true">!<\/span>/);
        // La ligne choisie est marquée pour les technologies d'assistance.
        expect(body).toMatch(/<tr data-key="sw-sw-val-1"[^>]*aria-current="true"/);
    });

    it('lie un point d’exploitation à la fiche de sa gare', () => {
        const rows = [
            {
                key: 'op-a',
                kind: 'op',
                pk_m: 0,
                km: 0,
                kmh: null,
                permille: null,
                item: { id: 'a', name: 'Cognac', kind: 'station', pk_m: 0, station_slug: 'cognac' },
                prov: { code: 'R', key: 'rinf', approx: false },
            },
            {
                key: 'op-b',
                kind: 'op',
                pk_m: 900,
                km: 0.9,
                kmh: null,
                permille: null,
                item: {
                    id: 'b',
                    name: 'Faisceau Pair',
                    kind: 'other',
                    pk_m: 900,
                    station_slug: null,
                },
                prov: { code: 'R', key: 'rinf', approx: false },
            },
        ];
        const { body } = render(TrackRouteTable, {
            props: {
                rows,
                lang: 'fr',
                t: (k, o) => (o?.name ? `${k}:${o.name}` : k),
                onselect: () => {},
                tracksById: new Map(),
            },
        });
        expect(body).toMatch(
            /<a[^>]*href="\/stations\/cognac"[^>]*aria-label="tracks.table.station_label:Cognac"[^>]*>tracks.table.station<\/a>/,
        );
        // Et vers ses voies (/stations/{slug}/voies) ; le faisceau sans gare n'a aucun lien.
        expect(body).toMatch(
            /<a[^>]*href="\/stations\/cognac\/voies"[^>]*aria-label="tracks.table.station_tracks_label:Cognac"[^>]*>tracks.table.station_tracks<\/a>/,
        );
        expect([...body.matchAll(/href="\/stations\//g)]).toHaveLength(2);
    });

    it('dit le sens de marche et non les PK sur un livret de parcours', () => {
        const signal = {
            key: 'sig-1',
            kind: 'signal',
            pk_m: 100,
            km: 0.1,
            kmh: null,
            permille: null,
            item: { id: '1', ref: 'C1', pk_m: 100, direction: 'up' },
            prov: { code: 'O', key: 'osm', approx: false },
        };
        const props = (axisMode) => ({
            rows: [signal],
            lang: 'fr',
            t: (k) => k,
            onselect: () => {},
            tracksById: new Map(),
            axisMode,
        });
        expect(render(TrackRouteTable, { props: props('route') }).body).toContain(
            'tracks.route.up',
        );
        expect(render(TrackRouteTable, { props: props('route') }).body).toContain(
            'tracks.route.caption',
        );
        expect(render(TrackRouteTable, { props: props('line') }).body).toContain('tracks.row.up');

        const { body } = render(TrackLayout, {
            props: {
                layout: raw('single-track'),
                slug: null,
                lineName: 'A - B',
                axisMode: 'route',
            },
        });
        expect(body).toContain('aria-label="Schéma des voies du parcours, du km 0 au km 32,4"');
        expect(body).not.toContain('id="tl-tab-map"');
    });
});
