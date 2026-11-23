<script>
    /**
     * Fiche d'une compagnie de chemins de fer : grandes dates, réseau décennie par décennie (courbe doublée d'un
     * tableau), carte sobre du réseau à son plus haut, lignes rattachées par un événement daté (avec la raison de
     * la fin du rattachement), sources et limites. Les chiffres viennent de CompanyFigures (cache, calculés par
     * vff:compute-company-figures) ; la page ne calcule rien.
     */
    import { formatStationDate } from '$lib/stationDates.js';
    import { formatNumber, formatLocale } from '$lib/typo.js';
    import { niceMax } from '$lib/departmentSheet.js';
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import SheetToc from '$components/Sheet/SheetToc.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import DepartmentCurve from '$components/Departments/DepartmentCurve.svelte';
    import CompanyMap from '$components/Companies/CompanyMap.svelte';
    import FiguresTable from '$components/Network/FiguresTable.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { breadcrumbSchema } from '$lib/structuredData.js';
    import { i18n } from '$lib/i18n.js';

    /** Props de CompanyController::show (App\Services\Companies\CompanyFigures). */
    let {
        company,
        computed_at,
        events = [],
        sources = [],
        predecessors = [],
        successors = [],
        decades = [],
        peak,
        first_year,
        lines = [],
        lines_total = 0,
        map_info = null,
    } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const n = (v) => formatNumber(v, lang);
    const km = (v) => t('companies.km', { value: n(v) });
    const NBSP = ' ';
    const formatDate = (iso, precision) => formatStationDate(iso, precision, null, t, lang);
    const year = (iso) => Number(String(iso).slice(0, 4));

    const kindLabel = $derived(t(`companies.kind.${company.kind}`));
    const title = $derived(t('companies.title', { name: company.short }));
    const description = $derived(
        t('companies.meta', { name: company.short, kind: kindLabel.toLowerCase() }),
    );
    const computedOn = $derived(
        new Date(computed_at).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );
    const eyebrow = $derived(
        [
            company.seat,
            company.ended
                ? `${year(company.founded.date)} - ${year(company.ended.date)}`
                : t('companies.index.period_open', { from: year(company.founded.date) }),
        ].filter(Boolean),
    );

    const none = $derived(t('companies.stats.none'));
    const statItems = $derived([
        {
            value: formatDate(company.founded.date, company.founded.precision),
            label: t('companies.stats.founded'),
        },
        {
            value: company.ended
                ? formatDate(company.ended.date, company.ended.precision)
                : t('companies.stats.ongoing'),
            label: t('companies.stats.ended'),
        },
        ...(company.seat ? [{ value: company.seat, label: t('companies.stats.seat') }] : []),
        {
            value: peak ? km(peak.km) : none,
            label: peak ? `${t('companies.stats.peak')} (${peak.year})` : t('companies.stats.peak'),
        },
        { value: n(lines.length), label: t('companies.stats.lines') },
    ]);

    const stockLabel = (r) => (r.current ? t('companies.today') : String(r.year));
    const hasSeries = $derived(Boolean(peak));
    const firstYear = $derived(first_year ?? decades[0]?.year ?? 1830);
    const seriesRows = $derived(
        decades.map((r) => ({ year: r.year, label: stockLabel(r), values: [r.km] })),
    );
    const seriesMax = $derived(niceMax(Math.max(...decades.map((r) => r.km), 0)));
    const todayRow = $derived(decades.find((r) => r.current) ?? decades[decades.length - 1]);
    const series = $derived([
        { label: t('companies.series.s_km'), tone: 'a', end: km(todayRow?.km ?? 0) },
    ]);
    const seriesSummary = $derived(
        peak
            ? t('companies.series.summary', { km: n(peak.km), year: peak.year, lines: peak.lines })
            : t('companies.series.summary_none'),
    );

    // Lignes : les premières arrivent avec la page, le reste est lu à l'ouverture du bloc replié.
    const otherCount = $derived(Math.max(lines_total - lines.length, 0));
    /** @type {Array<any>} */
    let otherLines = $state([]);
    let otherLoaded = $state(false);
    async function loadOtherLines(event) {
        if (!event.currentTarget.open || otherLoaded) return;
        otherLoaded = true;
        try {
            const response = await fetch(`/companies/${company.slug}/lines.json`, {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) otherLines = await response.json();
            else otherLoaded = false;
        } catch {
            otherLoaded = false;
        }
    }

    // Tracé : lu à part (plusieurs centaines de Ko), quand la section approche de l'écran.
    /** @type {any} */
    let map = $state(null);
    let mapSection = $state();
    $effect(() => {
        if (!map_info || !mapSection || map) return;
        const load = async () => {
            try {
                const response = await fetch(`/companies/${company.slug}/map.json`, {
                    headers: { Accept: 'application/json' },
                });
                if (response.ok) map = await response.json();
            } catch {
                /* la carte reste vide : la liste des lignes en est l'alternative */
            }
        };
        if (typeof IntersectionObserver === 'undefined') {
            load();
            return;
        }
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    observer.disconnect();
                    load();
                }
            },
            { rootMargin: '600px 0px' },
        );
        observer.observe(mapSection);

        return () => observer.disconnect();
    });
    const lineSpan = (l) => {
        const from = t('companies.lines.from', { date: formatDate(l.from_date, l.from_precision) });
        if (l.to_basis === 'open') return from;

        return l.to_year ? `${from}, ${t('companies.lines.to', { year: l.to_year })}` : from;
    };

    const listFormat = $derived(new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }));
    const TOC = ['history', 'series', 'map', 'lines', 'method'];
    const tocItems = $derived(
        TOC.filter((id) => (id !== 'map' || map_info) && (id !== 'lines' || lines.length)).map(
            (id) => ({
                id,
                label: t(`companies.toc.${id}`),
            }),
        ),
    );
</script>

<svelte:head>
    <title>{title} - VFF</title>
</svelte:head>

<SeoHead
    {title}
    {description}
    path={`/companies/${company.slug}`}
    type="article"
    jsonLd={(origin) => [
        breadcrumbSchema(origin, [
            { name: $i18n.t('common.app_full'), path: '/' },
            { name: t('companies.back'), path: '/companies' },
            { name: company.short, path: `/companies/${company.slug}` },
        ]),
    ]}
/>

{#snippet lineRow(l)}
    <li class="line-row">
        <div class="line-id">
            <a class="line-name" href="/lines/{l.slug}">{l.name}</a>
            <p class="line-meta">
                {#if l.code}<span class="vff-plate">{l.code}</span>{/if}
                {#if l.is_lgv}<span class="vff-badge muted">{t('companies.lines.lgv')}</span>{/if}
                {#if l.km !== null}<span class="km"
                        >{t('companies.lines.km', { km: n(l.km), year: peak.year }).replace(
                            /(\d) (?=km)/u,
                            `$1${NBSP}`,
                        )}</span
                    >{/if}
                {#if l.open_today}<span>{t('companies.lines.open_today')}</span>{/if}
            </p>
        </div>
        <p class="facts">
            <span>{lineSpan(l)}</span>
            <span class="muted">{t(`companies.lines.basis.${l.to_basis}`)}</span>
        </p>
    </li>
{/snippet}

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[{ href: '/companies', label: t('companies.back'), icon: 'bi-buildings' }]}
            crumbsLabel={t('companies.breadcrumbs')}
            plate={kindLabel}
            plateIcon="bi-building"
            {eyebrow}
            title={company.name}
            titleLang="fr"
            stats={heroStats}
        >
            <p class="summary">{company.summary}</p>
            <p class="computed">{t('companies.computed', { date: computedOn })}</p>
        </SheetHero>

        {#snippet heroStats()}
            <StatStrip items={statItems} label={t('companies.stats.title')} />
        {/snippet}

        <SheetToc items={tocItems} label={t('companies.toc.label')} />

        <SheetSection
            id="history"
            title={t('companies.history.title')}
            intro={t('companies.history.intro')}
        >
            <ol class="dates">
                {#each events as e (e.date + e.text)}
                    <li>
                        <span class="when">{formatDate(e.date, e.precision)}</span>
                        <span>{e.text}</span>
                    </li>
                {/each}
            </ol>
            {#if predecessors.length}
                <p class="neighbours">
                    {t('companies.history.before', {
                        names: listFormat.format(predecessors.map((c) => c.short)),
                    })}
                </p>
                <ul class="links">
                    {#each predecessors as c (c.slug)}
                        <li><a href="/companies/{c.slug}">{c.short}</a></li>
                    {/each}
                </ul>
            {/if}
            {#if successors.length}
                <p class="neighbours">
                    {t('companies.history.after', {
                        names: listFormat.format(successors.map((c) => c.short)),
                    })}
                </p>
                <ul class="links">
                    {#each successors as c (c.slug)}
                        <li><a href="/companies/{c.slug}">{c.short}</a></li>
                    {/each}
                </ul>
            {/if}
            <ul class="sources">
                {#each sources as s (s.url)}
                    <li>
                        <a href={s.url} rel="noopener" lang="fr"
                            >{s.label}{s.label === 'Wikidata' ? ` ${company.wikidata}` : ''}</a
                        >
                    </li>
                {/each}
            </ul>
        </SheetSection>

        <SheetSection
            id="series"
            title={t('companies.series.title')}
            intro={t('companies.series.intro')}
        >
            {#if hasSeries}
                <DepartmentCurve
                    rows={seriesRows}
                    {series}
                    max={seriesMax}
                    maxLabel={t('companies.series.y_max', { value: n(seriesMax) })}
                    caption={t('companies.series.caption', { first: firstYear })}
                    summary={seriesSummary}
                />
                <FiguresTable
                    summary={t('companies.series.table_toggle')}
                    caption={t('companies.series.caption', { first: firstYear })}
                    columns={[
                        { label: t('companies.series.col_date') },
                        { label: t('companies.series.col_lines'), num: true },
                        { label: t('companies.series.col_km'), num: true },
                    ]}
                    rows={decades
                        .filter((r) => r.year >= firstYear)
                        .map((r) => [stockLabel(r), n(r.lines), n(r.km)])}
                />
                <p class="note">{t('companies.series.definition')}</p>
            {:else}
                <EmptyState icon="bi-graph-up" title={t('companies.series.summary_none')} />
            {/if}
        </SheetSection>

        {#if map_info}
            <SheetSection
                id="map"
                title={t('companies.map.title')}
                intro={t('companies.map.intro', { year: map_info.year })}
            >
                <div bind:this={mapSection} class="map-slot">
                    {#if map}
                        <CompanyMap
                            {map}
                            label={t('companies.map.label', {
                                name: company.short,
                                year: map_info.year,
                                lines: map_info.lines,
                            })}
                            legend={t('companies.map.summary', {
                                lines: map_info.lines,
                                km: n(peak?.km ?? 0),
                                year: map_info.year,
                            })}
                        />
                    {:else}
                        <p class="note first" role="status">
                            {t('companies.map.summary', {
                                lines: map_info.lines,
                                km: n(peak?.km ?? 0),
                                year: map_info.year,
                            })}
                        </p>
                    {/if}
                </div>
            </SheetSection>
        {/if}

        {#if lines.length}
            <SheetSection
                id="lines"
                title={t('companies.lines.title')}
                count={lines_total}
                intro={t('companies.lines.intro')}
            >
                <ul class="lines-list">
                    {#each lines as l (l.slug + l.from_year)}{@render lineRow(l)}{/each}
                </ul>
                {#if otherCount > 0}
                    <details class="more" ontoggle={loadOtherLines}>
                        <summary
                            >{t('companies.lines.detail_open', {
                                count: n(otherCount),
                            })}</summary
                        >
                        <ul class="lines-list">
                            {#each otherLines as l (l.slug + l.from_year)}{@render lineRow(
                                    l,
                                )}{/each}
                        </ul>
                    </details>
                {/if}
            </SheetSection>
        {/if}

        <SheetSection id="method" title={t('companies.method.title')}>
            <p class="note first">{t('companies.method.text')}</p>
            {#if lines_total === 0}
                <p class="note">{t('companies.lines.none')}</p>
            {/if}
            <p class="note">
                <a href="/companies">{t('companies.method.index_link')}</a>
            </p>
        </SheetSection>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
        padding-bottom: var(--vff-section-space);
    }
    .summary,
    .computed,
    .note,
    .neighbours {
        max-width: var(--vff-measure);
        margin: var(--vff-space-4) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .summary {
        margin-top: var(--vff-space-3);
    }
    .computed {
        margin-top: 10px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .note {
        color: var(--vff-text-muted);
    }
    .note.first {
        margin-top: 0;
    }
    .dates {
        max-width: var(--vff-measure);
        margin: 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .dates li {
        display: grid;
        grid-template-columns: 11em minmax(0, 1fr);
        gap: var(--vff-space-1) var(--vff-space-4);
        padding: var(--vff-space-2) 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .when {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .neighbours {
        margin-bottom: var(--vff-space-1);
    }
    .links,
    .sources {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-1) var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
    }
    .links a,
    .sources a {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
    }
    .sources {
        margin-top: var(--vff-space-4);
        font-size: var(--vff-fs-sm);
    }
    .lines-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .line-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 18em);
        gap: 4px 20px;
        align-items: start;
        padding: var(--vff-space-3) 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .line-row p {
        margin: 0;
    }
    .line-id {
        display: grid;
        gap: 4px;
        min-width: 0;
    }
    .line-name {
        justify-self: start;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        line-height: 1.4;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .line-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 12px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .km {
        font-variant-numeric: tabular-nums;
    }
    .facts {
        display: grid;
        gap: 2px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .map-slot {
        min-height: 120px;
    }
    .muted {
        color: var(--vff-text-muted);
    }
    .more {
        margin-top: var(--vff-space-4);
    }
    .more summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        cursor: pointer;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
    }
    @media (max-width: 700px) {
        .dates li,
        .line-row {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
