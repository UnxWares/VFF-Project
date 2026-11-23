<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { hasPmtilesProtocol, loadMaplibre } from '$lib/loadMaplibre.js';
    import { MAP_MAX_ZOOM } from '$lib/basemap.js';
    import { followBasemapTheme, pageMapStyle } from '$lib/mapStyle.svelte.js';
    import { isOfflineView, reportPartialMap } from '$lib/offlineMap.svelte.js';
    import { HERITAGE_COLOR } from '$lib/railStyle.js';
    import { franceBounds, heritagePoints } from '$lib/heritage.js';

    /**
     * Carte sobre du patrimoine ferroviaire protégé : un point par monument affiché (gare ou ouvrage d'art). La liste
     * placée sous la carte en est l'équivalent textuel ; un point ouvre une bulle avec le nom et un lien vers sa
     * fiche dans la liste. La carte suit les filtres de la page.
     *
     * Props : `items` (monuments affichés), `height`.
     */
    /** @type {{ items: Array<any>, height?: string }} */
    let { items, height = '420px' } = $props();

    let container = $state();
    let map = null;
    // Carte dessinée sur le fond libre de VFF (lib/mapStyle.svelte.js) : ses couleurs suivent le thème.
    let onBasemap = false;
    followBasemapTheme(() => (onBasemap ? map : null));
    let maplibre = null;
    let ready = false;
    let releasePartialMap = null;
    let destroyed = false;
    let popup = null;

    const points = $derived(heritagePoints(items));
    const located = $derived(points.features.length);

    function fit(collection) {
        const bounds = franceBounds(collection);
        if (!map || !bounds) return;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        map.fitBounds(bounds, { padding: 40, maxZoom: 9, duration: reduce ? 0 : 400 });
    }

    function showPopup(feature, lngLat) {
        popup?.remove();
        const body = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = feature.properties.name;
        const link = document.createElement('a');
        link.href = `#${feature.properties.id}`;
        link.textContent = String($i18n.t('heritage.map.see_in_list'));
        link.className = 'heritage-popup-link';
        body.append(title, document.createElement('br'), link);
        popup = new maplibre.Popup({ closeButton: true, maxWidth: '260px', offset: 10 })
            .setLngLat(lngLat)
            .setDOMContent(body)
            .addTo(map);
    }

    onMount(async () => {
        maplibre = await loadMaplibre();
        if (destroyed || !container) return;
        const offline = isOfflineView(page.props);
        if (offline) releasePartialMap = reportPartialMap();
        const mapBackground = pageMapStyle(offline, { pmtiles: hasPmtilesProtocol() });
        onBasemap = mapBackground.onBasemap;
        map = new maplibre.Map({
            container,
            style: mapBackground.style,
            center: [2.5, 46.6],
            zoom: 4.6,
            maxZoom: MAP_MAX_ZOOM,
            attributionControl: { compact: true },
            locale: {
                'NavigationControl.ZoomIn': String($i18n.t('heritage.map.zoom_in')),
                'NavigationControl.ZoomOut': String($i18n.t('heritage.map.zoom_out')),
                'FullscreenControl.Enter': String($i18n.t('heritage.map.fullscreen_enter')),
                'FullscreenControl.Exit': String($i18n.t('heritage.map.fullscreen_exit')),
                'AttributionControl.ToggleAttribution': String($i18n.t('heritage.map.attribution')),
                'AttributionControl.MapFeedback': String($i18n.t('heritage.map.attribution')),
            },
        });
        map.addControl(new maplibre.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibre.FullscreenControl(), 'top-right');
        map.on('load', () => {
            map.addSource('heritage', { type: 'geojson', data: points });
            map.addLayer({
                id: 'heritage-points',
                type: 'circle',
                source: 'heritage',
                paint: {
                    'circle-radius': 7,
                    'circle-color': HERITAGE_COLOR,
                    'circle-stroke-color': '#ffffff',
                    'circle-stroke-width': 2,
                },
            });
            map.on('click', 'heritage-points', (e) => {
                const f = e.features?.[0];
                if (f) showPopup(f, f.geometry.coordinates);
            });
            map.on(
                'mouseenter',
                'heritage-points',
                () => (map.getCanvas().style.cursor = 'pointer'),
            );
            map.on('mouseleave', 'heritage-points', () => (map.getCanvas().style.cursor = ''));
            ready = true;
            fit(points);
        });
    });

    // La carte suit les filtres.
    $effect(() => {
        const data = points;
        if (!ready || !map) return;
        popup?.remove();
        map.getSource('heritage')?.setData(data);
        fit(data);
    });

    onDestroy(() => {
        destroyed = true;
        releasePartialMap?.();
        map?.remove();
        map = null;
    });
</script>

<div
    class="heritage-map"
    style:height
    role="region"
    aria-label={$i18n.t('heritage.map.label', { count: located })}
>
    <div class="canvas" bind:this={container}></div>
</div>

<style>
    .heritage-map {
        position: relative;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        background-color: var(--vff-bg-subtle);
    }
    .canvas {
        width: 100%;
        height: 100%;
    }
    :global(.heritage-popup-link) {
        display: inline-block;
        padding-block: 4px;
        color: var(--vff-accent-ink);
    }
    :global(.maplibregl-popup-content) {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        color: #222;
    }
</style>
