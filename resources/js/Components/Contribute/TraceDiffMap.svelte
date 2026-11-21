<script>
    import maplibregl from '$lib/maplibre.js';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { addHistoricBasemapControl } from '$lib/historicBasemapControl.js';
    import { boundsOf, portionColor } from '$lib/traceGeometry.js';

    /**
     * Diff visuel avant / après d'une contribution de tracé (modération).
     *
     * Props :
     *   portions : portions proposées (payload.portions)
     *   before   : géométrie GeoJSON des tracés remplacés, figée à la proposition
     *   current  : géométrie actuelle de la ligne ({ segments, traces }) - l'état
     *              « avant » vivant, qui a pu évoluer depuis la proposition
     *   basemap  : fond historique utilisé par le contributeur (affiché par défaut)
     */
    let {
        portions = [],
        before = null,
        current = null,
        basemap = null,
        height = '380px',
    } = $props();

    let container = $state();
    let map = null;
    let view = $state('overlay'); // overlay | before | after

    const EMPTY = { type: 'FeatureCollection', features: [] };

    function afterFC() {
        return {
            type: 'FeatureCollection',
            features: portions.map((p, i) => ({
                type: 'Feature',
                properties: { color: portionColor(i), key: p.key },
                geometry: p.geometry,
            })),
        };
    }

    function endsFC() {
        return {
            type: 'FeatureCollection',
            features: portions.flatMap((p, i) => {
                const c = p.geometry?.coordinates ?? [];
                return c.length
                    ? [c[0], c[c.length - 1]].map((pt) => ({
                          type: 'Feature',
                          properties: { color: portionColor(i) },
                          geometry: { type: 'Point', coordinates: pt },
                      }))
                    : [];
            }),
        };
    }

    function linesOf(g) {
        if (!g) return [];
        if (g.type === 'LineString') return [g.coordinates];
        if (g.type === 'MultiLineString') return g.coordinates;
        return [];
    }

    function applyView(v) {
        if (!map?.getLayer('diff-after')) return;
        const vis = (id, on) => map.setLayoutProperty(id, 'visibility', on ? 'visible' : 'none');
        vis('diff-current', v !== 'after');
        vis('diff-before', v !== 'after');
        vis('diff-after', v !== 'before');
        vis('diff-after-ends', v !== 'before');
    }

    $effect(() => applyView(view));

    onMount(() => {
        map = new maplibregl.Map({
            container,
            style: {
                version: 8,
                sources: {
                    'osm-raster': {
                        type: 'raster',
                        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                        tileSize: 256,
                        maxzoom: 19,
                        attribution: $i18n.t('common.osm_attribution', {
                            link: '<a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        }),
                    },
                },
                layers: [{ id: 'osm', type: 'raster', source: 'osm-raster' }],
            },
            center: [2.5, 46.8],
            zoom: 5,
            attributionControl: { compact: true },
            dragRotate: false,
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        addHistoricBasemapControl(map, 'top-right', { value: basemap ?? 'none' });

        map.on('load', () => {
            const currentFC = {
                type: 'FeatureCollection',
                features: [
                    ...(current?.segments?.features ?? []),
                    ...(current?.traces?.features ?? []),
                ],
            };
            map.addSource('diff-current', { type: 'geojson', data: currentFC });
            map.addSource('diff-before', {
                type: 'geojson',
                data: before ? { type: 'Feature', properties: {}, geometry: before } : EMPTY,
            });
            map.addSource('diff-after', { type: 'geojson', data: afterFC() });
            map.addSource('diff-after-ends', { type: 'geojson', data: endsFC() });

            map.addLayer({
                id: 'diff-current',
                type: 'line',
                source: 'diff-current',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#38bdf8',
                    'line-width': ['interpolate', ['linear'], ['zoom'], 6, 4, 14, 11],
                    'line-opacity': 0.45,
                },
            });
            map.addLayer({
                id: 'diff-before',
                type: 'line',
                source: 'diff-before',
                paint: {
                    'line-color': '#dc2626',
                    'line-width': 3,
                    'line-dasharray': [2, 1.5],
                    'line-opacity': 0.9,
                },
            });
            map.addLayer({
                id: 'diff-after',
                type: 'line',
                source: 'diff-after',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': ['get', 'color'], 'line-width': 4 },
            });
            map.addLayer({
                id: 'diff-after-ends',
                type: 'circle',
                source: 'diff-after-ends',
                paint: {
                    'circle-radius': 5,
                    'circle-color': '#ffffff',
                    'circle-stroke-color': ['get', 'color'],
                    'circle-stroke-width': 2.5,
                },
            });
            applyView(view);

            const b = boundsOf([
                ...portions.map((p) => p.geometry?.coordinates ?? []),
                ...linesOf(before),
            ]);
            if (b) map.fitBounds(b, { padding: 40, duration: 0, maxZoom: 15 });
        });
    });

    onDestroy(() => {
        map?.remove();
        map = null;
    });
</script>

<div class="diff">
    <div class="diff-bar">
        <div class="views" role="group" aria-label={$i18n.t('editor.diff.label')}>
            {#each ['overlay', 'before', 'after'] as v (v)}
                <button
                    type="button"
                    class:active={view === v}
                    aria-pressed={view === v}
                    onclick={() => (view = v)}
                >
                    {$i18n.t(`editor.diff.${v}`)}
                </button>
            {/each}
        </div>
        <div class="legend">
            <span
                ><i class="sw thick" style:--c="#38bdf8" aria-hidden="true"></i>{$i18n.t(
                    'editor.diff.current',
                )}</span
            >
            {#if before}<span
                    ><i class="sw dashed" style:--c="#dc2626" aria-hidden="true"></i>{$i18n.t(
                        'editor.diff.replaced',
                    )}</span
                >{/if}
            <span
                ><i class="sw" style:--c="#ff6500" aria-hidden="true"></i>{$i18n.t(
                    'editor.diff.proposed',
                )}</span
            >
        </div>
    </div>
    <div class="map" bind:this={container} style:height></div>
</div>

<style>
    .diff {
        min-width: 0;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
    }
    .diff-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px 14px;
        padding: var(--vff-space-2) var(--vff-space-3);
        background: var(--vff-bg-subtle);
        border-bottom: 1px solid var(--vff-border);
    }
    .views {
        display: inline-flex;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
    }
    .views button {
        min-height: 32px;
        padding: 4px 14px;
        background: var(--vff-bg);
        border: 0;
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .views button + button {
        border-left: 1px solid var(--vff-border-control);
    }
    .views button:hover:not(.active) {
        background: var(--vff-bg-muted);
    }
    .views button.active {
        background: var(--vff-accent);
        color: var(--vff-on-accent);
    }
    .views button:focus-visible {
        outline-offset: -3px;
        outline-color: var(--vff-text);
    }
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 14px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .legend span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .sw {
        display: inline-block;
        width: 18px;
        border-top: 3px solid var(--c);
    }
    .sw.dashed {
        border-top-style: dashed;
    }
    .sw.thick {
        border-top-width: 6px;
        opacity: 0.6;
    }
    .map {
        width: 100%;
    }
    /* Contrôles MapLibre aux couleurs du thème (clair et sombre). */
    .diff :global(.maplibregl-ctrl-attrib),
    .diff :global(.maplibregl-ctrl-group) {
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
    }
    .diff :global(.maplibregl-ctrl-attrib a) {
        color: var(--vff-link);
    }
    :global([data-theme='dark']) .diff :global(.maplibregl-ctrl-group button .maplibregl-ctrl-icon),
    :global([data-theme='dark']) .diff :global(.maplibregl-ctrl-attrib-button) {
        filter: invert(1);
    }
</style>
