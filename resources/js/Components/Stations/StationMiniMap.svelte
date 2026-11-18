<script>
    import maplibregl from 'maplibre-gl';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Mini-carte centrée sur une gare, avec ses voisines. Région nommée (le titre dit ce que montre
     * la carte) ; l'alternative textuelle (position, lien OpenStreetMap, liste des voisines) est
     * fournie à côté par la page. Contrôles MapLibre traduits.
     */
    let { station, neighbors = [], height = '380px' } = $props();

    let mapContainer = $state();
    let map = null;

    function buildStyle() {
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
            glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
        };
    }

    onMount(() => {
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(),
            center: [station.lng, station.lat],
            zoom: 12,
            attributionControl: { compact: true },
            locale: {
                'NavigationControl.ZoomIn': String($i18n.t('stations.map.zoom_in')),
                'NavigationControl.ZoomOut': String($i18n.t('stations.map.zoom_out')),
                'FullscreenControl.Enter': String($i18n.t('stations.map.fullscreen_enter')),
                'FullscreenControl.Exit': String($i18n.t('stations.map.fullscreen_exit')),
                'AttributionControl.ToggleAttribution': String($i18n.t('stations.map.attribution')),
                'AttributionControl.MapFeedback': String($i18n.t('stations.map.attribution')),
            },
        });

        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.FullscreenControl(), 'top-right');

        map.on('load', () => {
            // Marker principal (gare courante)
            const main = document.createElement('div');
            main.className = 'station-main-marker';
            main.setAttribute('role', 'img');
            main.setAttribute('aria-label', String($i18n.t('stations.map.this_station')));
            main.innerHTML = '<i class="bi bi-pin-map-fill" aria-hidden="true"></i>';
            new maplibregl.Marker({ element: main, anchor: 'bottom' })
                .setLngLat([station.lng, station.lat])
                .addTo(map);

            // Voisins (cercles plus discrets)
            if (neighbors.length) {
                map.addSource('neighbors', {
                    type: 'geojson',
                    data: {
                        type: 'FeatureCollection',
                        features: neighbors.map((n) => ({
                            type: 'Feature',
                            properties: { name: n.name, slug: n.slug },
                            geometry: { type: 'Point', coordinates: [n.lng, n.lat] },
                        })),
                    },
                });
                map.addLayer({
                    id: 'neighbors-pts',
                    type: 'circle',
                    source: 'neighbors',
                    paint: {
                        'circle-radius': 5,
                        'circle-color': '#ffffff',
                        'circle-stroke-color': '#ff8b35',
                        'circle-stroke-width': 2,
                    },
                });
                map.addLayer({
                    id: 'neighbors-labels',
                    type: 'symbol',
                    source: 'neighbors',
                    minzoom: 11,
                    layout: {
                        'text-field': ['get', 'name'],
                        'text-size': 11,
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
            }
        });
    });

    onDestroy(() => {
        map?.remove();
        map = null;
    });
</script>

<div
    class="mini-map-wrapper"
    style:height
    role="region"
    aria-label={$i18n.t('stations.map.title', { name: station.name })}
>
    <div class="mini-map" bind:this={mapContainer}></div>
</div>

<style>
    .mini-map-wrapper {
        position: relative;
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        border: 1px solid var(--vff-border-strong);
        background-color: var(--vff-bg-subtle);
    }
    .mini-map {
        width: 100%;
        height: 100%;
    }
    :global(.station-main-marker) {
        font-size: 36px;
        color: var(--vff-accent);
        line-height: 1;
        cursor: default;
    }
</style>
