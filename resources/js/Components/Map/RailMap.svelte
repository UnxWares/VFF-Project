<script>
    import maplibregl from 'maplibre-gl';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { MediaQuery } from 'svelte/reactivity';
    import { get } from 'svelte/store';
    import { page, router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { isOfflineView, neutralMapStyle, reportPartialMap } from '$lib/offlineMap.svelte.js';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { colon, formatNumber } from '$lib/typo.js';
    import { mapControlsLocale } from '$lib/mapControls.js';
    import { ROUTE_STYLES } from '$lib/routeCompare.js';
    import {
        electrificationInfo,
        escapeHtml,
        isTrue,
        stationPopupHtml,
        statusLabel,
        trafficInfo,
    } from './lineInfo.js';
    // Fonds de carte historiques IGN (Licence Ouverte 2.0) : overlays + contrôle avec opacité.
    import {
        HISTORIC_OVERLAY_DEFS,
        addHistoricBasemapControl,
    } from '$lib/historicBasemapControl.js';
    import { HISTORIC_LAYER_ID } from '$lib/historicBasemaps.js';
    // Teinte d'époque du fond de carte (sépia discret pour les époques anciennes).
    import { NEUTRAL_TINT, WASH_COLOR, eraTint, rasterTintPaint } from '$lib/eraTint.js';
    // Liseré foncé sous les tracés pleins (contraste des couleurs claires sur le fond clair).
    import { CASING_OPACITY, casingLayer } from '$lib/lineCasing.js';
    // Palette trafic × statut partagée avec la légende et la mini-carte.
    import {
        ACTIVE_STATUSES,
        HERITAGE_COLOR,
        STATUS_COLOR,
        STATUS_STYLES,
        THREATENED_COLOR,
        REOPENING_COLOR,
        segmentColorExpr,
        isLgvExpr,
        isVelorailExpr,
        stationColorExpr,
        stationFillExpr,
        stationStrokeWidthExpr,
        VELORAIL_DASH,
        NOT_COVERED,
        DRAW_ORDER,
        modeColorExpr,
    } from '$lib/railStyle.js';
    // Vues par époque (comparaison, toutes les époques empilées) : calques ajoutés, style intact.
    import {
        COMPARE_CHANGES,
        compareColorExpr,
        compareOpacityExpr,
        compareSortExpr,
        compareWidthExpr,
        stackColorExpr,
    } from '$lib/epochViews.js';
    // Calque facultatif des accidents (points sobres, zone pâle quand seule la commune est connue).
    import {
        ACCIDENT_AREA_LAYER_ID,
        ACCIDENT_POINT_LAYER_ID,
        ACCIDENT_SOURCE_ID,
        accidentLayers,
        accidentPopupHtml,
        accidentYearFilter,
        accidentYears,
    } from '$lib/accidentMap.js';
    // Calques facultatifs du réseau d'aujourd'hui : passages à niveau, signalisation (SNCF Réseau).
    import {
        LEVEL_CROSSING_LAYER_ID,
        LEVEL_CROSSING_MIN_ZOOM,
        LEVEL_CROSSING_SOURCE_ID,
        levelCrossingLayer,
        levelCrossingPopupHtml,
        levelCrossingTilesUrl,
    } from '$lib/levelCrossingMap.js';
    import {
        SIGNALLING_DASH_GROUPS,
        SIGNALLING_LAYER_IDS,
        SIGNALLING_SOURCE_ID,
        signallingLayers,
        signallingPopupHtml,
        signallingTilesUrl,
    } from '$lib/signallingMap.js';
    // Calque facultatif « Voies vertes » (OpenStreetMap, sur d'anciennes emprises), toutes époques.
    import {
        GREENWAY_LAYER_IDS,
        GREENWAY_LINE_LAYER_ID,
        GREENWAY_MIN_ZOOM,
        GREENWAY_SOURCE_ID,
        greenwayLayers,
        greenwayPopupHtml,
        greenwayTilesUrl,
    } from '$lib/greenwayMap.js';

    /**
     * Composant carte interactive du réseau ferré VFF.
     *
     * Consomme les endpoints :
     *   GET /api/v1/eras/{era}/tiles/{z}/{x}/{y}.mvt   → tracés (couche `vff-lines`)
     *   GET /api/v1/eras/{era}/stations.geojson        → gares (couche `vff-stations`)
     *
     * Fond cartographique :
     *   - Si VITE_MAPTILER_KEY défini : MapTiler Basic (vectoriel)
     *   - Sinon : MapLibre demotiles (dev / sans clé API)
     */

    let {
        era = 2030,
        height = '640px',
        center = [2.5, 47.0], // Centre approximatif France métropolitaine
        zoom = 5.5,
        showVanished = false,
        showHeritage = true,
        showFreight = true,
        showAbandoned = false,
        showDismantled = false,
        // Overlay raster optionnel : 'none' | 'orm-standard' | 'orm-maxspeed' |
        // 'orm-signals' | 'orm-electrification' | 'orm-gauge'
        overlay = 'none',
        // Élément à passer en plein écran. Si null → on prend le wrapper interne.
        // Utile pour qu'un parent (.map-frame) puisse inclure le TimelineSlider + légende en fullscreen.
        fullscreenContainer = null,
        // Cache-bust : si on passe un timestamp, il est ajouté en query sur les
        // URLs MVT/GeoJSON pour forcer le rafraîchissement après un import.
        dataVersion = null,
        // Callback appelé au clic sur une ligne. Si fourni, on N'affiche PAS la
        // popup par défaut (le parent gère le rendu via un drawer custom).
        onLineClick = null,
        // Slot enfants pour overlays positionnés dans le wrapper de la carte
        // (drawer fiche, etc.). Ne s'étend pas au-delà du canvas carto.
        children = null,
        // Target coords externes (search). { lng, lat, zoom? } ou null.
        // À chaque changement, on flyTo.
        flyTarget = null,
        // Catégories masquées via la légende (cf. TOGGLE_KEYS de railStyle.js) :
        // 'passenger' | 'mixed' | 'freight' | 'unknown' | 'heritage' |
        // 'service_track' | 'construction' | 'abandoned' | 'dismantled'.
        // null = mode historique piloté par les booléens show* ci-dessus.
        hidden = null,
        // Décennie « actuelle » (eras.is_current). Les halos de campagne (ligne
        // menacée, réouverture) décrivent l'état ACTUEL d'une ligne : ils ne
        // s'affichent qu'à cette décennie, jamais sur la carte de 1900 ou 1960.
        currentEra = 2030,
        // Couleur des tracés : 'traffic' (défaut) | 'electrification' | 'gauge' (cf. MAP_MODES).
        mode = 'traffic',
        // Itinéraire historique à tracer (réponse de /api/v1/route) ou null.
        route = null,
        // Comparaison de deux époques : itinéraire de l'époque la plus ancienne, dessiné sous
        // `route` dans un autre style (routeCompare.js), ou null.
        earlierRoute = null,
        // Emprise [[ouest, sud], [est, nord]] cadrée à l'ouverture quand la carte fait moins
        // de 700 px de large (téléphone) ; au-dessus, centre et zoom ci-dessus. null : jamais.
        narrowBounds = null,
        // Emprise cadrée à l'ouverture à toutes les largeurs (prioritaire sur center / zoom et
        // narrowBounds) : la France entière, Corse comprise, quelle que soit la hauteur de la carte.
        fitBounds = null,
        // Marges du cadrage (px) : objet MapLibre, ou fonction (largeur, hauteur) => objet, pour
        // laisser la place de la légende ouverte sur ordinateur.
        fitPadding = null,
        // Ligne mise en évidence (slug) : celle dont la fiche latérale est ouverte.
        selectedLine = null,
        // Liens des bulles ouverts dans un nouvel onglet (carte intégrée dans un autre site).
        popupLinksNewTab = false,
        // Vue : 'era' (une décennie, couches habituelles), 'compare' (changements entre `era`
        // et `compareEra`) ou 'stack' (toutes les décennies empilées).
        view = 'era',
        // Seconde décennie de la comparaison (la plus récente ; `era` est la plus ancienne).
        compareEra = null,
        // Bouton « Me localiser » (sur action du visiteur seulement ; position gardée dans le navigateur).
        geolocate = false,
        // Accidents à afficher (FeatureCollection de GET /api/v1/accidents.geojson) ou null (calque
        // masqué). Filtrés selon la vue : jusqu'à l'ère, entre les deux ères comparées, ou tous.
        accidents = null,
        // Calque « Passages à niveau » (tuiles à partir du zoom 9) : le parent ne l'active qu'à
        // l'ère courante, seul état connu.
        levelCrossings = false,
        // Calque « Signalisation » : tranches de SNCF Réseau à l'ère courante, mises en service datées
        // (Wikipédia) à une ère passée ; le réseau habituel est alors estompé pour que les couleurs se lisent.
        signalling = false,
        // Calque « Voies vertes » : tracés d'OpenStreetMap sur l'emprise de lignes disparues ou
        // fermées, état actuel superposé à n'importe quelle époque.
        greenways = false,
        // Rappelé avec le zoom à la fin de chaque déplacement (le parent affiche alors une aide,
        // par exemple « zoomez pour voir les passages à niveau »).
        onZoomChange = null,
        // Teinte d'époque du fond (lib/eraTint.js) voulue par le visiteur. Coupée aussi quand le
        // système demande un contraste renforcé, hors de la vue « une décennie » et sous un fond
        // historique de l'IGN (rendu d'origine).
        tint = false,
    } = $props();

    // Préférences du système, suivies en direct.
    const moreContrast = new MediaQuery('(prefers-contrast: more)', false);
    const reducedMotion = new MediaQuery('(prefers-reduced-motion: reduce)', false);
    // Un fond historique de l'IGN est affiché (couche ajoutée par HistoricBasemapPicker).
    let historicShown = $state(false);
    const tintSettings = $derived(
        eraTint(era, {
            enabled: tint && !moreContrast.current && !historicShown,
            view,
            today: currentEra,
        }),
    );

    // Géolocalisation refusée ('denied') ou impossible ('unavailable') : message sur la carte.
    let geoError = $state(null);

    /**
     * Rendu en trois couches superposées (de bas en haut) :
     *   - vff-lines-gone   : déposées / disparues (dismantled, razed) - pointillés gris
     *   - vff-lines-closed : fermées, voie en place (abandoned)      - tirets rouges
     *   - vff-lines-active : en service, voie de service, construction - trait plein,
     *                        COULEUR = trafic de la portion à la décennie affichée
     *                        (voyageurs / mixte / fret / inconnu), vert si patrimoine.
     */
    //   - vff-lines-velorail : exploitée par une association sans trains (vélorail) - pointillés verts
    const LINE_LAYERS = [
        'vff-lines-gone',
        'vff-lines-closed',
        'vff-lines-velorail',
        'vff-lines-active',
    ];
    const LINE_LAYER_OPACITY = {
        'vff-lines-gone': 0.75,
        'vff-lines-closed': 0.85,
        'vff-lines-velorail': 1,
        'vff-lines-active': 1,
    };

    /** Ensemble effectif des catégories masquées (légende + anciens booléens). */
    function hiddenSet() {
        const keys = [...(hidden ?? [])];
        if (showFreight === false) keys.push('freight');
        if (showHeritage === false) keys.push('heritage', 'velorail');
        // Anciens toggles : fermées/déposées masquées seulement si le parent
        // ne pilote pas la légende (`hidden` = null) et ne demande pas les disparues.
        if (hidden == null && !showVanished) {
            if (!showAbandoned) keys.push('abandoned');
            if (!showDismantled) keys.push('dismantled');
        }
        return new Set(keys);
    }

    // Filtre « rien » valide (['==', 1, 0] est lu comme un filtre legacy invalide).
    const NEVER = ['==', ['get', 'status'], '__none__'];

    function heritageClause(h) {
        return h.has('heritage') ? ['!=', ['get', 'is_heritage'], true] : true;
    }

    function buildActiveFilter() {
        const h = hiddenSet();
        const hiddenTraffic = ['mixed', 'freight', 'unknown'].filter((k) => h.has(k));
        const branches = [];
        // Patrimoine (prioritaire sur le trafic) : visible sauf si masqué.
        if (!h.has('heritage')) {
            branches.push([
                'all',
                ['==', ['get', 'is_heritage'], true],
                ['match', ['get', 'status'], ACTIVE_STATUSES, true, false],
            ]);
        }
        branches.push([
            'all',
            ['!=', ['get', 'is_heritage'], true],
            ['match', ['get', 'status'], ACTIVE_STATUSES, true, false],
            // LGV : catégorie à part dans la légende ; sinon, filtre par trafic du réseau classique.
            [
                'case',
                isLgvExpr(),
                !h.has('lgv'),
                hiddenTraffic.length
                    ? [
                          'match',
                          ['coalesce', ['get', 'traffic_type'], 'unknown'],
                          hiddenTraffic,
                          false,
                          true,
                      ]
                    : true,
            ],
        ]);
        if (!h.has('service_track')) branches.push(['==', ['get', 'status'], 'service_track']);
        if (!h.has('construction')) branches.push(['==', ['get', 'status'], 'construction']);
        return ['all', NOT_COVERED, heritageClause(h), ['any', ...branches]];
    }

    function buildClosedFilter() {
        const h = hiddenSet();
        if (h.has('abandoned')) return NEVER;
        // Une portion exploitée par une association (vélorail) n'est pas « fermée » : couche verte dédiée.
        return [
            'all',
            NOT_COVERED,
            ['==', ['get', 'status'], 'abandoned'],
            ['!=', ['get', 'is_heritage'], true],
        ];
    }

    function buildVelorailFilter() {
        return hiddenSet().has('velorail') ? NEVER : ['all', NOT_COVERED, isVelorailExpr()];
    }

    function buildGoneFilter() {
        const h = hiddenSet();
        if (h.has('dismantled')) return NEVER;
        return [
            'all',
            NOT_COVERED,
            ['match', ['get', 'status'], ['dismantled', 'razed'], true, false],
            heritageClause(h),
        ];
    }

    // Halos de campagne soutenue par VFF : attributs de la LIGNE (lines.is_threatened,
    // lines.advocacy), donc identiques à toutes les décennies. Ils ne valent que
    // pour la décennie actuelle (sinon : halo rouge « menacée » en 1900, halo
    // turquoise « réouverture » en 1960 sur une ligne alors en service).
    function buildThreatenedFilter(year = era) {
        return year === currentEra
            ? ['all', NOT_COVERED, ['==', ['get', 'is_threatened'], true]]
            : NEVER;
    }

    function buildReopeningFilter(year = era) {
        return year === currentEra
            ? ['all', NOT_COVERED, ['==', ['get', 'advocacy_kind'], 'reopening']]
            : NEVER;
    }

    const SELECTED_LAYER_ID = 'vff-lines-selected';
    // Liseré des tracés en service (une couche pour toute la source) : mêmes filtres et même ordre de
    // dessin que `vff-lines-active`, posé juste dessous. Les couches en tirets n'en ont pas (lineCasing.js).
    const CASING_LAYER_ID = 'vff-lines-casing';

    function selectedFilter(slug) {
        return slug ? ['==', ['get', 'line_slug'], slug] : NEVER;
    }

    const LINE_FILTERS = {
        [CASING_LAYER_ID]: buildActiveFilter,
        'vff-lines-gone': buildGoneFilter,
        'vff-lines-closed': buildClosedFilter,
        'vff-lines-velorail': buildVelorailFilter,
        'vff-lines-active': buildActiveFilter,
    };

    // Largeur : LGV plus épaisse (en plus de sa couleur dédiée), voie de service plus fine.
    function lineWidthExpr(base) {
        const factor = [
            'case',
            isLgvExpr(),
            1.7,
            ['==', ['get', 'status'], 'service_track'],
            0.7,
            1,
        ];
        return [
            'interpolate',
            ['linear'],
            ['zoom'],
            4,
            ['*', base[0], factor],
            8,
            ['*', base[1], factor],
            12,
            ['*', base[2], factor],
            16,
            ['*', base[3], factor],
        ];
    }

    // ── Overlays raster externes ──────────────────────────────────────────
    // OpenRailwayMap (CC-BY-SA-2.0) : infrastructure, vitesses, signaux, etc.
    const ORM_VARIANTS = ['standard', 'maxspeed', 'signals', 'electrification', 'gauge'];
    const OVERLAY_DEFS = {
        ...Object.fromEntries(
            ORM_VARIANTS.map((v) => [
                `orm-${v}`,
                {
                    tiles: [`https://a.tiles.openrailwaymap.org/${v}/{z}/{x}/{y}.png`],
                    attribution:
                        '© <a href="https://www.openrailwaymap.org/">OpenRailwayMap</a> CC-BY-SA',
                    minzoom: 2,
                    maxzoom: 19,
                },
            ]),
        ),
        // Cartes historiques IGN : 'ign-cassini', 'ign-etatmajor40', 'ign-scan50-1950', 'ign-ortho-…'
        ...HISTORIC_OVERLAY_DEFS,
    };
    const OVERLAY_SOURCE_ID = 'vff-overlay';
    const OVERLAY_LAYER_ID = 'vff-overlay-layer';

    let mapContainer = $state();
    let wrapperEl = $state();
    let map = null;
    let popup = null;

    const TILES_BASE = '/api/v1/eras';
    const FADE_MS = 350;

    // Couche prête (évènement `load` reçu). NB : `map.isStyleLoaded()` vaut false
    // tant que des tuiles chargent → l'utiliser comme garde faisait IGNORER un
    // changement d'ère (ou d'overlay) demandé pendant un chargement.
    let layersReady = false;

    // Fin du signal « carte sur fond neutre » (lib/offlineMap.svelte.js), appelée quand la carte disparaît.
    let releasePartialMap = null;

    /**
     * @param {boolean} offline page lue sans réseau : fond neutre, aucune tuile ni style d'un tiers
     * @returns {string | import('maplibre-gl').StyleSpecification}
     */
    function buildStyle(offline) {
        if (offline) return neutralMapStyle({ dark: theme.isDark });
        const key = import.meta.env.VITE_MAPTILER_KEY;
        if (key) {
            return `https://api.maptiler.com/maps/basic-v2/style.json?key=${key}`;
        }
        // Fond carto par défaut : OpenStreetMap raster (look iconique).
        // `maxzoom: 19` est essentiel - sinon MapLibre tente de fetcher z=20+
        // qui n'existe pas → 404 + overzoom flou.
        return {
            version: 8,
            sources: {
                'osm-raster': {
                    type: 'raster',
                    tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                    tileSize: 256,
                    maxzoom: 19,
                    attribution: get(i18n).t('common.osm_attribution', {
                        link: '<a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }),
                },
            },
            layers: [{ id: 'osm', type: 'raster', source: 'osm-raster' }],
            // OpenFreeMap sert des polices Noto Sans complètes (vs demotiles qui
            // renvoyait des 404 sur "Open Sans Semibold").
            glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
        };
    }

    function bustParam() {
        return dataVersion ? `?v=${dataVersion}` : '';
    }

    function tilesUrlForEra(year) {
        return `${window.location.origin}${TILES_BASE}/${year}/tiles/{z}/{x}/{y}.mvt${bustParam()}`;
    }

    function compareTilesUrl(from, to) {
        return `${window.location.origin}${TILES_BASE}/${from}/compare/${to}/tiles/{z}/{x}/{y}.mvt${bustParam()}`;
    }

    function stackTilesUrl() {
        return `${window.location.origin}${TILES_BASE}/stacked/tiles/{z}/{x}/{y}.mvt${bustParam()}`;
    }

    function stationsUrlForEra(year) {
        return `${TILES_BASE}/${year}/stations.geojson${bustParam()}`;
    }

    function addVffLayers(year) {
        // ── Source tracés (MVT) ───────────────────────────────────────────
        map.addSource('vff-lines', {
            type: 'vector',
            tiles: [tilesUrlForEra(year)],
            minzoom: 0,
            maxzoom: 14,
        });

        // ── Source gares (GeoJSON) ────────────────────────────────────────
        map.addSource('vff-stations', {
            type: 'geojson',
            data: stationsUrlForEra(year),
        });

        // ── Halo « campagne de réouverture » soutenue par VFF ──────────────

        map.addLayer({
            id: 'vff-lines-reopening',

            type: 'line',

            source: 'vff-lines',

            'source-layer': 'vff-lines',

            filter: buildReopeningFilter(year),

            layout: { 'line-cap': 'round', 'line-join': 'round' },

            paint: {
                'line-color': REOPENING_COLOR,

                'line-width': ['interpolate', ['linear'], ['zoom'], 5, 6, 10, 12, 14, 20],

                'line-blur': 3,

                'line-opacity': 0.55,
            },
        });

        // ── Halo « ligne menacée » (sous le tracé principal) ──────────────
        map.addLayer({
            id: 'vff-lines-threatened',
            type: 'line',
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: buildThreatenedFilter(year),
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': THREATENED_COLOR, // rouge mobilisation
                'line-width': ['interpolate', ['linear'], ['zoom'], 5, 6, 10, 12, 14, 20],
                'line-blur': 3,
                'line-opacity': 0.55,
            },
        });

        // ── Ligne sélectionnée (fiche latérale ouverte) : liseré sombre sous ses tracés, y
        //    compris les tronçons partagés avec une autre ligne. Filtre seul : rien à recalculer.
        map.addLayer({
            id: SELECTED_LAYER_ID,
            type: 'line',
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: selectedFilter(selectedLine),
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': '#111827',
                'line-width': ['interpolate', ['linear'], ['zoom'], 4, 5, 8, 7, 12, 10, 16, 14],
                'line-opacity': 0.9,
            },
        });

        // ── Rails : déposées / disparues (pointillés, sous les autres) ────
        const goneDash = STATUS_STYLES.find((st) => st.key === 'dismantled').dash;
        map.addLayer({
            id: 'vff-lines-gone',
            type: 'line',
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: buildGoneFilter(),
            layout: { 'line-sort-key': DRAW_ORDER },
            paint: {
                'line-color': [
                    'match',
                    ['get', 'status'],
                    'razed',
                    STATUS_COLOR.razed,
                    STATUS_COLOR.dismantled,
                ],
                'line-width': lineWidthExpr([1, 1.6, 2.6, 4]),
                'line-dasharray': goneDash,
                'line-opacity': LINE_LAYER_OPACITY['vff-lines-gone'],
                'line-opacity-transition': { duration: FADE_MS },
            },
        });

        // ── Rails : fermées, voie en place (tirets rouges) ────────────────
        map.addLayer({
            id: 'vff-lines-closed',
            type: 'line',
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: buildClosedFilter(),
            layout: { 'line-sort-key': DRAW_ORDER },
            paint: {
                'line-color': STATUS_COLOR.abandoned,
                'line-width': lineWidthExpr([1.2, 2, 3.2, 5]),
                'line-dasharray': STATUS_STYLES.find((st) => st.key === 'abandoned').dash,
                'line-opacity': LINE_LAYER_OPACITY['vff-lines-closed'],
                'line-opacity-transition': { duration: FADE_MS },
            },
        });

        // ── Rails : vélorail exploité par une association (pointillés verts) ──
        map.addLayer({
            id: 'vff-lines-velorail',
            type: 'line',
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: buildVelorailFilter(),
            layout: { 'line-cap': 'round', 'line-join': 'round', 'line-sort-key': DRAW_ORDER },
            paint: {
                'line-color': HERITAGE_COLOR,
                'line-width': lineWidthExpr([1.4, 2.4, 3.8, 5.5]),
                'line-dasharray': VELORAIL_DASH,
                'line-opacity': LINE_LAYER_OPACITY['vff-lines-velorail'],
                'line-opacity-transition': { duration: FADE_MS },
            },
        });

        // ── Liseré foncé sous les tracés en service (une couche pour toute la source) ──
        map.addLayer(
            casingLayer({
                id: CASING_LAYER_ID,
                source: 'vff-lines',
                sourceLayer: 'vff-lines',
                filter: buildActiveFilter(),
                sortKey: DRAW_ORDER,
                width: lineWidthExpr([1.5, 2.5, 4, 6]),
                opacityTransitionMs: FADE_MS,
            }),
        );

        // ── Rails : en service - couleur = trafic de la portion ───────────
        map.addLayer({
            id: 'vff-lines-active',
            type: 'line',
            source: 'vff-lines',
            'source-layer': 'vff-lines',
            filter: buildActiveFilter(),
            // Voie partagée : la portion la plus active (LGV > mixte > fret > inconnu) au-dessus.
            layout: { 'line-cap': 'round', 'line-join': 'round', 'line-sort-key': DRAW_ORDER },
            paint: {
                'line-color': segmentColorExpr(),
                'line-width': lineWidthExpr([1.5, 2.5, 4, 6]),
                'line-opacity': LINE_LAYER_OPACITY['vff-lines-active'],
                'line-opacity-transition': { duration: FADE_MS },
            },
        });

        // ── Layer gares (seulement en zoomant : illisibles en vue d'ensemble) ──
        map.addLayer({
            id: 'vff-stations-points',
            type: 'circle',
            source: 'vff-stations',
            minzoom: 7,
            paint: {
                'circle-radius': [
                    'interpolate',
                    ['linear'],
                    ['zoom'],
                    7,
                    2.5,
                    9,
                    3.5,
                    11,
                    4.5,
                    14,
                    7,
                ],
                // Fond blanc ; grisé pour une gare « incertaine » (aucune voie de l'ère ne
                // confirme son ouverture : jamais montrée ouverte).
                'circle-color': stationFillExpr(),
                // Couleur = statut de la gare à la décennie (railStyle.js STATION_STYLES,
                // mêmes teintes que les lignes) : orange=voyageurs, vert=touristique,
                // bleu=fret seul ; incertaine = ardoise ; fermée ou statut inconnu = gris
                // neutre, jamais orange.
                'circle-stroke-color': stationColorExpr(),
                'circle-stroke-width': stationStrokeWidthExpr(),
                'circle-opacity': 1,
                'circle-stroke-opacity': 1,
                'circle-opacity-transition': { duration: FADE_MS },
                'circle-stroke-opacity-transition': { duration: FADE_MS },
            },
        });

        // ── Layer labels gares (apparaît à zoom élevé) ────────────────────
        map.addLayer({
            id: 'vff-stations-labels',
            type: 'symbol',
            source: 'vff-stations',
            minzoom: 10,
            layout: {
                'text-field': ['get', 'name'],
                'text-size': 13,
                'text-offset': [0, 1.2],
                'text-anchor': 'top',
                'text-font': ['Noto Sans Bold'],
            },
            paint: {
                'text-color': '#1a1a1a',
                'text-halo-color': '#ffffff',
                'text-halo-width': 2,
                'text-opacity': 1,
                'text-opacity-transition': { duration: FADE_MS },
            },
        });
    }

    /**
     * Applique un overlay raster (OpenRailwayMap, …) ou le retire.
     * Insère le layer JUSTE après le fond cartographique pour qu'il reste
     * sous nos polylignes orange.
     */
    function applyOverlay(mode) {
        if (!map || !layersReady) return;

        // Toujours retirer l'overlay existant avant d'en ajouter un nouveau.
        if (map.getLayer(OVERLAY_LAYER_ID)) map.removeLayer(OVERLAY_LAYER_ID);
        if (map.getSource(OVERLAY_SOURCE_ID)) map.removeSource(OVERLAY_SOURCE_ID);

        if (mode === 'none' || !OVERLAY_DEFS[mode]) return;

        const def = OVERLAY_DEFS[mode];
        map.addSource(OVERLAY_SOURCE_ID, {
            type: 'raster',
            tiles: def.tiles,
            tileSize: 256,
            attribution: def.attribution,
            minzoom: def.minzoom,
            maxzoom: def.maxzoom,
        });
        // Insère AVANT le layer des lignes pour qu'il passe en-dessous.
        const beforeId = ['vff-lines-threatened', ...LINE_LAYERS].find((id) => map.getLayer(id));
        map.addLayer(
            {
                id: OVERLAY_LAYER_ID,
                type: 'raster',
                source: OVERLAY_SOURCE_ID,
                paint: { 'raster-opacity': 0.75 },
            },
            beforeId,
        );
    }

    // ── Teinte d'époque du fond ───────────────────────────────────────────
    const TINT_LAYER_ID = 'vff-era-tint';
    const TINT_MS = 600;
    const RASTER_TINT_PROPS = Object.keys(rasterTintPaint(NEUTRAL_TINT));

    /**
     * Voile couleur papier posé juste au-dessus du fond : après la couche raster `osm`, ou sous
     * le premier libellé d'un fond vectoriel. Les fonds historiques, OpenRailwayMap et les tracés,
     * insérés plus tard sous les tracés, restent au-dessus de lui.
     */
    function addTintLayer() {
        const layers = map.getStyle()?.layers ?? [];
        const osmIndex = layers.findIndex((l) => l.id === 'osm');
        const before =
            osmIndex >= 0 ? layers[osmIndex + 1]?.id : layers.find((l) => l.type === 'symbol')?.id;
        map.addLayer(
            {
                id: TINT_LAYER_ID,
                type: 'background',
                paint: { 'background-color': WASH_COLOR, 'background-opacity': 0 },
            },
            before,
        );
    }

    /** Applique les réglages de teinte, en fondu (sauf si le visiteur demande moins d'animations). */
    function applyTint(settings, instant) {
        if (!map || !layersReady || !map.getLayer(TINT_LAYER_ID)) return;
        const transition = { duration: instant ? 0 : TINT_MS, delay: 0 };
        map.setPaintProperty(TINT_LAYER_ID, 'background-opacity-transition', transition);
        map.setPaintProperty(TINT_LAYER_ID, 'background-opacity', settings.wash);
        if (!map.getLayer('osm')) return;
        const paint = rasterTintPaint(settings);
        for (const prop of RASTER_TINT_PROPS) {
            map.setPaintProperty('osm', `${prop}-transition`, transition);
            map.setPaintProperty('osm', prop, paint[prop]);
        }
    }

    /**
     * Transition fluide entre époques :
     *   1. Fade-out des layers (350ms grâce à `*-opacity-transition`)
     *   2. Swap des sources pendant que tout est invisible
     *   3. Fade-in vers les expressions d'opacité d'origine
     */
    function fadeAndSwap(year) {
        if (!map || !layersReady || !map.getLayer('vff-lines-active')) return;

        for (const id of LINE_LAYERS) map.setPaintProperty(id, 'line-opacity', 0);
        map.setPaintProperty(CASING_LAYER_ID, 'line-opacity', 0);
        map.setPaintProperty('vff-stations-points', 'circle-opacity', 0);
        map.setPaintProperty('vff-stations-points', 'circle-stroke-opacity', 0);
        map.setPaintProperty('vff-stations-labels', 'text-opacity', 0);

        setTimeout(() => {
            if (!map) return;
            map.getSource('vff-lines')?.setTiles([tilesUrlForEra(year)]);
            map.getSource('vff-stations')?.setData(stationsUrlForEra(year));
            // Halos de campagne : seulement à la décennie actuelle.
            map.setFilter('vff-lines-threatened', buildThreatenedFilter(year));
            map.setFilter('vff-lines-reopening', buildReopeningFilter(year));
            for (const id of LINE_LAYERS) map.setPaintProperty(id, 'line-opacity', lineOpacity(id));
            map.setPaintProperty(CASING_LAYER_ID, 'line-opacity', casingOpacity());
            map.setPaintProperty('vff-stations-points', 'circle-opacity', 1);
            map.setPaintProperty('vff-stations-points', 'circle-stroke-opacity', 1);
            map.setPaintProperty('vff-stations-labels', 'text-opacity', 1);
        }, FADE_MS);
    }

    // Lien d'une bulle vers la fiche : même onglet (navigation Inertia) sur le site, nouvel
    // onglet signalé dans la carte intégrée (sinon la fiche s'ouvrirait dans le cadre du site hôte).
    function popupLink(href, labelKey = 'maps.drawer.view_full_sheet') {
        if (!href) return '';
        const t = get(i18n).t;
        const label = escapeHtml(t(labelKey));
        if (!popupLinksNewTab) return `<a class="rail-popup-cta" href="${href}">${label}</a>`;
        return `<a class="rail-popup-cta" href="${href}" target="_blank" rel="noopener">${label}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span class="vff-sr-only"> (${escapeHtml(t('common.new_tab'))})</span></a>`;
    }

    function openPopup(lngLat, html, href) {
        popup?.remove();
        popup = new maplibregl.Popup({ closeButton: true, closeOnClick: true, maxWidth: '300px' })
            .setLngLat(lngLat)
            .setHTML(html)
            .addTo(map);
        const cta = popup.getElement()?.querySelector('.rail-popup-cta');
        if (cta && href && !popupLinksNewTab) {
            cta.addEventListener('click', (ev) => {
                ev.preventDefault();
                router.visit(href);
            });
        }
    }

    function setupInteractions() {
        // Click sur un tracé → soit callback parent (drawer), soit popup fallback.
        map.on('click', LINE_LAYERS, (e) => {
            if (!e.features?.length || overlayAt(e.point)) return;
            const props = e.features[0].properties;

            const line = {
                slug: props.line_slug,
                name: props.line_name,
                code: props.line_code,
                status: props.status,
                traffic_type: props.traffic_type,
                is_lgv: isTrue(props.is_lgv),
                network: props.network,
                // Exploitation associative : tourist_train | velorail | museum
                heritage_kind: isTrue(props.is_heritage)
                    ? (props.heritage_kind ?? 'tourist_train')
                    : null,
                era,
                // Valeurs au niveau LIGNE → cohérentes quel que soit le trait/voie cliqué
                max_speed_kmh: props.line_max_speed ?? props.max_speed_kmh,
                electrified: props.line_electrified ?? props.electrified,
                voltage_v: props.line_voltage_v ?? props.voltage_v,
            };

            // Si le parent a fourni onLineClick, on lui délègue (fiche latérale)
            if (typeof onLineClick === 'function') {
                onLineClick(line);
                // Téléphone : la fiche s'ouvre en bas de la carte ; le point cliqué remonte
                // dans la partie visible au lieu de rester caché dessous.
                if (mapContainer.clientWidth <= 600) {
                    map.easeTo({
                        center: e.lngLat,
                        offset: [0, -mapContainer.clientHeight * 0.25],
                        duration: 300,
                    });
                }
                return;
            }

            // Bulle MapLibre (carte intégrée) : mêmes mots que la fiche latérale et la légende.
            const t = get(i18n).t;
            const lang = get(i18n).language;
            const sep = colon(lang);
            const row = (label, value) =>
                `<li><span class="rail-popup-label">${escapeHtml(label)}${sep}</span>${escapeHtml(value)}</li>`;
            const when = era === currentEra ? t('maps.drawer.today') : String(era);
            const traffic = trafficInfo(line, t);
            const rows = [
                row(t('maps.drawer.status'), `${statusLabel(line.status, t)} (${when})`),
                traffic
                    ? row(
                          t(line.heritage_kind ? 'maps.drawer.heritage' : 'maps.drawer.traffic'),
                          traffic.label,
                      )
                    : '',
                line.network
                    ? row(t('maps.drawer.network'), t(`maps.networks.${line.network}`))
                    : '',
                line.max_speed_kmh
                    ? row(
                          t('maps.drawer.max_speed'),
                          `${formatNumber(line.max_speed_kmh, lang)}\u00a0km/h`,
                      )
                    : '',
                isTrue(line.electrified)
                    ? row(
                          t('maps.drawer.electrification'),
                          electrificationInfo(line.voltage_v, t, lang).label,
                      )
                    : '',
            ].join('');
            const href = line.slug ? `/lines/${encodeURIComponent(line.slug)}` : null;
            const html = `<div class="rail-popup"><p class="rail-popup-title">${escapeHtml(line.name ?? t('maps.drawer.fallback_line_name'))}</p><ul>${rows}</ul>${popupLink(href)}</div>`;
            openPopup(e.lngLat, html, href);
        });
        map.on('mouseenter', LINE_LAYERS, () => (map.getCanvas().style.cursor = 'pointer'));
        map.on('mouseleave', LINE_LAYERS, () => (map.getCanvas().style.cursor = ''));

        // Click sur une gare → popup
        map.on('click', 'vff-stations-points', (e) => {
            if (!e.features?.length || accidentAt(e.point) || levelCrossingAt(e.point)) return;
            const props = e.features[0].properties;
            const href = props.slug ? `/stations/${encodeURIComponent(props.slug)}` : null;
            const html = stationPopupHtml(props, popupLink(href, 'maps.drawer.view_station'));
            openPopup(e.lngLat, html, href);
        });
        map.on(
            'mouseenter',
            'vff-stations-points',
            () => (map.getCanvas().style.cursor = 'pointer'),
        );
        map.on('mouseleave', 'vff-stations-points', () => (map.getCanvas().style.cursor = ''));
    }

    // Vue (centre + zoom) appliquée à la carte. La page d'intégration lit
    // ?center= et ?zoom= dans SON onMount, qui s'exécute APRÈS celui de RailMap :
    // la carte était déjà créée sur la vue par défaut et ces paramètres étaient
    // ignorés. L'effet plus bas recale la vue quand ces props changent.
    const viewKey = (c, z) => `${c?.[0]},${c?.[1]},${z}`;
    let appliedView = null;

    // Libellés des boutons de MapLibre dans la langue de la page (sinon « Zoom in »…).
    function controlsLocale() {
        return mapControlsLocale(get(i18n).t);
    }

    // « Me localiser » : la position n'est demandée qu'au clic, une seule fois (pas de suivi),
    // et ne sert qu'à centrer la carte dans le navigateur : elle n'est envoyée à aucun serveur.
    function geolocateControl() {
        const control = new maplibregl.GeolocateControl({
            positionOptions: { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 },
            trackUserLocation: false,
            fitBoundsOptions: { maxZoom: 12 },
        });
        // Code 1 : le visiteur (ou son navigateur) refuse l'accès à la position.
        control.on('error', (e) => (geoError = e?.code === 1 ? 'denied' : 'unavailable'));
        control.on('geolocate', () => (geoError = null));
        return control;
    }

    // Cadrage d'ouverture : emprise complète (fitBounds), sinon emprise téléphone, sinon centre / zoom.
    function initialView() {
        const w = mapContainer.clientWidth;
        const h = mapContainer.clientHeight;
        if (fitBounds) {
            const padding =
                typeof fitPadding === 'function' ? fitPadding(w, h) : (fitPadding ?? 24);
            return { bounds: fitBounds, fitBoundsOptions: { padding } };
        }
        if (narrowBounds && w < 700) {
            return {
                bounds: narrowBounds,
                fitBoundsOptions: { padding: { top: 100, right: 44, bottom: 24, left: 12 } },
            };
        }
        return {};
    }

    onMount(() => {
        appliedView = viewKey(center, zoom);
        const offline = isOfflineView(page.props);
        if (offline) releasePartialMap = reportPartialMap();
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(offline),
            center,
            zoom,
            ...initialView(),
            locale: controlsLocale(),
            attributionControl: { compact: true },
        });

        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(
            new maplibregl.FullscreenControl({ container: fullscreenContainer ?? wrapperEl }),
            'top-right',
        );
        if (geolocate) map.addControl(geolocateControl(), 'top-right');
        map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
        addHistoricBasemapControl(map, 'top-right');

        map.on('load', () => {
            addTintLayer();
            addVffLayers(era);
            layersReady = true;
            applyTint(tintSettings, true);
            map.on('styledata', () => {
                historicShown = Boolean(map?.getLayer(HISTORIC_LAYER_ID));
            });
            applyMode(mode);
            applyOverlay(overlay);
            setupInteractions();
            applyRoutes(route, earlierRoute);
            applyView(view, era, compareEra);
            applyAccidents(accidents, view, era, compareEra);
            applyLevelCrossings(levelCrossings);
            applySignalling(signalling, signallingYear);
            applyGreenways(greenways);
            if (typeof onZoomChange === 'function') {
                onZoomChange(map.getZoom());
                map.on('zoomend', () => onZoomChange(map.getZoom()));
            }
        });
    });

    // Mode d'affichage (légende) : couleur des tracés selon le trafic (couleur propre de
    // chaque couche, relevée à sa création), l'électrification ou l'écartement ; le trait
    // reste celui du statut.
    const lineBaseColor = {};

    function applyMode(m) {
        if (!map || !layersReady) return;
        for (const id of LINE_LAYERS) {
            if (!map.getLayer(id)) continue;
            if (!(id in lineBaseColor)) lineBaseColor[id] = map.getPaintProperty(id, 'line-color');
            map.setPaintProperty(id, 'line-color', modeColorExpr(m, lineBaseColor[id]));
        }
    }

    $effect(() => applyMode(mode));

    // Réactif : ère, vue, réglage ou préférences changés → teinte du fond mise à jour.
    $effect(() => applyTint(tintSettings, reducedMotion.current));

    // Réactif : changement d'époque OU de dataVersion → fade + swap des sources
    $effect(() => {
        // Touche les deux props pour que Svelte tracke leur dépendance.
        dataVersion;
        fadeAndSwap(era);
    });

    // Réactif : toggles / légende → met à jour les filtres des couches de tracés
    $effect(() => {
        showVanished;
        showHeritage;
        showFreight;
        showAbandoned;
        showDismantled;
        hidden?.length;
        [...(hidden ?? [])];
        if (!map || !layersReady) return;
        for (const id of [...LINE_LAYERS, CASING_LAYER_ID]) {
            if (map.getLayer(id)) map.setFilter(id, LINE_FILTERS[id]());
        }
    });

    // Réactif : changement d'overlay → applique ou retire le calque raster
    $effect(() => applyOverlay(overlay));

    // Réactif : props center / zoom changées après la création (embed ?center=&zoom=)
    $effect(() => {
        const key = viewKey(center, zoom);
        if (!map || key === appliedView) return;
        appliedView = key;
        map.jumpTo({ center, zoom });
    });

    // Réactif : flyTarget changé → animation vers la cible (saut direct si le visiteur a
    // demandé moins d'animations : MapLibre le respecte pour un déplacement non « essentiel »).
    $effect(() => {
        if (!flyTarget || !map) return;
        map.flyTo({
            center: [flyTarget.lng, flyTarget.lat],
            zoom: flyTarget.zoom ?? 12,
            duration: 1400,
        });
    });

    // Réactif : ligne sélectionnée → liseré sous ses tracés.
    $effect(() => {
        const slug = selectedLine;
        if (!map || !layersReady || !map.getLayer(SELECTED_LAYER_ID)) return;
        map.setFilter(SELECTED_LAYER_ID, selectedFilter(slug));
    });

    // ── Vues par époque : comparaison de deux décennies, toutes les décennies empilées ──
    // Calques ajoutés au premier usage ; en dehors de la vue « une décennie », les couches
    // habituelles (tracés, halos, gares) sont masquées : MapLibre ne charge alors plus leurs
    // tuiles. Le style des couches habituelles n'est jamais modifié.
    const BASE_SOURCES = ['vff-lines', 'vff-stations'];
    const COMPARE_SOURCE_ID = 'vff-compare';
    const COMPARE_LAYER_ID = 'vff-compare-lines';
    const STACK_SOURCE_ID = 'vff-stack';
    const STACK_LAYER_ID = 'vff-stack-lines';
    const STACK_CASING_LAYER_ID = `${STACK_LAYER_ID}-casing`;
    const STACK_CASING_MIN_ZOOM = 9;
    const STACK_WIDTH = ['interpolate', ['linear'], ['zoom'], 4, 1.4, 8, 2.4, 12, 4, 16, 6];
    let compareTiles = null;

    // Sous les accidents et l'itinéraire, s'ils sont affichés.
    const viewLayersBefore = () =>
        [ACCIDENT_AREA_LAYER_ID, ...ROUTE_LAYERS].find((above) => map.getLayer(above));

    // Liseré d'une vue par époque : une couche pour toute la source, posée juste sous les tracés pleins
    // qu'elle borde (ajoutée avant eux). Les tronçons en tirets et les tronçons « restés en service »
    // (contexte discret) n'en ont pas.
    function addViewCasing(spec) {
        map.addLayer(casingLayer(spec), viewLayersBefore());
    }

    // Couche de tracés d'une vue par époque, sous l'itinéraire s'il est affiché ; un clic
    // ouvre la bulle de la vue (`popupKind` : 'compare' ou 'stack').
    function addViewLayer({
        id,
        source,
        sourceLayer,
        paint,
        sortKey,
        filter = null,
        round,
        popupKind,
    }) {
        map.addLayer(
            {
                id,
                type: 'line',
                source,
                'source-layer': sourceLayer,
                ...(filter ? { filter } : {}),
                layout: {
                    'line-join': 'round',
                    'line-sort-key': sortKey,
                    ...(round ? { 'line-cap': 'round' } : {}),
                },
                paint,
            },
            viewLayersBefore(),
        );
        map.on('click', id, (e) => {
            if (e.features?.length && !overlayAt(e.point))
                openViewPopup(popupKind, e.lngLat, e.features[0].properties);
        });
        map.on('mouseenter', id, () => (map.getCanvas().style.cursor = 'pointer'));
        map.on('mouseleave', id, () => (map.getCanvas().style.cursor = ''));
    }

    // Trait selon le changement : `line-dasharray` n'accepte pas d'expression par entité, d'où
    // une couche par motif (trait plein, tirets, pointillés) sur la même source.
    const COMPARE_DASHES = [...new Set(COMPARE_CHANGES.map((c) => JSON.stringify(c.dash)))].map(
        (key, i) => ({
            id: `${COMPARE_LAYER_ID}-${i}`,
            dash: JSON.parse(key),
            keys: COMPARE_CHANGES.filter((c) => JSON.stringify(c.dash) === key).map((c) => c.key),
        }),
    );

    function ensureCompareLayers(tiles) {
        if (map.getSource(COMPARE_SOURCE_ID)) {
            if (tiles !== compareTiles) map.getSource(COMPARE_SOURCE_ID).setTiles([tiles]);
            compareTiles = tiles;
            return;
        }
        compareTiles = tiles;
        map.addSource(COMPARE_SOURCE_ID, { type: 'vector', tiles: [tiles], maxzoom: 14 });
        for (const group of COMPARE_DASHES) {
            // Liseré des ouvertures (trait plein, couleur claire sur le fond clair).
            if (!group.dash) {
                addViewCasing({
                    id: `${group.id}-casing`,
                    source: COMPARE_SOURCE_ID,
                    sourceLayer: 'vff-compare',
                    filter: ['==', ['get', 'change'], 'opened'],
                    sortKey: compareSortExpr(),
                    width: compareWidthExpr(),
                });
            }
            addViewLayer({
                id: group.id,
                source: COMPARE_SOURCE_ID,
                sourceLayer: 'vff-compare',
                filter: ['match', ['get', 'change'], group.keys, true, false],
                sortKey: compareSortExpr(),
                round: !group.dash,
                popupKind: 'compare',
                paint: {
                    'line-color': compareColorExpr(),
                    'line-width': compareWidthExpr(),
                    'line-opacity': compareOpacityExpr(),
                    ...(group.dash ? { 'line-dasharray': group.dash } : {}),
                },
            });
        }
    }

    function ensureStackLayer() {
        if (map.getSource(STACK_SOURCE_ID)) return;
        map.addSource(STACK_SOURCE_ID, { type: 'vector', tiles: [stackTilesUrl()], maxzoom: 14 });
        addViewCasing({
            id: STACK_CASING_LAYER_ID,
            source: STACK_SOURCE_ID,
            sourceLayer: 'vff-stack',
            sortKey: ['get', 'last_year'],
            width: STACK_WIDTH,
            // Vue d'ensemble : la clarté du trait porte la durée de service, un filet foncé la brouillerait.
            minzoom: STACK_CASING_MIN_ZOOM,
        });
        addViewLayer({
            id: STACK_LAYER_ID,
            source: STACK_SOURCE_ID,
            sourceLayer: 'vff-stack',
            // Les plus récents au-dessus : le réseau actuel recouvre ce qui a disparu.
            sortKey: ['get', 'last_year'],
            round: true,
            popupKind: 'stack',
            paint: {
                'line-color': stackColorExpr(currentEra),
                'line-width': STACK_WIDTH,
                // Transparence : les tracés superposés de décennies différentes restent lisibles.
                'line-opacity': 0.85,
            },
        });
    }

    function setVisibility(id, on) {
        if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', on ? 'visible' : 'none');
    }

    function applyView(v, from, to) {
        if (!map || !layersReady) return;
        const comparing = v === 'compare' && from != null && to != null && from !== to;
        const stacking = v === 'stack';
        if (comparing) ensureCompareLayers(compareTilesUrl(from, to));
        if (stacking) ensureStackLayer();
        for (const layer of map.getStyle().layers) {
            if (BASE_SOURCES.includes(layer.source)) {
                setVisibility(layer.id, !comparing && !stacking);
            }
        }
        for (const group of COMPARE_DASHES) {
            setVisibility(group.id, comparing);
            setVisibility(`${group.id}-casing`, comparing);
        }
        setVisibility(STACK_LAYER_ID, stacking);
        setVisibility(STACK_CASING_LAYER_ID, stacking);
        if (comparing || stacking) popup?.remove();
    }

    $effect(() => applyView(view, era, compareEra));

    // Bulle d'un tracé des vues par époque : nom, ce qui lui est arrivé, lien vers la fiche.
    function openViewPopup(kind, lngLat, props) {
        const t = get(i18n).t;
        const yearLabel = (y) => (Number(y) === currentEra ? t('maps.drawer.today') : String(y));
        let text;
        if (kind === 'stack') {
            const first = Number(props.first_year);
            const last = Number(props.last_year);
            text =
                first === last
                    ? t('maps.stack.popup_single', { year: yearLabel(first) })
                    : t(last === currentEra ? 'maps.stack.popup_today' : 'maps.stack.popup_span', {
                          from: first,
                          to: yearLabel(last),
                      });
        } else {
            text = t(`maps.compare.popup.${props.change}`, {
                from: era,
                to: yearLabel(compareEra),
            });
        }
        const href = props.line_slug ? `/lines/${encodeURIComponent(props.line_slug)}` : null;
        const name = props.line_name ?? t('maps.drawer.fallback_line_name');
        const html = `<div class="rail-popup"><p class="rail-popup-title">${escapeHtml(name)}</p><p class="rail-popup-place">${escapeHtml(text)}</p>${popupLink(href)}</div>`;
        openPopup(lngLat, html, href);
    }

    // ── Calque « Accidents » (facultatif) : au-dessus du réseau et des gares, sous l'itinéraire ──
    // Un point se clique avec quelques pixels de marge (les points font 7 à 14 px) ; il passe
    // alors avant le tracé ou la gare qui se trouve dessous.
    const ACCIDENT_HIT_PX = 6;
    // Données posées sur la source (setData seulement quand elles changent, pas à chaque ère).
    let accidentData = null;

    function accidentAt(point) {
        if (!map?.getLayer(ACCIDENT_POINT_LAYER_ID)) return null;
        const box = [
            [point.x - ACCIDENT_HIT_PX, point.y - ACCIDENT_HIT_PX],
            [point.x + ACCIDENT_HIT_PX, point.y + ACCIDENT_HIT_PX],
        ];
        return map.queryRenderedFeatures(box, { layers: [ACCIDENT_POINT_LAYER_ID] })[0] ?? null;
    }

    function openAccidentPopup(feature) {
        const { t, language } = get(i18n);
        const props = feature.properties;
        const href = `/accidents/${encodeURIComponent(props.slug)}`;
        const html = accidentPopupHtml(
            props,
            t,
            language,
            popupLink(href, 'maps.accidents.view_sheet'),
        );
        openPopup(feature.geometry.coordinates, html, href);
    }

    function addAccidentLayers(data) {
        map.addSource(ACCIDENT_SOURCE_ID, { type: 'geojson', data });
        const before = ROUTE_LAYERS.find((routeId) => map.getLayer(routeId));
        for (const layer of accidentLayers()) map.addLayer(layer, before);
        map.on('click', (e) => {
            const feature = accidentAt(e.point);
            if (feature) openAccidentPopup(feature);
        });
        map.on(
            'mouseenter',
            ACCIDENT_POINT_LAYER_ID,
            () => (map.getCanvas().style.cursor = 'pointer'),
        );
        map.on('mouseleave', ACCIDENT_POINT_LAYER_ID, () => (map.getCanvas().style.cursor = ''));
    }

    function applyAccidents(data, v, from, to) {
        if (!map || !layersReady) return;
        const source = map.getSource(ACCIDENT_SOURCE_ID);
        if (!data) {
            if (source) for (const layer of accidentLayers()) setVisibility(layer.id, false);
            return;
        }
        if (!source) addAccidentLayers(data);
        else if (data !== accidentData) source.setData(data);
        accidentData = data;
        const years = accidentYearFilter(accidentYears(v, from, to));
        for (const layer of accidentLayers()) {
            map.setFilter(layer.id, layer.filter ? ['all', layer.filter, years] : years);
            setVisibility(layer.id, true);
        }
    }

    $effect(() => applyAccidents(accidents, view, era, compareEra));

    // ── Calques « Passages à niveau » et « Signalisation » (facultatifs, réseau d'aujourd'hui) ──
    // Sources et couches ajoutées au premier affichage, masquées ensuite (MapLibre ne charge pas les
    // tuiles d'une couche masquée). Un clic sur un point ou une tranche passe avant le tracé dessous.
    const OVERLAY_HIT_PX = 6;
    // Réseau habituel estompé sous la signalisation (opacité multipliée), rétabli ensuite. À une époque
    // passée, peu de lignes ont une mise en service datée : le réseau s'efface davantage pour qu'elles se lisent.
    const NETWORK_DIM = 0.3;
    const NETWORK_DIM_HISTORY = 0.15;
    let networkDim = 1;

    function lineOpacity(id) {
        return LINE_LAYER_OPACITY[id] * networkDim;
    }

    // Le liseré s'estompe avec le réseau (sinon il resterait seul, foncé, autour de tracés pâles).
    function casingOpacity() {
        return CASING_OPACITY * networkDim;
    }

    function featureAt(point, layers, px = OVERLAY_HIT_PX) {
        if (!layers.some((id) => map?.getLayer(id))) return null;
        const box = [
            [point.x - px, point.y - px],
            [point.x + px, point.y + px],
        ];
        return (
            map.queryRenderedFeatures(box, {
                layers: layers.filter((id) => map.getLayer(id)),
            })[0] ?? null
        );
    }

    const levelCrossingAt = (point) => featureAt(point, [LEVEL_CROSSING_LAYER_ID]);
    // Gare sous le clic (point exact, comme le clic de la couche) : elle passe avant la signalisation.
    const stationAt = (point) => featureAt(point, ['vff-stations-points'], 0);
    const signallingAt = (point) =>
        featureAt(
            point,
            SIGNALLING_DASH_GROUPS.map((g) => g.id),
            4,
        );
    /** Point ou tranche d'un calque facultatif sous le clic (prioritaire sur les tracés). */
    const greenwayAt = (point) => featureAt(point, [GREENWAY_LINE_LAYER_ID], 4);
    const overlayAt = (point) =>
        accidentAt(point) ?? levelCrossingAt(point) ?? signallingAt(point) ?? greenwayAt(point);

    function openOverlayPopup(html, lineSlug, lngLat) {
        const href = lineSlug ? `/lines/${encodeURIComponent(lineSlug)}` : null;
        openPopup(lngLat, html(popupLink(href, 'maps.level_crossings.view_line')), href);
    }

    // Couches des deux calques sous les accidents, l'itinéraire et les gares. Ordre d'empilement des
    // calques facultatifs, de haut en bas : accidents, passages à niveau, signalisation, puis le
    // réseau ; le fond OpenRailwayMap (image) reste sous le réseau.
    function overlayBefore() {
        return [ACCIDENT_AREA_LAYER_ID, ...ROUTE_LAYERS, 'vff-stations-points'].find((id) =>
            map.getLayer(id),
        );
    }

    function applyLevelCrossings(on) {
        if (!map || !layersReady) return;
        if (on && !map.getSource(LEVEL_CROSSING_SOURCE_ID)) {
            map.addSource(LEVEL_CROSSING_SOURCE_ID, {
                type: 'vector',
                tiles: [levelCrossingTilesUrl(window.location.origin, bustParam())],
                minzoom: LEVEL_CROSSING_MIN_ZOOM,
                maxzoom: 14,
            });
            map.addLayer(levelCrossingLayer(), overlayBefore());
            map.on('click', (e) => {
                if (accidentAt(e.point)) return;
                const f = levelCrossingAt(e.point);
                if (!f) return;
                const { t, language } = get(i18n);
                openOverlayPopup(
                    (link) => levelCrossingPopupHtml(f.properties, t, language, link),
                    f.properties.line_slug,
                    f.geometry.coordinates,
                );
            });
            map.on(
                'mouseenter',
                LEVEL_CROSSING_LAYER_ID,
                () => (map.getCanvas().style.cursor = 'pointer'),
            );
            map.on(
                'mouseleave',
                LEVEL_CROSSING_LAYER_ID,
                () => (map.getCanvas().style.cursor = ''),
            );
        }
        setVisibility(LEVEL_CROSSING_LAYER_ID, on);
    }

    // Ère dont les tuiles de signalisation sont chargées (elles suivent la décennie affichée).
    let signallingEra = null;

    function applySignalling(on, year) {
        if (!map || !layersReady) return;
        const tiles = signallingTilesUrl(window.location.origin, year, bustParam());
        if (on && map.getSource(SIGNALLING_SOURCE_ID) && signallingEra !== year) {
            map.getSource(SIGNALLING_SOURCE_ID).setTiles([tiles]);
            signallingEra = year;
        }
        if (on && !map.getSource(SIGNALLING_SOURCE_ID)) {
            map.addSource(SIGNALLING_SOURCE_ID, { type: 'vector', tiles: [tiles], maxzoom: 14 });
            signallingEra = year;
            // Calques superposés : les tranches (des lignes) passent sous les passages à niveau
            // (des points), quel que soit l'ordre dans lequel on les coche.
            const before = map.getLayer(LEVEL_CROSSING_LAYER_ID)
                ? LEVEL_CROSSING_LAYER_ID
                : overlayBefore();
            for (const layer of signallingLayers()) map.addLayer(layer, before);
            map.on('click', (e) => {
                if (accidentAt(e.point) || levelCrossingAt(e.point) || stationAt(e.point)) return;
                const f = signallingAt(e.point);
                if (!f) return;
                const { t, language } = get(i18n);
                openOverlayPopup(
                    (link) => signallingPopupHtml(f.properties, t, language, link),
                    f.properties.line_slug,
                    e.lngLat,
                );
            });
            for (const g of SIGNALLING_DASH_GROUPS) {
                map.on('mouseenter', g.id, () => (map.getCanvas().style.cursor = 'pointer'));
                map.on('mouseleave', g.id, () => (map.getCanvas().style.cursor = ''));
            }
        }
        for (const id of SIGNALLING_LAYER_IDS) setVisibility(id, on);
        const dim = !on ? 1 : year === currentEra ? NETWORK_DIM : NETWORK_DIM_HISTORY;
        if (networkDim !== dim) {
            networkDim = dim;
            for (const id of LINE_LAYERS) {
                if (map.getLayer(id)) map.setPaintProperty(id, 'line-opacity', lineOpacity(id));
            }
            if (map.getLayer(CASING_LAYER_ID)) {
                map.setPaintProperty(CASING_LAYER_ID, 'line-opacity', casingOpacity());
            }
        }
    }

    // Voies vertes : sous les passages à niveau et la signalisation, quel que soit l'ordre des cases
    // cochées, au-dessus du réseau.
    function applyGreenways(on) {
        if (!map || !layersReady) return;
        if (on && !map.getSource(GREENWAY_SOURCE_ID)) {
            map.addSource(GREENWAY_SOURCE_ID, {
                type: 'vector',
                tiles: [greenwayTilesUrl(window.location.origin, bustParam())],
                minzoom: GREENWAY_MIN_ZOOM,
                maxzoom: 14,
            });
            const before = [SIGNALLING_LAYER_IDS[0], LEVEL_CROSSING_LAYER_ID].find((id) =>
                map.getLayer(id),
            );
            for (const layer of greenwayLayers()) map.addLayer(layer, before ?? overlayBefore());
            map.on('click', (e) => {
                if (accidentAt(e.point) || levelCrossingAt(e.point) || signallingAt(e.point))
                    return;
                if (stationAt(e.point)) return;
                const f = greenwayAt(e.point);
                if (!f) return;
                const { t, language } = get(i18n);
                openOverlayPopup(
                    (link) => greenwayPopupHtml(f.properties, t, language, link),
                    f.properties.line_slug,
                    e.lngLat,
                );
            });
            map.on(
                'mouseenter',
                GREENWAY_LINE_LAYER_ID,
                () => (map.getCanvas().style.cursor = 'pointer'),
            );
            map.on('mouseleave', GREENWAY_LINE_LAYER_ID, () => (map.getCanvas().style.cursor = ''));
        }
        for (const id of GREENWAY_LAYER_IDS) setVisibility(id, on);
    }

    $effect(() => applyLevelCrossings(levelCrossings));
    // Décennie de la signalisation : celle de la carte dans la vue « une époque » (le parent ne
    // demande le calque que dans cette vue), sinon l'ère courante.
    const signallingYear = $derived(view === 'era' ? era : currentEra);
    $effect(() => applySignalling(signalling, signallingYear));
    $effect(() => applyGreenways(greenways));

    // ── Itinéraire historique (RoutePlanner) : tracé + gares, au-dessus du réseau ──
    // Comparaison de deux époques : le trajet le plus ancien (earlierRoute) est dessiné sous le
    // plus récent (route), dans un autre style (ROUTE_STYLES de routeCompare.js).
    const ROUTE_TRACES = [
        { key: 'earlier', source: 'vff-route-earlier', style: ROUTE_STYLES.earlier },
        { key: 'later', source: 'vff-route', style: ROUTE_STYLES.later },
    ];
    const traceLayers = (source) => [
        `${source}-casing`,
        `${source}-line`,
        `${source}-stops`,
        `${source}-labels`,
    ];
    // Couches des itinéraires, de la plus basse à la plus haute.
    const ROUTE_LAYERS = ROUTE_TRACES.flatMap((trace) => traceLayers(trace.source));

    function routeGeojson(r) {
        const features = [];
        if (r?.geometry)
            features.push({ type: 'Feature', geometry: r.geometry, properties: { kind: 'path' } });
        (r?.stations ?? []).forEach((s, i, all) => {
            const end = i === 0 || i === all.length - 1;
            features.push({
                type: 'Feature',
                geometry: { type: 'Point', coordinates: [s.lng, s.lat] },
                properties: { kind: end ? 'end' : 'stop', name: s.name },
            });
        });
        return { type: 'FeatureCollection', features };
    }

    function removeTrace(source) {
        for (const id of traceLayers(source)) if (map.getLayer(id)) map.removeLayer(id);
        if (map.getSource(source)) map.removeSource(source);
    }

    /**
     * Ajoute les couches d'un tracé, sous `before` (première couche d'un tracé déjà présent
     * au-dessus). Les noms des terminus ne sont écrits qu'une fois, par le tracé du haut.
     */
    function addTrace({ source, style }, data, before, labelEnds) {
        map.addSource(source, { type: 'geojson', data });
        const path = ['==', ['get', 'kind'], 'path'];
        const width = (scale, ...stops) => [
            'interpolate',
            ['linear'],
            ['zoom'],
            ...stops.flatMap(([z, w]) => [z, w * scale]),
        ];
        map.addLayer(
            {
                id: `${source}-casing`,
                type: 'line',
                source,
                filter: path,
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': style.casing,
                    'line-width': width(style.casingScale, [4, 7], [10, 12], [14, 16]),
                    'line-opacity': 0.9,
                },
            },
            before,
        );
        map.addLayer(
            {
                id: `${source}-line`,
                type: 'line',
                source,
                filter: path,
                layout: {
                    'line-cap': style.dash ? 'butt' : 'round',
                    'line-join': 'round',
                },
                paint: {
                    'line-color': style.core,
                    'line-width': width(1, [4, 2.5], [10, 4], [14, 6]),
                    ...(style.dash && { 'line-dasharray': style.dash }),
                },
            },
            before,
        );
        map.addLayer(
            {
                id: `${source}-stops`,
                type: 'circle',
                source,
                filter: ['!=', ['get', 'kind'], 'path'],
                paint: {
                    // Le zoom doit être l'entrée d'un « interpolate » de premier niveau (sinon
                    // MapLibre refuse la couche, et les noms des terminus ne s'affichaient pas).
                    'circle-radius': [
                        'interpolate',
                        ['linear'],
                        ['zoom'],
                        5,
                        ['case', ['==', ['get', 'kind'], 'end'], 7, 1.5],
                        9,
                        ['case', ['==', ['get', 'kind'], 'end'], 7, 3.5],
                        13,
                        ['case', ['==', ['get', 'kind'], 'end'], 7, 5],
                    ],
                    'circle-color': ['case', ['==', ['get', 'kind'], 'end'], '#ff6500', '#ffffff'],
                    'circle-stroke-color': '#111827',
                    'circle-stroke-width': ['case', ['==', ['get', 'kind'], 'end'], 2.5, 1.5],
                },
            },
            before,
        );
        const stopLabels = ['all', ['==', ['get', 'kind'], 'stop'], ['>=', ['zoom'], 9]];
        map.addLayer(
            {
                id: `${source}-labels`,
                type: 'symbol',
                source,
                filter: labelEnds
                    ? ['any', ['==', ['get', 'kind'], 'end'], stopLabels]
                    : stopLabels,
                layout: {
                    'text-field': ['get', 'name'],
                    'text-size': ['case', ['==', ['get', 'kind'], 'end'], 14, 13],
                    'text-offset': [0, 1.2],
                    'text-anchor': 'top',
                    'text-font': ['Noto Sans Bold'],
                },
                paint: {
                    'text-color': '#111827',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 2,
                },
            },
            before,
        );
    }

    function applyRoutes(later, earlier) {
        if (!map || !layersReady) return;
        const routes = { later, earlier };
        // Du haut vers le bas, pour poser chaque tracé sous ceux déjà présents au-dessus.
        let labelled = false;
        for (const trace of [...ROUTE_TRACES].reverse()) {
            const r = routes[trace.key];
            if (!r) {
                removeTrace(trace.source);
                continue;
            }
            const data = routeGeojson(r);
            const labelEnds = !labelled;
            labelled = true;
            // Le tracé du haut a pu apparaître ou disparaître : on repose les couches.
            removeTrace(trace.source);
            const above = ROUTE_LAYERS.slice(ROUTE_LAYERS.indexOf(`${trace.source}-labels`) + 1);
            addTrace(
                trace,
                data,
                above.find((id) => map.getLayer(id)),
                labelEnds,
            );
        }
        const pts = [...(later?.stations ?? []), ...(earlier?.stations ?? [])];
        if (pts.length) {
            const lngs = pts.map((s) => s.lng);
            const lats = pts.map((s) => s.lat);
            map.fitBounds(
                [
                    [Math.min(...lngs), Math.min(...lats)],
                    [Math.max(...lngs), Math.max(...lats)],
                ],
                { padding: routePadding(), maxZoom: 11, duration: 1200 },
            );
        }
    }

    // Cadrage d'un itinéraire : mêmes marges que l'ouverture (place de la légende), plus 40 px
    // pour que les terminus et leur nom ne touchent pas les bords.
    function routePadding() {
        if (typeof fitPadding !== 'function') return 70;
        const p = fitPadding(mapContainer.clientWidth, mapContainer.clientHeight);
        return { top: p.top + 40, right: p.right + 40, bottom: p.bottom + 40, left: p.left + 40 };
    }

    $effect(() => applyRoutes(route, earlierRoute));

    onDestroy(() => {
        releasePartialMap?.();
        popup?.remove();
        map?.remove();
        map = null;
    });
</script>

<div class="rail-map-wrapper" bind:this={wrapperEl}>
    <div class="rail-map" bind:this={mapContainer} style="height: {height};"></div>
    {@render children?.()}
    {#if geoError}
        <div class="geo-notice" role="alert">
            <i class="bi bi-geo-alt" aria-hidden="true"></i>
            <p>{$i18n.t(`maps.geolocate.${geoError}`)}</p>
            <button
                type="button"
                class="geo-close"
                aria-label={$i18n.t('maps.geolocate.dismiss')}
                onclick={() => (geoError = null)}
            >
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    {/if}
</div>

<style>
    /* Le cadre (bordure, coins) est posé par la page : la carte remplit simplement sa place. */
    .rail-map-wrapper {
        position: relative;
        overflow: hidden;
    }
    /* Géolocalisation refusée ou impossible : message posé en haut de la carte, à gauche des
       boutons de MapLibre. */
    .geo-notice {
        position: absolute;
        top: 12px;
        right: 56px;
        z-index: 3;
        display: flex;
        gap: 8px;
        align-items: flex-start;
        max-width: min(380px, calc(100% - 80px));
        padding: 8px 8px 8px 12px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .geo-notice p {
        margin: 0;
        padding-top: 4px;
    }
    .geo-notice > .bi {
        padding-top: 4px;
        color: var(--vff-text-muted);
    }
    .geo-close {
        display: inline-grid;
        flex: none;
        place-items: center;
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text-muted);
        cursor: pointer;
    }
    .geo-close:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .rail-map {
        width: 100%;
        /* Place réservée pendant le chargement des tuiles (pas de saut, pas de flash blanc). */
        background-color: var(--vff-bg-muted);
    }
    /* Focus clavier sur la carte (déplacement aux flèches) : contour dans le cadre, pas rogné. */
    .rail-map :global(.maplibregl-canvas:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: calc(-1 * var(--vff-focus-width));
    }

    /* Bulles (carte intégrée, gares) : fond et texte du thème, croix de 32 px. */
    :global(.maplibregl-popup-content) {
        padding: 14px 40px 14px 16px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    :global(.maplibregl-popup-tip) {
        display: none;
    }
    :global(.maplibregl-popup-close-button) {
        top: 6px;
        right: 6px;
        width: 32px;
        height: 32px;
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text-muted);
        font-size: 22px;
        line-height: 1;
    }
    :global(.maplibregl-popup-close-button:hover) {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    :global(.rail-popup-title) {
        margin: 0 0 6px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        line-height: var(--vff-lh-heading);
        color: var(--vff-text);
    }
    :global(.rail-popup-place) {
        margin: 0;
        color: var(--vff-text-muted);
    }
    :global(.rail-popup ul) {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    :global(.rail-popup li) {
        padding: 2px 0;
        font-family: inherit;
    }
    :global(.rail-popup-label) {
        color: var(--vff-text-muted);
    }
    /* Bulle d'un accident : lieu approché, fiche à vérifier. */
    :global(.rail-popup li.rail-popup-note) {
        color: var(--vff-text-muted);
    }
    :global(.rail-popup-cta) {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        margin-top: 8px;
        color: var(--vff-link);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 0.18em;
    }
    :global(.rail-popup-cta:hover) {
        text-decoration-thickness: 2px;
    }

    /* Boutons de la carte : focus du site (le halo bleu de MapLibre ne suit pas la charte). */
    :global(.maplibregl-ctrl button:focus) {
        box-shadow: none;
    }
    :global(.maplibregl-ctrl button:focus-visible) {
        position: relative;
        z-index: 1;
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 1px;
    }
    :global(.maplibregl-ctrl-attrib-inner) {
        font-size: var(--vff-fs-xs);
    }
    /* Écran tactile : boutons de 36 px (29 px par défaut). */
    @media (pointer: coarse) {
        :global(.maplibregl-ctrl-group button) {
            width: 36px;
            height: 36px;
        }
    }

    /* Thème sombre : boutons, échelle et crédits de la carte sur les fonds du thème (le fond
       OpenStreetMap lui-même n'est pas modifié). Les icônes de MapLibre sont gris foncé : inversées. */
    :global([data-theme='dark'] .maplibregl-ctrl-group) {
        background-color: var(--vff-bg-elevated);
        box-shadow: 0 0 0 1px var(--vff-border-strong);
    }
    :global([data-theme='dark'] .maplibregl-ctrl-group button + button) {
        border-top-color: var(--vff-border-strong);
    }
    :global([data-theme='dark'] .maplibregl-ctrl-group button:not(:disabled):hover) {
        background-color: var(--vff-bg-muted);
    }
    :global([data-theme='dark'] .maplibregl-ctrl button .maplibregl-ctrl-icon),
    :global([data-theme='dark'] .maplibregl-ctrl-attrib-button) {
        filter: invert(1);
    }
    :global([data-theme='dark'] .maplibregl-ctrl.maplibregl-ctrl-attrib),
    :global([data-theme='dark'] .maplibregl-ctrl-attrib.maplibregl-compact) {
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text-muted);
    }
    :global([data-theme='dark'] .maplibregl-ctrl-attrib a) {
        color: var(--vff-text);
    }
    :global([data-theme='dark'] .maplibregl-ctrl-scale) {
        border-color: var(--vff-text-muted);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
    }
</style>
