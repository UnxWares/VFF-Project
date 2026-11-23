<script>
    /**
     * « Ce que sait l'encyclopédie » : la couverture des données des lignes, des gares, des accidents et du
     * matériel, en nombre et en pourcentage, avec la part qui vient de sources ouvertes, puis la précision par
     * époque. Les mesures viennent de CoverageFigures (cache, calculées par vff:compute-coverage-figures) ;
     * chaque graphique est doublé d'un tableau.
     */
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import NetworkChart from '$components/Network/NetworkChart.svelte';
    import FiguresTable from '$components/Network/FiguresTable.svelte';
    import CoverageMeter from '$components/Network/CoverageMeter.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber, formatLocale } from '$lib/typo.js';
    import { formatShare, metricOf, ratio } from '$lib/coverage.js';

    /** @type {{ coverage: { computed_at: string, lines: any, stations: any, accidents: any, materials: any, eras: Array<any> } }} */
    let { coverage } = $props();

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`coverage.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const unit = (u) => t(`units.${u}`);
    const pct = (share) => formatShare(share, lang) ?? '';
    const computedOn = $derived(
        new Date(coverage.computed_at).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );

    const TONES = { day: 'a', month: 'b', year: 'c', none: 'none' };

    /** Une mesure prête pour CoverageMeter. */
    function meterOf(section, m) {
        const base = `metrics.${section}.${m.key}`;
        const unitName = unit(m.unit);
        const props = {
            key: `${section}.${m.key}`,
            name: t(`${base}.name`),
            definition: t(`${base}.def`),
            unavailable: m.available ? null : t('unavailable'),
            shareText: pct(m.share),
            figure: t('figure', { done: n(m.done), total: n(m.total), unit: unitName }),
            share: m.share,
            openText:
                m.open === null
                    ? t('open_na')
                    : t('open_part', { count: n(m.open), share: pct(ratio(m.open, m.done)) }),
            levels: (m.levels ?? []).map((l) => ({
                label: l.level,
                text: `${t(`levels.${l.level}`)}${' '}: ${n(l.count)} (${pct(l.share)})`,
                share: l.share,
                tone: TONES[l.level] ?? 'a',
            })),
            levelsLabel: t('levels_label'),
        };

        return props;
    }

    const sections = $derived(
        ['lines', 'stations', 'accidents', 'materials'].map((key) => ({
            key,
            meters: coverage[key].metrics.map((m) => ({ m, props: meterOf(key, m) })),
        })),
    );

    function tableRows(section) {
        return section.meters.map(({ m, props }) => [
            props.name,
            m.available ? n(m.total) : '-',
            m.available ? n(m.done) : '-',
            m.available ? pct(m.share) : t('unavailable'),
            m.available && m.open !== null ? n(m.open) : '-',
        ]);
    }

    const tableColumns = $derived([
        { label: t('col_measure') },
        { label: t('col_total'), num: true },
        { label: t('col_done'), num: true },
        { label: t('col_share'), num: true },
        { label: t('col_open'), num: true },
    ]);

    // Les quatre chiffres de l'essentiel.
    const keyFigures = $derived(
        [
            { label: 'key_opening', m: metricOf(coverage.lines, 'opening_dated') },
            {
                label: 'key_trace',
                m: metricOf(coverage.lines, 'trace_precise'),
                detail: 'key_trace_detail',
            },
            { label: 'key_stations', m: metricOf(coverage.stations, 'opening_dated') },
            { label: 'key_photo', m: metricOf(coverage.stations, 'photo') },
            { label: 'key_reviewed', m: metricOf(coverage.accidents, 'reviewed') },
        ].filter((k) => k.m !== null && k.m.available),
    );

    // Par époque.
    const eraLabel = (r) => (r.current ? t('today') : String(r.year));
    const eras = $derived(coverage.eras.filter((r) => r.lines > 0 || r.trace_km > 0));
    const eraChart = (total, precise, sr, fmt) =>
        eras
            .filter((r) => r[total] > 0)
            .map((r) => ({
                key: r.year,
                label: eraLabel(r),
                values: [r[precise], r[total] - r[precise]],
                value: `${pct(ratio(r[precise], r[total]))}`,
                sr: t(sr, { label: eraLabel(r), precise: fmt(r[precise]), total: fmt(r[total]) }),
            }));
    const eraSeries = $derived([
        { label: t('s_precise'), tone: 'a' },
        { label: t('s_other'), tone: 'c' },
    ]);
    const eraLines = $derived(eraChart('lines', 'lines_precise_opening', 'era_lines_sr', n));
    const eraTrace = $derived(eraChart('trace_km', 'trace_precise_km', 'era_trace_sr', n));
    const eraStations = $derived(
        eraChart('stations', 'stations_precise_opening', 'era_stations_sr', n),
    );

    // Accidents et matériel par décennie.
    const accSeries = $derived([
        { label: t('s_reviewed'), tone: 'a' },
        { label: t('s_to_review'), tone: 'd' },
    ]);
    const accRows = $derived(
        coverage.accidents.by_decade.map((d) => ({
            key: d.decade,
            label: String(d.decade),
            values: [d.reviewed, d.total - d.reviewed],
            value: `${n(d.reviewed)} / ${n(d.total)}`,
            sr: t('figure_sr', {
                name: String(d.decade),
                share: pct(ratio(d.reviewed, d.total)),
                done: n(d.reviewed),
                total: n(d.total),
                unit: unit('accidents'),
            }),
        })),
    );
    const matSeries = $derived([{ label: t('s_series'), tone: 'b' }]);
    const matRows = $derived(
        coverage.materials.by_decade.map((d) => ({
            key: d.decade,
            label: String(d.decade),
            values: [d.total],
            value: n(d.total),
            sr: `${d.decade} : ${n(d.total)} ${unit('series')}`,
        })),
    );
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/network/coverage" />
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

        <SectionBlock title={t('key_title')} maxWidth="1180px">
            <dl class="key">
                {#each keyFigures as { label, m, detail } (label)}
                    <div>
                        <dt>{t(label)}</dt>
                        <dd>{pct(m.share)}</dd>
                        <dd class="detail">
                            {t('figure', {
                                done: n(m.done),
                                total: n(m.total),
                                unit: unit(m.unit),
                            })}
                        </dd>
                        {#if detail}
                            <dd class="detail">{t(detail)}</dd>
                        {/if}
                    </div>
                {/each}
            </dl>
            <p class="reading">{t('reading')}</p>
        </SectionBlock>

        {#each sections as section, i (section.key)}
            <SectionBlock
                kind={i % 2 === 0 ? 'alt' : 'default'}
                title={t(`${section.key}_title`)}
                intro={t(`${section.key}_intro`)}
                maxWidth="1180px"
            >
                <ul class="meters">
                    {#each section.meters as { props } (props.key)}
                        <CoverageMeter {...props} />
                    {/each}
                </ul>
                <FiguresTable
                    summary={t('table_toggle')}
                    caption={t(`${section.key}_table`)}
                    columns={tableColumns}
                    rows={tableRows(section)}
                />

                {#if section.key === 'accidents' && accRows.length > 0}
                    <div class="chart">
                        <NetworkChart
                            rows={accRows}
                            series={accSeries}
                            caption={t('accidents_by_decade')}
                        />
                        <FiguresTable
                            summary={t('table_toggle')}
                            caption={t('accidents_by_decade_table')}
                            columns={[
                                { label: t('col_decade') },
                                { label: t('col_reviewed'), num: true },
                                { label: t('col_to_review'), num: true },
                            ]}
                            rows={coverage.accidents.by_decade.map((d) => [
                                String(d.decade),
                                n(d.reviewed),
                                n(d.total - d.reviewed),
                            ])}
                        />
                    </div>
                {/if}
                {#if section.key === 'materials' && matRows.length > 0}
                    <div class="chart">
                        <NetworkChart
                            rows={matRows}
                            series={matSeries}
                            caption={t('materials_by_decade')}
                            reserve={6}
                        />
                        <FiguresTable
                            summary={t('table_toggle')}
                            caption={t('materials_by_decade_table')}
                            columns={[
                                { label: t('col_decade') },
                                { label: t('s_series'), num: true },
                            ]}
                            rows={coverage.materials.by_decade.map((d) => [
                                String(d.decade),
                                n(d.total),
                            ])}
                        />
                    </div>
                {/if}
            </SectionBlock>
        {/each}

        <SectionBlock kind="alt" title={t('eras_title')} intro={t('eras_intro')} maxWidth="1180px">
            <NetworkChart
                rows={eraLines}
                series={eraSeries}
                caption={t('eras_lines')}
                reserve={6}
            />
            <div class="chart">
                <NetworkChart
                    rows={eraTrace}
                    series={eraSeries}
                    caption={t('eras_trace')}
                    reserve={6}
                />
            </div>
            <div class="chart">
                <NetworkChart
                    rows={eraStations}
                    series={eraSeries}
                    caption={t('eras_stations')}
                    reserve={6}
                />
            </div>
            <FiguresTable
                summary={t('table_toggle')}
                caption={t('eras_table')}
                columns={[
                    { label: t('col_era') },
                    { label: t('col_lines'), num: true },
                    { label: t('col_lines_precise'), num: true },
                    { label: t('col_trace'), num: true },
                    { label: t('col_trace_precise'), num: true },
                    { label: t('col_stations'), num: true },
                    { label: t('col_stations_precise'), num: true },
                ]}
                rows={eras.map((r) => [
                    eraLabel(r),
                    n(r.lines),
                    n(r.lines_precise_opening),
                    n(r.trace_km),
                    n(r.trace_precise_km),
                    n(r.stations),
                    n(r.stations_precise_opening),
                ])}
            />
        </SectionBlock>

        <SectionBlock title={t('method_title')} maxWidth="1180px">
            <p class="note">{t('method_text')}</p>
            <p class="note">{t('method_trace')}</p>
            <p class="note">
                <Link href="/contribute" class="vff-link">{t('contribute_link')}</Link>
            </p>
            <p class="note">
                {t('api_text')}
                <a href="/api/v1/stats/coverage" class="vff-link">/api/v1/stats/coverage</a>
            </p>
            <p class="note">
                <Link href="/network" class="vff-link">{t('back')}</Link>
            </p>
        </SectionBlock>
    </main>
</Layout>

<style>
    .key {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
        gap: var(--vff-space-5) var(--vff-space-6);
        margin: 0;
        padding: 0;
    }
    .key > div {
        min-width: 0;
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border-strong);
    }
    .key dt {
        margin-bottom: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.4;
        color: var(--vff-text-muted);
    }
    .key dd {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-figure);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
        color: var(--vff-text);
    }
    .key dd.detail {
        margin-top: 4px;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        font-weight: 400;
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .meters {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .chart {
        margin-top: var(--vff-space-6);
    }
    .reading,
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
</style>
