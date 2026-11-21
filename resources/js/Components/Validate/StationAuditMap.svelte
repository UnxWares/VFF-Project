<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { loadMaplibre } from '$lib/loadMaplibre.js';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { auditBounds, trackCollection } from '$lib/stationAudit.js';

    /**
     * Carte d'audit d'une gare (page /validate/audit/stations) : la position publiée par VFF
     * (repère « VFF »), un repère numéroté par source (le numéro de la liste des positions ; contour
     * en tirets pour une source grossière, repère pâle pour une position recopiée) et les voies de
     * ses lignes à moins de 2 km. Cadrage sur l'ensemble des positions. Les repères ne sont pas lus
     * par les lecteurs d'écran : la liste des positions, à côté, en est l'équivalent textuel.
     *
     * Props : `station` (fiche JSON de la gare), `positions` (positions numérotées, voir
     * numberedPositions), `label` (nom de la région de la carte).
     */
    /** @type {{ station: Record<string, any>, positions: Array<Record<string, any>>, label: string }} */
    let { station, positions, label } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));

    let container = $state();
    let map = null;
    let maplibre = null;
    let loaded = $state(false);
    let failed = $state(false);
    let destroyed = false;
    /** Repères affichés, retirés avant chaque nouvelle fiche. */
    let markers = [];

    const EMPTY = { type: 'FeatureCollection', features: [] };
    const trackColor = (dark) => (dark ? '#c3ccd6' : '#3d4652');

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
                    // Fond pâle et désaturé : les repères et les voies doivent ressortir.
                    paint: {
                        'raster-saturation': -0.9,
                        'raster-opacity': 0.6,
                        'raster-brightness-max': theme.isDark ? 0.55 : 1,
                    },
                },
            ],
        };
    }

    /**
     * Repère HTML : pastille avec un texte court, sans rôle (la liste en donne le détail).
     *
     * @param {string} text
     * @param {string[]} classes
     */
    function markerElement(text, classes) {
        const el = document.createElement('span');
        el.className = ['audit-marker', ...classes].join(' ');
        el.textContent = text;
        el.setAttribute('aria-hidden', 'true');
        return el;
    }

    /** Remplace repères et voies par ceux de la fiche affichée, puis recadre. */
    function draw(current, list) {
        for (const m of markers) m.remove();
        markers = [];
        map.getSource('tracks')?.setData(current ? trackCollection(current.tracks) : EMPTY);
        if (!current) return;
        // Sources d'abord, position publiée en dernier : elle reste au-dessus.
        for (const p of list) {
            if (!p.onMap) continue;
            const classes = [`family-${p.family ?? 'other'}`];
            if (!p.precise) classes.push('coarse');
            if (p.derived) classes.push('derived');
            markers.push(
                new maplibre.Marker({ element: markerElement(String(p.n), classes) })
                    .setLngLat([p.lon, p.lat])
                    .addTo(map),
            );
        }
        if (Number.isFinite(current.lon) && Number.isFinite(current.lat)) {
            markers.push(
                new maplibre.Marker({
                    element: markerElement(t('audit_stations.map.retained_marker'), ['retained']),
                })
                    .setLngLat([current.lon, current.lat])
                    .addTo(map),
            );
        }
        const bounds = auditBounds(current);
        if (bounds) map.fitBounds(bounds, { padding: 56, maxZoom: 17, duration: 0 });
    }

    onMount(async () => {
        try {
            maplibre = await loadMaplibre();
        } catch {
            failed = true;
            return;
        }
        if (destroyed || !container) return;
        map = new maplibre.Map({
            container,
            style: baseStyle(),
            center: [station?.lon ?? 2.4, station?.lat ?? 46.6],
            zoom: 15,
            attributionControl: { compact: true },
            locale: {
                'NavigationControl.ZoomIn': t('audit_stations.map.zoom_in'),
                'NavigationControl.ZoomOut': t('audit_stations.map.zoom_out'),
                'FullscreenControl.Enter': t('audit_stations.map.fullscreen_enter'),
                'FullscreenControl.Exit': t('audit_stations.map.fullscreen_exit'),
                'AttributionControl.ToggleAttribution': t('audit_stations.map.attribution'),
                'AttributionControl.MapFeedback': t('audit_stations.map.attribution'),
            },
        });
        map.addControl(new maplibre.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibre.FullscreenControl(), 'top-right');
        map.on('load', () => {
            map.addSource('tracks', { type: 'geojson', data: EMPTY });
            map.addLayer({
                id: 'tracks',
                type: 'line',
                source: 'tracks',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': trackColor(theme.isDark),
                    'line-width': ['interpolate', ['linear'], ['zoom'], 12, 2, 17, 5],
                },
            });
            loaded = true;
        });
    });

    // Nouvelle fiche (ou carte prête) : repères, voies et cadrage.
    $effect(() => {
        const current = station;
        const list = positions;
        if (!loaded || !map) return;
        draw(current, list);
    });

    $effect(() => {
        const dark = theme.isDark;
        if (!loaded || !map?.getLayer('osm')) return;
        map.setPaintProperty('osm', 'raster-brightness-max', dark ? 0.55 : 1);
        map.setPaintProperty('tracks', 'line-color', trackColor(dark));
    });

    onDestroy(() => {
        destroyed = true;
        for (const m of markers) m.remove();
        markers = [];
        map?.remove();
        map = null;
    });
</script>

<div class="audit-map" role="region" aria-label={label}>
    <div class="map" bind:this={container}></div>
    {#if failed}
        <p class="state">{t('audit_stations.map.error')}</p>
    {:else if !loaded}
        <p class="state" aria-hidden="true">{t('audit_stations.map.loading')}</p>
    {/if}
</div>

<style>
    .audit-map {
        position: relative;
        height: clamp(320px, 52vh, 600px);
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
    /* Repères créés par MapLibre hors du composant : styles globaux, préfixés. Une pastille ronde
       numérotée par source ; la position publiée est une étiquette « VFF » à fond orange. */
    :global(.audit-marker) {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        padding: 0 6px;
        border: 3px solid var(--marker-color, var(--vff-border-control));
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 700;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        cursor: default;
    }
    :global(.audit-marker.coarse) {
        border-style: dashed;
    }
    :global(.audit-marker.derived) {
        opacity: 0.55;
    }
    :global(.audit-marker.retained) {
        min-width: 40px;
        height: 30px;
        border-color: var(--vff-text);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-accent);
        color: var(--vff-on-accent);
    }
    /* Teinte de chaque famille de sources (le numéro et la liste disent la source). */
    :global(.audit-marker.family-sncf) {
        --marker-color: #b4237a;
    }
    :global(.audit-marker.family-osm) {
        --marker-color: #2f6fb3;
    }
    :global(.audit-marker.family-bdtopo) {
        --marker-color: #2e7d4f;
    }
    :global(.audit-marker.family-wikidata) {
        --marker-color: #8a5a00;
    }
    :global(.audit-marker.family-ohm) {
        --marker-color: #0f766e;
    }
    :global(.audit-marker.family-pk) {
        --marker-color: #5b6470;
    }
    :global(.audit-marker.family-correction) {
        --marker-color: #b42318;
    }
    .audit-map :global(.maplibregl-ctrl-group),
    .audit-map :global(.maplibregl-ctrl-attrib) {
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
    }
</style>
