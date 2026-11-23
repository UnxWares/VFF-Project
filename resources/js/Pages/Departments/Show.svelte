<script>
    /**
     * Fiche « Le rail dans <département> » : lignes et gares décennie par décennie (courbe doublée d'un tableau),
     * grandes dates, carte, lignes, communes ferroviaires et accidents. Les chiffres viennent de DepartmentFigures
     * (cache, calculés par vff:compute-department-figures) ; la page ne calcule rien.
     */
    import { formatStationDate } from '$lib/stationDates.js';
    import { formatNumber, formatLocale } from '$lib/typo.js';
    import { niceMax } from '$lib/departmentSheet.js';
    import { percentOf } from '$lib/networkFigures.js';
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import SheetToc from '$components/Sheet/SheetToc.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import CommuneMap from '$components/Communes/CommuneMap.svelte';
    import DepartmentCurve from '$components/Departments/DepartmentCurve.svelte';
    import FiguresTable from '$components/Network/FiguresTable.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { breadcrumbSchema } from '$lib/structuredData.js';
    import { i18n } from '$lib/i18n.js';

    /** Props de DepartmentController::show (App\Services\Departments\DepartmentFigures). */
    let {
        department,
        computed_at,
        reference_year,
        current_year,
        closed_share,
        decades = [],
        milestones,
        lines = [],
        communes = [],
        accidents,
        map,
    } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const n = (v) => formatNumber(v, lang);
    const km = (v) => t('departments.km', { value: n(v) });
    const NBSP = '\u00A0';
    /** Espace insécable entre un nombre et son unité (« 5,2 km »). */
    const nb = (s) => s.replace(/(\d) (?=km(?![\p{L}]))/gu, `$1${NBSP}`);
    const formatDate = (d) => formatStationDate(d.date, d.precision, null, t, lang);

    const title = $derived(t('departments.title', { name: department.name }));
    const description = $derived(
        t('departments.meta', {
            name: department.name,
            code: department.code,
            region: department.region,
        }),
    );
    const computedOn = $derived(
        new Date(computed_at).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );
    const eyebrow = $derived([department.region, t('departments.code', { code: department.code })]);

    const stockLabel = (r) => (r.current ? t('departments.today') : String(r.year));
    const today = $derived(decades.find((r) => r.current) ?? decades[decades.length - 1]);
    const reference = $derived(decades.find((r) => r.year === reference_year));
    const peak = $derived(
        decades.reduce((best, r) => (best === null || r.open_km > best.open_km ? r : best), null),
    );
    const stationsOpen = (r) => r.stations.active + r.stations.tourist + r.stations.no_commercial;
    const stationsPeak = $derived(
        decades.reduce(
            (best, r) => (best === null || stationsOpen(r) > stationsOpen(best) ? r : best),
            null,
        ),
    );
    const firstYear = $derived(decades[0]?.year ?? 1830);
    const hasKm = $derived((peak?.open_km ?? 0) > 0);

    const share = $derived(percentOf(closed_share));
    const statItems = $derived([
        { value: km(today?.open_km ?? 0), label: t('departments.stats.km_today') },
        {
            value: reference ? km(reference.open_km) : t('departments.stats.none'),
            label: t('departments.stats.km_reference', { year: reference_year }),
        },
        {
            value: share === null ? t('departments.stats.none') : `${share}${NBSP}%`,
            label: t('departments.stats.closed_share', { year: reference_year }),
        },
        {
            value: n(today ? stationsOpen(today) : 0),
            label: t('departments.stats.stations_today'),
        },
    ]);

    // 1. Lignes par décennie.
    const seriesMax = $derived(
        niceMax(
            Math.max(
                ...decades.map((r) =>
                    Math.max(r.open_km, r.closed_km, r.dismantled_km + r.razed_km),
                ),
                0,
            ),
        ),
    );
    const lineRows = $derived(
        decades.map((r) => ({
            year: r.year,
            label: stockLabel(r),
            values: [r.open_km, r.closed_km, r.dismantled_km + r.razed_km],
        })),
    );
    const lineSeries = $derived([
        { label: t('departments.series.s_open'), tone: 'a', end: km(today?.open_km ?? 0) },
        { label: t('departments.series.s_closed'), tone: 'd', end: km(today?.closed_km ?? 0) },
        {
            label: t('departments.series.s_gone'),
            tone: 'e',
            end: km((today?.dismantled_km ?? 0) + (today?.razed_km ?? 0)),
        },
    ]);
    const lineSummary = $derived(
        hasKm
            ? t('departments.series.summary', {
                  km: n(peak.open_km),
                  year: peak.year,
                  today: n(today?.open_km ?? 0),
              })
            : t('departments.series.summary_none'),
    );

    // 2. Gares par décennie.
    const closedStations = (r) => r.stations.declassified;
    const stationsMax = $derived(
        niceMax(Math.max(...decades.map((r) => Math.max(stationsOpen(r), closedStations(r))), 0)),
    );
    const stationRows = $derived(
        decades.map((r) => ({
            year: r.year,
            label: stockLabel(r),
            values: [stationsOpen(r), closedStations(r)],
        })),
    );
    const stationSeries = $derived([
        {
            label: t('departments.stations.s_open'),
            tone: 'a',
            end: n(today ? stationsOpen(today) : 0),
        },
        {
            label: t('departments.stations.s_closed'),
            tone: 'd',
            end: n(today ? closedStations(today) : 0),
        },
    ]);
    const stationSummary = $derived(
        today && stationsPeak && stationsOpen(stationsPeak) > 0
            ? t('departments.stations.summary', {
                  open: n(stationsOpen(today)),
                  peak: n(stationsOpen(stationsPeak)),
                  year: stationsPeak.year,
              })
            : t('departments.stations.summary_none'),
    );

    // 3. Grandes dates.
    const hasDates = $derived(
        Boolean(
            milestones.first_opening ||
            milestones.peak ||
            milestones.closure_waves.length ||
            milestones.reopenings.length,
        ),
    );
    /** Repère mis à la place de la ligne dans la phrase traduite, pour y glisser un lien. */
    const MARK = '§';
    /** Phrase coupée autour de la ligne : texte avant, texte après (le lien s'écrit entre les deux). */
    function around(key, vars) {
        const [before, after = ''] = t(key, { ...vars, line: MARK }).split(MARK);

        return { before, after };
    }
    const listFormat = $derived(new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }));
    function waveText(w) {
        return t(w.lines_count === 1 ? 'departments.dates.wave_one' : 'departments.dates.wave', {
            km: n(w.removed_km),
            from: w.from_year,
            to: w.year,
            count: w.lines_count,
            lines: listFormat.format(w.lines.map((l) => l.name)),
        });
    }

    // 4. Lignes.
    function lineFacts(l) {
        const facts = [];
        if (l.opened) facts.push(t('departments.lines.opened', { date: formatDate(l.opened) }));
        if (l.closed) {
            const key = l.closed.scope === 'all' ? 'closed' : `closed_${l.closed.scope}`;
            facts.push(t(`departments.lines.${key}`, { date: formatDate(l.closed) }));
        }
        if (l.electrified)
            facts.push(t('departments.lines.electrified', { date: formatDate(l.electrified) }));

        return facts;
    }
    const lineLength = (l) =>
        nb(
            t(l.km_year === current_year ? 'departments.lines.km' : 'departments.lines.km_past', {
                km: formatNumber(l.km, lang, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                }),
                year: l.km_year,
            }),
        );

    // 5. Communes.
    const COMMUNES_SHOWN = 24;
    const shownCommunes = $derived(communes.slice(0, COMMUNES_SHOWN));
    const otherCommunes = $derived(communes.slice(COMMUNES_SHOWN));
    const communeNote = (c) =>
        c.stations > 0
            ? t('departments.communes.stations', { count: c.stations, open: c.stations_open })
            : t('departments.communes.no_station');

    // 6. Accidents.
    const deathsText = (a) =>
        a.deaths === null || a.deaths === undefined
            ? t('departments.accidents.deaths_unknown')
            : a.deaths === 1
              ? t('departments.accidents.deaths_one')
              : t('departments.accidents.deaths', { count: n(a.deaths) });

    const TOC = ['series', 'stations', 'dates', 'map', 'communes', 'accidents'];
    const tocItems = $derived(
        TOC.filter(
            (id) =>
                (id !== 'dates' || hasDates) &&
                (id !== 'communes' || communes.length) &&
                (id !== 'accidents' || accidents.count),
        ).map((id) => ({ id, label: t(`departments.toc.${id}`) })),
    );
    const openLines = $derived(lines.filter((l) => l.state === 'open').length);
    const LINES_SHOWN = 30;
    const shownLines = $derived(lines.slice(0, LINES_SHOWN));
    const otherLines = $derived(lines.slice(LINES_SHOWN));
</script>

<svelte:head>
    <title>{title} - VFF</title>
</svelte:head>

<SeoHead
    {title}
    {description}
    path={`/departments/${department.slug}`}
    type="article"
    jsonLd={(origin) => [
        breadcrumbSchema(origin, [
            { name: $i18n.t('common.app_full'), path: '/' },
            { name: t('departments.back'), path: '/departments' },
            { name: department.name, path: `/departments/${department.slug}` },
        ]),
    ]}
/>

{#snippet lineRow(l)}
    {@const facts = lineFacts(l)}
    <li class="line-row">
        <div class="line-id">
            <a class="line-name" href="/lines/{l.slug}">{l.name}</a>
            <p class="line-meta">
                {#if l.code}<span class="vff-plate">{l.code}</span>{/if}
                {#if l.is_lgv}<span class="vff-badge muted">{t('departments.lines.lgv')}</span>{/if}
                <span class="km">{lineLength(l)}</span>
            </p>
        </div>
        <p class={['state', l.state]}>
            {t(`departments.lines.state.${l.state}`)}
            {#if l.track}<span class="track">{t(`departments.lines.track.${l.track}`)}</span>{/if}
        </p>
        {#if facts.length}
            <p class="facts">
                {#each facts as f, i (i)}<span>{f}</span>{/each}
            </p>
        {/if}
    </li>
{/snippet}

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                { href: '/departments', label: t('departments.back'), icon: 'bi-grid-3x3-gap' },
            ]}
            crumbsLabel={t('departments.breadcrumbs')}
            plate={t('departments.plate')}
            plateIcon="bi-geo-alt-fill"
            {eyebrow}
            title={department.name}
            stats={heroStats}
        >
            <p class="computed">{t('departments.computed', { date: computedOn })}</p>
        </SheetHero>

        {#snippet heroStats()}
            <StatStrip items={statItems} label={t('departments.stats.title')} />
        {/snippet}

        <SheetToc items={tocItems} label={t('departments.toc.label')} />

        <SheetSection
            id="series"
            title={t('departments.series.title')}
            intro={t('departments.series.intro')}
        >
            <DepartmentCurve
                rows={lineRows}
                series={lineSeries}
                max={seriesMax}
                maxLabel={t('departments.series.y_max', { value: n(seriesMax) })}
                caption={t('departments.series.caption', {
                    name: department.name,
                    first: firstYear,
                })}
                summary={lineSummary}
            />
            <FiguresTable
                summary={t('departments.table_toggle')}
                caption={t('departments.series.caption', {
                    name: department.name,
                    first: firstYear,
                })}
                columns={[
                    { label: t('departments.col_date') },
                    { label: t('departments.col_open'), num: true },
                    { label: t('departments.col_closed'), num: true },
                    { label: t('departments.col_service'), num: true },
                    { label: t('departments.col_dismantled'), num: true },
                    { label: t('departments.col_razed'), num: true },
                ]}
                rows={decades.map((r) => [
                    stockLabel(r),
                    n(r.open_km),
                    n(r.closed_km),
                    n(r.service_km),
                    n(r.dismantled_km),
                    n(r.razed_km),
                ])}
            />
            <p class="note">{t('departments.series.definition')}</p>
        </SheetSection>

        <SheetSection
            id="stations"
            title={t('departments.stations.title')}
            intro={t('departments.stations.intro')}
        >
            <DepartmentCurve
                rows={stationRows}
                series={stationSeries}
                max={stationsMax}
                maxLabel={n(stationsMax)}
                caption={t('departments.stations.caption', {
                    name: department.name,
                    first: firstYear,
                })}
                summary={stationSummary}
            />
            <FiguresTable
                summary={t('departments.table_toggle')}
                caption={t('departments.stations.caption', {
                    name: department.name,
                    first: firstYear,
                })}
                columns={[
                    { label: t('departments.col_date') },
                    { label: t('departments.col_stations_open'), num: true },
                    { label: t('departments.col_stations_tourist'), num: true },
                    { label: t('departments.col_stations_no_commercial'), num: true },
                    { label: t('departments.col_stations_declassified'), num: true },
                ]}
                rows={decades.map((r) => [
                    stockLabel(r),
                    n(r.stations.active),
                    n(r.stations.tourist),
                    n(r.stations.no_commercial),
                    n(r.stations.declassified),
                ])}
            />
            <p class="note">{t('departments.stations.definition')}</p>
        </SheetSection>

        {#if hasDates}
            <SheetSection
                id="dates"
                title={t('departments.dates.title')}
                intro={t('departments.dates.intro')}
            >
                <ul class="dates">
                    {#if milestones.first_opening}
                        {@const f = around('departments.dates.first_opening', {
                            date: formatDate(milestones.first_opening),
                        })}
                        <li>
                            {f.before}{#if milestones.first_opening.line}<a
                                    href="/lines/{milestones.first_opening.line.slug}"
                                    >{milestones.first_opening.line.name}</a
                                >{/if}{f.after}
                        </li>
                    {/if}
                    {#if milestones.peak}
                        <li>
                            {t('departments.dates.peak', {
                                km: n(milestones.peak.km),
                                year: milestones.peak.year,
                            })}
                        </li>
                    {/if}
                </ul>
                {#if milestones.closure_waves.length}
                    <h3>{t('departments.dates.waves_title')}</h3>
                    <ul class="dates">
                        {#each milestones.closure_waves as w (w.year)}
                            <li>{waveText(w)}</li>
                        {/each}
                    </ul>
                    <p class="note">{t('departments.dates.waves_note')}</p>
                {/if}
                {#if milestones.reopenings.length}
                    <h3>{t('departments.dates.reopenings_title')}</h3>
                    <ul class="dates">
                        {#each milestones.reopenings as e, i (i)}
                            <li>
                                {#if e.line}
                                    {@const r = around('departments.dates.reopening', {
                                        date: formatDate(e),
                                    })}
                                    {r.before}<a href="/lines/{e.line.slug}">{e.line.name}</a
                                    >{r.after}
                                {:else}
                                    {t('departments.dates.reopening_unknown_line', {
                                        date: formatDate(e),
                                    })}
                                {/if}
                            </li>
                        {/each}
                    </ul>
                {/if}
            </SheetSection>
        {/if}

        <SheetSection
            id="map"
            title={t('departments.lines.title')}
            count={lines.length}
            intro={t('departments.lines.intro')}
        >
            <div class="map-lines">
                <div class="map-col">
                    <div class="map-frame">
                        <CommuneMap {map} name={department.name} />
                    </div>
                    <p class="map-note">
                        {t('departments.map.summary', {
                            stations: map.stations.length,
                            lines: lines.length,
                        })}
                    </p>
                    <ul class="legend" aria-label={t('departments.map.legend')}>
                        <li>
                            <span class="swatch open" aria-hidden="true"></span>{t(
                                'departments.map.legend_open',
                            )}
                        </li>
                        <li>
                            <span class="swatch closed" aria-hidden="true"></span>{t(
                                'departments.map.legend_closed',
                            )}
                        </li>
                        <li>
                            <span class="swatch station" aria-hidden="true"></span>{t(
                                'departments.map.legend_station',
                            )}
                        </li>
                    </ul>
                </div>
                <div class="lines-col">
                    {#if lines.length}
                        <p class="lines-count">
                            {t('departments.lines.count', {
                                open: n(openLines),
                                total: n(lines.length),
                            })}
                        </p>
                        <ul class="lines-list">
                            {#each shownLines as l (l.slug)}
                                {@render lineRow(l)}
                            {/each}
                        </ul>
                        {#if otherLines.length}
                            <details class="more">
                                <summary
                                    >{t('departments.lines.more', {
                                        count: n(otherLines.length),
                                    })}</summary
                                >
                                <ul class="lines-list">
                                    {#each otherLines as l (l.slug)}
                                        {@render lineRow(l)}
                                    {/each}
                                </ul>
                            </details>
                        {/if}
                    {:else}
                        <EmptyState icon="bi-bezier2" title={t('departments.lines.none')} />
                    {/if}
                </div>
            </div>
        </SheetSection>

        {#if communes.length}
            <SheetSection
                id="communes"
                title={t('departments.communes.title')}
                count={communes.length}
                intro={t('departments.communes.intro')}
            >
                <ul class="communes">
                    {#each shownCommunes as c (c.insee)}
                        <li>
                            <a href="/communes/{c.slug}">{c.name}</a>
                            <span class="muted">{communeNote(c)}</span>
                        </li>
                    {/each}
                </ul>
                {#if otherCommunes.length}
                    <details class="more">
                        <summary
                            >{t('departments.communes.more', {
                                count: n(otherCommunes.length),
                            })}</summary
                        >
                        <ul class="communes">
                            {#each otherCommunes as c (c.insee)}
                                <li>
                                    <a href="/communes/{c.slug}">{c.name}</a>
                                    <span class="muted">{communeNote(c)}</span>
                                </li>
                            {/each}
                        </ul>
                    </details>
                {/if}
            </SheetSection>
        {/if}

        {#if accidents.count}
            <SheetSection
                id="accidents"
                title={t('departments.accidents.title')}
                intro={t('departments.accidents.intro')}
            >
                <p class="note first">
                    {t(
                        accidents.count === 1
                            ? 'departments.accidents.summary_one'
                            : 'departments.accidents.summary',
                        {
                            count: n(accidents.count),
                            deaths: n(accidents.deaths),
                        },
                    )}
                </p>
                <ul class="accident-list">
                    {#each accidents.list as a (a.slug)}
                        <li>
                            <a href="/accidents/{a.slug}">{a.name}</a>
                            <span class="muted"
                                >{formatStationDate(
                                    a.date,
                                    a.date_precision,
                                    null,
                                    t,
                                    lang,
                                )}{a.commune ? `, ${a.commune}` : ''}, {deathsText(a)}</span
                            >
                        </li>
                    {/each}
                </ul>
                <p class="note">
                    <a class="vff-link" href="/accidents?dept={department.code}"
                        >{t('departments.accidents.all')}</a
                    >
                </p>
            </SheetSection>
        {/if}

        <SheetSection id="method" title={t('departments.definitions.title')}>
            <p class="note first">{t('departments.definitions.text')}</p>
        </SheetSection>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
        padding-bottom: var(--vff-section-space);
    }
    .computed,
    .note,
    .map-note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-4) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .computed {
        margin-top: 10px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .note.first {
        margin-top: 0;
    }
    h3 {
        margin: var(--vff-space-6) 0 var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.3;
    }
    .dates,
    .accident-list {
        max-width: var(--vff-measure);
        margin: 0;
        padding-left: 1.2em;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .dates li,
    .accident-list li {
        margin-bottom: var(--vff-space-2);
    }
    .muted {
        color: var(--vff-text-muted);
    }
    .accident-list .muted {
        display: block;
        font-size: var(--vff-fs-sm);
    }

    /* Carte à gauche, lignes à droite : l'essentiel se voit sans défiler. */
    .map-lines {
        display: grid;
        grid-template-columns: minmax(0, 6fr) minmax(0, 6fr);
        gap: var(--vff-space-5) var(--vff-space-6);
        align-items: start;
    }
    .map-col {
        position: sticky;
        top: var(--vff-space-5);
    }
    .map-frame {
        --map-h: 520px;
        height: var(--map-h);
    }
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 20px;
        margin: var(--vff-space-2) 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .swatch {
        display: inline-block;
        flex-shrink: 0;
        width: 28px;
        height: 0;
        border-top: 4px solid #ff6500;
        border-radius: 2px;
    }
    .swatch.closed {
        border-top: 3px dashed #78716c;
    }
    .swatch.station {
        width: 14px;
        height: 14px;
        box-sizing: border-box;
        border: 3px solid #ff6500;
        border-radius: 50%;
        background-color: #fff;
    }

    .lines-count {
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .lines-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .line-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
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
    .state {
        display: grid;
        gap: 2px;
        text-align: right;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-text);
    }
    .state.closed {
        color: var(--vff-text-muted);
    }
    .track {
        font-size: var(--vff-fs-sm);
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .facts {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        gap: 2px 16px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text);
    }

    .communes {
        columns: 260px;
        column-gap: var(--vff-space-6);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .communes li {
        break-inside: avoid;
        display: grid;
        padding: var(--vff-space-2) 0;
        font-family: var(--vff-font-heading);
    }
    .communes a {
        justify-self: start;
        min-height: 24px;
        font-size: var(--vff-fs-md);
        font-weight: 600;
    }
    .communes .muted {
        font-size: var(--vff-fs-sm);
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

    @media (max-width: 900px) {
        .map-lines {
            grid-template-columns: minmax(0, 1fr);
        }
        .map-col {
            position: static;
        }
        .map-frame {
            --map-h: 380px;
        }
    }
    @media (max-width: 600px) {
        .map-frame {
            --map-h: 320px;
        }
        .line-row {
            grid-template-columns: minmax(0, 1fr);
        }
        .state {
            text-align: left;
        }
    }
</style>
