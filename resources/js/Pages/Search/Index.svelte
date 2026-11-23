<script>
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { positionItems } from '$lib/siteSearch.js';
    import {
        FILTERABLE_TYPES,
        resultDetail,
        searchHref,
        splitAround,
        typeTabs,
    } from '$lib/searchPage.js';

    /**
     * Page de résultats de la recherche (/search?q=…) : lignes, gares, accidents et matériel roulant
     * (SearchPageController). Vue « tout » : les premiers résultats de chaque groupe et un lien vers
     * la suite ; vue d'un type : tous ses résultats, page par page, avec les filtres d'état et de
     * département pour les lignes et les gares. Tout réglage est dans l'adresse (partageable).
     */
    let {
        query = '',
        searched = false,
        filters = { type: '', state: '', dept: '' },
        groups = [],
        counts = {},
        total = 0,
        approximate = false,
        suggestion = null,
        departments = [],
        positions = [],
    } = $props();

    /** Traduction (toujours une chaîne : i18next la type aussi en objet). */
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const lang = $derived($i18n.language ?? 'fr');
    const fmt = (/** @type {number} */ n) => (n ?? 0).toLocaleString(lang);

    // Champ de recherche : part de la requête de la page, suit la saisie, revient à la requête
    // reçue à chaque nouvelle page (suggestion, retour arrière).
    let q = $derived(query);

    const type = $derived(filters.type ?? '');
    const filterable = $derived(FILTERABLE_TYPES.includes(type));
    const hasFilters = $derived(!!(filters.state || filters.dept));
    const tabs = $derived(
        typeTabs({
            q: query,
            type,
            state: filters.state,
            dept: filters.dept,
            counts,
            total,
        }),
    );
    // Département choisi absent des options (plus aucun résultat avec l'état choisi) : gardé quand même.
    const deptOptions = $derived(
        filters.dept && !departments.some((d) => d.code === filters.dept)
            ? [{ code: filters.dept, name: filters.dept, count: 0 }, ...departments]
            : departments,
    );
    const SUGGESTION = '\u0001';
    const suggestionText = $derived(
        splitAround(t('search.suggestion', { suggestion: SUGGESTION }), SUGGESTION),
    );
    const pageTitle = $derived(query ? t('search.page_title', { query }) : t('search.title'));
    const single = $derived(type !== '' ? groups[0] : null);
    const shown = $derived(groups.filter((g) => g.items.length));
    // Position sur une ligne (« 570000 PK 120 ») : fiche de la ligne à ce PK, carte centrée, ou bornes de la ligne.
    const positionList = $derived(positionItems(positions, t, lang));

    function visit(params, options = {}) {
        router.get(searchHref({ q: query, ...filters, ...params }), {}, options);
    }

    function submit(e) {
        e.preventDefault();
        // Nouvelle recherche : mêmes type et filtres, première page.
        visit({ q, page: 1 });
    }

    function setFilter(name, value) {
        visit({ [name]: value, page: 1 }, { preserveScroll: true, preserveState: true });
    }
</script>

<svelte:head>
    <title>{pageTitle} - VFF</title>
    <meta
        name="description"
        content={query ? t('search.meta', { query }) : t('search.meta_empty')}
    />
    <meta name="robots" content="noindex" />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={pageTitle} subtitle={query ? null : t('search.intro')}>
            <search class="search" aria-label={t('search.form_label')}>
                <form action="/search" method="get" onsubmit={submit}>
                    <label class="vff-sr-only" for="search-page-q">{t('search.field_label')}</label>
                    <input
                        id="search-page-q"
                        class="vff-input"
                        type="search"
                        name="q"
                        value={q}
                        oninput={(e) => (q = e.currentTarget.value)}
                        placeholder={t('search.placeholder')}
                        autocomplete="off"
                        spellcheck="false"
                        enterkeyhint="search"
                        maxlength="100"
                    />
                    <button class="vff-btn primary" type="submit">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <span>{t('search.submit')}</span>
                    </button>
                </form>
            </search>
        </PageHero>

        <div class="vff-container results">
            {#if !searched}
                <EmptyState
                    icon="bi-search"
                    title={t('search.empty_title')}
                    text={t('search.empty_text')}
                />
            {:else}
                {#if total > 0}
                    <nav class="types" aria-label={t('search.types_label')}>
                        <ul>
                            {#each tabs as tab (tab.type)}
                                <li>
                                    <Link
                                        href={tab.href}
                                        class={tab.current ? 'tab current' : 'tab'}
                                        aria-current={tab.current ? 'page' : undefined}
                                        preserveScroll
                                    >
                                        {t(`search.types.${tab.type || 'all'}`)}
                                        <span class="tab-count">{fmt(tab.count)}</span>
                                    </Link>
                                </li>
                            {/each}
                        </ul>
                    </nav>
                {/if}

                {#if filterable && (total > 0 || hasFilters)}
                    <form
                        class="filters"
                        aria-label={t('search.filters_label')}
                        onsubmit={(e) => e.preventDefault()}
                    >
                        <div class="vff-field">
                            <label class="vff-label" for="search-page-state"
                                >{t('search.state_label')}</label
                            >
                            <select
                                id="search-page-state"
                                class="vff-select"
                                value={filters.state}
                                onchange={(e) => setFilter('state', e.currentTarget.value)}
                            >
                                <option value="">{t('search.state_all')}</option>
                                <option value="open">{t('search.states.open')}</option>
                                <option value="closed">{t('search.states.closed')}</option>
                            </select>
                        </div>
                        <div class="vff-field">
                            <label class="vff-label" for="search-page-dept"
                                >{t('search.dept_label')}</label
                            >
                            <select
                                id="search-page-dept"
                                class="vff-select"
                                value={filters.dept}
                                onchange={(e) => setFilter('dept', e.currentTarget.value)}
                            >
                                <option value="">{t('search.dept_all')}</option>
                                {#each deptOptions as d (d.code)}
                                    <option value={d.code}
                                        >{t('search.dept_option', {
                                            name: d.name,
                                            code: d.code,
                                            count: fmt(d.count),
                                        })}</option
                                    >
                                {/each}
                            </select>
                        </div>
                        {#if hasFilters}
                            <button
                                type="button"
                                class="vff-btn secondary reset"
                                onclick={() =>
                                    visit(
                                        { state: '', dept: '', page: 1 },
                                        { preserveScroll: true },
                                    )}>{t('search.reset_filters')}</button
                            >
                        {/if}
                    </form>
                {/if}

                {#if positionList.length}
                    <section class="group" aria-labelledby="search-group-position">
                        <div class="group-head">
                            <h2 id="search-group-position">{t('nav.search.positions')}</h2>
                        </div>
                        <ul class="items">
                            {#each positionList as item (item.href)}
                                <li class="item">
                                    <Link class="item-link" href={item.href}>
                                        <span class="item-name">{item.name}</span>
                                    </Link>
                                    {#if item.detail}<p class="item-detail">{item.detail}</p>{/if}
                                </li>
                            {/each}
                        </ul>
                    </section>
                {/if}

                {#if total > 0 || !positionList.length}
                    <div class="summary">
                        <p role="status">
                            {single
                                ? t('search.results', { count: single.total, query })
                                : t('search.results', { count: total, query })}
                        </p>
                        {#if suggestion}
                            <p class="suggestion">
                                {suggestionText[0]}<Link href={searchHref({ q: suggestion, type })}
                                    >{suggestion}</Link
                                >{suggestionText[1]}
                            </p>
                        {/if}
                        {#if approximate}
                            <p class="note">{t('search.approximate', { query })}</p>
                        {/if}
                    </div>
                {/if}

                {#each shown as g (g.type)}
                    <section class="group" aria-labelledby="search-group-{g.type}">
                        <div class="group-head">
                            <h2 id="search-group-{g.type}">{t(`search.types.${g.type}`)}</h2>
                            <span class="group-count"
                                >{t('search.group_count', {
                                    count: g.total,
                                })}</span
                            >
                            {#if g.approximate && !approximate}
                                <span class="vff-badge muted">{t('search.group_approximate')}</span>
                            {/if}
                        </div>
                        <ol class="items" start={(g.current_page - 1) * g.per_page + 1}>
                            {#each g.items as item (item.slug)}
                                {@const href = `/${g.type}s/${item.slug}`}
                                <li class="item">
                                    <Link class="item-link" {href}>
                                        {#if g.type === 'line' && item.code}
                                            <span
                                                class={[
                                                    'vff-plate',
                                                    'code',
                                                    { hit: item.code_match },
                                                ]}
                                                ><span class="vff-sr-only"
                                                    >{t('search.line_code')}&nbsp;:
                                                </span>{item.code}</span
                                            >
                                        {/if}
                                        <span class="item-name"
                                            >{#each item.highlight as part, i (i)}{#if part.mark}<mark
                                                        >{part.text}</mark
                                                    >{:else}{part.text}{/if}{/each}</span
                                        >
                                    </Link>
                                    <p class="item-detail">
                                        {resultDetail(g.type, item, t, lang)}
                                    </p>
                                </li>
                            {/each}
                        </ol>
                        {#if !single && g.total > g.items.length}
                            <Link class="see-all" href={searchHref({ q: query, type: g.type })}>
                                {t(`search.see_all.${g.type}`, { count: fmt(g.total) })}
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </Link>
                        {/if}
                    </section>
                {:else}
                    {#if !positionList.length}
                        <EmptyState
                            icon="bi-search"
                            title={hasFilters
                                ? t('search.no_filtered')
                                : t('search.no_results', { query })}
                            text={hasFilters ? null : t('search.no_results_text')}
                        >
                            {#if hasFilters}
                                <button
                                    type="button"
                                    class="vff-btn secondary small"
                                    onclick={() => visit({ state: '', dept: '', page: 1 })}
                                    >{t('search.reset_filters')}</button
                                >
                            {/if}
                        </EmptyState>
                    {/if}
                {/each}
            {/if}
        </div>

        {#if single && single.last_page > 1}
            <Pagination
                current={single.current_page}
                last={single.last_page}
                label={t('search.pagination')}
                onpage={(page) => visit({ page })}
            />
        {:else}
            <div class="end"></div>
        {/if}
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .search {
        display: block;
        max-width: 40rem;
        margin-top: var(--vff-space-5);
    }
    .search form {
        display: flex;
        gap: var(--vff-space-2);
    }
    .search .vff-input {
        flex: 1;
        min-width: 0;
        min-height: 52px;
        font-size: 1.0625rem;
    }
    .search .vff-btn {
        flex-shrink: 0;
        min-height: 52px;
    }
    .results {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-space-5);
    }

    /* Onglets des types : pastilles arrondies sur une rangée, qui passent à la ligne. */
    .types ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .types :global(.tab) {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        text-decoration: none;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .types :global(.tab:hover) {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-accent-soft);
    }
    .types :global(.tab.current) {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-weight: 600;
    }
    .tab-count {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .types :global(.tab.current .tab-count) {
        color: inherit;
    }

    .filters {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-4);
    }
    .filters .vff-field {
        flex: 0 1 18rem;
    }
    .filters .reset {
        min-height: 44px;
    }

    .summary {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        max-width: var(--vff-measure);
    }
    .summary p {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .summary [role='status'] {
        color: var(--vff-text-muted);
    }
    .suggestion {
        font-size: var(--vff-fs-md);
    }
    .suggestion :global(a) {
        font-weight: 600;
        color: var(--vff-accent-ink);
        text-underline-offset: 3px;
    }
    .note {
        color: var(--vff-text-muted);
    }

    .group + .group {
        margin-top: var(--vff-space-4);
    }
    .group {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
    }
    .group-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .group-head h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: clamp(1.3rem, 1.2rem + 0.4vw, 1.5rem);
        line-height: var(--vff-lh-tight);
        color: var(--vff-text);
    }
    .group-count {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    /* Résultats : une liste à filets, comme un index de livret, pas des cartes. */
    .items {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .item {
        padding: 14px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .items :global(.item-link) {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 10px;
        max-width: 100%;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        line-height: 1.35;
        color: var(--vff-text);
        text-decoration: none;
        text-underline-offset: 3px;
        text-decoration-thickness: 2px;
    }
    .items :global(.item-link:hover) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
    }
    .item-name {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    /* Passage trouvé : teinte douce, sans gras (le nom reste lisible d'un trait). */
    mark {
        padding: 0 1px;
        border-radius: 3px;
        background-color: var(--vff-accent-soft);
        color: inherit;
    }
    .code {
        flex-shrink: 0;
        font-size: var(--vff-fs-xs);
    }
    .code.hit {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-bg-accent);
    }
    .item-detail {
        margin: 4px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .group :global(.see-all) {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        align-self: flex-start;
        min-height: 44px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-accent-ink);
        text-underline-offset: 3px;
    }
    .end {
        height: var(--vff-section-space);
    }

    @media (max-width: 600px) {
        .search form {
            flex-direction: column;
        }
        .search .vff-btn {
            width: 100%;
        }
        .filters .vff-field {
            flex: 1 1 100%;
        }
    }
</style>
