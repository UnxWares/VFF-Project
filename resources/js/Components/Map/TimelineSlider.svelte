<script>
    import { onDestroy } from 'svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Slider temporel VFF - sélection d'une époque par décennie.
     *
     * Pour l'instant : snap sur les époques seedées (1990 → 2030).
     * À terme : slider continu année par année quand on aura des données fines.
     *
     * Props :
     *   eras : tableau des années disponibles (ex: [1900, 1910, …, 2030])
     *   emptyYears : décennies sans tracé reconstitué (marqueur estompé, restent sélectionnables)
     *   value : époque sélectionnée (bindable)
     *   playIntervalMs : durée d'une étape en lecture auto (default 2500ms)
     */

    let {
        eras = [1990, 2000, 2010, 2020, 2030],
        emptyYears = [],
        value = $bindable(2030),
        playIntervalMs = 2500,
    } = $props();

    let isPlaying = $state(false);
    let timer = null;

    // Groupe les ères par "siècle décennal" : 1800's = 1800-1899, 1900's = 1900-1999, 2000's = 2000-2099.
    // L'utilisateur sélectionne une box, puis voit les marqueurs décennaux de ce groupe.
    const groups = $derived.by(() => {
        const map = new Map();
        for (const year of eras) {
            const groupStart = Math.floor(year / 100) * 100;
            if (!map.has(groupStart)) map.set(groupStart, []);
            map.get(groupStart).push(year);
        }
        // Libellé = plage réelle des décennies du groupe (« 1900-1990 »), neutre
        // quelle que soit la langue (avant : « 1900's » codé en dur).
        return Array.from(map.entries())
            .map(([start, years]) => ({
                start,
                label: years.length > 1 ? `${years[0]}-${years[years.length - 1]}` : `${years[0]}`,
                years,
            }))
            .sort((a, b) => a.start - b.start);
    });

    let selectedGroupStart = $state(Math.floor(2030 / 100) * 100);

    // Quand value change (ex. via clic sur un marqueur), aligne le group
    $effect(() => {
        const g = Math.floor(value / 100) * 100;
        if (g !== selectedGroupStart) selectedGroupStart = g;
    });

    const currentYears = $derived(
        groups.find((g) => g.start === selectedGroupStart)?.years ?? eras,
    );
    const minYear = $derived(currentYears[0]);
    const maxYear = $derived(currentYears[currentYears.length - 1]);
    const span = $derived(maxYear - minYear || 1);

    function percentFor(year) {
        return ((year - minYear) / span) * 100;
    }

    function selectIndex(i) {
        const list = currentYears;
        const clamped = Math.max(0, Math.min(list.length - 1, i));
        value = list[clamped];
    }

    function step(delta) {
        // Parcourt toutes les décennies (pas seulement le groupe affiché) :
        // l'effet ci-dessus bascule le groupe automatiquement.
        const list = eras;
        const idx = list.indexOf(value);
        const next = idx + delta;
        if (next < 0 || next >= list.length) {
            stopPlay();
            return;
        }
        value = list[next];
    }

    function pickGroup(start) {
        selectedGroupStart = start;
        const years = groups.find((g) => g.start === start)?.years ?? [];
        if (years.length) value = years[years.length - 1]; // saut au plus récent du groupe
    }

    function togglePlay() {
        if (isPlaying) {
            stopPlay();
            return;
        }
        // Si on est au bout, repartir du début
        if (value === eras[eras.length - 1]) value = eras[0];
        isPlaying = true;
        timer = setInterval(() => step(1), playIntervalMs);
    }

    function stopPlay() {
        isPlaying = false;
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function onKeydown(e) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            step(-1);
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            step(1);
        } else if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            togglePlay();
        }
    }

    onDestroy(stopPlay);

    const fillPercent = $derived(percentFor(value));
</script>

<div class="timeline" role="group" aria-label={$i18n.t('maps.timeline.aria_label')}>
    <button
        type="button"
        class="play-btn"
        class:playing={isPlaying}
        onclick={togglePlay}
        aria-label={isPlaying ? $i18n.t('maps.timeline.pause') : $i18n.t('maps.timeline.play')}
        title={isPlaying ? $i18n.t('maps.timeline.pause') : $i18n.t('maps.timeline.play_title')}
    >
        <i class="bi {isPlaying ? 'bi-pause-fill' : 'bi-play-fill'}"></i>
    </button>

    <div class="year-display" aria-live="polite">
        <span class="year-value">{value}</span>
        <span class="year-label"
            >{emptyYears.includes(value)
                ? $i18n.t('maps.era.no_data_short')
                : $i18n.t('maps.timeline.era')}</span
        >
    </div>

    {#if groups.length > 1}
        <div class="group-tabs" role="tablist" aria-label={$i18n.t('maps.timeline.group_aria')}>
            {#each groups as g}
                <button
                    type="button"
                    role="tab"
                    aria-selected={selectedGroupStart === g.start}
                    class="group-tab"
                    class:active={selectedGroupStart === g.start}
                    onclick={() => pickGroup(g.start)}
                >
                    {g.label}
                    <span class="group-count">{g.years.length}</span>
                </button>
            {/each}
        </div>
    {/if}

    <div
        class="track-wrapper"
        role="slider"
        tabindex="0"
        aria-valuemin={minYear}
        aria-valuemax={maxYear}
        aria-valuenow={value}
        onkeydown={onKeydown}
    >
        <div class="track">
            <div class="track-fill" style="width: {fillPercent}%"></div>
            {#each currentYears as year, i}
                <button
                    type="button"
                    class="marker"
                    class:active={value === year}
                    class:past={value > year}
                    class:empty={emptyYears.includes(year)}
                    style="left: {percentFor(year)}%"
                    onclick={() => selectIndex(i)}
                    aria-label={emptyYears.includes(year)
                        ? $i18n.t('maps.timeline.year_empty_aria', { year })
                        : $i18n.t('maps.timeline.year_aria', { year })}
                    title={emptyYears.includes(year) ? $i18n.t('maps.era.no_data_short') : null}
                >
                    <span class="marker-dot"></span>
                    <span class="marker-label">{year}</span>
                </button>
            {/each}
        </div>
    </div>
</div>

<style>
    .timeline {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 18px 22px 24px;
        background-color: var(--vff-bg-elevated);
        border-top: 1px solid var(--vff-border);
    }

    .play-btn {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: 50%;
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        transition: all var(--vff-transition);
        box-shadow: 0 4px 12px rgba(255, 101, 0, 0.3);
    }
    .play-btn:hover {
        transform: scale(1.06);
        box-shadow: 0 6px 16px rgba(255, 101, 0, 0.4);
    }
    .play-btn.playing {
        background-color: var(--vff-text);
        box-shadow: 0 4px 12px var(--vff-shadow);
    }

    .year-display {
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        min-width: 80px;
    }
    .year-value {
        font-family: var(--vff-font-display);
        font-size: 32px;
        font-weight: 700;
        color: var(--vff-accent);
        line-height: 1;
    }
    .year-label {
        font-family: var(--vff-font-display);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--vff-text-muted);
        margin-top: 2px;
    }

    .group-tabs {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
        padding: 6px;
        background-color: var(--vff-bg-muted);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .group-tab {
        background: transparent;
        border: 0;
        padding: 6px 12px;
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 700;
        color: var(--vff-text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all var(--vff-transition);
    }
    .group-tab:hover:not(.active) {
        background-color: var(--vff-bg);
        color: var(--vff-text);
    }
    .group-tab.active {
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
        box-shadow: 0 2px 8px rgba(255, 101, 0, 0.3);
    }
    .group-tab .group-count {
        font-size: 11px;
        font-weight: 600;
        opacity: 0.7;
        padding: 1px 6px;
        background-color: rgba(255, 255, 255, 0.18);
        border-radius: var(--vff-radius-pill);
    }
    :global([data-theme='dark']) .group-tab:not(.active) .group-count {
        opacity: 1;
        color: #cbd5e1;
        background-color: rgba(255, 255, 255, 0.08);
    }
    .group-tab.active .group-count {
        background-color: rgba(255, 255, 255, 0.25);
        opacity: 0.95;
    }

    .track-wrapper {
        flex: 1;
        position: relative;
        padding: 18px 0 28px;
        outline: none;
    }
    .track-wrapper:focus-visible .track {
        box-shadow: 0 0 0 3px var(--vff-accent-soft);
    }

    .track {
        position: relative;
        height: 6px;
        background-color: var(--vff-bg-muted);
        border-radius: 3px;
        border: 1px solid var(--vff-border);
    }
    .track-fill {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        background: linear-gradient(90deg, var(--vff-accent-soft), var(--vff-accent));
        border-radius: 3px;
        transition: width 600ms cubic-bezier(0.4, 0, 0.2, 1);
    }

    .marker {
        position: absolute;
        top: 50%;
        transform: translate(-50%, -50%);
        background: transparent;
        border: 0;
        /* Zone de clic de 24 px autour de la pastille de 14 px (cible tactile). */
        padding: 5px;
        cursor: pointer;
    }
    .marker-dot {
        display: block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background-color: var(--vff-bg);
        border: 2px solid var(--vff-border-strong);
        transition: all var(--vff-transition);
    }
    .marker:hover .marker-dot {
        border-color: var(--vff-accent);
        transform: scale(1.15);
    }
    .marker.past .marker-dot {
        background-color: var(--vff-accent);
        border-color: var(--vff-accent);
    }
    .marker.active .marker-dot {
        background-color: var(--vff-accent);
        border-color: var(--vff-accent);
        transform: scale(1.4);
        box-shadow: 0 0 0 4px var(--vff-accent-soft);
    }
    /* Décennie pas encore reconstituée : sélectionnable mais estompée. */
    .marker.empty:not(.active) .marker-dot {
        background-color: transparent;
        border-style: dashed;
    }
    .marker.empty:not(.active) .marker-label {
        opacity: 0.55;
    }
    .marker-label {
        position: absolute;
        top: 23px;
        left: 50%;
        transform: translateX(-50%);
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        color: var(--vff-text-muted);
        white-space: nowrap;
        transition: color var(--vff-transition);
    }
    .marker.active .marker-label {
        color: var(--vff-accent);
    }

    @media (max-width: 640px) {
        .timeline {
            flex-wrap: wrap;
            gap: 14px;
            padding: 16px;
        }
        .year-display {
            min-width: auto;
        }
        .year-value {
            font-size: 26px;
        }
        .group-tabs {
            flex-basis: 100%;
            justify-content: center;
        }
        .track-wrapper {
            flex-basis: 100%;
            padding-top: 22px;
        }
    }
</style>
