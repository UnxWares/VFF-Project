<script>
    import maplibregl from 'maplibre-gl';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { stationColorExpr, stationFillExpr, VELORAIL_DASH } from '$lib/railStyle.js';
    import {
        CATEGORY_STYLES,
        STRIP_ORDER,
        categorySwatch,
        formatKm,
        phaseFeatureCollection,
        portionsBounds,
    } from '$lib/linePhases.js';

    /**
     * Carte de la fiche ligne : la ligne ENTIÈRE à la phase choisie (LinePhases). Toutes
     * les portions sont chargées une fois ; changer de phase ne fait que recolorer.
     * Les portions modifiées par la phase sont soulignées d'un halo. Gares : leur état à
     * la décennie de la phase (station_eras), absentes si elles n'existaient pas encore.
     */
    let {
        portions = [],
        phase = null,
        stations = [],
        eraYear = null,
        isCurrent = false,
        height = '460px',
    } = $props();

    let mapContainer = $state();
    let map = null;
    let loaded = $state(false);

    /** @returns {string | import('maplibre-gl').StyleSpecification} */
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
            layers: [
                {
                    id: 'osm',
                    type: 'raster',
                    source: 'osm-raster',
                    paint: { 'raster-saturation': -0.6, 'raster-opacity': 0.85 },
                },
            ],
            glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
        };
    }

    /** Gares à la décennie de la phase (état de station_eras ; aujourd'hui pour l'ère courante). */
    function stationFeatures() {
        const features = [];
        for (const s of stations ?? []) {
            const status = isCurrent ? (s.status_today ?? s.eras?.[eraYear]) : s.eras?.[eraYear];
            if (!status || s.lng == null) continue;
            features.push({
                type: 'Feature',
                properties: { name: s.name, slug: s.slug, status },
                geometry: { type: 'Point', coordinates: [s.lng, s.lat] },
            });
        }
        return { type: 'FeatureCollection', features };
    }

    const legend = $derived(
        STRIP_ORDER.filter((cat) => (phase?.length_by_category?.[cat] ?? 0) > 0).map((cat) => ({
            cat,
            m: phase.length_by_category[cat],
        })),
    );

    const width = (base) => [
        'interpolate',
        ['linear'],
        ['zoom'],
        4,
        base,
        8,
        base * 1.5,
        12,
        base * 2.4,
    ];

    onMount(() => {
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(),
            center: [2.5, 47.0],
            zoom: 5.5,
            attributionControl: { compact: true },
            cooperativeGestures: true,
            locale: {
                'CooperativeGesturesHandler.WindowsHelpText': $i18n.t(
                    'lines.phases.gesture_windows',
                ),
                'CooperativeGesturesHandler.MacHelpText': $i18n.t('lines.phases.gesture_mac'),
                'CooperativeGesturesHandler.MobileHelpText': $i18n.t('lines.phases.gesture_mobile'),
            },
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.FullscreenControl(), 'top-right');

        map.on('load', () => {
            map.addSource('vff-line', {
                type: 'geojson',
                data: phaseFeatureCollection(portions, phase),
            });
            // Halo des portions qui changent à cette phase.
            map.addLayer({
                id: 'vff-line-changed',
                type: 'line',
                source: 'vff-line',
                filter: ['==', ['get', 'changed'], true],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': ['get', 'color'],
                    'line-width': width(11),
                    'line-opacity': 0.28,
                    'line-blur': 2,
                },
            });
            const dashed = [
                ['gone', CATEGORY_STYLES.dismantled.dash, 2],
                ['closed', CATEGORY_STYLES.closed.dash, 2.2],
                ['construction', CATEGORY_STYLES.construction.dash, 2.2],
                ['velorail', VELORAIL_DASH, 2.4],
            ];
            for (const [layer, dash, w] of dashed) {
                map.addLayer({
                    id: `vff-line-${layer}`,
                    type: 'line',
                    source: 'vff-line',
                    filter: ['==', ['get', 'layer'], layer],
                    paint: {
                        'line-color': ['get', 'color'],
                        'line-width': width(w),
                        'line-dasharray': dash,
                        'line-opacity': 0.9,
                    },
                });
            }
            map.addLayer({
                id: 'vff-line-solid',
                type: 'line',
                source: 'vff-line',
                filter: ['==', ['get', 'layer'], 'solid'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': ['get', 'color'],
                    // LGV plus épaisse, comme sur la carte principale.
                    'line-width': [
                        'interpolate',
                        ['linear'],
                        ['zoom'],
                        ...[
                            [4, 2.5],
                            [8, 3.75],
                            [12, 6],
                        ].flatMap(([z, w]) => [z, ['*', w, ['case', ['get', 'thick'], 1.6, 1]]]),
                    ],
                    'line-opacity': 0.95,
                },
            });

            map.addSource('vff-stations', { type: 'geojson', data: stationFeatures() });
            map.addLayer({
                id: 'vff-stations',
                type: 'circle',
                source: 'vff-stations',
                paint: {
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 5, 3.5, 10, 6],
                    'circle-color': stationFillExpr(),
                    'circle-stroke-color': stationColorExpr(),
                    'circle-stroke-width': 2.2,
                },
            });
            map.addLayer({
                id: 'vff-stations-labels',
                type: 'symbol',
                source: 'vff-stations',
                minzoom: 8,
                layout: {
                    'text-field': ['get', 'name'],
                    'text-size': 12,
                    'text-offset': [0, 1.1],
                    'text-anchor': 'top',
                    // OpenFreeMap sert Noto Sans (Open Sans → 404 sur les glyphes).
                    'text-font': ['Noto Sans Bold'],
                },
                paint: {
                    'text-color': '#1a1a1a',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 2,
                },
            });
            map.on('click', 'vff-stations', (e) => {
                const slug = e.features?.[0]?.properties?.slug;
                if (slug) router.visit(`/stations/${slug}`);
            });
            map.on('mouseenter', 'vff-stations', () => (map.getCanvas().style.cursor = 'pointer'));
            map.on('mouseleave', 'vff-stations', () => (map.getCanvas().style.cursor = ''));

            const bounds = portionsBounds(portions);
            if (bounds) map.fitBounds(bounds, { padding: 36, maxZoom: 11, duration: 0 });
            loaded = true;
        });
    });

    // Changement de phase : on recolore (pas de rechargement de la carte ni de recadrage).
    $effect(() => {
        const data = phaseFeatureCollection(portions, phase);
        const st = stationFeatures();
        if (!loaded || !map) return;
        map.getSource('vff-line')?.setData(data);
        map.getSource('vff-stations')?.setData(st);
    });

    onDestroy(() => {
        map?.remove();
        map = null;
    });
</script>

<div class="phase-map" style="height: {height};">
    <div class="map" bind:this={mapContainer}></div>
    {#if legend.length}
        <ul class="legend" aria-label={$i18n.t('lines.phases.legend')}>
            {#each legend as item (item.cat)}
                <li>
                    <i style={categorySwatch(item.cat)}></i>
                    {$i18n.t(CATEGORY_STYLES[item.cat]?.labelKey ?? 'lines.categories.unknown')}
                    <span class="km">{formatKm(item.m, $i18n.language ?? 'fr')}</span>
                </li>
            {/each}
        </ul>
    {/if}
</div>

<style>
    .phase-map {
        position: relative;
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        border: 1px solid var(--vff-border);
        background: var(--vff-bg-muted);
    }
    .map {
        width: 100%;
        height: 100%;
    }
    .legend {
        position: absolute;
        left: 10px;
        bottom: 10px;
        z-index: 2;
        margin: 0;
        padding: 0.5rem 0.7rem;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        max-width: calc(100% - 20px);
        background: rgba(255, 255, 255, 0.94);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
        font-family: var(--vff-font-body);
        font-size: 0.78rem;
        color: #1f2328;
    }
    @media (max-width: 600px) {
        .legend {
            top: 10px;
            bottom: auto;
            max-width: calc(100% - 64px);
        }
    }
    .legend li {
        font-size: 12.5px;
        display: grid;
        grid-template-columns: 20px 1fr auto;
        align-items: center;
        gap: 0.45rem;
    }
    .legend i {
        width: 20px;
        border-radius: 2px;
    }
    .legend .km {
        font-variant-numeric: tabular-nums;
        color: #555a64;
        padding-left: 0.6rem;
    }
</style>
