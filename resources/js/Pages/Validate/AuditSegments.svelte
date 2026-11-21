<script>
    import { Link, router } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import ReviewNav from '$components/Contribute/ReviewNav.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import Field from '$components/ui/Field.svelte';
    import SegmentAuditMap from '$components/Validate/SegmentAuditMap.svelte';
    import { reportDate } from '$lib/errorReport.js';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import {
        BASE_URL,
        CHECKS,
        checkShares,
        cleanParams,
        currentParams,
        evidenceText,
        exportUrl,
        formParams,
        formatKm,
        gradeKey,
        pageUrl,
        percentNumber,
        segmentCollection,
        sortSegments,
    } from '$lib/segmentAudit.js';

    /**
     * Audit des tronçons (SegmentAuditAdminController), pour les administrateurs : résumé de
     * l'époque par contrôle, lignes filtrées de la plus mal notée à la mieux notée, et fiche d'une
     * ligne : ses quatre notes, ses contrôles propres, la carte de ses tronçons colorés par note et
     * la liste des tronçons avec les preuves de chaque note. Lecture seule.
     *
     * Les filtres sont un formulaire GET natif ; en JavaScript, la page suit par une visite Inertia
     * qui garde le défilement. Choisir une ligne ajoute `line` à l'adresse ; la fermer le retire.
     * La liste des tronçons est l'équivalent accessible de la carte.
     */
    let {
        eras = [],
        year = null,
        filters = null,
        checks = CHECKS,
        grades = ['good', 'fair', 'poor', 'unknown'],
        statuses = [],
        regions = [],
        summary = null,
        lines = null,
        selected = null,
        selected_slug: selectedSlug = null,
        thresholds = {},
        rule_version: ruleVersion = null,
    } = $props();

    const lang = $derived($i18n.language);
    /**
     * @param {string} key
     * @param {Record<string, unknown>} [opts]
     */
    const t = (key, opts) => String($i18n.t(key, opts));
    const A = (key, opts) => t(`audit_segments.${key}`, opts);
    const num = (n) => (n == null ? '' : formatNumber(n, lang));
    const km = (m) => formatKm(m, lang);
    const gradeName = (g) => A(`grades.${gradeKey(g)}`);
    const checkName = (c) => (c === 'worst' ? A('filters.check_any') : A(`checks.${c}`));
    const checkHelp = (c) =>
        A(`check_help.${c}`, {
            target: num(thresholds.target_m ?? 1),
            tolerance: num(thresholds.tolerance_m ?? 5),
        });
    const eraName = (e) =>
        e.current ? A('filters.era_current', { year: e.year }) : String(e.year);

    // État affiché : époque, filtres, page ; base des liens « Voir », de la pagination et des exports.
    const params = $derived(currentParams(year, filters, lines?.page ?? null));
    const items = $derived(lines?.items ?? []);
    const shares = $derived(checkShares(summary?.by_check));
    const filtering = $derived(
        Boolean(
            filters?.check || filters?.grade || filters?.status || filters?.region || filters?.q,
        ),
    );

    let busy = $state(false);
    function visit(next) {
        router.get(BASE_URL, cleanParams(next), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (busy = true),
            onFinish: () => (busy = false),
        });
    }

    // Le formulaire est recréé à chaque « Tout effacer » : ses champs reprennent les filtres.
    let formKey = $state(0);
    function submitFilters(event) {
        event.preventDefault();
        visit({ ...formParams(new FormData(event.currentTarget)), line: selectedSlug });
    }
    function resetFilters() {
        formKey += 1;
        visit({ era: year, line: selectedSlug });
    }

    // Fiche de la ligne choisie.
    const segments = $derived(sortSegments(selected?.segments ?? []));
    const collection = $derived(segmentCollection(selected?.segments ?? []));
    let mapCheck = $state('worst');
    /** Tronçon ouvert dans la liste (et souligné sur la carte), noté avec sa ligne : il est
     * oublié quand la ligne change. */
    let opened = $state({ slug: null, id: null });
    const openId = $derived(opened.slug === selectedSlug ? opened.id : null);
    const setOpen = (id) => (opened = { slug: selectedSlug, id });
    let announce = $state('');

    // Ligne choisie ou fermée (visite Inertia) : le focus suit, sans bouger au premier affichage.
    let shownSlug = null;
    let mounted = false;
    $effect(() => {
        const slug = selectedSlug;
        if (!mounted) {
            mounted = true;
            shownSlug = slug;
            return;
        }
        if (slug === shownSlug) return;
        shownSlug = slug;
        tick().then(() =>
            document.getElementById(slug ? 'audit-sheet-title' : 'audit-list-title')?.focus(),
        );
    });

    /** Clic sur la carte : le tronçon s'ouvre dans la liste et prend le focus. */
    async function pickSegment(id) {
        setOpen(id);
        announce = A('line.picked', { id });
        await tick();
        document.getElementById(`segment-${id}`)?.querySelector('summary')?.focus();
    }

    function toggled(event, id) {
        if (event.currentTarget.open) setOpen(id);
        else if (openId === id) setOpen(null);
    }

    const lineChecks = $derived(selected?.line_checks ?? {});
    const lengthText = $derived.by(() => {
        const c = lineChecks;
        if (c.official_length_m == null || c.length_ratio == null) return A('line.length_unknown');
        return A('line.length_ratio', {
            ratio: formatNumber(c.length_ratio, lang, {
                style: 'percent',
                maximumFractionDigits: 1,
            }),
            official: formatNumber(Number(c.official_length_m) / 1000, lang, {
                maximumFractionDigits: 1,
            }),
            source: A(`line.official_sources.${c.official_source}`, {
                defaultValue: c.official_source ?? '',
            }),
        });
    });

    const evidence = (check, ev) => evidenceText(check, ev, { t, lang, thresholds });
    const lineLabel = (l) => (l?.code ? `${l.name} (${l.code})` : (l?.name ?? ''));
    const segmentMeta = (s) =>
        A('line.segment_meta', {
            status: A(`statuses.${s.status}`, { defaultValue: s.status ?? '' }),
            traffic: A(`traffic.${s.traffic_type ?? 'unknown'}`, {
                defaultValue: s.traffic_type ?? '',
            }),
            length: km(s.length_m),
            source: A(`sources.${s.trace_source ?? 'unknown'}`, {
                defaultValue: s.trace_source ?? '',
            }),
        });
</script>

<svelte:head><title>{A('meta_title')} - VFF</title></svelte:head>

{#snippet grade(g)}
    <span class="grade"
        ><span class="dot g-{gradeKey(g)}" aria-hidden="true"></span>{gradeName(g)}</span
    >
{/snippet}

<Layout compactHeader>
    <main class="audit-page">
        <PageHero title={A('title')} subtitle={A('intro')}>
            <ReviewNav current="audit_segments" />
        </PageHero>

        <div class="vff-container body">
            {#if year === null}
                <EmptyState icon="bi-clipboard-data" title={A('not_computed')} headingLevel={2} />
            {:else}
                {#if summary}
                    <section class="block" aria-labelledby="audit-summary-title">
                        <h2 id="audit-summary-title">{A('summary_label', { year })}</h2>
                        <p class="note">
                            {A('summary_lines', {
                                lines: num(summary.lines),
                                segments: num(summary.segments),
                                km: formatNumber(Number(summary.length_m ?? 0) / 1000, lang, {
                                    maximumFractionDigits: 0,
                                }),
                            })}
                            {#if summary.audited_at}
                                {A('computed_at', {
                                    date: reportDate(summary.audited_at, lang),
                                    version: ruleVersion ?? '',
                                })}
                            {/if}
                        </p>
                        <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                        <div
                            class="vff-table-wrap"
                            role="region"
                            aria-labelledby="audit-summary-caption"
                            tabindex="0"
                        >
                            <table class="vff-table summary-table">
                                <caption id="audit-summary-caption" class="vff-sr-only">
                                    {A('summary_label', { year })}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{A('filters.check')}</th>
                                        <th scope="col">{A('share_column')}</th>
                                        {#each grades as g (g)}
                                            <th scope="col" class="num">{gradeName(g)}</th>
                                        {/each}
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each shares as row (row.check)}
                                        <tr>
                                            <th scope="row">
                                                {checkName(row.check)}
                                                <span class="sub">{checkHelp(row.check)}</span>
                                            </th>
                                            <td>
                                                <span class="share-text"
                                                    >{A('share_good', {
                                                        pct: percentNumber(row.good_share, lang),
                                                    })}</span
                                                >
                                                <span class="bar" aria-hidden="true">
                                                    {#each row.parts as p (p.grade)}
                                                        {#if p.share > 0}
                                                            <span
                                                                class="g-{p.grade}"
                                                                style:width="{p.share * 100}%"
                                                            ></span>
                                                        {/if}
                                                    {/each}
                                                </span>
                                            </td>
                                            {#each grades as g (g)}
                                                {@const p = row.parts.find((x) => x.grade === g)}
                                                <td class="num">
                                                    {km(p?.length_m ?? 0)}
                                                    <span class="sub"
                                                        >{A('list.segments_count', {
                                                            count: num(p?.segments ?? 0),
                                                        })}</span
                                                    >
                                                </td>
                                            {/each}
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </section>
                {/if}

                <section class="block" aria-labelledby="audit-filters-title">
                    <h2 id="audit-filters-title">{A('filters.legend')}</h2>
                    {#key formKey}
                        <form
                            class="filters"
                            method="get"
                            action={BASE_URL}
                            aria-labelledby="audit-filters-title"
                            onsubmit={submitFilters}
                        >
                            {#if selectedSlug}
                                <input type="hidden" name="line" value={selectedSlug} />
                            {/if}
                            <Field id="audit-era" label={A('filters.era')}>
                                {#snippet children(attrs)}
                                    <select {...attrs} name="era" class="vff-select" value={year}>
                                        {#each eras as e (e.year)}
                                            <option value={e.year}>{eraName(e)}</option>
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                            <Field id="audit-check" label={A('filters.check')}>
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        name="check"
                                        class="vff-select"
                                        value={filters?.check ?? ''}
                                    >
                                        <option value="">{A('filters.check_any')}</option>
                                        {#each checks as c (c)}
                                            <option value={c}>{checkName(c)}</option>
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                            <Field id="audit-grade" label={A('filters.grade')}>
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        name="grade"
                                        class="vff-select"
                                        value={filters?.grade ?? ''}
                                    >
                                        <option value="">{A('filters.grade_any')}</option>
                                        {#each grades as g (g)}
                                            <option value={g}>{gradeName(g)}</option>
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                            <Field id="audit-status" label={A('filters.status')}>
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        name="status"
                                        class="vff-select"
                                        value={filters?.status ?? ''}
                                    >
                                        <option value="">{A('filters.status_any')}</option>
                                        {#each statuses as s (s)}
                                            <option value={s}>{A(`statuses.${s}`)}</option>
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
                                        value={filters?.region ?? ''}
                                    >
                                        <option value="">{A('filters.region_any')}</option>
                                        {#each regions as r (r.code)}
                                            <option value={r.code}>{r.name}</option>
                                        {/each}
                                    </select>
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
                                        spellcheck="false"
                                        maxlength="100"
                                        value={filters?.q ?? ''}
                                    />
                                {/snippet}
                            </Field>
                            <div class="actions">
                                <button type="submit" class="vff-btn primary"
                                    >{A('filters.apply')}</button
                                >
                                {#if filtering}
                                    <button
                                        type="button"
                                        class="vff-btn secondary"
                                        onclick={resetFilters}>{A('filters.reset')}</button
                                    >
                                {/if}
                            </div>
                        </form>
                    {/key}
                </section>

                <section class="block" aria-labelledby="audit-list-title" aria-busy={busy}>
                    <h2 id="audit-list-title" tabindex="-1">
                        {A('list.caption', { year })}
                    </h2>
                    <div class="list-head">
                        <p class="count" role="status">
                            {A('list.total', { total: num(lines?.total ?? 0) })}
                        </p>
                        {#if (lines?.total ?? 0) > 0}
                            <a
                                class="vff-btn secondary small"
                                href={exportUrl({ ...params, line: null })}
                                download
                            >
                                <i class="bi bi-download" aria-hidden="true"></i>
                                {A('list.export')}
                            </a>
                        {/if}
                    </div>
                    <p class="note">{A('list.score_help')}</p>

                    {#if items.length === 0}
                        <EmptyState icon="bi-search" title={A('list.empty')}>
                            {#if filtering}
                                <button
                                    type="button"
                                    class="vff-btn secondary small"
                                    onclick={resetFilters}>{A('filters.reset')}</button
                                >
                            {/if}
                        </EmptyState>
                    {:else}
                        <!-- Ordinateur et tablette : tableau ; téléphone : la même liste en fiches
                             (une seule des deux est affichée, donc lue). -->
                        <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                        <div
                            class="vff-table-wrap lines-table"
                            role="region"
                            aria-labelledby="audit-lines-caption"
                            tabindex="0"
                        >
                            <table class="vff-table">
                                <caption id="audit-lines-caption" class="vff-sr-only">
                                    {A('list.caption', { year })}, {A('list.page', {
                                        page: num(lines.page),
                                        last: num(lines.last_page),
                                    })}
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{A('list.line')}</th>
                                        <th scope="col" class="num">{A('list.score')}</th>
                                        {#each checks as c (c)}
                                            <th scope="col">{checkName(c)}</th>
                                        {/each}
                                        <th scope="col" class="num">{A('list.length')}</th>
                                        <th scope="col" class="num">{A('list.segments')}</th>
                                        <th scope="col"
                                            ><span class="vff-sr-only">{A('list.view')}</span></th
                                        >
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each items as item (item.slug)}
                                        <tr class={{ current: item.slug === selectedSlug }}>
                                            <th scope="row">
                                                {item.name}
                                                {#if item.code}<span class="vff-plate"
                                                        >{item.code}</span
                                                    >{/if}
                                            </th>
                                            <td class="num">{num(item.score)}</td>
                                            {#each checks as c (c)}
                                                <td>{@render grade(item.grades?.[c])}</td>
                                            {/each}
                                            <td class="num">{km(item.length_m)}</td>
                                            <td class="num">{num(item.segments)}</td>
                                            <td>
                                                <Link
                                                    href={pageUrl({ ...params, line: item.slug })}
                                                    class="vff-btn secondary small"
                                                    aria-current={item.slug === selectedSlug
                                                        ? 'true'
                                                        : undefined}
                                                    preserveScroll
                                                    preserveState
                                                    >{A('list.view')}<span class="vff-sr-only"
                                                        >&nbsp;: {lineLabel(item)}</span
                                                    ></Link
                                                >
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>

                        <ul class="line-cards">
                            {#each items as item (item.slug)}
                                <li class={{ current: item.slug === selectedSlug }}>
                                    <Link
                                        href={pageUrl({ ...params, line: item.slug })}
                                        class="card-title"
                                        aria-current={item.slug === selectedSlug
                                            ? 'true'
                                            : undefined}
                                        preserveScroll
                                        preserveState
                                    >
                                        {item.name}
                                        {#if item.code}<span class="vff-plate">{item.code}</span
                                            >{/if}
                                    </Link>
                                    <p class="card-meta">
                                        {A('list.score_inline', { score: num(item.score) })},
                                        {km(item.length_m)},
                                        {A('list.segments_count', { count: num(item.segments) })}
                                    </p>
                                    <dl class="grades">
                                        {#each checks as c (c)}
                                            <div>
                                                <dt>{checkName(c)}</dt>
                                                <dd>{@render grade(item.grades?.[c])}</dd>
                                            </div>
                                        {/each}
                                    </dl>
                                </li>
                            {/each}
                        </ul>

                        {#if lines.last_page > 1}
                            <Pagination
                                current={lines.page}
                                last={lines.last_page}
                                onpage={(n) => visit({ ...params, line: selectedSlug, page: n })}
                            />
                        {/if}
                    {/if}
                </section>

                {#if selectedSlug}
                    <section class="block sheet" aria-labelledby="audit-sheet-title">
                        <div class="sheet-head">
                            <h2 id="audit-sheet-title" tabindex="-1">
                                {A('line.heading', {
                                    line: lineLabel(selected?.line) || selectedSlug,
                                    year,
                                })}
                            </h2>
                            <Link
                                href={pageUrl({ ...params, line: null })}
                                class="vff-btn quiet"
                                preserveScroll
                                preserveState
                            >
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                {A('line.close')}
                            </Link>
                        </div>

                        {#if !selected}
                            <EmptyState title={A('line.none')} />
                        {:else}
                            <div class="sheet-links">
                                <a
                                    class="vff-btn secondary small"
                                    href={exportUrl({ era: year, line: selected.line.slug })}
                                    download
                                >
                                    <i class="bi bi-download" aria-hidden="true"></i>
                                    {A('line.export')}
                                </a>
                                <Link class="vff-link" href="/lines/{selected.line.slug}"
                                    >{A('line.open_sheet')}</Link
                                >
                            </div>

                            <p class="lead">
                                {A('line.score', {
                                    score: num(selected.score),
                                    km: km(selected.length_m),
                                })}
                            </p>
                            <dl class="line-grades">
                                {#each checks as c (c)}
                                    <div>
                                        <dt>{checkName(c)}</dt>
                                        <dd>
                                            {@render grade(selected.grades?.[c])}
                                            {#if selected.shares?.[c]}
                                                <span class="sub"
                                                    >{A('share_good', {
                                                        pct: percentNumber(
                                                            selected.shares[c].good ?? 0,
                                                            lang,
                                                        ),
                                                    })}</span
                                                >
                                            {/if}
                                        </dd>
                                    </div>
                                {/each}
                            </dl>

                            <h3>{A('line.checks')}</h3>
                            <ul class="facts">
                                {#each lineChecks.reasons ?? [] as r (r)}
                                    <li>{A(`reasons.${r}`, { defaultValue: r })}</li>
                                {/each}
                                <li>{lengthText}</li>
                                {#if lineChecks.station_order_inversions > 0}
                                    <li>
                                        {A('line.inversions', {
                                            count: num(lineChecks.station_order_inversions),
                                        })}
                                    </li>
                                {/if}
                            </ul>

                            {#if collection.features.length > 0}
                                <fieldset class="map-check">
                                    <legend>{A('line.map_check')}</legend>
                                    <div class="pills">
                                        {#each ['worst', ...checks] as c (c)}
                                            <label class={['pill', { active: c === mapCheck }]}>
                                                <input
                                                    type="radio"
                                                    name="map-check"
                                                    value={c}
                                                    checked={c === mapCheck}
                                                    onchange={() => (mapCheck = c)}
                                                />
                                                {checkName(c)}
                                            </label>
                                        {/each}
                                    </div>
                                </fieldset>
                                <SegmentAuditMap
                                    {collection}
                                    check={mapCheck}
                                    selectedId={openId}
                                    onselect={pickSegment}
                                    label={A('line.map_label', { line: lineLabel(selected.line) })}
                                />
                                <div class="legend">
                                    <h4>{A('line.legend')}</h4>
                                    <ul>
                                        {#each grades as g (g)}
                                            <li>{@render grade(g)}</li>
                                        {/each}
                                    </ul>
                                </div>
                            {:else}
                                <p class="note">{A('line.map_empty')}</p>
                            {/if}
                            <p class="vff-sr-only" aria-live="polite">{announce}</p>

                            <h3>{A('line.segments_heading')}</h3>
                            {#if segments.length === 0}
                                <p class="note">{A('line.none')}</p>
                            {:else}
                                <ul class="segments">
                                    {#each segments as s (s.segment_id)}
                                        <li>
                                            <details
                                                id="segment-{s.segment_id}"
                                                open={openId === s.segment_id}
                                                ontoggle={(e) => toggled(e, s.segment_id)}
                                            >
                                                <summary>
                                                    <span class="seg-title"
                                                        >{A('line.segment', {
                                                            id: s.segment_id,
                                                        })}</span
                                                    >
                                                    {@render grade(s.worst_grade)}
                                                    <span class="sub"
                                                        >{A('list.score_inline', {
                                                            score: num(s.score),
                                                        })}, {segmentMeta(s)}</span
                                                    >
                                                </summary>
                                                {#if openId === s.segment_id}
                                                    <dl class="evidence">
                                                        {#each checks as c (c)}
                                                            {@const ev = evidence(
                                                                c,
                                                                s.evidence?.[c],
                                                            )}
                                                            <div>
                                                                <dt>
                                                                    {checkName(c)}
                                                                    {@render grade(s.grades?.[c])}
                                                                </dt>
                                                                <dd>
                                                                    {#if ev.reasons.length + ev.facts.length + ev.verdicts.length === 0}
                                                                        <p class="note">
                                                                            {A('evidence.none')}
                                                                        </p>
                                                                    {:else}
                                                                        <ul class="facts">
                                                                            {#each [...ev.reasons, ...ev.facts] as line, i (i)}
                                                                                <li>{line}</li>
                                                                            {/each}
                                                                        </ul>
                                                                        {#if ev.verdicts.length}
                                                                            <p
                                                                                class="verdicts-title"
                                                                            >
                                                                                {A(
                                                                                    'evidence.verdicts',
                                                                                )}
                                                                            </p>
                                                                            <ul class="facts">
                                                                                {#each ev.verdicts as v, i (i)}
                                                                                    <li>{v}</li>
                                                                                {/each}
                                                                            </ul>
                                                                        {/if}
                                                                    {/if}
                                                                </dd>
                                                            </div>
                                                        {/each}
                                                    </dl>
                                                {/if}
                                            </details>
                                        </li>
                                    {/each}
                                </ul>
                            {/if}
                        {/if}
                    </section>
                {/if}
            {/if}
        </div>
    </main>
</Layout>

<style>
    /* Couleurs des notes : mêmes valeurs que GRADE_COLORS (lib/segmentAudit.js), qui colore la
       carte ; contraste d'au moins 3:1 sur les fonds de la page dans les deux thèmes. */
    .audit-page {
        --grade-good: #15803d;
        --grade-fair: #b45309;
        --grade-poor: #b91c1c;
        --grade-unknown: #6b7280;
    }
    :global([data-theme='dark']) .audit-page {
        --grade-good: #4ade80;
        --grade-fair: #fbbf24;
        --grade-poor: #f87171;
        --grade-unknown: #94a3b8;
    }
    .g-good {
        background-color: var(--grade-good);
    }
    .g-fair {
        background-color: var(--grade-fair);
    }
    .g-poor {
        background-color: var(--grade-poor);
    }
    .g-unknown {
        background-color: var(--grade-unknown);
    }

    .body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-section-space);
        padding-block: var(--vff-block-space) var(--vff-section-space);
    }
    .block {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
    }
    .block > h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        text-wrap: balance;
    }
    .block h3 {
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-h3);
    }
    .note {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
        text-wrap: pretty;
    }
    .lead {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-body);
    }
    .sub {
        display: block;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        font-weight: 400;
        color: var(--vff-text-muted);
    }

    /* Pastille de note : la couleur souligne, le texte dit la note. */
    .grade {
        display: inline-flex;
        align-items: center;
        gap: var(--vff-space-2);
        white-space: nowrap;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-body);
        font-weight: 400;
        color: var(--vff-text);
    }
    .dot {
        flex: none;
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }

    /* Résumé de l'époque. */
    .summary-table th[scope='row'] {
        min-width: 14rem;
        max-width: 22rem;
        white-space: normal;
    }
    .summary-table td {
        vertical-align: top;
        font-variant-numeric: tabular-nums;
    }
    .share-text {
        display: block;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        white-space: nowrap;
    }
    .bar {
        display: flex;
        width: 10rem;
        height: 10px;
        margin-top: var(--vff-space-2);
        overflow: hidden;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-muted);
    }
    .bar > span {
        height: 100%;
    }

    /* Filtres. */
    .filters {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 14rem), 1fr));
        align-items: end;
        gap: var(--vff-space-4) var(--vff-space-5);
    }
    .filters :global(.vff-select),
    .filters :global(.vff-input) {
        width: 100%;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
    }

    /* Liste des lignes. */
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
    .block[aria-busy='true'] .lines-table,
    .block[aria-busy='true'] .line-cards {
        opacity: 0.6;
        transition: opacity var(--vff-transition);
    }
    .lines-table th[scope='row'] {
        min-width: 12rem;
    }
    .lines-table th[scope='row'] .vff-plate {
        margin-inline-start: var(--vff-space-2);
    }
    .lines-table tr.current {
        background-color: var(--vff-bg-accent);
    }
    .line-cards {
        display: none;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .line-cards li {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        padding: var(--vff-space-4) var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .line-cards li + li {
        margin-top: var(--vff-space-3);
    }
    .line-cards li.current {
        border-color: var(--vff-accent);
        background-color: var(--vff-bg-accent);
    }
    .line-cards :global(.card-title) {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-body);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .card-meta {
        margin: 0;
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .grades,
    .line-grades {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 12rem), 1fr));
        gap: var(--vff-space-2) var(--vff-space-5);
        margin: 0;
    }
    .grades dt,
    .line-grades dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
    }
    .grades dd,
    .line-grades dd {
        margin: 0;
    }
    @media (max-width: 600px) {
        .lines-table {
            display: none;
        }
        .line-cards {
            display: block;
        }
    }

    /* Fiche de la ligne. */
    .sheet {
        padding-top: var(--vff-block-space);
        border-top: 1px solid var(--vff-border);
    }
    .sheet-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-3) var(--vff-space-4);
    }
    .sheet-head h2 {
        margin: 0;
        min-width: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        text-wrap: balance;
        overflow-wrap: anywhere;
    }
    .sheet-links {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-5);
    }
    .line-grades {
        padding: var(--vff-space-4) var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .facts {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-1);
        max-width: var(--vff-measure);
        margin: 0;
        padding-inline-start: 1.25em;
        font-size: var(--vff-fs-body);
        text-wrap: pretty;
    }
    .map-check {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .map-check legend {
        margin-bottom: var(--vff-space-2);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .pills {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
    }
    .pill {
        display: inline-flex;
        align-items: center;
        gap: var(--vff-space-2);
        min-height: 44px;
        padding: 6px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-body);
        color: var(--vff-text);
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .pill:hover {
        background-color: var(--vff-bg-subtle);
    }
    .pill.active {
        border-color: var(--vff-text);
        background-color: var(--vff-bg-accent);
        font-weight: 600;
    }
    .pill input {
        accent-color: var(--vff-accent);
    }
    .pill:has(input:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .legend {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-5);
    }
    .legend h4 {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-body);
        font-weight: 600;
    }
    .legend ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    /* Tronçons et preuves. */
    .segments {
        margin: 0;
        padding: 0;
        list-style: none;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .segments li + li {
        border-top: 1px solid var(--vff-border);
    }
    .segments summary {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-4);
        min-height: 44px;
        padding: var(--vff-space-3) var(--vff-space-5);
        cursor: pointer;
        border-radius: var(--vff-radius-md);
        transition: background-color var(--vff-transition);
    }
    .segments summary:hover {
        background-color: var(--vff-bg-subtle);
    }
    .segments summary .sub {
        flex-basis: 100%;
    }
    .segments details[open] summary {
        background-color: var(--vff-bg-accent);
        border-end-start-radius: 0;
        border-end-end-radius: 0;
    }
    .seg-title {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .evidence {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        margin: 0;
        padding: var(--vff-space-4) var(--vff-space-5) var(--vff-space-5);
    }
    .evidence dt {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
        margin-bottom: var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .evidence dd {
        margin: 0;
    }
    .verdicts-title {
        margin: var(--vff-space-3) 0 var(--vff-space-1);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-body);
        font-weight: 600;
    }
</style>
