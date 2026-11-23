<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { barOf, decadeTicks, periodLabel, stateIn, yearLeft } from '$lib/materialTimeline.js';

    /**
     * Frise du matériel roulant : une section par traction, une rangée par série (lien vers la fiche), une
     * barre de la mise en service au retrait. Le bord estompé dit que la fin n'est pas renseignée. Une année
     * choisie met en évidence les séries en service ; les autres s'effacent. Le texte de chaque rangée porte la
     * période et l'état (la couleur ne dit rien seule) ; le tableau de la page donne les mêmes informations.
     *
     * Props : `groups` ([{ group, rows }]), `axis` ({ start, end, span }), `year` (année choisie ou null),
     * `currentYear`, `focusSlug` (série à repérer).
     */
    /** @type {{ groups: { group: string, rows: object[] }[], axis: { start: number, end: number, span: number }, year: number|null, currentYear: number, focusSlug?: string|null }} */
    let { groups, axis, year, currentYear, focusSlug = null } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const fmt = (n) => Number(n ?? 0).toLocaleString(lang);

    const ticks = $derived(decadeTicks(axis));
    const flag = $derived(year === null ? null : yearLeft(year, axis));

    /** Texte lu pour une rangée : période, exemplaires, état à l'année choisie. */
    function spoken(row, state) {
        const parts = [periodLabel(row, t)];
        if (Number.isInteger(row.units_built)) {
            parts.push(t('materials_timeline.units_long', { count: fmt(row.units_built) }));
        }
        if (year !== null && state !== 'no') {
            parts.push(t(`materials_timeline.state.${state}`));
        }
        return parts.join(', ');
    }
</script>

<div
    class={['chart', { flagged: year !== null }]}
    style:--decades={axis.span / 10}
    style:--flag={flag === null ? undefined : `${flag}%`}
>
    {#each groups as g (g.group)}
        <section class="group" aria-labelledby="tl-{g.group}">
            <h3 id="tl-{g.group}">
                {t(`materials_timeline.groups.${g.group}`)}
                <span class="count"
                    >{t('materials_timeline.group_count', { count: fmt(g.rows.length) })}</span
                >
            </h3>
            <div class="axis" aria-hidden="true">
                <span class="spacer"></span>
                <span class="scale">
                    {#each ticks as tick (tick.year)}
                        {#if tick.year % 20 === 0}
                            <span class="tick" style:left="{tick.left}%">{tick.year}</span>
                        {/if}
                    {/each}
                    {#if year !== null}
                        <span class="flag" style:left="{flag}%">{year}</span>
                    {/if}
                </span>
            </div>
            <ul class="rows">
                {#each g.rows as row (row.slug)}
                    {@const bar = barOf(row, axis, currentYear)}
                    {@const state = year === null ? 'none' : stateIn(row, year)}
                    <li
                        class={['row', state, { focus: row.slug === focusSlug }]}
                        data-slug={row.slug}
                    >
                        <Link
                            href="/materials/{row.slug}"
                            class="link"
                            aria-current={row.slug === focusSlug ? 'true' : undefined}
                        >
                            <span class="label">
                                <span class="name">{row.name}</span>
                                {#if year !== null && state !== 'no' && Number.isInteger(row.units_built)}
                                    <span class="units" aria-hidden="true"
                                        >{t('materials_timeline.units', {
                                            count: fmt(row.units_built),
                                        })}</span
                                    >
                                {/if}
                            </span>
                            <span class="vff-sr-only">{spoken(row, state)}</span>
                            <span class="track" aria-hidden="true">
                                {#if bar}
                                    <span
                                        class={['bar', bar.kind]}
                                        style:left="{bar.left}%"
                                        style:width="{bar.width}%"
                                        style:--fade="{(1 - bar.fade) * 100}%"
                                    ></span>
                                {/if}
                            </span>
                        </Link>
                    </li>
                {/each}
            </ul>
        </section>
    {/each}
</div>

<style>
    .chart {
        --label-w: clamp(10rem, 21vw, 18rem);
        --row-h: 2.25rem;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-6);
    }
    .group h3 {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-3);
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-lead);
    }
    .count {
        font-size: var(--vff-fs-sm);
        font-weight: 400;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }

    .axis,
    .row :global(.link) {
        display: grid;
        grid-template-columns: var(--label-w) minmax(0, 1fr);
        align-items: center;
    }
    .axis {
        position: relative;
        height: 2rem;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .scale {
        position: relative;
        height: 100%;
    }
    .tick {
        position: absolute;
        top: 0;
        transform: translateX(-50%);
        white-space: nowrap;
    }
    .tick:first-child {
        transform: none;
    }
    .flag {
        position: absolute;
        top: 0;
        transform: translateX(-50%);
        padding: 0 var(--vff-space-2);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-accent-ink);
        color: var(--vff-text-inverse);
        font-weight: 700;
        white-space: nowrap;
    }

    .rows {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .row {
        border-bottom: 1px solid var(--vff-border);
    }
    .row :global(.link) {
        min-height: var(--row-h);
        color: var(--vff-text);
        text-decoration: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        border-radius: var(--vff-radius-xs);
    }
    .row :global(.link:hover) {
        background-color: var(--vff-accent-soft);
    }
    .row :global(.link:hover .name) {
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .label {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        column-gap: var(--vff-space-2);
        min-width: 0;
        padding-right: var(--vff-space-3);
    }
    .name {
        min-width: 0;
        padding-block: 2px;
        overflow-wrap: anywhere;
    }

    /* Piste : repères de décennie en fond, ligne de l'année choisie par-dessus. */
    .track {
        grid-column: 2;
        grid-row: 1;
        position: relative;
        align-self: stretch;
        background-image: repeating-linear-gradient(
            to right,
            var(--vff-border) 0 1px,
            transparent 1px calc(100% / var(--decades))
        );
    }
    .chart.flagged .track::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: var(--flag);
        width: 2px;
        background-color: var(--vff-accent-ink);
        pointer-events: none;
    }
    .bar {
        position: absolute;
        top: 50%;
        height: 0.9rem;
        min-width: 6px;
        transform: translateY(-50%);
        border-radius: var(--vff-radius-pill);
        background: var(--vff-accent-strong);
    }
    /* Fin non renseignée : le bord droit s'estompe (la barre n'affirme pas qu'elle s'arrête là). */
    .bar.withdrawn,
    .bar.unknown {
        background: linear-gradient(to right, var(--vff-accent-strong) var(--fade), transparent);
    }
    .bar.ongoing {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    /* Année choisie : les séries en service ressortent, les autres s'effacent. */
    .row.no {
        opacity: 0.4;
    }
    .row.yes .name,
    .row.maybe .name {
        font-weight: 700;
    }
    .row.maybe .bar {
        outline: 2px dashed var(--vff-accent-ink);
        outline-offset: 1px;
    }
    .units {
        flex: none;
        font-size: var(--vff-fs-sm);
        font-weight: 400;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .row.focus {
        background-color: var(--vff-bg-accent);
        outline: 2px solid var(--vff-accent-ink);
    }
    .row :global(.link:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 2px;
    }
    @media (prefers-reduced-motion: no-preference) {
        .row.no {
            transition: opacity var(--vff-transition);
        }
    }
</style>
