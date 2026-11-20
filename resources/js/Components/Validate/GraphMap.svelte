<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { loadMaplibre } from '$lib/loadMaplibre.js';
    import { theme } from '$lib/stores/theme.svelte.js';
    import {
        GRAPH_MIN_ZOOM,
        edgeColorExpr,
        featureRequest,
        nodeColorExpr,
        paddedBounds,
        tileUrl,
    } from '$lib/graphAdmin.js';

    /**
     * Carte du graphe d'une ère (page /validate/graph) : tuiles MVT de la couche choisie (calques
     * `edges` et `nodes`) à partir du zoom GRAPH_MIN_ZOOM, points de la page de diagnostics en
     * cours à toutes les échelles, élément choisi mis en évidence. Un clic sur un élément appelle
     * `onselect({ kind, id })`. La carte n'est pas utilisable au clavier : le tableau des
     * diagnostics de la page en est l'équivalent accessible.
     *
     * Props : `year`, `layer`, `markers` (points [lng, lat] de la liste affichée), `focus`
     * ({ bbox } ou { point }, recadrage demandé), `onselect`.
     */
    /** @type {{ year: number, layer: string, markers?: number[][], focus?: { bbox?: number[], point?: number[] } | null, onselect?: (f: { kind: string, id: number }) => void, label: string }} */
    let { year, layer, markers = [], focus = null, onselect = () => {}, label } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));

    let container = $state();
    let map = null;
    let loaded = $state(false);
    let failed = $state(false);
    let zoom = $state(5.2);
    let destroyed = false;

    const EMPTY = { type: 'FeatureCollection', features: [] };

    /** @returns {import('maplibre-gl').StyleSpecification} */
    function baseStyle() {
        return {
            version: 8,
            sources: {
                'osm-raster': {
                    type: 'raster',
                    tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                    tileSize: 256,
                    maxzoom: 19,
                    attribution:
                        '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                },
            },
            layers: [
                {
                    id: 'osm',
                    type: 'raster',
                    source: 'osm-raster',
                    // Fond pâle et désaturé : le graphe doit ressortir.
                    paint: {
                        'raster-saturation': -0.9,
                        'raster-opacity': 0.6,
                        'raster-brightness-max': theme.isDark ? 0.55 : 1,
                    },
                },
            ],
        };
    }

    /** (Re)crée la source de tuiles et les calques de la couche choisie. */
    function addGraph() {
        for (const id of ['graph-nodes', 'graph-edges']) {
            if (map.getLayer(id)) map.removeLayer(id);
        }
        if (map.getSource('graph')) map.removeSource('graph');
        map.addSource('graph', {
            type: 'vector',
            tiles: [tileUrl(window.location.origin, year, layer)],
            minzoom: GRAPH_MIN_ZOOM,
            maxzoom: 14,
        });
        const before = map.getLayer('diag-points') ? 'diag-points' : undefined;
        map.addLayer(
            {
                id: 'graph-edges',
                type: 'line',
                source: 'graph',
                'source-layer': 'edges',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': edgeColorExpr(layer),
                    // Zoom en tête de l'expression (règle de MapLibre) ; raccords plus épais.
                    'line-width': [
                        'interpolate',
                        ['linear'],
                        ['zoom'],
                        8,
                        ['case', ['==', ['get', 'kind'], 'connector'], 3, 2],
                        14,
                        ['case', ['==', ['get', 'kind'], 'connector'], 7, 5],
                    ],
                },
            },
            before,
        );
        map.addLayer(
            {
                id: 'graph-nodes',
                type: 'circle',
                source: 'graph',
                'source-layer': 'nodes',
                paint: {
                    'circle-color': nodeColorExpr(layer),
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 8, 2.5, 14, 6],
                    'circle-stroke-width': 1,
                    'circle-stroke-color': theme.isDark ? '#0f1419' : '#ffffff',
                },
            },
            before,
        );
    }

    function addOverlays() {
        map.addSource('diag', { type: 'geojson', data: EMPTY });
        map.addLayer({
            id: 'diag-points',
            type: 'circle',
            source: 'diag',
            paint: {
                'circle-radius': 7,
                'circle-color': 'rgba(0,0,0,0)',
                'circle-stroke-width': 2.5,
                'circle-stroke-color': '#dc2626',
            },
        });
        map.addSource('focus', { type: 'geojson', data: EMPTY });
        map.addLayer({
            id: 'focus-area',
            type: 'line',
            source: 'focus',
            filter: ['==', ['geometry-type'], 'Polygon'],
            paint: { 'line-color': '#ff6500', 'line-width': 3, 'line-dasharray': [2, 1] },
        });
        map.addLayer({
            id: 'focus-point',
            type: 'circle',
            source: 'focus',
            filter: ['==', ['geometry-type'], 'Point'],
            paint: {
                'circle-radius': 12,
                'circle-color': 'rgba(0,0,0,0)',
                'circle-stroke-width': 3,
                'circle-stroke-color': '#ff6500',
            },
        });
    }

    function markerData(points) {
        return {
            type: 'FeatureCollection',
            features: (points ?? []).map((p) => ({
                type: 'Feature',
                properties: {},
                geometry: { type: 'Point', coordinates: p },
            })),
        };
    }

    function focusData(target) {
        if (target?.bbox) {
            const [[w, s], [e, n]] = paddedBounds(target.bbox);
            return {
                type: 'Feature',
                properties: {},
                geometry: {
                    type: 'Polygon',
                    coordinates: [
                        [
                            [w, s],
                            [e, s],
                            [e, n],
                            [w, n],
                            [w, s],
                        ],
                    ],
                },
            };
        }
        if (target?.point) {
            return {
                type: 'Feature',
                properties: {},
                geometry: { type: 'Point', coordinates: target.point },
            };
        }
        return EMPTY;
    }

    onMount(async () => {
        let maplibregl;
        try {
            maplibregl = await loadMaplibre();
        } catch {
            failed = true;
            return;
        }
        if (destroyed || !container) return;
        map = new maplibregl.Map({
            container,
            style: baseStyle(),
            center: [2.4, 46.6],
            zoom: 5.2,
            attributionControl: { compact: true },
            locale: {
                'NavigationControl.ZoomIn': t('graph_admin.map.zoom_in'),
                'NavigationControl.ZoomOut': t('graph_admin.map.zoom_out'),
                'FullscreenControl.Enter': t('graph_admin.map.fullscreen_enter'),
                'FullscreenControl.Exit': t('graph_admin.map.fullscreen_exit'),
                'AttributionControl.ToggleAttribution': t('graph_admin.map.attribution'),
                'AttributionControl.MapFeedback': t('graph_admin.map.attribution'),
            },
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.FullscreenControl(), 'top-right');
        map.on('zoomend', () => (zoom = map.getZoom()));
        map.on('load', () => {
            addOverlays();
            addGraph();
            loaded = true;
        });
        map.on('click', (e) => {
            const hits = map.queryRenderedFeatures(
                [
                    [e.point.x - 5, e.point.y - 5],
                    [e.point.x + 5, e.point.y + 5],
                ],
                { layers: ['graph-nodes', 'graph-edges'] },
            );
            // Un nœud l'emporte sur l'arête qu'il coupe.
            const hit = hits.find((h) => h.layer.id === 'graph-nodes') ?? hits[0];
            if (!hit) return;
            const request = featureRequest(layer, hit.sourceLayer, hit.properties);
            if (request) onselect(request);
        });
        map.on('mouseenter', 'graph-edges', () => (map.getCanvas().style.cursor = 'pointer'));
        map.on('mouseenter', 'graph-nodes', () => (map.getCanvas().style.cursor = 'pointer'));
        map.on('mouseleave', 'graph-edges', () => (map.getCanvas().style.cursor = ''));
        map.on('mouseleave', 'graph-nodes', () => (map.getCanvas().style.cursor = ''));
    });

    // Ère ou couche changée : nouvelles tuiles, même cadrage.
    $effect(() => {
        const key = `${year}:${layer}`;
        if (!loaded || !map || !key) return;
        addGraph();
    });

    $effect(() => {
        const data = markerData(markers);
        if (!loaded || !map) return;
        map.getSource('diag')?.setData(data);
    });

    $effect(() => {
        const target = focus;
        if (!loaded || !map) return;
        map.getSource('focus')?.setData(focusData(target));
        if (target?.bbox) {
            map.fitBounds(paddedBounds(target.bbox), { padding: 48, maxZoom: 14 });
        } else if (target?.point) {
            map.flyTo({ center: target.point, zoom: Math.max(map.getZoom(), 15) });
        }
    });

    $effect(() => {
        const dark = theme.isDark;
        if (!loaded || !map?.getLayer('osm')) return;
        map.setPaintProperty('osm', 'raster-brightness-max', dark ? 0.55 : 1);
        map.setPaintProperty('graph-nodes', 'circle-stroke-color', dark ? '#0f1419' : '#ffffff');
    });

    onDestroy(() => {
        destroyed = true;
        map?.remove();
        map = null;
    });
</script>

<div class="graph-map" role="region" aria-label={label}>
    <div class="map" bind:this={container}></div>
    {#if failed}
        <p class="state">{t('graph_admin.map.error')}</p>
    {:else if !loaded}
        <p class="state" aria-hidden="true">{t('graph_admin.map.loading')}</p>
    {:else if zoom < GRAPH_MIN_ZOOM}
        <p class="hint">{t('graph_admin.map.zoom_hint')}</p>
    {/if}
</div>

<style>
    .graph-map {
        position: relative;
        height: clamp(420px, 68vh, 860px);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        background-color: var(--vff-bg-subtle);
    }
    .map {
        width: 100%;
        height: 100%;
    }
    .state,
    .hint {
        position: absolute;
        left: var(--vff-space-3);
        right: var(--vff-space-3);
        margin: 0;
        padding: var(--vff-space-2) var(--vff-space-3);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .state {
        top: var(--vff-space-3);
    }
    .hint {
        bottom: var(--vff-space-6);
        max-width: 46ch;
    }
</style>
