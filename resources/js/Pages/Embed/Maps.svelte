<script>
    import { onMount, untrack } from 'svelte';
    import RailMap from '$components/Map/RailMap.svelte';
    import TimelineSlider from '$components/Map/TimelineSlider.svelte';
    import MapLegend from '$components/Map/MapLegend.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Page d'intégration iframe - pleine fenêtre, sans en-tête ni pied de page.
     *
     * Paramètres d'URL :
     *   ?era=2000             (défaut : décennie actuelle)
     *   ?showVanished=1       (défaut 0)
     *   ?center=2.5,47.0      (lng,lat ; défaut : France entière, Corse comprise)
     *   ?zoom=5.5             (défaut : France entière)
     *   ?theme=dark|light     (défaut : réglage du visiteur)
     *   ?timeline=0           (défaut 1 = visible)
     *   ?legend=0             (défaut 1 = visible)
     *   ?attribution=0        (défaut 1 = lien « Carte : VFF »)
     *   ?mode=electrification (couleur des tracés : traffic | electrification | gauge ; défaut traffic)
     */

    // Ères servies par MapController::embed (toutes les décennies 1830 → 2030).
    let { eras: erasProp = null, currentEraYear = 2030, dataVersion = null } = $props();
    const ERAS = $derived(
        erasProp?.length ? erasProp.map((e) => e.year) : [1990, 2000, 2010, 2020, 2030],
    );
    const emptyYears = $derived(
        erasProp?.length ? erasProp.filter((e) => e.has_data === false).map((e) => e.year) : [],
    );
    const eraLabels = $derived(
        Object.fromEntries((erasProp ?? []).filter((e) => e.label).map((e) => [e.year, e.label])),
    );

    const FRANCE_BOUNDS = [
        [-5.2, 41.3],
        [9.6, 51.1],
    ];
    // Légende ouverte sur un cadre large : la France est cadrée à droite de la légende.
    const fitPadding = (w) =>
        w > 900 && parseBool(readParam('legend'), true)
            ? { top: 24, right: 56, bottom: 32, left: 300 }
            : { top: 60, right: 52, bottom: 28, left: 16 };

    // Décennie affichée : la décennie actuelle à l'ouverture (ou celle de l'URL, lue au montage).
    let selectedEra = $state(untrack(() => currentEraYear));
    // Catégories masquées : sans ?showVanished=1, les portions fermées /
    // déposées sont masquées (comportement historique de l'embed).
    let hidden = $state(['abandoned', 'dismantled']);
    // Couleur des tracés : trafic, électrification ou écartement (sélecteur de la légende).
    let colorMode = $state('traffic');
    let center = $state([2.5, 47.0]);
    let zoom = $state(5.5);
    // Sans ?center ni ?zoom : la France entière, quel que soit le format du cadre (lu avant la
    // création de la carte, qui se fait au montage de RailMap, avant celui de cette page).
    const fitFrance = readParam('center') === null && readParam('zoom') === null;
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

        hidden = parseBool(readParam('showVanished'), false) ? [] : ['abandoned', 'dismantled'];
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
    <title>{$i18n.t('maps.embed.iframe_title')}</title>
    <meta name="robots" content="noindex,nofollow" />
</svelte:head>

<main class="embed-root" bind:this={frameEl}>
    <h1 class="vff-sr-only">{$i18n.t('maps.embed.iframe_title')}</h1>
    <RailMap
        era={selectedEra}
        currentEra={currentEraYear}
        {hidden}
        mode={colorMode}
        {dataVersion}
        {center}
        {zoom}
        height="100%"
        fitBounds={fitFrance ? FRANCE_BOUNDS : null}
        {fitPadding}
        popupLinksNewTab
        fullscreenContainer={frameEl}
    >
        {#if showLegend}
            <div class="legend-slot">
                <MapLegend
                    era={selectedEra}
                    currentEra={currentEraYear}
                    bind:hidden
                    bind:mode={colorMode}
                />
            </div>
        {/if}

        {#if showAttribution}
            <a
                class="embed-credit"
                href="https://vff-project.org/maps"
                target="_blank"
                rel="noopener"
            >
                {$i18n.t('maps.embed.credit')}<i
                    class="bi bi-box-arrow-up-right vff-ext"
                    aria-hidden="true"
                ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span>
            </a>
        {/if}
    </RailMap>

    {#if showTimeline}
        <TimelineSlider
            eras={ERAS}
            {emptyYears}
            currentEra={currentEraYear}
            labels={eraLabels}
            bind:value={selectedEra}
        />
    {/if}
</main>

<style>
    .embed-root {
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100vh;
        height: 100dvh;
        width: 100%;
        background-color: var(--vff-bg);
    }
    .embed-root :global(.rail-map-wrapper) {
        flex: 1;
        min-height: 0;
    }
    .embed-root :global(.rail-map) {
        height: 100% !important;
    }
    .embed-root :global(.timeline) {
        flex-shrink: 0;
    }

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

    /* Lien vers VFF : en haut à droite, à gauche des boutons de la carte. */
    .embed-credit {
        position: absolute;
        top: 10px;
        right: 52px;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        padding: 4px 10px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        text-decoration: none;
        transition: border-color var(--vff-transition);
    }
    .embed-credit:hover {
        border-color: var(--vff-accent-ink);
        text-decoration: underline;
    }
</style>
