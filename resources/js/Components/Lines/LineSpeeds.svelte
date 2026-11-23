<script>
    import { i18n } from '$lib/i18n.js';
    import { firstOfMonth, formatLocale, formatNumber, NBSP, tieUnits } from '$lib/typo.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import {
        eventKey,
        pkExtent,
        pkLabel,
        pkTicks,
        rangeAt,
        speedGaps,
        speedTicks,
        stepPath,
    } from '$lib/lineSpeeds.js';

    /**
     * Section « Vitesses maximales » de la fiche ligne (App\Services\Speed\LineSpeeds) : chiffres, graphique
     * en escalier de la vitesse maximale nominale selon le PK (SNCF Réseau, ère courante), lisible au survol
     * et au clavier (curseur : flèches, Début, Fin), tableau des sections replié (alternative au graphique),
     * puis les changements de vitesse datés relevés dans Wikipédia.
     *
     * @type {{ speeds: {
     *   era_year: number,
     *   ranges: Array<{ pk_start_m: number, pk_end_m: number, pk_start_label: string, pk_end_label: string,
     *     length_m: number, max_speed_kmh: number }>,
     *   summary: { max_speed_kmh: number|null, min_speed_kmh: number|null, covered_m: number,
     *     by_speed: Array<{ max_speed_kmh: number, length_m: number }> },
     *   history: Array<{ kind: string, speed_kmh: number, previous_speed_kmh: number|null, date: string,
     *     date_precision: string, reliability: string, scope: string,
     *     section: { from: string|null, to: string|null, pk_start_m: number|null, pk_end_m: number|null }|null,
     *     source: { provider: string, title: string, url: string, revision: number, excerpt: string|null } }>,
     * } }}
     */
    let { speeds } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));
    const kmh = (v) => `${formatNumber(v, lang)}${NBSP}km/h`;
    const km = (m) =>
        formatNumber(m / 1000, lang, { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    const ranges = $derived(speeds.ranges);
    const s = $derived(speeds.summary);
    const top = $derived(s.by_speed[0] ?? null);
    const intro = $derived(
        ranges.length === 0
            ? t('speeds.intro_history_only')
            : tieUnits(
                  s.min_speed_kmh === s.max_speed_kmh
                      ? t('speeds.intro_single', { max: s.max_speed_kmh })
                      : t('speeds.intro', { min: s.min_speed_kmh, max: s.max_speed_kmh }),
              ),
    );
    const statItems = $derived(
        ranges.length === 0
            ? []
            : [
                  { value: kmh(s.max_speed_kmh), label: t('speeds.stat_max') },
                  ...(s.min_speed_kmh !== s.max_speed_kmh
                      ? [
                            { value: kmh(s.min_speed_kmh), label: t('speeds.stat_min') },
                            {
                                value: `${km(top.length_m)}${NBSP}km`,
                                label: t('speeds.stat_top_share'),
                            },
                        ]
                      : []),
                  { value: `${km(s.covered_m)}${NBSP}km`, label: t('speeds.stat_covered') },
              ],
    );

    const rangeLabel = (r) =>
        tieUnits(
            t('speeds.range_label', {
                from: r.pk_start_label,
                to: r.pk_end_label,
                speed: r.max_speed_kmh,
                km: km(r.length_m),
            }),
        );

    // Géométrie : largeur mesurée, hauteur fixe ; vitesses à gauche, PK en bas.
    let width = $state(0);
    const narrow = $derived(width > 0 && width < 600);
    const height = $derived(narrow ? 220 : 260);
    const pad = $derived({ top: 30, right: 12, bottom: 50, left: narrow ? 60 : 72 });
    const plotW = $derived(Math.max(10, width - pad.left - pad.right));
    const plotH = $derived(height - pad.top - pad.bottom);
    const baseY = $derived(pad.top + plotH);
    const extent = $derived(pkExtent(ranges));
    const yTicks = $derived(speedTicks(s.max_speed_kmh ?? 40, narrow ? 3 : 4));
    const yTop = $derived(yTicks.at(-1) || 1);
    const x = $derived.by(
        () => (pk) => pad.left + ((pk - extent[0]) / (extent[1] - extent[0])) * plotW,
    );
    const y = $derived.by(() => (v) => pad.top + (1 - v / yTop) * plotH);
    const xTicks = $derived(pkTicks(extent[0], extent[1], Math.max(3, Math.floor(plotW / 80))));
    const path = $derived(stepPath(ranges, x, y));
    const gaps = $derived(speedGaps(ranges));

    // Tranche lue : survol ou curseur clavier.
    let active = $state(-1);
    const current = $derived(active >= 0 ? (ranges[active] ?? null) : null);

    function onPointer(event) {
        const box = event.currentTarget.getBoundingClientRect();
        const px = ((event.clientX - box.left) / box.width) * width;
        const pk = extent[0] + ((px - pad.left) / plotW) * (extent[1] - extent[0]);
        active = rangeAt(ranges, pk);
    }

    function onKey(event) {
        const last = ranges.length - 1;
        const moves = {
            ArrowRight: Math.min(last, active + 1),
            ArrowUp: Math.min(last, active + 1),
            ArrowLeft: Math.max(0, active - 1),
            ArrowDown: Math.max(0, active - 1),
            Home: 0,
            End: last,
        };
        if (event.key in moves) {
            event.preventDefault();
            active = moves[event.key];
        }
    }

    const dateOf = (e) =>
        firstOfMonth(formatEventDate(e.date, e.date_precision, formatLocale(lang)), lang);
    const eventText = (e) =>
        tieUnits(t(eventKey(e), { speed: e.speed_kmh, from: e.previous_speed_kmh }));
    const scopeText = (e) => {
        if (e.scope === 'whole_line') return t('speeds.scope.whole_line');
        if (!e.section) return null;
        if (e.section.from && e.section.to)
            return t('speeds.scope.section', { from: e.section.from, to: e.section.to });
        if (e.section.pk_start_m !== null && e.section.pk_end_m !== null)
            return t('speeds.scope.section_pk', {
                from: pkLabel(e.section.pk_start_m),
                to: pkLabel(e.section.pk_end_m),
            });
        return null;
    };
</script>

<SheetSection id="speeds" title={t('speeds.title')} {intro}>
    <div class="speeds">
        {#if ranges.length}
            <StatStrip items={statItems} size="sm" />

            <figure class="chart">
                <div class="plot" bind:clientWidth={width}>
                    {#if width > 0}
                        <!-- Curseur : une seule étape de tabulation, flèches pour passer d'une section à l'autre. -->
                        <svg
                            {width}
                            {height}
                            viewBox="0 0 {width} {height}"
                            role="slider"
                            tabindex="0"
                            aria-label={t('speeds.chart_label')}
                            aria-valuemin="0"
                            aria-valuemax={ranges.length - 1}
                            aria-valuenow={Math.max(0, active)}
                            aria-valuetext={rangeLabel(ranges[Math.max(0, active)])}
                            onkeydown={onKey}
                            onfocus={() => {
                                if (active < 0) active = 0;
                            }}
                            onpointermove={onPointer}
                            onpointerdown={onPointer}
                            onpointerleave={() => (active = -1)}
                            onblur={() => (active = -1)}
                        >
                            <defs>
                                <pattern
                                    id="speeds-gap"
                                    width="8"
                                    height="8"
                                    patternUnits="userSpaceOnUse"
                                    patternTransform="rotate(-45)"
                                >
                                    <line x1="0" y1="0" x2="0" y2="8" class="gap-hatch" />
                                </pattern>
                            </defs>
                            {#each yTicks as v (v)}
                                <line
                                    class="grid"
                                    x1={pad.left}
                                    x2={pad.left + plotW}
                                    y1={y(v)}
                                    y2={y(v)}
                                />
                                <text
                                    class="tick"
                                    x={pad.left - 8}
                                    y={y(v)}
                                    text-anchor="end"
                                    dy="0.35em">{formatNumber(v, lang)}</text
                                >
                            {/each}
                            <text class="axis" x={pad.left - 8} y={pad.top - 14} text-anchor="end"
                                >km/h</text
                            >
                            {#each xTicks as k (k)}
                                <text
                                    class="tick"
                                    x={x(k * 1000)}
                                    y={baseY + 24}
                                    text-anchor="middle">{formatNumber(k, lang)}</text
                                >
                            {/each}
                            <text class="axis" x={pad.left + plotW} y={height - 6} text-anchor="end"
                                >{t('speeds.axis_pk')}</text
                            >
                            {#each gaps as g (g.from_m)}
                                <rect
                                    class="gap"
                                    x={x(g.from_m)}
                                    y={pad.top}
                                    width={Math.max(2, x(g.to_m) - x(g.from_m))}
                                    height={plotH}
                                />
                            {/each}
                            {#each ranges as r, i (i)}
                                <rect
                                    class={['bar', { on: i === active }]}
                                    x={x(r.pk_start_m)}
                                    y={y(r.max_speed_kmh)}
                                    width={Math.max(1.5, x(r.pk_end_m) - x(r.pk_start_m))}
                                    height={baseY - y(r.max_speed_kmh)}
                                />
                            {/each}
                            <path class="step" d={path} />
                            {#if current}
                                <line
                                    class="cursor"
                                    x1={x(current.pk_start_m)}
                                    x2={Math.max(x(current.pk_start_m) + 2, x(current.pk_end_m))}
                                    y1={y(current.max_speed_kmh)}
                                    y2={y(current.max_speed_kmh)}
                                />
                            {/if}
                        </svg>
                    {/if}
                </div>
                <figcaption>
                    <!-- Lecture visuelle ; le lecteur d'écran lit la même chose dans aria-valuetext. -->
                    <p class="reading" aria-hidden="true">
                        {#if current}<strong>{kmh(current.max_speed_kmh)}</strong>
                            <span
                                >{t('speeds.scope.section_pk', {
                                    from: current.pk_start_label,
                                    to: current.pk_end_label,
                                })}, {km(current.length_m)}{NBSP}km</span
                            >{:else}<span class="muted">{t('speeds.hint')}</span>{/if}
                    </p>
                    {#if gaps.length}
                        <span class="legend-item"
                            ><span class="legend-swatch" aria-hidden="true"></span>{t(
                                'speeds.gap_legend',
                            )}</span
                        >
                    {/if}
                </figcaption>
            </figure>

            <details class="list">
                <summary>{t('speeds.show_table', { count: ranges.length })}</summary>
                <!-- Cadre défilant : focalisable pour défiler au clavier (GUIDE 5.8). -->
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <div
                    class="vff-table-wrap"
                    role="region"
                    aria-label={t('speeds.table_caption')}
                    tabindex="0"
                >
                    <table class="vff-table">
                        <caption class="vff-sr-only">{t('speeds.table_caption')}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="num">{t('speeds.col_from')}</th>
                                <th scope="col" class="num">{t('speeds.col_to')}</th>
                                <th scope="col" class="num">{t('speeds.col_length')}</th>
                                <th scope="col" class="num">{t('speeds.col_speed')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each ranges as r, i (i)}
                                <tr>
                                    <td class="num">{r.pk_start_label}</td>
                                    <td class="num">{r.pk_end_label}</td>
                                    <td class="num">{km(r.length_m)}{NBSP}km</td>
                                    <th scope="row" class="num">{kmh(r.max_speed_kmh)}</th>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </details>

            <p class="source">{t('speeds.source')}</p>
        {/if}

        {#if speeds.history.length}
            <h3 class="sub">{t('speeds.history_title')}</h3>
            <p class="note">{t('speeds.history_intro')}</p>
            <ol class="history">
                {#each speeds.history as e, i (i)}
                    {@const where = scopeText(e)}
                    <li>
                        <p class="when">{dateOf(e)}</p>
                        <div class="body">
                            <p class="main">{eventText(e)}</p>
                            {#if where}<p class="portion">{where}</p>{/if}
                            <p>
                                <a
                                    class="vff-link"
                                    href={e.source.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{t('speeds.source_link')}<i
                                        class="bi bi-box-arrow-up-right vff-ext"
                                        aria-hidden="true"
                                    ></i><span class="vff-sr-only">
                                        ({t('speeds.source_hint', { title: e.source.title })})</span
                                    ></a
                                >
                            </p>
                        </div>
                    </li>
                {/each}
            </ol>
            <p class="source">{t('speeds.history_source')}</p>
        {/if}
    </div>
</SheetSection>

<style>
    .speeds {
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
        border-radius: var(--vff-radius-sm);
        touch-action: pan-y;
        cursor: crosshair;
    }
    svg:focus-visible {
        outline: 2px solid var(--vff-accent-strong);
        outline-offset: 4px;
    }
    .grid {
        stroke: var(--vff-border);
        stroke-width: 1;
    }
    .tick,
    .axis {
        font-family: Inter, sans-serif;
        font-size: 17px;
        font-variant-numeric: tabular-nums;
        fill: var(--vff-text-muted);
    }
    .bar {
        fill: var(--vff-accent-soft);
    }
    .bar.on {
        fill: var(--vff-accent-strong);
        fill-opacity: 0.35;
    }
    /* Paliers : orange appuyé (contraste de 3:1 au moins avec le fond dans les deux thèmes). */
    .step {
        fill: none;
        stroke: var(--vff-accent-strong);
        stroke-width: 2.5;
        stroke-linejoin: round;
    }
    .cursor {
        stroke: var(--vff-text);
        stroke-width: 4;
        stroke-linecap: round;
    }
    .gap {
        fill: url(#speeds-gap);
    }
    .gap-hatch {
        stroke: var(--vff-border);
        stroke-width: 2;
    }
    figcaption {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px 20px;
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .reading {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 12px;
        min-height: 1.6em;
        margin: 0;
        font-family: Inter, sans-serif;
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
    }
    .reading .muted {
        color: var(--vff-text-muted);
    }
    .legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .legend-swatch {
        flex-shrink: 0;
        width: 24px;
        height: 10px;
        border: 1px solid var(--vff-border);
        border-radius: 2px;
        background: repeating-linear-gradient(-45deg, var(--vff-border) 0 2px, transparent 2px 6px);
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
