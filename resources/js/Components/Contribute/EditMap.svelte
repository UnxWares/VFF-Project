<script>
    import maplibregl from '$lib/maplibre.js';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';
    import { formatKm } from '$lib/traceGeometry.js';
    import { formatPk } from './segmentChanges.js';

    /**
     * Mini-carte d'édition par PK : affiche la ligne choisie, cadrée sur son tracé ; deux clics sur
     * la ligne relèvent le PK de début puis le PK de fin (API /api/v1/lines/{slug}/pk).
     *
     * Props :
     *   lineSlug : slug de la ligne (la page ne monte la carte que si elle est connue)
     *   lineName : nom de la ligne (nom accessible de la carte)
     *   eraYear  : ère de la carte
     *   onPkStart, onPkEnd : rappels avec le PK en mètres (null quand on efface les points)
     */
    let {
        lineSlug = null,
        lineName = '',
        eraYear = 2030,
        onPkStart = null,
        onPkEnd = null,
        height = '340px',
    } = $props();

    let mapContainer = $state();
    let map = null;
    let mode = $state('start'); // 'start' | 'end'
    let startPk = $state(null);
    let endPk = $state(null);

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
                    attribution: $i18n.t('common.osm_attribution', {
                        link: '<a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }),
                },
            },
            layers: [{ id: 'osm', type: 'raster', source: 'osm-raster' }],
        };
    }

    /**
     * Cadre la carte sur le tracé de la ligne et le dessine (même source que l'éditeur de tracé) :
     * une ligne fermée n'apparaît pas dans les tuiles de l'ère actuelle, mais reste à situer.
     */
    async function fitToLine() {
        if (!lineSlug) return;
        try {
            const res = await fetch(`/contribute/trace/lines/${encodeURIComponent(lineSlug)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!map) return;
            const features = [...(data.segments?.features ?? []), ...(data.traces?.features ?? [])];
            if (features.length && !map.getSource('line-geom')) {
                map.addSource('line-geom', {
                    type: 'geojson',
                    data: { type: 'FeatureCollection', features },
                });
                map.addLayer(
                    {
                        id: 'line-geom',
                        type: 'line',
                        source: 'line-geom',
                        layout: { 'line-cap': 'round', 'line-join': 'round' },
                        paint: { 'line-color': '#ff6500', 'line-width': 4, 'line-opacity': 0.85 },
                    },
                    map.getLayer('vff-lines-selected') ? 'vff-lines-selected' : undefined,
                );
            }
            if (data.bbox) {
                map.fitBounds(
                    [
                        [data.bbox[0], data.bbox[1]],
                        [data.bbox[2], data.bbox[3]],
                    ],
                    { padding: 40, duration: 0, maxZoom: 13 },
                );
            }
        } catch (_) {
            /* la carte reste utilisable sans cadrage */
        }
    }

    async function fetchPkFromClick(lng, lat) {
        if (!lineSlug) return null;
        try {
            const url = `/api/v1/lines/${lineSlug}/pk?lat=${lat}&lng=${lng}&era=${eraYear}`;
            const res = await fetch(url);
            if (!res.ok) return null;
            return await res.json();
        } catch (_) {
            return null;
        }
    }

    function setPoint(id, coord, color) {
        if (!map?.isStyleLoaded()) return;
        const data = { type: 'Feature', geometry: { type: 'Point', coordinates: coord } };
        if (map.getSource(id)) {
            map.getSource(id).setData(data);
            return;
        }
        map.addSource(id, { type: 'geojson', data });
        map.addLayer({
            id: `${id}-pt`,
            type: 'circle',
            source: id,
            paint: {
                'circle-radius': 8,
                'circle-color': color,
                'circle-stroke-color': '#ffffff',
                'circle-stroke-width': 2,
            },
        });
    }

    onMount(() => {
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(),
            center: [2.5, 47.0],
            zoom: 5.5,
            attributionControl: { compact: true },
            dragRotate: false,
        });
        map.touchZoomRotate.disableRotation();
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

        map.on('load', () => {
            // Lignes de l'ère (tuiles VFF) et ligne choisie en orange.
            map.addSource('vff-lines', {
                type: 'vector',
                tiles: [`${window.location.origin}/api/v1/eras/${eraYear}/tiles/{z}/{x}/{y}.mvt`],
                minzoom: 0,
                maxzoom: 14,
            });
            map.addLayer({
                id: 'vff-lines-bg',
                type: 'line',
                source: 'vff-lines',
                'source-layer': 'vff-lines',
                paint: { 'line-color': '#7c838c', 'line-width': 1.5, 'line-opacity': 0.7 },
            });
            if (lineSlug) {
                map.addLayer({
                    id: 'vff-lines-selected',
                    type: 'line',
                    source: 'vff-lines',
                    'source-layer': 'vff-lines',
                    filter: ['==', ['get', 'line_slug'], lineSlug],
                    paint: { 'line-color': '#ff6500', 'line-width': 4 },
                });
            }
            fitToLine();
        });

        map.on('click', async (e) => {
            if (!lineSlug) return;
            const result = await fetchPkFromClick(e.lngLat.lng, e.lngLat.lat);
            if (!result) return;
            const coord = [result.snap.lng, result.snap.lat];
            if (mode === 'start') {
                startPk = result.pk_m;
                setPoint('sel-start', coord, '#16a34a');
                onPkStart?.(result.pk_m);
                mode = 'end';
            } else {
                endPk = result.pk_m;
                setPoint('sel-end', coord, '#dc2626');
                onPkEnd?.(result.pk_m);
                mode = 'start';
            }
        });
    });

    function resetSelection() {
        startPk = null;
        endPk = null;
        for (const id of ['sel-start', 'sel-end']) {
            if (map?.getLayer(`${id}-pt`)) map.removeLayer(`${id}-pt`);
            if (map?.getSource(id)) map.removeSource(id);
        }
        mode = 'start';
        onPkStart?.(null);
        onPkEnd?.(null);
    }

    onDestroy(() => {
        map?.remove();
        map = null;
    });
</script>

<div class="edit-map">
    <div class="toolbar">
        <p class="hint" aria-live="polite">
            <span class="step" aria-hidden="true">{mode === 'start' ? '1' : '2'}</span>
            <span>
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html $i18n.t(
                    mode === 'start' ? 'editor.segment.hint_start' : 'editor.segment.hint_end',
                )}
            </span>
        </p>
        <button
            type="button"
            class="vff-btn quiet small"
            onclick={resetSelection}
            disabled={startPk === null && endPk === null}
        >
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            {$i18n.t('editor.segment.reset')}
        </button>
    </div>
    <div
        class="map"
        bind:this={mapContainer}
        style:height
        role="region"
        aria-label={$i18n.t('editor.segment.map_label', { name: lineName })}
    ></div>
    {#if startPk !== null || endPk !== null}
        <p class="pk-summary">
            <span
                ><span class="dot start" aria-hidden="true"></span>{$i18n.t(
                    'editor.segment.pk_start',
                )}{colon($i18n.language)}<strong
                    >{startPk !== null ? formatPk(startPk) : '-'}</strong
                ></span
            >
            <span
                ><span class="dot end" aria-hidden="true"></span>{$i18n.t(
                    'editor.segment.pk_end',
                )}{colon($i18n.language)}<strong>{endPk !== null ? formatPk(endPk) : '-'}</strong
                ></span
            >
            {#if startPk !== null && endPk !== null}
                <span class="dist"
                    >{$i18n.t('editor.segment.length')}{colon($i18n.language)}<strong
                        >{formatKm(Math.abs(endPk - startPk), $i18n.language)}</strong
                    ></span
                >
            {/if}
        </p>
    {/if}
</div>

<style>
    .edit-map {
        min-width: 0;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        background-color: var(--vff-bg-elevated);
    }
    .toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-3);
        padding: var(--vff-space-2) var(--vff-space-3);
        border-bottom: 1px solid var(--vff-border);
    }
    .hint {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
        min-width: 0;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .hint :global(strong) {
        color: var(--vff-text);
    }
    .step {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background-color: var(--vff-accent);
        color: var(--vff-on-accent);
        font-size: var(--vff-fs-xs);
        font-weight: 700;
    }
    .map {
        width: 100%;
    }
    .pk-summary {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-1) var(--vff-space-5);
        margin: 0;
        padding: var(--vff-space-2) var(--vff-space-3);
        border-top: 1px solid var(--vff-border);
        background-color: var(--vff-bg-subtle);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .pk-summary > span {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }
    .pk-summary strong {
        color: var(--vff-text);
    }
    .dot {
        width: 10px;
        height: 10px;
        margin-right: var(--vff-space-2);
        border-radius: 50%;
        border: 2px solid var(--vff-bg);
        box-shadow: 0 0 0 1px var(--vff-border-strong);
    }
    .dot.start {
        background-color: #16a34a;
    }
    .dot.end {
        background-color: #dc2626;
    }
    /* Contrôles MapLibre aux couleurs du thème (clair et sombre). */
    .edit-map :global(.maplibregl-ctrl-attrib),
    .edit-map :global(.maplibregl-ctrl-group) {
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
    }
    .edit-map :global(.maplibregl-ctrl-attrib a) {
        color: var(--vff-link);
    }
    :global([data-theme='dark'])
        .edit-map
        :global(.maplibregl-ctrl-group button .maplibregl-ctrl-icon),
    :global([data-theme='dark']) .edit-map :global(.maplibregl-ctrl-attrib-button) {
        filter: invert(1);
    }
</style>
