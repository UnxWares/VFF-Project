<script>
    /**
     * « Chronologie du rail en France » : une décennie à la fois, année par année, les faits que
     * l'encyclopédie connaît (ouvertures, réouvertures, fermetures, électrifications, signalisation,
     * matériel, accidents graves, jalons nationaux), chacun avec sa fiche et ses sources. Les données
     * viennent de NetworkChronology (cache, calculé par vff:compute-network-chronology). Filtres par
     * types et par région dans l'adresse : le formulaire marche aussi sans JavaScript.
     */
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber, formatLocale } from '$lib/typo.js';
    import {
        chronologyHref,
        dateInYear,
        decadeShares,
        groupByKind,
        yearTotals,
    } from '$lib/chronology.js';

    /** @type {{ chronology: any, kinds: string[], regions: Array<{ code: string, name: string }>, thresholds: { deaths: number, units: number } }} */
    let { chronology, kinds, regions, thresholds } = $props();

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`chronology.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const km = (v) => t('km', { value: formatNumber(v, lang, { maximumFractionDigits: 1 }) });
    const count = (key, value) => t(key, { count: value, value: n(value) });

    const filters = $derived(chronology.filters);
    const decade = $derived(chronology.decade);
    const decades = $derived(chronology.decades);
    const shares = $derived(decadeShares(decades));
    const index = $derived(decades.findIndex((d) => d.decade === decade));
    const previous = $derived(index > 0 ? decades[index - 1] : null);
    const next = $derived(index >= 0 && index < decades.length - 1 ? decades[index + 1] : null);
    const lastYear = $derived(Math.min(decade + 9, chronology.last_year));
    const firstYear = $derived(Math.max(decade, chronology.first_year));
    const regionName = $derived(regions.find((r) => r.code === filters.region)?.name ?? null);
    const computedOn = $derived(
        new Date(chronology.computed_at).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );

    /** @param {number|null} d */
    const href = (d) => chronologyHref({ decade: d, types: filters.types, region: filters.region });

    /** @param {SubmitEvent} event */
    function submit(event) {
        event.preventDefault();
        const data = new FormData(/** @type {HTMLFormElement} */ (event.currentTarget));
        const types = kinds.filter((k) => data.getAll('types[]').includes(k));
        const region = String(data.get('region') ?? '') || null;
        router.get(chronologyHref({ decade, types, region }), {}, { preserveScroll: true });
    }

    /** Résumé d'une année : « 144 fermetures, 4 373 km ; 1 jalon national ». */
    function summary(counts) {
        return yearTotals(counts)
            .shown.map((c) => {
                const text = count(`counts.${c.kind}`, c.shown);
                return c.km > 0
                    ? t('with_km', {
                          text,
                          km: t('km', { value: formatNumber(Math.round(c.km), lang) }),
                      })
                    : text;
            })
            .join(t('list_sep'));
    }

    /** Une liste de plus de douze faits s'ouvre à la demande. */
    const openByDefault = (list) => list.length <= 12 || filters.types.length === 1;
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/chronology" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('hero_title')}
            subtitle={t('hero_subtitle')}
            status={t('computed', { date: computedOn })}
        />

        <SectionBlock title={t('browse_title')} maxWidth="1180px">
            <form class="filters" method="get" action="/chronology" onsubmit={submit}>
                <input type="hidden" name="decade" value={decade} />
                <fieldset>
                    <legend>{t('filters_legend')}</legend>
                    <p class="hint">{t('filters_hint')}</p>
                    <div class="checks">
                        {#each kinds as k (k)}
                            <label class="vff-check">
                                <input
                                    type="checkbox"
                                    name="types[]"
                                    value={k}
                                    checked={filters.types.includes(k)}
                                />
                                {t(`kinds.${k}`)}
                            </label>
                        {/each}
                    </div>
                </fieldset>
                <div class="row">
                    <div class="vff-field">
                        <label class="vff-label" for="chronology-region">{t('region_label')}</label>
                        <select
                            id="chronology-region"
                            name="region"
                            class="vff-select"
                            value={filters.region ?? ''}
                        >
                            <option value="">{t('region_all')}</option>
                            {#each regions as r (r.code)}
                                <option value={r.code}>{r.name}</option>
                            {/each}
                        </select>
                    </div>
                    <div class="actions">
                        <button type="submit" class="vff-btn primary">{t('apply')}</button>
                        {#if filters.types.length > 0 || filters.region}
                            <Link
                                href={chronologyHref({ decade })}
                                class="vff-btn secondary"
                                preserveScroll>{t('reset')}</Link
                            >
                        {/if}
                    </div>
                </div>
                {#if filters.region}
                    <p class="hint">{t('national_note')}</p>
                {/if}
            </form>

            <nav class="decades" aria-label={t('decades_nav')}>
                <ol>
                    {#each decades as d (d.decade)}
                        <li>
                            <Link
                                href={href(d.decade)}
                                aria-current={d.decade === decade ? 'page' : undefined}
                                preserveScroll
                            >
                                <span class="d">{d.decade}</span>
                                <span class="c">{count('decade_count', d.shown + d.hidden)}</span>
                                <span class="bar" aria-hidden="true">
                                    <span style:inline-size="{shares[d.decade]}%"></span>
                                </span>
                            </Link>
                        </li>
                    {/each}
                </ol>
            </nav>
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={t('decade_title', { from: firstYear, to: lastYear })}
            intro={regionName}
            maxWidth="1180px"
            id="decade"
        >
            {#if chronology.years.length === 0}
                <p class="empty">{t('decade_empty')}</p>
            {/if}
            {#each chronology.years as y (y.year)}
                {@const totals = yearTotals(y.counts)}
                <section class="year" aria-labelledby="year-{y.year}">
                    <h3 id="year-{y.year}">{y.year}</h3>
                    {#if totals.shown.length > 0}
                        <p class="summary">{summary(y.counts)}</p>
                    {/if}
                    {#if totals.hidden > 0}
                        <p class="hidden-note">{count('year_hidden', totals.hidden)}</p>
                    {/if}

                    {#each groupByKind(y.facts, kinds) as g (g.kind)}
                        {#if g.kind === 'milestone'}
                            {#each g.facts as f (f.id)}
                                <div class="milestone">
                                    <p class="m-when">{dateInYear(f, t('when_year'))}</p>
                                    <p class="m-title">{f.title}</p>
                                    <p class="m-text">{f.text}</p>
                                    {@render sources(f)}
                                </div>
                            {/each}
                        {:else}
                            <details class="group" open={openByDefault(g.facts)}>
                                <summary>
                                    <span class="g-title">{t(`kinds.${g.kind}`)}</span>
                                    <span class="g-count">{n(g.facts.length)}</span>
                                </summary>
                                <ul class="facts">
                                    {#each g.facts as f (f.id)}
                                        {@render fact(f)}
                                    {/each}
                                </ul>
                            </details>
                        {/if}
                    {/each}
                </section>
            {/each}

            <nav class="pager" aria-label={t('decades_pager')}>
                {#if previous}
                    <Link href={href(previous.decade)} class="vff-btn secondary"
                        >{t('prev', { decade: previous.decade })}</Link
                    >
                {/if}
                {#if next}
                    <Link href={href(next.decade)} class="vff-btn secondary"
                        >{t('next', { decade: next.decade })}</Link
                    >
                {/if}
            </nav>
        </SectionBlock>

        <SectionBlock
            title={t('method_title')}
            intro={t('method_intro', { date: computedOn })}
            maxWidth="900px"
        >
            <ul class="method">
                {#each ['sources', 'dedup', 'dates', 'km', 'publish', 'thresholds', 'regions'] as m (m)}
                    <li>{t(`method.${m}`, thresholds)}</li>
                {/each}
            </ul>
            <p class="note">
                {t('api_text')}
                <a href="/api/v1/chronology" class="vff-link">/api/v1/chronology</a>
            </p>
            <p class="note">
                <Link href="/network" class="vff-link">{t('link_network')}</Link>
            </p>
        </SectionBlock>
    </main>
</Layout>

{#snippet sources(f)}
    {#if f.sources.length > 0}
        <p class="src">
            {count('source_label', f.sources.length)}
            {#each f.sources as s, i (s.provider)}
                <span class="s"
                    >{#if s.url}<a href={s.url} class="vff-link" rel="noopener external"
                            >{s.name}<span class="vff-sr-only"> {t('external')}</span></a
                        >{:else}{s.name}{/if}{i < f.sources.length - 1 ? ',' : ''}</span
                >{/each}
        </p>
    {/if}
{/snippet}

{#snippet fact(f)}
    <li class="fact">
        <span class="when">{dateInYear(f, t('when_year'))}</span>
        <div class="what">
            <p class="head">
                {#if f.kind === 'signalling'}
                    <span class="system">{f.title}</span>
                    <Link href={f.path} class="vff-link">{f.line.name}</Link>
                {:else if f.line}
                    <Link href={f.path} class="vff-link">{f.line.name}</Link>
                {:else}
                    <Link href={f.path} class="vff-link">{f.title}</Link>
                {/if}
                {#if f.traffic}
                    <span class="traffic">{t(`traffic.${f.traffic}`)}</span>
                {/if}
            </p>
            {#if f.sections.length > 0 && f.sections.length <= 3}
                <p class="detail">
                    {f.sections.map((s) => s.text).join(', ')}{f.km !== null ? `, ${km(f.km)}` : ''}
                </p>
            {:else if f.sections.length > 3}
                <details class="sections">
                    <summary
                        >{t('sections_many', { value: n(f.sections.length) })}{f.km !== null
                            ? `, ${km(f.km)}`
                            : ''}</summary
                    >
                    <ul>
                        {#each f.sections as s, i (i)}
                            <li>{s.text}{s.km !== null ? `, ${km(s.km)}` : ''}</li>
                        {/each}
                    </ul>
                </details>
            {:else if f.km !== null}
                <p class="detail">{km(f.km)}</p>
            {/if}
            {#if f.deaths !== null}
                <p class="detail">{count('deaths', f.deaths)}</p>
            {/if}
            {#if f.units !== null}
                <p class="detail">{count('units', f.units)}</p>
            {/if}
            {@render sources(f)}
        </div>
    </li>
{/snippet}

<style>
    .filters {
        display: grid;
        gap: var(--vff-space-4);
        margin: 0 0 var(--vff-space-6);
    }
    .filters fieldset {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .filters legend {
        margin-bottom: var(--vff-space-2);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-text);
    }
    .hint {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .checks {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
    }
    .row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-4);
    }
    .row .vff-field {
        min-width: min(100%, 280px);
        margin: 0;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }

    .decades ol {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(84px, 1fr));
        gap: var(--vff-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .decades :global(a) {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-height: 44px;
        padding: 8px 10px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        text-decoration: none;
    }
    .decades :global(a:hover) {
        border-color: var(--vff-border-strong);
    }
    .decades :global(a:focus-visible) {
        outline: var(--vff-focus-width, 3px) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset, 2px);
    }
    .decades :global(a[aria-current='page']) {
        border-color: var(--vff-accent-ink);
        background: var(--vff-accent-soft);
    }
    .decades .d {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-body);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .decades .c {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .decades .bar {
        display: block;
        block-size: 4px;
        margin-top: 4px;
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg-muted);
        overflow: hidden;
    }
    .decades .bar > span {
        display: block;
        block-size: 100%;
        border-radius: var(--vff-radius-pill);
        background: var(--vff-accent-ink);
    }

    .empty,
    .note {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .year {
        padding-block: var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
    }
    .year:first-of-type {
        border-top: 0;
        padding-top: 0;
    }
    .year h3 {
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
        color: var(--vff-text);
    }
    .summary,
    .hidden-note {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text);
        text-wrap: pretty;
    }
    .hidden-note {
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .milestone {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        padding: var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
    }
    .milestone p {
        margin: 0 0 6px;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .milestone .m-when {
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .milestone .m-title {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.3;
        text-wrap: balance;
    }
    .group {
        margin: 0 0 var(--vff-space-3);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg);
    }
    .group > summary {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--vff-space-3);
        min-height: 44px;
        padding: 10px var(--vff-space-4);
        border-radius: var(--vff-radius-md);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        cursor: pointer;
    }
    .group > summary:focus-visible {
        outline: var(--vff-focus-width, 3px) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset, 2px);
    }
    /* Repère d'ouverture (le marqueur natif disparaît avec display: flex), muet pour les lecteurs d'écran. */
    .g-title::before {
        content: '▸' / '';
        display: inline-block;
        inline-size: 1.1em;
        color: var(--vff-text-muted);
    }
    .group[open] > summary .g-title::before {
        content: '▾' / '';
    }
    .g-count {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .facts {
        margin: 0;
        padding: 0 var(--vff-space-4) var(--vff-space-2);
        list-style: none;
    }
    .fact {
        display: grid;
        grid-template-columns: 9.5em minmax(0, 1fr);
        gap: var(--vff-space-3);
        padding-block: var(--vff-space-3);
        border-top: 1px solid var(--vff-border);
    }
    .when {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        font-variant-numeric: tabular-nums;
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .what p {
        margin: 0 0 4px;
        font-family: var(--vff-font-body);
        line-height: 1.55;
    }
    .head {
        font-size: var(--vff-fs-body);
        overflow-wrap: anywhere;
    }
    .system {
        font-weight: 700;
    }
    .system::after {
        content: ' · ';
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .traffic {
        color: var(--vff-text-muted);
    }
    .detail {
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }
    .sections {
        margin: 0 0 4px;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
    }
    .sections summary {
        min-height: 32px;
        cursor: pointer;
    }
    .sections ul {
        margin: 4px 0 0;
        padding-left: 1.2em;
        line-height: 1.55;
    }
    .src {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    /* Un nom de source et sa virgule restent ensemble ; les noms passent à la ligne entre eux. */
    .src .s {
        display: inline-block;
        margin-inline-end: 0.3em;
    }
    .pager {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: var(--vff-space-3);
        margin-top: var(--vff-space-5);
    }
    .method {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        padding-left: 1.2em;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .method li {
        margin-bottom: var(--vff-space-2);
        text-wrap: pretty;
    }
    @media (max-width: 600px) {
        .checks {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 var(--vff-space-3);
        }
        .checks .vff-check {
            align-items: flex-start;
            padding-block: 10px;
            line-height: 1.35;
            hyphens: auto;
            overflow-wrap: anywhere;
        }
        .fact {
            grid-template-columns: minmax(0, 1fr);
            gap: 2px;
        }
        .facts {
            padding-inline: var(--vff-space-3);
        }
        .group > summary {
            padding-inline: var(--vff-space-3);
        }
    }
</style>
