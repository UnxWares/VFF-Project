<script>
    import { onDestroy } from 'svelte';
    import { i18n } from '$lib/i18n.js';
    import { visibleLabels } from '$lib/timelineLabels.js';

    /**
     * Frise des époques : choix de la décennie affichée par la carte, dessinée comme un schéma
     * de ligne (les décennies sont les gares, la partie parcourue est le rail orange).
     *
     * Clavier : la frise est un curseur (role="slider") ; flèches = décennie précédente ou
     * suivante, Début / Fin = première ou dernière, Page haut / bas = cinq décennies. Les points
     * de la frise sont des raccourcis à la souris et au doigt. La décennie « actuelle » montre
     * le réseau d'aujourd'hui : elle est affichée « Aujourd'hui », jamais comme l'année 2030.
     *
     * Props :
     *   eras       : années disponibles (ex. [1830, 1840, …, 2030])
     *   emptyYears : décennies sans tracé reconstitué (point en pointillé, restent sélectionnables)
     *   value      : décennie sélectionnée (bindable)
     *   currentEra : décennie actuelle (défaut : la dernière)
     *   labels     : { [année]: nom de l'époque } (noms en français, affichés sur le site français)
     *   playIntervalMs : durée d'une étape en lecture automatique
     *   onPlay     : lecture confiée à la page (/maps : mode Lecture, EraPlayer.svelte) ; le bouton
     *                l'appelle au lieu de lancer la minuterie de la frise
     *   playing    : lecture de la page en cours (icône du bouton), avec `onPlay`
     */
    /** @type {{ eras?: number[], emptyYears?: number[], value?: number, currentEra?: number|null, labels?: Record<number, string>, playIntervalMs?: number, onPlay?: (() => void)|null, playing?: boolean }} */
    let {
        eras = [1990, 2000, 2010, 2020, 2030],
        emptyYears = [],
        value = $bindable(2030),
        currentEra = null,
        labels = {},
        playIntervalMs = 2500,
        onPlay = null,
        playing = false,
    } = $props();

    /** Traduction (toujours une chaîne : i18next la type aussi en objet). */
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const todayEra = $derived(currentEra ?? eras[eras.length - 1]);
    const thisYear = new Date().getFullYear();

    let timerPlaying = $state(false);
    const isPlaying = $derived(onPlay ? playing : timerPlaying);
    let timer = null;
    // Annonce des changements qui ne viennent pas du curseur (lecture, période, point cliqué) :
    // le curseur annonce déjà sa valeur quand on le manipule au clavier.
    let announcement = $state('');
    let trackEl = $state();

    // Périodes par siècle : 1830-1890, 1900-1990, « Depuis 2000 ».
    const groups = $derived.by(() => {
        const byCentury = {};
        for (const year of eras) {
            const start = Math.floor(year / 100) * 100;
            (byCentury[start] ??= []).push(year);
        }
        return Object.entries(byCentury)
            .map(([start, years]) => [Number(start), years])
            .sort((a, b) => a[0] - b[0])
            .map(([start, years]) => ({
                start,
                years,
                label:
                    years.length === 1
                        ? String(years[0])
                        : years.includes(todayEra)
                          ? t('maps.timeline.since', { year: years[0] })
                          : `${years[0]}-${years[years.length - 1]}`,
            }));
    });

    const groupStart = $derived(Math.floor(value / 100) * 100);
    const currentYears = $derived(groups.find((g) => g.start === groupStart)?.years ?? eras);
    const span = $derived(currentYears[currentYears.length - 1] - currentYears[0] || 1);
    const percentFor = (year) => ((year - currentYears[0]) / span) * 100;
    const fillPercent = $derived(percentFor(value));

    // Étiquettes qui tiennent sans se toucher à la largeur réelle de la frise (1900-1990 à 1 280 px
    // donnait « 19001910 ») : mesurées dans le navigateur, une sur deux ou sur trois au besoin, la
    // décennie choisie et les deux bouts d'abord (lib/timelineLabels.js). Les points restent tous.
    let stopsWidth = $state(0);
    /** @type {number[]} */
    let labelWidths = $state([]);
    const shownLabels = $derived(
        stopsWidth > 0
            ? visibleLabels(
                  currentYears.map((y) => (percentFor(y) / 100) * stopsWidth),
                  currentYears.map((_, i) => labelWidths[i] ?? 0),
                  currentYears.map(stopLabel),
                  currentYears.indexOf(value),
              )
            : currentYears.map(() => true),
    );

    const isToday = $derived(value === todayEra);
    const isEmpty = $derived(emptyYears.includes(value));
    const displayYear = $derived(isToday ? thisYear : value);
    // Nom de l'époque (base, en français) sur le site français ; mention générique sinon.
    const caption = $derived(
        isEmpty
            ? t('maps.era.no_data_short')
            : $i18n.language === 'fr' && labels[value]
              ? labels[value]
              : isToday
                ? t('maps.timeline.today_caption')
                : t('maps.timeline.era'),
    );

    function stopLabel(year) {
        return year === todayEra ? t('maps.timeline.today') : String(year);
    }

    function valueText(year) {
        if (year === todayEra) return t('maps.timeline.value_today');
        if (emptyYears.includes(year)) return t('maps.timeline.value_empty', { year });
        return String(year);
    }

    function select(year, announce = true) {
        if (!eras.includes(year)) return;
        value = year;
        announcement = announce ? valueText(year) : '';
    }

    function step(delta) {
        const idx = eras.indexOf(value);
        const next = Math.max(0, Math.min(eras.length - 1, idx + delta));
        return eras[next];
    }

    function onSliderKeydown(e) {
        const keys = {
            ArrowLeft: () => step(-1),
            ArrowDown: () => step(-1),
            ArrowRight: () => step(1),
            ArrowUp: () => step(1),
            PageDown: () => step(-5),
            PageUp: () => step(5),
            Home: () => eras[0],
            End: () => eras[eras.length - 1],
        };
        if (!keys[e.key]) return;
        e.preventDefault();
        stopPlay();
        select(keys[e.key](), false);
    }

    // Clic ou glissé sur le rail : décennie la plus proche de la position du pointeur.
    function nearestYear(clientX) {
        const rect = trackEl.getBoundingClientRect();
        const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / (rect.width || 1)));
        const target = currentYears[0] + ratio * span;
        return currentYears.reduce((best, y) =>
            Math.abs(y - target) < Math.abs(best - target) ? y : best,
        );
    }

    function onPointerDown(e) {
        if (e.button !== 0) return;
        stopPlay();
        e.currentTarget.setPointerCapture?.(e.pointerId);
        select(nearestYear(e.clientX), false);
    }

    function onPointerMove(e) {
        if (!e.currentTarget.hasPointerCapture?.(e.pointerId)) return;
        const year = nearestYear(e.clientX);
        if (year !== value) select(year, false);
    }

    function pickGroup(g) {
        stopPlay();
        // Saut à la décennie la plus récente de la période.
        select(g.years[g.years.length - 1]);
    }

    function togglePlay() {
        if (onPlay) {
            onPlay();
            return;
        }
        if (isPlaying) {
            stopPlay();
            return;
        }
        // Au bout de la frise : repartir du début.
        if (value === eras[eras.length - 1]) select(eras[0]);
        timerPlaying = true;
        timer = setInterval(() => {
            const next = step(1);
            if (next === value) {
                stopPlay();
                return;
            }
            select(next);
        }, playIntervalMs);
    }

    function stopPlay() {
        timerPlaying = false;
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    onDestroy(stopPlay);
</script>

<div class="timeline" role="group" aria-label={t('maps.timeline.aria_label')}>
    <div class="timeline-head">
        <button
            type="button"
            class="play-btn"
            data-playback-control
            onclick={togglePlay}
            aria-label={isPlaying ? t('maps.timeline.pause') : t('maps.timeline.play')}
            title={isPlaying ? t('maps.timeline.pause') : t('maps.timeline.play')}
        >
            <i class="bi {isPlaying ? 'bi-pause-fill' : 'bi-play-fill'}" aria-hidden="true"></i>
        </button>

        <p class="year-display">
            <span class="year-value">{displayYear}</span>
            <span class="year-caption">{caption}</span>
        </p>
    </div>

    {#if groups.length > 1}
        <div class="periods" role="group" aria-label={t('maps.timeline.group_aria')}>
            {#each groups as g (g.start)}
                <button
                    type="button"
                    class="period"
                    aria-pressed={groupStart === g.start}
                    onclick={() => pickGroup(g)}
                >
                    {g.label}
                </button>
            {/each}
        </div>
    {/if}

    <div class="track-area">
        <div
            class="slider"
            role="slider"
            tabindex="0"
            aria-label={t('maps.timeline.slider')}
            aria-valuemin={eras[0]}
            aria-valuemax={eras[eras.length - 1]}
            aria-valuenow={value}
            aria-valuetext={valueText(value)}
            onkeydown={onSliderKeydown}
            onpointerdown={onPointerDown}
            onpointermove={onPointerMove}
        >
            <div class="rail" bind:this={trackEl}>
                <div class="rail-fill" style:width="{fillPercent}%"></div>
            </div>
        </div>
        <div class="stops" bind:clientWidth={stopsWidth}>
            {#each currentYears as year, i (year)}
                <button
                    type="button"
                    tabindex="-1"
                    aria-hidden="true"
                    class={[
                        'stop',
                        {
                            active: value === year,
                            past: value > year,
                            empty: emptyYears.includes(year),
                            first: i === 0,
                            last: i === currentYears.length - 1,
                            'label-hidden': !shownLabels[i],
                        },
                    ]}
                    style:left="{percentFor(year)}%"
                    onclick={() => {
                        stopPlay();
                        select(year);
                    }}
                >
                    <span class="stop-dot"></span>
                    <span class="stop-label" bind:offsetWidth={labelWidths[i]}
                        >{stopLabel(year)}</span
                    >
                </button>
            {/each}
        </div>
    </div>

    <p class="vff-sr-only" aria-live="polite">{announcement}</p>
</div>

<style>
    .timeline {
        display: flex;
        align-items: center;
        gap: 16px 24px;
        padding: 16px 24px 18px;
        border-top: 1px solid var(--vff-border);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
    }
    .timeline-head {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: 14px;
    }

    .play-btn {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: 50%;
        background-color: var(--vff-accent);
        color: var(--vff-on-accent);
        font-size: 22px;
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .play-btn:hover {
        background-color: var(--vff-accent-hover);
    }

    /* Largeur fixe : le nom de l'époque change pendant la lecture sans décaler la frise. */
    .year-display {
        display: flex;
        flex-direction: column;
        gap: 2px;
        width: 11.5rem;
        margin: 0;
    }
    .year-value {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-figure);
        font-weight: 700;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        color: var(--vff-accent-strong);
    }
    .year-caption {
        display: -webkit-box;
        min-height: 2.6em;
        overflow: hidden;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.3;
        color: var(--vff-text-muted);
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    /* Périodes : boutons à bascule (aria-pressed), même dessin que le choix de couleur de la
       légende. */
    .periods {
        display: flex;
        flex-shrink: 0;
        gap: 6px;
    }
    .period {
        min-height: 36px;
        padding: 6px 14px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background-color: transparent;
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .period:hover {
        background-color: var(--vff-bg-muted);
    }
    .period[aria-pressed='true'] {
        border-color: var(--vff-accent);
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-weight: 600;
    }

    /* Rail et décennies. Les points sont posés entre 18 px des bords : l'étiquette du premier
       et du dernier ne touche jamais le cadre. */
    .track-area {
        position: relative;
        flex: 1;
        min-width: 0;
        height: 56px;
    }
    .slider {
        position: absolute;
        inset: 0;
        padding: 14px 18px 0;
        border-radius: var(--vff-radius-sm);
        cursor: pointer;
        touch-action: none;
    }
    .slider:focus-visible {
        outline-offset: 0;
    }
    .rail {
        position: relative;
        height: 4px;
        margin-top: 6px;
        border-radius: 2px;
        background-color: var(--vff-border-strong);
    }
    .rail-fill {
        position: absolute;
        inset: 0 auto 0 0;
        border-radius: 2px;
        background-color: var(--vff-accent);
        transition: width 300ms ease;
    }
    .stops {
        position: absolute;
        top: 0;
        right: 18px;
        bottom: 0;
        left: 18px;
        pointer-events: none;
    }
    .stop {
        position: absolute;
        top: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 0;
        background: transparent;
        transform: translateX(-50%);
        cursor: pointer;
        pointer-events: auto;
    }
    .stop-dot {
        width: 12px;
        height: 12px;
        border: 2px solid var(--vff-border-control);
        border-radius: 50%;
        background-color: var(--vff-bg-elevated);
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .stop:hover .stop-dot {
        border-color: var(--vff-accent);
    }
    .stop.past .stop-dot {
        border-color: var(--vff-accent);
        background-color: var(--vff-accent);
    }
    /* Décennie choisie : gare du schéma de ligne, cercle blanc à gros contour orange. */
    .stop.active .stop-dot {
        width: 18px;
        height: 18px;
        border-width: 4px;
        border-color: var(--vff-accent);
        background-color: var(--vff-bg-elevated);
    }
    .stop.empty:not(.active) .stop-dot {
        border-style: dashed;
        background-color: var(--vff-bg-elevated);
    }
    .stop-label {
        position: absolute;
        top: 30px;
        left: 50%;
        transform: translateX(-50%);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
        color: var(--vff-text-muted);
        white-space: nowrap;
    }
    .stop.first .stop-label {
        left: 4px;
        transform: none;
    }
    .stop.last .stop-label {
        right: 4px;
        left: auto;
        transform: none;
    }
    .stop.active .stop-label {
        color: var(--vff-text);
        font-weight: 600;
    }
    /* Étiquette sans la place voulue : cachée mais toujours mesurée (visibility garde sa largeur). */
    .stop.label-hidden .stop-label {
        visibility: hidden;
    }

    /* Jusqu'à 1 180 px : la frise passe sous le titre et les périodes, sur toute la largeur (à côté,
       elle était trop courte : « 2020 » et « Aujourd'hui » se chevauchaient vers 1 024 px). */
    @media (max-width: 1180px) {
        .timeline {
            flex-wrap: wrap;
        }
        .track-area {
            flex-basis: 100%;
        }
    }
    @media (max-width: 900px) {
        .timeline {
            padding: 14px 16px 16px;
        }
    }
    /* Cadre bas (carte intégrée dans une autre page, téléphone à l'horizontale) : frise compacte (la
       légende de l'année s'efface, les périodes restent : seul moyen de changer de siècle) ; la carte
       garde la place. */
    @media (max-height: 520px) {
        .timeline {
            gap: 8px 16px;
            padding: 8px 16px 10px;
        }
        .year-caption {
            display: none;
        }
        .period {
            min-height: 32px;
            padding-block: 4px;
        }
        .track-area {
            height: 48px;
        }
    }
    /* Téléphone : bouton et année, puis périodes, puis frise. */
    @media (max-width: 600px) {
        .timeline-head {
            flex-basis: 100%;
        }
        .year-display {
            flex: 1;
            width: auto;
        }
        /* Les trois périodes tiennent sur une rangée dès 360 px environ ; plus étroit (320 px), la
           dernière passe dessous au lieu de déborder de sa pilule et du cadre (texte en 14 px,
           chaque bouton à la largeur de son libellé, cible tactile de 44 px). */
        .periods {
            flex-basis: 100%;
            flex-wrap: wrap;
            min-width: 0;
            gap: 4px;
        }
        .period {
            flex: 1 1 auto;
            min-height: 44px;
            padding-inline: 4px;
            font-size: var(--vff-fs-xs);
        }
    }
</style>
