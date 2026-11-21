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
     * sous deux phases) ; elle ne défile qu'au-delà de la hauteur de la carte (variable
     * --phases-max posée par la fiche, proche de la hauteur de l'écran), et dit alors combien de
     * dates restent plus bas (bouton qui fait défiler). Sur téléphone : liste complète.
     */
    let { phases = [], selected = $bindable(null), best = null } = $props();

    const scale = $derived(maxKnownLength(phases));
    const lang = $derived($i18n.language ?? 'fr');
    const dateLabel = (p) => firstOfMonth(phaseDateLabel(p, $i18n.t, formatLocale(lang)), lang);
    let buttons = $state([]);
    let listEl = $state();
    // Dates sous le bas visible de la liste (0 : tout est visible, ou la liste est au bout).
    let below = $state(0);
    let overflowing = $state(false);

    function measureBelow() {
        if (!listEl) return;
        overflowing = listEl.scrollHeight > listEl.clientHeight + 2;
        const limit = listEl.scrollTop + listEl.clientHeight - 24;
        below = overflowing ? buttons.filter((b) => b && b.offsetTop > limit).length : 0;
    }

    function scrollDown() {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        listEl?.scrollBy({ top: listEl.clientHeight * 0.8, behavior: reduce ? 'auto' : 'smooth' });
    }

    // La phase choisie (à l'ouverture : la longueur maximale ; ou depuis l'histoire) est rendue
    // visible dans la liste, sans faire défiler la page.
    $effect(() => {
        const btn = buttons[selected];
        if (!listEl || !btn) return;
        const top = btn.offsetTop;
        const bottom = top + btn.offsetHeight;
        if (top < listEl.scrollTop || bottom > listEl.scrollTop + listEl.clientHeight) {
            // Au tiers haut de la liste, calée sur le haut d'une phase (pas de phase coupée en haut).
            const target = top - listEl.clientHeight / 3;
            const first = buttons.filter((b) => b && b.offsetTop <= target).at(-1);
            listEl.scrollTop = first ? first.offsetTop : 0;
        }
        measureBelow();
    });

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
        bind:this={listEl}
        onkeydown={onKeydown}
        onscroll={measureBelow}
    >
        {#each phases as p, i (i)}
            {@const strip = phaseStrip(p, scale)}
            <li role="presentation">
                <button
                    type="button"
                    role="option"
                    aria-selected={selected === i}
                    tabindex={selected === i ? 0 : -1}
                    class={['phase', `kind-${p.kind}`, { active: selected === i }]}
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
                        <span class={['date', { undated: p.precision === 'range' }]}
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
    {#if overflowing}
        <button
            type="button"
            class={['more-below', { hidden: below === 0 }]}
            aria-hidden={below === 0 ? 'true' : undefined}
            tabindex={below === 0 ? -1 : undefined}
            onclick={scrollDown}
        >
            <i class="bi bi-chevron-double-down" aria-hidden="true"></i>
            {$i18n.t('lines.sheet.phases.more_below', { count: below || 1 })}
        </button>
    {/if}
</div>

<svelte:window onresize={measureBelow} />

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
        min-height: 36px;
        min-width: 36px;
        justify-content: center;
        padding: 4px 10px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
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
    /* Téléphone : cible tactile de 44 px. */
    @media (max-width: 600px) {
        .tool {
            min-width: 44px;
            min-height: 44px;
        }
    }
    .tool.best-jump[aria-pressed='true'] {
        border-color: var(--vff-accent);
        background: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
    }
    /* Hauteur de la liste : celle de ses phases, plafonnée à la hauteur de la carte (posée par la
       fiche ; aucune limite sur téléphone, où la liste est sous la carte). */
    .phases {
        position: relative;
        max-height: var(--phases-max, none);
        margin: 0;
        padding: 0 4px 0 0;
        overflow-y: auto;
        list-style: none;
    }
    /* Fil de la chronologie : un tronçon par phase, du haut au bas de la phase, centré sous le
       repère (colonne de 32 px, repère à 24 px du haut : 8 px de marge + moitié du repère). Il
       commence au premier repère et finit au dernier, et défile avec la liste (un fil posé sur la
       liste elle-même s'arrêtait au bas de la partie visible et dépassait sous le dernier repère).
       Il passe au-dessus du fond de la phase survolée ou choisie, sous le repère. */
    .phases li {
        position: relative;
        font-size: var(--vff-fs-sm);
    }
    .phases li::before {
        content: '';
        position: absolute;
        z-index: 1;
        top: 0;
        bottom: 0;
        left: 15px;
        width: 2px;
        background: var(--vff-border-strong);
        pointer-events: none;
    }
    .phases li:first-child::before {
        top: 24px;
    }
    .phases li:last-child::before {
        bottom: calc(100% - 24px);
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
        position: relative;
        z-index: 2;
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
        font-size: 0.9375rem;
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
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-accent-ink);
    }
    /* Indice de suite : « 9 dates plus bas », sous la liste ; il garde sa place quand on arrive
       au bout (pas de saut). */
    .more-below {
        display: inline-flex;
        align-self: flex-start;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        margin-top: 8px;
        padding: 4px 12px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        cursor: pointer;
    }
    .more-below:hover {
        background: var(--vff-bg-muted);
    }
    .more-below.hidden {
        visibility: hidden;
    }
    @media (prefers-reduced-motion: no-preference) {
        .phase,
        .tool {
            transition: background-color var(--vff-transition);
        }
    }
</style>
