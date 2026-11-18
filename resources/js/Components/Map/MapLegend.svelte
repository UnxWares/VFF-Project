<script>
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
     *   mode        : couleur des tracés (bindable) : 'traffic' | 'electrification' | 'gauge'
     */
    let {
        era = null,
        hidden = $bindable([]),
        interactive = true,
        currentEra = 2030,
        mode = $bindable('traffic'),
    } = $props();

    const uid = $props.id();
    const bodyId = `${uid}-body`;

    // Ouverte d'emblée sur ordinateur, repliée sur tablette et téléphone ; lu avant le premier
    // rendu pour que la légende ne s'ouvre pas puis se referme au chargement.
    let open = $state(
        typeof window === 'undefined' || !window.matchMedia?.('(max-width: 900px)').matches,
    );

    const title = $derived(
        era === null || era === undefined
            ? $i18n.t('maps.legend.expand')
            : era === currentEra
              ? $i18n.t('maps.legend.title_current')
              : $i18n.t('maps.legend.title', { year: era }),
    );

    // Lignes de légende du mode électrification / écartement (couleur seule, non masquables).
    const modeRows = $derived(
        (mode === 'electrification'
            ? ELECTRIFICATION_STYLES
            : mode === 'gauge'
              ? GAUGE_STYLES
              : []
        ).map((s) => ({
            key: s.key,
            labelKey: s.labelKey,
            swatch: swatchStyle(s.color),
        })),
    );

    // Halos de campagne : légende affichée seulement quand la carte peut les montrer.
    const showAdvocacy = $derived(era === null || era === currentEra);

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
            swatch: swatchStyle(mode === 'traffic' ? r.color : STATION_UNCERTAIN_COLOR, r.dash),
        })),
    );

    // Gares : ouvertes (voyageurs, touristique, fret seul) et fermées.
    const stationRows = STATION_STYLES;

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
        <fieldset class="legend-modes">
            <legend class="legend-group">{$i18n.t('maps.mode.label')}</legend>
            <div class="mode-chips">
                {#each MAP_MODES as m (m)}
                    <label class="mode-chip">
                        <input type="radio" name={`${uid}-mode`} value={m} bind:group={mode} />
                        <span>{$i18n.t(`maps.mode.${m}`)}</span>
                    </label>
                {/each}
            </div>
        </fieldset>

        {#if interactive}
            <p class="legend-hint">{$i18n.t('maps.legend.hint')}</p>
        {/if}

        {#if mode !== 'traffic'}
            <p class="legend-group" id={`${uid}-colours`}>
                {$i18n.t(mode === 'gauge' ? 'maps.gauge.title' : 'maps.electrification.title')}
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
                        <i
                            class="swatch halo"
                            style:background={THREATENED_COLOR}
                            aria-hidden="true"
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
    </div>
</div>

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
        font-size: 0.9375rem;
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
        padding: 4px 10px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
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
    .swatch.station {
        width: 10px;
        height: 10px;
        margin: 0 6px;
        border: 2px solid;
        border-radius: 50%;
    }
</style>
