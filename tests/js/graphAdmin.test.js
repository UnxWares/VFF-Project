import { describe, expect, it } from 'vitest';
import {
    CHECKS_BY_LAYER,
    LINE_PALETTE,
    checkUrl,
    detailRows,
    detailTitle,
    edgeColorExpr,
    featureRequest,
    featureUrl,
    formatDistance,
    formatPk,
    VISIT_OPTIONS,
    itemSummary,
    itemTarget,
    latestRequest,
    layerFigures,
    listView,
    lineColor,
    nodeColorExpr,
    pageQuery,
    paddedBounds,
    tileUrl,
} from '../../resources/js/lib/graphAdmin.js';

/** Traduction factice : la clé suivie des paramètres, pour vérifier ce qui est demandé. */
const t = (key, opts) => (opts ? `${key} ${JSON.stringify(opts)}` : key);

describe('adresses', () => {
    it('construit les adresses des tuiles, des listes et des éléments', () => {
        expect(tileUrl('https://vff.test', 1930, 'stations')).toBe(
            'https://vff.test/validate/graph/1930/tiles/stations/{z}/{x}/{y}.mvt',
        );
        expect(checkUrl(2030, 'junctions', 3)).toBe(
            '/validate/graph/2030/diagnostics/junctions?page=3',
        );
        expect(featureUrl(1970, 'station_edge', 12)).toBe(
            '/validate/graph/1970/features/station_edge/12',
        );
    });

    it("garde l'ère et la couche dans l'adresse de la page, sans la couche par défaut", () => {
        expect(pageQuery(1930, 'topology')).toBe('/validate/graph?era=1930');
        expect(pageQuery(1930, 'lines')).toBe('/validate/graph?era=1930&layer=lines');
        expect(pageQuery(null, 'topology')).toBe('/validate/graph');
    });
});

describe('clic sur la carte', () => {
    it('associe chaque calque de chaque couche à un type de fiche', () => {
        expect(featureRequest('topology', 'edges', { id: 4 })).toEqual({ kind: 'edge', id: 4 });
        expect(featureRequest('topology', 'nodes', { id: 5 })).toEqual({ kind: 'node', id: 5 });
        expect(featureRequest('stations', 'edges', { id: 6 })).toEqual({
            kind: 'station_edge',
            id: 6,
        });
        expect(featureRequest('stations', 'nodes', { id: 7 })).toEqual({ kind: 'station', id: 7 });
        expect(featureRequest('lines', 'nodes', { id: 8 })).toEqual({ kind: 'stop', id: 8 });
    });

    it('ignore un élément sans identifiant ou un calque inconnu', () => {
        expect(featureRequest('topology', 'edges', {})).toBeNull();
        expect(featureRequest('topology', 'edges', { id: -1 })).toBeNull();
        expect(featureRequest('topology', 'labels', { id: 3 })).toBeNull();
    });
});

describe('cibles des diagnostics', () => {
    it('recadre sur une île et décrit le nœud ou le raccord d’une jonction', () => {
        expect(itemTarget('islands', { bbox: [1, 2, 3, 4] })).toEqual({ bbox: [1, 2, 3, 4] });
        expect(itemTarget('junctions', { type: 'spread', id: 9, lng: 2, lat: 48 })).toEqual({
            point: [2, 48],
            feature: { kind: 'node', id: 9 },
        });
        expect(
            itemTarget('junctions', { type: 'connector', id: 10, lng: 2, lat: 48 }).feature,
        ).toEqual({
            kind: 'edge',
            id: 10,
        });
        expect(
            itemTarget('edges', { layer: 'stations', id: 11, lng: 1, lat: 1 }).feature.kind,
        ).toBe('station_edge');
        expect(itemTarget('stations_without_edge', { id: 12, lng: 1, lat: 1 }).feature).toEqual({
            kind: 'station',
            id: 12,
        });
    });

    it('chaque couche a ses listes', () => {
        expect(CHECKS_BY_LAYER.topology).toContain('junctions');
        expect(CHECKS_BY_LAYER.stations).toContain('stations_without_edge');
        expect(CHECKS_BY_LAYER.lines).toEqual(['fragmented_lines']);
    });

    it('élargit une emprise trop petite, garde une grande emprise', () => {
        const [[w, s], [e, n]] = paddedBounds([2, 48, 2, 48]);
        expect(e - w).toBeCloseTo(0.01);
        expect(n - s).toBeCloseTo(0.01);
        expect(paddedBounds([0, 40, 5, 45])).toEqual([
            [0, 40],
            [5, 45],
        ]);
    });
});

describe('couleurs', () => {
    it('donne une couleur stable à chaque ligne, même règle que la carte', () => {
        expect(lineColor(0)).toBe(LINE_PALETTE[0]);
        expect(lineColor(LINE_PALETTE.length + 2)).toBe(LINE_PALETTE[2]);
        expect(lineColor('abc')).toBe('#475569');
        const expr = edgeColorExpr('lines');
        expect(expr[0]).toBe('case');
        expect(JSON.stringify(expr)).toContain(LINE_PALETTE[3]);
    });

    it('colore arêtes et nœuds selon leur type', () => {
        const topology = edgeColorExpr('topology');
        expect(topology.slice(0, 2)).toEqual(['match', ['get', 'kind']]);
        expect(topology).toContain('connector');
        expect(nodeColorExpr('stations')[1]).toEqual(['get', 'state']);
        expect(nodeColorExpr('lines')[1]).toEqual(['get', 'status']);
    });
});

describe('formats', () => {
    it('écrit les distances en centimètres, mètres ou kilomètres', () => {
        expect(formatDistance(0.25, 'fr')).toBe('0,25 m');
        expect(formatDistance(37.4, 'fr')).toBe('37 m');
        expect(formatDistance(12_345, 'en')).toBe('12.3 km');
        expect(formatDistance(null, 'fr')).toBe('');
    });

    it('écrit un PK en kilomètres à trois décimales', () => {
        expect(formatPk(498_273, 'fr')).toBe('498,273');
        expect(formatPk(undefined, 'fr')).toBe('');
    });
});

describe('fiche d’un élément', () => {
    it('donne un titre selon le type', () => {
        expect(detailTitle({ kind: 'edge', id: 4 }, t)).toContain('graph_admin.details.kinds.edge');
        expect(detailTitle({ kind: 'stop', id: 4, station: { name: 'Volvic' } }, t)).toContain(
            'Volvic',
        );
    });

    it("liste les attributs d'une arête avec le lien vers la ligne et le PK", () => {
        const rows = detailRows(
            {
                kind: 'edge',
                id: 7,
                from_node_id: 1,
                to_node_id: 2,
                segment_id: 3,
                line: { id: 5, slug: 'rfn-711000', name: 'Ligne de X à Y', code: '711000' },
                length_m: 1000,
                measured_m: 999.7,
                pk: { start_m: 1000, end_m: 2000 },
                source: 'osm',
                osm_way_id: 42,
            },
            t,
            'fr',
        );
        const labels = rows.map((r) => r.label);
        expect(labels).toEqual(
            expect.arrayContaining([
                'graph_admin.details.line',
                'graph_admin.details.pk',
                'graph_admin.details.source',
            ]),
        );
        const line = rows.find((r) => r.label === 'graph_admin.details.line');
        expect(line.parts[0]).toEqual({
            text: 'Ligne de X à Y (711000)',
            href: '/lines/rfn-711000',
        });
        const osm = rows.find((r) => r.label === 'graph_admin.details.osm_way');
        expect(osm.parts[0].href).toBe('https://www.openstreetmap.org/way/42');
        // Champs absents (trafic, réseau) : pas de ligne vide.
        expect(labels).not.toContain('graph_admin.details.traffic');
    });

    it('signale un tronçon sans PK', () => {
        const rows = detailRows(
            { kind: 'edge', id: 1, segment_id: 2, pk: null, length_m: 5 },
            t,
            'fr',
        );
        expect(rows.find((r) => r.label === 'graph_admin.details.pk').parts[0].text).toBe(
            'graph_admin.details.pk_missing',
        );
    });

    it("liste les voisines d'une gare et en annonce le reste", () => {
        const rows = detailRows(
            {
                kind: 'station',
                id: 1,
                slug: 'volvic',
                name: 'Volvic',
                status: 'active',
                node_id: 9,
                neighbours: [{ id: 2, slug: 'riom', name: 'Riom', length_m: 12000 }],
                neighbour_count: 3,
                provenance: { geom: 'sncf' },
            },
            t,
            'fr',
        );
        const neighbours = rows.find((r) => r.label === 'graph_admin.details.neighbours');
        expect(neighbours.parts[0]).toEqual({ text: 'Riom, 12 km', href: '/stations/riom' });
        expect(neighbours.parts[1].text).toContain('"count":2');
        expect(rows.find((r) => r.label === 'graph_admin.details.provenance').parts[0].text).toBe(
            'geom : sncf',
        );
    });
});

describe('chiffres clés', () => {
    const summary = {
        topology: { nodes: 10, edges: 6, islands: 3, spread_junctions: 1 },
        stations: { stations: 3, edges: 1, islands: 1, without_edge: 2 },
        lines: { lines: 3, stops: 0, fragmented: 1, stops_off_graph: 0 },
    };

    it('donne quatre chiffres par couche', () => {
        expect(layerFigures(summary, 'topology').map((f) => f.value)).toEqual([10, 6, 3, 1]);
        expect(layerFigures(summary, 'stations').map((f) => f.key)).toContain('without_edge');
        expect(layerFigures(summary, 'lines')).toHaveLength(4);
        expect(layerFigures(null, 'topology')).toEqual([]);
    });
});

describe('corrections de la relecture', () => {
    it('un saut sans tracé montre son arête', () => {
        expect(itemTarget('junctions', { type: 'jump', id: 21, lng: 2, lat: 48 })).toEqual({
            point: [2, 48],
            feature: { kind: 'edge', id: 21 },
        });
    });

    it('la fiche d’une arête sans tracé donne l’écart entre ses nœuds', () => {
        const rows = detailRows(
            {
                kind: 'edge',
                id: 5,
                from_node_id: 1,
                to_node_id: 2,
                length_m: 0,
                measured_m: null,
                nodes_gap_m: 311.3,
            },
            t,
            'fr',
        );
        const gap = rows.find((r) => r.label === 'graph_admin.details.nodes_gap');
        expect(gap?.parts[0].text).toBe('311 m');
        expect(rows.some((r) => r.label === 'graph_admin.details.measured')).toBe(false);
    });

    it('résume une île, une île de gares et une ligne en morceaux pour le panneau', () => {
        const island = itemSummary(
            'islands',
            {
                id: 7,
                nodes: 12,
                edges: 11,
                length_m: 4200,
                stations: 2,
                lines: [{ id: 3, slug: 'l3', name: 'Ligne 3', code: 'L3' }],
            },
            t,
            'fr',
        );
        expect(island.title).toBe('graph_admin.details.kinds.island {"id":7}');
        expect(island.rows.map((r) => r.label)).toEqual([
            'graph_admin.details.nodes',
            'graph_admin.details.edges',
            'graph_admin.details.length',
            'graph_admin.details.stations_count',
            'graph_admin.details.lines',
        ]);
        expect(island.rows.at(-1).parts[0]).toEqual({ text: 'Ligne 3 (L3)', href: '/lines/l3' });

        const stations = itemSummary(
            'station_islands',
            {
                id: 4,
                stations: 2,
                edges: 1,
                length_m: 800,
                sample: [{ id: 4, slug: 'c', name: 'Gare C' }],
            },
            t,
            'fr',
        );
        expect(stations.title).toContain('station_island');
        expect(stations.rows.at(-1).parts[0]).toEqual({ text: 'Gare C', href: '/stations/c' });

        const line = itemSummary(
            'fragmented_lines',
            {
                line_id: 9,
                pieces: 3,
                edges: 8,
                length_m: 12000,
                line: { id: 9, slug: 'l9', name: 'Ligne 9', code: null },
            },
            t,
            'fr',
        );
        expect(line.title).toBe('graph_admin.details.kinds.fragmented_line {"name":"Ligne 9"}');
        expect(line.rows[0].parts[0].href).toBe('/lines/l9');

        expect(itemSummary('edges', { id: 1 }, t, 'fr')).toBeNull();
    });

    it('garde la page affichée pendant le chargement de la suivante', () => {
        const page1 = { check: 'junctions', page: 1, items: [] };
        expect(listView(page1, 'junctions', 'loading')).toEqual({ view: 'table', busy: true });
        expect(listView(page1, 'junctions', 'ready')).toEqual({ view: 'table', busy: false });
        // Une liste d'une autre sorte n'est pas montrée sous les colonnes de la nouvelle.
        expect(listView(page1, 'islands', 'loading')).toEqual({ view: 'loading', busy: true });
        expect(listView(null, 'islands', 'loading').view).toBe('loading');
        expect(listView(page1, 'junctions', 'error').view).toBe('error');
    });

    it('change d’ère ou de couche sans recréer la page', () => {
        expect(VISIT_OPTIONS.preserveState).toBe(true);
        expect(VISIT_OPTIONS.preserveScroll).toBe(true);
        expect(VISIT_OPTIONS.only).toEqual(['year', 'missing_era', 'layer', 'summary']);
    });

    it('ne garde que la dernière demande de fiche', () => {
        const requests = latestRequest();
        const first = requests.next();
        const second = requests.next();
        expect(first.aborted).toBe(true);
        expect(requests.isCurrent(first)).toBe(false);
        expect(requests.isCurrent(second)).toBe(true);
        requests.cancel();
        expect(second.aborted).toBe(true);
        expect(requests.isCurrent(second)).toBe(false);
    });
});
