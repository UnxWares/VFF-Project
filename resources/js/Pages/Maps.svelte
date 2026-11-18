<script>
    import { onMount, untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import RailMap from '$components/Map/RailMap.svelte';
    import TimelineSlider from '$components/Map/TimelineSlider.svelte';
    import EmbedCodeModal from '$components/Map/EmbedCodeModal.svelte';
    import LineDrawer from '$components/Map/LineDrawer.svelte';
    import SearchBox from '$components/Map/SearchBox.svelte';
    import MapLegend from '$components/Map/MapLegend.svelte';
    import RoutePlanner from '$components/Map/RoutePlanner.svelte';
    import { i18n } from '$lib/i18n.js';

    // « Lire la carte » : ce que montre la carte, au présent (vocabulaire de la légende).
    const READING = ['era', 'color', 'line', 'sheet', 'route', 'layers'];

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
    let overlay = $state('none');
    let drawerLine = $state(null);
    let flyTarget = $state(null);
    // Itinéraire historique affiché (réponse /api/v1/route) : la carte passe à son ère.
    let route = $state(null);

    function onRoute(result) {
        route = result;
        if (eras.includes(result.era_year)) selectedEra = result.era_year;
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
    const fitPadding = (w) =>
        w > 900
            ? { top: 24, right: 56, bottom: 32, left: 300 }
            : { top: 60, right: 52, bottom: 28, left: 16 };

    const OVERLAY_OPTIONS = [
        { value: 'none', labelKey: 'maps.overlay.none' },
        { value: 'orm-standard', labelKey: 'maps.overlay.orm_standard' },
        { value: 'orm-maxspeed', labelKey: 'maps.overlay.orm_maxspeed' },
        { value: 'orm-signals', labelKey: 'maps.overlay.orm_signals' },
        { value: 'orm-electrification', labelKey: 'maps.overlay.orm_electrification' },
        { value: 'orm-gauge', labelKey: 'maps.overlay.orm_gauge' },
    ];

    function readEraFromUrl() {
        if (typeof window === 'undefined') return null;
        const param = new URL(window.location.href).searchParams.get('era');
        const parsed = Number.parseInt(param, 10);
        return Number.isFinite(parsed) && eras.includes(parsed) ? parsed : null;
    }

    function writeEraToUrl(year) {
        if (typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        if (year === eras[eras.length - 1]) {
            url.searchParams.delete('era');
        } else {
            url.searchParams.set('era', String(year));
        }
        window.history.replaceState(window.history.state, '', url);
    }

    onMount(() => {
        const fromUrl = readEraFromUrl();
        if (fromUrl !== null) selectedEra = fromUrl;
    });

    $effect(() => writeEraToUrl(selectedEra));
</script>

<svelte:head>
    <title>{$i18n.t('maps.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('maps.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={$i18n.t('maps.hero.title')} subtitle={$i18n.t('maps.hero.subtitle')} />

        <section class="map-stage" id="carte" aria-labelledby="map-title">
            <div class="vff-container">
                <h2 class="vff-sr-only" id="map-title">{$i18n.t('maps.map_title')}</h2>
                <div class="map-frame" bind:this={mapFrameEl}>
                    <div class="map-toolbar">
                        <div class="toolbar-search">
                            <SearchBox onPick={onSearchPick} />
                        </div>
                        <div class="toolbar-tools">
                            <label class="overlay-label" for="map-overlay">
                                <i class="bi bi-layers" aria-hidden="true"></i>
                                <span class="overlay-label-text"
                                    >{$i18n.t('maps.overlay.label')}</span
                                >
                            </label>
                            <select
                                id="map-overlay"
                                class="vff-select overlay-select"
                                bind:value={overlay}
                            >
                                {#each OVERLAY_OPTIONS as opt (opt.value)}
                                    <option value={opt.value}>{$i18n.t(opt.labelKey)}</option>
                                {/each}
                            </select>
                            <button
                                type="button"
                                class="vff-btn secondary"
                                aria-haspopup="dialog"
                                onclick={() => (embedModalOpen = true)}
                            >
                                <i class="bi bi-code-slash" aria-hidden="true"></i>
                                {$i18n.t('maps.embed.button')}
                            </button>
                        </div>
                    </div>

                    <RailMap
                        era={selectedEra}
                        currentEra={currentEraYear}
                        {hidden}
                        mode={colorMode}
                        {overlay}
                        {dataVersion}
                        {flyTarget}
                        {route}
                        height="clamp(400px, 64vh, 720px)"
                        fitBounds={FRANCE_BOUNDS}
                        {fitPadding}
                        selectedLine={drawerLine?.slug ?? null}
                        fullscreenContainer={mapFrameEl}
                        onLineClick={(props) => (drawerLine = props)}
                    >
                        <div class="legend-slot">
                            <MapLegend
                                era={selectedEra}
                                currentEra={currentEraYear}
                                bind:hidden
                                bind:mode={colorMode}
                            />
                        </div>
                        {#if selectedIsEmpty}
                            <p class="era-empty">
                                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                {$i18n.t('maps.era.no_data', { year: selectedEra })}
                            </p>
                        {/if}
                        <LineDrawer bind:line={drawerLine} currentEra={currentEraYear} />
                    </RailMap>

                    <TimelineSlider
                        {eras}
                        {emptyYears}
                        currentEra={currentEraYear}
                        labels={eraLabels}
                        bind:value={selectedEra}
                    />
                </div>
            </div>
        </section>

        <div class="vff-container route-section">
            <RoutePlanner
                year={selectedEra}
                minYear={eras[0]}
                maxYear={eras[eras.length - 1]}
                currentEra={currentEraYear}
                mapAnchor="carte"
                {onRoute}
                onClear={() => (route = null)}
            />
        </div>

        <EmbedCodeModal
            bind:open={embedModalOpen}
            defaultEra={selectedEra}
            {eras}
            currentEra={currentEraYear}
        />

        <SectionBlock kind="alt" title={$i18n.t('maps.read.title')} maxWidth="1100px">
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
    /* La carte, élément principal de la page, sur la grille commune (même bord gauche que le
       bandeau de titre), sans chevaucher le bandeau. */
    .map-stage {
        padding-top: var(--vff-block-space);
        scroll-margin-top: 80px;
    }
    .map-frame {
        position: relative;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background-color: var(--vff-bg-elevated);
    }
    /* Plein écran (natif ou pseudo) : la carte prend toute la hauteur entre la barre et la frise. */
    .map-frame:fullscreen,
    .map-frame:global(.maplibregl-pseudo-fullscreen) {
        border: 0;
        border-radius: 0;
    }
    .map-frame:fullscreen :global(.rail-map-wrapper),
    .map-frame:global(.maplibregl-pseudo-fullscreen) :global(.rail-map-wrapper) {
        flex: 1;
    }
    .map-frame:fullscreen :global(.rail-map),
    .map-frame:global(.maplibregl-pseudo-fullscreen) :global(.rail-map) {
        height: 100% !important;
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
    .overlay-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
        white-space: nowrap;
    }
    .overlay-label .bi {
        color: var(--vff-text-muted);
    }
    .overlay-select {
        width: auto;
        max-width: 20rem;
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
    .era-empty {
        position: absolute;
        top: 12px;
        left: 50%;
        z-index: 2;
        display: flex;
        gap: 8px;
        align-items: baseline;
        max-width: min(420px, calc(100% - 340px));
        margin: 0;
        padding: 8px 12px;
        transform: translateX(-50%);
        border: 1px solid var(--vff-border-strong);
        border-left: 3px solid var(--vff-accent);
        border-radius: var(--vff-radius-sm);
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

    /* Tablette et téléphone : la recherche sur toute la largeur, puis le calque et « Intégrer »
       côte à côte ; la légende est repliée, le bandeau « pas encore de données » descend sous elle. */
    @media (max-width: 900px) {
        .toolbar-search {
            flex-basis: 100%;
            max-width: none;
        }
        .toolbar-tools {
            width: 100%;
            margin-left: 0;
        }
        .overlay-select {
            flex: 1;
            max-width: none;
        }
        .era-empty {
            top: 60px;
            max-width: calc(100% - 24px);
        }
    }
    @media (max-width: 600px) {
        .reading {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    /* Très petit écran : « Calque » reste lu mais seule l'icône est affichée. */
    @media (max-width: 400px) {
        .overlay-label-text {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }
    }
</style>
