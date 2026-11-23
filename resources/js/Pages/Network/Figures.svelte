<script>
    /**
     * « Le réseau en chiffres » : longueurs ouvertes, fermées et déposées, grandes vagues de fermeture,
     * gares, électrification, voies et accidents, décennie par décennie. Les agrégats viennent de
     * NetworkFigures (cache, calculés par vff:compute-network-figures). Chaque graphique est doublé d'un
     * tableau ; chaque chiffre a sa définition, sa source et la date du calcul.
     */
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import NetworkChart from '$components/Network/NetworkChart.svelte';
    import FiguresTable from '$components/Network/FiguresTable.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber, formatLocale } from '$lib/typo.js';
    import { peakOf, percentOf, periodOf } from '$lib/networkFigures.js';

    /** @type {{ figures: { computed_at: string, decades: Array<any>, tracks: any, waves: any } }} */
    let { figures } = $props();

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`network.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const km = (v) => t('km', { value: n(v) });
    const decades = $derived(figures.decades);
    const today = $derived(decades.find((r) => r.current) ?? decades[decades.length - 1]);
    const computedOn = $derived(
        new Date(figures.computed_at).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    );

    const stockLabel = (r) => (r.current ? t('today') : String(r.year));
    function periodLabel(r) {
        const p = periodOf(r);
        if (p.kind === 'until') return t('period_until', { to: p.to });
        if (p.kind === 'since') return t('period_since', { from: p.from });

        return `${p.from}-${p.to}`;
    }

    const peakKm = $derived(peakOf(decades, (r) => r.open_km));
    const peakStations = $derived(peakOf(decades, (r) => r.stations.active));
    const withTraffic = $derived(decades.filter((r) => r.open_km > 0));

    // 1. Longueur ouverte, par trafic.
    const openSeries = $derived([
        { label: t('s_passenger'), tone: 'a' },
        { label: t('s_freight'), tone: 'b' },
        { label: t('s_unknown'), tone: 'c' },
    ]);
    const openRows = $derived(
        withTraffic.map((r) => ({
            key: r.year,
            label: stockLabel(r),
            values: [r.open_passenger_km, r.open_freight_km, r.open_unknown_km],
            value: km(r.open_km),
            sr: t('open_sr', {
                label: stockLabel(r),
                total: km(r.open_km),
                passenger: km(r.open_passenger_km),
                freight: km(r.open_freight_km),
                unknown: km(r.open_unknown_km),
            }),
        })),
    );

    // 2. Fermé et déposé.
    const goneSeries = $derived([
        { label: t('s_closed'), tone: 'd' },
        { label: t('s_gone'), tone: 'e' },
    ]);
    const goneRows = $derived(
        decades.map((r) => ({
            key: r.year,
            label: stockLabel(r),
            values: [r.closed_km, r.gone_km],
            value: km(r.closed_km + r.gone_km),
            sr: t('gone_sr', {
                label: stockLabel(r),
                closed: km(r.closed_km),
                gone: km(r.gone_km),
            }),
        })),
    );

    // 3. Gares ouvertes aux voyageurs.
    const stationSeries = $derived([{ label: t('s_stations'), tone: 'a' }]);
    const stationRows = $derived(
        decades.map((r) => ({
            key: r.year,
            label: stockLabel(r),
            values: [r.stations.active],
            value: n(r.stations.active),
            sr: t('stations_sr', { label: stockLabel(r), count: n(r.stations.active) }),
        })),
    );

    // 4. Électrification.
    const elecSeries = $derived([{ label: t('s_electrified'), tone: 'b' }]);
    const elecRows = $derived(
        withTraffic.map((r) => {
            const share = percentOf(r.electrified_share);
            return {
                key: r.year,
                label: stockLabel(r),
                values: [r.electrified_km],
                value: t('elec_value', { km: km(r.electrified_km), share: share ?? 0 }),
                sr: t('elec_sr', {
                    label: stockLabel(r),
                    km: km(r.electrified_km),
                    share: share ?? 0,
                }),
            };
        }),
    );

    // 5. Accidents.
    const accidentSeries = $derived([{ label: t('s_accidents'), tone: 'd' }]);
    const accidentRows = $derived(
        decades.map((r) => ({
            key: r.year,
            label: periodLabel(r),
            values: [r.accidents.count],
            value: t('accident_value', {
                count: r.accidents.count,
                value: n(r.accidents.count),
                deaths: n(r.accidents.deaths),
            }),
            sr: t('accident_sr', {
                label: periodLabel(r),
                count: r.accidents.count,
                value: n(r.accidents.count),
                deaths: n(r.accidents.deaths),
            }),
        })),
    );

    const tracks = $derived(figures.tracks);
    const defs = [
        'open',
        'traffic',
        'closed',
        'removed',
        'events',
        'stations',
        'electrified',
        'tracks',
        'accidents',
    ];
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/network" />
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
                <div>
                    <dt>{t('key_open')}</dt>
                    <dd>{km(today.open_km)}</dd>
                </div>
                <div>
                    <dt>{t('key_peak')}</dt>
                    <dd>{peakKm ? km(peakKm.open_km) : ''}</dd>
                    <dd class="detail">
                        {peakKm ? t('key_peak_detail', { year: peakKm.year }) : ''}
                    </dd>
                </div>
                <div>
                    <dt>{t('key_electrified')}</dt>
                    <dd>{percentOf(today.electrified_share) ?? 0} %</dd>
                    <dd class="detail">{t('key_electrified_detail')}</dd>
                </div>
                <div>
                    <dt>{t('key_stations')}</dt>
                    <dd>{n(today.stations.active)}</dd>
                    <dd class="detail">
                        {peakStations
                            ? t('key_stations_detail', {
                                  count: n(peakStations.stations.active),
                                  year: peakStations.year,
                              })
                            : ''}
                    </dd>
                </div>
            </dl>
            <p class="reading">{t('reading')}</p>
        </SectionBlock>

        <SectionBlock kind="alt" title={t('open_title')} intro={t('open_intro')} maxWidth="1180px">
            <NetworkChart rows={openRows} series={openSeries} caption={t('open_caption')} />
            <FiguresTable
                summary={t('table_toggle')}
                caption={t('open_table')}
                columns={[
                    { label: t('col_date') },
                    { label: t('s_passenger'), num: true },
                    { label: t('s_freight'), num: true },
                    { label: t('s_unknown'), num: true },
                    { label: t('col_total'), num: true },
                ]}
                rows={withTraffic.map((r) => [
                    stockLabel(r),
                    n(r.open_passenger_km),
                    n(r.open_freight_km),
                    n(r.open_unknown_km),
                    n(r.open_km),
                ])}
            />
            <p class="note">{t('open_note')}</p>
        </SectionBlock>

        <SectionBlock title={t('gone_title')} intro={t('gone_intro')} maxWidth="1180px">
            <NetworkChart rows={goneRows} series={goneSeries} caption={t('gone_caption')} />
            <FiguresTable
                summary={t('table_toggle')}
                caption={t('gone_table')}
                columns={[
                    { label: t('col_date') },
                    { label: t('s_closed'), num: true },
                    { label: t('s_gone'), num: true },
                ]}
                rows={decades.map((r) => [stockLabel(r), n(r.closed_km), n(r.gone_km)])}
            />
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={t('waves_title')}
            intro={t('waves_intro')}
            maxWidth="1180px"
        >
            <ul class="waves">
                {#each figures.waves.closures as w (w.year)}
                    <li>
                        {t('wave_closure', {
                            period: periodLabel(decades.find((r) => r.year === w.year)),
                            km: km(w.removed_km),
                            events: n(w.closed_events),
                        })}
                    </li>
                {/each}
                {#each figures.waves.reopenings as w (w.year)}
                    <li>
                        {t('wave_reopening', {
                            period: periodLabel(decades.find((r) => r.year === w.year)),
                            count: w.reopened_events,
                            value: n(w.reopened_events),
                        })}
                    </li>
                {/each}
            </ul>
            <FiguresTable
                summary={t('table_toggle')}
                caption={t('waves_table')}
                columns={[
                    { label: t('col_period') },
                    { label: t('col_removed'), num: true },
                    { label: t('col_added'), num: true },
                    { label: t('col_closed'), num: true },
                    { label: t('col_closed_passenger'), num: true },
                    { label: t('col_closed_freight'), num: true },
                    { label: t('col_dismantled'), num: true },
                    { label: t('col_reopened'), num: true },
                    { label: t('col_opened'), num: true },
                ]}
                rows={decades.map((r) => [
                    periodLabel(r),
                    n(r.removed_km),
                    n(r.added_km),
                    n(r.events.closed),
                    n(r.events.closed_passenger),
                    n(r.events.closed_freight),
                    n(r.events.dismantled),
                    n(r.events.reopened),
                    n(r.events.opened),
                ])}
            />
            <p class="note">{t('waves_note')}</p>
        </SectionBlock>

        <SectionBlock title={t('stations_title')} intro={t('stations_intro')} maxWidth="1180px">
            <NetworkChart
                rows={stationRows}
                series={stationSeries}
                caption={t('stations_caption')}
            />
            <FiguresTable
                summary={t('table_toggle')}
                caption={t('stations_table')}
                columns={[
                    { label: t('col_date') },
                    { label: t('st_active'), num: true },
                    { label: t('st_tourist'), num: true },
                    { label: t('st_no_commercial'), num: true },
                    { label: t('st_declassified'), num: true },
                ]}
                rows={decades.map((r) => [
                    stockLabel(r),
                    n(r.stations.active),
                    n(r.stations.tourist),
                    n(r.stations.no_commercial),
                    n(r.stations.declassified),
                ])}
            />
        </SectionBlock>

        <SectionBlock kind="alt" title={t('elec_title')} intro={t('elec_intro')} maxWidth="1180px">
            <NetworkChart
                rows={elecRows}
                series={elecSeries}
                caption={t('elec_caption')}
                reserve={13}
            />
            <FiguresTable
                summary={t('table_toggle')}
                caption={t('elec_table')}
                columns={[
                    { label: t('col_date') },
                    { label: t('s_electrified'), num: true },
                    { label: t('col_open'), num: true },
                    { label: t('col_share'), num: true },
                ]}
                rows={withTraffic.map((r) => [
                    stockLabel(r),
                    n(r.electrified_km),
                    n(r.open_km),
                    `${percentOf(r.electrified_share) ?? 0} %`,
                ])}
            />
        </SectionBlock>

        <SectionBlock title={t('tracks_title')} maxWidth="1180px">
            {#if tracks}
                <p class="lead-figure">
                    {t('tracks_text', {
                        double: km(tracks.double_km),
                        single: km(tracks.single_km),
                        share: percentOf(tracks.double_share) ?? 0,
                    })}
                </p>
                <p class="note">{t('tracks_note')}</p>
            {:else}
                <p class="note">{t('tracks_missing')}</p>
            {/if}
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={t('accidents_title')}
            intro={t('accidents_intro')}
            maxWidth="1180px"
        >
            <NetworkChart
                rows={accidentRows}
                series={accidentSeries}
                caption={t('accidents_caption')}
                reserve={19}
            />
            <FiguresTable
                summary={t('table_toggle')}
                caption={t('accidents_table')}
                columns={[
                    { label: t('col_period') },
                    { label: t('col_accidents'), num: true },
                    { label: t('col_deaths'), num: true },
                    { label: t('col_deaths_known'), num: true },
                ]}
                rows={decades.map((r) => [
                    periodLabel(r),
                    n(r.accidents.count),
                    n(r.accidents.deaths),
                    n(r.accidents.deaths_known),
                ])}
            />
            <p class="note">
                {t('accidents_note')}
                <Link href="/accidents" class="vff-link">{t('accidents_link')}</Link>
            </p>
        </SectionBlock>

        <SectionBlock
            title={t('defs_title')}
            intro={t('defs_intro', { date: computedOn })}
            maxWidth="1180px"
        >
            <ul class="defs">
                {#each defs as d (d)}
                    <li>
                        <h3>{t(`def_${d}_term`)}</h3>
                        <p>{t(`def_${d}_text`)}</p>
                        <p class="source">
                            {t('source_label')}
                            {t(`def_${d}_source`)}
                            {t('computed', { date: computedOn })}.
                        </p>
                    </li>
                {/each}
            </ul>
            <p class="note">
                {t('api_text')}
                <a href="/api/v1/stats/network" class="vff-link">/api/v1/stats/network</a>
            </p>
            <p class="note">
                <Link href="/chronology" class="vff-link">{t('link_chronology')}</Link>
            </p>
            <p class="note">
                <Link href="/on-this-day" class="vff-link">{t('link_on_this_day')}</Link>
            </p>
            <p class="note">
                <Link href="/departments" class="vff-link">{t('link_departments')}</Link>
            </p>
            <p class="note">
                <Link href="/companies" class="vff-link">{t('link_companies')}</Link>
            </p>
            <p class="note">
                <Link href="/projects" class="vff-link">{t('link_projects')}</Link>
            </p>
        </SectionBlock>
    </main>
</Layout>

<style>
    .key {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
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
    .reading,
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .lead-figure {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-lead);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .waves {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        padding-left: 1.2em;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .waves li {
        margin-bottom: var(--vff-space-2);
    }
    .defs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-5) var(--vff-space-6);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .defs li {
        min-width: 0;
        margin: 0;
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border-strong);
    }
    .defs h3 {
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.3;
    }
    .defs p {
        margin: 0 0 6px;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text);
    }
    .defs .source {
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    @media (max-width: 900px) {
        .key {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .defs {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 520px) {
        .key {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
