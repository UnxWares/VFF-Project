<script>
    import { i18n } from '$lib/i18n.js';
    import MapListingColumn from '$components/Map/MapListingColumn.svelte';
    import { LINE_STATES, STATION_STATES, createListingCache } from '$lib/mapListing.js';

    /**
     * « Liste de ce qui est affiché » : alternative textuelle à la carte, pour les lecteurs d'écran, le
     * clavier et les petits écrans. Sous la carte, les lignes et les gares de la zone visible à l'époque
     * choisie (nom, état, kilomètres dans la zone, lien vers la fiche), filtrables par état comme la
     * légende, triées, paginées. Avec la comparaison, les deux époques côte à côte.
     *
     * Props : eras ([{ year, title }] : une ou deux époques, la plus ancienne d'abord), bbox (zone visible
     * [ouest, sud, est, nord] ou null), hidden (catégories masquées dans la légende : leurs lignes ne sont
     * pas listées), version (version des données), onClose (bouton « Fermer la liste »). Le parent monte le
     * composant seulement quand la liste est ouverte : aucune requête n'est faite avant.
     */
    let {
        eras,
        bbox = null,
        hidden = [],
        version = null,
        onClose = null,
        headingEl = $bindable(),
    } = $props();

    const uid = $props.id();
    const cache = createListingCache();

    let onlyLine = $state('');
    let onlyStation = $state('');
    let sort = $state('km');

    // Catégories de lignes proposées au filtre : celles que la légende n'a pas masquées.
    const lineOptions = $derived(LINE_STATES.filter((s) => !hidden.includes(s.key)));
    const hiddenCount = $derived(LINE_STATES.filter((s) => hidden.includes(s.key)).length);
    // Un état masqué après son choix ne doit pas laisser une liste vide sans explication.
    $effect(() => {
        if (onlyLine && hidden.includes(onlyLine)) onlyLine = '';
    });
</script>

<section class="listing" id="liste-carte" aria-labelledby="{uid}-title">
    <div class="head">
        <h2 id="{uid}-title" tabindex="-1" bind:this={headingEl}>
            {$i18n.t('maps.listing.title')}
        </h2>
        {#if onClose}
            <button type="button" class="vff-btn secondary small" onclick={onClose}>
                {$i18n.t('maps.listing.close')}
            </button>
        {/if}
    </div>
    <p class="intro">{$i18n.t('maps.listing.intro')}</p>

    <div class="filters">
        <div class="vff-field">
            <label class="vff-label" for="{uid}-line">{$i18n.t('maps.listing.filter_lines')}</label>
            <select id="{uid}-line" class="vff-select" bind:value={onlyLine}>
                <option value="">{$i18n.t('maps.listing.all_states')}</option>
                {#each lineOptions as state (state.key)}
                    <option value={state.key}>{$i18n.t(state.labelKey)}</option>
                {/each}
            </select>
        </div>
        <div class="vff-field">
            <label class="vff-label" for="{uid}-station">
                {$i18n.t('maps.listing.filter_stations')}
            </label>
            <select id="{uid}-station" class="vff-select" bind:value={onlyStation}>
                <option value="">{$i18n.t('maps.listing.all_states')}</option>
                {#each STATION_STATES as state (state.key)}
                    <option value={state.key}>{$i18n.t(state.labelKey)}</option>
                {/each}
            </select>
        </div>
        <div class="vff-field">
            <label class="vff-label" for="{uid}-sort">{$i18n.t('maps.listing.sort')}</label>
            <select id="{uid}-sort" class="vff-select" bind:value={sort}>
                <option value="km">{$i18n.t('maps.listing.sort_km')}</option>
                <option value="name">{$i18n.t('maps.listing.sort_name')}</option>
            </select>
        </div>
    </div>
    {#if hiddenCount > 0}
        <p class="note">{$i18n.t('maps.listing.hidden_note')}</p>
    {/if}

    <div class="columns" class:two={eras.length > 1}>
        {#each eras as era (era.year)}
            <MapListingColumn
                year={era.year}
                title={era.title}
                {bbox}
                {hidden}
                {onlyLine}
                {onlyStation}
                {sort}
                {version}
                {cache}
            />
        {/each}
    </div>
</section>

<style>
    .listing {
        scroll-margin-top: var(--vff-space-4);
        padding-block: var(--vff-space-5) var(--vff-space-6);
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-3) var(--vff-space-5);
        margin-bottom: var(--vff-space-3);
    }
    h2 {
        margin: 0;
        font-size: var(--vff-fs-h2);
        line-height: var(--vff-lh-heading);
    }
    h2:focus-visible {
        outline: 3px solid var(--vff-focus, currentColor);
        outline-offset: 4px;
        border-radius: var(--vff-radius-sm);
    }
    .intro,
    .note {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .note {
        margin-top: calc(var(--vff-space-2) * -1);
        font-size: var(--vff-fs-sm);
    }
    .filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
        gap: var(--vff-space-3) var(--vff-space-5);
        margin-bottom: var(--vff-space-5);
    }
    .columns {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-6);
    }
    @media (min-width: 900px) {
        .columns.two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--vff-space-7);
        }
    }
</style>
