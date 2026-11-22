<script>
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import DirectoryTable from '$components/Directory/DirectoryTable.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { accidentDate, tollText } from '$lib/accidentText.js';
    import { decadeOf, formatNumber } from '$lib/typo.js';

    // `facets` est une prop « once » d'Inertia : une copie hors ligne peut ne pas l'avoir (menus vides).
    let {
        accidents,
        filters,
        facets = { decades: [], kinds: {}, departments: [], lines: [] },
    } = $props();

    // Les champs partent des filtres reçus puis évoluent avec la saisie : valeur initiale voulue.
    // svelte-ignore state_referenced_locally
    let q = $state(filters.q ?? '');
    // svelte-ignore state_referenced_locally
    let decade = $state(filters.decade ? String(filters.decade) : '');
    // svelte-ignore state_referenced_locally
    let kind = $state(filters.kind ?? '');
    // svelte-ignore state_referenced_locally
    let dept = $state(filters.dept ?? '');
    // svelte-ignore state_referenced_locally
    let line = $state(filters.line ?? '');
    // svelte-ignore state_referenced_locally
    let sort = $state(filters.sort ?? 'date');

    const KINDS = [
        'collision',
        'derailment',
        'level_crossing',
        'fire',
        'explosion',
        'structure_failure',
        'attack',
        'war_action',
        'runaway',
        'incident',
        'other',
    ];
    const SORTS = [
        ['date', 'sort_oldest'],
        ['-date', 'sort_newest'],
        ['-deaths', 'sort_toll'],
    ];
    const lang = $derived($i18n.language ?? 'fr');
    const hasFilters = $derived(!!(q || decade || kind || dept || line));
    const showReviewNote = $derived(accidents.data.some((a) => a.review_status === 'to_review'));

    function params(extra = {}) {
        const p = { q, decade, kind, dept, line, sort: sort === 'date' ? '' : sort, ...extra };
        Object.keys(p).forEach((k) => (p[k] === '' || p[k] == null) && delete p[k]);

        return p;
    }

    let debounceTimer;
    function applyFilters(now = false) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(
            () => {
                router.get('/accidents', params(), {
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
        q = decade = kind = dept = line = '';
        sort = 'date';
        router.get('/accidents', {}, { preserveState: true, replace: true });
    }

    function goToPage(page) {
        router.get('/accidents', params({ page }), { preserveScroll: false });
    }

    /** « Brétigny-sur-Orge (91) » ; rien si le lieu est inconnu. */
    function placeText(a) {
        if (!a.commune) return a.department_code ?? '';
        return a.department_code ? `${a.commune} (${a.department_code})` : a.commune;
    }
</script>

<svelte:head>
    <title>{$i18n.t('accidents.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('accidents.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('accidents.index.title')}
            subtitle={$i18n.t('accidents.index.subtitle')}
        >
            <p class="intro">{$i18n.t('accidents.index.intro')}</p>
        </PageHero>

        <div class="vff-container directory">
            <DirectoryFilters
                label={$i18n.t('accidents.index.filters_title')}
                results={$i18n.t('accidents.index.results', { count: accidents.total })}
                canReset={hasFilters}
                resetLabel={$i18n.t('accidents.index.clear')}
                onreset={clearFilters}
                onsubmit={() => applyFilters(true)}
            >
                <div class="vff-field wide">
                    <label class="vff-label" for="accidents-q"
                        >{$i18n.t('accidents.index.search_label')}</label
                    >
                    <input
                        id="accidents-q"
                        class="vff-input"
                        type="search"
                        name="q"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={$i18n.t('accidents.index.search_hint')}
                        bind:value={q}
                        oninput={() => applyFilters()}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="accidents-decade"
                        >{$i18n.t('accidents.index.filter_decade')}</label
                    >
                    <select
                        id="accidents-decade"
                        class="vff-select"
                        bind:value={decade}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('accidents.index.all_decades')}</option>
                        {#each facets.decades as d (d.decade)}
                            <option value={String(d.decade)}
                                >{$i18n.t('accidents.index.decade_option', {
                                    decade: decadeOf(d.decade, lang),
                                })} ({formatNumber(d.count, lang)})</option
                            >
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="accidents-kind"
                        >{$i18n.t('accidents.index.filter_kind')}</label
                    >
                    <select
                        id="accidents-kind"
                        class="vff-select"
                        bind:value={kind}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('accidents.index.all_kinds')}</option>
                        {#each KINDS as k (k)}
                            {#if facets.kinds[k] || kind === k}
                                <option value={k}
                                    >{$i18n.t(`accidents.kinds.${k}`)} ({formatNumber(
                                        facets.kinds[k],
                                        lang,
                                    )})</option
                                >
                            {/if}
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="accidents-dept"
                        >{$i18n.t('accidents.index.filter_department')}</label
                    >
                    <select
                        id="accidents-dept"
                        class="vff-select"
                        bind:value={dept}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('accidents.index.all_departments')}</option>
                        {#each facets.departments as d (d.code)}
                            <option value={d.code}>{d.code} {d.name} ({d.count})</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="accidents-line"
                        >{$i18n.t('accidents.index.filter_line')}</label
                    >
                    <select
                        id="accidents-line"
                        class="vff-select"
                        bind:value={line}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('accidents.index.all_lines')}</option>
                        {#each facets.lines as l (l.slug)}
                            <option value={l.slug}
                                >{l.code ? `${l.code} ` : ''}{l.name} ({l.count})</option
                            >
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="accidents-sort"
                        >{$i18n.t('accidents.index.sort_label')}</label
                    >
                    <select
                        id="accidents-sort"
                        class="vff-select"
                        bind:value={sort}
                        onchange={() => applyFilters(true)}
                    >
                        {#each SORTS as [value, key] (value)}
                            <option {value}>{$i18n.t(`accidents.index.${key}`)}</option>
                        {/each}
                    </select>
                </div>
            </DirectoryFilters>

            {#if accidents.data.length}
                <DirectoryTable
                    label={$i18n.t('accidents.index.table_label')}
                    caption={$i18n.t('accidents.index.table_caption')}
                >
                    <thead>
                        <tr>
                            <th scope="col">{$i18n.t('accidents.index.col_date')}</th>
                            <th scope="col">{$i18n.t('accidents.index.col_accident')}</th>
                            <th scope="col">{$i18n.t('accidents.index.col_kind')}</th>
                            <th scope="col">{$i18n.t('accidents.index.col_line')}</th>
                            <th scope="col">{$i18n.t('accidents.index.col_toll')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each accidents.data as a (a.slug)}
                            <tr>
                                <td class="date" data-label={$i18n.t('accidents.index.col_date')}>
                                    <time datetime={a.date}
                                        >{accidentDate(a.date, a.date_precision, lang)}</time
                                    >
                                </td>
                                <td class="name">
                                    <Link class="accident-link" href="/accidents/{a.slug}"
                                        >{a.name}</Link
                                    >
                                    {#if placeText(a)}<span class="where">{placeText(a)}</span>{/if}
                                    {#if a.review_status === 'to_review'}
                                        <span class="vff-badge warning review"
                                            >{$i18n.t('accidents.index.review_badge')}</span
                                        >
                                    {/if}
                                </td>
                                <td data-label={$i18n.t('accidents.index.col_kind')}>
                                    {$i18n.t(`accidents.kinds.${a.kind}`)}
                                </td>
                                <td class="line" data-label={$i18n.t('accidents.index.col_line')}>
                                    {#if a.line}
                                        <Link class="line-link" href="/lines/{a.line.slug}">
                                            {#if a.line.code}<span class="vff-plate"
                                                    >{a.line.code}</span
                                                >{/if}
                                            <span class="line-name">{a.line.name}</span>
                                        </Link>
                                    {:else}
                                        <span class="muted"
                                            >{$i18n.t('accidents.index.line_none')}</span
                                        >
                                    {/if}
                                </td>
                                <td class="toll" data-label={$i18n.t('accidents.index.col_toll')}>
                                    <span class={{ muted: a.deaths == null && a.injuries == null }}
                                        >{tollText(a.deaths, a.injuries, $i18n.t)}</span
                                    >
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </DirectoryTable>
                <section class="notes" aria-label={$i18n.t('accidents.index.notes_title')}>
                    <ul>
                        <li>{$i18n.t('accidents.index.note_toll')}</li>
                        {#if showReviewNote}<li>{$i18n.t('accidents.index.note_review')}</li>{/if}
                    </ul>
                </section>
            {:else}
                <EmptyState
                    icon="bi-search"
                    title={$i18n.t('accidents.index.no_results')}
                    text={$i18n.t('accidents.index.no_results_text')}
                >
                    {#if hasFilters}
                        <button type="button" class="vff-btn secondary small" onclick={clearFilters}
                            >{$i18n.t('accidents.index.clear')}</button
                        >
                    {/if}
                </EmptyState>
            {/if}
        </div>

        {#if accidents.last_page > 1}
            <Pagination
                current={accidents.current_page}
                last={accidents.last_page}
                onpage={goToPage}
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
    .intro {
        max-width: var(--vff-measure);
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
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
    .date {
        white-space: nowrap;
        font-family: var(--vff-font-heading);
        font-variant-numeric: tabular-nums;
    }
    .name {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .name :global(.accident-link) {
        /* Zone cliquable de 24 px au moins sans changer l'espacement des lignes. */
        display: inline-block;
        padding-block: 4px;
        margin-block: -4px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        line-height: 1.3;
        color: var(--vff-text);
        text-decoration: none;
        text-underline-offset: 3px;
        text-decoration-thickness: 2px;
        hyphens: none;
    }
    .name :global(.accident-link:hover) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
    }
    .where {
        display: block;
        margin-top: 2px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .review {
        margin-top: 6px;
    }
    .line {
        min-width: 200px;
    }
    .line :global(.line-link) {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 8px;
        padding-block: 3px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text);
        text-decoration: none;
    }
    .line :global(.line-link:hover .line-name) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
    }
    .line-name {
        max-width: 32ch;
    }
    .toll {
        font-variant-numeric: tabular-nums;
    }
    .muted {
        color: var(--vff-text-muted);
    }
    .notes {
        max-width: var(--vff-measure);
        margin-top: calc(-1 * var(--vff-space-3));
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .notes ul {
        margin: 0;
        padding-left: 1.2em;
    }
    @media (max-width: 600px) {
        .line {
            min-width: 0;
        }
        .date {
            white-space: normal;
        }
    }
</style>
