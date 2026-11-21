<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { loadMaplibre } from '$lib/loadMaplibre.js';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { collectionBounds, colorExpression } from '$lib/segmentAudit.js';

    /**
     * Carte des tronçons d'une ligne (page /validate/audit/segments) : un trait par tronçon, coloré
     * selon la note choisie (`check` : `worst` ou l'un des quatre contrôles), tronçon choisi
     * souligné d'un liseré. Cadrage sur l'emprise des tronçons à chaque nouvelle ligne. Un clic
     * sur un tronçon appelle `onselect(segmentId)`. La carte n'est pas utilisable au clavier : la
     * liste des tronçons, sous la carte, en est l'équivalent accessible.
     */
    /** @type {{ collection: { type: string, features: any[] }, check: string, selectedId?: number | null, onselect?: (id: number) => void, label: string }} */
    let { collection, check, selectedId = null, onselect = () => {}, label } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));

    let container = $state();
    let map = null;
    let loaded = $state(false);
    let failed = $state(false);
    let destroyed = false;
    /** Collection affichée : évite de recadrer pour les mêmes données. */
    let shown = null;

    const casingColor = (dark) => (dark ? '#e8ecec' : '#25221e');

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
                    // Fond pâle et désaturé : les notes doivent ressortir.
                    paint: {
                        'raster-saturation': -0.9,
                        'raster-opacity': 0.6,
                        'raster-brightness-max': theme.isDark ? 0.55 : 1,
                    },
                },
            ],
        };
    }

    const selectedFilter = (id) => ['==', ['get', 'segment_id'], id ?? -1];

    function addLayers() {
        map.addSource('segments', { type: 'geojson', data: collection });
        map.addLayer({
            id: 'segments-selected',
            type: 'line',
            source: 'segments',
            filter: selectedFilter(selectedId),
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': casingColor(theme.isDark),
                'line-width': ['interpolate', ['linear'], ['zoom'], 6, 8, 14, 14],
            },
        });
        map.addLayer({
            id: 'segments',
            type: 'line',
            source: 'segments',
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': colorExpression(check, theme.isDark),
                'line-width': ['interpolate', ['linear'], ['zoom'], 6, 3.5, 14, 7],
            },
        });
    }

    function frame(data) {
        const bounds = collectionBounds(data);
        if (bounds) map.fitBounds(bounds, { padding: 48, maxZoom: 15, duration: 0 });
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
        map.on('load', () => {
            addLayers();
            shown = collection;
            frame(collection);
            loaded = true;
        });
        map.on('click', (e) => {
            const hits = map.queryRenderedFeatures(
                [
                    [e.point.x - 6, e.point.y - 6],
                    [e.point.x + 6, e.point.y + 6],
                ],
                { layers: ['segments'] },
            );
            const id = Number(hits[0]?.properties?.segment_id);
            if (Number.isFinite(id)) onselect(id);
        });
        map.on('mouseenter', 'segments', () => (map.getCanvas().style.cursor = 'pointer'));
        map.on('mouseleave', 'segments', () => (map.getCanvas().style.cursor = ''));
    });

    // Nouvelle ligne : nouvelles données et nouveau cadrage. Au premier chargement, le cadrage
    // vient de map.on('load').
    $effect(() => {
        const data = collection;
        if (!loaded || !map || data === shown) return;
        shown = data;
        map.getSource('segments')?.setData(data);
        frame(data);
    });

    $effect(() => {
        const expr = colorExpression(check, theme.isDark);
        if (!loaded || !map?.getLayer('segments')) return;
        map.setPaintProperty('segments', 'line-color', expr);
    });

    $effect(() => {
        const filter = selectedFilter(selectedId);
        if (!loaded || !map?.getLayer('segments-selected')) return;
        map.setFilter('segments-selected', filter);
    });

    $effect(() => {
        const dark = theme.isDark;
        if (!loaded || !map?.getLayer('osm')) return;
        map.setPaintProperty('osm', 'raster-brightness-max', dark ? 0.55 : 1);
        map.setPaintProperty('segments-selected', 'line-color', casingColor(dark));
    });

    onDestroy(() => {
        destroyed = true;
        map?.remove();
        map = null;
    });
</script>

<div class="segment-map" role="region" aria-label={label}>
    <div class="map" bind:this={container}></div>
    {#if failed}
        <p class="state">{t('graph_admin.map.error')}</p>
    {:else if !loaded}
        <p class="state" aria-hidden="true">{t('graph_admin.map.loading')}</p>
    {/if}
</div>

<style>
    .segment-map {
        position: relative;
        height: clamp(320px, 56vh, 680px);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        background-color: var(--vff-bg-subtle);
    }
    .map {
        width: 100%;
        height: 100%;
    }
    .state {
        position: absolute;
        top: var(--vff-space-3);
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
</style>
