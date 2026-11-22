<script>
    import { Link, router } from '@inertiajs/svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        buildersText,
        matchedDesignation,
        servicePeriodText,
        splitArrows,
    } from '$lib/materialFacts.js';

    let { materials, counts = [], filters = {}, sorts = [], overall = 0 } = $props();

    // Clé de traduction de chaque tri (les valeurs d'adresse commencent parfois par « - »).
    const SORT_KEYS = {
        name: 'name',
        in_service_from: 'oldest',
        '-in_service_from': 'newest',
        '-max_speed_kmh': 'fastest',
        '-units_built': 'most_built',
        '-lines_count': 'most_lines',
    };
    const DEFAULT_SORT = 'name';

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const fmt = (n) => Number(n ?? 0).toLocaleString(lang);

    // Les champs partent des filtres reçus puis évoluent avec la saisie : valeur initiale voulue.
    // svelte-ignore state_referenced_locally
    let q = $state(filters.q ?? '');
    // svelte-ignore state_referenced_locally
    let sort = $state(filters.sort ?? DEFAULT_SORT);
    const type = $derived(filters.type ?? '');
    const hasFilters = $derived(!!(q || type || sort !== DEFAULT_SORT));
    const countedAll = $derived(counts.reduce((n, c) => n + c.count, 0));

    /** Paramètres de l'adresse, sans les valeurs par défaut. */
    function params(extra = {}) {
        const p = { q, type, sort: sort === DEFAULT_SORT ? '' : sort, ...extra };
        return Object.fromEntries(Object.entries(p).filter(([, v]) => v !== '' && v != null));
    }
    const typeHref = (value) => {
        const query = new URLSearchParams(params({ type: value })).toString();
        return query ? `/materials?${query}` : '/materials';
    };

    let debounceTimer;
    function applyFilters(now = false) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(
            () =>
                router.get('/materials', params(), {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            now ? 0 : 250,
        );
    }

    function reset() {
        clearTimeout(debounceTimer);
        q = '';
        sort = DEFAULT_SORT;
        router.get('/materials', {}, { preserveState: true, replace: true });
    }

    function goToPage(page) {
        router.get('/materials', params({ page }), { preserveScroll: false });
    }

    /**
     * Téléphone : la rangée des catégories défile ; la catégorie choisie y est ramenée en vue
     * (sans bouger la page). Relancé quand la catégorie change.
     */
    function revealCurrent(list) {
        void type;
        const current = list.querySelector('[aria-current="page"]');
        if (current && list.scrollWidth > list.clientWidth) {
            list.scrollLeft = current.offsetLeft - list.offsetLeft - 16;
        }
    }

    /** Catégorie et période : « Autorail · 1950-1988 » (le constructeur a sa propre ligne). */
    function metaOf(m) {
        return [
            t(`materials.type.${m.type}`),
            servicePeriodText(m.in_service_from, m.in_service_to, t),
            // Seulement quand une source le dit : une date de retrait absente ne prouve rien.
            m.service_status === 'in_service' && t('materials.service_state.in_service'),
        ].filter(Boolean);
    }

    /** Chiffres disponibles : engins construits, vitesse, lignes parcourues. */
    function figuresOf(m) {
        return [
            m.units_built != null && t('materials.row.units', { count: m.units_built }),
            m.max_speed_kmh != null && t('materials.unit.kmh', { value: fmt(m.max_speed_kmh) }),
            m.lines_count > 0 && t('materials.row.lines', { count: m.lines_count }),
        ].filter(Boolean);
    }
</script>

{#snippet arrowed(text)}{#each splitArrows(text) as part, i (i)}{#if i > 0}<span aria-hidden="true"
                >&nbsp;→&nbsp;</span
            ><span class="vff-sr-only">{` ${t('materials.renumbered')} `}</span
            >{/if}{part}{/each}{/snippet}

<svelte:head>
    <title>{t('materials.meta_title')} - VFF</title>
    <meta name="description" content={t('materials.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('materials.title')}
            subtitle={overall > 0
                ? t('materials.subtitle', { total: fmt(overall) })
                : t('materials.subtitle_empty')}
        >
            {#if overall > 0}
                <p class="quiz-link">
                    <Link href="/quiz?game=materials" class="vff-link"
                        >{t('quiz.links.materials')}</Link
                    >
                </p>
            {/if}
        </PageHero>

        <div class="vff-container directory">
            {#if overall === 0}
                <EmptyState
                    icon="bi-train-front"
                    headingLevel={2}
                    title={t('materials.empty_title')}
                    text={t('materials.empty_text')}
                >
                    <Link href="/lines" class="vff-btn primary">{t('materials.empty_browse')}</Link>
                    <Link href="/contribute" class="vff-btn secondary"
                        >{t('materials.empty_cta')}</Link
                    >
                </EmptyState>
            {:else}
                <DirectoryFilters
                    label={t('materials.filters_title')}
                    results={t('materials.results', { count: fmt(materials.total) })}
                    canReset={hasFilters}
                    resetLabel={t('materials.reset')}
                    onreset={reset}
                    onsubmit={() => applyFilters(true)}
                >
                    <div class="vff-field">
                        <label class="vff-label" for="materials-q"
                            >{t('materials.search_label')}</label
                        >
                        <input
                            id="materials-q"
                            class="vff-input"
                            type="search"
                            name="q"
                            autocomplete="off"
                            enterkeyhint="search"
                            placeholder={t('materials.search_hint')}
                            bind:value={q}
                            oninput={() => applyFilters()}
                        />
                    </div>
                    <div class="vff-field">
                        <label class="vff-label" for="materials-sort"
                            >{t('materials.sort_label')}</label
                        >
                        <select
                            id="materials-sort"
                            class="vff-select"
                            bind:value={sort}
                            onchange={() => applyFilters(true)}
                        >
                            {#each sorts as s (s)}
                                <option value={s}
                                    >{t(`materials.sorts.${SORT_KEYS[s] ?? s}`)}</option
                                >
                            {/each}
                        </select>
                    </div>
                </DirectoryFilters>

                <nav class="categories" aria-label={t('materials.filter_type')}>
                    <ul {@attach revealCurrent}>
                        <li>
                            <Link
                                class="chip"
                                href={typeHref('')}
                                preserveState
                                preserveScroll
                                aria-current={type === '' ? 'page' : undefined}
                                >{t('materials.all_types')}
                                <span class="n">{fmt(countedAll)}</span></Link
                            >
                        </li>
                        {#each counts as c (c.type)}
                            <li>
                                <Link
                                    class="chip"
                                    href={typeHref(c.type)}
                                    preserveState
                                    preserveScroll
                                    aria-current={type === c.type ? 'page' : undefined}
                                    >{t(`materials.types.${c.type}`)}
                                    <span class="n">{fmt(c.count)}</span></Link
                                >
                            </li>
                        {/each}
                    </ul>
                </nav>

                {#if materials.data.length}
                    <ul class="list">
                        {#each materials.data as m, i (m.slug)}
                            {@const alias = matchedDesignation(m, filters.q ?? '')}
                            {@const builders = buildersText(m.builders, m.builder, t)}
                            <li class="item">
                                <div class="thumb">
                                    {#if m.thumb}
                                        <img
                                            src={commonsSrc(m.thumb.url)}
                                            alt=""
                                            width="240"
                                            height="160"
                                            loading={i < 6 ? undefined : 'lazy'}
                                            decoding="async"
                                        />
                                    {/if}
                                </div>
                                <div class="body">
                                    <Link class="name" href="/materials/{m.slug}">{m.name}</Link>
                                    {#if alias}
                                        <span class="alias"
                                            >{@render arrowed(
                                                t('materials.also_known', { name: alias }),
                                            )}</span
                                        >
                                    {/if}
                                    <span class="meta">
                                        {#each metaOf(m) as part, j (j)}<span>{part}</span>{/each}
                                    </span>
                                    {#if builders}
                                        <!-- Comme la fiche ligne : tous les noms jusqu'à quatre, sinon trois et « et N autres ». -->
                                        <span class="builder">{builders}</span>
                                    {/if}
                                    {#if figuresOf(m).length}
                                        <span class="meta figures">
                                            {#each figuresOf(m) as part, j (j)}<span>{part}</span
                                                >{/each}
                                        </span>
                                    {/if}
                                </div>
                            </li>
                        {/each}
                    </ul>

                    {#if materials.last_page > 1}
                        <Pagination
                            current={materials.current_page}
                            last={materials.last_page}
                            onpage={goToPage}
                        />
                    {/if}
                {:else}
                    <EmptyState
                        icon="bi-search"
                        title={t('materials.no_match_title')}
                        text={t('materials.no_match_text')}
                    >
                        <button type="button" class="vff-btn secondary small" onclick={reset}>
                            {t('materials.reset')}
                        </button>
                    </EmptyState>
                {/if}
            {/if}
        </div>
    </main>
</Layout>

<style>
    /* Lien vers le quiz du matériel, sous le chapeau du bandeau. */
    .quiz-link {
        margin: var(--vff-space-4) 0 0;
    }

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

    /* Catégories : étiquettes en pilule, la catégorie choisie en fond orange pâle et soulignée. */
    .categories ul {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .categories :global(.chip) {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 6px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-elevated);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        text-decoration: none;
        white-space: nowrap;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .categories :global(.chip:hover) {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-accent-soft);
    }
    .categories :global(.chip[aria-current='page']) {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .n {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
        font-weight: 400;
    }

    /* Liste : une rangée par série (vignette, nom, détails), deux colonnes sur grand écran. */
    .list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 32px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .item {
        position: relative;
        display: grid;
        grid-template-columns: 120px minmax(0, 1fr);
        gap: 16px;
        align-items: start;
        padding: 16px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .thumb {
        aspect-ratio: 3 / 2;
        overflow: hidden;
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-subtle);
    }
    .thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .body :global(.name) {
        display: inline-block;
        padding-block: 2px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        line-height: 1.35;
        color: var(--vff-text);
        text-decoration: none;
        overflow-wrap: anywhere;
    }
    /* Toute la rangée est cliquable par le lien du nom (une seule cible pour le clavier). */
    .body :global(.name)::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: var(--vff-radius-sm);
    }
    .item:hover :global(.name) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
        text-underline-offset: 3px;
    }
    .body :global(.name:focus-visible) {
        outline: none;
    }
    .body :global(.name:focus-visible)::after {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .alias {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        column-gap: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .meta span:not(:last-child)::after {
        content: '·';
        margin-left: 6px;
    }
    .builder {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .figures {
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 900px) {
        .list {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        /* Téléphone : les catégories défilent sur une rangée au lieu d'occuper cinq lignes. */
        .categories {
            margin-inline: calc(-1 * var(--vff-gutter));
        }
        .categories ul {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 2px var(--vff-gutter) 6px;
            scrollbar-width: thin;
        }
        .categories :global(.chip) {
            min-height: 44px;
        }
        .item {
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 12px;
        }
    }
</style>
