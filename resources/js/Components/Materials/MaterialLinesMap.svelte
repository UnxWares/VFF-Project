<script>
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { loadMaplibre } from '$lib/loadMaplibre.js';
    import { whenNear } from '$lib/whenNear.js';
    import { MAP_MAX_ZOOM } from '$lib/basemap.js';
    import { isOfflineView, neutralMapStyle, reportPartialMap } from '$lib/offlineMap.svelte.js';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { confidenceKey } from '$lib/materialFacts.js';
    import { periodText } from '$lib/materialPeriods.js';
    import { decadeOf, formatLocale } from '$lib/typo.js';
    import { decadeRange, decadesOf, lineState, linesBounds } from '$lib/materialMap.js';

    /**
     * Carte des lignes où une série a circulé, avec un curseur de décennies : les lignes desservies pendant la
     * décennie choisie sont allumées. Une ligne dont une source date la circulation est en trait plein, une ligne
     * attestée sans date en tirets (on ne dit pas qu'elle était desservie à cette date, seulement que la série
     * y a circulé). La liste des lignes sous la carte est l'équivalent complet, au clavier : mêmes lignes, mêmes
     * dates, même source, même niveau de confiance. Le tracé est allégé (MaterialLinesMap) et lu à part.
     */
    let { url, name = '', height = '520px' } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const CONFIDENCE_TONE = { high: 'success', medium: 'muted', low: 'warning' };
    // Lignes listées d'emblée ; la suite se déplie (la série la plus citée en compte 58).
    const LIST_SHOWN = 12;
    const CC_BY_SA = 'https://creativecommons.org/licenses/by-sa/4.0/';

    // Svelte retire l'espace qui borde un bloc {#if}.
    const SPACE = ' ';

    let mapContainer = $state();
    let map = null;
    let loaded = $state(false);
    let failed = $state(false);
    /** @type {{ material: any, lines: any[] } | null} */
    let data = $state.raw(null);
    // Toutes les dates, ou l'indice de la décennie choisie.
    let all = $state(true);
    let index = $state(0);
    let selected = $state(null);
    let releasePartialMap = null;

    const lines = $derived(data?.lines ?? []);
    const decades = $derived(data ? decadesOf(data.material, lines) : []);
    const range = $derived(
        all || !decades.length ? null : decadeRange(decades[index] ?? decades[0]),
    );
    const states = $derived(
        Object.fromEntries(lines.map((l) => [l.slug, lineState(l, data.material, range)])),
    );
    // Lignes à la circulation datée d'abord, puis les liens les plus sûrs.
    const RANK = { high: 0, medium: 1, low: 2 };
    const lit = $derived(
        lines
            .filter((l) => states[l.slug] !== 'off')
            .sort(
                (a, b) =>
                    Number(states[a.slug] === 'undated') - Number(states[b.slug] === 'undated') ||
                    (RANK[a.confidence] ?? 3) - (RANK[b.confidence] ?? 3),
            ),
    );
    const others = $derived(lines.filter((l) => states[l.slug] === 'off'));
    const undatedLit = $derived(lit.filter((l) => states[l.slug] === 'undated').length);
    const hasGeometry = $derived(lines.some((l) => l.geometry));
    const litShown = $derived(lit.slice(0, LIST_SHOWN));
    const litMore = $derived(lit.slice(LIST_SHOWN));
    const datedLit = $derived(lit.length - undatedLit);
    const current = $derived(lines.find((l) => l.slug === selected) ?? null);
    const rangeLabel = $derived(
        range ? t('materials.map.decade_during', { from: range[0], to: range[1] }) : '',
    );

    /**
     * @param {boolean} offline
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
                        'raster-saturation': -0.7,
                        'raster-opacity': 0.8,
                        'raster-brightness-max': theme.isDark ? 0.7 : 1,
                    },
                },
            ],
            glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
        };
    }

    const fc = () => ({
        type: 'FeatureCollection',
        features: lines
            .filter((l) => l.geometry)
            .map((l) => ({
                type: 'Feature',
                properties: { slug: l.slug, name: l.name },
                geometry: l.geometry,
            })),
    });

    /** Pousse l'état de chaque ligne (et la sélection) dans la carte, sans recharger les tracés. */
    function paint() {
        if (!loaded || !map) return;
        for (const l of lines) {
            if (!l.geometry) continue;
            map.setFeatureState(
                { source: 'vff-material-lines', id: l.slug },
                { state: states[l.slug], selected: l.slug === selected },
            );
        }
    }

    const stateColor = (name) => [
        'match',
        ['feature-state', 'state'],
        'dated',
        theme.isDark ? '#ff8b35' : '#e55a00',
        'undated',
        theme.isDark ? '#ffb27a' : '#b86a2c',
        name,
    ];

    let destroyed = false;
    /** @type {ReturnType<typeof whenNear> | null} */
    let near = null;

    onMount(async () => {
        const request = fetch(url, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .catch(() => null);
        near = whenNear(mapContainer);
        await near.promise;
        if (destroyed) return;
        const [maplibregl, body] = await Promise.all([loadMaplibre(), request]);
        if (destroyed || !mapContainer) return;
        if (!body?.lines) {
            failed = true;
            return;
        }
        data = body;
        // Aucun tracé à dessiner (tout retenu) : la liste suffit.
        const bounds = linesBounds(body.lines);
        if (!bounds) return;
        const offline = isOfflineView(page.props);
        if (offline) releasePartialMap = reportPartialMap();
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(offline),
            bounds,
            fitBoundsOptions: { padding: 36, maxZoom: 9 },
            maxZoom: MAP_MAX_ZOOM,
            attributionControl: { compact: true },
            cooperativeGestures: true,
            locale: {
                'Map.Title': t('materials.map.title', { name }),
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
            map.addSource('vff-material-lines', { type: 'geojson', data: fc(), promoteId: 'slug' });
            const w = (base) => [
                'interpolate',
                ['linear'],
                ['zoom'],
                4,
                base,
                8,
                base * 1.6,
                12,
                base * 2.6,
            ];
            const lit = ['!=', ['feature-state', 'state'], 'off'];

            // Liseré clair sous les lignes allumées : lisible sur tous les fonds, y compris sombre.
            map.addLayer({
                id: 'vff-ml-casing',
                type: 'line',
                source: 'vff-material-lines',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': theme.isDark ? '#14171c' : '#ffffff',
                    'line-width': w(5),
                    'line-opacity': ['case', lit, 0.85, 0],
                },
            });
            map.addLayer({
                id: 'vff-ml-off',
                type: 'line',
                source: 'vff-material-lines',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': theme.isDark ? '#8a929c' : '#7b8590',
                    'line-width': w(1.4),
                    'line-opacity': ['case', lit, 0, 0.55],
                },
            });
            // Les filtres ne lisent pas l'état d'un objet : chaque couche se masque par son opacité.
            const chosen = ['boolean', ['feature-state', 'selected'], false];
            const wSel = (base) => [
                'interpolate',
                ['linear'],
                ['zoom'],
                ...[
                    [4, 1],
                    [8, 1.6],
                    [12, 2.6],
                ].flatMap(([z, k]) => [z, ['*', k, ['case', chosen, base * 1.7, base]]]),
            ];
            map.addLayer({
                id: 'vff-ml-dated',
                type: 'line',
                source: 'vff-material-lines',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': stateColor('#7b8590'),
                    'line-width': wSel(3.2),
                    'line-opacity': ['case', ['==', ['feature-state', 'state'], 'dated'], 0.95, 0],
                },
            });
            map.addLayer({
                id: 'vff-ml-undated',
                type: 'line',
                source: 'vff-material-lines',
                layout: { 'line-join': 'round' },
                paint: {
                    'line-color': stateColor('#7b8590'),
                    'line-width': wSel(3),
                    'line-dasharray': [2, 1.6],
                    'line-opacity': [
                        'case',
                        ['==', ['feature-state', 'state'], 'undated'],
                        0.95,
                        0,
                    ],
                },
            });
            for (const id of ['vff-ml-dated', 'vff-ml-undated', 'vff-ml-off']) {
                map.on('click', id, (e) => {
                    const slug = e.features?.[0]?.properties?.slug ?? e.features?.[0]?.id;
                    if (slug) selected = String(slug);
                });
                map.on('mouseenter', id, () => (map.getCanvas().style.cursor = 'pointer'));
                map.on('mouseleave', id, () => (map.getCanvas().style.cursor = ''));
            }
            loaded = true;
        });
    });

    $effect(() => {
        // Dépendances : états et sélection.
        void states;
        void selected;
        paint();
    });

    // Bascule de thème pendant la visite : fond, liseré et couleurs.
    $effect(() => {
        const dark = theme.isDark;
        if (!loaded || !map) return;
        if (map.getLayer('osm'))
            map.setPaintProperty('osm', 'raster-brightness-max', dark ? 0.7 : 1);
        map.setPaintProperty('vff-ml-casing', 'line-color', dark ? '#14171c' : '#ffffff');
        map.setPaintProperty('vff-ml-off', 'line-color', dark ? '#8a929c' : '#7b8590');
        map.setPaintProperty('vff-ml-dated', 'line-color', stateColor('#7b8590'));
        map.setPaintProperty('vff-ml-undated', 'line-color', stateColor('#7b8590'));
    });

    /** Choisit une ligne ; la carte se cadre dessus (la liste reste la référence au clavier). */
    function select(l) {
        selected = selected === l.slug ? null : l.slug;
        if (selected && map && l.geometry) {
            const b = linesBounds([l]);
            if (b) map.fitBounds(b, { padding: 60, maxZoom: 11 });
        }
    }

    function datesOf(l) {
        return l.periods.map((p) => periodText(p, t, formatLocale(lang)));
    }

    onDestroy(() => {
        destroyed = true;
        near?.cancel();
        releasePartialMap?.();
        map?.remove();
        map = null;
    });
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

{#snippet lineItem(l)}
    {@const key = confidenceKey(l.confidence, l.kind)}
    {@const state = states[l.slug]}
    <li class={['item', { on: selected === l.slug }]}>
        <div class="head">
            <a class="name" href="/lines/{l.slug}"
                >{#if l.code}<span class="vff-plate">{l.code}</span>{/if}<span>{l.name}</span></a
            >
            <span class={['vff-badge', CONFIDENCE_TONE[l.confidence] ?? 'muted']}
                >{t(`${key}.label`)}</span
            >
        </div>
        <p class="when">
            {#if l.periods.length}
                {datesOf(l).join(', ')}
            {:else}
                {t('materials.map.undated')}
            {/if}
            {#if range}
                <span class="state"
                    >{state === 'off'
                        ? t('materials.map.state_off', { range: rangeLabel })
                        : state === 'dated'
                          ? t('materials.map.state_dated', { range: rangeLabel })
                          : t('materials.map.state_undated')}</span
                >
            {/if}
        </p>
        <p class="src">
            {t(`${key}.text`)}
            {#if l.source_url}
                <a href={l.source_url} target="_blank" rel="noopener noreferrer"
                    >{l.source === 'wikidata'
                        ? t('materials.section_lines.wikidata')
                        : t('materials.map.source')}{@render ext()}</a
                >
            {/if}
        </p>
        {#if l.geometry}
            <button
                type="button"
                class="vff-btn secondary small"
                aria-pressed={selected === l.slug}
                onclick={() => select(l)}
                >{selected === l.slug ? t('materials.map.unfocus') : t('materials.map.focus')}<span
                    class="vff-sr-only"
                >
                    {l.name}</span
                ></button
            >
        {/if}
        {#if selected === l.slug && l.quote}
            <blockquote class="quote" lang="fr" cite={l.source_url}>
                <p>{l.quote}</p>
                <footer>
                    {#if l.article}<span lang="fr">{l.article}</span>,{/if}
                    <a href={CC_BY_SA} target="_blank" rel="license noopener noreferrer"
                        >{t('materials.section_lines.license')}{@render ext()}</a
                    >
                </footer>
            </blockquote>
        {/if}
    </li>
{/snippet}

<div class="lines-map">
    <div class="controls" role="group" aria-label={t('materials.map.controls')}>
        {#if decades.length > 1}
            <div class="mode">
                <button
                    type="button"
                    class="vff-btn small"
                    class:secondary={!all}
                    aria-pressed={all}
                    disabled={!data}
                    onclick={() => (all = true)}>{t('materials.map.all_dates')}</button
                >
                <label for="material-decade" class="slider-label"
                    >{all
                        ? t('materials.map.pick_decade')
                        : t('materials.map.decade_range', { from: range[0], to: range[1] })}</label
                >
            </div>
            <input
                id="material-decade"
                type="range"
                min="0"
                max={decades.length - 1}
                step="1"
                bind:value={index}
                oninput={() => (all = false)}
                aria-valuetext={t('materials.map.decade_range', {
                    from: decades[index],
                    to: decades[index] + 9,
                })}
                disabled={!data}
            />
            <div class="ticks" aria-hidden="true">
                <span>{decadeOf(decades[0], lang)}</span>
                <span>{decadeOf(decades.at(-1), lang)}</span>
            </div>
        {/if}
        <p class="summary" role="status" aria-live="polite">
            {#if !data}
                {failed ? t('materials.map.unavailable') : t('materials.map.loading')}
            {:else if !range}
                {t('materials.map.summary_all', { count: lines.length })}
            {:else}
                {t('materials.map.summary', {
                    count: datedLit,
                    range: rangeLabel,
                })}{#if undatedLit}
                    {SPACE}{t('materials.map.summary_undated', { count: undatedLit })}{/if}
            {/if}
        </p>
    </div>

    <div class="frame" class:hidden={data && !hasGeometry} style:height>
        <div class="map" bind:this={mapContainer}></div>
        {#if !loaded}<p class="loading" aria-hidden="true">
                {failed ? t('materials.map.unavailable') : t('lines.sheet.map.loading')}
            </p>{/if}
        {#if loaded}
            <ul class="legend" aria-label={t('materials.map.legend')}>
                <li><i class="sw dated"></i>{t('materials.map.legend_dated')}</li>
                <li><i class="sw undated"></i>{t('materials.map.legend_undated')}</li>
                {#if range}<li><i class="sw off"></i>{t('materials.map.legend_off')}</li>{/if}
            </ul>
        {/if}
    </div>

    {#if data}
        {#if current}
            <p class="vff-sr-only" role="status">
                {t('materials.map.focused', { name: current.name })}
            </p>
        {/if}
        <h3 class="list-title">
            {range
                ? t('materials.map.list_lit', { count: lit.length, range: rangeLabel })
                : t('materials.map.list_all', { count: lines.length })}
        </h3>
        {#if lit.length}
            <ul class="list">
                {#each litShown as l (l.slug)}{@render lineItem(l)}{/each}
            </ul>
            {#if litMore.length}
                <details class="fold">
                    <summary>{t('materials.map.more', { count: litMore.length })}</summary>
                    <ul class="list">
                        {#each litMore as l (l.slug)}{@render lineItem(l)}{/each}
                    </ul>
                </details>
            {/if}
        {:else}
            <p class="empty">{t('materials.map.none', { range: rangeLabel })}</p>
        {/if}
        {#if others.length}
            <details class="fold">
                <summary>{t('materials.map.others', { count: others.length })}</summary>
                <ul class="list">
                    {#each others as l (l.slug)}{@render lineItem(l)}{/each}
                </ul>
            </details>
        {/if}
    {/if}
</div>

<style>
    .lines-map {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
    }
    .controls {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        padding: var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
    }
    .mode {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
    }
    .slider-label {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-size: var(--vff-fs-base);
        color: var(--vff-text);
    }
    input[type='range'] {
        width: 100%;
        min-height: 44px;
        accent-color: var(--vff-primary, #ff6500);
    }
    .ticks {
        display: flex;
        justify-content: space-between;
        margin-top: -8px;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .summary {
        margin: 0;
        color: var(--vff-text);
    }
    .frame {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
    }
    .frame.hidden {
        display: none;
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
        gap: 4px;
        max-width: calc(100% - 20px);
        margin: 0;
        padding: 8px 12px;
        list-style: none;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sw {
        flex-shrink: 0;
        width: 26px;
        height: 0;
        border-top: 4px solid #e55a00;
    }
    .sw.undated {
        border-top: 4px dashed #b86a2c;
    }
    .sw.off {
        border-top: 2px solid #7b8590;
    }
    :global(:root[data-theme='dark']) .sw.dated {
        border-top-color: #ff8b35;
    }
    :global(:root[data-theme='dark']) .sw.undated {
        border-top-color: #ffb27a;
    }
    .list-title {
        margin: var(--vff-space-2) 0 0;
        font-size: var(--vff-fs-lg);
    }
    .list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr));
        gap: var(--vff-space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-2);
        min-width: 0;
        padding: var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
    }
    .item.on {
        border-color: var(--vff-primary, #ff6500);
        box-shadow: 0 0 0 2px var(--vff-primary, #ff6500);
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .name {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .item p {
        margin: 0;
        font-size: var(--vff-fs-base);
        overflow-wrap: anywhere;
    }
    .state {
        display: block;
        color: var(--vff-text-muted);
    }
    .src {
        color: var(--vff-text-muted);
    }
    .quote {
        margin: 0;
        padding: var(--vff-space-3);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
    }
    .quote footer {
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .fold summary {
        cursor: pointer;
        margin-bottom: var(--vff-space-3);
        min-height: 44px;
        display: flex;
        align-items: center;
    }
    .empty {
        margin: 0;
        color: var(--vff-text-muted);
    }
</style>
