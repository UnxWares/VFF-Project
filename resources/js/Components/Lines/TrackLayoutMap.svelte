<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { hasPmtilesProtocol, loadMaplibre } from '$lib/loadMaplibre.js';
    import { MAP_MAX_ZOOM } from '$lib/basemap.js';
    import { followBasemapTheme, pageMapStyle } from '$lib/mapStyle.svelte.js';
    import { isOfflineView, reportPartialMap } from '$lib/offlineMap.svelte.js';
    import { pkLabel } from '$lib/trackLayout.js';

    /**
     * Onglet « Carte » du schéma des voies : le tracé de la ligne aujourd'hui (GET /api/v1/lines/{line}/geojson,
     * qui applique la règle des droits des tracés) et l'objet choisi dans le tableau, placé par son PK
     * (GET /api/v1/lines/{line}/at-pk). MapLibre n'est chargé qu'à l'ouverture de l'onglet.
     *
     * @type {{ slug: string, lineName: string, pk: number|null }}
     */
    let { slug, lineName, pk } = $props();

    const t = (k, o) => String($i18n.t(k, o));

    /** @type {HTMLDivElement|undefined} */
    let container = $state();
    let status = $state(/** @type {'loading'|'ready'|'error'} */ ('loading'));
    let withheld = $state(false);
    /** @type {any} */
    let map = null;
    /** @type {any} */
    let maplibre = null;
    /** @type {any} */
    let marker = null;
    let ready = $state(false);
    let onBasemap = false;
    let destroyed = false;
    let releasePartialMap = null;
    let token = 0;
    followBasemapTheme(() => (onBasemap && ready ? map : null));

    const base = $derived(`/api/v1/lines/${encodeURIComponent(slug)}`);

    function bounds(features) {
        let w = 180;
        let s = 90;
        let e = -180;
        let n = -90;
        for (const f of features) {
            const lines =
                f.geometry?.type === 'MultiLineString'
                    ? f.geometry.coordinates
                    : [f.geometry?.coordinates ?? []];
            for (const line of lines) {
                for (const [x, y] of line) {
                    w = Math.min(w, x);
                    e = Math.max(e, x);
                    s = Math.min(s, y);
                    n = Math.max(n, y);
                }
            }
        }
        return w <= e ? /** @type {[number, number, number, number]} */ ([w, s, e, n]) : null;
    }

    onMount(async () => {
        try {
            const [lib, res] = await Promise.all([
                loadMaplibre(),
                fetch(`${base}/geojson`, { headers: { Accept: 'application/geo+json' } }),
            ]);
            if (destroyed || !container) return;
            if (!res.ok) throw new Error(String(res.status));
            const geojson = await res.json();
            maplibre = lib;
            const offline = isOfflineView(page.props);
            if (offline) releasePartialMap = reportPartialMap();
            const background = pageMapStyle(offline, { pmtiles: hasPmtilesProtocol() });
            onBasemap = background.onBasemap;
            const box = bounds(geojson.features ?? []);
            map = new lib.Map({
                container,
                style: background.style,
                bounds: box ?? undefined,
                fitBoundsOptions: { padding: 32 },
                center: box ? undefined : [2.4, 46.6],
                zoom: box ? undefined : 5,
                maxZoom: MAP_MAX_ZOOM,
                attributionControl: { compact: true },
                cooperativeGestures: true,
                locale: {
                    'NavigationControl.ZoomIn': t('tracks.map.zoom_in'),
                    'NavigationControl.ZoomOut': t('tracks.map.zoom_out'),
                    'AttributionControl.ToggleAttribution': t('tracks.map.attribution'),
                    'AttributionControl.MapFeedback': t('tracks.map.attribution'),
                },
            });
            map.addControl(new lib.NavigationControl({ showCompass: false }), 'top-right');
            map.on('load', () => {
                // Peintures MapLibre : couleurs écrites en dur (exception admise par le guide, section 2).
                map.addSource('line', { type: 'geojson', data: geojson });
                map.addLayer({
                    id: 'line-casing',
                    type: 'line',
                    source: 'line',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: { 'line-color': '#fcfbf8', 'line-width': 7 },
                });
                map.addLayer({
                    id: 'line',
                    type: 'line',
                    source: 'line',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: { 'line-color': '#d65000', 'line-width': 4 },
                });
                ready = true;
                status = 'ready';
            });
        } catch {
            if (!destroyed) status = 'error';
        }
    });

    // Objet choisi : placé par son PK sur le tracé, sauf là où la source du tracé ne le permet pas.
    $effect(() => {
        const at = pk;
        if (!ready || at === null) return;
        const mine = ++token;
        fetch(`${base}/at-pk?pk_m=${Math.round(at)}`, { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                if (mine !== token || !map || !json) return;
                withheld = Boolean(json.position_withheld) || json.lat == null;
                if (withheld) {
                    marker?.remove();
                    marker = null;
                    return;
                }
                if (!marker) {
                    const el = document.createElement('div');
                    el.className = 'tl-marker';
                    el.setAttribute('role', 'img');
                    marker = new maplibre.Marker({ element: el });
                }
                marker
                    .getElement()
                    .setAttribute('aria-label', t('tracks.map.marker', { pk: pkLabel(at) }));
                marker.setLngLat([json.lng, json.lat]).addTo(map);
                map.easeTo({ center: [json.lng, json.lat], zoom: Math.max(map.getZoom(), 13) });
            })
            .catch(() => {});
    });

    onDestroy(() => {
        destroyed = true;
        releasePartialMap?.();
        map?.remove();
        map = null;
    });
</script>

<div class="map-wrap" role="region" aria-label={t('tracks.map.label', { name: lineName })}>
    <div class="map" bind:this={container}></div>
    {#if status !== 'ready'}
        <p class="state" role={status === 'error' ? 'alert' : 'status'}>
            {t(status === 'error' ? 'tracks.map.error' : 'tracks.map.loading')}
        </p>
    {/if}
</div>
<p class="note" aria-live="polite">
    {pk === null ? t('tracks.map.pick') : withheld ? t('tracks.map.withheld') : ''}
</p>

<style>
    .map-wrap {
        position: relative;
        height: clamp(320px, 60vh, 640px);
        overflow: hidden;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
    }
    .map {
        width: 100%;
        height: 100%;
    }
    .state {
        position: absolute;
        inset: auto var(--vff-space-3) var(--vff-space-3);
        margin: 0;
        color: var(--vff-text-muted);
    }
    .note {
        min-height: 1.5em;
        margin: var(--vff-space-2) 0 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-xs);
    }
    :global(.tl-marker) {
        width: 18px;
        height: 18px;
        border: 3px solid #fcfbf8;
        border-radius: 50%;
        background: #d65000;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
    }
</style>
