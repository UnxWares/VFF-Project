<script>
    import maplibregl from 'maplibre-gl';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';

    /**
     * Mini-carte d'édition : affiche la ligne sélectionnée, l'utilisateur clique
     * 2 fois pour définir start/end PK (via API /pk).
     *
     * Props :
     *   lineSlug : slug ligne pour charger sa géométrie
     *   eraYear : ère cible
     *   onPkStart, onPkEnd : callbacks avec PK en mètres
     */
    let {
        lineSlug = null,
        eraYear = 2030,
        onPkStart = null,
        onPkEnd = null,
        height = '320px',
    } = $props();

    let mapContainer = $state();
    let map = null;
    let mode = $state('start'); // 'start' | 'end'
    let startPk = $state(null);
    let endPk = $state(null);
    let snapStartCoord = null;
    let snapEndCoord = null;

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
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OSM</a>',
                },
            },
            layers: [{ id: 'osm', type: 'raster', source: 'osm-raster' }],
        };
    }

    function formatPk(m) {
        const km = Math.floor(m / 1000);
        return `${km}+${(m % 1000).toString().padStart(3, '0')}`;
    }

    async function loadLineGeometry() {
        if (!lineSlug || !map) return;
        try {
            const res = await fetch(`/api/v1/lines/${lineSlug}/history`);
            const data = await res.json();
            const era = (data.status_by_era ?? []).find((e) => e.year === eraYear);
            if (!era) return;

            // Tile-based : on charge la ligne via MVT en ajoutant un filtre par line_id
            // Plus simple : on récupère les segments via le tile MVT existant.
            // Ici on se contente de positionner la map sur le centre approximatif
            // (à venir : un endpoint /api/v1/lines/{slug}/geojson dédié).
        } catch (_) {}
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

    function renderSelection() {
        if (!map?.isStyleLoaded()) return;

        // Markers
        if (snapStartCoord) {
            if (map.getSource('sel-start')) {
                map.getSource('sel-start').setData({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: snapStartCoord },
                });
            } else {
                map.addSource('sel-start', {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        geometry: { type: 'Point', coordinates: snapStartCoord },
                    },
                });
                map.addLayer({
                    id: 'sel-start-pt',
                    type: 'circle',
                    source: 'sel-start',
                    paint: {
                        'circle-radius': 8,
                        'circle-color': '#22c55e',
                        'circle-stroke-color': '#ffffff',
                        'circle-stroke-width': 2,
                    },
                });
            }
        }
        if (snapEndCoord) {
            if (map.getSource('sel-end')) {
                map.getSource('sel-end').setData({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: snapEndCoord },
                });
            } else {
                map.addSource('sel-end', {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        geometry: { type: 'Point', coordinates: snapEndCoord },
                    },
                });
                map.addLayer({
                    id: 'sel-end-pt',
                    type: 'circle',
                    source: 'sel-end',
                    paint: {
                        'circle-radius': 8,
                        'circle-color': '#ef4444',
                        'circle-stroke-color': '#ffffff',
                        'circle-stroke-width': 2,
                    },
                });
            }
        }
    }

    onMount(() => {
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(),
            center: [2.5, 47.0],
            zoom: 5.5,
            attributionControl: { compact: true },
        });

        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

        map.on('load', async () => {
            // Couche MVT lignes (réutilise notre endpoint VFF)
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
                paint: { 'line-color': '#cccccc', 'line-width': 1.5, 'line-opacity': 0.7 },
            });
            // La ligne sélectionnée en orange épais (filtre par slug)
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

            await loadLineGeometry();
        });

        // Click handler : snap + appel API PK
        map.on('click', async (e) => {
            if (!lineSlug) return;
            const result = await fetchPkFromClick(e.lngLat.lng, e.lngLat.lat);
            if (!result) return;
            if (mode === 'start') {
                startPk = result.pk_m;
                snapStartCoord = [result.snap.lng, result.snap.lat];
                onPkStart?.(result.pk_m);
                mode = 'end';
            } else {
                endPk = result.pk_m;
                snapEndCoord = [result.snap.lng, result.snap.lat];
                onPkEnd?.(result.pk_m);
                mode = 'start';
            }
            renderSelection();
        });
    });

    function resetSelection() {
        startPk = null;
        endPk = null;
        snapStartCoord = null;
        snapEndCoord = null;
        for (const id of ['sel-start-pt', 'sel-end-pt']) {
            if (map?.getLayer(id)) map.removeLayer(id);
        }
        for (const id of ['sel-start', 'sel-end']) {
            if (map?.getSource(id)) map.removeSource(id);
        }
        mode = 'start';
        onPkStart?.(0);
        onPkEnd?.(1000);
    }

    onDestroy(() => {
        map?.remove();
        map = null;
    });
</script>

<div class="edit-map-wrap">
    <div class="toolbar">
        <span class="hint">
            {#if !lineSlug}
                {$i18n.t('maps.editor.edit.hint_need_line')}
            {:else if mode === 'start'}
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <i class="bi bi-1-circle-fill"></i>
                {@html $i18n.t('maps.editor.edit.hint_start')}
            {:else}
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                <i class="bi bi-2-circle-fill"></i>
                {@html $i18n.t('maps.editor.edit.hint_end')}
            {/if}
        </span>
        <button type="button" class="reset" onclick={resetSelection}>
            <i class="bi bi-arrow-counterclockwise"></i>
            {$i18n.t('maps.editor.edit.reset')}
        </button>
    </div>
    <div class="map" bind:this={mapContainer} style="height: {height};"></div>
    {#if startPk !== null || endPk !== null}
        <div class="pk-summary">
            <span
                >{$i18n.t('maps.editor.edit.pk_start')}{colon($i18n.language)}{startPk !== null
                    ? formatPk(startPk)
                    : '-'}</span
            >
            <span
                >{$i18n.t('maps.editor.edit.pk_end')}{colon($i18n.language)}{endPk !== null
                    ? formatPk(endPk)
                    : '-'}</span
            >
            {#if startPk !== null && endPk !== null}
                <span class="dist">
                    ≈ {(Math.round(Math.abs(endPk - startPk) / 100) / 10).toLocaleString(
                        $i18n.language,
                    )} km
                </span>
            {/if}
        </div>
    {/if}
</div>

<style>
    .edit-map-wrap {
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        background-color: var(--vff-bg-elevated);
    }
    .toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--vff-border);
        background-color: var(--vff-bg);
    }
    .hint {
        font-size: 13px;
        color: var(--vff-text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .hint i {
        color: var(--vff-accent);
        font-size: 16px;
    }
    .hint strong {
        color: var(--vff-text);
    }
    .reset {
        background: transparent;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        padding: 4px 10px;
        font-family: var(--vff-font-display);
        font-size: 12px;
        color: var(--vff-text-muted);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .reset:hover {
        color: var(--vff-accent);
        border-color: var(--vff-accent);
    }
    .map {
        width: 100%;
    }
    .pk-summary {
        padding: 10px 14px;
        background-color: var(--vff-bg-muted);
        border-top: 1px solid var(--vff-border);
        display: flex;
        gap: 18px;
        font-family: var(--vff-font-secondary, monospace);
        font-size: 13px;
        color: var(--vff-text);
    }
    .pk-summary .dist {
        margin-left: auto;
        color: var(--vff-accent);
        font-weight: 700;
    }
</style>
