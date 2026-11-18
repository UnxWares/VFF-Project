<script>
    import { onMount } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import RailMap from '$components/Map/RailMap.svelte';
    import TimelineSlider from '$components/Map/TimelineSlider.svelte';
    import EmbedCodeModal from '$components/Map/EmbedCodeModal.svelte';
    import LineDrawer from '$components/Map/LineDrawer.svelte';
    import SearchBox from '$components/Map/SearchBox.svelte';
    import MapLegend from '$components/Map/MapLegend.svelte';
    import RoutePlanner from '$components/Map/RoutePlanner.svelte';
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    const features = [
        {
            icon: 'bi-sliders',
            titleKey: 'maps.features.time_title',
            textKey: 'maps.features.time_text',
        },
        {
            icon: 'bi-bezier',
            titleKey: 'maps.features.lines_title',
            textKey: 'maps.features.lines_text',
        },
        {
            icon: 'bi-train-freight-front',
            titleKey: 'maps.features.materials_title',
            textKey: 'maps.features.materials_text',
        },
        {
            icon: 'bi-images',
            titleKey: 'maps.features.archives_title',
            textKey: 'maps.features.archives_text',
        },
    ];

    let { eras: erasProp = null, currentEraYear = 2030, dataVersion = 'v0' } = $props();
    // TOUTES les décennies sont sélectionnables (1900 → 2030) ; celles sans
    // tracé reconstitué sont signalées (marqueur estompé + bandeau) au lieu
    // d'être masquées. Fallback aux 5 ères historiques si pas de prop.
    const eras = $derived(
        erasProp?.length ? erasProp.map((e) => e.year) : [1990, 2000, 2010, 2020, 2030],
    );
    const emptyYears = $derived(
        erasProp?.length ? erasProp.filter((e) => e.has_data === false).map((e) => e.year) : [],
    );
    let selectedEra = $state(currentEraYear);
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

    // France métropolitaine et Corse, cadrée à l'ouverture sur téléphone (cf. RailMap).
    const FRANCE_BOUNDS = [
        [-5.2, 41.3],
        [9.6, 51.1],
    ];

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
        <PageHero
            eyebrow={$i18n.t('maps.hero.eyebrow')}
            title={$i18n.t('maps.hero.title')}
            subtitle={$i18n.t('maps.hero.subtitle')}
            status={$i18n.t('maps.hero.status')}
        />

        <section class="map-stage">
            <div class="map-frame" bind:this={mapFrameEl}>
                <RailMap
                    era={selectedEra}
                    currentEra={currentEraYear}
                    {hidden}
                    mode={colorMode}
                    {overlay}
                    {dataVersion}
                    {flyTarget}
                    {route}
                    height="560px"
                    narrowBounds={FRANCE_BOUNDS}
                    fullscreenContainer={mapFrameEl}
                    onLineClick={(props) => (drawerLine = props)}
                >
                    <LineDrawer bind:line={drawerLine} />
                </RailMap>
                <div class="map-legend-slot">
                    <MapLegend
                        era={selectedEra}
                        currentEra={currentEraYear}
                        bind:hidden
                        bind:mode={colorMode}
                    />
                </div>
                {#if selectedIsEmpty}
                    <div class="era-empty" role="status">
                        <i class="bi bi-hourglass-split"></i>
                        {$i18n.t('maps.era.no_data', { year: selectedEra })}
                    </div>
                {/if}
                <div class="map-search">
                    <SearchBox onPick={onSearchPick} />
                </div>
                <div class="map-actions">
                    <label class="overlay-select">
                        <i class="bi bi-layers"></i>
                        <select bind:value={overlay} aria-label={$i18n.t('maps.overlay.label')}>
                            {#each OVERLAY_OPTIONS as opt}
                                <option value={opt.value}>{$i18n.t(opt.labelKey)}</option>
                            {/each}
                        </select>
                    </label>
                    <button
                        type="button"
                        class="map-action-btn"
                        onclick={() => (embedModalOpen = true)}
                    >
                        <i class="bi bi-code-slash"></i>
                        <span>{$i18n.t('maps.embed.button')}</span>
                    </button>
                </div>
                <TimelineSlider {eras} {emptyYears} bind:value={selectedEra} />
            </div>
            <RoutePlanner
                year={selectedEra}
                minYear={eras[0]}
                maxYear={eras[eras.length - 1]}
                {onRoute}
                onClear={() => (route = null)}
            />
        </section>

        <EmbedCodeModal bind:open={embedModalOpen} defaultEra={selectedEra} {eras} />

        <SectionBlock title={$i18n.t('maps.features.title')} maxWidth="1100px">
            <div class="features-grid">
                {#each features as f}
                    <IconCard icon={f.icon} title={$i18n.t(f.titleKey)} text={$i18n.t(f.textKey)} />
                {/each}
            </div>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('maps.tech.title')} maxWidth="780px">
            <p class="tech-paragraph">{$i18n.t('maps.tech.paragraph')}</p>
        </SectionBlock>

        <SectionBlock title={$i18n.t('maps.progress.title')} maxWidth="700px">
            <div class="progress-card">
                <i class="bi bi-flag-fill"></i>
                <div>
                    <h3>{$i18n.t('maps.progress.phase')}</h3>
                    <p>
                        {@html $i18n.t('maps.progress.description', {
                            roadmap_url:
                                'https://github.com/UnxWares/VFF-Project/blob/main/ROADMAP.md',
                        })}
                    </p>
                </div>
            </div>
        </SectionBlock>

        <SectionBlock kind="cta" maxWidth="640px">
            <h2>{$i18n.t('maps.cta.title')}</h2>
            <p>{$i18n.t('maps.cta.text')}</p>
            <a
                href="https://discord.com/invite/6SwTfXBx4h"
                target="_blank"
                rel="noopener"
                class="vff-btn on-cta"
            >
                <i class="bi bi-discord"></i>
                {$i18n.t('maps.cta.discord')}
            </a>
        </SectionBlock>
    </main>
</Layout>

<style>
    .map-stage {
        max-width: 1180px;
        margin: -20px auto 0;
        padding: 0 16px 40px;
        position: relative;
        z-index: 2;
    }
    .map-frame {
        position: relative;
        display: flex;
        flex-direction: column;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        box-shadow: 0 24px 64px var(--vff-shadow-strong);
        overflow: hidden;
    }
    /* En plein écran (natif ou pseudo), on remplit l'écran et la carte prend tout l'espace dispo. */
    .map-frame:fullscreen,
    .map-frame:global(.maplibregl-pseudo-fullscreen) {
        border-radius: 0;
        border: none;
        box-shadow: none;
    }
    .map-frame:fullscreen :global(.rail-map-wrapper),
    .map-frame:global(.maplibregl-pseudo-fullscreen) :global(.rail-map-wrapper) {
        flex: 1;
        border-radius: 0;
        border: none;
        box-shadow: none;
    }
    .map-frame:fullscreen :global(.rail-map),
    .map-frame:global(.maplibregl-pseudo-fullscreen) :global(.rail-map) {
        height: 100% !important;
    }
    .map-legend-slot {
        position: absolute;
        top: 16px;
        left: 16px;
        z-index: 10;
    }
    .era-empty {
        position: absolute;
        top: 64px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9;
        max-width: min(460px, calc(100% - 32px));
        padding: 8px 14px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-left: 3px solid var(--vff-accent);
        border-radius: var(--vff-radius-sm);
        box-shadow: 0 4px 12px var(--vff-shadow);
        font-family: var(--vff-font-body);
        font-size: 13px;
        color: var(--vff-text);
        text-align: center;
    }
    .era-empty i {
        color: var(--vff-accent);
        margin-right: 4px;
    }

    .map-search {
        position: absolute;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 11;
    }
    .map-actions {
        position: absolute;
        top: 16px;
        right: 64px;
        z-index: 10;
        display: flex;
        gap: 8px;
    }
    .map-action-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 600;
        color: var(--vff-text-muted);
        box-shadow: 0 4px 12px var(--vff-shadow);
        transition: all var(--vff-transition);
    }
    .map-action-btn:hover {
        color: var(--vff-accent);
        border-color: var(--vff-accent);
    }
    .map-action-btn i {
        font-size: 14px;
    }
    .overlay-select {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px 6px 12px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 600;
        color: var(--vff-text-muted);
        box-shadow: 0 4px 12px var(--vff-shadow);
    }
    .overlay-select i {
        font-size: 14px;
        color: var(--vff-accent);
    }
    .overlay-select select {
        background: transparent;
        border: 0;
        font-family: inherit;
        font-size: inherit;
        font-weight: inherit;
        color: var(--vff-text);
        cursor: pointer;
        padding: 4px 0;
    }
    .overlay-select select:focus {
        outline: none;
    }

    /* Téléphone (≤ 700 px, seuil où la légende se replie) : la recherche, la légende,
       le sélecteur de calque et « Intégrer » se superposaient en haut de la carte (calque
       coupé, légende cachée sous la recherche). Recherche sur toute la largeur, légende
       repliée dessous, calque et intégration dans une barre entre la carte et la frise. */
    @media (max-width: 700px) {
        .map-search {
            top: 10px;
            left: 10px;
            right: 52px;
            transform: none;
        }
        .map-search :global(.search-box) {
            min-width: 0;
            max-width: none;
        }
        .map-legend-slot {
            top: 58px;
            left: 10px;
        }
        .era-empty {
            top: 104px;
        }
        .map-actions {
            position: static;
            order: 1;
            flex-wrap: wrap;
            padding: 12px 16px 0;
            background-color: var(--vff-bg-elevated);
            border-top: 1px solid var(--vff-border);
        }
        .map-frame :global(.timeline) {
            order: 2;
            border-top: 0;
        }
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }
    .tech-paragraph {
        font-size: 17px;
        line-height: 1.65;
        text-align: justify;
        color: var(--vff-text);
    }
    .progress-card {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 24px;
    }
    .progress-card i {
        font-size: 32px;
        color: var(--vff-accent);
    }
    .progress-card h3 {
        margin: 0 0 4px;
    }
    .progress-card p {
        margin: 0;
        color: var(--vff-text-muted);
        line-height: 1.5;
    }
    .progress-card :global(p a) {
        color: var(--vff-accent);
        text-decoration: none;
    }
    .progress-card :global(p a:hover) {
        text-decoration: underline;
    }
    /* `.vff-btn` est défini globalement dans general.css. */
    @media (max-width: 600px) {
        .tech-paragraph {
            text-align: left;
        }
        .progress-card {
            flex-direction: column;
        }
    }
</style>
