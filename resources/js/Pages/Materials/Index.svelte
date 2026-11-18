<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import DirectoryTable from '$components/Directory/DirectoryTable.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';

    let { groups = [], total = 0 } = $props();

    const TYPE_ICONS = {
        locomotive_vapeur: 'bi-fire',
        locomotive_diesel: 'bi-fuel-pump-fill',
        locomotive_electrique: 'bi-lightning-charge-fill',
        automotrice: 'bi-train-front-fill',
        autorail: 'bi-train-front',
        rame_tgv: 'bi-speedometer2',
        voiture: 'bi-train-lightrail-front',
        wagon: 'bi-box-seam',
    };
    const typeLabel = (type) => $i18n.t(`materials.types.${type}`, { defaultValue: type });
    const fmt = (n) => Number(n ?? 0).toLocaleString($i18n.language ?? 'fr');

    // Le parc est petit et arrive en une seule page : recherche et filtre se font ici, sans rechargement.
    let q = $state('');
    let type = $state('');
    const hasFilters = $derived(!!(q.trim() || type));

    /** Sans accents ni capitales : « evreux » trouve « Évreux ». */
    const fold = (text) =>
        String(text ?? '')
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase();

    const visible = $derived.by(() => {
        const needle = fold(q.trim());

        return groups
            .filter((g) => !type || g.type === type)
            .map((g) => ({
                ...g,
                items: g.items.filter(
                    (m) =>
                        !needle ||
                        fold(m.name).includes(needle) ||
                        fold(m.builder).includes(needle),
                ),
            }))
            .filter((g) => g.items.length);
    });
    const shown = $derived(visible.reduce((n, g) => n + g.items.length, 0));

    function reset() {
        q = '';
        type = '';
    }

    const period = (m) =>
        m.in_service_from
            ? `${m.in_service_from}-${m.in_service_to ?? $i18n.t('materials.present')}`
            : '';
</script>

<svelte:head>
    <title>{$i18n.t('materials.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('materials.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('materials.title')}
            subtitle={total > 0
                ? $i18n.t('materials.subtitle', { total: fmt(total) })
                : $i18n.t('materials.subtitle_empty')}
        />

        <div class="vff-container directory">
            {#if total === 0}
                <EmptyState
                    icon="bi-train-front"
                    headingLevel={2}
                    title={$i18n.t('materials.empty_title')}
                    text={$i18n.t('materials.empty_text')}
                >
                    <a href="/lines" class="vff-btn primary">
                        <i class="bi bi-signpost-split" aria-hidden="true"></i>
                        {$i18n.t('materials.empty_browse')}
                    </a>
                    <a href="/contribute" class="vff-btn secondary">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        {$i18n.t('materials.empty_cta')}
                    </a>
                </EmptyState>
            {:else}
                <DirectoryFilters
                    label={$i18n.t('materials.filters_title')}
                    results={$i18n.t('materials.results', { count: fmt(shown) })}
                    canReset={hasFilters}
                    resetLabel={$i18n.t('materials.reset')}
                    onreset={reset}
                >
                    <div class="vff-field wide">
                        <label class="vff-label" for="materials-q"
                            >{$i18n.t('materials.search_label')}</label
                        >
                        <input
                            id="materials-q"
                            class="vff-input"
                            type="search"
                            autocomplete="off"
                            enterkeyhint="search"
                            placeholder={$i18n.t('materials.search_hint')}
                            bind:value={q}
                        />
                    </div>
                    <div class="vff-field">
                        <label class="vff-label" for="materials-type"
                            >{$i18n.t('materials.filter_type')}</label
                        >
                        <select id="materials-type" class="vff-select" bind:value={type}>
                            <option value="">{$i18n.t('materials.all_types')}</option>
                            {#each groups as g (g.type)}
                                <option value={g.type}
                                    >{typeLabel(g.type)} ({fmt(g.items.length)})</option
                                >
                            {/each}
                        </select>
                    </div>
                </DirectoryFilters>

                {#each visible as g (g.type)}
                    <section class="type-block" aria-labelledby="type-{g.type}">
                        <h2 class="vff-section-title" id="type-{g.type}">
                            <i
                                class="bi {TYPE_ICONS[g.type] ?? 'bi-train-front'}"
                                aria-hidden="true"
                            ></i>
                            {typeLabel(g.type)}
                            <span class="count">({fmt(g.items.length)})</span>
                        </h2>
                        <DirectoryTable label={typeLabel(g.type)} caption={typeLabel(g.type)}>
                            <thead>
                                <tr>
                                    <th scope="col">{$i18n.t('materials.col_name')}</th>
                                    <th scope="col">{$i18n.t('materials.col_builder')}</th>
                                    <th scope="col" class="num"
                                        >{$i18n.t('materials.col_period')}</th
                                    >
                                </tr>
                            </thead>
                            <tbody>
                                {#each g.items as m (m.slug)}
                                    <tr>
                                        <td class="name">
                                            <a class="material-link" href="/materials/{m.slug}"
                                                >{m.name}</a
                                            >
                                        </td>
                                        <td
                                            class={{ blank: !m.builder }}
                                            data-label={$i18n.t('materials.col_builder')}
                                        >
                                            {m.builder ?? ''}
                                        </td>
                                        <td
                                            class={['num', { blank: !m.in_service_from }]}
                                            data-label={$i18n.t('materials.col_period')}
                                        >
                                            {period(m)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </DirectoryTable>
                    </section>
                {:else}
                    <EmptyState
                        icon="bi-search"
                        title={$i18n.t('materials.no_match_title')}
                        text={$i18n.t('materials.no_match_text')}
                    >
                        <button type="button" class="vff-btn secondary small" onclick={reset}>
                            {$i18n.t('materials.reset')}
                        </button>
                    </EmptyState>
                {/each}
            {/if}
        </div>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .directory {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-section-space);
    }
    .type-block {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
    }
    .type-block h2 {
        margin: 0;
    }
    .type-block h2 i {
        margin-right: 6px;
        color: var(--vff-accent-ink);
    }
    .count {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 400;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .material-link {
        display: inline-block;
        /* Zone cliquable de 24 px au moins sans changer l'espacement des lignes. */
        padding-block: 4px;
        margin-block: -4px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
        text-decoration: none;
        text-underline-offset: 3px;
        text-decoration-thickness: 2px;
    }
    .material-link:hover {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
    }
</style>
