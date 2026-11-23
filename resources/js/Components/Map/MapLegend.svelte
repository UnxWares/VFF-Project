<script>
    import { Link } from '@inertiajs/svelte';
    import GlossaryText from '$components/GlossaryText.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        HERITAGE_STYLES,
        STATUS_STYLES,
        THREATENED_COLOR,
        REOPENING_COLOR,
        STATION_STYLES,
        STATION_UNCERTAIN_COLOR,
        TRAFFIC_STYLES,
        ELECTRIFICATION_STYLES,
        GAUGE_STYLES,
        MAP_MODES,
        swatchStyle,
    } from '$lib/railStyle.js';
    import { coloringStyles } from '$lib/lineColoring.js';
    import { COMPARE_CHANGES, stackLabels } from '$lib/epochViews.js';
    import { ACCIDENT_COLOR, ACCIDENT_HALO } from '$lib/accidentMap.js';
    import { LEVEL_CROSSING_GROUPS } from '$lib/levelCrossingMap.js';
    import {
        SIGNALLING_CONTROL,
        SIGNALLING_FAMILIES,
        SIGNALLING_UNKNOWN_OPACITY,
    } from '$lib/signallingMap.js';
    import { GREENWAY_COLOR } from '$lib/greenwayMap.js';
    import { OVERLAY_HALO } from '$lib/overlayPalette.js';
    import { SCHEMA_CATEGORIES, categoryColors, paletteFor } from '$lib/schematicMap.js';
    import { theme } from '$lib/stores/theme.svelte.js';

    /**
     * Légende de la carte VFF : COULEUR = trafic des portions en service à la
     * décennie affichée, TRAIT = statut (fermée, déposée…). En mode interactif,
     * chaque entrée affiche / masque la catégorie correspondante (`hidden`).
     *
     * Posée sur la carte, elle ne dépasse jamais de sa hauteur : l'en-tête reste visible et le
     * contenu défile. Repliée par défaut jusqu'à 900 px de large (elle couvrirait l'ouest).
     *
     * Props :
     *   era         : année affichée (titre « Réseau en 1950 », « Réseau actuel »)
     *   hidden      : clés masquées (bindable) - cf. TOGGLE_KEYS de railStyle.js
     *   interactive : entrées cliquables (défaut true)
     *   currentEra  : décennie actuelle ; les halos de campagne (menacée,
     *                 réouverture) n'existent qu'à cette décennie (cf. RailMap)
     *   mode        : couleur des tracés (bindable) : 'traffic' | 'electrification' | 'gauge', et sur
     *                 /maps 'speed' | 'signalling'
     *   modes       : colorations proposées (défaut MAP_MODES ; /maps : MAP_COLORINGS)
     *   drawnMode   : coloration dessinée par la carte (défaut `mode`) : le trafic quand la coloration
     *                 choisie n'existe pas aux époques affichées
     *   unavailable : { coloration: phrase } des colorations impossibles aux époques affichées (choix
     *                 grisés, la phrase dit pour quelles époques elles existent)
     *   view        : 'era' (une décennie), 'compare' (changements de `era` à `compareEra`),
     *                 'curtain' (rideau : `era` à gauche, `compareEra` à droite, symboles d'une époque),
     *                 'stack' (toutes les époques) ou 'schema' (vue ferroviaire : catégories masquables
     *                 et symboles des gares du plan) ; hors 'era', la légende décrit le calque de la vue
     *   accidents   : calque « Accidents » affiché ({ state: 'loading'|'error'|'ready', count, from,
     *                 to } : années montrées, cf. accidentYears) ou null ; ses symboles, la période,
     *                 le nombre d'accidents localisés et le lien vers la liste ouvrent la légende
     *   overlays    : calques affichés, 'level-crossings', 'signalling' et/ou 'greenways' ;
     *                 leurs teintes et leurs limites suivent les accidents, dans
     *                 l'ordre d'empilement de la carte (points au-dessus des lignes)
     */
    let {
        era = null,
        hidden = $bindable([]),
        interactive = true,
        currentEra = 2030,
        mode = $bindable('traffic'),
        modes = MAP_MODES,
        drawnMode = undefined,
        unavailable = {},
        view = 'era',
        compareEra = null,
        accidents = null,
        overlays = [],
    } = $props();

    const uid = $props.id();
    const bodyId = `${uid}-body`;

    // Ouverte d'emblée sur ordinateur, repliée sur tablette et téléphone ; lu avant le premier
    // rendu pour que la légende ne s'ouvre pas puis se referme au chargement.
    let open = $state(
        typeof window === 'undefined' || !window.matchMedia?.('(max-width: 900px)').matches,
    );

    // Décennie dans une phrase (« de 1930 à aujourd'hui »).
    const inline = (year) =>
        year === currentEra ? $i18n.t('maps.compare.today_inline') : String(year);

    const title = $derived.by(() => {
        if (view === 'compare' && compareEra !== null)
            return $i18n.t('maps.compare.legend_title', { from: era, to: inline(compareEra) });
        // « 1930 à gauche, aujourd'hui à droite » (la gauche est toujours la plus ancienne).
        if (view === 'curtain' && compareEra !== null) {
            return $i18n.t('maps.curtain.legend_title', { left: era, right: inline(compareEra) });
        }
        if (view === 'stack') return $i18n.t('maps.stack.legend_title');
        if (view === 'schema') {
            return era === currentEra
                ? $i18n.t('maps.schema.legend_title_current')
                : $i18n.t('maps.schema.legend_title', { year: era });
        }
        if (era === null || era === undefined) return $i18n.t('maps.legend.expand');
        return era === currentEra
            ? $i18n.t('maps.legend.title_current')
            : $i18n.t('maps.legend.title', { year: era });
    });

    // Changements de la comparaison (même trait que sur la carte).
    // Familles de cantonnement : même trait que sur la carte (tirets, pointillés).
    const signallingRows = SIGNALLING_FAMILIES.map((f) => ({
        key: f.key,
        swatch: swatchStyle(f.color, f.dash, true),
    }));
    // Époque passée : la signalisation vient des mises en service datées (Wikipédia), pas de SNCF Réseau ;
    // un trait fin et pâle couvre une ligne entière dont la portion n'est pas connue.
    const signallingPast = $derived(view === 'era' && era !== null && era !== currentEra);
    const unknownSwatch = swatchStyle(SIGNALLING_FAMILIES[0].color, null, false);
    // Commande centralisée (époques passées) : trait fin sombre en pointillés, posé sur le cantonnement.
    const controlSwatch = swatchStyle(SIGNALLING_CONTROL.color, SIGNALLING_CONTROL.dash, false);
    const compareRows = COMPARE_CHANGES.map((c) => ({
        key: c.key,
        swatch: swatchStyle(c.color, c.dash, c.key !== 'kept'),
    }));
    // Classes de la vue empilée, du plus récent (foncé) au plus ancien (clair).
    const stackRows = $derived([...stackLabels(currentEra)].reverse());

    // Coloration dessinée : celle choisie, sauf si elle n'existe pas aux époques affichées (trafic).
    const shownMode = $derived(drawnMode ?? mode);
    const unavailableNotes = $derived(modes.filter((m) => unavailable[m]));
    // Lignes de légende de la coloration (vitesse, électrification, écartement : non masquables). La
    // signalisation a son propre bloc (familles et notes du calque « Signalisation »).
    const modeRows = $derived(
        (shownMode === 'electrification'
            ? ELECTRIFICATION_STYLES
            : shownMode === 'gauge'
              ? GAUGE_STYLES
              : shownMode === 'speed'
                ? coloringStyles('speed')
                : []
        ).map((s) => ({
            key: s.key,
            labelKey: s.labelKey,
            swatch: swatchStyle(s.color),
        })),
    );
    const modeTitleKey = $derived(
        shownMode === 'gauge'
            ? 'maps.gauge.title'
            : shownMode === 'speed'
              ? 'maps.coloring.speed_title'
              : 'maps.electrification.title',
    );

    // Halos de campagne : légende affichée seulement quand la carte peut les montrer.
    const showAdvocacy = $derived(
        era === null || era === currentEra || (view === 'curtain' && compareEra === currentEra),
    );

    const style = (key) => STATUS_STYLES.find((s) => s.key === key);

    const trafficRows = TRAFFIC_STYLES.map((t) => ({
        key: t.key,
        labelKey: `maps.traffic.${t.key}`,
        swatch: swatchStyle(t.color, null, !!t.thick),
    }));

    const statusRows = [
        // Exploitée par une association : train touristique / musée (trait plein), vélorail (pointillés).
        ...HERITAGE_STYLES.map((h) => ({
            key: h.key,
            labelKey: h.labelKey,
            color: h.color,
            dash: h.dash,
        })),
        {
            key: 'service_track',
            labelKey: 'maps.status.service_track',
            color: style('service_track').color,
            dash: null,
        },
        {
            key: 'construction',
            labelKey: 'maps.status.construction',
            color: style('construction').color,
            dash: style('construction').dash,
        },
        {
            key: 'abandoned',
            labelKey: 'maps.status.abandoned',
            color: style('abandoned').color,
            dash: style('abandoned').dash,
        },
        {
            key: 'dismantled',
            labelKey: 'maps.status.gone',
            color: style('dismantled').color,
            dash: style('dismantled').dash,
        },
    ];
    // Hors mode trafic, la couleur dit l'électrification / l'écartement : seul le TRAIT du
    // statut compte, pastilles en teinte neutre.
    const statusRowsView = $derived(
        statusRows.map((r) => ({
            ...r,
            swatch: swatchStyle(
                shownMode === 'traffic' ? r.color : STATION_UNCERTAIN_COLOR,
                r.dash,
            ),
        })),
    );

    // Gares : ouvertes (voyageurs, touristique, fret seul) et fermées.
    const stationRows = STATION_STYLES;

    // Vue ferroviaire : mêmes catégories masquables que la carte, couleurs du plan selon le thème
    // (le gris du trafic inconnu est éclairci sur fond sombre, lib/schematicMap.js).
    const schemaInk = $derived(paletteFor(theme.isDark).ink);
    const schemaRows = $derived.by(() => {
        const colors = categoryColors(theme.isDark);
        return SCHEMA_CATEGORIES.map((key) => ({
            key,
            labelKey: key === 'heritage' ? 'maps.legend.heritage_train' : `maps.traffic.${key}`,
            swatch: swatchStyle(colors[key], null, true),
        }));
    });

    // Période des accidents montrés (« Survenus jusqu'en 1950 », « de 1931 à 1980 »).
    const accidentPeriod = $derived.by(() => {
        if (!accidents) return '';
        const { from, to } = accidents;
        if (from != null && to != null)
            return $i18n.t('maps.accidents.between', { from, to: inline(to) });
        if (to == null) return $i18n.t('maps.accidents.all');
        return to === currentEra
            ? $i18n.t('maps.accidents.until_today')
            : $i18n.t('maps.accidents.until', { year: to });
    });
    const accidentCount = $derived(
        !accidents?.count
            ? $i18n.t('maps.accidents.count_none')
            : $i18n.t('maps.accidents.count', { count: accidents.count }),
    );

    function isOn(key) {
        return !hidden.includes(key);
    }

    function toggle(key) {
        hidden = hidden.includes(key) ? hidden.filter((k) => k !== key) : [...hidden, key];
    }
</script>

{#snippet row(r)}
    <li>
        {#if interactive}
            <button
                type="button"
                class="legend-row"
                class:off={!isOn(r.key)}
                aria-pressed={isOn(r.key)}
                onclick={() => toggle(r.key)}
            >
                <i class="swatch" style={r.swatch} aria-hidden="true"></i>
                <span>{$i18n.t(r.labelKey)}</span>
            </button>
        {:else}
            <span class="legend-row">
                <i class="swatch" style={r.swatch} aria-hidden="true"></i>
                <span>{$i18n.t(r.labelKey)}</span>
            </span>
        {/if}
    </li>
{/snippet}

<div class="vff-legend" class:collapsed={!open}>
    <button
        type="button"
        class="legend-toggle"
        aria-expanded={open}
        aria-controls={bodyId}
        title={open ? $i18n.t('maps.legend.collapse') : $i18n.t('maps.legend.expand_label')}
        onclick={() => (open = !open)}
    >
        <span class="legend-title">{open ? title : $i18n.t('maps.legend.expand')}</span>
        <i class="bi {open ? 'bi-chevron-up' : 'bi-chevron-down'}" aria-hidden="true"></i>
    </button>

    <div class="legend-body" id={bodyId} hidden={!open}>
        <!-- Calques choisis par le visiteur : en tête, visibles sans faire défiler la légende, un
             bloc par calque séparé par un filet, de haut en bas comme sur la carte. -->
        {#if accidents}
            <div class="overlay-block">{@render accidentsBody()}</div>
        {/if}
        {#if overlays.includes('level-crossings')}
            <div class="overlay-block">{@render levelCrossingsBody()}</div>
        {/if}
        <!-- Coloration « Signalisation » : le même bloc est décrit plus bas, avec les couleurs des tracés. -->
        {#if overlays.includes('signalling') && !(shownMode === 'signalling' && (view === 'era' || view === 'curtain'))}
            <div class="overlay-block">{@render signallingBody()}</div>
        {/if}
        {#if overlays.includes('greenways')}
            <div class="overlay-block">{@render greenwaysBody()}</div>
        {/if}
        {#if view === 'compare'}
            <ul class="view-rows">
                {#each compareRows as r (r.key)}
                    <li>
                        <span class="legend-row">
                            <i class="swatch" style={r.swatch} aria-hidden="true"></i>
                            <span>{$i18n.t(`maps.compare.${r.key}`)}</span>
                        </span>
                    </li>
                {/each}
            </ul>
        {:else if view === 'schema'}
            <p class="legend-hint">{$i18n.t('maps.schema.legend_intro')}</p>
            <p class="legend-group" id={`${uid}-schema`}>{$i18n.t('maps.traffic.title')}</p>
            <ul aria-labelledby={`${uid}-schema`}>
                {#each schemaRows as r (r.key)}
                    {@render row(r)}
                {/each}
            </ul>
            {#if interactive}
                <p class="legend-hint">{$i18n.t('maps.legend.hint')}</p>
            {/if}
            <p class="legend-group" id={`${uid}-schema-stations`}>
                {$i18n.t('maps.schema.stations_group')}
            </p>
            <ul aria-labelledby={`${uid}-schema-stations`}>
                <li>
                    <span class="legend-row">
                        <i class="swatch station" style:border-color={schemaInk} aria-hidden="true"
                        ></i>
                        <span>{$i18n.t('maps.schema.node')}</span>
                    </span>
                </li>
                <li>
                    <span class="legend-row">
                        <i class="swatch tick" style:--ink={schemaInk} aria-hidden="true"></i>
                        <span>{$i18n.t('maps.schema.stop')}</span>
                    </span>
                </li>
            </ul>
        {:else if view === 'stack'}
            <p class="legend-hint">{$i18n.t('maps.stack.intro')}</p>
            <p class="legend-group" id={`${uid}-stack`}>{$i18n.t('maps.stack.group')}</p>
            <ul aria-labelledby={`${uid}-stack`}>
                {#each stackRows as r (r.key)}
                    <li>
                        <span class="legend-row">
                            <i class="swatch stack" style:background={r.color} aria-hidden="true"
                            ></i>
                            <span>{$i18n.t(r.labelKey, r.params)}</span>
                        </span>
                    </li>
                {/each}
            </ul>
            <p class="legend-hint">{$i18n.t('maps.stack.note')}</p>
        {:else}
            {@render eraBody()}
        {/if}
    </div>
</div>

<!-- Calque « Accidents » : mêmes symboles que les couches vff-accidents-* (lib/accidentMap.js). -->
{#snippet accidentsBody()}
    <p class="legend-group accidents-group" id={`${uid}-accidents`}>
        {$i18n.t('maps.accidents.title')}
        <span class="legend-group-note">{accidentPeriod}</span>
    </p>
    {#if accidents.state === 'loading'}
        <p class="legend-hint">{$i18n.t('maps.accidents.loading')}</p>
    {:else if accidents.state === 'error'}
        <p class="legend-hint">{$i18n.t('maps.accidents.error')}</p>
    {:else}
        <ul aria-labelledby={`${uid}-accidents`}>
            <li>
                <span class="legend-row">
                    <i
                        class="swatch accident"
                        style:background={ACCIDENT_COLOR}
                        style:border-color={ACCIDENT_HALO}
                        aria-hidden="true"
                    ></i>
                    <span>{$i18n.t('maps.accidents.point_exact')}</span>
                </span>
            </li>
            <li>
                <span class="legend-row">
                    <i
                        class="swatch accident-area"
                        style:--accident={ACCIDENT_COLOR}
                        aria-hidden="true"
                    ></i>
                    <span>{$i18n.t('maps.accidents.point_commune')}</span>
                </span>
            </li>
        </ul>
        <p class="legend-hint">{accidentCount}. {$i18n.t('maps.accidents.note')}</p>
    {/if}
    <p class="legend-more">
        <Link href="/accidents" class="vff-link">{$i18n.t('maps.accidents.list_link')}</Link>
    </p>
{/snippet}

<!-- Calque « Passages à niveau » : mêmes teintes que la couche vff-level-crossings-points. -->
{#snippet levelCrossingsBody()}
    <p class="legend-group overlay-group" id={`${uid}-crossings`}>
        {$i18n.t('maps.level_crossings.title')}
    </p>
    <ul aria-labelledby={`${uid}-crossings`}>
        {#each LEVEL_CROSSING_GROUPS as g (g.key)}
            <li>
                <span class="legend-row">
                    <i
                        class="swatch point"
                        style:background={g.color}
                        style:border-color={OVERLAY_HALO}
                        aria-hidden="true"
                    ></i>
                    <span>{$i18n.t(`level_crossings.groups.${g.key}`)}</span>
                </span>
            </li>
        {/each}
    </ul>
    <p class="legend-hint">
        {$i18n.t('maps.level_crossings.zoom_hint')}
        {$i18n.t('maps.level_crossings.note')}
        {$i18n.t('maps.level_crossings.source')}
    </p>
{/snippet}

<!-- Calque « Signalisation » : familles de cantonnement ; le KVB (contrôle de vitesse) n'en est pas une. -->
{#snippet signallingBody()}
    <p class="legend-group overlay-group" id={`${uid}-signalling`}>
        {$i18n.t('maps.signalling.title')}
    </p>
    <p class="legend-hint">
        {signallingPast
            ? $i18n.t('maps.signalling.history.intro', { year: era })
            : $i18n.t('maps.signalling.intro')}
    </p>
    <ul aria-labelledby={`${uid}-signalling`}>
        {#each signallingRows as r (r.key)}
            <li>
                <span class="legend-row">
                    <i class="swatch" style={r.swatch} aria-hidden="true"></i>
                    <span>{$i18n.t(`maps.signalling.families.${r.key}`)}</span>
                </span>
            </li>
        {/each}
        {#if signallingPast}
            <li>
                <span class="legend-row">
                    <i class="swatch" style={controlSwatch} aria-hidden="true"></i>
                    <span>{$i18n.t(`maps.signalling.families.${SIGNALLING_CONTROL.key}`)}</span>
                </span>
            </li>
            <li>
                <span class="legend-row">
                    <i
                        class="swatch"
                        style={unknownSwatch}
                        style:opacity={SIGNALLING_UNKNOWN_OPACITY}
                        aria-hidden="true"
                    ></i>
                    <span>{$i18n.t('maps.signalling.history.unknown_row')}</span>
                </span>
            </li>
        {/if}
    </ul>
    <p class="legend-hint"><GlossaryText text={String($i18n.t('maps.signalling.kvb_note'))} /></p>
    {#if signallingPast}
        <p class="legend-hint">{$i18n.t('maps.signalling.history.source_note')}</p>
    {:else}
        <p class="legend-hint">
            {$i18n.t('maps.signalling.placement_note')}
            {$i18n.t('maps.level_crossings.source')}
        </p>
    {/if}
{/snippet}

<!-- Calque « Voies vertes » : trait plein rose à liseré blanc, comme la couche vff-greenways-line. -->
{#snippet greenwaysBody()}
    <p class="legend-group overlay-group" id={`${uid}-greenways`}>
        {$i18n.t('maps.greenways.title')}
    </p>
    <ul aria-labelledby={`${uid}-greenways`}>
        <li>
            <span class="legend-row">
                <i class="swatch greenway" style:background={GREENWAY_COLOR} aria-hidden="true"></i>
                <span>{$i18n.t('maps.greenways.entry')}</span>
            </span>
        </li>
    </ul>
    <p class="legend-hint">
        {$i18n.t('maps.greenways.note')}
        {$i18n.t('maps.greenways.source')}
    </p>
{/snippet}

{#snippet eraBody()}
    <fieldset class="legend-modes">
        <legend class="legend-group">{$i18n.t('maps.mode.label')}</legend>
        <div class="mode-chips">
            {#each modes as m (m)}
                <label class="mode-chip">
                    <input
                        type="radio"
                        name={`${uid}-mode`}
                        value={m}
                        disabled={Boolean(unavailable[m])}
                        aria-describedby={unavailable[m] ? `${uid}-mode-${m}` : undefined}
                        bind:group={mode}
                    />
                    <span>{$i18n.t(`maps.mode.${m}`)}</span>
                </label>
            {/each}
        </div>
        <!-- Colorations grisées : pour quelles époques elles existent (et, si l'une d'elles est
             choisie, la carte garde le trafic). -->
        {#each unavailableNotes as m (m)}
            <p class="legend-hint mode-note" id={`${uid}-mode-${m}`}>
                {unavailable[m]}
                {#if m === mode}{$i18n.t('maps.coloring.fallback')}{/if}
            </p>
        {/each}
    </fieldset>

    {#if interactive}
        <p class="legend-hint">{$i18n.t('maps.legend.hint')}</p>
    {/if}

    {#if shownMode === 'signalling'}
        <div class="mode-block">{@render signallingBody()}</div>
    {:else if shownMode !== 'traffic'}
        <p class="legend-group" id={`${uid}-colours`}>
            {$i18n.t(modeTitleKey)}
        </p>
        <ul aria-labelledby={`${uid}-colours`}>
            {#each modeRows as r (r.key)}
                <li>
                    <span class="legend-row">
                        <i class="swatch" style={r.swatch} aria-hidden="true"></i>
                        <span>{$i18n.t(r.labelKey)}</span>
                    </span>
                </li>
            {/each}
        </ul>
        {#if shownMode === 'speed'}
            <p class="legend-hint">{$i18n.t('maps.coloring.speed_note')}</p>
        {/if}
        {#if shownMode === 'speed' || shownMode === 'electrification'}
            <p class="legend-hint">{$i18n.t('maps.coloring.labels_note')}</p>
        {/if}
    {:else}
        <p class="legend-group" id={`${uid}-traffic`}>{$i18n.t('maps.traffic.title')}</p>
        <ul aria-labelledby={`${uid}-traffic`}>
            {#each trafficRows as r (r.key)}
                {@render row(r)}
            {/each}
        </ul>
    {/if}

    <p class="legend-group" id={`${uid}-status`}>{$i18n.t('maps.status.title')}</p>
    <ul aria-labelledby={`${uid}-status`}>
        {#each statusRowsView as r (r.key)}
            {@render row(r)}
        {/each}
        {#if showAdvocacy}
            <li>
                <span class="legend-row">
                    <i class="swatch halo" style:background={THREATENED_COLOR} aria-hidden="true"
                    ></i>
                    <span>{$i18n.t('maps.legend.threatened')}</span>
                </span>
            </li>
            <li>
                <span class="legend-row">
                    <i class="swatch halo" style:background={REOPENING_COLOR} aria-hidden="true"
                    ></i>
                    <span>{$i18n.t('maps.legend.reopening')}</span>
                </span>
            </li>
        {/if}
    </ul>

    <!-- Gares : mêmes couleurs que la couche vff-stations-points (railStyle.js STATION_STYLES). -->
    <p class="legend-group" id={`${uid}-stations`}>
        {$i18n.t('maps.legend.stations')}
        <span class="legend-group-note">{$i18n.t('maps.legend.stations_hint')}</span>
    </p>
    <ul aria-labelledby={`${uid}-stations`}>
        {#each stationRows as r (r.key)}
            <li>
                <span class="legend-row">
                    <i
                        class="swatch station"
                        style:border-color={r.color}
                        style:background={r.fill ?? '#ffffff'}
                        aria-hidden="true"
                    ></i>
                    <span>{$i18n.t(r.labelKey)}</span>
                </span>
            </li>
        {/each}
    </ul>
{/snippet}

<style>
    /* Panneau posé sur la carte : la hauteur est bornée par son conteneur (.legend-slot de la
       page, qui a un haut et un bas), l'en-tête reste visible et le contenu défile. */
    .vff-legend {
        display: flex;
        flex-direction: column;
        width: 272px;
        max-width: calc(100vw - 48px);
        max-height: 100%;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.35;
    }
    .vff-legend.collapsed {
        width: auto;
    }

    .legend-toggle {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        min-height: 40px;
        padding: 8px 12px;
        border: 0;
        border-radius: var(--vff-radius-md);
        background: transparent;
        color: var(--vff-text);
        text-align: left;
        cursor: pointer;
    }
    /* Téléphone : cible tactile de 44 px. */
    @media (max-width: 600px) {
        .legend-toggle {
            min-height: 44px;
        }
    }
    .vff-legend:not(.collapsed) .legend-toggle {
        border-bottom: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md) var(--vff-radius-md) 0 0;
    }
    .legend-toggle:hover {
        background-color: var(--vff-bg-muted);
    }
    .legend-toggle:focus-visible {
        outline-offset: -2px;
    }
    .legend-title {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-sm);
        font-weight: 700;
    }
    .legend-toggle .bi {
        color: var(--vff-text-muted);
        font-size: 14px;
    }

    .legend-body {
        min-height: 0;
        padding: 4px 12px 12px;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
    }
    .legend-body[hidden] {
        display: none;
    }

    /* Choix de la couleur des tracés : boutons radio (une seule tabulation, flèches pour
       changer) dessinés en pastilles qui passent à la ligne au besoin (allemand). */
    .legend-modes {
        margin: 8px 0 0;
        padding: 0;
        border: 0;
        min-width: 0;
    }
    .mode-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    /* Les pastilles s'étirent pour remplir chaque rangée (pas de pastille orpheline à gauche). */
    .mode-chip {
        position: relative;
        display: inline-flex;
        flex: 1 1 auto;
    }
    .mode-chip input {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        opacity: 0;
        cursor: pointer;
    }
    .mode-chip span {
        display: inline-flex;
        align-items: center;
        min-height: 30px;
        padding: 4px 12px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        color: var(--vff-text);
        white-space: nowrap;
        flex: 1;
        justify-content: center;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .mode-chip:hover span {
        background-color: var(--vff-bg-muted);
    }
    .mode-chip input:checked + span {
        border-color: var(--vff-accent);
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-weight: 600;
    }
    .mode-chip input:focus-visible + span {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    /* Coloration sans donnée aux époques affichées : grisée, bord en tirets (pas seulement une teinte),
       la phrase sous les pastilles dit pourquoi. */
    .mode-chip input:disabled {
        cursor: not-allowed;
    }
    .mode-chip input:disabled + span {
        border-style: dashed;
        color: var(--vff-text-muted);
        background-color: transparent;
    }
    .mode-chip input:disabled:checked + span {
        border-color: var(--vff-border-control);
        font-weight: 400;
    }
    .mode-note {
        margin-top: 8px;
    }
    .mode-block {
        margin-top: 4px;
    }

    .legend-hint {
        margin: 10px 0 0;
        color: var(--vff-text-muted);
    }
    .legend-group {
        margin: 14px 0 4px;
        padding: 0;
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        color: var(--vff-text);
    }
    .legend-modes .legend-group {
        margin: 0 0 6px;
    }
    .legend-group-note {
        display: block;
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    ul {
        display: flex;
        flex-direction: column;
        gap: 1px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    li {
        font-family: inherit;
        font-size: inherit;
        line-height: inherit;
    }
    .legend-row {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        min-height: 28px;
        padding: 3px 6px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: inherit;
        font: inherit;
        text-align: left;
    }
    button.legend-row {
        cursor: pointer;
    }
    button.legend-row:hover {
        background-color: var(--vff-bg-muted);
    }
    button.legend-row:focus-visible {
        outline-offset: -2px;
    }
    /* Catégorie masquée : barrée et grisée (et aria-pressed="false"), la couleur n'est pas
       seule à le dire. */
    .legend-row.off span {
        color: var(--vff-text-muted);
        text-decoration: line-through;
    }
    .legend-row.off .swatch {
        opacity: 0.3;
    }
    @media (pointer: coarse) {
        .legend-row {
            min-height: 36px;
        }
        .mode-chip span {
            min-height: 36px;
        }
    }

    .swatch {
        display: inline-block;
        flex-shrink: 0;
        width: 22px;
        border-radius: 2px;
    }
    /* Halo de campagne : bande large et pâle, comme le halo flou de la carte. */
    .swatch.halo {
        height: 8px;
        border-radius: 4px;
        opacity: 0.5;
    }
    /* Pastille de gare : rond blanc cerclé de la couleur du statut (comme sur la carte). */
    /* Vue empilée : bande pleine, plus haute, pour lire la progression des teintes. */
    .swatch.stack {
        height: 6px;
        border-radius: 3px;
    }
    .view-rows {
        margin-top: 8px;
    }
    /* Accidents : point plein cerclé de clair ; zone pâle et anneau quand seule la commune est connue. */
    .swatch.accident {
        width: 10px;
        height: 10px;
        margin: 0 6px;
        border: 1.5px solid;
        border-radius: 50%;
    }
    /* Passage à niveau : point de la teinte du groupe, cerclé de clair (comme sur la carte). */
    .swatch.point {
        width: 10px;
        height: 10px;
        margin: 0 6px;
        border: 1.5px solid;
        border-radius: 50%;
    }
    /* Voie verte : trait plein rose cerclé de blanc (comme sur la carte), jamais en tirets. */
    .swatch.greenway {
        height: 4px;
        border-radius: 2px;
        box-shadow: 0 0 0 1.5px #fff;
    }
    .swatch.accident-area {
        position: relative;
        width: 22px;
        height: 22px;
        border: 1px solid color-mix(in srgb, var(--accident) 45%, transparent);
        border-radius: 50%;
        background-color: color-mix(in srgb, var(--accident) 12%, transparent);
    }
    .swatch.accident-area::after {
        content: '';
        position: absolute;
        inset: 6px;
        border: 2px solid var(--accident);
        border-radius: 50%;
        background-color: #fff;
    }
    .legend-more {
        margin: 6px 0 0;
    }
    /* Un bloc par calque facultatif, fermé par un filet avant le bloc suivant ou le réseau. */
    .overlay-block {
        padding-bottom: 10px;
        border-bottom: 1px solid var(--vff-border);
    }
    .overlay-block + .overlay-block {
        padding-top: 4px;
    }
    .accidents-group,
    .overlay-group {
        margin-top: 8px;
    }
    .legend-more :global(a) {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        font-weight: 600;
    }
    .swatch.station {
        width: 10px;
        height: 10px;
        margin: 0 6px;
        border: 2px solid;
        border-radius: 50%;
    }
    /* Vue ferroviaire : gare intermédiaire, trait court en travers d'un axe (comme sur le plan). */
    .swatch.tick {
        position: relative;
        height: 6px;
        border-radius: 3px;
        background-color: var(--vff-text-muted);
    }
    .swatch.tick::after {
        content: '';
        position: absolute;
        top: -4px;
        left: 10px;
        width: 2px;
        height: 14px;
        background-color: var(--ink);
    }
</style>
