<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { loadMaplibre } from '$lib/loadMaplibre.js';
    import { isOfflineView, neutralMapStyle, reportPartialMap } from '$lib/offlineMap.svelte.js';
    import { theme } from '$lib/stores/theme.svelte.js';

    /**
     * Mini-carte du lieu d'un accident. Position précise (coordonnées de la source, passage à
     * niveau, gare) : une épingle. Commune seule : une zone pâle autour du centre de la commune,
     * sans épingle, pour ne pas faire passer ce centre pour le lieu de l'accident. Région nommée ;
     * l'alternative textuelle (commune, coordonnées, lien OpenStreetMap) est donnée par la page.
     */
    /** @type {{ lat: number, lng: number, name: string, precision?: string|null, height?: string }} */
    let { lat, lng, name, precision = 'exact', height = '340px' } = $props();

    /** Rayon de la zone tracée quand seule la commune est connue, en mètres. */
    const AREA_RADIUS_M = 2500;

    let mapContainer = $state();
    let map = null;

    /** @returns {string | import('maplibre-gl').StyleSpecification} */
    // Fin du signal « carte sur fond neutre » (lib/offlineMap.svelte.js), appelée quand la carte disparaît.
    let releasePartialMap = null;

    /**
     * @param {boolean} offline page lue sans réseau : fond neutre, aucune tuile ni style d'un tiers
     * @returns {string | import('maplibre-gl').StyleSpecification}
     */
    function buildStyle(offline) {
        if (offline) return neutralMapStyle({ dark: theme.isDark });
        const key = import.meta.env.VITE_MAPTILER_KEY;
        if (key) return `https://api.maptiler.com/maps/basic-v2/style.json?key=${key}`;
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
            layers: [{ id: 'osm', type: 'raster', source: 'osm-raster' }],
        };
    }

    /** Polygone circulaire (64 sommets) de rayon donné autour d'un point, en WGS 84. */
    function circle(centerLng, centerLat, radiusM) {
        const coords = [];
        const dLat = radiusM / 111320;
        const dLng = radiusM / (111320 * Math.cos((centerLat * Math.PI) / 180));
        for (let i = 0; i <= 64; i++) {
            const a = (i / 64) * 2 * Math.PI;
            coords.push([centerLng + dLng * Math.cos(a), centerLat + dLat * Math.sin(a)]);
        }
        return {
            type: 'Feature',
            properties: {},
            geometry: { type: 'Polygon', coordinates: [coords] },
        };
    }

    // MapLibre (1 Mo) chargé après l'affichage de la fiche, comme les mini-cartes des fiches ligne et
    // gare : le texte ne l'attend plus.
    let destroyed = false;

    onMount(async () => {
        const maplibregl = await loadMaplibre();
        if (destroyed || !mapContainer) return;
        const vague = precision === 'commune';
        const offline = isOfflineView(page.props);
        if (offline) releasePartialMap = reportPartialMap();
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(offline),
            center: [lng, lat],
            zoom: vague ? 11 : 13.5,
            attributionControl: { compact: true },
            cooperativeGestures: true,
            locale: {
                'NavigationControl.ZoomIn': String($i18n.t('accidents.map.zoom_in')),
                'NavigationControl.ZoomOut': String($i18n.t('accidents.map.zoom_out')),
                'FullscreenControl.Enter': String($i18n.t('accidents.map.fullscreen_enter')),
                'FullscreenControl.Exit': String($i18n.t('accidents.map.fullscreen_exit')),
                'AttributionControl.ToggleAttribution': String(
                    $i18n.t('accidents.map.attribution'),
                ),
                'AttributionControl.MapFeedback': String($i18n.t('accidents.map.attribution')),
            },
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.FullscreenControl(), 'top-right');

        map.on('load', () => {
            if (vague) {
                // Peintures MapLibre : couleurs écrites en dur (exception admise par le guide, section 2).
                map.addSource('area', { type: 'geojson', data: circle(lng, lat, AREA_RADIUS_M) });
                map.addLayer({
                    id: 'area-fill',
                    type: 'fill',
                    source: 'area',
                    paint: { 'fill-color': '#ff6500', 'fill-opacity': 0.12 },
                });
                map.addLayer({
                    id: 'area-line',
                    type: 'line',
                    source: 'area',
                    paint: { 'line-color': '#a94300', 'line-width': 2, 'line-dasharray': [2, 2] },
                });

                return;
            }
            const marker = document.createElement('div');
            marker.className = 'accident-marker';
            marker.setAttribute('role', 'img');
            marker.setAttribute('aria-label', String($i18n.t('accidents.show.map_marker')));
            marker.innerHTML = '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>';
            new maplibregl.Marker({ element: marker, anchor: 'bottom' })
                .setLngLat([lng, lat])
                .addTo(map);
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
    class="mini-map-wrapper"
    style:height
    role="region"
    aria-label={$i18n.t('accidents.show.map_title', { name })}
>
    <div class="mini-map" bind:this={mapContainer}></div>
    {#if precision === 'commune'}
        <p class="area-note">{$i18n.t('accidents.map.area')}</p>
    {/if}
</div>

<style>
    .mini-map-wrapper {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-subtle);
    }
    .mini-map {
        width: 100%;
        height: 100%;
    }
    .area-note {
        position: absolute;
        left: var(--vff-space-3);
        bottom: var(--vff-space-3);
        z-index: var(--vff-z-raised);
        max-width: calc(100% - 2 * var(--vff-space-3));
        margin: 0;
        padding: var(--vff-space-1) var(--vff-space-2);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text);
    }
    :global(.accident-marker) {
        font-size: 34px;
        line-height: 1;
        color: var(--vff-accent-ink);
        cursor: default;
    }
</style>
