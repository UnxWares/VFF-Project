<script>
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { chartGeometry, polyline, WIDTH, HEIGHT } from '$lib/stationAttendance.js';
    import { formatNumber } from '$lib/typo.js';
    import { i18n } from '$lib/i18n.js';

    /**
     * Fréquentation annuelle de la gare (SNCF Gares & Connexions, ODbL) : valeur de la dernière année, courbe
     * des voyageurs, tableau des années, source et année de publication. Les deux séries (2015-2024, puis 2025)
     * viennent de méthodes différentes : la courbe ne les relie pas et la rupture est dite. Rien n'est affiché
     * quand les jeux n'ont aucun chiffre pour la gare (`attendance` nul).
     */
    /** @type {{ attendance?: null | { years: Array<{ year: number, passengers: number|null, footfall: number|null, non_passengers: number|null, series: string }>, latest: { year: number, passengers: number|null, footfall: number|null, published_year: number|null }, series_break: boolean }, name: string }} */
    let { attendance = null, name } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const num = (n) => formatNumber(n, lang);

    const years = $derived(attendance?.years ?? []);
    const geometry = $derived(chartGeometry(years));
    const withData = $derived(years.filter((y) => y.passengers != null));
    const first = $derived(withData[0]);
    const latest = $derived(attendance?.latest);
    const hasVisitors = $derived(
        years.some((y) => y.non_passengers != null && y.non_passengers > 0),
    );
    const showFootfall = $derived(
        latest?.footfall != null && latest.footfall > (latest.passengers ?? 0),
    );
</script>

{#if attendance && withData.length > 0}
    <SheetSection id="attendance" title={t('stations.attendance.title')}>
        <div class="attendance">
            <p class="latest">
                <span class="figure">{num(latest.passengers)}</span>
                <span class="unit">{t('stations.attendance.latest', { year: latest.year })}</span>
            </p>
            {#if showFootfall}
                <p class="note">
                    {t('stations.attendance.with_visitors', { count: num(latest.footfall) })}
                </p>
            {/if}

            {#if withData.length > 1}
                <figure class="chart">
                    <svg
                        viewBox="0 0 {WIDTH} {HEIGHT}"
                        role="img"
                        aria-label={t('stations.attendance.chart_alt', {
                            name,
                            from: first.year,
                            to: withData.at(-1).year,
                            first: num(first.passengers),
                            last: num(withData.at(-1).passengers),
                        })}
                    >
                        <line class="base" x1="0" y1={HEIGHT - 6} x2={WIDTH} y2={HEIGHT - 6} />
                        {#if geometry.breakX !== null}
                            <line
                                class="break"
                                x1={geometry.breakX}
                                y1="4"
                                x2={geometry.breakX}
                                y2={HEIGHT - 6}
                            />
                        {/if}
                        {#each geometry.segments as seg (seg.series)}
                            {#if seg.points.length > 1}
                                <polyline class="curve" points={polyline(seg.points)} />
                            {/if}
                            {#each seg.points as p (p.year)}
                                <circle class="dot" cx={p.x} cy={p.y} r="4" />
                            {/each}
                        {/each}
                    </svg>
                    <figcaption>
                        <span>{first.year}</span>
                        <span>{withData.at(-1).year}</span>
                    </figcaption>
                </figure>
            {/if}

            {#if attendance.series_break}
                <p class="note">{t('stations.attendance.series_break')}</p>
            {/if}

            <details class="table">
                <summary>{t('stations.attendance.table')}</summary>
                <div class="scroll">
                    <table>
                        <caption class="vff-sr-only"
                            >{t('stations.attendance.table_caption', { name })}</caption
                        >
                        <thead>
                            <tr>
                                <th scope="col">{t('stations.attendance.year')}</th>
                                <th scope="col" class="n">{t('stations.attendance.passengers')}</th>
                                {#if hasVisitors}
                                    <th scope="col" class="n"
                                        >{t('stations.attendance.footfall')}</th
                                    >
                                {/if}
                            </tr>
                        </thead>
                        <tbody>
                            {#each [...years].reverse() as y (y.year)}
                                <tr>
                                    <th scope="row">{y.year}</th>
                                    <td class="n"
                                        >{y.passengers != null ? num(y.passengers) : ''}</td
                                    >
                                    {#if hasVisitors}
                                        <td class="n"
                                            >{y.footfall != null ? num(y.footfall) : ''}</td
                                        >
                                    {/if}
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </details>

            <p class="source">
                {t('stations.attendance.source', {
                    year: latest.published_year ?? latest.year,
                })}
                <a
                    href="https://data.sncf.com/explore/dataset/frequentation-gares/"
                    target="_blank"
                    rel="noopener noreferrer license">{t('stations.attendance.licence')}</a
                >
            </p>
        </div>
    </SheetSection>
{/if}

<style>
    .attendance {
        max-width: 720px;
    }
    .latest {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px var(--vff-space-3);
        margin: 0;
    }
    .figure {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-figure);
        line-height: 1.1;
        color: var(--vff-text);
    }
    .unit {
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
    }
    .note,
    .source {
        margin: var(--vff-space-3) 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .chart {
        margin: var(--vff-space-4) 0 0;
    }
    svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }
    .base {
        stroke: var(--vff-border-strong);
        stroke-width: 1;
    }
    .break {
        stroke: var(--vff-border-control);
        stroke-width: 1;
        stroke-dasharray: 3 4;
    }
    .curve {
        fill: none;
        stroke: var(--vff-accent);
        stroke-width: 2.5;
        stroke-linejoin: round;
        stroke-linecap: round;
        vector-effect: non-scaling-stroke;
    }
    .dot {
        fill: var(--vff-accent);
    }
    figcaption {
        display: flex;
        justify-content: space-between;
        margin-top: var(--vff-space-1);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .table {
        margin-top: var(--vff-space-3);
    }
    summary {
        cursor: pointer;
        font-size: var(--vff-fs-sm);
        color: var(--vff-accent-ink);
    }
    .scroll {
        overflow-x: auto;
        margin-top: var(--vff-space-2);
    }
    table {
        border-collapse: collapse;
        font-size: var(--vff-fs-sm);
    }
    th,
    td {
        padding: var(--vff-space-1) var(--vff-space-4) var(--vff-space-1) 0;
        text-align: left;
        font-weight: 400;
        border-bottom: 1px solid var(--vff-border);
    }
    thead th {
        color: var(--vff-text-muted);
    }
    .n {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .source a {
        color: var(--vff-accent-ink);
    }
</style>
