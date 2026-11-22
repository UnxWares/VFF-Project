<script>
    import { router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import DirectoryTable from '$components/Directory/DirectoryTable.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { STATION_STATUS_COLOR } from '$components/Stations/stationStatus.js';
    import { i18n } from '$lib/i18n.js';
    import { decadeOf } from '$lib/typo.js';
    import { directoryOpening } from '$lib/stationDates.js';

    let { stations, filters, counts = {}, eras = [], departments = [], overall = 0 } = $props();

    // Les champs partent des filtres reçus puis évoluent avec la saisie : valeur initiale voulue.
    // svelte-ignore state_referenced_locally
    let q = $state(filters.q ?? '');
    // svelte-ignore state_referenced_locally
    let status = $state(filters.status ?? '');
    // svelte-ignore state_referenced_locally
    let era = $state(filters.era ? String(filters.era) : '');
    // svelte-ignore state_referenced_locally
    let dept = $state(filters.dept ?? '');
    // svelte-ignore state_referenced_locally
    let flagged = $state(!!filters.flagged);
    // svelte-ignore state_referenced_locally
    let sort = $state(filters.sort ?? 'name');

    const STATUSES = ['active', 'tourist', 'no_commercial', 'declassified', 'gone', 'planned'];
    const SORTS = [
        ['name', 'sort_name'],
        ['name-desc', 'sort_name_desc'],
        ['city', 'sort_city'],
        ['opened', 'sort_opened'],
        ['closed', 'sort_closed'],
    ];
    const hasFilters = $derived(!!(q || status || era || dept || flagged));
    const fmt = (n) => (n ?? 0).toLocaleString($i18n.language ?? 'fr');
    const showInferredNote = $derived(
        stations.data.some(
            (s) =>
                s.opened_source?.startsWith('inferred') || s.closed_source?.startsWith('inferred'),
        ),
    );
    const showUndatedNote = $derived(stations.data.some((s) => s.closed_source === 'vff:eras'));
    const showStatusNote = $derived(
        stations.data.some((s) => s.status === 'declassified' || s.status === 'gone'),
    );

    function params(extra = {}) {
        const p = {
            q,
            status,
            era,
            dept,
            flagged: flagged ? 1 : '',
            sort: sort === 'name' ? '' : sort,
            ...extra,
        };
        Object.keys(p).forEach(
            (k) => (p[k] === '' || p[k] === null || p[k] === undefined) && delete p[k],
        );

        return p;
    }

    let debounceTimer;
    function applyFilters(now = false) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(
            () => {
                router.get('/stations', params(), {
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
        q = status = era = dept = '';
        flagged = false;
        sort = 'name';
        router.get('/stations', {}, { preserveState: true, replace: true });
    }

    function goToPage(page) {
        router.get('/stations', params({ page }), { preserveScroll: false });
    }

    /** Commune (département) puis type : « Vire Normandie (14), Gare ». */
    function whereText(s) {
        const place = s.city
            ? `${s.city}${s.department_code ? ` (${s.department_code})` : ''}`
            : '';
        const type = $i18n.t(`stations.types.${s.type}`);
        return place ? `${place}, ${type}` : type;
    }

    /** Ouverture de l'annuaire : « entre A et B » pour un intervalle, sinon l'année. */
    const openedText = (s) =>
        directoryOpening(s, (key, opts) => String($i18n.t(key, opts)), $i18n.language ?? 'fr');

    /** Fermeture : date, « non datée » (seulement encadrée par l'état des lignes) ou rien. */
    function closedText(s) {
        if (s.closed_source === 'vff:eras') {
            const base = $i18n.t('stations.closed_undated');
            return s.closed_after
                ? `${base} (${$i18n.t('stations.closed_after', { year: s.closed_after })})`
                : base;
        }
        return s.closed_year ? String(s.closed_year) : '';
    }
</script>

<svelte:head>
    <title>{$i18n.t('stations.list_title')} - VFF</title>
    <meta name="description" content={$i18n.t('stations.meta_list')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('stations.list_title')}
            subtitle={$i18n.t('stations.list_subtitle', { total: fmt(overall) })}
        />

        <div class="vff-container directory">
            <DirectoryFilters
                label={$i18n.t('stations.filters_title')}
                results={$i18n.t('stations.results', { count: fmt(stations.total) })}
                canReset={hasFilters}
                resetLabel={$i18n.t('stations.clear')}
                onreset={clearFilters}
                onsubmit={() => applyFilters(true)}
            >
                <div class="vff-field wide">
                    <label class="vff-label" for="stations-q"
                        >{$i18n.t('stations.search_label')}</label
                    >
                    <input
                        id="stations-q"
                        class="vff-input"
                        type="search"
                        name="q"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={$i18n.t('stations.search_hint')}
                        bind:value={q}
                        oninput={() => applyFilters()}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="stations-status"
                        >{$i18n.t('stations.filter_status')}</label
                    >
                    <select
                        id="stations-status"
                        class="vff-select"
                        bind:value={status}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('stations.all_statuses')}</option>
                        {#each STATUSES as s (s)}
                            {#if counts[s] || status === s}
                                <option value={s}
                                    >{$i18n.t(`stations.statuses.${s}`)} ({fmt(counts[s])})</option
                                >
                            {/if}
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="stations-era"
                        >{$i18n.t('stations.filter_era')}</label
                    >
                    <select
                        id="stations-era"
                        class="vff-select"
                        bind:value={era}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('stations.all_eras')}</option>
                        {#each eras as y (y)}
                            <option value={String(y)}
                                >{$i18n.t('stations.era_option', {
                                    decade: decadeOf(y, $i18n.language),
                                })}</option
                            >
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="stations-dept"
                        >{$i18n.t('stations.filter_department')}</label
                    >
                    <select
                        id="stations-dept"
                        class="vff-select"
                        bind:value={dept}
                        onchange={() => applyFilters(true)}
                    >
                        <option value="">{$i18n.t('stations.all_departments')}</option>
                        {#each departments as d (d)}
                            <option value={d}>{d}</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="stations-sort"
                        >{$i18n.t('stations.sort_label')}</label
                    >
                    <select
                        id="stations-sort"
                        class="vff-select"
                        bind:value={sort}
                        onchange={() => applyFilters(true)}
                    >
                        {#each SORTS as [value, key] (value)}
                            <option {value}>{$i18n.t(`stations.${key}`)}</option>
                        {/each}
                    </select>
                </div>
                <label class="vff-check wide">
                    <input
                        type="checkbox"
                        bind:checked={flagged}
                        onchange={() => applyFilters(true)}
                    />
                    {$i18n.t('stations.flagged_only')}
                </label>
            </DirectoryFilters>

            {#if stations.data.length}
                <DirectoryTable
                    label={$i18n.t('stations.table_label')}
                    caption={$i18n.t('stations.table_caption')}
                >
                    <thead>
                        <tr>
                            <th scope="col">{$i18n.t('stations.col_station')}</th>
                            <th scope="col">{$i18n.t('stations.col_lines')}</th>
                            <th scope="col">{$i18n.t('stations.col_status')}</th>
                            <th scope="col" class="num">{$i18n.t('stations.col_opened')}</th>
                            <th scope="col" class="num">{$i18n.t('stations.col_closed')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each stations.data as s (s.slug)}
                            {@const closed = closedText(s)}
                            <tr>
                                <td class="name">
                                    <a class="station-link" href="/stations/{s.slug}">{s.name}</a>
                                    <span class="where">{whereText(s)}</span>
                                    {#if s.position_flagged}
                                        <span class="vff-badge warning flag">
                                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                            {$i18n.t('stations.position_flag')}
                                        </span>
                                    {/if}
                                </td>
                                <td
                                    class={['lines', { blank: !s.lines.items.length }]}
                                    data-label={$i18n.t('stations.col_lines')}
                                >
                                    {#each s.lines.items as l (l.slug)}
                                        {#if l.code}
                                            <a class="line-plate vff-plate" href="/lines/{l.slug}"
                                                >{l.code}<span class="vff-sr-only">
                                                    : {l.name}</span
                                                ></a
                                            >
                                        {:else}
                                            <a class="line-name" href="/lines/{l.slug}">{l.name}</a>
                                        {/if}
                                    {/each}
                                    {#if s.lines.more}
                                        <span class="more"
                                            >{$i18n.t('stations.more_lines', {
                                                count: s.lines.more,
                                            })}</span
                                        >
                                    {/if}
                                </td>
                                <td data-label={$i18n.t('stations.col_status')}>
                                    <span class="status">
                                        <i
                                            class={['dot', { gone: s.status === 'gone' }]}
                                            style:--dot={STATION_STATUS_COLOR[s.status] ??
                                                STATION_STATUS_COLOR.declassified}
                                            aria-hidden="true"
                                        ></i>{$i18n.t(`stations.statuses.${s.status}`)}
                                    </span>
                                </td>
                                <td
                                    class={['num', 'opened', { blank: !s.opened_year }]}
                                    data-label={$i18n.t('stations.col_opened')}
                                >
                                    {#if s.opened_year}
                                        {openedText(
                                            s,
                                        )}{#if s.opened_source?.startsWith('inferred')}<span
                                                class="inferred"
                                                >({$i18n.t('stations.date_inferred')})</span
                                            >{/if}
                                    {:else}
                                        <span aria-hidden="true">-</span><span class="vff-sr-only"
                                            >{$i18n.t('stations.date_none')}</span
                                        >
                                    {/if}
                                </td>
                                <td
                                    class={['num', 'closed', { blank: !closed }]}
                                    data-label={$i18n.t('stations.col_closed')}
                                >
                                    {#if closed}
                                        <span class={{ undated: s.closed_source === 'vff:eras' }}
                                            >{closed}</span
                                        >{#if s.closed_source?.startsWith('inferred')}<span
                                                class="inferred"
                                                >({$i18n.t('stations.date_inferred')})</span
                                            >{/if}
                                    {:else}
                                        <span aria-hidden="true">-</span><span class="vff-sr-only"
                                            >{$i18n.t('stations.date_none')}</span
                                        >
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </DirectoryTable>
                {#if showInferredNote || showUndatedNote || showStatusNote}
                    <section class="notes" aria-label={$i18n.t('stations.notes_title')}>
                        <ul>
                            {#if showInferredNote}<li>{$i18n.t('stations.note_inferred')}</li>{/if}
                            {#if showUndatedNote}<li>{$i18n.t('stations.note_undated')}</li>{/if}
                            {#if showStatusNote}<li>{$i18n.t('stations.note_statuses')}</li>{/if}
                        </ul>
                    </section>
                {/if}
            {:else}
                <EmptyState
                    icon="bi-search"
                    title={$i18n.t('stations.no_results')}
                    text={$i18n.t('stations.no_results_text')}
                />
            {/if}
        </div>

        {#if stations.last_page > 1}
            <Pagination
                current={stations.current_page}
                last={stations.last_page}
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
    .name {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .station-link {
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
        hyphens: none;
    }
    .station-link:hover {
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
    .flag {
        margin-top: 6px;
    }
    .lines {
        min-width: 200px;
    }
    .lines :global(a),
    .more {
        margin: 0 6px 6px 0;
    }
    .line-plate {
        text-decoration: none;
        font-size: var(--vff-fs-xs);
    }
    .line-plate:hover {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-bg-accent);
    }
    /* Nom d'une ligne sans code : en entier, sur deux lignes au besoin (coupé par « … » avant, le
       nom n'était plus lisible : « Lignes minières du Bassin minier du Nord (secteur… »). */
    .line-name {
        display: block;
        max-width: 18.9em;
        font-size: var(--vff-fs-xs);
        line-height: 1.35;
        color: var(--vff-text);
        text-decoration: none;
        padding-block: 3px;
    }
    .line-name:hover {
        color: var(--vff-accent-ink);
        text-decoration: underline;
    }
    .more {
        display: inline-block;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }

    /* Anneau de la couleur du statut sur la carte ; la gare « disparue » a un anneau en pointillés,
       la différence avec « fermée » ne repose pas sur la couleur seule (le texte dit le statut). */
    .dot {
        display: inline-block;
        flex-shrink: 0;
        width: 12px;
        height: 12px;
        border: 3px solid var(--dot);
        border-radius: 50%;
        background-color: var(--vff-bg);
        box-sizing: border-box;
        margin-right: 8px;
        vertical-align: middle;
    }
    .dot.gone {
        border-style: dotted;
        border-color: var(--vff-text-muted);
    }
    td.opened,
    td.closed {
        white-space: normal;
    }
    .inferred {
        margin-left: 4px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .undated {
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
        .lines {
            min-width: 0;
        }
        .line-name {
            max-width: none;
        }
    }
</style>
