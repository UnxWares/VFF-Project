<script>
    import { router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import { i18n } from '$lib/i18n.js';
    import { STATUS_COLOR, TRAFFIC_COLOR } from '$lib/railStyle.js';

    let { lines, filters, stats } = $props();

    const NETWORKS = ['principal', 'secondaire', 'militaire', 'particulier', 'none'];
    const TRAFFICS = ['passenger', 'mixed', 'freight', 'unknown'];

    let q = $state(filters.q ?? '');
    let network = $state(filters.network ?? '');
    let traffic = $state(filters.traffic ?? '');
    let status = $state(filters.status ?? '');
    let heritage = $state(!!filters.heritage);

    const hasFilters = $derived(!!(q || network || traffic || status || heritage));
    const fmt = (n) => Number(n ?? 0).toLocaleString($i18n.language ?? 'fr');

    function currentParams() {
        const params = {};
        if (q) params.q = q;
        if (network) params.network = network;
        if (traffic) params.traffic = traffic;
        if (status) params.status = status;
        if (heritage) params.heritage = 1;
        return params;
    }

    let debounceTimer;
    function applyFilters() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            router.get('/lines', currentParams(), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 250);
    }

    function clearFilters() {
        q = '';
        network = '';
        traffic = '';
        status = '';
        heritage = false;
        router.get('/lines', {}, { preserveState: true, replace: true });
    }

    function goToPage(p) {
        router.get('/lines', { ...currentParams(), page: p }, { preserveScroll: false });
    }

    function pickNetwork(n) {
        network = network === n ? '' : n;
        applyFilters();
    }
</script>

<svelte:head>
    <title>{$i18n.t('lines.index.meta_title')} - VFF</title>
    <meta
        name="description"
        content={$i18n.t('lines.index.meta_description', { total: stats.total })}
    />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            eyebrow={$i18n.t('lines.index.eyebrow')}
            title={$i18n.t('lines.index.title')}
            subtitle={$i18n.t('lines.index.subtitle', {
                total: fmt(stats.total),
                open: fmt(stats.open),
                closed: fmt(stats.closed),
            })}
        />

        <section class="filters">
            <div class="filter-row">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input
                        type="text"
                        placeholder={$i18n.t('lines.index.search_placeholder')}
                        aria-label={$i18n.t('lines.index.search_placeholder')}
                        bind:value={q}
                        oninput={applyFilters}
                    />
                </div>
                <select
                    bind:value={network}
                    onchange={applyFilters}
                    aria-label={$i18n.t('lines.index.filter_network')}
                >
                    <option value="">{$i18n.t('lines.index.all_networks')}</option>
                    {#each NETWORKS as n (n)}
                        <option value={n}
                            >{$i18n.t(`lines.networks.${n}`)} ({fmt(stats.networks?.[n])})</option
                        >
                    {/each}
                </select>
                <select
                    bind:value={traffic}
                    onchange={applyFilters}
                    aria-label={$i18n.t('lines.index.filter_traffic')}
                >
                    <option value="">{$i18n.t('lines.index.all_traffics')}</option>
                    {#each TRAFFICS as t (t)}
                        <option value={t}
                            >{$i18n.t(`lines.traffics.${t}`)} ({fmt(stats.traffics?.[t])})</option
                        >
                    {/each}
                </select>
                <select
                    bind:value={status}
                    onchange={applyFilters}
                    aria-label={$i18n.t('lines.index.filter_state')}
                >
                    <option value="">{$i18n.t('lines.index.all_states')}</option>
                    <option value="open">{$i18n.t('lines.states.open')}</option>
                    <option value="closed">{$i18n.t('lines.states.closed')}</option>
                </select>
                <label class="checkbox">
                    <input type="checkbox" bind:checked={heritage} onchange={applyFilters} />
                    <span>{$i18n.t('lines.index.heritage')} ({fmt(stats.heritage)})</span>
                </label>
                {#if hasFilters}
                    <button class="vff-btn ghost" onclick={clearFilters}>
                        <i class="bi bi-x-circle"></i>
                        {$i18n.t('lines.index.reset')}
                    </button>
                {/if}
            </div>
            <div
                class="network-chips"
                role="group"
                aria-label={$i18n.t('lines.index.filter_network')}
            >
                {#each NETWORKS as n (n)}
                    {#if stats.networks?.[n] || network === n}
                        <button
                            type="button"
                            class="chip"
                            class:active={network === n}
                            aria-pressed={network === n}
                            onclick={() => pickNetwork(n)}
                        >
                            {$i18n.t(`lines.networks.${n}`)} <span>{fmt(stats.networks?.[n])}</span>
                        </button>
                    {/if}
                {/each}
            </div>
            <p class="result-count">
                {$i18n.t('lines.index.results', { count: fmt(lines.total) })}
            </p>
        </section>

        <section class="lines-list">
            {#if lines.data.length}
                <div class="grid">
                    {#each lines.data as l (l.slug)}
                        <a class="line-card" href="/lines/{l.slug}">
                            <span class="line-info">
                                <strong>{l.name}</strong>
                                <span class="line-meta">
                                    {#if l.code}<span class="code">{l.code}</span>{/if}
                                    {#if l.network}<span
                                            >{$i18n.t(`lines.networks.${l.network}`)}</span
                                        >{/if}
                                    {#if l.state === 'open' && l.traffic}
                                        <span class="traffic"
                                            ><i
                                                style="--c: {TRAFFIC_COLOR[l.traffic] ??
                                                    TRAFFIC_COLOR.unknown}"
                                            ></i>{$i18n.t(`lines.traffics.${l.traffic}`)}</span
                                        >
                                    {:else if l.state === 'closed'}
                                        <span class="traffic closed"
                                            ><i style="--c: {STATUS_COLOR.abandoned}"></i>{$i18n.t(
                                                'lines.states.closed',
                                            )}</span
                                        >
                                    {/if}
                                </span>
                                {#if l.historical_operator}
                                    <small class="operator">{l.historical_operator}</small>
                                {/if}
                            </span>
                        </a>
                    {/each}
                </div>
            {:else}
                <p class="empty">{$i18n.t('lines.index.empty')}</p>
            {/if}
        </section>

        {#if lines.last_page > 1}
            <Pagination
                current={lines.current_page}
                last={lines.last_page}
                onpage={goToPage}
                label={$i18n.t('lines.index.pagination')}
                prev={$i18n.t('lines.index.prev')}
                next={$i18n.t('lines.index.next')}
                status={$i18n.t('lines.index.page', {
                    current: lines.current_page,
                    last: lines.last_page,
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
        min-width: 240px;
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
    .search-input input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px 10px 36px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-body);
        font-size: 14px;
    }
    .search-input input:focus {
        outline: none;
        border-color: var(--vff-accent);
    }
    .filters select {
        padding: 10px 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-body);
        font-size: 14px;
        cursor: pointer;
    }
    .checkbox {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        cursor: pointer;
        color: var(--vff-text);
    }
    .result-count {
        margin: 12px 0 0;
        font-size: 13px;
        color: var(--vff-text-muted);
    }

    .lines-list {
        max-width: 1100px;
        margin: 0 auto;
        padding: 30px 24px;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(340px, 100%), 1fr));
        gap: 10px 14px;
    }
    /* Une entrée de l'annuaire : la plaque (code de la ligne), le nom en entier, puis le réseau
       et le trafic d'aujourd'hui (pastille de la couleur de la carte, texte en encre). */
    .line-card {
        display: block;
        padding: 14px 16px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
        transition: border-color var(--vff-transition);
    }
    .line-card:hover {
        border-color: var(--vff-accent);
    }
    .line-card:hover strong {
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
        text-underline-offset: 3px;
    }
    .line-card:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .code {
        padding: 0 6px;
        border: 1.5px solid var(--vff-text);
        border-radius: 4px;
        font-family: var(--vff-font-heading);
        font-size: 12.5px;
        font-weight: 600;
        letter-spacing: 0.03em;
        font-variant-numeric: tabular-nums;
        line-height: 1.45;
        color: var(--vff-text);
    }
    .line-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .line-info strong {
        font-family: var(--vff-font-display);
        font-size: 16px;
        line-height: 1.3;
    }
    .line-meta {
        display: flex;
        gap: 4px 14px;
        align-items: center;
        flex-wrap: wrap;
        font-family: var(--vff-font-heading);
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .traffic {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--vff-text);
    }
    .traffic i {
        width: 14px;
        height: 4px;
        border-radius: 2px;
        background: var(--c);
    }
    .traffic.closed i {
        background: repeating-linear-gradient(90deg, var(--c) 0 4px, transparent 4px 6px);
    }
    .network-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
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

    .operator {
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .empty {
        text-align: center;
        font-style: italic;
        color: var(--vff-text-muted);
        padding: 40px;
    }
    /* Case à cocher à 18 px (cible tactile), couleur de la charte. */
    .checkbox input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--vff-accent);
    }
</style>
