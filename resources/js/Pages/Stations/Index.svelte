<script>
    import { router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import { STATION_STATUS_COLOR } from '$components/Stations/stationStatus.js';
    import { i18n } from '$lib/i18n.js';

    let { stations, filters, counts = {}, eras = [], departments = [] } = $props();

    let q = $state(filters.q ?? '');
    let status = $state(filters.status ?? '');
    let era = $state(filters.era ? String(filters.era) : '');
    let dept = $state(filters.dept ?? '');
    let flagged = $state(!!filters.flagged);

    const STATUSES = ['active', 'tourist', 'no_commercial', 'declassified', 'gone', 'planned'];
    const total = $derived(Object.values(counts).reduce((a, b) => a + b, 0));
    const hasFilters = $derived(!!(q || status || era || dept || flagged));
    const fmt = (n) => (n ?? 0).toLocaleString($i18n.language ?? 'fr');

    function params(extra = {}) {
        const p = { q, status, era, dept, flagged: flagged ? 1 : '', ...extra };
        Object.keys(p).forEach(
            (k) => (p[k] === '' || p[k] === null || p[k] === undefined) && delete p[k],
        );

        return p;
    }

    let debounceTimer;
    function applyFilters() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            router.get('/stations', params(), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 250);
    }

    function pickStatus(s) {
        status = status === s ? '' : s;
        applyFilters();
    }

    function clearFilters() {
        q = status = era = dept = '';
        flagged = false;
        router.get('/stations', {}, { preserveState: true, replace: true });
    }

    function goToPage(page) {
        router.get('/stations', params({ page }), { preserveScroll: false });
    }
</script>

<svelte:head>
    <title>{$i18n.t('stations.list_title')} - VFF</title>
    <meta name="description" content={$i18n.t('stations.meta_list')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('stations.list_eyebrow')}
            title={$i18n.t('stations.list_title')}
            subtitle={$i18n.t('stations.list_subtitle', { total: fmt(total) })}
        />

        <section class="filters">
            <div class="filter-row">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        placeholder={$i18n.t('stations.search_placeholder')}
                        aria-label={$i18n.t('stations.search_placeholder')}
                        bind:value={q}
                        oninput={applyFilters}
                    />
                </div>
                <select
                    bind:value={era}
                    onchange={applyFilters}
                    aria-label={$i18n.t('stations.all_eras')}
                >
                    <option value="">{$i18n.t('stations.all_eras')}</option>
                    {#each eras as y (y)}
                        <option value={String(y)}
                            >{$i18n.t('stations.era_option', { year: y })}</option
                        >
                    {/each}
                </select>
                <select
                    bind:value={dept}
                    onchange={applyFilters}
                    aria-label={$i18n.t('stations.all_departments')}
                >
                    <option value="">{$i18n.t('stations.all_departments')}</option>
                    {#each departments as d (d)}
                        <option value={d}>{d}</option>
                    {/each}
                </select>
                <label class="checkbox">
                    <input type="checkbox" bind:checked={flagged} onchange={applyFilters} />
                    <span>{$i18n.t('stations.flagged_only')}</span>
                </label>
                {#if hasFilters}
                    <button class="vff-btn ghost" onclick={clearFilters}>
                        <i class="bi bi-x-circle"></i>
                        {$i18n.t('stations.clear')}
                    </button>
                {/if}
            </div>
            <div class="chips" role="group" aria-label={$i18n.t('stations.all_statuses')}>
                {#each STATUSES as s (s)}
                    {#if counts[s] || status === s}
                        <button
                            type="button"
                            class="chip"
                            class:active={status === s}
                            aria-pressed={status === s}
                            style="--dot: {STATION_STATUS_COLOR[s]}"
                            onclick={() => pickStatus(s)}
                        >
                            <i class="dot"></i>
                            {$i18n.t(`stations.statuses.${s}`)} <span>{fmt(counts[s])}</span>
                        </button>
                    {/if}
                {/each}
            </div>
        </section>

        <section class="list">
            {#if stations.data.length}
                <div class="grid">
                    {#each stations.data as s (s.slug)}
                        <a class="card" href="/stations/{s.slug}">
                            <i
                                class="dot big"
                                style="--dot: {STATION_STATUS_COLOR[s.status] ??
                                    STATION_STATUS_COLOR.declassified}"
                            ></i>
                            <div class="info">
                                <strong>{s.name}</strong>
                                <div class="meta">
                                    {#if s.city}<span
                                            >{s.city}{s.department_code
                                                ? ` (${s.department_code})`
                                                : ''}</span
                                        >{/if}
                                    <span>{$i18n.t(`stations.types.${s.type}`)}</span>
                                </div>
                                <div class="meta">
                                    <span class="status"
                                        >{$i18n.t(`stations.statuses.${s.status}`)}</span
                                    >
                                    {#if s.opened_year}
                                        <small>
                                            {$i18n.t('stations.opened_in', {
                                                year: s.opened_year,
                                            })}{#if s.opened_source?.startsWith('inferred')}&nbsp;<abbr
                                                    title={$i18n.t('stations.events.inferred_hint')}
                                                    >({$i18n.t('stations.inferred_short')})</abbr
                                                >{/if}
                                        </small>
                                    {/if}
                                    {#if s.closed_year}
                                        <small
                                            >{$i18n.t('stations.closed_in', {
                                                year: s.closed_year,
                                            })}</small
                                        >
                                    {/if}
                                    {#if s.position_flagged}
                                        <i
                                            class="bi bi-exclamation-triangle flag"
                                            title={$i18n.t('stations.flagged_only')}
                                            aria-label={$i18n.t('stations.flagged_only')}
                                            role="img"
                                        ></i>
                                    {/if}
                                </div>
                            </div>
                        </a>
                    {/each}
                </div>
            {:else}
                <p class="empty">{$i18n.t('stations.no_results')}</p>
            {/if}
        </section>

        {#if stations.last_page > 1}
            <Pagination
                current={stations.current_page}
                last={stations.last_page}
                onpage={goToPage}
                label={$i18n.t('stations.pagination')}
                prev={$i18n.t('stations.previous')}
                next={$i18n.t('stations.next')}
                status={$i18n.t('stations.page', {
                    current: stations.current_page,
                    last: stations.last_page,
                })}
            />
        {/if}
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }

    .filters {
        max-width: 1100px;
        margin: -30px auto 0;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg);
        box-shadow: 0 12px 32px var(--vff-shadow);
        padding: 20px 24px;
        position: relative;
        z-index: 2;
    }
    .filter-row {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    .search-input {
        flex: 1;
        min-width: 220px;
        position: relative;
    }
    .search-input i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--vff-text-muted);
        font-size: 16px;
    }
    .search-input input,
    .filters select {
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-body);
        font-size: 14px;
    }
    .search-input input {
        width: 100%;
        padding-left: 36px;
    }
    .search-input input:focus,
    .filters select:focus {
        outline: none;
        border-color: var(--vff-accent);
    }
    .checkbox {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        cursor: pointer;
        color: var(--vff-text);
    }

    .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }
    .chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-display);
        font-size: 12px;
        cursor: pointer;
        transition: all var(--vff-transition);
    }
    .chip span {
        color: var(--vff-text-muted);
        font-size: 11px;
    }
    .chip:hover,
    .chip.active {
        border-color: var(--vff-accent);
        color: var(--vff-accent);
    }
    .chip.active {
        background-color: color-mix(in srgb, var(--vff-accent) 10%, transparent);
    }

    .dot {
        display: inline-block;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background-color: var(--vff-bg-elevated);
        border: 2px solid var(--dot);
        flex-shrink: 0;
    }
    .dot.big {
        width: 14px;
        height: 14px;
        border-width: 3px;
    }

    .list {
        max-width: 1100px;
        margin: 0 auto;
        padding: 30px 24px;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr));
        gap: 10px 14px;
    }
    /* Une entrée : le point de la gare (couleur de son statut, comme sur la carte), le nom en
       entier, la commune et le type, puis le statut en clair et les dates. */
    .card {
        display: grid;
        grid-template-columns: 18px minmax(0, 1fr);
        align-items: start;
        gap: 12px;
        padding: 14px 16px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
        transition: border-color var(--vff-transition);
    }
    .card:hover {
        border-color: var(--vff-accent);
    }
    .card:hover strong {
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
        text-underline-offset: 3px;
    }
    .card:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .card .dot.big {
        margin-top: 3px;
    }
    .info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .info strong {
        font-family: var(--vff-font-display);
        font-size: 16px;
        line-height: 1.3;
    }
    .meta {
        display: flex;
        gap: 4px 12px;
        align-items: center;
        flex-wrap: wrap;
        font-family: var(--vff-font-heading);
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .meta small {
        font-size: 13px;
    }
    .status {
        color: var(--vff-text);
        font-weight: 600;
    }
    abbr {
        text-decoration: none;
        cursor: help;
    }
    .flag {
        color: #b45309;
    }

    .empty {
        text-align: center;
        font-style: italic;
        color: var(--vff-text-muted);
        padding: 40px;
    }

    @media (max-width: 600px) {
        .filters {
            margin: -30px 16px 0;
            padding: 16px;
        }
        .list {
            padding: 24px 16px;
        }
        .grid {
            grid-template-columns: 1fr;
        }
    }
    /* Case à cocher à 18 px (cible tactile), couleur de la charte. */
    .checkbox input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--vff-accent);
    }
</style>
