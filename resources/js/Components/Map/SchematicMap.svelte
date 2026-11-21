<script>
    import maplibregl from '$lib/maplibre.js';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount, untrack } from 'svelte';
    import { get } from 'svelte/store';
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { MAP_GLYPHS } from '$lib/offlineMap.svelte.js';
    import { mapControlsLocale } from '$lib/mapControls.js';
    import { formatNumber } from '$lib/typo.js';
    import {
        SCHEMA_AXIS_LAYERS,
        SCHEMA_LABEL_LAYERS,
        SCHEMA_LAYERS,
        SCHEMA_NODE_LAYERS,
        SCHEMA_SOURCE,
        SCHEMA_STATION_LAYERS,
        SCHEMA_TICK_IMAGE,
        axisFilter,
        cityLabelMinZoom,
        drawerLine,
        lineIdOf,
        paletteFor,
        schematicLayers,
        schematicUrl,
        selectedFilter,
        stationPosition,
        summaryParts,
        tickImage,
        withLabelOffsets,
    } from '$lib/schematicMap.js';
    import { escapeHtml, stationPopupHtml } from './lineInfo.js';

    /**
     * Vue ferroviaire de /maps : le réseau en service d'une époque en plan schématique (axes à 0, 45
     * ou 90 degrés, gares en traits, sans fond de carte). Le plan est calculé par le serveur
     * (GET /api/v1/eras/{year}/schematic.geojson) ; le navigateur ne fait que le dessiner.
     *
     * Mêmes usages que la carte (RailMap) : clic sur un axe = fiche de la ligne (onLineClick, même
     * fiche latérale), clic sur une gare = bulle avec le lien vers sa fiche, recherche d'une gare =
     * vol vers sa place sur le plan, catégories masquées par la légende (`hidden`). Le plan reste une
     * image : un résumé chiffré le décrit aux lecteurs d'écran (aria-describedby du canevas).
     *
     * Props : era, currentEra, hidden, selectedLine (slug), onLineClick, flyTarget ({ lng, lat,
     * zoom, key, slug }), fitBounds, fitPadding, fullscreenContainer, children (légende, fiche).
     */
    let {
        era = 2030,
        currentEra = 2030,
        hidden = [],
        selectedLine = null,
        onLineClick = null,
        flyTarget = null,
        fitBounds = null,
        fitPadding = null,
        fullscreenContainer = null,
        children = null,
    } = $props();

    const uid = $props.id();
    const descriptionId = `${uid}-description`;

    let container = $state();
    let wrapper = $state();
    /** @type {import('maplibre-gl').Map | null} */
    let map = null;
    let popup = null;
    // Style prêt (source et couches ajoutées) : les effets ne touchent pas la carte avant.
    let ready = $state(false);
    // loading | ready | error
    let phase = $state('loading');
    let data = $state.raw(null);
    // Thème dont les couches sont peintes (évite de tout repeindre au premier passage).
    let paintedDark = null;
    // Vol demandé avant l'ouverture de la vue : ignoré (la recherche a déjà eu lieu sur la carte).
    let flownKey = untrack(() => flyTarget?.key ?? null);

    const EMPTY = { type: 'FeatureCollection', features: [] };

    const t = (key, params = {}) => String($i18n.t(key, params));
    const lang = $derived($i18n.language);

    const summary = $derived.by(() => {
        const parts = summaryParts(data);
        if (!parts) return '';
        const when =
            era === currentEra
                ? t('maps.schema.summary_today')
                : t('maps.schema.summary_at', { year: era });
        const list = parts.parts
            .map((p) =>
                t('maps.schema.summary_part', {
                    km: formatNumber(p.km, lang),
                    category: t(`maps.schema.categories.${p.key}`),
                }),
            )
            .join(', ');
        return t('maps.schema.summary', {
            when,
            km: formatNumber(parts.total, lang),
            parts: list,
            stations: formatNumber(parts.stations, lang),
        });
    });

    function addLayers(dark) {
        const palette = paletteFor(dark);
        map.setPaintProperty('vff-schema-background', 'background-color', palette.background);
        const image = tickImage(palette.ink);
        if (map.hasImage(SCHEMA_TICK_IMAGE)) map.updateImage(SCHEMA_TICK_IMAGE, image);
        else map.addImage(SCHEMA_TICK_IMAGE, image, { pixelRatio: 2 });
        for (const id of [
            ...Object.values(SCHEMA_LAYERS),
            ...SCHEMA_NODE_LAYERS,
            ...SCHEMA_LABEL_LAYERS,
        ]) {
            if (map.getLayer(id)) map.removeLayer(id);
        }
        const selected = lineIdOf(selectedLine, data?.lines);
        const width = container.clientWidth;
        for (const layer of schematicLayers({ dark, hidden: [...hidden], selected, width })) {
            map.addLayer(/** @type {import('maplibre-gl').AddLayerObject} */ (layer));
        }
        paintedDark = dark;
    }

    // Entités sous le pointeur, avec quelques pixels de marge (axes fins, doigt sur un écran tactile).
    function featureAt(point, layers, px) {
        /** @type {[import('maplibre-gl').PointLike, import('maplibre-gl').PointLike]} */
        const box = [
            [point.x - px, point.y - px],
            [point.x + px, point.y + px],
        ];
        return map.queryRenderedFeatures(box, {
            layers: layers.filter((id) => map.getLayer(id)),
        })[0];
    }

    function openStation(feature) {
        const props = feature.properties;
        const href = props.slug ? `/stations/${encodeURIComponent(props.slug)}` : null;
        const link = href
            ? `<a class="rail-popup-cta" href="${href}">${escapeHtml(t('maps.drawer.view_station'))}</a>`
            : '';
        popup?.remove();
        popup = new maplibregl.Popup({ closeButton: true, closeOnClick: true, maxWidth: '300px' })
            .setLngLat(feature.geometry.coordinates)
            .setHTML(stationPopupHtml(props, link))
            .addTo(map);
        popup
            .getElement()
            ?.querySelector('.rail-popup-cta')
            ?.addEventListener('click', (ev) => {
                ev.preventDefault();
                router.visit(href);
            });
    }

    function onClick(e) {
        const station = featureAt(e.point, SCHEMA_STATION_LAYERS, 6);
        if (station) {
            openStation(station);
            return;
        }
        const axis = featureAt(e.point, SCHEMA_AXIS_LAYERS, 4);
        if (!axis || typeof onLineClick !== 'function') return;
        onLineClick(drawerLine(axis.properties, data?.lines, era));
        // Téléphone : la fiche s'ouvre en bas ; le point cliqué remonte dans la partie visible.
        if (container.clientWidth <= 600) {
            map.easeTo({
                center: e.lngLat,
                offset: [0, -container.clientHeight * 0.25],
                duration: 300,
            });
        }
    }

    function onMove(e) {
        const hit =
            featureAt(e.point, SCHEMA_STATION_LAYERS, 6) ??
            featureAt(e.point, SCHEMA_AXIS_LAYERS, 4);
        map.getCanvas().style.cursor = hit ? 'pointer' : '';
    }

    onMount(() => {
        const dark = theme.isDark;
        const w = container.clientWidth;
        const h = container.clientHeight;
        const padding = typeof fitPadding === 'function' ? fitPadding(w, h) : (fitPadding ?? 24);
        map = new maplibregl.Map({
            container,
            style: {
                version: 8,
                sources: {},
                layers: [
                    {
                        id: 'vff-schema-background',
                        type: 'background',
                        paint: { 'background-color': paletteFor(dark).background },
                    },
                ],
                glyphs: MAP_GLYPHS,
            },
            ...(fitBounds ? { bounds: fitBounds, fitBoundsOptions: { padding } } : {}),
            minZoom: 3,
            maxZoom: 13,
            // Un plan se lit à plat et nord en haut : ni rotation ni inclinaison.
            dragRotate: false,
            pitchWithRotate: false,
            touchPitch: false,
            locale: mapControlsLocale(get(i18n).t, {
                'Map.Title': get(i18n).t('maps.schema.map_label'),
            }),
            attributionControl: { compact: true },
        });
        map.touchZoomRotate.disableRotation();
        map.keyboard.disableRotation();
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(
            new maplibregl.FullscreenControl({ container: fullscreenContainer ?? wrapper }),
            'top-right',
        );
        map.on('load', () => {
            map.addSource(SCHEMA_SOURCE, {
                type: 'geojson',
                data: data ?? EMPTY,
                attribution: `<a href="/credits/tech">${escapeHtml(get(i18n).t('maps.schema.attribution'))}</a>`,
            });
            addLayers(theme.isDark);
            map.getCanvas().setAttribute('aria-describedby', descriptionId);
            map.on('click', onClick);
            map.on('mousemove', onMove);
            // Passage de téléphone à ordinateur (rotation, plein écran) : noms des grandes villes.
            map.on('resize', () =>
                map.setLayerZoomRange(
                    SCHEMA_LABEL_LAYERS[0],
                    cityLabelMinZoom(container.clientWidth),
                    24,
                ),
            );
            ready = true;
        });
    });

    onDestroy(() => {
        popup?.remove();
        map?.remove();
        map = null;
    });

    // Plan de l'époque : chargé à chaque changement d'époque (réponse gardée en cache par le serveur
    // et le navigateur), requête précédente abandonnée.
    $effect(() => {
        const controller = new AbortController();
        phase = 'loading';
        fetch(schematicUrl(era), {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((json) => {
                data = withLabelOffsets(json);
                phase = 'ready';
            })
            .catch((error) => {
                if (error.name !== 'AbortError') phase = 'error';
            });
        return () => controller.abort();
    });

    $effect(() => {
        const next = data;
        if (!ready) return;
        const source = /** @type {import('maplibre-gl').GeoJSONSource | undefined} */ (
            map.getSource(SCHEMA_SOURCE)
        );
        source?.setData(next ?? EMPTY);
        popup?.remove();
    });

    // Thème : fond, encre et couleurs du gris « trafic inconnu » suivent le thème du site.
    $effect(() => {
        const dark = theme.isDark;
        if (ready && dark !== paintedDark) addLayers(dark);
    });

    // Catégories masquées par la légende.
    $effect(() => {
        const filter = /** @type {import('maplibre-gl').FilterSpecification} */ (
            axisFilter([...hidden])
        );
        if (!ready) return;
        map.setFilter(SCHEMA_LAYERS.casing, filter);
        map.setFilter(SCHEMA_LAYERS.axes, filter);
    });

    // Ligne dont la fiche est ouverte : liseré foncé sous ses axes.
    $effect(() => {
        const filter = /** @type {import('maplibre-gl').FilterSpecification} */ (
            selectedFilter(lineIdOf(selectedLine, data?.lines))
        );
        if (ready) map.setFilter(SCHEMA_LAYERS.selected, filter);
    });

    // Recherche d'une gare : vol vers sa place sur le plan (à défaut, vers sa position réelle).
    $effect(() => {
        const target = flyTarget;
        if (!ready || !target || target.key === flownKey) return;
        flownKey = target.key;
        const center = stationPosition(data, target.slug) ?? [target.lng, target.lat];
        map.flyTo({ center, zoom: Math.min(target.zoom ?? 11, 11), duration: 1400 });
    });
</script>

<div class="rail-map-wrapper schema-wrapper" bind:this={wrapper}>
    <div class="rail-map schema-map" bind:this={container}></div>
    <p class="vff-sr-only" id={descriptionId}>{summary}</p>
    {#if phase !== 'ready'}
        <p class="schema-status" role="status">
            {phase === 'error' ? t('maps.schema.error') : t('maps.schema.loading')}
        </p>
    {/if}
    {@render children?.()}
</div>

<style>
    .schema-wrapper {
        position: relative;
        overflow: hidden;
    }
    .schema-map {
        width: 100%;
        height: 100%;
        background-color: var(--vff-bg-elevated);
    }
    /* Focus clavier sur le plan (déplacement aux flèches, zoom avec + et -) : contour dans le cadre. */
    .schema-map :global(.maplibregl-canvas:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: calc(-1 * var(--vff-focus-width));
    }
    /* Chargement ou échec : message posé en haut du plan, au centre (comme les messages de la carte). */
    .schema-status {
        position: absolute;
        top: 12px;
        left: 50%;
        z-index: 2;
        max-width: calc(100% - 24px);
        margin: 0;
        padding: 8px 12px;
        transform: translateX(-50%);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
</style>
