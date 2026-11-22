<script>
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import DirectoryTable from '$components/Directory/DirectoryTable.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { effectiveSort, sortOptions, sortParam } from '$lib/directorySort.js';
    import { STATUS_COLOR, TRAFFIC_COLOR, HERITAGE_COLOR } from '$lib/railStyle.js';

    let { lines, filters, stats, overview, sort_chosen = false, approximate = false } = $props();

    const NETWORKS = ['principal', 'secondaire', 'militaire', 'particulier', 'none'];
    const TRAFFICS = ['passenger', 'mixed', 'freight', 'unknown'];
    const SORTS = [
        ['relevance', 'sort_relevance'],
        ['name', 'sort_name'],
        ['name-desc', 'sort_name_desc'],
        ['code', 'sort_code'],
    ];

    // Les champs partent des filtres reçus puis évoluent avec la saisie : valeur initiale voulue.
    // svelte-ignore state_referenced_locally
    let q = $state(filters.q ?? '');
    // svelte-ignore state_referenced_locally
    let network = $state(filters.network ?? '');
    // svelte-ignore state_referenced_locally
    let traffic = $state(filters.traffic ?? '');
    // svelte-ignore state_referenced_locally
    let status = $state(filters.status ?? '');
    // svelte-ignore state_referenced_locally
    let heritage = $state(!!filters.heritage);
    // Tri choisi par le visiteur, '' sinon (pertinence avec une recherche, nom sans : $lib/directorySort.js).
    // svelte-ignore state_referenced_locally
    let sort = $state(sort_chosen ? (filters.sort ?? '') : '');

    const hasFilters = $derived(!!(q || network || traffic || status || heritage));
    const fmt = (n) => Number(n ?? 0).toLocaleString($i18n.language ?? 'fr');
    const hasUnknown = $derived(lines.data.some((l) => l.state === 'unknown'));

    function currentParams() {
        /** @type {Record<string, string|number>} */
        const params = {};
        if (q) params.q = q;
        if (network) params.network = network;
        if (traffic) params.traffic = traffic;
        if (status) params.status = status;
        if (heritage) params.heritage = 1;
        if (sortParam(sort, q)) params.sort = sortParam(sort, q);
        return params;
    }

    let debounceTimer;
    function applyFilters(now = false) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(
            () => {
                router.get('/lines', currentParams(), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                });
            },
            now ? 0 : 250,
        );
    }

    function clearFilters() {
        clearTimeout(debounceTimer);
        q = '';
        network = '';
        traffic = '';
        status = '';
        heritage = false;
        sort = '';
        router.get('/lines', {}, { preserveState: true, replace: true });
    }

    function goToPage(p) {
        router.get('/lines', { ...currentParams(), page: p }, { preserveScroll: false });
    }

    /** Une période « (1930-1940) » ne se coupe pas au trait d'union (joint de mots après le tiret). */
    const keepPeriods = (name) => String(name ?? '').replace(/(\d)-(\d)/g, '$1-\u2060$2');

    /** Trafic ou état d'une ligne : pastille de la couleur de la carte + texte. */
    function trafficCell(l) {
        if (l.state === 'closed') {
            return {
                text: $i18n.t('lines.states.closed'),
                color: STATUS_COLOR.abandoned,
                dashed: true,
            };
        }
        if (l.state !== 'open') {
            return { text: $i18n.t('lines.states.unknown'), color: null, dashed: false };
        }
        if (l.heritage && (!l.traffic || l.traffic === 'unknown')) {
            return {
                text: $i18n.t('lines.traffics.heritage'),
                color: HERITAGE_COLOR,
                dashed: false,
            };
        }
        const key = l.traffic ?? 'unknown';
        return {
            text: $i18n.t(`lines.traffics.${key}`),
            color: TRAFFIC_COLOR[key] ?? TRAFFIC_COLOR.unknown,
            dashed: false,
        };
    }
</script>

<svelte:head>
    <title>{$i18n.t('lines.index.meta_title')} - VFF</title>
    <meta
        name="description"
        content={$i18n.t('lines.index.meta_description', { total: overview.total })}
    />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('lines.index.title')}
            subtitle={$i18n.t('lines.index.subtitle', {
                total: fmt(overview.total),
                open: fmt(overview.open),
                closed: fmt(overview.closed),
                unknown: fmt(overview.unknown),
            })}
        >
            <p class="quiz-link">
                <Link href="/quiz?game=lines" class="vff-link">{$i18n.t('quiz.links.lines')}</Link>
            </p>
        </PageHero>

        <div class="vff-container directory">
            <DirectoryFilters
                label={$i18n.t('lines.index.filters_title')}
                results={$i18n.t('lines.index.results', { count: fmt(lines.total) })}
                canReset={hasFilters}
                resetLabel={$i18n.t('lines.index.reset')}
                onreset={clearFilters}
                onsubmit={() => applyFilters(true)}
            >
                <div class="vff-field wide">
                    <label class="vff-label" for="lines-q"
                        >{$i18n.t('lines.index.search_label')}</label
                    >
                    <input
                        id="lines-q"
                        class="vff-input"
                        type="search"
                        name="q"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={$i18n.t('lines.index.search_hint')}
                        bind:value={q}
                        oninput={() => applyFilters()}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="lines-network"
                        >{$i18n.t('lines.index.filter_network')}</label
                    >
                    <select
                        id="lines-network"
                        class="vff-select"
                        bind:value={network}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('lines.index.all_networks')}</option>
                        {#each NETWORKS as n (n)}
                            <option value={n}
                                >{$i18n.t(`lines.networks.${n}`)} ({fmt(
                                    stats.networks?.[n],
                                )})</option
                            >
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="lines-traffic"
                        >{$i18n.t('lines.index.filter_traffic')}</label
                    >
                    <select
                        id="lines-traffic"
                        class="vff-select"
                        bind:value={traffic}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('lines.index.all_traffics')}</option>
                        {#each TRAFFICS as t (t)}
                            <option value={t}
                                >{$i18n.t(`lines.traffics.${t}`)} ({fmt(
                                    stats.traffics?.[t],
                                )})</option
                            >
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="lines-state"
                        >{$i18n.t('lines.index.filter_state')}</label
                    >
                    <select
                        id="lines-state"
                        class="vff-select"
                        bind:value={status}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('lines.index.all_states')}</option>
                        <option value="open">{$i18n.t('lines.states.open')}</option>
                        <option value="closed">{$i18n.t('lines.states.closed')}</option>
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="lines-sort"
                        >{$i18n.t('lines.index.sort_label')}</label
                    >
                    <select
                        id="lines-sort"
                        class="vff-select"
                        value={effectiveSort(sort, q)}
                        onchange={(e) => {
                            sort = e.currentTarget.value;
                            applyFilters(true);
                        }}
                    >
                        {#each sortOptions(SORTS, q) as [value, key] (value)}
                            <option {value}>{$i18n.t(`lines.index.${key}`)}</option>
                        {/each}
                    </select>
                </div>
                <label class="vff-check wide">
                    <input
                        type="checkbox"
                        bind:checked={heritage}
                        onchange={() => applyFilters(true)}
                    />
                    {$i18n.t('lines.index.heritage')} ({fmt(stats.heritage)})
                </label>
            </DirectoryFilters>

            {#if approximate && lines.data.length}
                <p class="approximate" role="status">
                    {$i18n.t('lines.index.approximate', { query: filters.q })}
                </p>
            {/if}
            {#if lines.data.length}
                <DirectoryTable
                    label={$i18n.t('lines.index.table_label')}
                    caption={$i18n.t('lines.index.table_caption')}
                >
                    <thead>
                        <tr>
                            <th scope="col">{$i18n.t('lines.index.col_code')}</th>
                            <th scope="col">{$i18n.t('lines.index.col_name')}</th>
                            <th scope="col">{$i18n.t('lines.index.col_network')}</th>
                            <th scope="col">{$i18n.t('lines.index.col_traffic')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each lines.data as l (l.slug)}
                            {@const cell = trafficCell(l)}
                            <tr>
                                <td
                                    class={['code', { blank: !l.code }]}
                                    data-label={$i18n.t('lines.index.col_code')}
                                >
                                    {#if l.code}<span class="vff-plate">{l.code}</span>{/if}
                                </td>
                                <td class="name">
                                    <a class="line-link" href="/lines/{l.slug}"
                                        >{keepPeriods(l.name)}</a
                                    >
                                    {#if l.historical_operator}
                                        <span class="operator">{l.historical_operator}</span>
                                    {/if}
                                </td>
                                <td data-label={$i18n.t('lines.index.col_network')}>
                                    <span class={{ muted: !l.network }}
                                        >{$i18n.t(`lines.networks.${l.network ?? 'none'}`)}</span
                                    >
                                </td>
                                <td data-label={$i18n.t('lines.index.col_traffic')}>
                                    <span class={['traffic', { muted: !cell.color }]}>
                                        {#if cell.color}<i
                                                class={['vff-swatch', { dashed: cell.dashed }]}
                                                style:--swatch={cell.color}
                                                aria-hidden="true"
                                            ></i>{/if}{cell.text}
                                    </span>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </DirectoryTable>
                {#if hasUnknown}
                    <p class="note">{$i18n.t('lines.index.unknown_note')}</p>
                {/if}
            {:else}
                <EmptyState
                    icon="bi-search"
                    title={$i18n.t('lines.index.empty_title')}
                    text={$i18n.t('lines.index.empty_text')}
                />
            {/if}
        </div>

        {#if lines.last_page > 1}
            <Pagination current={lines.current_page} last={lines.last_page} onpage={goToPage} />
        {:else}
            <div class="end"></div>
        {/if}
    </main>
</Layout>

<style>
    .approximate {
        margin: 0 0 1rem;
        color: var(--vff-text-muted);
    }

    /* Lien vers le quiz des lignes, sous le chapeau du bandeau. */
    .quiz-link {
        margin: var(--vff-space-4) 0 0;
    }

    main {
        background-color: var(--vff-bg);
    }
    /* Panneau, tableau et notes : même grille que le bandeau, espacement régulier. */
    .directory {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-space-5);
    }
    .end {
        height: var(--vff-section-space);
    }
    .code {
        width: 1%;
        white-space: nowrap;
    }
    .code :global(.vff-plate) {
        padding-block: 0;
        font-size: var(--vff-fs-xs);
    }
    .name {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .line-link {
        /* Zone cliquable de 24 px au moins sans changer l'espacement des lignes. */
        padding-block: 4px;
        margin-block: -4px;
        display: inline-block;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        line-height: 1.3;
        color: var(--vff-text);
        text-decoration: none;
        text-underline-offset: 3px;
        text-decoration-thickness: 2px;
        /* Une période « (1930-1940) » ne se coupe pas au trait d'union. */
        hyphens: none;
    }
    .line-link:hover {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
    }
    .operator {
        display: block;
        margin-top: 2px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .traffic :global(.vff-swatch) {
        margin-right: 8px;
        vertical-align: middle;
    }
    .traffic :global(.vff-swatch.dashed) {
        height: 0;
        background: none;
        border-top: 4px dashed var(--swatch);
        border-radius: 0;
    }
    .muted {
        color: var(--vff-text-muted);
    }
    .note {
        max-width: var(--vff-measure);
        margin: calc(-1 * var(--vff-space-3)) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .code {
            width: auto;
        }
    }
</style>
