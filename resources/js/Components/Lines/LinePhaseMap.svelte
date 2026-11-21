<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import { loadMaplibre } from '$lib/loadMaplibre.js';
    import { loadLinePortions } from '$lib/linePortions.js';
    import { whenNear } from '$lib/whenNear.js';
    import { i18n } from '$lib/i18n.js';
    import { isOfflineView, neutralMapStyle, reportPartialMap } from '$lib/offlineMap.svelte.js';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { formatLocale, tieUnits } from '$lib/typo.js';
    import {
        STATION_STYLES,
        stationColorExpr,
        stationFillExpr,
        VELORAIL_DASH,
    } from '$lib/railStyle.js';
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
     * les portions sont chargées une fois, depuis `portionsUrl` (LinePortionsController), en même
     * temps que MapLibre ; changer de phase ne fait que recolorer.
     * Les portions modifiées par la phase sont soulignées d'un halo. Gares : leur état à
     * la décennie de la phase (station_eras), absentes si elles n'existaient pas encore.
     * La légende explique les traits ET les gares affichés.
     */
    let {
        portionsUrl,
        phase = null,
        stations = [],
        name = '',
        eraYear = null,
        isCurrent = false,
        height = '460px',
    } = $props();

    let mapContainer = $state();
    let map = null;
    let loaded = $state(false);
    // Portions lues à part (géométrie : jamais rendue profondément réactive) ; `failed` : tracé introuvable.
    /** @type {Array<{ length_m: number, geometry: any }>} */
    let portions = $state.raw([]);
    let failed = $state(false);

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));

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
            layers: [
                {
                    id: 'osm',
                    type: 'raster',
                    source: 'osm-raster',
                    paint: {
                        'raster-saturation': -0.6,
                        'raster-opacity': 0.85,
                        // Thème sombre : fond atténué (moins éblouissant), tracé et gares inchangés.
                        'raster-brightness-max': theme.isDark ? 0.78 : 1,
                    },
                },
            ],
            glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
        };
    }

    /** Statut d'une gare à la phase (état de station_eras ; aujourd'hui pour l'ère courante). */
    function statusOf(s) {
        return isCurrent ? (s.status_today ?? s.eras?.[eraYear]) : s.eras?.[eraYear];
    }

    function stationFeatures() {
        const features = [];
        for (const s of stations ?? []) {
            const status = statusOf(s);
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
    // Gares visibles à cette phase, par statut (ordre de la légende de la carte principale).
    const stationLegend = $derived.by(() => {
        const present = new Set(
            (stations ?? [])
                .filter((s) => s.lng != null)
                .map(statusOf)
                .filter(Boolean),
        );
        return STATION_STYLES.filter((s) => present.has(s.key));
    });

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

    // MapLibre (1 Mo) chargé après l'affichage de la fiche : le texte ne l'attend plus. Il ne démarre que
    // quand la carte entre à l'écran ou à la première interaction (lib/whenNear.js) : sur téléphone, la
    // carte est sous la ligne de flottaison et ses tâches longues ne bloquent plus l'ouverture. Le tracé,
    // simple lecture réseau, part tout de suite.
    let destroyed = false;
    /** @type {ReturnType<typeof whenNear> | null} */
    let near = null;

    onMount(async () => {
        const lineworksRequest = loadLinePortions(portionsUrl).catch(() => null);
        near = whenNear(mapContainer);
        await near.promise;
        if (destroyed) return;
        const [maplibregl, lineworks] = await Promise.all([loadMaplibre(), lineworksRequest]);
        if (destroyed || !mapContainer) return;
        if (!lineworks) {
            failed = true;
            return;
        }
        portions = lineworks;
        const offline = isOfflineView(page.props);
        if (offline) releasePartialMap = reportPartialMap();
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(offline),
            center: [2.5, 47.0],
            zoom: 5.5,
            attributionControl: { compact: true },
            cooperativeGestures: true,
            locale: {
                'Map.Title': t('lines.sheet.map.title', { name }),
                'NavigationControl.ZoomIn': t('lines.sheet.map.zoom_in'),
                'NavigationControl.ZoomOut': t('lines.sheet.map.zoom_out'),
                'FullscreenControl.Enter': t('lines.sheet.map.fullscreen_enter'),
                'FullscreenControl.Exit': t('lines.sheet.map.fullscreen_exit'),
                'AttributionControl.ToggleAttribution': t('lines.sheet.map.attribution'),
                'CooperativeGesturesHandler.WindowsHelpText': t('lines.phases.gesture_windows'),
                'CooperativeGesturesHandler.MacHelpText': t('lines.phases.gesture_mac'),
                'CooperativeGesturesHandler.MobileHelpText': t('lines.phases.gesture_mobile'),
            },
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(new maplibregl.FullscreenControl(), 'top-right');

        map.on('load', () => {
            map.addSource('vff-line', {
                type: 'geojson',
                data: phaseFeatureCollection(portions, phase),
            });
            // Halo des portions qui changent à cette phase. Il dépend du zoom : absent quand toute
            // la ligne est à l'écran (zoom 7 et moins, un halo de 11 px y empâtait le tracé), fin
            // et pâle ensuite, puis exactement le rendu d'origine à partir du zoom 11 (largeur
            // width(11), opacité 0,28, flou 2).
            map.addLayer({
                id: 'vff-line-changed',
                type: 'line',
                source: 'vff-line',
                filter: ['==', ['get', 'changed'], true],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': ['get', 'color'],
                    'line-width': [
                        'interpolate',
                        ['linear'],
                        ['zoom'],
                        7,
                        0,
                        9,
                        7,
                        11,
                        23.925,
                        12,
                        26.4,
                    ],
                    'line-opacity': ['interpolate', ['linear'], ['zoom'], 7, 0, 9, 0.14, 11, 0.28],
                    'line-blur': ['interpolate', ['linear'], ['zoom'], 9, 0.5, 11, 2],
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
                    'text-size': 13,
                    // Le nom se place là où il tient (sous, au-dessus ou à côté du point).
                    'text-variable-anchor': ['top', 'bottom', 'left', 'right'],
                    'text-radial-offset': 0.9,
                    'text-justify': 'auto',
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

            // Marges : place pour les noms des terminus, les boutons (à droite) et la légende (en bas).
            const bounds = portionsBounds(portions);
            if (bounds) {
                const narrow = mapContainer.clientWidth < 600;
                map.fitBounds(bounds, {
                    padding: narrow
                        ? { top: 40, right: 56, bottom: 40, left: 40 }
                        : { top: 48, right: 110, bottom: 96, left: 90 },
                    maxZoom: 11,
                    duration: 0,
                });
            }
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

    // Bascule de thème pendant la visite : fond de carte atténué ou non.
    $effect(() => {
        const dark = theme.isDark;
        if (!loaded || !map?.getLayer('osm')) return;
        map.setPaintProperty('osm', 'raster-brightness-max', dark ? 0.78 : 1);
    });

    onDestroy(() => {
        destroyed = true;
        near?.cancel();
        releasePartialMap?.();
        map?.remove();
        map = null;
    });
</script>

<div class="phase-map-wrap">
    <div class="phase-map" style:height>
        <div class="map" bind:this={mapContainer}></div>
        {#if failed}
            <p class="loading" role="status">{t('lines.sheet.map.unavailable')}</p>
        {:else if !loaded}
            <p class="loading" aria-hidden="true">{t('lines.sheet.map.loading')}</p>
        {/if}
    </div>
    {#if legend.length || stationLegend.length}
        <div class="legend" role="group" aria-label={t('lines.phases.legend')}>
            {#if legend.length}
                <ul aria-label={t('lines.sheet.map.legend_line')}>
                    {#each legend as item (item.cat)}
                        <li>
                            <i class="line" style={categorySwatch(item.cat)}></i>
                            <span
                                >{t(
                                    CATEGORY_STYLES[item.cat]?.labelKey ??
                                        'lines.categories.unknown',
                                )}</span
                            >
                            <span class="km">{tieUnits(formatKm(item.m, formatLocale(lang)))}</span>
                        </li>
                    {/each}
                </ul>
            {/if}
            {#if stationLegend.length}
                <ul class="st" aria-label={t('lines.sheet.map.legend_stations')}>
                    {#each stationLegend as s (s.key)}
                        <li>
                            <i
                                class="dot"
                                style:--stroke={s.color}
                                style:--fill={s.fill ?? '#ffffff'}
                            ></i>
                            <span>{t(s.labelKey)}</span>
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>
    {/if}
</div>

<style>
    .phase-map-wrap {
        position: relative;
    }
    .phase-map {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
    }
    .map {
        width: 100%;
        height: 100%;
    }
    .loading {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        pointer-events: none;
    }
    .legend {
        position: absolute;
        left: 10px;
        bottom: 10px;
        z-index: var(--vff-z-raised);
        display: flex;
        flex-direction: column;
        gap: 6px;
        max-width: calc(100% - 20px);
        padding: 8px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.35;
        color: var(--vff-text);
    }
    .legend ul {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .legend ul.st {
        flex-flow: row wrap;
        gap: 4px 12px;
        padding-top: 6px;
        border-top: 1px solid var(--vff-border);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: var(--vff-fs-xs);
    }
    .legend .line {
        flex-shrink: 0;
        width: 20px;
        border-radius: 2px;
    }
    .legend .dot {
        flex-shrink: 0;
        width: 10px;
        height: 10px;
        border: 2.5px solid var(--stroke);
        border-radius: 50%;
        background: var(--fill);
    }
    .legend .km {
        margin-left: auto;
        padding-left: 12px;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    /* Contrôles MapLibre : mêmes fonds, contours et focus que le site, dans les deux thèmes. */
    .phase-map :global(.maplibregl-ctrl-group),
    .phase-map :global(.maplibregl-ctrl-attrib) {
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
    }
    .phase-map :global(.maplibregl-ctrl-group) {
        border: 1px solid var(--vff-border-strong);
        box-shadow: none;
    }
    .phase-map :global(.maplibregl-ctrl-group button + button) {
        border-top-color: var(--vff-border);
    }
    .phase-map :global(.maplibregl-ctrl-attrib a) {
        color: var(--vff-link);
    }
    .phase-map :global(.maplibregl-ctrl button:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
        box-shadow: none;
    }
    :global([data-theme='dark']) .phase-map :global(.maplibregl-ctrl-icon) {
        filter: invert(1);
    }
    /* Téléphone : la légende passe sous la carte (elle masquait le tracé et le crédit). */
    @media (max-width: 600px) {
        .legend {
            position: static;
            max-width: none;
            margin-top: 8px;
            padding: 8px 12px;
            box-shadow: none;
        }
        .legend ul:not(.st) {
            flex-flow: row wrap;
            gap: 4px 12px;
        }
        .legend .km {
            margin-left: 0;
            padding-left: 0;
        }
    }
</style>
