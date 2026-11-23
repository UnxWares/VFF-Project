<script>
    import { Link } from '@inertiajs/svelte';
    import { onMount, tick } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import DirectoryFilters from '$components/Directory/DirectoryFilters.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import TimelineChart from '$components/Materials/TimelineChart.svelte';
    import TimelineTable from '$components/Materials/TimelineTable.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        axisOf,
        builderChoices,
        byDecade,
        filterRows,
        groupDated,
        periodLabel,
        stateIn,
        undatedRows,
        yearSummary,
    } from '$lib/materialTimeline.js';

    /**
     * Frise du matériel roulant : filtres (traction, constructeur, nom), année choisie, trois affichages
     * (frise, liste par décennie pour téléphone, tableau triable) et liste des séries sans date.
     */
    let { rows = [], meta = {}, groups = [], focus = null } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const fmt = (n) => Number(n ?? 0).toLocaleString(lang);

    const currentYear = $derived(meta.current_year);
    const firstYear = $derived(meta.first_year ?? currentYear - 100);
    const axis = $derived(axisOf(rows, currentYear));

    let group = $state('');
    let builder = $state('');
    let name = $state('');
    let year = $state(/** @type {number|null} */ (null));
    // Chiffres écrits dans le champ « année » : validés avant de devenir l'année choisie.
    let yearText = $state('');

    // Téléphone (moins de 768 px) : liste par décennie ; ordinateur : frise. Le choix de la personne prime.
    const WIDE = '(min-width: 768px)';
    let view = $state(
        typeof window !== 'undefined' && window.matchMedia && !window.matchMedia(WIDE).matches
            ? 'list'
            : 'chart',
    );
    let chosen = false;
    const VIEWS = ['chart', 'list', 'table'];

    onMount(() => {
        const media = window.matchMedia(WIDE);
        const follow = (e) => {
            if (!chosen) view = e.matches ? 'chart' : 'list';
        };
        media.addEventListener('change', follow);
        return () => media.removeEventListener('change', follow);
    });

    // Série à repérer (lien « Voir dans la frise » d'une fiche) : rangée défilée au centre, sans animation.
    onMount(async () => {
        if (!focus) return;
        await tick();
        const el = document.querySelector(`[data-slug="${CSS.escape(focus)}"]`);
        el?.scrollIntoView({ block: 'center' });
    });

    const filtered = $derived(filterRows(rows, { group, builder, q: name }));
    const dated = $derived(filtered.filter((r) => Number.isInteger(r.in_service_from)));
    const undated = $derived(undatedRows(filtered));
    const chartGroups = $derived(groupDated(filtered, groups));
    const decades = $derived(byDecade(filtered));
    const summary = $derived(year === null ? null : yearSummary(filtered, year));
    const stateOf = (r) => (year === null ? 'no' : stateIn(r, year));

    // Constructeurs proposés par le champ : noms distincts, du plus fréquent au moins fréquent.
    const builderNames = $derived(builderChoices(rows));

    const hasFilters = $derived(!!(group || builder || name || year !== null));

    function setYear(value) {
        const n = Number.parseInt(String(value), 10);
        year = Number.isInteger(n) ? Math.min(Math.max(n, firstYear), currentYear) : null;
        yearText = year === null ? '' : String(year);
    }
    function onYearText(e) {
        yearText = e.currentTarget.value;
        const n = Number.parseInt(yearText, 10);
        // Année complète (4 chiffres) : appliquée ; sinon la saisie continue sans bouger la frise.
        if (/^\d{4}$/.test(yearText) && n >= firstYear && n <= currentYear) year = n;
        else if (yearText === '') year = null;
    }
    function reset() {
        group = '';
        builder = '';
        name = '';
        year = null;
        yearText = '';
    }
    function pick(v) {
        chosen = true;
        view = v;
    }

    const unitsText = (r) =>
        Number.isInteger(r.units_built)
            ? t('materials_timeline.units_long', { count: fmt(r.units_built) })
            : null;
</script>

<svelte:head>
    <title>{t('materials_timeline.meta_title')} - VFF</title>
    <meta name="description" content={t('materials_timeline.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('materials_timeline.title')}
            subtitle={t('materials_timeline.subtitle', {
                dated: fmt(meta.dated),
                total: fmt(meta.total),
            })}
        >
            <p class="back">
                <Link href="/materials" class="vff-link"
                    ><i class="bi bi-arrow-left" aria-hidden="true"></i>
                    {t('materials_timeline.all_materials')}</Link
                >
            </p>
        </PageHero>

        <div class="vff-container page">
            <DirectoryFilters
                label={t('materials_timeline.filters_title')}
                results={t('materials_timeline.results', {
                    count: fmt(dated.length),
                    undated: fmt(undated.length),
                })}
                canReset={hasFilters}
                resetLabel={t('materials_timeline.reset')}
                onreset={reset}
            >
                <div class="vff-field">
                    <label class="vff-label" for="tl-name"
                        >{t('materials_timeline.name_label')}</label
                    >
                    <input
                        id="tl-name"
                        class="vff-input"
                        type="search"
                        autocomplete="off"
                        enterkeyhint="search"
                        placeholder={t('materials_timeline.name_hint')}
                        bind:value={name}
                    />
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="tl-group"
                        >{t('materials_timeline.group_label')}</label
                    >
                    <select id="tl-group" class="vff-select" bind:value={group}>
                        <option value="">{t('materials_timeline.all_groups')}</option>
                        {#each groups as g (g)}
                            <option value={g}>{t(`materials_timeline.groups.${g}`)}</option>
                        {/each}
                    </select>
                </div>
                <div class="vff-field">
                    <label class="vff-label" for="tl-builder"
                        >{t('materials_timeline.builder_label')}</label
                    >
                    <input
                        id="tl-builder"
                        class="vff-input"
                        type="search"
                        list="tl-builders"
                        autocomplete="off"
                        placeholder={t('materials_timeline.builder_hint')}
                        bind:value={builder}
                    />
                    <datalist id="tl-builders">
                        {#each builderNames as b (b)}<option value={b}></option>{/each}
                    </datalist>
                </div>
            </DirectoryFilters>

            <section class="year" aria-labelledby="tl-year-title">
                <h2 id="tl-year-title">{t('materials_timeline.year_title')}</h2>
                <div class="year-controls">
                    <div class="vff-field number">
                        <label class="vff-label" for="tl-year"
                            >{t('materials_timeline.year_label')}</label
                        >
                        <input
                            id="tl-year"
                            class="vff-input"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]{'{4}'}"
                            maxlength="4"
                            autocomplete="off"
                            value={yearText}
                            oninput={onYearText}
                        />
                    </div>
                    <input
                        class="slider"
                        type="range"
                        min={firstYear}
                        max={currentYear}
                        step="1"
                        value={year ?? currentYear}
                        aria-label={t('materials_timeline.year_slider')}
                        oninput={(e) => setYear(e.currentTarget.value)}
                    />
                    <button
                        type="button"
                        class="vff-btn secondary small"
                        disabled={year === null}
                        onclick={() => setYear('')}
                    >
                        {t('materials_timeline.year_clear')}
                    </button>
                </div>
                <p class="summary" role="status" aria-live="polite">
                    {#if summary === null}
                        {t('materials_timeline.year_hint')}
                    {:else if summary.yes + summary.maybe === 0}
                        {t('materials_timeline.year_none', { year })}
                    {:else}
                        {t('materials_timeline.year_summary', {
                            year,
                            yes: fmt(summary.yes),
                        })}{summary.maybe
                            ? t('materials_timeline.year_maybe', { maybe: fmt(summary.maybe) })
                            : ''}{summary.units
                            ? t('materials_timeline.year_units', { units: fmt(summary.units) })
                            : ''}.
                    {/if}
                </p>
            </section>

            <div class="views" role="group" aria-label={t('materials_timeline.view_label')}>
                {#each VIEWS as v (v)}
                    <button
                        type="button"
                        class={['view', { on: view === v }]}
                        aria-pressed={view === v}
                        onclick={() => pick(v)}>{t(`materials_timeline.view.${v}`)}</button
                    >
                {/each}
            </div>

            {#if view === 'chart'}
                <details class="legend" open>
                    <summary>{t('materials_timeline.legend_title')}</summary>
                    <ul>
                        {#each ['range', 'ongoing', 'withdrawn', 'unknown'] as kind (kind)}
                            <li>
                                <span class={['swatch', kind]} aria-hidden="true"></span>
                                {t(`materials_timeline.legend.${kind}`)}
                            </li>
                        {/each}
                    </ul>
                </details>
                <p class="vff-hint">{t('materials_timeline.chart_help')}</p>
            {/if}

            {#if filtered.length === 0}
                <EmptyState icon="bi-search" title={t('materials_timeline.no_results')} text="">
                    <button type="button" class="vff-btn secondary small" onclick={reset}>
                        {t('materials_timeline.reset')}
                    </button>
                </EmptyState>
            {:else if view === 'chart'}
                <TimelineChart groups={chartGroups} {axis} {year} {currentYear} focusSlug={focus} />
            {:else if view === 'list'}
                <div class="decades">
                    {#each decades as d (d.decade)}
                        <section aria-labelledby="dec-{d.decade}">
                            <h3 id="dec-{d.decade}">
                                {t('materials_timeline.decade', { decade: d.decade })}
                            </h3>
                            <ul class="items">
                                {#each d.rows as r (r.slug)}
                                    {@const s = stateOf(r)}
                                    <li
                                        class={['item', { out: year !== null && s === 'no' }]}
                                        data-slug={r.slug}
                                    >
                                        <Link
                                            href="/materials/{r.slug}"
                                            class="name"
                                            aria-current={r.slug === focus ? 'true' : undefined}
                                            >{r.name}</Link
                                        >
                                        <span class="meta">
                                            {t(`materials_timeline.groups_short.${r.group}`)}
                                            · {periodLabel(r, t)}{unitsText(r)
                                                ? ` · ${unitsText(r)}`
                                                : ''}{year !== null && s !== 'no'
                                                ? ` · ${t(`materials_timeline.state.${s}`)}`
                                                : ''}
                                        </span>
                                    </li>
                                {/each}
                            </ul>
                        </section>
                    {/each}
                </div>
            {:else}
                <TimelineTable rows={filtered} {year} {stateOf} />
            {/if}

            {#if undated.length && view !== 'table'}
                <section class="undated" aria-labelledby="tl-undated">
                    <h2 id="tl-undated">{t('materials_timeline.undated_title')}</h2>
                    <p>{t('materials_timeline.undated_intro', { count: fmt(undated.length) })}</p>
                    <details open={undated.some((r) => r.slug === focus)}>
                        <summary>{t('materials_timeline.undated_toggle')}</summary>
                        <ul class="items cols">
                            {#each undated as r (r.slug)}
                                <li class="item" data-slug={r.slug}>
                                    <Link
                                        href="/materials/{r.slug}"
                                        class="name"
                                        aria-current={r.slug === focus ? 'true' : undefined}
                                        >{r.name}</Link
                                    >
                                    <span class="meta">
                                        {t(
                                            `materials_timeline.groups_short.${r.group}`,
                                        )}{Number.isInteger(r.in_service_to)
                                            ? ` · ${periodLabel(r, t)}`
                                            : ''}
                                    </span>
                                </li>
                            {/each}
                        </ul>
                    </details>
                </section>
            {/if}

            <p class="vff-hint note">{t('materials_timeline.note')}</p>
        </div>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .back {
        margin: var(--vff-space-4) 0 0;
    }
    .page {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        padding-top: var(--vff-block-space);
        padding-bottom: var(--vff-section-space);
    }

    /* Année : champ, curseur, bouton ; le résumé est annoncé quand il change. */
    .year {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        padding: var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .year h2 {
        margin: 0;
        font-size: var(--vff-fs-lead);
    }
    .year-controls {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-4);
    }
    .number {
        width: 7.5rem;
    }
    .slider {
        flex: 1 1 14rem;
        min-width: 0;
        min-height: 44px;
        accent-color: var(--vff-accent-ink);
    }
    .summary {
        margin: 0;
        font-size: var(--vff-fs-body);
        min-height: 1.5em;
    }

    /* Choix de l'affichage : trois boutons en pilule, le choisi en fond orange pâle. */
    .views {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
    }
    .view {
        min-height: 44px;
        padding: 6px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        cursor: pointer;
    }
    .view:hover {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-accent-soft);
    }
    .view.on {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .view:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 2px;
    }

    .legend summary {
        cursor: pointer;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        min-height: 44px;
        display: flex;
        align-items: center;
    }
    .legend ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-sm);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
    }
    .swatch {
        flex: none;
        width: 2.5rem;
        height: 0.9rem;
        border-radius: var(--vff-radius-pill);
        background: var(--vff-accent-strong);
    }
    .swatch.withdrawn,
    .swatch.unknown {
        background: linear-gradient(to right, var(--vff-accent-strong) 40%, transparent);
    }
    .swatch.ongoing {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    /* Liste par décennie (téléphone) et liste des séries sans date. */
    .decades {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
    }
    .decades h3 {
        margin: 0 0 var(--vff-space-2);
        font-size: var(--vff-fs-lead);
    }
    .items {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: var(--vff-space-3) 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .item.out {
        opacity: 0.45;
    }
    .item :global(.name) {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        align-self: flex-start;
    }
    .item :global(.name[aria-current='true']) {
        background-color: var(--vff-bg-accent);
        border-radius: var(--vff-radius-xs);
    }
    .meta {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    @media (min-width: 768px) {
        .items.cols {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: var(--vff-space-6);
        }
    }
    @media (min-width: 1200px) {
        .items.cols {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    .undated h2 {
        margin: 0 0 var(--vff-space-2);
        font-size: var(--vff-fs-lead);
    }
    .undated summary {
        cursor: pointer;
        min-height: 44px;
        display: flex;
        align-items: center;
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .note {
        margin: 0;
    }
</style>
