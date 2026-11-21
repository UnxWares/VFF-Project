<script>
    import { Link, router } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import ReviewNav from '$components/Contribute/ReviewNav.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import Field from '$components/ui/Field.svelte';
    import StationAuditMap from '$components/Validate/StationAuditMap.svelte';
    import { reportDate } from '$lib/errorReport.js';
    import { formatDistance, formatPk, latestRequest } from '$lib/graphAdmin.js';
    import { i18n } from '$lib/i18n.js';
    import {
        BASE_URL,
        DATE_GRADE_TONES,
        GRADE_TONES,
        PRIORITY_CHECK,
        auditDate,
        deltaYears,
        detailUrl,
        exportUrl,
        formFilters,
        hostOf,
        neighbourItem,
        numberedPositions,
        pageUrl,
        share,
        sortChecks,
        sourceFamilies,
    } from '$lib/stationAudit.js';

    /**
     * Audit des gares (StationAuditAdminController), pour les administrateurs : bilan de la
     * dernière passe de `vff:audit-stations`, file de relecture filtrée (désaccords d'abord) et
     * fiche d'une gare : carte des positions de chaque source doublée de leur liste, dates publiées
     * et leur origine, services, contrôles en échec, dates de Wikidata comparées. Lecture seule.
     *
     * Les filtres sont un formulaire GET natif ; en JavaScript, la page suit par une visite Inertia
     * qui garde le défilement et la fiche ouverte. La fiche se charge en JSON ; la fermer rend le
     * focus au bouton de sa ligne.
     */
    let { summary, filters, stations, options, thresholds = {} } = $props();

    const lang = $derived($i18n.language);
    /**
     * @param {string} key
     * @param {Record<string, unknown>} [opts]
     */
    const t = (key, opts) => String($i18n.t(key, opts));
    const A = (key, opts) => t(`audit_stations.${key}`, opts);
    const num = (n) => (n == null ? '' : Number(n).toLocaleString(lang));
    const dist = (m) => formatDistance(m, lang) || A('queue.no_value');
    const pct = (v) => v.toLocaleString(lang, { style: 'percent', maximumFractionDigits: 1 });
    const date = (iso, precision) => auditDate(iso, precision, t, lang);
    const sourceName = (s) => A(`sources.${s}`, { defaultValue: s ?? '' });
    const dateSourceName = (s) => t(`stations.sources.names.${s}`, { defaultValue: s ?? '' });
    const thresholdOpts = $derived({
        agree: num(thresholds.agree_m ?? 20),
        close: num(thresholds.close_m ?? 100),
        disagree: num(thresholds.disagree_precise_m ?? 100),
        coarse: num(thresholds.disagree_coarse_m ?? 1000),
        track: num(thresholds.track_far_m ?? 50),
    });
    const checkText = (code) => A(`checks_text.${code}`, thresholdOpts);

    const audited = $derived(Number(summary?.audited ?? 0));
    const items = $derived(stations?.items ?? []);
    const total = $derived(Number(stations?.total ?? 0));

    // Bilan : gares auditées puis gares par note de placement.
    const figures = $derived([
        { value: num(audited), label: A('summary.audited') },
        ...(options?.grades ?? []).map((g) => ({
            value: num(summary?.placement?.[g] ?? 0),
            label: A(`grades.${g}`),
        })),
    ]);
    const dateKinds = ['opening', 'closure'];
    const dateGrades = $derived(options?.date_grades ?? []);
    const candidateParts = $derived(
        Object.entries(summary?.candidates ?? {})
            .filter(([, n]) => Number(n) > 0)
            .map(([k, n]) =>
                A('summary.candidate_count', {
                    label: A(`candidates.comparisons.${k}`),
                    count: num(n),
                }),
            ),
    );

    // File : visite Inertia qui garde le défilement et l'état (fiche ouverte).
    let busy = $state(false);
    function visit(url) {
        router.get(
            url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => (busy = true),
                onFinish: () => (busy = false),
            },
        );
    }

    function submitFilters(event) {
        event.preventDefault();
        visit(pageUrl(formFilters(new FormData(event.currentTarget))));
    }

    const optionCount = (label, count) =>
        A('filters.option_count', { label, count: num(count ?? 0) });

    // Fiche de la gare choisie : un nouveau choix abandonne la requête précédente.
    const detailRequest = latestRequest();
    let selectedId = $state(null);
    let selectedName = $state('');
    let detail = $state(null);
    let detailState = $state('idle');
    let announce = $state('');
    let sheetHeading = $state();
    let queueHeading = $state();

    const positions = $derived(numberedPositions(detail?.positions));
    const sheetChecks = $derived(sortChecks(detail?.checks ?? [], (c) => c.code));
    const stale = $derived(Boolean(detail) && detail.station_id !== selectedId);
    const previous = $derived(neighbourItem(items, selectedId, -1));
    const next = $derived(neighbourItem(items, selectedId, 1));

    async function openSheet(item) {
        const signal = detailRequest.next();
        selectedId = item.station_id;
        selectedName = item.name;
        detailState = 'loading';
        await tick();
        sheetHeading?.focus();
        try {
            const r = await fetch(detailUrl(item.station_id), {
                headers: { Accept: 'application/json' },
                signal,
            });
            if (!detailRequest.isCurrent(signal)) return;
            if (r.status === 404) {
                detail = null;
                detailState = 'missing';
                return;
            }
            if (!r.ok) throw new Error(String(r.status));
            const data = await r.json();
            if (!detailRequest.isCurrent(signal)) return;
            detail = data;
            detailState = 'ready';
            announce = A('sheet.shown', { name: data.name });
        } catch {
            if (!detailRequest.isCurrent(signal)) return;
            detailState = 'error';
        }
    }

    async function closeSheet() {
        detailRequest.cancel();
        const id = selectedId;
        selectedId = null;
        detail = null;
        detailState = 'idle';
        await tick();
        const button = document.getElementById(`audit-open-${id}`);
        if (button) button.focus();
        else queueHeading?.focus();
    }

    const sheetTitle = $derived(detail && !stale ? detail.name : selectedName);
</script>

<svelte:head><title>{A('meta_title')} - VFF</title></svelte:head>

{#snippet gradeBadge(grade)}
    {#if grade}
        <span class="vff-badge {GRADE_TONES[grade] ?? 'muted'}">{A(`grades.${grade}`)}</span>
    {/if}
{/snippet}

{#snippet dateBadge(grade)}
    {#if grade}
        <span class="vff-badge {DATE_GRADE_TONES[grade] ?? 'muted'}"
            >{A(`date_grades.${grade}`)}</span
        >
    {/if}
{/snippet}

{#snippet priorityBadge()}
    <span class="vff-badge danger priority">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        {A('queue.priority')}
    </span>
{/snippet}

<Layout compactHeader>
    <main class="audit-page">
        <PageHero title={A('title')} subtitle={A('subtitle')}>
            <ReviewNav current="audit_stations" />
        </PageHero>

        <div class="vff-container body">
            {#if audited === 0}
                <EmptyState
                    icon="bi-geo-alt"
                    title={A('not_run')}
                    text={A('not_run_text')}
                    headingLevel={2}
                />
            {:else}
                <section class="block" aria-labelledby="audit-summary-title">
                    <h2 id="audit-summary-title">{A('summary.title')}</h2>
                    {#if summary.audited_at}
                        <p class="note">
                            {A('summary.audited_at', {
                                date: reportDate(summary.audited_at, lang),
                            })}
                        </p>
                    {/if}
                    <StatStrip items={figures} label={A('summary.label')} size="sm" />

                    <h3>{A('summary.dates_title')}</h3>
                    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                    <div
                        class="vff-table-wrap"
                        role="region"
                        aria-labelledby="audit-dates-caption"
                        tabindex="0"
                    >
                        <table class="vff-table">
                            <caption id="audit-dates-caption" class="vff-sr-only">
                                {A('summary.dates_caption', { count: num(audited) })}
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">{A('summary.date')}</th>
                                    {#each dateGrades as g (g)}
                                        <th scope="col" class="num">{A(`date_grades.${g}`)}</th>
                                    {/each}
                                </tr>
                            </thead>
                            <tbody>
                                {#each dateKinds as kind (kind)}
                                    <tr>
                                        <th scope="row">{A(`dates.${kind}`)}</th>
                                        {#each dateGrades as g (g)}
                                            {@const n = summary?.[kind]?.[g] ?? 0}
                                            <td class="num">
                                                {A('summary.count_share', {
                                                    count: num(n),
                                                    share: pct(share(n, audited)),
                                                })}
                                            </td>
                                        {/each}
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                    <p class="note">
                        {candidateParts.length
                            ? A('summary.candidates', { list: candidateParts.join(', ') })
                            : A('summary.candidates_none')}
                    </p>

                    <details class="help">
                        <summary>{A('help.title')}</summary>
                        <div class="help-body">
                            <h3>{A('help.placement')}</h3>
                            <p class="note">{A('help.placement_intro')}</p>
                            <dl class="defs">
                                {#each options?.grades ?? [] as g (g)}
                                    <div>
                                        <dt>{@render gradeBadge(g)}</dt>
                                        <dd>{A(`grades_text.${g}`, thresholdOpts)}</dd>
                                    </div>
                                {/each}
                            </dl>
                            <h3>{A('help.dates')}</h3>
                            <dl class="defs">
                                {#each dateGrades as g (g)}
                                    <div>
                                        <dt>{@render dateBadge(g)}</dt>
                                        <dd>{A(`date_grades_text.${g}`)}</dd>
                                    </div>
                                {/each}
                            </dl>
                            <h3>{A('help.checks')}</h3>
                            <dl class="defs">
                                {#each sortChecks(options?.checks ?? []) as c (c)}
                                    <div>
                                        <dt>{A(`checks.${c}`)}</dt>
                                        <dd>{checkText(c)}</dd>
                                    </div>
                                {/each}
                            </dl>
                        </div>
                    </details>
                </section>

                <section class="block" aria-labelledby="audit-queue-title">
                    <h2 id="audit-queue-title" tabindex="-1" bind:this={queueHeading}>
                        {A('queue.title')}
                    </h2>

                    <form
                        class="vff-panel filters"
                        method="get"
                        action={BASE_URL}
                        aria-label={A('filters.title')}
                        onsubmit={submitFilters}
                    >
                        <div class="fields">
                            <Field id="audit-grade" label={A('filters.grade')}>
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        name="grade"
                                        class="vff-select"
                                        value={filters.grade}
                                    >
                                        {#each options?.grades ?? [] as g (g)}
                                            <option value={g}
                                                >{optionCount(
                                                    A(`grades.${g}`),
                                                    summary.placement?.[g],
                                                )}</option
                                            >
                                        {/each}
                                        <option value="all">{A('grades.all')}</option>
                                    </select>
                                {/snippet}
                            </Field>
                            <Field id="audit-check" label={A('filters.check')}>
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        name="check"
                                        class="vff-select"
                                        value={filters.check ?? ''}
                                    >
                                        <option value="">{A('filters.any_check')}</option>
                                        {#each sortChecks(options?.checks ?? []) as c (c)}
                                            <option value={c}
                                                >{optionCount(
                                                    A(`checks.${c}`),
                                                    summary.checks?.[c],
                                                )}</option
                                            >
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                            <Field id="audit-region" label={A('filters.region')}>
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        name="region"
                                        class="vff-select"
                                        value={filters.region ?? ''}
                                    >
                                        <option value="">{A('filters.all_regions')}</option>
                                        {#each options?.regions ?? [] as r (r.code)}
                                            <option value={r.code} lang="fr">{r.name}</option>
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                            {#each dateKinds as kind (kind)}
                                <Field id="audit-{kind}" label={A(`filters.${kind}`)}>
                                    {#snippet children(attrs)}
                                        <select
                                            {...attrs}
                                            name={kind}
                                            class="vff-select"
                                            value={filters[kind] ?? ''}
                                        >
                                            <option value="">{A('filters.any_grade')}</option>
                                            {#each dateGrades as g (g)}
                                                <option value={g}
                                                    >{optionCount(
                                                        A(`date_grades.${g}`),
                                                        summary[kind]?.[g],
                                                    )}</option
                                                >
                                            {/each}
                                        </select>
                                    {/snippet}
                                </Field>
                            {/each}
                            <Field
                                id="audit-line"
                                label={A('filters.line')}
                                hint={A('filters.line_hint')}
                            >
                                {#snippet children(attrs)}
                                    <input
                                        {...attrs}
                                        name="line"
                                        class="vff-input"
                                        type="search"
                                        autocomplete="off"
                                        spellcheck="false"
                                        maxlength="120"
                                        value={filters.line ?? ''}
                                    />
                                {/snippet}
                            </Field>
                            <Field id="audit-q" label={A('filters.q')}>
                                {#snippet children(attrs)}
                                    <input
                                        {...attrs}
                                        name="q"
                                        class="vff-input"
                                        type="search"
                                        autocomplete="off"
                                        maxlength="120"
                                        value={filters.q ?? ''}
                                    />
                                {/snippet}
                            </Field>
                            <Field id="audit-sort" label={A('filters.sort')}>
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        name="sort"
                                        class="vff-select"
                                        value={filters.sort}
                                    >
                                        {#each options?.sorts ?? [] as s (s)}
                                            <option value={s}>{A(`sorts.${s}`)}</option>
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                        </div>
                        <div class="actions">
                            <button type="submit" class="vff-btn primary">
                                {A('filters.apply')}
                            </button>
                            <Link href={BASE_URL} class="vff-btn quiet" preserveScroll>
                                {A('filters.reset')}
                            </Link>
                        </div>
                    </form>

                    <div class="list-head">
                        <p class="count" role="status">
                            {A('queue.count', { count: total, n: num(total) })}
                        </p>
                        {#if total > 0}
                            <div class="export">
                                <a
                                    class="vff-btn secondary small"
                                    href={exportUrl(filters)}
                                    download
                                    aria-describedby="audit-export-hint"
                                >
                                    <i class="bi bi-download" aria-hidden="true"></i>
                                    {A('filters.export')}
                                </a>
                                <span id="audit-export-hint" class="hint"
                                    >{A('filters.export_hint')}</span
                                >
                            </div>
                        {/if}
                    </div>

                    {#if items.length === 0}
                        <EmptyState icon="bi-search" title={A('queue.empty')}>
                            <Link href={BASE_URL} class="vff-btn secondary small" preserveScroll>
                                {A('filters.reset')}
                            </Link>
                        </EmptyState>
                    {:else}
                        <!-- Un seul tableau : rangées classiques dès 768 px, une carte par gare en dessous
                             (CSS), chaque valeur précédée du libellé de sa colonne (data-label). Cadre
                             défilant focalisable entre 768 px et la largeur du tableau (WCAG 2.1.1). -->
                        <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                        <div
                            class="vff-table-wrap queue-wrap"
                            role="region"
                            aria-labelledby="audit-queue-caption"
                            aria-busy={busy}
                            tabindex="0"
                        >
                            <table class="vff-table queue">
                                <caption id="audit-queue-caption" class="vff-sr-only">
                                    {A('queue.caption', {
                                        page: num(stations.page),
                                        last: num(stations.last_page),
                                    })}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{A('queue.columns.station')}</th>
                                        <th scope="col">{A('queue.columns.placement')}</th>
                                        <th scope="col" class="num wrap"
                                            >{A('queue.columns.nearest')}</th
                                        >
                                        <th scope="col" class="num wrap"
                                            >{A('queue.columns.farthest')}</th
                                        >
                                        <th scope="col" class="num wrap"
                                            >{A('queue.columns.track')}</th
                                        >
                                        <th scope="col">{A('queue.columns.dates')}</th>
                                        <th scope="col">{A('queue.columns.checks')}</th>
                                        <th scope="col"
                                            ><span class="vff-sr-only"
                                                >{A('queue.columns.sheet')}</span
                                            ></th
                                        >
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each items as item (item.station_id)}
                                        {@const open = item.station_id === selectedId}
                                        {@const codes = sortChecks(item.checks)}
                                        {@const fam = sourceFamilies(item)}
                                        <tr class={{ selected: open }}>
                                            <th scope="row" class="name">
                                                <Link href="/stations/{item.slug}" lang="fr"
                                                    >{item.name}</Link
                                                >
                                                {#if item.department || item.region}
                                                    <span class="sub" lang="fr"
                                                        >{[item.department, item.region]
                                                            .filter(Boolean)
                                                            .join(', ')}</span
                                                    >
                                                {/if}
                                            </th>
                                            <td data-label={A('queue.columns.placement')}>
                                                {@render gradeBadge(item.placement_grade)}
                                                <span class="sub">
                                                    {A('queue.families', {
                                                        count: fam.count,
                                                    })}{#if fam.list.length}
                                                        : {fam.list
                                                            .map((f) => A(`families.${f}`))
                                                            .join(', ')}{/if}
                                                </span>
                                            </td>
                                            <td class="num" data-label={A('queue.columns.nearest')}
                                                >{dist(item.nearest_agreeing_m)}</td
                                            >
                                            <td class="num" data-label={A('queue.columns.farthest')}
                                                >{dist(item.farthest_precise_m)}</td
                                            >
                                            <td class="num" data-label={A('queue.columns.track')}
                                                >{dist(item.track_offset_m)}</td
                                            >
                                            <td data-label={A('queue.columns.dates')}>
                                                <span class="date-line"
                                                    >{A('queue.columns.opening')}
                                                    {@render dateBadge(item.opening_grade)}</span
                                                >
                                                <span class="date-line"
                                                    >{A('queue.columns.closure')}
                                                    {@render dateBadge(item.closure_grade)}</span
                                                >
                                            </td>
                                            <td data-label={A('queue.columns.checks')}>
                                                {#if codes.length}
                                                    <ul class="checks">
                                                        {#each codes as c (c)}
                                                            <li>
                                                                {#if c === PRIORITY_CHECK}
                                                                    {@render priorityBadge()}
                                                                {/if}
                                                                {A(`checks.${c}`)}
                                                            </li>
                                                        {/each}
                                                    </ul>
                                                {:else}
                                                    <span class="muted">{A('queue.no_check')}</span>
                                                {/if}
                                            </td>
                                            <td class="open-cell">
                                                <button
                                                    type="button"
                                                    id="audit-open-{item.station_id}"
                                                    class="vff-btn secondary small"
                                                    aria-expanded={open}
                                                    aria-controls={open ? 'audit-sheet' : undefined}
                                                    onclick={() => openSheet(item)}
                                                >
                                                    {A('queue.open')}<span class="vff-sr-only"
                                                        >&nbsp;: {item.name}</span
                                                    >
                                                </button>
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                        {#if stations.last_page > 1}
                            <Pagination
                                current={stations.page}
                                last={stations.last_page}
                                onpage={(n) => visit(pageUrl(filters, n))}
                            />
                        {/if}
                    {/if}
                </section>

                {#if selectedId !== null}
                    <section
                        id="audit-sheet"
                        class="sheet"
                        aria-labelledby="audit-sheet-title"
                        aria-busy={detailState === 'loading'}
                    >
                        <div class="sheet-head">
                            <h2
                                id="audit-sheet-title"
                                tabindex="-1"
                                lang="fr"
                                bind:this={sheetHeading}
                            >
                                {sheetTitle}
                            </h2>
                            <div class="sheet-actions">
                                {#if previous}
                                    <button
                                        type="button"
                                        class="vff-btn quiet"
                                        onclick={() => openSheet(previous)}
                                    >
                                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                        {A('sheet.previous')}
                                    </button>
                                {/if}
                                {#if next}
                                    <button
                                        type="button"
                                        class="vff-btn quiet"
                                        onclick={() => openSheet(next)}
                                    >
                                        {A('sheet.next')}
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </button>
                                {/if}
                                <button
                                    type="button"
                                    class="vff-btn secondary small"
                                    onclick={closeSheet}
                                >
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    {A('sheet.close')}
                                </button>
                            </div>
                        </div>

                        {#if detailState === 'loading'}
                            <p class="muted">{A('sheet.loading')}</p>
                        {:else if detailState === 'error'}
                            <p class="error">{A('sheet.error')}</p>
                        {:else if detailState === 'missing'}
                            <p class="muted">{A('sheet.missing')}</p>
                        {/if}

                        {#if detail}
                            <div class={['sheet-body', { stale }]}>
                                <div class="sheet-grid">
                                    <div class="map-col">
                                        <StationAuditMap
                                            station={detail}
                                            {positions}
                                            label={A('map.label', { name: detail.name })}
                                        />
                                        <ul class="legend" aria-label={A('map.legend')}>
                                            <li>
                                                <span class="key retained" aria-hidden="true"
                                                    >{A('map.retained_marker')}</span
                                                >
                                                {A('map.legend_retained')}
                                            </li>
                                            <li>
                                                <span class="key" aria-hidden="true">1</span>
                                                {A('map.legend_precise')}
                                            </li>
                                            <li>
                                                <span class="key coarse" aria-hidden="true">2</span>
                                                {A('map.legend_coarse')}
                                            </li>
                                            <li>
                                                <span class="key derived" aria-hidden="true">3</span
                                                >
                                                {A('map.legend_derived')}
                                            </li>
                                            <li>
                                                <span class="track-key" aria-hidden="true"></span>
                                                {A('map.legend_track')}
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="facts-col">
                                        <dl class="facts">
                                            <div>
                                                <dt>{A('sheet.placement')}</dt>
                                                <dd>
                                                    {@render gradeBadge(detail.placement_grade)}
                                                    <span class="sub"
                                                        >{A('queue.families', {
                                                            count: detail.families,
                                                        })}</span
                                                    >
                                                </dd>
                                            </div>
                                            {#if detail.department}
                                                <div>
                                                    <dt>{A('sheet.location')}</dt>
                                                    <dd>
                                                        {A('sheet.location_value', {
                                                            department: detail.department,
                                                            region: detail.region ?? '',
                                                        })}
                                                    </dd>
                                                </div>
                                            {/if}
                                        </dl>

                                        <h3>{A('dates.title')}</h3>
                                        <dl class="facts">
                                            {#each [{ kind: 'opening', iso: detail.opened_at, precision: detail.opened_precision, source: detail.opened_source, grade: detail.opening_grade }, { kind: 'closure', iso: detail.closed_at, precision: detail.closed_precision, source: detail.closed_source, grade: detail.closure_grade }] as d (d.kind)}
                                                <div>
                                                    <dt>{A(`dates.${d.kind}`)}</dt>
                                                    <dd>
                                                        <span
                                                            >{d.iso
                                                                ? date(d.iso, d.precision)
                                                                : A('dates.none')}</span
                                                        >
                                                        {@render dateBadge(d.grade)}
                                                        {#if d.source}
                                                            <span class="sub"
                                                                >{A('dates.source', {
                                                                    source: dateSourceName(
                                                                        d.source,
                                                                    ),
                                                                })}</span
                                                            >
                                                        {/if}
                                                    </dd>
                                                </div>
                                            {/each}
                                        </dl>

                                        <h3>{A('services.title')}</h3>
                                        <dl class="facts">
                                            {#each ['passenger', 'freight'] as kind (kind)}
                                                {@const s = detail.services?.[kind]}
                                                <div>
                                                    <dt>{A(`services.${kind}`)}</dt>
                                                    <dd>
                                                        <span
                                                            >{A(
                                                                `services.states.${s?.state ?? 'unknown'}`,
                                                            )}{#if s?.closed_on}, {A(
                                                                    'services.closed_on',
                                                                    {
                                                                        date: date(
                                                                            s.closed_on,
                                                                            s.closed_precision,
                                                                        ),
                                                                    },
                                                                )}{/if}</span
                                                        >
                                                        {@render dateBadge(s?.grade)}
                                                        {#if s?.source}
                                                            <span class="sub"
                                                                >{A('dates.source', {
                                                                    source: dateSourceName(
                                                                        s.source,
                                                                    ),
                                                                })}</span
                                                            >
                                                        {/if}
                                                    </dd>
                                                </div>
                                            {/each}
                                        </dl>
                                        <Link class="vff-link" href="/stations/{detail.slug}"
                                            >{A('sheet.public')}<span class="vff-sr-only"
                                                >&nbsp;: {detail.name}</span
                                            ></Link
                                        >
                                    </div>
                                </div>

                                <h3>{A('positions.title')}</h3>
                                {#if positions.length === 0}
                                    <p class="muted">{A('positions.none')}</p>
                                {:else}
                                    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                                    <div
                                        class="vff-table-wrap"
                                        role="region"
                                        aria-labelledby="audit-positions-caption"
                                        tabindex="0"
                                    >
                                        <table class="vff-table">
                                            <caption
                                                id="audit-positions-caption"
                                                class="vff-sr-only"
                                            >
                                                {A('positions.caption', { name: detail.name })}
                                            </caption>
                                            <thead>
                                                <tr>
                                                    <th scope="col" class="num"
                                                        >{A('positions.columns.marker')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('positions.columns.source')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('positions.columns.name')}</th
                                                    >
                                                    <th scope="col" class="num"
                                                        >{A('positions.columns.distance')}</th
                                                    >
                                                    <th scope="col" class="num"
                                                        >{A('positions.columns.track')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('positions.columns.precision')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('positions.columns.licence')}</th
                                                    >
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {#each positions as p (p.n)}
                                                    <tr class={{ derived: p.derived }}>
                                                        <td class="num">
                                                            <span
                                                                class={[
                                                                    'key',
                                                                    {
                                                                        coarse: !p.precise,
                                                                        derived: p.derived,
                                                                    },
                                                                ]}>{p.n}</span
                                                            >
                                                        </td>
                                                        <th scope="row">
                                                            {sourceName(p.source)}
                                                            {#if p.ref}<span class="sub ref"
                                                                    >{p.ref}</span
                                                                >{/if}
                                                            {#if p.retained}
                                                                <span class="vff-badge accent"
                                                                    >{A('positions.retained')}</span
                                                                >
                                                            {/if}
                                                            {#if p.derived}
                                                                <span
                                                                    class="vff-badge muted"
                                                                    title={A(
                                                                        'positions.derived_hint',
                                                                    )}
                                                                    >{A('positions.derived')}</span
                                                                >
                                                            {/if}
                                                            {#if !p.onMap}
                                                                <span class="sub"
                                                                    >{A(
                                                                        'positions.not_on_map',
                                                                    )}</span
                                                                >
                                                            {/if}
                                                        </th>
                                                        <td lang="fr">{p.name ?? ''}</td>
                                                        <td class="num">{dist(p.distance_m)}</td>
                                                        <td class="num">{dist(p.track_m)}</td>
                                                        <td>
                                                            {p.precise
                                                                ? A('positions.precise')
                                                                : A('positions.coarse')}
                                                            {#if p.precision_m != null}
                                                                <span class="sub"
                                                                    >{A('positions.within', {
                                                                        distance: dist(
                                                                            p.precision_m,
                                                                        ),
                                                                    })}</span
                                                                >
                                                            {/if}
                                                        </td>
                                                        <td>
                                                            {p.provenance?.license ??
                                                                A('positions.licence_unknown')}
                                                            {#if p.provenance?.license_status}
                                                                <span class="sub"
                                                                    >{A(
                                                                        `positions.licence_status.${p.provenance.license_status}`,
                                                                    )}</span
                                                                >
                                                            {/if}
                                                        </td>
                                                    </tr>
                                                {/each}
                                            </tbody>
                                        </table>
                                    </div>
                                {/if}

                                <div class="sheet-pair">
                                    <div>
                                        <h3>{A('failed.title')}</h3>
                                        {#if sheetChecks.length}
                                            <ul class="failed">
                                                {#each sheetChecks as c, i (i)}
                                                    <li>
                                                        <p class="check-title">
                                                            {#if c.code === PRIORITY_CHECK}
                                                                {@render priorityBadge()}
                                                            {/if}
                                                            {A(`checks.${c.code}`)}
                                                        </p>
                                                        <p class="note">{checkText(c.code)}</p>
                                                        {#if c.detail}
                                                            <p class="sub">
                                                                {A('failed.detail', {
                                                                    detail: c.detail,
                                                                })}
                                                            </p>
                                                        {/if}
                                                    </li>
                                                {/each}
                                            </ul>
                                        {:else}
                                            <p class="muted">{A('failed.none')}</p>
                                        {/if}
                                    </div>
                                    <div>
                                        <h3>{A('lines.title')}</h3>
                                        {#if detail.lines?.length}
                                            <ul class="lines">
                                                {#each detail.lines as l (l.code)}
                                                    <li>
                                                        <Link href="/lines/{l.slug}" lang="fr"
                                                            >{l.name}</Link
                                                        >
                                                        <span class="vff-plate">{l.code}</span>
                                                        {#if l.pk_m != null}
                                                            <span class="sub"
                                                                >{A('lines.pk', {
                                                                    pk: formatPk(l.pk_m, lang),
                                                                })}</span
                                                            >
                                                        {/if}
                                                    </li>
                                                {/each}
                                            </ul>
                                        {:else}
                                            <p class="muted">{A('lines.none')}</p>
                                        {/if}
                                    </div>
                                </div>

                                <h3>{A('candidates.title')}</h3>
                                {#if !detail.candidates?.length}
                                    <p class="muted">{A('candidates.none')}</p>
                                {:else}
                                    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                                    <div
                                        class="vff-table-wrap"
                                        role="region"
                                        aria-labelledby="audit-candidates-caption"
                                        tabindex="0"
                                    >
                                        <table class="vff-table">
                                            <caption
                                                id="audit-candidates-caption"
                                                class="vff-sr-only"
                                            >
                                                {A('candidates.caption', { name: detail.name })}
                                            </caption>
                                            <thead>
                                                <tr>
                                                    <th scope="col"
                                                        >{A('candidates.columns.kind')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('candidates.columns.candidate')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('candidates.columns.vff')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('candidates.columns.comparison')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('candidates.columns.replace')}</th
                                                    >
                                                    <th scope="col"
                                                        >{A('candidates.columns.item')}</th
                                                    >
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {#each detail.candidates as c, i (i)}
                                                    {@const years = deltaYears(c.delta_days)}
                                                    {@const refs = (c.references ?? []).filter(
                                                        (r) => r?.url,
                                                    )}
                                                    <tr>
                                                        <th scope="row"
                                                            >{A(`candidates.kinds.${c.kind}`)}</th
                                                        >
                                                        <td>{date(c.date, c.precision)}</td>
                                                        <td>
                                                            {#if c.vff_date}
                                                                {date(c.vff_date, c.vff_precision)}
                                                                {#if c.vff_source}
                                                                    <span class="sub"
                                                                        >{dateSourceName(
                                                                            c.vff_source,
                                                                        )}</span
                                                                    >
                                                                {/if}
                                                            {:else}
                                                                <span class="muted"
                                                                    >{A('candidates.no_vff')}</span
                                                                >
                                                            {/if}
                                                        </td>
                                                        <td>
                                                            {A(
                                                                `candidates.comparisons.${c.comparison}`,
                                                            )}
                                                            {#if years}
                                                                <span class="sub"
                                                                    >{A('candidates.delta', {
                                                                        count: years,
                                                                    })}</span
                                                                >
                                                            {/if}
                                                        </td>
                                                        <td>
                                                            {#if c.would_replace}
                                                                <span class="vff-badge warning"
                                                                    >{A(
                                                                        'candidates.would_replace',
                                                                    )}</span
                                                                >
                                                            {:else}
                                                                {A('candidates.would_keep')}
                                                            {/if}
                                                        </td>
                                                        <td>
                                                            {#if c.source_url}
                                                                <a
                                                                    href={c.source_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    >{c.source_ref ?? 'Wikidata'}<i
                                                                        class="bi bi-box-arrow-up-right vff-ext"
                                                                        aria-hidden="true"
                                                                    ></i><span class="vff-sr-only">
                                                                        ({t(
                                                                            'common.new_tab',
                                                                        )})</span
                                                                    ></a
                                                                >
                                                            {:else}
                                                                {c.source_ref ?? ''}
                                                            {/if}
                                                            {#if c.references?.length}
                                                                <span class="sub"
                                                                    >{A('candidates.references', {
                                                                        count: c.references.length,
                                                                    })}</span
                                                                >
                                                            {/if}
                                                            {#each refs as r, j (j)}
                                                                <a
                                                                    class="sub ref-link"
                                                                    href={r.url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer nofollow"
                                                                    >{hostOf(r.url)}<span
                                                                        class="vff-sr-only"
                                                                    >
                                                                        ({t(
                                                                            'common.new_tab',
                                                                        )})</span
                                                                    ></a
                                                                >
                                                            {/each}
                                                        </td>
                                                    </tr>
                                                {/each}
                                            </tbody>
                                        </table>
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </section>
                {/if}
                <p class="vff-sr-only" aria-live="polite">{announce}</p>
            {/if}
        </div>
    </main>
</Layout>

<style>
    .body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-section-space);
        padding-block: var(--vff-section-space);
    }
    .block,
    .sheet {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
    }
    h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        overflow-wrap: anywhere;
    }
    h3 {
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
    }
    .note {
        max-width: var(--vff-measure);
        margin: 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
    }
    .muted {
        margin: 0;
        color: var(--vff-text-muted);
    }
    .error {
        margin: 0;
        color: var(--vff-danger);
    }
    .sub {
        display: block;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .ref {
        overflow-wrap: anywhere;
    }

    /* Explication des notes, repliée. */
    .help summary {
        display: flex;
        align-items: center;
        min-height: 44px;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        cursor: pointer;
    }
    .help-body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        padding-top: var(--vff-space-2);
    }
    .defs {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        max-width: 60rem;
        margin: 0;
    }
    .defs > div {
        display: grid;
        grid-template-columns: minmax(0, 15rem) minmax(0, 1fr);
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .defs dt {
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .defs dd {
        margin: 0;
    }
    @media (max-width: 600px) {
        .defs > div {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    /* Filtres. */
    .filters {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
    }
    .fields {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
        gap: var(--vff-space-4) var(--vff-space-5);
        align-items: start;
    }
    .fields :global(.vff-select),
    .fields :global(.vff-input) {
        width: 100%;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .list-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .count {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .export {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .hint {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }

    /* File. */
    .queue-wrap[aria-busy='true'] {
        opacity: 0.6;
        transition: opacity var(--vff-transition);
    }
    .queue .name {
        min-width: 9rem;
    }
    .queue .wrap {
        white-space: normal;
        min-width: 6.5rem;
    }
    .queue tr.selected > * {
        background-color: var(--vff-bg-accent);
    }
    .open-cell .vff-btn {
        white-space: nowrap;
    }
    .date-line {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-2);
        white-space: nowrap;
    }
    .date-line + .date-line {
        margin-top: var(--vff-space-1);
    }
    .checks {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-1);
        min-width: 10rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    /* Téléphone et petite tablette : une carte arrondie par gare, sans défilement en largeur.
       Les en-têtes de colonne sont repris devant chaque valeur (data-label). */
    @media (max-width: 767.98px) {
        .queue-wrap {
            overflow: visible;
            border: 0;
            border-radius: 0;
        }
        .queue,
        .queue tbody,
        .queue tr,
        .queue th,
        .queue td {
            display: block;
        }
        .queue thead {
            display: none;
        }
        .queue tbody {
            display: flex;
            flex-direction: column;
            gap: var(--vff-space-3);
        }
        .queue tr {
            padding: var(--vff-space-4);
            border: 1px solid var(--vff-border);
            border-radius: var(--vff-radius-md);
            background-color: var(--vff-bg-elevated);
        }
        .queue tr.selected {
            border-color: var(--vff-border-control);
            background-color: var(--vff-bg-accent);
        }
        .queue tr.selected > * {
            background-color: transparent;
        }
        .vff-table-wrap .queue :is(th, td),
        .vff-table-wrap .queue :is(th, td):first-child,
        .vff-table-wrap .queue :is(th, td):last-child {
            padding: var(--vff-space-2) 0;
            border-bottom: 1px solid var(--vff-border);
        }
        .queue .name {
            min-width: 0;
            padding-top: 0;
            font-size: var(--vff-fs-md);
        }
        .queue td[data-label] {
            display: grid;
            grid-template-columns: minmax(6.5rem, 38%) minmax(0, 1fr);
            gap: var(--vff-space-1) var(--vff-space-3);
            text-align: left;
            white-space: normal;
        }
        .queue td[data-label]::before {
            content: attr(data-label);
            color: var(--vff-text-muted);
        }
        .queue td[data-label] > * {
            grid-column: 2;
        }
        .vff-table-wrap .queue .open-cell {
            padding-bottom: 0;
            border-bottom: 0;
        }
        .checks {
            min-width: 0;
        }
    }
    .priority {
        margin-inline-end: var(--vff-space-1);
    }

    /* Fiche. */
    .sheet {
        padding: var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .sheet-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-3);
    }
    .sheet-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
    }
    .sheet-body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
        transition: opacity var(--vff-transition);
    }
    .sheet-body.stale {
        opacity: 0.6;
    }
    .sheet-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-5);
    }
    @media (min-width: 900px) {
        .sheet-grid {
            grid-template-columns: minmax(0, 3fr) minmax(16rem, 2fr);
            align-items: start;
        }
    }
    .map-col,
    .facts-col {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        min-width: 0;
    }
    .facts-col h3 {
        margin-top: var(--vff-space-2);
    }
    .facts {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        margin: 0;
    }
    .facts dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .facts dd {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-2);
        margin: 0;
    }
    .facts dd .sub {
        flex-basis: 100%;
    }
    .legend {
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
    /* Repères de la légende et de la liste, comme sur la carte. */
    .key {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        padding: 0 6px;
        border: 3px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .key.coarse {
        border-style: dashed;
    }
    .key.derived {
        opacity: 0.55;
    }
    .key.retained {
        border-color: var(--vff-text);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-accent);
        color: var(--vff-on-accent);
    }
    .track-key {
        display: inline-block;
        width: 28px;
        height: 4px;
        border-radius: 2px;
        background-color: var(--vff-text);
    }
    tr.derived > :not(:first-child) {
        color: var(--vff-text-muted);
    }
    .sheet-pair {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-5);
    }
    @media (min-width: 900px) {
        .sheet-pair {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .failed,
    .lines {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        margin: var(--vff-space-3) 0 0;
        padding: 0;
        list-style: none;
    }
    .failed p {
        margin: 0;
    }
    .check-title {
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .lines li {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-2);
    }
    .lines .sub {
        display: inline;
    }
    .ref-link {
        overflow-wrap: anywhere;
    }
</style>
