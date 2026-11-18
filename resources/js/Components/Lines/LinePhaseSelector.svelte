<script>
    import { i18n } from '$lib/i18n.js';
    import { firstOfMonth, formatLocale, tieUnits } from '$lib/typo.js';
    import {
        PHASE_KIND_ICONS,
        categoryColor,
        changeVerbKey,
        formatKm,
        maxKnownLength,
        phaseDateLabel,
        phaseStrip,
        stepPhase,
        summarizeChanges,
    } from '$lib/linePhases.js';

    /**
     * Sélecteur des phases de la ligne, à côté de la carte : une entrée par date où le
     * fer ou le trafic change. Chaque entrée montre sa date, ce qui change et le ruban
     * de composition de TOUTE la ligne (longueur proportionnelle à la plus grande
     * longueur connue : on voit la ligne grandir puis décliner). Flèches haut/bas pour
     * passer d'une phase à l'autre. La liste prend la hauteur de ses phases (pas de fil vide
     * sous deux phases) et ne défile qu'au-delà d'une hauteur de carte.
     */
    let { phases = [], selected = $bindable(null), best = null } = $props();

    const scale = $derived(maxKnownLength(phases));
    const lang = $derived($i18n.language ?? 'fr');
    const dateLabel = (p) => firstOfMonth(phaseDateLabel(p, $i18n.t, formatLocale(lang)), lang);
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

    /** Ligne de résumé : le plus grand changement de la phase (et le nombre d'autres). */
    function headline(p) {
        const { changes } = summarizeChanges(p);
        const c = changes[0];
        if (!c) return $i18n.t('lines.phases.minor_only');
        const text = $i18n.t(changeVerbKey(c.from, c.to), {
            km: tieUnits(formatKm(c.length_m, formatLocale(lang))),
            to: $i18n.t(`lines.categories.${c.to}`),
            from: $i18n.t(`lines.categories.${c.from}`),
        });
        return changes.length > 1
            ? `${text} ${$i18n.t('lines.phases.and_more', { count: changes.length - 1 })}`
            : text;
    }
</script>

<div class="selector">
    <div class="toolbar">
        <button
            type="button"
            class="tool"
            onclick={() => select(stepPhase(selected, -1, phases.length))}
            disabled={!selected}
        >
            <i class="bi bi-chevron-up" aria-hidden="true"></i>
            <span class="vff-sr-only">{$i18n.t('lines.phases.previous')}</span>
        </button>
        <button
            type="button"
            class="tool"
            onclick={() => select(stepPhase(selected, 1, phases.length))}
            disabled={selected === phases.length - 1}
        >
            <i class="bi bi-chevron-down" aria-hidden="true"></i>
            <span class="vff-sr-only">{$i18n.t('lines.phases.next')}</span>
        </button>
        {#if best != null}
            <button
                type="button"
                class="tool best-jump"
                onclick={() => select(best)}
                aria-pressed={selected === best}
                title={$i18n.t('lines.sheet.phases.show_best_label')}
            >
                <i class="bi bi-arrows-angle-expand" aria-hidden="true"></i>
                {$i18n.t('lines.sheet.phases.show_best')}
            </button>
        {/if}
    </div>

    <ol
        class="phases"
        role="listbox"
        aria-label={$i18n.t('lines.phases.title')}
        tabindex="-1"
        onkeydown={onKeydown}
    >
        {#each phases as p, i (i)}
            {@const strip = phaseStrip(p, scale)}
            <li role="presentation">
                <button
                    type="button"
                    role="option"
                    aria-selected={selected === i}
                    tabindex={selected === i ? 0 : -1}
                    class="phase kind-{p.kind}"
                    class:active={selected === i}
                    bind:this={buttons[i]}
                    onclick={() => select(i)}
                >
                    <span
                        class="marker"
                        style="--c: {categoryColor(
                            summarizeChanges(p).changes[0]?.to ?? 'unknown',
                        )}"
                    >
                        <i class="bi {PHASE_KIND_ICONS[p.kind] ?? 'bi-circle'}" aria-hidden="true"
                        ></i>
                    </span>
                    <span class="body">
                        <span class="date" class:undated={p.precision === 'range'}
                            >{dateLabel(p)}</span
                        >
                        <span class="what">{headline(p)}</span>
                        <span class="strip" aria-hidden="true">
                            {#each strip as s (s.cat)}
                                <span
                                    class="seg cat-{s.cat}"
                                    style="width: {s.pct}%; --c: {categoryColor(s.cat)}"
                                ></span>
                            {/each}
                        </span>
                        {#if i === best}
                            <span class="best">{$i18n.t('lines.sheet.phases.best_badge')}</span>
                        {/if}
                    </span>
                </button>
            </li>
        {/each}
    </ol>
</div>

<style>
    .selector {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 10px;
    }
    .tool {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 32px;
        padding: 4px 10px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        cursor: pointer;
    }
    .tool:hover:not(:disabled) {
        background: var(--vff-bg-muted);
    }
    .tool:disabled {
        border-color: var(--vff-border);
        color: var(--vff-text-muted);
        cursor: default;
    }
    .tool.best-jump {
        margin-left: auto;
    }
    .tool.best-jump[aria-pressed='true'] {
        border-color: var(--vff-accent);
        background: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
    }
    /* Hauteur de la liste : celle de ses phases, plafonnée à la hauteur de la carte. */
    .phases {
        position: relative;
        max-height: 440px;
        margin: 0;
        padding: 0 4px 0 0;
        overflow-y: auto;
        list-style: none;
    }
    /* Fil de la chronologie, derrière les repères. */
    .phases::before {
        content: '';
        position: absolute;
        top: 24px;
        bottom: 24px;
        left: 15px;
        width: 2px;
        background: var(--vff-border);
    }
    .phases li {
        font-size: var(--vff-fs-sm);
    }
    .phase {
        position: relative;
        display: grid;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: 12px;
        width: 100%;
        padding: 8px 10px 10px 0;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text);
        text-align: left;
        cursor: pointer;
    }
    .phase:hover {
        background: var(--vff-bg-muted);
    }
    .phase.active {
        background: var(--vff-bg-accent);
    }
    .marker {
        z-index: 1;
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border: 2px solid var(--c);
        border-radius: 50%;
        background: var(--vff-bg);
        color: var(--c);
        font-size: 14px;
    }
    .phase.active .marker {
        background: var(--c);
        color: var(--vff-bg);
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }
    .date {
        font-family: var(--vff-font-display);
        font-size: 17px;
        font-weight: 700;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }
    .date.undated {
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .what {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        line-height: 1.4;
        color: var(--vff-text-muted);
    }
    .phase.active .what {
        color: var(--vff-text);
    }
    .strip {
        display: flex;
        height: 6px;
        margin-top: 4px;
        overflow: hidden;
        border-radius: 3px;
    }
    .seg {
        min-width: 1px;
        background: var(--c);
    }
    .seg.cat-closed,
    .seg.cat-construction {
        background: repeating-linear-gradient(90deg, var(--c) 0 5px, transparent 5px 8px);
    }
    .seg.cat-dismantled,
    .seg.cat-razed,
    .seg.cat-gone,
    .seg.cat-velorail {
        background: repeating-linear-gradient(90deg, var(--c) 0 2px, transparent 2px 5px);
    }
    .best {
        align-self: flex-start;
        margin-top: 4px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        color: var(--vff-accent-ink);
    }
    @media (max-width: 900px) {
        .phases {
            max-height: 360px;
        }
    }
    @media (prefers-reduced-motion: no-preference) {
        .phase,
        .tool {
            transition: background-color var(--vff-transition);
        }
    }
</style>
