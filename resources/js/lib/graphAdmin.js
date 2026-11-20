// Graphe ferroviaire d'une ère dans l'espace d'administration (Pages/Validate/Graph.svelte) :
// couleurs des trois couches (topologie, gares, lignes), adresses des tuiles et des fiches
// d'éléments, cible sur la carte d'une ligne des diagnostics, formats des distances.
// Fonctions pures, testées par tests/js/graphAdmin.test.js.

import { HERITAGE_COLOR, LGV_COLOR, STATION_CLOSED_COLOR } from './railStyle.js';

export const LAYERS = ['topology', 'stations', 'lines'];

/** Zoom à partir duquel les tuiles du graphe sont demandées (une tuile plus large pèse plusieurs Mo). */
export const GRAPH_MIN_ZOOM = 8;

const RED = '#dc2626';
const SLATE = '#475569';
const BLUE = '#0284c7';
const ORANGE = '#ff6500';
const AMBER = '#b45309';

/** Types d'arêtes et de nœuds de chaque couche, dans l'ordre de la légende. */
export const EDGE_STYLES = {
    topology: [
        { key: 'track', color: SLATE },
        { key: 'lgv', color: LGV_COLOR },
        { key: 'non_passenger', color: BLUE },
        { key: 'heritage', color: HERITAGE_COLOR },
        { key: 'connector', color: RED },
    ],
    stations: [
        { key: 'commercial', color: ORANGE },
        { key: 'non_passenger', color: BLUE },
        { key: 'heritage', color: HERITAGE_COLOR },
        { key: 'gap', color: RED },
    ],
    lines: [{ key: 'connector', color: RED }],
};

export const NODE_STYLES = {
    topology: [
        { key: 'junction', color: '#2563eb' },
        { key: 'end', color: AMBER },
        { key: 'station', color: ORANGE },
        { key: 'closed_station', color: STATION_CLOSED_COLOR },
        { key: 'gap', color: RED },
        { key: 'link', color: '#94a3b8' },
    ],
    stations: [
        { key: 'linked', color: ORANGE },
        { key: 'no_neighbour', color: AMBER },
        { key: 'unattached', color: RED },
    ],
    lines: [
        { key: 'active', color: ORANGE },
        { key: 'other', color: STATION_CLOSED_COLOR },
    ],
};

/** Couleurs des lignes de la couche « lignes » : la couleur suit l'identifiant de la ligne. */
export const LINE_PALETTE = [
    '#2563eb',
    '#db2777',
    '#16a34a',
    '#ea580c',
    '#7c3aed',
    '#0891b2',
    '#ca8a04',
    '#475569',
];

/**
 * Expression MapLibre `match` sur une propriété, avec une couleur par défaut.
 *
 * @param {string} property
 * @param {{ key: string, color: string }[]} styles
 * @param {string} fallback
 */
function matchColor(property, styles, fallback) {
    return ['match', ['get', property], ...styles.flatMap((s) => [s.key, s.color]), fallback];
}

/** Couleur d'une arête de la couche. */
export function edgeColorExpr(layer) {
    if (layer === 'lines') {
        return [
            'case',
            ['==', ['get', 'kind'], 'connector'],
            RED,
            [
                'match',
                ['%', ['to-number', ['get', 'line_id'], 0], LINE_PALETTE.length],
                ...LINE_PALETTE.flatMap((c, i) => [i, c]),
                SLATE,
            ],
        ];
    }
    return matchColor('kind', EDGE_STYLES[layer] ?? [], SLATE);
}

/** Couleur d'un nœud de la couche. */
export function nodeColorExpr(layer) {
    if (layer === 'stations') return matchColor('state', NODE_STYLES.stations, ORANGE);
    if (layer === 'lines') {
        return ['match', ['get', 'status'], 'active', ORANGE, STATION_CLOSED_COLOR];
    }
    return matchColor('kind', NODE_STYLES.topology, SLATE);
}

/** Couleur de la ligne `id` dans la couche « lignes » (même règle que edgeColorExpr). */
export function lineColor(id) {
    const n = Number(id);
    if (!Number.isFinite(n)) return SLATE;
    return LINE_PALETTE[((n % LINE_PALETTE.length) + LINE_PALETTE.length) % LINE_PALETTE.length];
}

/**
 * Adresse des tuiles d'une couche (gabarit MapLibre).
 *
 * @param {string} origin ex. « https://vff-project.org »
 * @param {number} year
 * @param {string} layer
 */
export function tileUrl(origin, year, layer) {
    return `${origin}/validate/graph/${year}/tiles/${layer}/{z}/{x}/{y}.mvt`;
}

/** Types de jonctions, dans l'ordre du filtre. */
export const JUNCTION_TYPES = ['spread', 'connector', 'jump'];

/**
 * Paramètres du filtre d'une liste : seules les jonctions se filtrent (type, ligne).
 *
 * @param {string} check
 * @param {{ type?: string, line?: string } | null | undefined} filters
 * @returns {[string, string][]}
 */
function filterParams(check, filters) {
    if (check !== 'junctions' || !filters) return [];
    /** @type {[string, string][]} */
    const params = [];
    if (filters.type && JUNCTION_TYPES.includes(filters.type)) params.push(['type', filters.type]);
    const line = (filters.line ?? '').trim();
    if (line) params.push(['line', line]);
    return params;
}

/** Adresse JSON d'une page d'une liste de diagnostics, avec le filtre des jonctions. */
export function checkUrl(year, check, page = 1, filters = null) {
    const params = new URLSearchParams([['page', String(page)], ...filterParams(check, filters)]);
    return `/validate/graph/${year}/diagnostics/${check}?${params}`;
}

/** Adresse de l'export CSV de toute une liste, avec le même filtre. */
export function exportUrl(year, check, filters = null) {
    const params = new URLSearchParams(filterParams(check, filters)).toString();
    return `/validate/graph/${year}/diagnostics/${check}.csv${params ? `?${params}` : ''}`;
}

/** Adresse JSON des attributs d'un élément. */
export function featureUrl(year, kind, id) {
    return `/validate/graph/${year}/features/${kind}/${id}`;
}

/**
 * Élément à décrire après un clic sur la carte : type pour /features/{kind}/{id}.
 *
 * @param {string} layer couche affichée
 * @param {string} sourceLayer calque de la tuile (`edges` ou `nodes`)
 * @param {Record<string, unknown>} properties
 * @returns {{ kind: string, id: number } | null}
 */
export function featureRequest(layer, sourceLayer, properties) {
    const id = Number(properties?.id);
    if (!Number.isInteger(id) || id <= 0) return null;
    const kinds = {
        topology: { edges: 'edge', nodes: 'node' },
        stations: { edges: 'station_edge', nodes: 'station' },
        lines: { edges: 'edge', nodes: 'stop' },
    };
    const kind = kinds[layer]?.[sourceLayer];
    return kind ? { kind, id } : null;
}

/** Listes de diagnostics de chaque couche (la liste des arêtes anormales sert aux deux premières). */
export const CHECKS_BY_LAYER = {
    topology: ['islands', 'isolated_nodes', 'edges', 'junctions'],
    stations: ['station_islands', 'stations_without_edge', 'edges'],
    lines: ['fragmented_lines'],
};

/**
 * Ce qu'une ligne d'une liste de diagnostics montre sur la carte : une emprise (île, ligne en
 * morceaux) ou un point, et l'élément à décrire.
 *
 * @param {string} check
 * @param {Record<string, any>} item
 * @returns {{ bbox?: number[], point?: number[], feature?: { kind: string, id: number } }}
 */
export function itemTarget(check, item) {
    const point =
        Number.isFinite(item?.lng) && Number.isFinite(item?.lat) ? [item.lng, item.lat] : undefined;
    switch (check) {
        case 'islands':
        case 'station_islands':
        case 'fragmented_lines':
            return item?.bbox ? { bbox: item.bbox } : {};
        case 'isolated_nodes':
            return { point, feature: { kind: 'node', id: item.id } };
        case 'stations_without_edge':
            return { point, feature: { kind: 'station', id: item.id } };
        case 'edges':
            return {
                point,
                feature: { kind: item.layer === 'stations' ? 'station_edge' : 'edge', id: item.id },
            };
        case 'junctions':
            // Raccord et saut sont des arêtes ; une extrémité écartée est un nœud.
            return {
                point,
                feature: { kind: item.type === 'spread' ? 'node' : 'edge', id: item.id },
            };
        default:
            return {};
    }
}

/**
 * Résumé d'un élément de liste qui n'a pas de fiche (île, île des gares, ligne en morceaux),
 * affiché dans le panneau après « Voir sur la carte » : titre et lignes, comme detailRows.
 *
 * @param {string} check
 * @param {Record<string, any>} item
 * @param {(key: string, opts?: Record<string, unknown>) => string} t
 * @param {string} lang
 * @returns {{ title: string, rows: DetailRow[] } | null}
 */
export function itemSummary(check, item, t, lang) {
    const L = (key) => t(`graph_admin.details.${key}`);
    const num = (n) => (n == null ? '' : Number(n).toLocaleString(lang));
    const d = (m) => formatDistance(m, lang);
    /** @type {DetailRow[]} */
    const rows = [];
    const add = (key, parts) => {
        const list = parts.filter((p) => p && p.text !== '' && p.text != null);
        if (list.length) rows.push({ label: L(key), parts: list });
    };
    const lineLinks = (lines) =>
        (lines ?? []).map((l) => ({ text: lineText(l), href: lineHref(l) }));
    switch (check) {
        case 'islands':
            add('nodes', [{ text: num(item.nodes) }]);
            add('edges', [{ text: num(item.edges) }]);
            add('length', [{ text: d(item.length_m) }]);
            add('stations_count', [{ text: num(item.stations) }]);
            add('lines', lineLinks(item.lines));
            return { title: t('graph_admin.details.kinds.island', { id: item.id }), rows };
        case 'station_islands':
            add('stations_count', [{ text: num(item.stations) }]);
            add('edges', [{ text: num(item.edges) }]);
            add('length', [{ text: d(item.length_m) }]);
            add(
                'station',
                (item.sample ?? []).map((s) => ({ text: s.name, href: stationHref(s) })),
            );
            return { title: t('graph_admin.details.kinds.station_island', { id: item.id }), rows };
        case 'fragmented_lines':
            if (item.line) add('line', [{ text: lineText(item.line), href: lineHref(item.line) }]);
            add('pieces', [{ text: num(item.pieces) }]);
            add('edges', [{ text: num(item.edges) }]);
            add('length', [{ text: d(item.length_m) }]);
            return {
                title: t('graph_admin.details.kinds.fragmented_line', {
                    name: item.line ? lineText(item.line) : item.line_id,
                }),
                rows,
            };
        default:
            return null;
    }
}

/**
 * Emprise élargie pour un recadrage : une île d'un seul point ou très petite reste lisible.
 *
 * @param {number[]} bbox [ouest, sud, est, nord]
 * @param {number} [minSpan] écart minimal en degrés (0,01 ° ≈ 1 km)
 * @returns {[[number, number], [number, number]]}
 */
export function paddedBounds(bbox, minSpan = 0.01) {
    const [w, s, e, n] = bbox;
    const padLng = Math.max(0, (minSpan - (e - w)) / 2);
    const padLat = Math.max(0, (minSpan - (n - s)) / 2);
    return [
        [w - padLng, s - padLat],
        [e + padLng, n + padLat],
    ];
}

/**
 * Distance lisible : centimètres sous 1 m, mètres sous 10 km (sans décimale au-delà de 10 m),
 * kilomètres au-delà.
 *
 * @param {number | null | undefined} meters
 * @param {string} lang
 */
export function formatDistance(meters, lang) {
    if (meters === null || meters === undefined || !Number.isFinite(Number(meters))) return '';
    const m = Number(meters);
    const fmt = (value, digits) =>
        value.toLocaleString(lang, { maximumFractionDigits: digits, minimumFractionDigits: 0 });
    if (Math.abs(m) >= 10_000) return `${fmt(m / 1000, 1)} km`;
    if (Math.abs(m) >= 10) return `${fmt(Math.round(m), 0)} m`;
    return `${fmt(m, 2)} m`;
}

/** Point kilométrique : « 498,273 » (km, trois décimales), à la manière des documents du rail. */
export function formatPk(meters, lang) {
    if (meters === null || meters === undefined || !Number.isFinite(Number(meters))) return '';
    return (Number(meters) / 1000).toLocaleString(lang, {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

/**
 * Options de la visite Inertia qui change l'ère ou la couche : la page garde son état (carte,
 * cadrage, fiche, liste choisie) et ne recharge que les propriétés qui changent.
 */
export const VISIT_OPTIONS = Object.freeze({
    preserveState: true,
    preserveScroll: true,
    only: ['year', 'missing_era', 'layer', 'summary'],
});

/**
 * Ce que montre la zone de la liste : `error`, `loading` (rien encore à montrer pour cette
 * liste) ou `table` ; `busy` quand une autre page se charge alors que la précédente reste
 * affichée (le bouton de pagination garde ainsi le focus).
 *
 * @param {{ check: string } | null} list dernière page reçue
 * @param {string} check liste choisie
 * @param {string} state idle, loading, ready ou error
 * @returns {{ view: 'error' | 'loading' | 'table', busy: boolean }}
 */
export function listView(list, check, state) {
    if (state === 'error') return { view: 'error', busy: false };
    if (!list || list.check !== check) return { view: 'loading', busy: state === 'loading' };
    return { view: 'table', busy: state === 'loading' };
}

/**
 * Compte de la liste affichée : « 18 sur 25 correspondent au filtre » (pluriel choisi sur le nombre
 * lui-même, `value` est le nombre mis en forme) ou « 25 au total ».
 * @param {{ total: number, filtered: number }} list
 * @param {boolean} filtering
 * @param {(key: string, opts?: Record<string, unknown>) => string} t
 * @param {(n: number) => string} num
 */
export function listCount(list, filtering, t, num) {
    return filtering
        ? t('graph_admin.checks.filtered', {
              count: list.filtered,
              value: num(list.filtered),
              total: num(list.total),
          })
        : t('graph_admin.checks.total', { total: num(list.total) });
}

/**
 * Texte de la zone d'état (role="status") de la liste : chargement de la page, erreur, puis le compte
 * ou le message de liste vide, pour qu'un lecteur d'écran entende le résultat du filtre (RGAA 7.5).
 * @param {{ busy: boolean, error: boolean, page: number, list: { total: number, filtered: number, items: unknown[] } | null, filtering: boolean }} state
 * @param {(key: string, opts?: Record<string, unknown>) => string} t
 * @param {(n: number) => string} num
 */
export function listStatus({ busy, error, page, list, filtering }, t, num) {
    if (busy) return t('graph_admin.checks.loading_page', { page });
    if (error) return t('graph_admin.checks.error');
    if (!list) return '';
    if ((list.items ?? []).length === 0)
        return t(filtering ? 'graph_admin.checks.empty_filtered' : 'graph_admin.checks.empty');
    return listCount(list, filtering, t, num);
}

/**
 * Requêtes dont seule la dernière compte (fiches d'éléments) : `next()` abandonne la précédente
 * et rend le signal de la nouvelle ; `isCurrent(signal)` dit si une réponse est encore attendue.
 */
export function latestRequest() {
    /** @type {AbortController | null} */
    let current = null;
    return {
        next() {
            current?.abort();
            current = new AbortController();
            return current.signal;
        },
        cancel() {
            current?.abort();
            current = null;
        },
        /** @param {AbortSignal} signal */
        isCurrent(signal) {
            return current !== null && current.signal === signal && !signal.aborted;
        },
    };
}

/** Paramètres d'adresse de la page (ère et couche choisies), pour un lien partageable. */
export function pageQuery(year, layer) {
    const params = new URLSearchParams();
    if (year) params.set('era', String(year));
    if (layer && layer !== 'topology') params.set('layer', layer);
    const query = params.toString();
    return query ? `/validate/graph?${query}` : '/validate/graph';
}

/**
 * @typedef {{ text: string, href?: string, lang?: string }} DetailPart
 * @typedef {{ label: string, parts: DetailPart[] }} DetailRow
 */

const lineHref = (line) => (line?.slug ? `/lines/${line.slug}` : undefined);
const stationHref = (station) => (station?.slug ? `/stations/${station.slug}` : undefined);
const lineText = (line) => (line?.code ? `${line.name} (${line.code})` : (line?.name ?? ''));

/**
 * Titre de la fiche d'un élément (« Arête 7263942 », « Gare Volvic »).
 *
 * @param {Record<string, any>} f réponse de /features/{kind}/{id}
 * @param {(key: string, opts?: Record<string, unknown>) => string} t
 */
export function detailTitle(f, t) {
    const name = f.kind === 'stop' ? f.station?.name : f.name;
    return t(`graph_admin.details.kinds.${f.kind}`, { id: f.id, name: name ?? f.id });
}

/**
 * Lignes de la fiche d'un élément, dans l'ordre d'affichage : libellé et valeur (texte, liens).
 * Les champs absents ne donnent pas de ligne.
 *
 * @param {Record<string, any>} f réponse de /features/{kind}/{id}
 * @param {(key: string, opts?: Record<string, unknown>) => string} t
 * @param {string} lang
 * @returns {DetailRow[]}
 */
export function detailRows(f, t, lang) {
    const L = (key) => t(`graph_admin.details.${key}`);
    const d = (m) => formatDistance(m, lang);
    const yesNo = (v) => L(v ? 'yes' : 'no');
    /** @type {DetailRow[]} */
    const rows = [];
    const add = (key, parts) => {
        const list = (Array.isArray(parts) ? parts : [parts]).filter(
            (p) => p && p.text !== '' && p.text != null,
        );
        if (list.length) rows.push({ label: L(key), parts: list });
    };
    const text = (value) => ({ text: value == null ? '' : String(value) });

    add('id', text(f.id));
    switch (f.kind) {
        case 'node':
            add('type', text(t(`graph_admin.node.${f.node_kind}`)));
            if (f.station) add('station', { text: f.station.name, href: stationHref(f.station) });
            add('degree', text(f.degree));
            if (f.spread_m != null) add('spread', text(d(f.spread_m)));
            add(
                'edges_at_node',
                (f.edges ?? []).map((e) => ({
                    text: [
                        e.line ? lineText(e.line) : t('graph_admin.edge.connector'),
                        d(e.length_m),
                        e.offset_m
                            ? t('graph_admin.details.offset', { distance: d(e.offset_m) })
                            : '',
                    ]
                        .filter(Boolean)
                        .join(', '),
                    href: lineHref(e.line),
                })),
            );
            break;
        case 'edge':
            if (f.line) add('line', { text: lineText(f.line), href: lineHref(f.line) });
            if (f.connector) add('type', text(L('connector')));
            add(
                'nodes',
                text(
                    t('graph_admin.details.nodes_value', {
                        from: f.from_node_id,
                        to: f.to_node_id,
                    }),
                ),
            );
            add('length', text(d(f.length_m)));
            if (f.measured_m != null) add('measured', text(d(f.measured_m)));
            // Sans tracé : l'écart que l'arête fait sauter entre ses deux nœuds.
            if (f.nodes_gap_m != null) add('nodes_gap', text(d(f.nodes_gap_m)));
            if (f.segment_id != null) {
                add(
                    'pk',
                    text(
                        f.pk
                            ? t('graph_admin.details.pk_range', {
                                  from: formatPk(f.pk.start_m, lang),
                                  to: formatPk(f.pk.end_m, lang),
                              })
                            : L('pk_missing'),
                    ),
                );
                add('segment', text(f.segment_id));
            }
            add('source', text(f.source));
            if (f.osm_way_id) {
                add('osm_way', {
                    text: String(f.osm_way_id),
                    href: `https://www.openstreetmap.org/way/${f.osm_way_id}`,
                });
            }
            if (f.source_trace_id) add('trace', text(f.source_trace_id));
            add('traffic', text(f.traffic_type));
            add('network', text(f.network));
            if (f.gauge_mm)
                add('gauge', text(t('graph_admin.details.gauge_value', { mm: f.gauge_mm })));
            if (f.heritage) add('heritage', text(L('yes')));
            break;
        case 'station_edge':
            add('from_to', [
                { text: f.from?.name ?? '', href: stationHref(f.from) },
                { text: f.to?.name ?? '', href: stationHref(f.to) },
            ]);
            add('length', text(d(f.length_m)));
            add('direct', text(d(f.direct_m)));
            add(
                'lines',
                (f.lines ?? []).map((l) => ({
                    text: `${lineText(l)}, ${d(l.length_m)}`,
                    href: lineHref(l),
                })),
            );
            add('skipped', text(f.skipped_count));
            if (f.non_passenger_m) add('non_passenger', text(d(f.non_passenger_m)));
            if (f.heritage_m) add('heritage_length', text(d(f.heritage_m)));
            if (f.gap_joins) add('gap_joins', text(f.gap_joins));
            break;
        case 'station':
            add('station', { text: f.name, href: stationHref(f) });
            add('status', text(f.status ?? L('none')));
            if (f.uncertain) add('uncertain', text(L('yes')));
            add('uic', text(f.uic_code));
            add('node', text(f.node_id ?? L('none')));
            add(
                'neighbours',
                (f.neighbours ?? []).length
                    ? [
                          ...f.neighbours.map((n) => ({
                              text: `${n.name}, ${d(n.length_m)}`,
                              href: stationHref(n),
                          })),
                          ...(f.neighbour_count > f.neighbours.length
                              ? [
                                    text(
                                        t('graph_admin.details.neighbours_more', {
                                            count: f.neighbour_count - f.neighbours.length,
                                        }),
                                    ),
                                ]
                              : []),
                      ]
                    : [text(L('none'))],
            );
            if (f.provenance && typeof f.provenance === 'object') {
                add(
                    'provenance',
                    Object.entries(f.provenance).map(([k, v]) => text(`${k} : ${v}`)),
                );
            }
            break;
        case 'stop':
            add('line', { text: lineText(f.line), href: lineHref(f.line) });
            add('station', { text: f.station?.name ?? '', href: stationHref(f.station) });
            add(
                'position',
                text(
                    t('graph_admin.details.position_value', {
                        position: f.position,
                        count: f.stops,
                    }),
                ),
            );
            add('pk', text(formatPk(f.pk_m, lang)));
            add('status', text(f.status));
            add('terminal', text(yesNo(f.terminal)));
            break;
        default:
            break;
    }
    return rows;
}

/**
 * Chiffres clés de la couche affichée, pour le résumé en tête de page.
 *
 * @param {Record<string, any> | null} summary résumé du rapport (topology, stations, lines)
 * @param {string} layer
 * @returns {{ key: string, value: number | string }[]}
 */
export function layerFigures(summary, layer) {
    const s = summary?.[layer];
    if (!s) return [];
    if (layer === 'topology') {
        return [
            { key: 'nodes', value: s.nodes },
            { key: 'edges', value: s.edges },
            { key: 'islands', value: s.islands },
            { key: 'spread', value: s.spread_junctions },
        ];
    }
    if (layer === 'stations') {
        return [
            { key: 'stations', value: s.stations },
            { key: 'station_edges', value: s.edges },
            { key: 'islands', value: s.islands },
            { key: 'without_edge', value: s.without_edge },
        ];
    }
    return [
        { key: 'lines', value: s.lines },
        { key: 'stops', value: s.stops },
        { key: 'fragmented', value: s.fragmented },
        { key: 'stops_off_graph', value: s.stops_off_graph },
    ];
}
