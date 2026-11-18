<script>
    import { onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        HERITAGE_STYLES,
        STATUS_STYLES,
        THREATENED_COLOR,
        REOPENING_COLOR,
        STATION_STYLES,
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
     * Props :
     *   era         : année affichée (titre « Réseau en 1950 »)
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

    let open = $state(true);

    onMount(() => {
        // Replie la légende par défaut sur petit écran (elle couvrirait la carte).
        if (window.matchMedia?.('(max-width: 700px)').matches) open = false;
    });

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
            swatch: swatchStyle(mode === 'traffic' ? r.color : '#64748b', r.dash),
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

<div class="vff-legend" class:collapsed={!open}>
    <div class="legend-head">
        <strong
            >{era
                ? $i18n.t('maps.legend.title', { year: era })
                : $i18n.t('maps.legend.expand')}</strong
        >
        <button
            type="button"
            class="legend-collapse"
            aria-expanded={open}
            title={open ? $i18n.t('maps.legend.collapse') : $i18n.t('maps.legend.expand')}
            aria-label={open ? $i18n.t('maps.legend.collapse') : $i18n.t('maps.legend.expand')}
            onclick={() => (open = !open)}
        >
            <i class="bi {open ? 'bi-chevron-up' : 'bi-chevron-down'}"></i>
        </button>
    </div>

    {#if open}
        <div class="legend-modes" role="group" aria-label={$i18n.t('maps.mode.label')}>
            <span class="legend-modes-label">{$i18n.t('maps.mode.label')}</span>
            <div class="legend-modes-buttons">
                {#each MAP_MODES as m (m)}
                    <button
                        type="button"
                        class:active={mode === m}
                        aria-pressed={mode === m}
                        onclick={() => (mode = m)}
                    >
                        {$i18n.t(`maps.mode.${m}`)}
                    </button>
                {/each}
            </div>
        </div>

        {#if mode !== 'traffic'}
            <p class="legend-group">
                {$i18n.t(mode === 'gauge' ? 'maps.gauge.title' : 'maps.electrification.title')}
            </p>
            <ul>
                {#each modeRows as r (r.key)}
                    <li>
                        <span class="legend-row static"
                            ><i class="swatch" style={r.swatch}></i><span
                                >{$i18n.t(r.labelKey)}</span
                            ></span
                        >
                    </li>
                {/each}
            </ul>
        {:else}
            <p class="legend-group">{$i18n.t('maps.traffic.title')}</p>
            <ul>
                {#each trafficRows as r (r.key)}
                    <li>
                        {#if interactive}
                            <button
                                type="button"
                                class="legend-row"
                                class:off={!isOn(r.key)}
                                aria-pressed={isOn(r.key)}
                                title={$i18n.t(
                                    isOn(r.key) ? 'maps.legend.hide' : 'maps.legend.show',
                                    { label: $i18n.t(r.labelKey) },
                                )}
                                onclick={() => toggle(r.key)}
                            >
                                <i class="swatch" style={r.swatch}></i>
                                <span>{$i18n.t(r.labelKey)}</span>
                            </button>
                        {:else}
                            <span class="legend-row"
                                ><i class="swatch" style={r.swatch}></i><span
                                    >{$i18n.t(r.labelKey)}</span
                                ></span
                            >
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}

        <p class="legend-group">{$i18n.t('maps.status.title')}</p>
        <ul>
            {#each statusRowsView as r (r.key)}
                <li>
                    {#if interactive}
                        <button
                            type="button"
                            class="legend-row"
                            class:off={!isOn(r.key)}
                            aria-pressed={isOn(r.key)}
                            title={$i18n.t(isOn(r.key) ? 'maps.legend.hide' : 'maps.legend.show', {
                                label: $i18n.t(r.labelKey),
                            })}
                            onclick={() => toggle(r.key)}
                        >
                            <i class="swatch" style={r.swatch}></i>
                            <span>{$i18n.t(r.labelKey)}</span>
                        </button>
                    {:else}
                        <span class="legend-row"
                            ><i class="swatch" style={r.swatch}></i><span
                                >{$i18n.t(r.labelKey)}</span
                            ></span
                        >
                    {/if}
                </li>
            {/each}
            {#if showAdvocacy}
                <li>
                    <span class="legend-row static">
                        <i class="swatch halo" style="background:{THREATENED_COLOR};"></i>
                        <span>{$i18n.t('maps.legend.threatened')}</span>
                    </span>
                </li>
                <li>
                    <span class="legend-row static">
                        <i class="swatch halo" style="background:{REOPENING_COLOR};"></i>
                        <span>{$i18n.t('maps.legend.reopening')}</span>
                    </span>
                </li>
            {/if}
        </ul>

        <!-- Gares : mêmes couleurs que la couche vff-stations-points (railStyle.js STATION_STYLES). -->
        <p class="legend-group">{$i18n.t('maps.legend.stations')}</p>
        <ul>
            {#each stationRows as r (r.key)}
                <li>
                    <span class="legend-row static">
                        <i
                            class="swatch station"
                            style="border-color:{r.color};{r.fill ? ` background:${r.fill};` : ''}"
                        ></i>
                        <span>{$i18n.t(r.labelKey)}</span>
                    </span>
                </li>
            {/each}
        </ul>

        {#if interactive}
            <p class="legend-hint">{$i18n.t('maps.legend.hint')}</p>
        {/if}
    {/if}
</div>

<style>
    .vff-legend {
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        padding: 10px 12px;
        font-family: var(--vff-font-body);
        font-size: 12px;
        color: var(--vff-text);
        box-shadow: 0 4px 12px var(--vff-shadow);
        width: 230px;
        max-width: calc(100vw - 48px);
        /* Le conteneur (.map-legend-slot) n'a pas de hauteur : un pourcentage ne
           s'y résout pas et la légende débordait de la carte sur le sélecteur
           d'époque (onglet « 1830-1890 » et bouton lecture inaccessibles). */
        max-height: min(440px, calc(100vh - 160px));
        overflow-y: auto;
    }
    .vff-legend.collapsed {
        width: auto;
    }
    .legend-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .legend-head strong {
        font-family: var(--vff-font-display);
        font-size: 13px;
        color: var(--vff-text);
    }
    .legend-collapse {
        border: 0;
        background: transparent;
        color: var(--vff-text-muted);
        cursor: pointer;
        padding: 2px 4px;
        font-size: 13px;
        line-height: 1;
    }
    .legend-collapse:hover {
        color: var(--vff-accent);
    }
    .legend-modes {
        margin: 8px 0 2px;
    }
    .legend-modes-label {
        display: block;
        margin-bottom: 4px;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--vff-text-muted);
        font-family: var(--vff-font-display);
    }
    .legend-modes-buttons {
        display: flex;
        border: 1px solid var(--vff-border);
        border-radius: 6px;
        overflow: hidden;
    }
    .legend-modes-buttons button {
        flex: 1;
        padding: 4px 2px;
        border: 0;
        background: transparent;
        font: inherit;
        font-size: 11px;
        color: var(--vff-text);
        cursor: pointer;
    }
    .legend-modes-buttons button + button {
        border-left: 1px solid var(--vff-border);
    }
    .legend-modes-buttons button.active {
        background: var(--vff-accent);
        color: #ffffff;
    }
    .legend-modes-buttons button:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: -2px;
    }
    .legend-group {
        margin: 10px 0 4px;
        font-family: var(--vff-font-display);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--vff-text-muted);
    }
    ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    /* general.css impose `li { font-size: 20px }` : on revient à la taille de la légende. */
    li {
        font-family: inherit;
        font-size: 12px;
        line-height: 1.3;
    }
    .legend-row {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 3px 4px;
        border: 0;
        border-radius: 4px;
        background: transparent;
        font: inherit;
        color: inherit;
        text-align: left;
    }
    button.legend-row {
        cursor: pointer;
    }
    @media (max-width: 768px) {
        .legend-row,
        .legend-modes-buttons button {
            min-height: 28px;
        }
        .legend-collapse {
            min-width: 28px;
            min-height: 28px;
        }
    }
    button.legend-row:hover {
        background-color: var(--vff-bg-elevated);
    }
    button.legend-row:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 1px;
    }
    .legend-row.off {
        opacity: 0.4;
    }
    .legend-row.off span {
        text-decoration: line-through;
    }
    .swatch {
        display: inline-block;
        flex-shrink: 0;
        width: 22px;
        border-radius: 2px;
    }
    .swatch.halo {
        height: 8px;
        opacity: 0.45;
        filter: blur(1px);
    }
    /* Pastille de gare : rond blanc cerclé de la couleur du statut (comme sur la carte). */
    .swatch.station {
        width: 10px;
        height: 10px;
        margin: 0 6px;
        border: 2px solid;
        border-radius: 50%;
        background: #ffffff;
    }
    .legend-hint {
        margin: 8px 0 0;
        font-size: 11px;
        color: var(--vff-text-muted);
    }
</style>
