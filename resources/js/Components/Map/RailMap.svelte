<script>
    import maplibregl from 'maplibre-gl';
    import 'maplibre-gl/dist/maplibre-gl.css';
    import { onDestroy, onMount } from 'svelte';
    import { get } from 'svelte/store';
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';
    // Fonds de carte historiques IGN (Licence Ouverte 2.0) : overlays + contrôle avec opacité.
    import {
        HISTORIC_OVERLAY_DEFS,
        addHistoricBasemapControl,
    } from '$lib/historicBasemapControl.js';
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
        // Emprise [[ouest, sud], [est, nord]] cadrée à l'ouverture quand la carte fait moins
        // de 700 px de large (téléphone) ; au-dessus, centre et zoom ci-dessus. null : jamais.
        narrowBounds = null,
    } = $props();

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
        const set = new Set(hidden ?? []);
        if (showFreight === false) set.add('freight');
        if (showHeritage === false) {
            set.add('heritage');
            set.add('velorail');
        }
        // Anciens toggles : fermées/déposées masquées seulement si le parent
        // ne pilote pas la légende (`hidden` = null) et ne demande pas les disparues.
        if (hidden == null && !showVanished) {
            if (!showAbandoned) set.add('abandoned');
            if (!showDismantled) set.add('dismantled');
        }
        return set;
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

    const LINE_FILTERS = {
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

    function buildStyle() {
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
                'text-size': 12,
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

    /**
     * Transition fluide entre époques :
     *   1. Fade-out des layers (350ms grâce à `*-opacity-transition`)
     *   2. Swap des sources pendant que tout est invisible
     *   3. Fade-in vers les expressions d'opacité d'origine
     */
    function fadeAndSwap(year) {
        if (!map || !layersReady || !map.getLayer('vff-lines-active')) return;

        for (const id of LINE_LAYERS) map.setPaintProperty(id, 'line-opacity', 0);
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
            for (const id of LINE_LAYERS)
                map.setPaintProperty(id, 'line-opacity', LINE_LAYER_OPACITY[id]);
            map.setPaintProperty('vff-stations-points', 'circle-opacity', 1);
            map.setPaintProperty('vff-stations-points', 'circle-stroke-opacity', 1);
            map.setPaintProperty('vff-stations-labels', 'text-opacity', 1);
        }, FADE_MS);
    }

    function setupInteractions() {
        // Click sur un tracé → soit callback parent (drawer), soit popup fallback.
        map.on('click', LINE_LAYERS, (e) => {
            if (!e.features?.length) return;
            const props = e.features[0].properties;

            // Si le parent a fourni onLineClick, on lui délègue (drawer)
            if (typeof onLineClick === 'function') {
                onLineClick({
                    slug: props.line_slug,
                    name: props.line_name,
                    code: props.line_code,
                    status: props.status,
                    traffic_type: props.traffic_type,
                    network: props.network,
                    // Exploitation associative : tourist_train | velorail | museum
                    heritage_kind:
                        props.is_heritage === true || props.is_heritage === 'true'
                            ? (props.heritage_kind ?? null)
                            : null,
                    era,
                    // Valeurs au niveau LIGNE → cohérentes quel que soit le trait/voie cliqué
                    max_speed_kmh: props.line_max_speed ?? props.max_speed_kmh,
                    electrified: props.line_electrified ?? props.electrified,
                    voltage_v: props.line_voltage_v ?? props.voltage_v,
                });
                return;
            }

            // Fallback popup MapLibre (utilisé dans Embed minimaliste)
            const t = get(i18n).t;
            const sep = colon(get(i18n).language);
            const slug = props.line_slug;
            const ctaHtml = slug
                ? `<a class="rail-popup-cta" href="/lines/${slug}" data-line-slug="${slug}">${t('maps.drawer.view_full_sheet')} <i class="bi bi-arrow-right"></i></a>`
                : '';
            const html = `
                <div class="rail-popup">
                    <strong>${props.line_name ?? t('maps.drawer.fallback_line_name')}</strong>
                    <ul>
                        <li>${t('maps.drawer.status')}${sep}${props.status ? t(`maps.status.${props.status}`) : '-'} (${era})</li>
                        ${props.is_heritage === true || props.is_heritage === 'true' ? `<li>${t('maps.drawer.heritage')}${sep}${t(`maps.heritage_kind.${props.heritage_kind ?? 'tourist_train'}`)}</li>` : ACTIVE_STATUSES.includes(props.status) ? `<li>${t('maps.drawer.traffic')}${sep}${t(`maps.traffic.${props.traffic_type ?? 'unknown'}`)}</li>` : ''}
                        ${props.network ? `<li>${t('maps.drawer.network')}${sep}${t(`maps.networks.${props.network}`)}</li>` : ''}
                        ${(props.line_max_speed ?? props.max_speed_kmh) ? `<li>${t('maps.drawer.max_speed')}${sep}${props.line_max_speed ?? props.max_speed_kmh} km/h</li>` : ''}
                        ${(props.line_electrified ?? props.electrified) === true || (props.line_electrified ?? props.electrified) === 'true' ? `<li>${t('maps.drawer.electrified')}${(props.line_voltage_v ?? props.voltage_v) ? ` (${props.line_voltage_v ?? props.voltage_v} V)` : ''}</li>` : ''}
                    </ul>
                    ${ctaHtml}
                </div>
            `;
            popup?.remove();
            popup = new maplibregl.Popup({ closeButton: true, closeOnClick: true })
                .setLngLat(e.lngLat)
                .setHTML(html)
                .addTo(map);

            const cta = popup.getElement()?.querySelector('.rail-popup-cta');
            if (cta && slug) {
                cta.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    router.visit(`/lines/${slug}`);
                });
            }
        });
        map.on('mouseenter', LINE_LAYERS, () => (map.getCanvas().style.cursor = 'pointer'));
        map.on('mouseleave', LINE_LAYERS, () => (map.getCanvas().style.cursor = ''));

        // Click sur une gare → popup
        map.on('click', 'vff-stations-points', (e) => {
            if (!e.features?.length) return;
            const props = e.features[0].properties;
            const slug = props.slug;
            const ctaHtml = slug
                ? `<a class="rail-popup-cta" href="/stations/${slug}" data-station-slug="${slug}">${get(i18n).t('maps.drawer.view_full_sheet')} <i class="bi bi-arrow-right"></i></a>`
                : '';
            popup?.remove();
            popup = new maplibregl.Popup({ closeButton: true })
                .setLngLat(e.lngLat)
                .setHTML(
                    `<div class="rail-popup"><strong>${props.name}</strong>${props.city ? `<br/>${props.city} (${props.department_code ?? ''})` : ''}${ctaHtml}</div>`,
                )
                .addTo(map);

            const cta = popup.getElement()?.querySelector('.rail-popup-cta');
            if (cta && slug) {
                cta.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    router.visit(`/stations/${slug}`);
                });
            }
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

    onMount(() => {
        appliedView = viewKey(center, zoom);
        const narrow = narrowBounds && mapContainer.clientWidth < 700;
        map = new maplibregl.Map({
            container: mapContainer,
            style: buildStyle(),
            center,
            zoom,
            ...(narrow
                ? {
                      bounds: narrowBounds,
                      fitBoundsOptions: { padding: { top: 100, right: 44, bottom: 24, left: 12 } },
                  }
                : {}),
            attributionControl: { compact: true },
        });

        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.addControl(
            new maplibregl.FullscreenControl({ container: fullscreenContainer ?? wrapperEl }),
            'top-right',
        );
        map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
        addHistoricBasemapControl(map, 'top-right');

        map.on('load', () => {
            addVffLayers(era);
            layersReady = true;
            applyMode(mode);
            applyOverlay(overlay);
            setupInteractions();
            applyRoute(route);
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

    // Réactif : changement d'époque OU de dataVersion → fade + swap des sources
    $effect(() => {
        // Touche les deux props pour que Svelte tracke leur dépendance.
        // eslint-disable-next-line no-unused-expressions
        dataVersion;
        fadeAndSwap(era);
    });

    // Réactif : toggles / légende → met à jour les filtres des couches de tracés
    $effect(() => {
        // eslint-disable-next-line no-unused-expressions
        showVanished;
        showHeritage;
        showFreight;
        showAbandoned;
        showDismantled;
        hidden?.length;
        [...(hidden ?? [])];
        if (!map || !layersReady) return;
        for (const id of LINE_LAYERS) {
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

    // Réactif : flyTarget changé → animation vers la cible
    $effect(() => {
        if (!flyTarget || !map) return;
        map.flyTo({
            center: [flyTarget.lng, flyTarget.lat],
            zoom: flyTarget.zoom ?? 12,
            duration: 1400,
            essential: true,
        });
    });

    // ── Itinéraire historique (RoutePlanner) : tracé + gares, au-dessus du réseau ──
    const ROUTE_SOURCE_ID = 'vff-route';
    const ROUTE_LAYERS = [
        'vff-route-casing',
        'vff-route-line',
        'vff-route-stops',
        'vff-route-labels',
    ];

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

    function applyRoute(r) {
        if (!map || !layersReady) return;
        if (!r) {
            for (const id of ROUTE_LAYERS) if (map.getLayer(id)) map.removeLayer(id);
            if (map.getSource(ROUTE_SOURCE_ID)) map.removeSource(ROUTE_SOURCE_ID);
            return;
        }
        const data = routeGeojson(r);
        if (map.getSource(ROUTE_SOURCE_ID)) {
            map.getSource(ROUTE_SOURCE_ID).setData(data);
        } else {
            map.addSource(ROUTE_SOURCE_ID, { type: 'geojson', data });
            const path = ['==', ['get', 'kind'], 'path'];
            map.addLayer({
                id: 'vff-route-casing',
                type: 'line',
                source: ROUTE_SOURCE_ID,
                filter: path,
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#fde047',
                    'line-width': ['interpolate', ['linear'], ['zoom'], 4, 7, 10, 12, 14, 16],
                    'line-opacity': 0.9,
                },
            });
            map.addLayer({
                id: 'vff-route-line',
                type: 'line',
                source: ROUTE_SOURCE_ID,
                filter: path,
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#111827',
                    'line-width': ['interpolate', ['linear'], ['zoom'], 4, 2.5, 10, 4, 14, 6],
                },
            });
            map.addLayer({
                id: 'vff-route-stops',
                type: 'circle',
                source: ROUTE_SOURCE_ID,
                filter: ['!=', ['get', 'kind'], 'path'],
                paint: {
                    'circle-radius': [
                        'case',
                        ['==', ['get', 'kind'], 'end'],
                        7,
                        ['interpolate', ['linear'], ['zoom'], 5, 1.5, 9, 3.5, 13, 5],
                    ],
                    'circle-color': ['case', ['==', ['get', 'kind'], 'end'], '#ff6500', '#ffffff'],
                    'circle-stroke-color': '#111827',
                    'circle-stroke-width': ['case', ['==', ['get', 'kind'], 'end'], 2.5, 1.5],
                },
            });
            map.addLayer({
                id: 'vff-route-labels',
                type: 'symbol',
                source: ROUTE_SOURCE_ID,
                filter: [
                    'any',
                    ['==', ['get', 'kind'], 'end'],
                    ['all', ['==', ['get', 'kind'], 'stop'], ['>=', ['zoom'], 9]],
                ],
                layout: {
                    'text-field': ['get', 'name'],
                    'text-size': ['case', ['==', ['get', 'kind'], 'end'], 14, 11],
                    'text-offset': [0, 1.2],
                    'text-anchor': 'top',
                    'text-font': ['Noto Sans Bold'],
                },
                paint: {
                    'text-color': '#111827',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 2,
                },
            });
        }
        const pts = r.stations ?? [];
        if (pts.length) {
            const lngs = pts.map((s) => s.lng);
            const lats = pts.map((s) => s.lat);
            map.fitBounds(
                [
                    [Math.min(...lngs), Math.min(...lats)],
                    [Math.max(...lngs), Math.max(...lats)],
                ],
                { padding: 70, maxZoom: 11, duration: 1200 },
            );
        }
    }

    $effect(() => applyRoute(route));

    onDestroy(() => {
        popup?.remove();
        map?.remove();
        map = null;
    });
</script>

<div class="rail-map-wrapper" bind:this={wrapperEl}>
    <div class="rail-map" bind:this={mapContainer} style="height: {height};"></div>
    {@render children?.()}
</div>

<style>
    .rail-map-wrapper {
        position: relative;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid var(--vff-border);
        box-shadow: 0 12px 32px var(--vff-shadow);
    }
    .rail-map {
        width: 100%;
    }

    /* Style des popups MapLibre - accordé à l'identité VFF */
    :global(.maplibregl-popup-content) {
        font-family: var(--vff-font-body);
        border-radius: var(--vff-radius-md);
        padding: 14px 16px;
        box-shadow: 0 8px 24px var(--vff-shadow-strong);
        color: var(--vff-text);
        background: var(--vff-bg);
    }
    :global(.maplibregl-popup-content strong) {
        font-family: var(--vff-font-display);
        color: var(--vff-accent);
        font-size: 16px;
        display: block;
        margin-bottom: 6px;
    }
    :global(.maplibregl-popup-content ul) {
        list-style: none;
        padding: 0;
        margin: 0;
        font-size: 14px;
        color: var(--vff-text-muted);
    }
    :global(.maplibregl-popup-content li) {
        font-size: 14px;
        padding: 2px 0;
    }
    :global(.maplibregl-popup-tip) {
        display: none;
    }
    :global(.rail-popup-cta) {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        padding: 8px 12px;
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse) !important;
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 600;
        border-radius: var(--vff-radius-pill);
        text-decoration: none !important;
        transition: all var(--vff-transition);
    }
    :global(.rail-popup-cta:hover) {
        transform: scale(0.97);
        text-decoration: none;
    }
    :global(.maplibregl-ctrl-attrib-inner) {
        font-size: 11px;
    }
</style>
