<script>
    import { onMount } from 'svelte';
    import RailMap from '$components/Map/RailMap.svelte';
    import TimelineSlider from '$components/Map/TimelineSlider.svelte';
    import MapLegend from '$components/Map/MapLegend.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Page d'intégration iframe - pleine fenêtre, sans header/footer.
     *
     * Query params supportés :
     *   ?era=2000             (défaut 2030)
     *   ?showVanished=1       (défaut 0)
     *   ?center=2.5,47.0      (lng,lat ; défaut "2.5,47.0")
     *   ?zoom=5.5             (défaut 5.5)
     *   ?theme=dark|light     (défaut : valeur courante / système)
     *   ?timeline=0           (défaut 1 = visible)
     *   ?legend=0             (défaut 1 = visible)
     *   ?attribution=0        (défaut 1 = badge "Propulsé par VFF")
     *   ?mode=electrification (couleur des tracés : traffic | electrification | gauge ; défaut traffic)
     */

    // Ères servies par MapController::embed (toutes les décennies 1900 → 2030).
    let { eras: erasProp = null, currentEraYear = 2030, dataVersion = null } = $props();
    const ERAS = $derived(
        erasProp?.length ? erasProp.map((e) => e.year) : [1990, 2000, 2010, 2020, 2030],
    );
    const emptyYears = $derived(
        erasProp?.length ? erasProp.filter((e) => e.has_data === false).map((e) => e.year) : [],
    );

    let selectedEra = $state(currentEraYear);
    let showVanished = $state(false);
    // Catégories masquées : sans ?showVanished=1, les portions fermées /
    // déposées sont masquées (comportement historique de l'embed).
    let hidden = $state(['abandoned', 'dismantled']);
    // Couleur des tracés : trafic, électrification ou écartement (sélecteur de la légende).
    let colorMode = $state('traffic');
    let center = $state([2.5, 47.0]);
    let zoom = $state(5.5);
    let showTimeline = $state(true);
    let showLegend = $state(true);
    let showAttribution = $state(true);
    let frameEl = $state();

    function readParam(name) {
        if (typeof window === 'undefined') return null;
        return new URL(window.location.href).searchParams.get(name);
    }

    function parseBool(v, fallback) {
        if (v === null || v === undefined) return fallback;
        return v === '1' || v === 'true' || v === 'yes';
    }

    onMount(() => {
        const era = Number.parseInt(readParam('era'), 10);
        if (Number.isFinite(era) && ERAS.includes(era)) selectedEra = era;

        showVanished = parseBool(readParam('showVanished'), false);
        hidden = showVanished ? [] : ['abandoned', 'dismantled'];
        showTimeline = parseBool(readParam('timeline'), true);
        showLegend = parseBool(readParam('legend'), true);
        showAttribution = parseBool(readParam('attribution'), true);
        const modeParam = readParam('mode');
        if (['traffic', 'electrification', 'gauge'].includes(modeParam)) colorMode = modeParam;

        const centerParam = readParam('center');
        if (centerParam) {
            const [lng, lat] = centerParam.split(',').map(Number);
            if (Number.isFinite(lng) && Number.isFinite(lat)) center = [lng, lat];
        }

        const zoomParam = Number.parseFloat(readParam('zoom'));
        if (Number.isFinite(zoomParam)) zoom = zoomParam;

        const theme = readParam('theme');
        if (theme === 'dark' || theme === 'light') {
            document.documentElement.setAttribute('data-theme', theme);
        }

        // On bascule en mode plein écran SEULEMENT pour cette page (sinon les
        // règles `overflow:hidden; height:100%` polluent tout le site, car le
        // CSS Svelte est bundlé globalement).
        document.documentElement.classList.add('vff-embed-mode');
        return () => {
            document.documentElement.classList.remove('vff-embed-mode');
        };
    });
</script>

<svelte:head>
    <title>VFF - {$i18n.t('maps.meta_title')}</title>
    <meta name="robots" content="noindex,nofollow" />
</svelte:head>

<div class="embed-root" bind:this={frameEl}>
    <RailMap
        era={selectedEra}
        {hidden}
        mode={colorMode}
        {dataVersion}
        {center}
        {zoom}
        height="100%"
        narrowBounds={[
            [-5.2, 41.3],
            [9.6, 51.1],
        ]}
        fullscreenContainer={frameEl}
    />

    {#if showLegend}
        <div class="embed-legend">
            <MapLegend era={selectedEra} bind:hidden bind:mode={colorMode} />
        </div>
    {/if}

    {#if showAttribution}
        <a
            class="embed-credit"
            href="https://vff-project.org"
            target="_blank"
            rel="noopener"
            title={$i18n.t('common.brand')}
        >
            <span class="logo-dot"></span>
            {$i18n.t('maps.embed.powered_by')} <strong>VFF</strong>
        </a>
    {/if}

    {#if showTimeline}
        <TimelineSlider eras={ERAS} {emptyYears} bind:value={selectedEra} />
    {/if}
</div>

<style>
    .embed-root {
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100vh;
        width: 100vw;
        background-color: var(--vff-bg);
    }
    .embed-root :global(.rail-map-wrapper) {
        flex: 1;
        border-radius: 0;
        border: none;
        box-shadow: none;
    }
    .embed-root :global(.rail-map) {
        height: 100% !important;
    }

    .embed-legend {
        position: absolute;
        top: 14px;
        left: 14px;
        z-index: 10;
    }

    /* En haut à droite, à côté des boutons de la carte : en bas, il recouvrait la fin
       de la frise (« 2030 ») et l'attribution OpenStreetMap. */
    .embed-credit {
        position: absolute;
        top: 12px;
        right: 54px;
        z-index: 10;
        display: flex;
        align-items: center;
        gap: 6px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        padding: 6px 12px;
        font-family: var(--vff-font-display);
        font-size: 12px;
        color: var(--vff-text-muted);
        text-decoration: none;
        box-shadow: 0 4px 12px var(--vff-shadow);
        transition: all var(--vff-transition);
    }
    .embed-credit:hover {
        color: var(--vff-accent);
        border-color: var(--vff-accent);
    }
    .embed-credit strong {
        color: var(--vff-accent);
        font-weight: 700;
    }
    .embed-credit .logo-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: var(--vff-accent);
    }

    /* Quand la timeline n'est pas affichée, on enlève sa zone occupée. */
    .embed-root :global(.timeline) {
        flex-shrink: 0;
    }
</style>
