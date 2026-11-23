<script>
    import { onMount } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { loadLinePortions } from '$lib/linePortions.js';
    import {
        STRIP_ORDER,
        categoryColor,
        categorySwatch,
        changeVerbKey,
        formatEventDate,
        formatKm,
        phaseDateLabel,
        stepPhase,
        summarizeChanges,
    } from '$lib/linePhases.js';
    import {
        axisLabels,
        phaseBands,
        portionAt,
        portionSpans,
        shortPhaseDate,
        stateSince,
        stationPositions,
    } from '$lib/lineSpaceTime.js';
    import { colon, firstOfMonth, formatLocale, tieUnits } from '$lib/typo.js';
    import LinePhaseText from '$components/Lines/LinePhaseText.svelte';

    /**
     * « Où et quand » : la ligne déroulée de bout en bout (portions de LinePhases dans leur ordre le
     * long de la ligne), une bande par phase, de la plus ancienne à la plus récente. On voit d'un coup
     * d'œil où la ligne a ouvert, perdu ses voyageurs, fermé, été déposée ou rouverte. Le trait sous
     * une bande marque les portions que la phase modifie. Survol (ou toucher) : l'état de la portion,
     * depuis quand, et l'événement daté avec sa source. Choisir une date (clic, toucher, flèches du
     * clavier) affiche le texte de la phase avec ses appels de note, et la même phase sur la carte.
     */
    let {
        portionsUrl,
        phases = [],
        stations = [],
        eventsById = {},
        selected = $bindable(null),
        onshow = () => {},
    } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));
    const locale = $derived(formatLocale(lang));
    const km = (m) => tieUnits(formatKm(m, locale));
    const kmShort = (m) => ((m ?? 0) / 1000).toLocaleString(locale, { maximumFractionDigits: 1 });

    /** @type {Array<{ length_m: number, geometry: any }>} */
    let portions = $state.raw([]);
    let failed = $state(false);
    let loading = $state(true);

    onMount(() => {
        let alive = true;
        loadLinePortions(portionsUrl)
            .then((list) => {
                if (alive) portions = list;
            })
            .catch(() => {
                if (alive) failed = true;
            })
            .finally(() => {
                if (alive) loading = false;
            });
        return () => {
            alive = false;
        };
    });

    const axis = $derived(portionSpans(portions));
    const rows = $derived(phases.map((p) => phaseBands(p, axis)));
    const positions = $derived(stationPositions(stations, portions, axis));
    const firstStation = $derived(positions[0]?.name ?? null);
    const lastStation = $derived(positions.length > 1 ? positions.at(-1).name : null);
    // Noms de gares qui tiennent sur l'axe sans se toucher (environ 9 px par caractère).
    let namesWidth = $state(0);
    const labels = $derived(
        namesWidth ? axisLabels(positions, axis.total, (9 * 100) / namesWidth, 3) : [],
    );

    const words = $derived({
        before: (year) => t('lines.phases.before', { year }),
        since: (year) => t('lines.phases.between_today', { from: year }),
    });
    // Deux dates de la même année qui se suivent : le mois les distingue (« 1868 », « nov. 1868 »).
    const shortDates = $derived.by(() => {
        const years = phases.map((p) => shortPhaseDate(p, words));
        return years.map((y, i) => {
            const p = phases[i];
            const twin = years[i - 1] === y || years[i + 1] === y;
            if (!twin || p.precision === 'range' || p.precision === 'year') return y;
            return new Date(`${String(p.date).slice(0, 10)}T00:00:00Z`).toLocaleDateString(locale, {
                month: 'short',
                year: 'numeric',
                timeZone: 'UTC',
            });
        });
    });
    const labelCh = $derived(Math.max(4, ...shortDates.map((d) => d.length)));
    const fullDate = (p) => firstOfMonth(phaseDateLabel(p, $i18n.t, locale), lang);
    const catLabel = (c) => t(`lines.categories.${c}`);

    const legend = $derived.by(() => {
        // Ensemble local du calcul, jamais lu par le rendu : pas de réactivité.
        // eslint-disable-next-line svelte/prefer-svelte-reactivity
        const present = new Set();
        for (const r of rows) for (const b of r) present.add(b.cat);
        return STRIP_ORDER.filter((c) => present.has(c));
    });

    /** Ce que la phase change, en une phrase (le plus grand changement et le nombre des autres). */
    function headline(p) {
        const { changes } = summarizeChanges(p);
        const c = changes[0];
        if (!c) return t('lines.phases.minor_only');
        const text = t(changeVerbKey(c.from, c.to), {
            km: km(c.length_m),
            to: catLabel(c.to),
            from: catLabel(c.from),
        });
        return changes.length > 1
            ? `${text} ${t('lines.phases.and_more', { count: changes.length - 1 })}`
            : text;
    }

    /** Texte lu pour une bande : date, changement, puis l'état de la ligne d'un bout à l'autre. */
    function rowLabel(p, bands) {
        const state = bands
            .map((b) =>
                t('lines.spacetime.state_part', {
                    state: catLabel(b.cat),
                    from: kmShort(b.from),
                    to: kmShort(b.to),
                }),
            )
            .join(', ');
        return `${fullDate(p)}${colon(lang)}${tieUnits(headline(p))}. ${t('lines.spacetime.state', { list: state })}`;
    }

    // Navigation au clavier dans la liste (même usage que le sélecteur de phases à côté de la carte).
    let buttons = $state([]);
    function select(i) {
        if (i == null) return;
        selected = i;
        buttons[i]?.focus();
    }
    function onKeydown(e) {
        const delta = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
        if (e.key === 'Home') select(0);
        else if (e.key === 'End') select(phases.length - 1);
        else if (delta) select(stepPhase(selected, delta, phases.length));
        else return;
        e.preventDefault();
    }

    // Bulle de survol (ou de toucher) : portion sous le pointeur, dans la bande pointée.
    let plotEl = $state();
    let plotWidth = $state(0);
    let tipWidth = $state(0);
    /** @type {{ row: number, portion: number, x: number, y: number } | null} */
    let tip = $state(null);

    function pointAt(e, row) {
        const bar = e.currentTarget.querySelector('.bar');
        if (!bar || !plotEl || !axis.total) return;
        const box = bar.getBoundingClientRect();
        const f = (e.clientX - box.left) / box.width;
        if (f < 0 || f > 1) {
            tip = null;
            return;
        }
        const portion = portionAt(axis, f * axis.total);
        const cat = portion == null ? null : (phases[row]?.state?.[portion] ?? 'none');
        if (portion == null || cat === 'none') {
            tip = null;
            return;
        }
        const outer = plotEl.getBoundingClientRect();
        tip = { row, portion, x: e.clientX - outer.left, y: box.bottom - outer.top + 8 };
    }

    const tipInfo = $derived.by(() => {
        if (!tip) return null;
        const cat = phases[tip.row]?.state?.[tip.portion] ?? 'none';
        // Portions voisines dans le même état à cette date : la bande entière, pas un morceau.
        const band = rows[tip.row]?.find((b) => b.members.includes(tip.portion));
        const since = stateSince(phases, tip.row, tip.portion);
        const ev = since?.change?.event_id != null ? eventsById[since.change.event_id] : null;
        return {
            cat,
            range: band
                ? t('lines.spacetime.range', { from: kmShort(band.from), to: kmShort(band.to) })
                : '',
            since: since ? fullDate(phases[since.phase]) : null,
            event: ev
                ? [
                      eventType(ev.type),
                      ev.date
                          ? firstOfMonth(formatEventDate(ev.date, ev.precision, locale), lang)
                          : null,
                      ev.source ? sourceLabel(ev.source) : null,
                  ]
                      .filter(Boolean)
                      .join(', ')
                : null,
        };
    });
    const tipLeft = $derived(
        tip ? Math.max(0, Math.min(tip.x - tipWidth / 2, plotWidth - tipWidth)) : 0,
    );

    function eventType(type) {
        const key = `lines.event_types.${type}`;
        const label = $i18n.t(key);
        return label === key ? type : label;
    }
    function sourceLabel(code) {
        const key = `lines.sources.${code}`;
        const label = $i18n.t(key);
        return label === key ? code : label;
    }

    const current = $derived(phases[selected] ?? null);
</script>

{#if failed}
    <p class="empty">{t('lines.spacetime.failed')}</p>
{:else if loading}
    <p class="empty" aria-live="polite">{t('lines.spacetime.loading')}</p>
{:else if axis.total > 0}
    <div class="spacetime" style:--label-w="{labelCh + 1}ch">
        <!-- Axe : gares placées le long de la ligne déroulée. -->
        <div class="axis top" aria-hidden="true">
            <span></span>
            <span class="names" bind:clientWidth={namesWidth}>
                {#each labels as l (l.name + l.at)}
                    <span
                        class={['name', l.edge]}
                        style:left="{l.pct}%"
                        style:max-width="{l.edge === 'start'
                            ? 100 - l.pct
                            : l.edge === 'end'
                              ? l.pct
                              : 2 * Math.min(l.pct, 100 - l.pct)}%">{l.name}</span
                    >
                {/each}
            </span>
        </div>

        <div class="plot" bind:this={plotEl} bind:clientWidth={plotWidth}>
            <ol
                class="rows"
                role="listbox"
                aria-label={firstStation && lastStation
                    ? t('lines.spacetime.label_ends', { from: firstStation, to: lastStation })
                    : t('lines.spacetime.label')}
                tabindex="-1"
                onkeydown={onKeydown}
                onpointerleave={(e) => {
                    if (e.pointerType === 'mouse') tip = null;
                }}
            >
                {#each phases as p, i (i)}
                    <li role="presentation">
                        <button
                            type="button"
                            role="option"
                            aria-selected={selected === i}
                            tabindex={selected === i ? 0 : -1}
                            class={['row', { active: selected === i }]}
                            bind:this={buttons[i]}
                            aria-label={rowLabel(p, rows[i] ?? [])}
                            onclick={(e) => {
                                selected = i;
                                pointAt(e, i);
                            }}
                            onpointermove={(e) => {
                                if (e.pointerType === 'mouse') pointAt(e, i);
                            }}
                        >
                            <span class={['date', { undated: p.precision === 'range' }]}
                                >{shortDates[i]}</span
                            >
                            <span class="bar">
                                <span class="track"></span>
                                {#each rows[i] ?? [] as b (b.first)}
                                    <span
                                        class="band cat-{b.cat}"
                                        class:changed={b.changed}
                                        style:left="{b.left}%"
                                        style:width="{b.width}%"
                                        style:--c={categoryColor(b.cat)}
                                    ></span>
                                {/each}
                            </span>
                        </button>
                    </li>
                {/each}
            </ol>

            {#if tip && tipInfo}
                <div
                    class="tip"
                    aria-hidden="true"
                    bind:clientWidth={tipWidth}
                    style:left="{tipLeft}px"
                    style:top="{tip.y}px"
                >
                    <strong
                        ><i class="sw" style={categorySwatch(tipInfo.cat)}></i>{catLabel(
                            tipInfo.cat,
                        )}</strong
                    >
                    <span>{tipInfo.range}</span>
                    {#if tipInfo.since}<span
                            >{t('lines.spacetime.since', { date: tipInfo.since })}</span
                        >{/if}
                    {#if tipInfo.event}<span class="ev">{tipInfo.event}</span>{/if}
                </div>
            {/if}
        </div>

        <div class="axis bottom" aria-hidden="true">
            <span></span>
            <span class="kms">
                <span>0</span>
                <span>{km(axis.total)}</span>
            </span>
        </div>
    </div>

    <ul class="legend" aria-label={t('lines.spacetime.legend')}>
        {#each legend as cat (cat)}
            <li>
                <span class="swatch" style={categorySwatch(cat)} aria-hidden="true"
                ></span>{catLabel(cat)}
            </li>
        {/each}
        <li>
            <span class="swatch changed-mark" aria-hidden="true"></span>{t(
                'lines.spacetime.changed',
            )}
        </li>
    </ul>

    {#if current}
        <div class="detail" aria-live="polite">
            <div class="detail-head">
                <h3>{fullDate(current)}</h3>
                <button type="button" class="see-map" onclick={() => onshow(selected)}>
                    <i class="bi bi-map" aria-hidden="true"></i>
                    {t('lines.phases.see_on_map')}
                </button>
            </div>
            <LinePhaseText phase={current} {eventsById} />
        </div>
    {/if}
    <p class="note">{t('lines.spacetime.order_note')}</p>
{/if}

<style>
    .spacetime {
        container-type: inline-size;
        font-family: var(--vff-font-heading);
    }
    /* Colonne des dates en « ch » de la police des dates : bandes et axes restent alignés. */
    .axis,
    .row {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-sm);
        font-weight: 700;
        display: grid;
        grid-template-columns: var(--label-w) minmax(0, 1fr);
        gap: 0 12px;
        align-items: center;
    }
    .axis.top {
        min-height: 26px;
        margin-bottom: 4px;
    }
    .names {
        position: relative;
        display: block;
        height: 22px;
    }
    .name {
        font-family: var(--vff-font-heading);
        font-weight: 400;
        position: absolute;
        top: 0;
        overflow: hidden;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        white-space: nowrap;
        text-overflow: ellipsis;
        transform: translateX(-50%);
    }
    .name.start {
        transform: none;
    }
    .name.end {
        transform: translateX(-100%);
    }
    .plot {
        position: relative;
    }
    .rows {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .row {
        width: 100%;
        min-height: 34px;
        padding: 4px 0;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text);
        text-align: left;
        cursor: pointer;
    }
    .row:hover {
        background: var(--vff-bg-muted);
    }
    .row.active {
        background: var(--vff-bg-accent);
    }
    .date {
        padding-left: 8px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-sm);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .date.undated {
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .bar {
        position: relative;
        display: block;
        height: 22px;
        margin-right: 8px;
    }
    /* Ligne pas encore construite : un fil discret sur toute la longueur. */
    .track {
        position: absolute;
        top: 7px;
        left: 0;
        right: 0;
        height: 2px;
        border-radius: 1px;
        background: var(--vff-border);
    }
    .band {
        position: absolute;
        top: 1px;
        height: 14px;
        min-width: 2px;
        border-radius: 3px;
        background: var(--c);
    }
    .band.cat-closed,
    .band.cat-construction {
        background: repeating-linear-gradient(90deg, var(--c) 0 5px, transparent 5px 8px);
    }
    .band.cat-dismantled,
    .band.cat-razed,
    .band.cat-gone,
    .band.cat-velorail {
        background: repeating-linear-gradient(90deg, var(--c) 0 2px, transparent 2px 5px);
    }
    /* Ce qui change à cette date : un trait sous la bande. */
    .band.changed::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        top: 18px;
        height: 3px;
        border-radius: 2px;
        background: var(--vff-text);
    }
    .axis.bottom {
        margin-top: 4px;
    }
    .kms {
        font-family: var(--vff-font-heading);
        font-weight: 400;
        display: flex;
        justify-content: space-between;
        margin-right: 8px;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .tip {
        position: absolute;
        z-index: 3;
        display: flex;
        flex-direction: column;
        gap: 2px;
        width: max-content;
        max-width: min(320px, 100%);
        padding: 10px 12px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg);
        box-shadow: var(--vff-shadow-popover);
        color: var(--vff-text);
        font-size: var(--vff-fs-sm);
        line-height: 1.4;
        pointer-events: none;
    }
    .tip strong {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
    }
    .tip .ev {
        color: var(--vff-text-muted);
    }
    .sw,
    .swatch {
        display: inline-block;
        flex-shrink: 0;
        width: 18px;
    }
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 20px;
        margin: 14px 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .legend li {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .changed-mark {
        height: 3px;
        border-radius: 2px;
        background: var(--vff-text);
    }
    .detail {
        margin-top: var(--vff-space-4);
        padding: 18px 20px;
        border-radius: var(--vff-radius-lg);
        background: var(--vff-bg-muted);
    }
    .detail-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px 16px;
        margin-bottom: 8px;
    }
    .detail h3 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
    }
    .see-map {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 6px 14px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        cursor: pointer;
    }
    .see-map:hover {
        background: var(--vff-bg-accent);
    }
    .note,
    .empty {
        margin: 12px 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        text-wrap: pretty;
    }
    @media (prefers-reduced-motion: no-preference) {
        .row {
            transition: background-color var(--vff-transition);
        }
    }
</style>
