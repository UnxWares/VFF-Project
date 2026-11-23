<script>
    import { i18n } from '$lib/i18n.js';
    import { firstOfMonth, formatLocale, formatNumber, NBSP, tieUnits } from '$lib/typo.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import {
        eventKey,
        extent as pkSpan,
        fillKind,
        lanes as laneList,
        pkLabel,
        pkTicks,
        span,
        systemKey,
        voltageText,
    } from '$lib/lineElectrification.js';

    /**
     * Section « Électrification » de la fiche ligne (App\Services\Traction\LineElectrificationHistory) :
     * diagramme sur l'axe des PK (bande des tranches électrifiées aujourd'hui selon SNCF Réseau, puis une voie
     * par portion dont une chronologie d'article donne la mise sous tension, avec son année), tableau des
     * tranches replié (alternative au diagramme) et liste datée complète : mises sous tension, conversions,
     * désélectrifications, chacune avec sa source.
     *
     * @type {{ electrification: {
     *   ranges: Array<{ pk_start_m: number, pk_end_m: number, pk_start_label: string, pk_end_label: string,
     *     length_m: number, voltage_v: number|null, system: string }>,
     *   summary: { line_electrified: boolean, line_voltage_v: number|null, electrified_m: number,
     *     by_voltage: Array<{ voltage_v: number|null, system: string, length_m: number }> },
     *   history: Array<{ kind: string, voltage_v: number|null, system: string|null, date: string,
     *     date_precision: string, reliability: string, scope: string,
     *     section: { from: string|null, to: string|null, pk_start_m: number|null, pk_end_m: number|null }|null,
     *     source: { provider: string, url: string|null, excerpt: string|null } }>,
     * } }}
     */
    let { electrification } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));
    const fmt = (n) => formatNumber(n, lang);
    const km = (m) =>
        formatNumber(m / 1000, lang, { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    const ranges = $derived(electrification.ranges);
    const history = $derived(electrification.history);
    const s = $derived(electrification.summary);

    /** « 1 500 V continu », « 25 kV alternatif 50 Hz » : tension et courant d'une tranche ou d'un fait. */
    function systemText(system, volt) {
        if (system === 'ac' && volt === 25000) return t('electrification.system_ac_50');
        const key = systemKey(system);
        return key ? t(key) : '';
    }
    function voltageLabel(volt, system) {
        const v = voltageText(volt, fmt);
        if (v === null) return t('electrification.voltage_unknown');
        return tieUnits(
            t('electrification.voltage_label', {
                volt: v,
                system: systemText(system, volt),
            }).trim(),
        );
    }
    const list = (items) => {
        try {
            return new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }).format(items);
        } catch {
            return items.join(', ');
        }
    };

    const intro = $derived(
        ranges.length === 0
            ? t('electrification.intro_history_only')
            : tieUnits(
                  t('electrification.intro', {
                      km: km(s.electrified_m),
                      voltages: list(s.by_voltage.map((b) => voltageLabel(b.voltage_v, b.system))),
                  }),
              ),
    );

    const dated = $derived(span(history));
    const dateYear = (iso) => formatEventDate(iso, 'year', formatLocale(lang));
    const statItems = $derived([
        ...(ranges.length
            ? [
                  { value: `${km(s.electrified_m)}${NBSP}km`, label: t('electrification.stat_km') },
                  {
                      value:
                          voltageText(s.by_voltage[0].voltage_v, fmt) ??
                          t('electrification.voltage_unknown'),
                      label: `${t('electrification.stat_voltage')} (${systemText(s.by_voltage[0].system, s.by_voltage[0].voltage_v) || t('electrification.system.unknown')})`,
                  },
              ]
            : []),
        ...(dated
            ? [
                  { value: dateYear(dated.first), label: t('electrification.stat_first') },
                  ...(dated.last.slice(0, 4) !== dated.first.slice(0, 4)
                      ? [{ value: dateYear(dated.last), label: t('electrification.stat_last') }]
                      : []),
              ]
            : []),
    ]);

    // Diagramme : une bande « aujourd'hui » puis une voie par portion datée et placée sur la ligne.
    const lanes = $derived(laneList(history));
    const showChart = $derived(ranges.length > 0 || lanes.length > 0);
    const extent = $derived(pkSpan(ranges, lanes));
    let width = $state(0);
    const narrow = $derived(width > 0 && width < 600);
    // Étroit : l'étiquette « Aujourd'hui » passe au-dessus de la bande au lieu de la précéder.
    const pad = $derived({ top: narrow ? 34 : 8, right: 12, bottom: 50, left: narrow ? 12 : 96 });
    const bandH = 30;
    const laneH = 26;
    const height = $derived(pad.top + bandH + 10 + lanes.length * laneH + pad.bottom);
    const plotW = $derived(Math.max(10, width - pad.left - pad.right));
    const x = $derived.by(
        () => (pk) => pad.left + ((pk - extent[0]) / (extent[1] - extent[0])) * plotW,
    );
    const baseY = $derived(height - pad.bottom);
    const xTicks = $derived(pkTicks(extent[0], extent[1], Math.max(3, Math.floor(plotW / 80))));
    const systems = $derived([...new Set(ranges.map((r) => r.system))]);
    const rangeLabel = (r) =>
        tieUnits(
            `${voltageLabel(r.voltage_v, r.system)}, ${t('electrification.scope.section_pk', {
                from: r.pk_start_label,
                to: r.pk_end_label,
            })}, ${km(r.length_m)}${NBSP}km`,
        );
    const laneYear = (i) => String(history[i].date.slice(0, 4));

    const dateOf = (e) =>
        firstOfMonth(formatEventDate(e.date, e.date_precision, formatLocale(lang)), lang);
    const eventText = (e) =>
        tieUnits(
            t(eventKey(e), {
                voltage: voltageLabel(e.voltage_v, e.system),
            }),
        );
    const scopeText = (e) => {
        if (e.scope === 'whole_line') return t('electrification.scope.whole_line');
        if (e.scope === 'unspecified') return t('electrification.scope.unspecified');
        if (!e.section) return null;
        if (e.section.from && e.section.to)
            return t('electrification.scope.section', { from: e.section.from, to: e.section.to });
        if (e.section.pk_start_m !== null && e.section.pk_end_m !== null)
            return t('electrification.scope.section_pk', {
                from: pkLabel(e.section.pk_start_m),
                to: pkLabel(e.section.pk_end_m),
            });
        return null;
    };
    const sourceName = (e) =>
        e.source.provider === 'wikidata'
            ? t('electrification.source_link_wikidata')
            : t('electrification.source_link');
</script>

<SheetSection id="electrification" title={t('electrification.title')} {intro}>
    <div class="elec">
        {#if statItems.length}
            <StatStrip items={statItems} size="sm" />
        {/if}

        {#if showChart}
            <figure class="chart">
                <div class="plot" bind:clientWidth={width}>
                    {#if width > 0}
                        <svg
                            {width}
                            {height}
                            viewBox="0 0 {width} {height}"
                            role="img"
                            aria-label={t('electrification.chart_label')}
                        >
                            <defs>
                                <pattern
                                    id="elec-hatch"
                                    width="7"
                                    height="7"
                                    patternUnits="userSpaceOnUse"
                                    patternTransform="rotate(-45)"
                                >
                                    <rect width="7" height="7" class="hatch-bg" />
                                    <line x1="0" y1="0" x2="0" y2="7" class="hatch-line" />
                                </pattern>
                                <pattern
                                    id="elec-dots"
                                    width="6"
                                    height="6"
                                    patternUnits="userSpaceOnUse"
                                >
                                    <rect width="6" height="6" class="dots-bg" />
                                    <circle cx="3" cy="3" r="1.4" class="dots-dot" />
                                </pattern>
                            </defs>
                            {#each xTicks as k (k)}
                                <line
                                    class="grid"
                                    x1={x(k * 1000)}
                                    x2={x(k * 1000)}
                                    y1={pad.top}
                                    y2={baseY}
                                />
                                <text
                                    class="tick"
                                    x={x(k * 1000)}
                                    y={baseY + 24}
                                    text-anchor="middle">{fmt(k)}</text
                                >
                            {/each}
                            <text class="axis" x={pad.left + plotW} y={height - 6} text-anchor="end"
                                >{t('electrification.axis_pk')}</text
                            >
                            <text
                                class="row-label"
                                x={narrow ? pad.left : pad.left - 8}
                                y={narrow ? pad.top - 10 : pad.top + bandH / 2}
                                dy={narrow ? '0' : '0.35em'}
                                text-anchor={narrow ? 'start' : 'end'}
                                >{t('electrification.row_today')}</text
                            >
                            {#each ranges as r, i (i)}
                                <rect
                                    class={['band', fillKind(r.system)]}
                                    x={x(r.pk_start_m)}
                                    y={pad.top}
                                    width={Math.max(2, x(r.pk_end_m) - x(r.pk_start_m))}
                                    height={bandH}
                                    rx="3"
                                >
                                    <title>{rangeLabel(r)}</title>
                                </rect>
                            {/each}
                            {#each lanes as l, i (l.index)}
                                {@const y0 = pad.top + bandH + 10 + i * laneH}
                                {@const x0 = x(l.pk_start_m)}
                                {@const w = Math.max(3, x(l.pk_end_m) - x0)}
                                <rect
                                    class="lane"
                                    x={x0}
                                    y={y0 + 4}
                                    width={w}
                                    height={laneH - 10}
                                    rx="3"
                                >
                                    <title
                                        >{eventText(history[l.index])}, {dateOf(
                                            history[l.index],
                                        )}</title
                                    >
                                </rect>
                                <text
                                    class="lane-year"
                                    x={x0 + w + 6 > pad.left + plotW - 40 ? x0 - 6 : x0 + w + 6}
                                    y={y0 + laneH / 2}
                                    dy="0.35em"
                                    text-anchor={x0 + w + 6 > pad.left + plotW - 40
                                        ? 'end'
                                        : 'start'}>{laneYear(l.index)}</text
                                >
                            {/each}
                        </svg>
                    {/if}
                </div>
                <figcaption>
                    <span class="legend-title">{t('electrification.legend_title')}</span>
                    {#each systems as sys (sys)}
                        <span class="legend-item"
                            ><span class={['legend-swatch', fillKind(sys)]} aria-hidden="true"
                            ></span>{systemText(sys, null) ||
                                t('electrification.system.unknown')}</span
                        >
                    {/each}
                    {#if lanes.length}
                        <span class="legend-item"
                            ><span class="legend-swatch lane-swatch" aria-hidden="true"></span>{t(
                                'electrification.dated_portions',
                            )}</span
                        >
                    {/if}
                </figcaption>
            </figure>
        {/if}

        {#if ranges.length}
            <details class="list">
                <summary>{t('electrification.show_table', { count: ranges.length })}</summary>
                <!-- Cadre défilant : focalisable pour défiler au clavier (GUIDE 5.8). -->
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <div
                    class="vff-table-wrap"
                    role="region"
                    aria-label={t('electrification.table_caption')}
                    tabindex="0"
                >
                    <table class="vff-table">
                        <caption class="vff-sr-only">{t('electrification.table_caption')}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="num">{t('electrification.col_from')}</th>
                                <th scope="col" class="num">{t('electrification.col_to')}</th>
                                <th scope="col" class="num">{t('electrification.col_length')}</th>
                                <th scope="col">{t('electrification.col_voltage')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each ranges as r, i (i)}
                                <tr>
                                    <td class="num">{r.pk_start_label}</td>
                                    <td class="num">{r.pk_end_label}</td>
                                    <td class="num">{km(r.length_m)}{NBSP}km</td>
                                    <th scope="row">{voltageLabel(r.voltage_v, r.system)}</th>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </details>
            <p class="source">{t('electrification.source')}</p>
        {/if}

        {#if history.length}
            <h3 class="sub">{t('electrification.history_title')}</h3>
            <p class="note">{t('electrification.history_intro')}</p>
            <ol class="history">
                {#each history as e, i (i)}
                    {@const where = scopeText(e)}
                    <li>
                        <p class="when">{dateOf(e)}</p>
                        <div class="body">
                            <p class="main">{eventText(e)}</p>
                            {#if where}<p class="portion">{where}</p>{/if}
                            {#if e.source.url}
                                <p>
                                    <a
                                        class="vff-link"
                                        href={e.source.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{sourceName(e)}<i
                                            class="bi bi-box-arrow-up-right vff-ext"
                                            aria-hidden="true"
                                        ></i><span class="vff-sr-only">
                                            ({t('electrification.source_hint')})</span
                                        ></a
                                    >
                                </p>
                            {/if}
                        </div>
                    </li>
                {/each}
            </ol>
            <p class="source">{t('electrification.history_source')}</p>
        {/if}
    </div>
</SheetSection>

<style>
    .elec {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
    }
    .note,
    .source {
        margin: 0;
        max-width: var(--vff-measure);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .sub {
        margin: var(--vff-space-3) 0 calc(-1 * var(--vff-space-3));
        font-size: var(--vff-fs-h3);
    }
    .chart {
        margin: 0;
        padding: var(--vff-space-4) var(--vff-space-4) var(--vff-space-3);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        min-width: 0;
    }
    .plot {
        position: relative;
    }
    svg {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }
    .grid {
        stroke: var(--vff-border);
        stroke-width: 1;
    }
    .tick,
    .axis,
    .row-label,
    .lane-year {
        font-family: Inter, sans-serif;
        font-size: 17px;
        font-variant-numeric: tabular-nums;
        fill: var(--vff-text-muted);
    }
    .lane-year {
        fill: var(--vff-text);
    }
    /* Courant continu : plein ; alternatif : hachuré ; troisième rail : pointillé (jamais la couleur seule). */
    .band.solid,
    .legend-swatch.solid {
        fill: var(--vff-accent-strong);
        background: var(--vff-accent-strong);
    }
    .band.hatch {
        fill: url(#elec-hatch);
    }
    .band.dots {
        fill: url(#elec-dots);
    }
    .hatch-bg,
    .dots-bg {
        fill: var(--vff-accent-soft);
    }
    .hatch-line {
        stroke: var(--vff-accent-strong);
        stroke-width: 3;
    }
    .dots-dot {
        fill: var(--vff-accent-strong);
    }
    .band {
        stroke: var(--vff-accent-strong);
        stroke-width: 1.5;
    }
    .lane {
        fill: var(--vff-accent-strong);
        fill-opacity: 0.55;
    }
    figcaption {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 20px;
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .legend-title {
        color: var(--vff-text);
    }
    .legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .legend-swatch {
        flex-shrink: 0;
        width: 26px;
        height: 12px;
        border: 1.5px solid var(--vff-accent-strong);
        border-radius: 3px;
    }
    .legend-swatch.hatch {
        background: repeating-linear-gradient(
            -45deg,
            var(--vff-accent-strong) 0 2px,
            var(--vff-accent-soft) 2px 6px
        );
    }
    .legend-swatch.dots {
        background:
            radial-gradient(circle, var(--vff-accent-strong) 1.4px, transparent 1.6px) 0 0 / 6px 6px,
            var(--vff-accent-soft);
    }
    .lane-swatch {
        background: var(--vff-accent-strong);
        opacity: 0.55;
    }
    .list summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        cursor: pointer;
    }
    .list[open] summary {
        margin-bottom: var(--vff-space-4);
    }
    .list .vff-table-wrap {
        max-height: 28rem;
        overflow: auto;
    }
    .history {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .history li {
        display: grid;
        grid-template-columns: minmax(0, 12em) minmax(0, 1fr);
        gap: 4px var(--vff-space-5);
        padding: 12px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .history p {
        margin: 0;
    }
    .when {
        font-variant-numeric: tabular-nums;
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .main {
        font-size: var(--vff-fs-md);
    }
    .portion {
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .chart {
            padding: var(--vff-space-3) var(--vff-space-2) var(--vff-space-2);
        }
        .history li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
