<script>
    import { onDestroy, onMount, tick, untrack } from 'svelte';
    import { MediaQuery } from 'svelte/reactivity';
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import RailMap from '$components/Map/RailMap.svelte';
    import SchematicMap from '$components/Map/SchematicMap.svelte';
    import TimelineSlider from '$components/Map/TimelineSlider.svelte';
    import EmbedCodeModal from '$components/Map/EmbedCodeModal.svelte';
    import LineDrawer from '$components/Map/LineDrawer.svelte';
    import SearchBox from '$components/Map/SearchBox.svelte';
    import MapLegend from '$components/Map/MapLegend.svelte';
    import RoutePlanner from '$components/Map/RoutePlanner.svelte';
    import RouteCompareKey from '$components/Map/RouteCompareKey.svelte';
    import EraViewSwitch from '$components/Map/EraViewSwitch.svelte';
    import EraCompareBar from '$components/Map/EraCompareBar.svelte';
    import EraCurtain from '$components/Map/EraCurtain.svelte';
    import EraPlayer from '$components/Map/EraPlayer.svelte';
    import MapListing from '$components/Map/MapListing.svelte';
    import LayerPicker from '$components/Map/LayerPicker.svelte';
    import PlacePanel from '$components/Map/PlacePanel.svelte';
    import { i18n } from '$lib/i18n.js';
    import { defaultPair, mapViews, readViewParams, writeViewParams } from '$lib/epochViews.js';
    import { accidentYears, countAccidents, fetchAccidents } from '$lib/accidentMap.js';
    import { LEVEL_CROSSING_MIN_ZOOM } from '$lib/levelCrossingMap.js';
    import { NO_ORM, activeLayers, layerNotices, readLayers, writeLayers } from '$lib/mapLayers.js';
    import { readTint, writeTint } from '$lib/eraTint.js';
    import { readMapView, writeMapView } from '$lib/mapView.js';
    import { bboxFromBounds } from '$lib/mapListing.js';
    import { pastEraFor, validPoint } from '$lib/placeHistory.js';
    import {
        CURTAIN_DEFAULT,
        DEFAULT_COMPARE_MODE,
        readCompareParams,
        writeCompareParams,
    } from '$lib/eraCurtain.js';
    import {
        DEFAULT_SPEED,
        createPlayback,
        isPlaybackGesture,
        openKmByYear,
        playbackYears,
        stepYear,
    } from '$lib/eraPlayback.js';

    // « Lire la carte » : ce que montre la carte, au présent (vocabulaire de la légende).
    const READING = ['era', 'views', 'color', 'line', 'sheet', 'route', 'layers'];

    // `schematic` : vue ferroviaire proposée (config maps.schematic, MapController).
    let {
        eras: erasProp = null,
        currentEraYear = 2030,
        dataVersion = 'v0',
        schematic = false,
    } = $props();
    // Vues du choix « Vue » ; la vue ferroviaire vient après celles de la carte.
    const views = $derived(mapViews(schematic));
    const reading = $derived(
        schematic ? [...READING.slice(0, 2), 'schema', ...READING.slice(2)] : READING,
    );
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
    // La fenêtre « Intégrer » (75 éléments) n'est montée qu'à sa première ouverture, puis gardée : le
    // premier rendu de la page ne la construit pas, et ses réglages restent d'une ouverture à l'autre.
    let embedModalUsed = $state(false);
    function openEmbedModal() {
        embedModalUsed = true;
        embedModalOpen = true;
    }
    // Calques facultatifs (bouton « Calques ») : ceux de VFF, cochés librement et superposés, et un
    // fond OpenRailwayMap au plus (lib/mapLayers.js).
    let dataLayers = $state([]);
    let ormLayer = $state(NO_ORM);
    // Teinte d'époque du fond (sépia discret pour les époques anciennes) ; `?tint=off` la coupe.
    let tintOn = $state(true);
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
    // Vue : une décennie ('era'), deux décennies comparées ('compare'), toutes ('stack'), plan
    // schématique d'une décennie ('schema', vue ferroviaire).
    let view = $state('era');
    // Décennies comparées (compareFrom < compareTo), choisies au passage à la comparaison.
    let compareFrom = $state(null);
    let compareTo = $state(null);
    // Présentation de la comparaison : rideau (une décennie de chaque côté) ou différences colorées.
    let compareMode = $state(DEFAULT_COMPARE_MODE);
    // Position du rideau (pour cent de la largeur) ; l'adresse la suit après le geste (`curtainShared`),
    // pas à chaque pixel (les navigateurs limitent les réécritures de l'adresse).
    let curtainPos = $state(CURTAIN_DEFAULT);
    let curtainShared = $state(CURTAIN_DEFAULT);
    // Vue dessinée par la carte et décrite par la légende : le rideau est une présentation de « Comparer ».
    const drawnView = $derived(view === 'compare' && compareMode === 'curtain' ? 'curtain' : view);
    const curtainLabel = (year) =>
        year === currentEraYear ? $i18n.t('maps.curtain.today') : String(year);
    const curtainInline = (year) =>
        year === currentEraYear ? $i18n.t('maps.compare.today_inline') : String(year);

    function setCompareMode(next) {
        // La fiche latérale (ouverte d'un clic sur le rideau) décrit une ligne à une décennie.
        if (next !== 'curtain') drawerLine = null;
        compareMode = next;
    }

    function setView(next) {
        // Un autre choix de vue quitte la lecture des époques (sans revenir à la vue d'avant).
        if (playbackOpen) closePlayback(false);
        if (next === 'compare' && compareFrom === null) {
            [compareFrom, compareTo] = defaultPair(selectedEra, eras, currentEraYear) ?? [];
        }
        // La fiche latérale décrit une ligne à UNE décennie : elle reste ouverte entre la carte et la
        // vue ferroviaire (même décennie), et se ferme dans les autres vues.
        if (next !== 'era' && next !== 'schema') drawerLine = null;
        // Une gare cherchée avant de changer de vue n'est pas rejointe de nouveau par la carte qui s'ouvre.
        if (next !== view) flyTarget = null;
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
            // `slug` : la vue ferroviaire vole vers la place de la gare sur le plan.
            flyTarget = {
                lng: picked.lng,
                lat: picked.lat,
                zoom: 13,
                key: Date.now(),
                slug: picked.slug,
            };
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
    const drawn = $derived(
        activeLayers({ data: dataLayers, orm: ormLayer }, showingToday, view === 'era'),
    );
    const showAccidents = $derived(drawn.accidents);
    // Passages à niveau : seul l'état d'aujourd'hui est connu (SNCF Réseau) ; hors de la carte
    // d'aujourd'hui, un message le dit et propose d'y revenir. Signalisation : état de SNCF Réseau
    // aujourd'hui, mises en service datées (Wikipédia) aux époques passées.
    const showLevelCrossings = $derived(drawn.levelCrossings);
    const showSignalling = $derived(drawn.signalling);
    // Vue (centre et zoom) demandée par l'adresse, lue avant la création de la carte : elle remplace
    // alors le cadrage de la France entière. `currentView` suit la carte (lien « Intégrer »).
    const urlView = typeof window === 'undefined' ? null : readMapView(window.location.search);
    const hasUrlView = Boolean(urlView?.center) || urlView?.zoom != null;
    let mapCenter = $state(urlView?.center ?? [2.5, 47.0]);
    let mapStartZoom = $state(urlView?.zoom ?? 5.5);
    let currentView = $state.raw(null);

    // L'adresse suit la vue à la fin de chaque déplacement : replaceState, donc sans entrée dans
    // l'historique du navigateur ni rechargement de la page.
    function writeViewToUrl(next) {
        currentView = next;
        const url = writeMapView(window.location.href, next);
        if (url !== window.location.href)
            window.history.replaceState(window.history.state, '', url);
    }

    // « Liste de ce qui est affiché » : alternative textuelle à la carte (lignes et gares de la zone
    // visible). Proposée pour la carte d'une époque et la comparaison ; `mapBbox` suit la carte à la fin
    // de chaque déplacement.
    let listingOpen = $state(false);
    let listingHeading = $state();
    let mapBbox = $state.raw(null);
    const listingAvailable = $derived(view === 'era' || view === 'compare');
    const listingTitle = (year) =>
        year === currentEraYear
            ? $i18n.t('maps.legend.title_current')
            : $i18n.t('maps.legend.title', { year });
    const listingEras = $derived(
        view === 'compare' && compareFrom !== null && compareTo !== null
            ? [compareFrom, compareTo].map((year) => ({ year, title: listingTitle(year) }))
            : [{ year: selectedEra, title: listingTitle(selectedEra) }],
    );

    async function toggleListing() {
        listingOpen = !listingOpen;
        if (!listingOpen) return;
        await tick();
        listingHeading?.scrollIntoView({ block: 'start' });
        listingHeading?.focus({ preventScroll: true });
    }

    // Fermée par son bouton : le focus revient au bouton qui l'a ouverte (celui de la barre d'outils ou
    // celui sous la carte, selon la largeur : le seul visible).
    async function closeListing() {
        listingOpen = false;
        await tick();
        /** @type {HTMLElement[]} */
        const toggles = [...document.querySelectorAll('[data-listing-toggle]')].map(
            (el) => /** @type {HTMLElement} */ (el),
        );
        toggles.find((el) => el.offsetParent !== null)?.focus();
    }

    // « Qu'y avait-il ici ? » : lieu interrogé (clic droit, appui long ou centre de la carte) et panneau.
    // `era` n'est fixée que pour la comparaison (côté du rideau touché) ; sur une seule époque, le panneau
    // suit la frise.
    let railMap = $state();
    let place = $state.raw(null);
    let lastPastEra = $state(null);
    const placeAvailable = $derived(view === 'era' || view === 'compare');
    const placeEra = $derived(view === 'era' ? selectedEra : (place?.era ?? compareTo));
    const pastEra = $derived(
        lastPastEra !== null && eras.includes(lastPastEra)
            ? lastPastEra
            : pastEraFor(eras, currentEraYear),
    );
    $effect(() => {
        if (view === 'era' && selectedEra !== currentEraYear) lastPastEra = selectedEra;
    });
    $effect(() => {
        if (!placeAvailable) place = null;
    });

    function openPlace(raw) {
        const point = validPoint(raw);
        if (!point) return;
        placeOpener = null;
        let era;
        if (view === 'compare') {
            // Rideau : l'époque du côté touché ; sinon (différences, clavier) la plus récente.
            era =
                drawnView === 'curtain' && raw.width > 0 && (raw.x / raw.width) * 100 < curtainPos
                    ? compareFrom
                    : compareTo;
        }
        drawerLine = null;
        place = { ...point, era };
    }
    // Ouvert par un bouton : le focus lui revient à la fermeture (sinon il revient à la carte).
    let placeOpener = null;
    function openPlaceAtCenter(event) {
        openPlace(railMap?.getCenter());
        placeOpener = event?.currentTarget ?? null;
    }
    async function closePlace() {
        const opener = placeOpener;
        placeOpener = null;
        place = null;
        await tick();
        if (opener?.isConnected && opener.offsetParent !== null) opener.focus();
    }

    function switchPlaceEra(target) {
        if (view !== 'era') setView('era');
        selectedEra = target;
    }

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

    function setTint(next) {
        tintOn = next;
        const url = writeTint(window.location.href, next);
        if (url !== window.location.href)
            window.history.replaceState(window.history.state, '', url);
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
    // La vue ferroviaire ne dessine pas les calques : aucun message de calque.
    const mapNotices = $derived(
        view === 'schema'
            ? []
            : layerNotices(drawn, {
                  showingToday,
                  accidentsFailed: accidentsState === 'error',
                  zoom: mapZoom,
                  minZoom: LEVEL_CROSSING_MIN_ZOOM,
              }).map((n) => ({ ...n, text: $i18n.t(n.textKey) })),
    );

    // ── Lecture des époques (lib/eraPlayback.js, EraPlayer.svelte) ──
    // La carte passe d'une décennie à la suivante, de la première ère reconstituée à aujourd'hui, sans se
    // recharger ; l'adresse suit (effet plus bas). Ouverte par le bouton lecture de la frise ; le rideau et
    // les autres vues laissent la place à la carte d'une décennie, puis reviennent en quittant la lecture.
    // Animations réduites demandées par le système : pas de lecture automatique, pas à pas seulement.
    const reducedMotion = new MediaQuery('(prefers-reduced-motion: reduce)', false);
    const playbackYearList = $derived(playbackYears(erasProp?.length ? erasProp : eras));
    const openKm = $derived(openKmByYear(erasProp));
    let playbackOpen = $state(false);
    let playbackPlaying = $state(false);
    let playbackEnded = $state(false);
    let playbackSpeed = $state(DEFAULT_SPEED);
    let playbackStatus = $state('');
    let playbackButton = $state();
    let viewBeforePlayback = null;
    // Seule l'ère suivante est préchargée, pendant la lecture.
    const preloadEra = $derived(
        playbackPlaying ? stepYear(playbackYearList, selectedEra, 1) : null,
    );
    const playbackYearText = (year) =>
        year === currentEraYear ? $i18n.t('maps.timeline.value_today') : String(year);

    const playback = createPlayback({
        years: () => playbackYearList,
        getYear: () => selectedEra,
        onStep: (year) => (selectedEra = year),
        onChange: ({ playing, ended }) => {
            playbackPlaying = playing;
            playbackEnded = ended;
            playbackStatus = playing
                ? $i18n.t('maps.playback.status_playing')
                : ended
                  ? $i18n.t('maps.playback.status_ended')
                  : $i18n.t('maps.playback.status_paused', { year: playbackYearText(selectedEra) });
        },
    });
    onDestroy(() => playback.destroy());

    async function openPlayback() {
        if (playbackOpen) {
            if (!reducedMotion.current) playback.toggle();
            return;
        }
        if (view !== 'era') {
            viewBeforePlayback = view;
            drawerLine = null;
            flyTarget = null;
            view = 'era';
        }
        playbackOpen = true;
        if (!reducedMotion.current) playback.play();
        await tick();
        (
            playbackButton ??
            /** @type {HTMLElement|null} */ (
                mapFrameEl?.querySelector('.era-player button:not(:disabled)')
            )
        )?.focus();
    }

    // Quitter : la vue d'avant revient (rideau compris) ; le focus revient au bouton de la frise s'il est là.
    async function closePlayback(restore = true) {
        playback.pause();
        playbackOpen = false;
        playbackStatus = '';
        const previous = viewBeforePlayback;
        viewBeforePlayback = null;
        if (!restore) return;
        if (previous) setView(previous);
        await tick();
        /** @type {HTMLElement|null} */
        const back =
            mapFrameEl?.querySelector('.timeline .play-btn') ??
            mapFrameEl?.querySelector('.toolbar-view button');
        back?.focus();
    }

    function stepPlayback(delta) {
        const moved = delta > 0 ? playback.next() : playback.prev();
        if (moved) playbackStatus = playbackYearText(selectedEra);
    }

    function setPlaybackSpeed(next) {
        playback.setSpeed(next);
        playbackSpeed = playback.speed;
    }

    // Au moindre geste hors des commandes de la lecture (appui, molette, touche), la lecture s'arrête.
    $effect(() => {
        if (!playbackPlaying) return;
        const stop = (/** @type {Event} */ e) => {
            if (isPlaybackGesture(e)) playback.pause();
        };
        const types = ['pointerdown', 'wheel', 'keydown'];
        for (const type of types)
            window.addEventListener(type, stop, { capture: true, passive: true });
        return () => {
            for (const type of types) window.removeEventListener(type, stop, { capture: true });
        };
    });

    // Système passé en animations réduites pendant la lecture : arrêt, pas à pas seulement.
    $effect(() => {
        if (reducedMotion.current) untrack(() => playback.pause());
    });

    // Le rideau entre dans l'adresse un court instant après le dernier mouvement.
    $effect(() => {
        const pos = curtainPos;
        const timer = setTimeout(() => (curtainShared = pos), 300);
        return () => clearTimeout(timer);
    });

    // Adresse partageable : ?era=1930, ?era=1930&compare=1980 (rideau, &curtain=30 pour sa position,
    // &show=changes pour les différences), ?view=stack, ?view=schema&era=1930
    // (vue ferroviaire), et ?layer=… pour chaque calque (lue au montage).
    onMount(() => {
        const fromUrl = readViewParams(window.location.search, eras, views);
        if (fromUrl.era !== null) selectedEra = fromUrl.era;
        if (fromUrl.view === 'compare') {
            compareFrom = fromUrl.era;
            compareTo = fromUrl.compare;
            // ?show=changes : différences colorées ; sinon le rideau, à ?curtain= (pour cent) ou au milieu.
            const shown = readCompareParams(window.location.search);
            compareMode = shown.mode;
            curtainPos = shown.curtain;
            curtainShared = shown.curtain;
        }
        view = fromUrl.view;
        // Calques demandés par un lien (fiche ligne : « Voir la signalisation sur la carte »).
        const layers = readLayers(window.location.search);
        ormLayer = layers.orm;
        setDataLayers(layers.data);
        tintOn = readTint(window.location.search);
    });

    $effect(() => {
        const state =
            view === 'compare'
                ? { view, era: compareFrom, compare: compareTo }
                : { view, era: selectedEra };
        let next = writeViewParams(window.location.href, state, eras[eras.length - 1]);
        next = writeCompareParams(
            next,
            view === 'compare' ? { mode: compareMode, curtain: curtainShared } : null,
        );
        // La vue ferroviaire a son propre cadrage : ni centre ni zoom dans son adresse.
        if (view === 'schema') next = writeMapView(next, null);
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
                            <EraViewSwitch bind:view={() => view, setView} {views} />
                        </div>
                        <div class="toolbar-tools">
                            <!-- Les calques se posent sur la carte géographique : pas sur le plan. -->
                            {#if view !== 'schema'}
                                <LayerPicker
                                    bind:data={() => dataLayers, setDataLayers}
                                    bind:orm={() => ormLayer, setOrmLayer}
                                    bind:tint={() => tintOn, setTint}
                                />
                            {/if}
                            {#if placeAvailable}
                                <button
                                    type="button"
                                    class="vff-btn secondary embed-btn"
                                    title={$i18n.t('maps.here.button_title')}
                                    aria-expanded={place !== null}
                                    onclick={openPlaceAtCenter}
                                >
                                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                                    <span class="embed-label">{$i18n.t('maps.here.button')}</span>
                                </button>
                            {/if}
                            {#if listingAvailable}
                                <button
                                    type="button"
                                    class="vff-btn secondary embed-btn"
                                    title={$i18n.t('maps.listing.button')}
                                    data-listing-toggle
                                    aria-expanded={listingOpen}
                                    aria-controls={listingOpen ? 'liste-carte' : undefined}
                                    onclick={toggleListing}
                                >
                                    <i class="bi bi-list-ul" aria-hidden="true"></i>
                                    <span class="embed-label">{$i18n.t('maps.listing.button')}</span
                                    >
                                </button>
                            {/if}
                            <button
                                type="button"
                                class="vff-btn secondary embed-btn"
                                aria-haspopup="dialog"
                                title={$i18n.t('maps.embed.button')}
                                onclick={openEmbedModal}
                            >
                                <i class="bi bi-code-slash" aria-hidden="true"></i>
                                <span class="embed-label">{$i18n.t('maps.embed.button')}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Légende, messages, fiche latérale : posés sur la carte comme sur le plan. -->
                    {#snippet mapOverlays()}
                        {#if drawnView === 'curtain'}
                            <EraCurtain
                                bind:value={curtainPos}
                                left={curtainLabel(compareFrom)}
                                right={curtainLabel(compareTo)}
                                leftText={curtainInline(compareFrom)}
                                rightText={curtainInline(compareTo)}
                            />
                        {/if}
                        <div class="legend-slot">
                            <MapLegend
                                era={view === 'compare' ? compareFrom : selectedEra}
                                view={drawnView}
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
                        {#if place && placeAvailable}
                            <PlacePanel
                                point={place}
                                era={placeEra}
                                currentEra={currentEraYear}
                                {pastEra}
                                version={dataVersion}
                                onClose={closePlace}
                                onSwitchEra={switchPlaceEra}
                            />
                        {/if}
                    {/snippet}

                    {#if view === 'schema'}
                        <SchematicMap
                            era={selectedEra}
                            currentEra={currentEraYear}
                            {hidden}
                            selectedLine={drawerLine?.slug ?? null}
                            onLineClick={(props) => (drawerLine = props)}
                            {flyTarget}
                            fitBounds={FRANCE_BOUNDS}
                            {fitPadding}
                            fullscreenContainer={mapFrameEl}
                        >
                            {@render mapOverlays()}
                        </SchematicMap>
                    {:else}
                        <RailMap
                            bind:this={railMap}
                            era={view === 'compare' ? compareFrom : selectedEra}
                            view={drawnView}
                            compareEra={compareTo}
                            curtain={curtainPos}
                            geolocate
                            currentEra={currentEraYear}
                            {hidden}
                            mode={colorMode}
                            overlay={ormLayer}
                            tint={tintOn}
                            accidents={showAccidents ? accidentData : null}
                            levelCrossings={showLevelCrossings}
                            signalling={showSignalling}
                            greenways={drawn.greenways}
                            onZoomChange={(z) => (mapZoom = z)}
                            onViewChange={writeViewToUrl}
                            onBoundsChange={(bounds) => (mapBbox = bboxFromBounds(bounds))}
                            onEraShown={(year) => playback.shown(year)}
                            {preloadEra}
                            center={mapCenter}
                            zoom={mapStartZoom}
                            {dataVersion}
                            {flyTarget}
                            {route}
                            {earlierRoute}
                            height="100%"
                            fitBounds={hasUrlView ? null : FRANCE_BOUNDS}
                            {fitPadding}
                            selectedLine={drawerLine?.slug ?? null}
                            fullscreenContainer={mapFrameEl}
                            onLineClick={(props) => {
                                drawerLine = props;
                                place = null;
                            }}
                            onPlacePick={openPlace}
                            placeMarker={place}
                        >
                            {@render mapOverlays()}
                        </RailMap>
                    {/if}

                    {#if view === 'compare'}
                        <EraCompareBar
                            {eras}
                            currentEra={currentEraYear}
                            bind:from={compareFrom}
                            bind:to={compareTo}
                            bind:mode={() => compareMode, setCompareMode}
                        />
                    {:else if playbackOpen && view === 'era'}
                        <!-- Lecture des époques : la barre de lecture remplace la frise, la carte reste entière. -->
                        <EraPlayer
                            year={selectedEra}
                            years={playbackYearList}
                            todayEra={currentEraYear}
                            openKm={openKm[selectedEra] ?? null}
                            playing={playbackPlaying}
                            ended={playbackEnded}
                            speed={playbackSpeed}
                            stepOnly={reducedMotion.current}
                            status={playbackStatus}
                            bind:playButton={playbackButton}
                            onToggle={() => playback.toggle()}
                            onPrev={() => stepPlayback(-1)}
                            onNext={() => stepPlayback(1)}
                            onSpeed={setPlaybackSpeed}
                            onClose={() => closePlayback()}
                        />
                    {:else if view === 'era' || view === 'schema'}
                        <TimelineSlider
                            {eras}
                            {emptyYears}
                            currentEra={currentEraYear}
                            labels={eraLabels}
                            bind:value={selectedEra}
                            onPlay={openPlayback}
                            playing={playbackPlaying}
                        />
                    {/if}
                </div>
            </section>
        </div>

        <div class="vff-container map-after">
            <p class="map-lead">{$i18n.t('maps.hero.subtitle')}</p>
            {#if placeAvailable}
                <button
                    type="button"
                    class="vff-btn secondary small embed-btn"
                    aria-expanded={place !== null}
                    onclick={openPlaceAtCenter}
                >
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    {$i18n.t('maps.here.button')}
                </button>
            {/if}
            {#if listingAvailable}
                <button
                    type="button"
                    class="vff-btn secondary small embed-btn"
                    data-listing-toggle
                    aria-expanded={listingOpen}
                    aria-controls={listingOpen ? 'liste-carte' : undefined}
                    onclick={toggleListing}
                >
                    <i class="bi bi-list-ul" aria-hidden="true"></i>
                    {$i18n.t('maps.listing.button')}
                </button>
            {/if}
            <button
                type="button"
                class="vff-btn secondary small embed-btn"
                aria-haspopup="dialog"
                onclick={openEmbedModal}
            >
                <i class="bi bi-code-slash" aria-hidden="true"></i>
                {$i18n.t('maps.embed.button')}
            </button>
        </div>

        {#if listingOpen && listingAvailable}
            <div class="vff-container">
                <MapListing
                    eras={listingEras}
                    bbox={mapBbox}
                    {hidden}
                    version={dataVersion}
                    bind:headingEl={listingHeading}
                    onClose={closeListing}
                />
            </div>
        {/if}

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

        {#if embedModalUsed}
            <EmbedCodeModal
                bind:open={embedModalOpen}
                defaultEra={selectedEra}
                mapView={currentView ??
                    (hasUrlView ? { center: mapCenter, zoom: mapStartZoom } : null)}
                {eras}
                currentEra={currentEraYear}
            />
        {/if}

        <SectionBlock
            kind="alt"
            id="lire-la-carte"
            title={$i18n.t('maps.read.title')}
            maxWidth="1100px"
        >
            <dl class="reading">
                {#each reading as key (key)}
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
        container-type: inline-size;
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
    /* Barre de moins de 1 010 px (écran de 1 024 px) : « Intégrer » ne garde que son icône, son nom
       reste lu par les lecteurs d'écran et s'affiche au survol. La recherche cède 40 px. Les quatre
       éléments restent sur une rangée au lieu de faire descendre les calques et « Intégrer ». */
    @container (max-width: 1010px) {
        .toolbar-search {
            flex-basis: 200px;
        }
        .toolbar-tools .embed-btn {
            width: 44px;
            padding-inline: 0;
            justify-content: center;
        }
        .toolbar-tools .embed-label {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
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
        /* Légende dépliée : au plus la moitié de la hauteur de la carte, le reste défile. */
        .legend-slot {
            bottom: auto;
            height: calc(50% - 12px);
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
