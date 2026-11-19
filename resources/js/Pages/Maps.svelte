<script>
    import { onMount, untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import RailMap from '$components/Map/RailMap.svelte';
    import TimelineSlider from '$components/Map/TimelineSlider.svelte';
    import EmbedCodeModal from '$components/Map/EmbedCodeModal.svelte';
    import LineDrawer from '$components/Map/LineDrawer.svelte';
    import SearchBox from '$components/Map/SearchBox.svelte';
    import MapLegend from '$components/Map/MapLegend.svelte';
    import RoutePlanner from '$components/Map/RoutePlanner.svelte';
    import RouteCompareKey from '$components/Map/RouteCompareKey.svelte';
    import EraViewSwitch from '$components/Map/EraViewSwitch.svelte';
    import EraCompareBar from '$components/Map/EraCompareBar.svelte';
    import LayerPicker from '$components/Map/LayerPicker.svelte';
    import { i18n } from '$lib/i18n.js';
    import { defaultPair, readViewParams, writeViewParams } from '$lib/epochViews.js';
    import { accidentYears, countAccidents, fetchAccidents } from '$lib/accidentMap.js';
    import { LEVEL_CROSSING_MIN_ZOOM } from '$lib/levelCrossingMap.js';
    import { NO_ORM, activeLayers, layerNotices, readLayers, writeLayers } from '$lib/mapLayers.js';

    // « Lire la carte » : ce que montre la carte, au présent (vocabulaire de la légende).
    const READING = ['era', 'views', 'color', 'line', 'sheet', 'route', 'layers'];

    let { eras: erasProp = null, currentEraYear = 2030, dataVersion = 'v0' } = $props();
    // TOUTES les décennies sont sélectionnables (1830 → 2030) ; celles sans
    // tracé reconstitué sont signalées (marqueur en pointillé + bandeau) au lieu
    // d'être masquées. Repli sur 5 ères si pas de prop.
    const eras = $derived(
        erasProp?.length ? erasProp.map((e) => e.year) : [1990, 2000, 2010, 2020, 2030],
    );
    const emptyYears = $derived(
        erasProp?.length ? erasProp.filter((e) => e.has_data === false).map((e) => e.year) : [],
    );
    // Noms des époques (base, en français) affichés sous l'année de la frise.
    const eraLabels = $derived(
        Object.fromEntries((erasProp ?? []).filter((e) => e.label).map((e) => [e.year, e.label])),
    );
    // Décennie affichée : la décennie actuelle à l'ouverture (ou celle de l'URL, lue au montage).
    let selectedEra = $state(untrack(() => currentEraYear));
    const selectedIsEmpty = $derived(emptyYears.includes(selectedEra));
    // Catégories masquées via la légende interactive (tout visible par défaut :
    // réseau vivant ET disparu - fermées en tirets, déposées en pointillés).
    let hidden = $state([]);
    // Couleur des tracés : trafic, électrification ou écartement (sélecteur de la légende).
    let colorMode = $state('traffic');
    let mapFrameEl = $state();
    let embedModalOpen = $state(false);
    // Calques facultatifs (bouton « Calques ») : ceux de VFF, cochés librement et superposés, et un
    // fond OpenRailwayMap au plus (lib/mapLayers.js).
    let dataLayers = $state([]);
    let ormLayer = $state(NO_ORM);
    // Calque « Accidents » : points chargés au premier affichage.
    let accidentData = $state.raw(null);
    let accidentsState = $state('idle');
    let drawerLine = $state(null);
    let flyTarget = $state(null);
    // Itinéraire historique affiché (réponse /api/v1/route) : la carte passe à son ère.
    let route = $state(null);
    // Comparaison d'un trajet entre deux époques (réponse de /api/v1/route/compare) : `route` est
    // alors le trajet le plus récent, `earlierRoute` le plus ancien (dessiné dessous).
    let routeComparison = $state.raw(null);
    let earlierRoute = $state.raw(null);
    // Vue : une décennie ('era'), deux décennies comparées ('compare'), toutes ('stack').
    let view = $state('era');
    // Décennies comparées (compareFrom < compareTo), choisies au passage à la comparaison.
    let compareFrom = $state(null);
    let compareTo = $state(null);

    function setView(next) {
        if (next === 'compare' && compareFrom === null) {
            [compareFrom, compareTo] = defaultPair(selectedEra, eras, currentEraYear) ?? [];
        }
        // La fiche latérale décrit une ligne à UNE décennie : elle se ferme hors de cette vue.
        if (next !== 'era') drawerLine = null;
        view = next;
    }

    function onRoute(result) {
        routeComparison = null;
        earlierRoute = null;
        route = result;
        view = 'era';
        if (eras.includes(result.era_year)) selectedEra = result.era_year;
    }

    // La carte reste sur l'ère de la première année demandée (`mapEra`).
    function onCompare(comparison, mapEra) {
        routeComparison = comparison;
        earlierRoute = comparison.earlier.route;
        route = comparison.later.route;
        view = 'era';
        if (eras.includes(mapEra)) selectedEra = mapEra;
    }

    function clearRoute() {
        routeComparison = null;
        earlierRoute = null;
        route = null;
    }

    function onSearchPick(picked) {
        if (picked.type === 'line') {
            router.visit(`/lines/${picked.slug}`);
            return;
        }
        if (picked.type === 'station') {
            flyTarget = { lng: picked.lng, lat: picked.lat, zoom: 13, key: Date.now() };
        }
    }

    // France métropolitaine et Corse, cadrée à l'ouverture à toutes les largeurs.
    const FRANCE_BOUNDS = [
        [-5.2, 41.3],
        [9.6, 51.1],
    ];
    // Ordinateur : la légende ouverte occupe la gauche (272 px), les boutons de zoom la droite.
    // Deux trajets comparés : place en bas pour leur légende, posée sur la carte.
    const fitPadding = (w) => {
        const keyRoom = routeComparison ? 64 : 0;
        return w > 900
            ? { top: 24, right: 56, bottom: 32 + keyRoom, left: 300 }
            : { top: 60, right: 52, bottom: 28 + keyRoom, left: 16 };
    };

    const showingToday = $derived(view === 'era' && selectedEra === currentEraYear);
    const drawn = $derived(activeLayers({ data: dataLayers, orm: ormLayer }, showingToday));
    const showAccidents = $derived(drawn.accidents);
    // Passages à niveau et signalisation : seul l'état d'aujourd'hui est connu (SNCF Réseau). Hors de
    // la carte d'aujourd'hui, un message le dit et propose d'y revenir.
    const showLevelCrossings = $derived(drawn.levelCrossings);
    const showSignalling = $derived(drawn.signalling);
    // Zoom de la carte, pour l'aide « zoomez pour voir les passages à niveau ».
    let mapZoom = $state(0);

    function showToday() {
        setView('era');
        selectedEra = currentEraYear;
    }

    // L'adresse suit les calques choisis (?layer=…&layer=…), pour pouvoir la partager.
    function writeLayerParams() {
        const next = writeLayers(window.location.href, { data: dataLayers, orm: ormLayer });
        if (next !== window.location.href) {
            window.history.replaceState(window.history.state, '', next);
        }
    }

    function setDataLayers(next) {
        dataLayers = next;
        writeLayerParams();
        if (
            next.includes('accidents') &&
            accidentsState !== 'ready' &&
            accidentsState !== 'loading'
        ) {
            loadAccidents();
        }
    }

    function setOrmLayer(next) {
        ormLayer = next;
        writeLayerParams();
    }

    async function loadAccidents() {
        accidentsState = 'loading';
        try {
            accidentData = await fetchAccidents();
            accidentsState = 'ready';
        } catch {
            accidentsState = 'error';
        }
    }

    // Légende du calque : années montrées selon la vue et nombre d'accidents localisés.
    const accidentLegend = $derived.by(() => {
        if (!showAccidents) return null;
        const years = accidentYears(
            view,
            view === 'compare' ? compareFrom : selectedEra,
            compareTo,
        );
        return { state: accidentsState, count: countAccidents(accidentData, years), ...years };
    });

    // Messages des calques posés sur la carte : échec des accidents, calques connus seulement
    // aujourd'hui (une phrase pour un ou deux calques), aide au zoom des passages à niveau.
    const mapNotices = $derived(
        layerNotices(drawn, {
            showingToday,
            accidentsFailed: accidentsState === 'error',
            zoom: mapZoom,
            minZoom: LEVEL_CROSSING_MIN_ZOOM,
        }).map((n) => ({ ...n, text: $i18n.t(n.textKey) })),
    );

    // Adresse partageable : ?era=1930, ?era=1930&compare=1980, ?view=stack, et ?layer=… pour
    // chaque calque (lue au montage).
    onMount(() => {
        const fromUrl = readViewParams(window.location.search, eras);
        if (fromUrl.era !== null) selectedEra = fromUrl.era;
        if (fromUrl.view === 'compare') {
            compareFrom = fromUrl.era;
            compareTo = fromUrl.compare;
        }
        view = fromUrl.view;
        // Calques demandés par un lien (fiche ligne : « Voir la signalisation sur la carte »).
        const layers = readLayers(window.location.search);
        ormLayer = layers.orm;
        setDataLayers(layers.data);
    });

    $effect(() => {
        const state =
            view === 'compare'
                ? { view, era: compareFrom, compare: compareTo }
                : { view, era: selectedEra };
        const next = writeViewParams(window.location.href, state, eras[eras.length - 1]);
        if (next !== window.location.href) {
            window.history.replaceState(window.history.state, '', next);
        }
    });
</script>

<svelte:head>
    <title>{$i18n.t('maps.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('maps.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <!-- La carte d'abord : titre sur une ligne, puis la carte haute de tout l'espace restant
             (100dvh moins l'en-tête et le titre). Le chapeau passe sous la carte. -->
        <div class="map-screen vff-container">
            <div class="map-head">
                <h1>{$i18n.t('maps.hero.title')}</h1>
                <nav class="map-jumps" aria-label={$i18n.t('maps.jumps_label')}>
                    <ul>
                        <li>
                            <a href="#itineraire"
                                ><i class="bi bi-signpost-split" aria-hidden="true"></i>
                                {$i18n.t('itinerary.title')}</a
                            >
                        </li>
                        <li>
                            <a href="#lire-la-carte"
                                ><i class="bi bi-book" aria-hidden="true"></i>
                                {$i18n.t('maps.read.title')}</a
                            >
                        </li>
                    </ul>
                </nav>
            </div>

            <section class="map-stage" id="carte" aria-labelledby="map-title">
                <h2 class="vff-sr-only" id="map-title">{$i18n.t('maps.map_title')}</h2>
                <div class="map-frame" bind:this={mapFrameEl}>
                    <div class="map-toolbar">
                        <div class="toolbar-search">
                            <SearchBox onPick={onSearchPick} />
                        </div>
                        <div class="toolbar-view">
                            <EraViewSwitch bind:view={() => view, setView} />
                        </div>
                        <div class="toolbar-tools">
                            <LayerPicker
                                bind:data={() => dataLayers, setDataLayers}
                                bind:orm={() => ormLayer, setOrmLayer}
                            />
                            <button
                                type="button"
                                class="vff-btn secondary embed-btn"
                                aria-haspopup="dialog"
                                onclick={() => (embedModalOpen = true)}
                            >
                                <i class="bi bi-code-slash" aria-hidden="true"></i>
                                {$i18n.t('maps.embed.button')}
                            </button>
                        </div>
                    </div>

                    <RailMap
                        era={view === 'compare' ? compareFrom : selectedEra}
                        {view}
                        compareEra={compareTo}
                        geolocate
                        currentEra={currentEraYear}
                        {hidden}
                        mode={colorMode}
                        overlay={ormLayer}
                        accidents={showAccidents ? accidentData : null}
                        levelCrossings={showLevelCrossings}
                        signalling={showSignalling}
                        onZoomChange={(z) => (mapZoom = z)}
                        {dataVersion}
                        {flyTarget}
                        {route}
                        {earlierRoute}
                        height="100%"
                        fitBounds={FRANCE_BOUNDS}
                        {fitPadding}
                        selectedLine={drawerLine?.slug ?? null}
                        fullscreenContainer={mapFrameEl}
                        onLineClick={(props) => (drawerLine = props)}
                    >
                        <div class="legend-slot">
                            <MapLegend
                                era={view === 'compare' ? compareFrom : selectedEra}
                                {view}
                                compareEra={compareTo}
                                currentEra={currentEraYear}
                                bind:hidden
                                bind:mode={colorMode}
                                accidents={accidentLegend}
                                overlays={drawn.legendOverlays}
                            />
                        </div>
                        <!-- Messages posés sur la carte, les uns sous les autres (jamais superposés). -->
                        {#if (view === 'era' && selectedIsEmpty) || mapNotices.length}
                            <div class="map-notices">
                                {#if view === 'era' && selectedIsEmpty}
                                    <p class="era-empty">
                                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                        {$i18n.t('maps.era.no_data', { year: selectedEra })}
                                    </p>
                                {/if}
                                {#each mapNotices as notice (notice.key)}
                                    {#if notice.key === 'today'}
                                        <div class="era-empty today-only">
                                            <p>{notice.text}</p>
                                            <button
                                                type="button"
                                                class="vff-btn secondary"
                                                onclick={showToday}
                                            >
                                                {$i18n.t('maps.level_crossings.show_today')}
                                            </button>
                                        </div>
                                    {:else}
                                        <p class="era-empty">
                                            {#if notice.icon}<i
                                                    class="bi {notice.icon}"
                                                    aria-hidden="true"
                                                ></i>{/if}
                                            {notice.text}
                                        </p>
                                    {/if}
                                {/each}
                            </div>
                        {/if}
                        <!-- Chargement ou échec du calque « Accidents », calques du réseau d'aujourd'hui,
                             aide au zoom des passages à niveau : annoncés aussi quand la légende est
                             repliée (téléphone). -->
                        <p class="vff-sr-only" role="status">
                            {[
                                showAccidents && accidentsState === 'loading'
                                    ? $i18n.t('maps.accidents.loading')
                                    : '',
                                ...mapNotices.map((n) => n.text),
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        </p>
                        {#if routeComparison && view === 'era'}
                            <div class="route-key-slot">
                                <RouteCompareKey
                                    comparison={routeComparison}
                                    compact
                                    label={$i18n.t('itinerary.compare.map_key')}
                                />
                            </div>
                        {/if}
                        <LineDrawer bind:line={drawerLine} currentEra={currentEraYear} />
                    </RailMap>

                    {#if view === 'compare'}
                        <EraCompareBar
                            {eras}
                            currentEra={currentEraYear}
                            bind:from={compareFrom}
                            bind:to={compareTo}
                        />
                    {:else if view === 'era'}
                        <TimelineSlider
                            {eras}
                            {emptyYears}
                            currentEra={currentEraYear}
                            labels={eraLabels}
                            bind:value={selectedEra}
                        />
                    {/if}
                </div>
            </section>
        </div>

        <div class="vff-container map-after">
            <p class="map-lead">{$i18n.t('maps.hero.subtitle')}</p>
            <button
                type="button"
                class="vff-btn secondary small embed-btn"
                aria-haspopup="dialog"
                onclick={() => (embedModalOpen = true)}
            >
                <i class="bi bi-code-slash" aria-hidden="true"></i>
                {$i18n.t('maps.embed.button')}
            </button>
        </div>

        <div class="vff-container route-section" id="itineraire">
            <RoutePlanner
                year={selectedEra}
                minYear={eras[0]}
                maxYear={eras[eras.length - 1]}
                currentEra={currentEraYear}
                mapAnchor="carte"
                {onRoute}
                {onCompare}
                onClear={clearRoute}
            />
        </div>

        <EmbedCodeModal
            bind:open={embedModalOpen}
            defaultEra={selectedEra}
            {eras}
            currentEra={currentEraYear}
        />

        <SectionBlock
            kind="alt"
            id="lire-la-carte"
            title={$i18n.t('maps.read.title')}
            maxWidth="1100px"
        >
            <dl class="reading">
                {#each READING as key (key)}
                    <div class="reading-item">
                        <dt>{$i18n.t(`maps.read.${key}_term`)}</dt>
                        <dd>{$i18n.t(`maps.read.${key}_text`)}</dd>
                    </div>
                {/each}
            </dl>
        </SectionBlock>

        <SectionBlock title={$i18n.t('maps.tech.title')} maxWidth="720px">
            <div class="vff-prose">
                <p>{$i18n.t('maps.tech.paragraph')}</p>
            </div>
            <Link href="/credits/tech" class="vff-link">{$i18n.t('maps.tech.credits')}</Link>
        </SectionBlock>

        <SectionBlock kind="cta" title={$i18n.t('maps.cta.title')} intro={$i18n.t('maps.cta.text')}>
            {#snippet actions()}
                <Link href="/contribute" class="vff-btn primary">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    {$i18n.t('maps.cta.contribute')}
                </Link>
                <a
                    href="https://discord.com/invite/6SwTfXBx4h"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vff-btn secondary"
                >
                    <i class="bi bi-discord" aria-hidden="true"></i>
                    {$i18n.t('maps.cta.discord')}<span class="vff-sr-only">
                        ({$i18n.t('common.new_tab')})</span
                    >
                </a>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    /* La carte d'abord : le titre et la carte remplissent le premier écran (100dvh moins l'en-tête),
       sur la grille commune. Hauteur minimale : sur un écran très bas (téléphone en paysage), la
       page défile normalement au lieu d'écraser la carte. Une marge reste sous le cadre : on peut
       toujours faire défiler la page au doigt sans passer par la carte. */
    .map-screen {
        display: flex;
        flex-direction: column;
        height: max(600px, calc(100dvh - var(--vff-header-h)));
        padding-bottom: var(--vff-space-5);
    }
    .map-head {
        display: flex;
        flex: none;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 4px var(--vff-space-6);
        padding-block: var(--vff-space-5) var(--vff-space-4);
    }
    .map-head h1 {
        margin: 0;
        font-size: clamp(1.375rem, 1rem + 1.1vw, 2rem);
        line-height: var(--vff-lh-heading);
        text-wrap: balance;
    }
    .map-jumps ul {
        display: flex;
        flex-wrap: wrap;
        gap: 4px var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .map-jumps a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 32px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-link);
        text-underline-offset: 0.2em;
    }
    .map-jumps a:hover {
        text-decoration-thickness: 2px;
    }
    .map-jumps .bi {
        font-size: 17px;
    }
    .map-stage {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        scroll-margin-top: var(--vff-space-4);
    }
    .map-frame {
        position: relative;
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    /* La carte prend la hauteur laissée par la barre d'outils et la frise. */
    .map-frame :global(.rail-map-wrapper) {
        flex: 1 1 auto;
        min-height: 0;
    }
    .map-frame :global(.rail-map) {
        height: 100% !important;
    }
    .map-frame :global(.timeline),
    .map-toolbar {
        flex: none;
    }
    /* Plein écran (natif ou pseudo) : sans cadre. */
    .map-frame:fullscreen,
    .map-frame:global(.maplibregl-pseudo-fullscreen) {
        border: 0;
        border-radius: 0;
    }
    /* Sous la carte : le chapeau (ce que montre la carte, comment l'utiliser). */
    .map-lead {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-lead);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .route-section {
        scroll-margin-top: var(--vff-space-4);
    }

    /* Barre d'outils au-dessus de la carte : la recherche, le calque et « Intégrer » ne se
       superposent plus à la carte ni entre eux, à aucune largeur. */
    .map-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 16px;
        padding: 12px;
        border-bottom: 1px solid var(--vff-border);
        background-color: var(--vff-bg-elevated);
    }
    .toolbar-search {
        flex: 1 1 320px;
        max-width: 480px;
        min-width: 0;
    }
    .toolbar-tools {
        display: flex;
        align-items: center;
        gap: 8px 12px;
        margin-left: auto;
        min-width: 0;
    }
    .toolbar-view {
        flex: none;
        min-width: 0;
    }
    /* Calques posés sur la carte (dans son cadre, sous les boutons de MapLibre). La légende a un
       haut et un bas : elle défile au lieu de dépasser, et laisse voir l'échelle. */
    .legend-slot {
        position: absolute;
        top: 12px;
        bottom: 40px;
        left: 12px;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        pointer-events: none;
    }
    .legend-slot > :global(*) {
        pointer-events: auto;
    }
    .era-empty.today-only {
        flex-wrap: wrap;
        align-items: center;
    }

    .today-only p {
        margin: 0;
    }

    /* Légende des deux trajets comparés, en bas à droite de la carte (au-dessus de l'attribution). */
    .route-key-slot {
        position: absolute;
        right: 10px;
        bottom: 36px;
        z-index: var(--vff-z-raised);
        padding: 8px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
    }

    /* Messages sur la carte, centrés en haut, empilés quand plusieurs s'appliquent (accidents en
       échec, réseau d'aujourd'hui seulement, aide au zoom). */
    .map-notices {
        position: absolute;
        top: 12px;
        left: 50%;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        width: max-content;
        max-width: min(420px, calc(100% - 340px));
        transform: translateX(-50%);
        pointer-events: none;
    }
    .map-notices > * {
        pointer-events: auto;
    }
    .era-empty {
        display: flex;
        gap: 8px;
        align-items: baseline;
        max-width: 100%;
        margin: 0;
        padding: 8px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
    }

    .route-section {
        padding-block: var(--vff-section-space);
    }

    /* « Lire la carte » : définitions courtes, deux ou trois colonnes à filets. */
    .reading {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px 32px;
        margin: 0;
    }
    .reading-item {
        padding-top: 16px;
        border-top: 1px solid var(--vff-border-strong);
    }
    .reading dt {
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.35;
        color: var(--vff-text);
    }
    .reading dd {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }

    /* Sous la carte : chapeau, et « Intégrer » sur téléphone. */
    .map-after {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-4) var(--vff-space-6);
        padding-top: var(--vff-space-3);
    }
    .map-after .embed-btn {
        display: none;
    }

    /* Jusqu'à 1 400 px : recherche, vue, calques et « Intégrer » tiennent sur une rangée. */
    @media (max-width: 1400px) {
        .toolbar-search {
            flex-basis: 240px;
        }
    }
    @media (max-width: 900px) {
        .map-head {
            padding-block: var(--vff-space-4) var(--vff-space-3);
        }
        .toolbar-search {
            flex-basis: 240px;
            max-width: none;
        }
        .toolbar-tools {
            margin-left: 0;
        }
        /* Tablette : la légende est repliée, les messages descendent sous son bouton. */
        .map-notices {
            top: 60px;
            max-width: calc(100% - 24px);
        }
    }
    /* Téléphone : recherche et calques sur une rangée ; « Intégrer » (tâche d'ordinateur) passe sous
       la carte ; les raccourcis du titre aussi (la page continue juste en dessous). */
    @media (max-width: 600px) {
        /* Hauteur minimale plutôt que fixe : avec le choix de la vue et le résumé de la
           comparaison, le cadre grandit au lieu d'écraser la carte (360 px au moins). */
        .map-screen {
            height: auto;
            min-height: max(560px, calc(100dvh - var(--vff-header-h)));
            padding-bottom: var(--vff-space-4);
        }
        .map-frame :global(.rail-map-wrapper) {
            flex: 1 0 360px;
        }
        .map-jumps {
            display: none;
        }
        .map-toolbar {
            gap: 8px;
            padding: 8px;
        }
        /* Le choix de la vue prend sa propre rangée, sous la recherche et le calque. */
        .toolbar-view {
            flex: 1 1 100%;
            order: 3;
        }
        .toolbar-search {
            flex: 1 1 0;
        }
        .toolbar-tools {
            flex: none;
            gap: 6px;
        }
        .toolbar-tools .embed-btn {
            display: none;
        }
        .map-after .embed-btn {
            display: inline-flex;
        }
        .reading {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
