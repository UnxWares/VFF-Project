<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { hasPmtilesProtocol, loadMaplibre } from '$lib/loadMaplibre.js';
    import { MAP_MAX_ZOOM } from '$lib/basemap.js';
    import { followBasemapTheme, pageMapStyle } from '$lib/mapStyle.svelte.js';
    import { isOfflineView, reportPartialMap } from '$lib/offlineMap.svelte.js';
    import { STATUS_COLOR, TRAFFIC_COLOR } from '$lib/railStyle.js';
    import { STATION_STATUS_COLOR } from '$components/Stations/stationStatus.js';

    /**
     * Petite carte sobre d'une commune : contour, tracés des lignes (en service en orange, fermées en
     * gris pointillé) et gares. Région nommée ; l'alternative textuelle est la liste des gares et des
     * lignes de la page. Contrôles MapLibre traduits.
     *
     * @type {{ map: { contour: object | null, stations: Array<any>, lines: Array<any> }, name: string, height?: string }}
     */
    let { map: data, name, height = '100%' } = $props();

    let mapContainer = $state();
    let map = null;
    // Carte dessinée sur le fond libre de VFF (lib/mapStyle.svelte.js) : ses couleurs suivent le thème.
    let onBasemap = false;
    followBasemapTheme(() => (onBasemap ? map : null));
    let destroyed = false;
    let releasePartialMap = null;

    /**
     * Emprise [[ouest, sud], [est, nord]] de tout ce que la carte montre (contour, tracés, gares).
     *
     * @returns {[[number, number], [number, number]] | null}
     */
    function bounds() {
        let w = Infinity;
        let s = Infinity;
        let e = -Infinity;
        let n = -Infinity;
        const add = (c) => {
            if (typeof c[0] === 'number') {
                w = Math.min(w, c[0]);
                e = Math.max(e, c[0]);
                s = Math.min(s, c[1]);
                n = Math.max(n, c[1]);
            } else {
                c.forEach(add);
            }
        };
        if (data.contour) add(data.contour.coordinates);
        data.lines.forEach((l) => add(l.geometry.coordinates));
        data.stations.forEach((st) => add([st.lng, st.lat]));
        return w === Infinity
            ? null
            : [
                  [w, s],
                  [e, n],
              ];
    }

    onMount(async () => {
        const maplibregl = await loadMaplibre();
        if (destroyed || !mapContainer) return;
        const offline = isOfflineView(page.props);
        if (offline) releasePartialMap = reportPartialMap();
        const box = bounds();
        const mapBackground = pageMapStyle(offline, { pmtiles: hasPmtilesProtocol() });
        onBasemap = mapBackground.onBasemap;
        map = new maplibregl.Map({
            container: mapContainer,
            style: mapBackground.style,
            ...(box
                ? { bounds: box, fitBoundsOptions: { padding: 32, maxZoom: 15 } }
                : { zoom: 10 }),
            maxZoom: MAP_MAX_ZOOM,
            attributionControl: { compact: true },
            locale: {
                'NavigationControl.ZoomIn': String($i18n.t('communes.map.zoom_in')),
                'NavigationControl.ZoomOut': String($i18n.t('communes.map.zoom_out')),
                'FullscreenControl.Enter': String($i18n.t('communes.map.fullscreen_enter')),
                'FullscreenControl.Exit': String($i18n.t('communes.map.fullscreen_exit')),
                'AttributionControl.ToggleAttribution': String($i18n.t('communes.map.attribution')),
                'AttributionControl.MapFeedback': String($i18n.t('communes.map.attribution')),
            },
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.FullscreenControl(), 'top-right');

        map.on('load', () => {
            if (data.contour) {
                map.addSource('contour', {
                    type: 'geojson',
                    data: { type: 'Feature', properties: {}, geometry: data.contour },
                });
                map.addLayer({
                    id: 'contour-fill',
                    type: 'fill',
                    source: 'contour',
                    paint: { 'fill-color': '#ff6500', 'fill-opacity': 0.06 },
                });
                map.addLayer({
                    id: 'contour-line',
                    type: 'line',
                    source: 'contour',
                    paint: { 'line-color': '#78716c', 'line-width': 1.5, 'line-opacity': 0.8 },
                });
            }
            map.addSource('lines', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: data.lines.map((l) => ({
                        type: 'Feature',
                        properties: { state: l.state },
                        geometry: l.geometry,
                    })),
                },
            });
            map.addLayer({
                id: 'lines-closed',
                type: 'line',
                source: 'lines',
                filter: ['==', ['get', 'state'], 'closed'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': STATUS_COLOR.dismantled,
                    'line-width': 2.5,
                    'line-dasharray': [2, 1.5],
                },
            });
            map.addLayer({
                id: 'lines-open',
                type: 'line',
                source: 'lines',
                filter: ['==', ['get', 'state'], 'open'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': TRAFFIC_COLOR.mixed, 'line-width': 3.5 },
            });
            map.addSource('stations', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: data.stations.map((st) => ({
                        type: 'Feature',
                        properties: {
                            name: st.name,
                            color:
                                STATION_STATUS_COLOR[st.status] ??
                                STATION_STATUS_COLOR.declassified,
                        },
                        geometry: { type: 'Point', coordinates: [st.lng, st.lat] },
                    })),
                },
            });
            map.addLayer({
                id: 'stations-points',
                type: 'circle',
                source: 'stations',
                paint: {
                    'circle-radius': 6,
                    'circle-color': '#ffffff',
                    'circle-stroke-color': ['get', 'color'],
                    'circle-stroke-width': 3,
                },
            });
            map.addLayer({
                id: 'stations-labels',
                type: 'symbol',
                source: 'stations',
                minzoom: 11,
                layout: {
                    'text-field': ['get', 'name'],
                    'text-size': 13,
                    'text-offset': [0, 1.2],
                    'text-anchor': 'top',
                    'text-font': ['Noto Sans Regular'],
                },
                paint: {
                    'text-color': '#444',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 2,
                },
            });
        });
    });

    onDestroy(() => {
        destroyed = true;
        releasePartialMap?.();
        map?.remove();
        map = null;
    });
</script>

<div
    class="commune-map"
    style:height
    role="region"
    aria-label={$i18n.t('communes.map.title', { name })}
>
    <div class="surface" bind:this={mapContainer}></div>
</div>

<style>
    .commune-map {
        position: relative;
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        border: 1px solid var(--vff-border-strong);
        background-color: var(--vff-bg-subtle);
    }
    .surface {
        width: 100%;
        height: 100%;
    }
</style>
