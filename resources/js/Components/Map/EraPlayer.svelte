<script>
    import { i18n } from '$lib/i18n.js';
    import { stepYear } from '$lib/eraPlayback.js';
    import { formatNumber } from '$lib/typo.js';

    /**
     * Lecture des époques (/maps) : barre posée sous la carte à la place de la frise, le temps de la
     * lecture. L'année affichée en grand, la longueur de lignes en service à cette époque (si « Le réseau en
     * chiffres » l'a déjà calculée), l'avancement de la première ère à aujourd'hui, et les commandes :
     * décennie précédente, lecture ou pause, décennie suivante, vitesse, quitter. Rien n'est posé sur la
     * carte : elle reste entière, à toutes les largeurs.
     *
     * Clavier, le focus dans la barre : Espace = lecture ou pause, flèches gauche et droite = décennie
     * précédente ou suivante, Échap = quitter la lecture. Animations réduites (`stepOnly`) : pas de
     * lecture automatique, seulement les pas.
     *
     * La logique (minuterie, attente du dessin, arrêt au geste) est dans lib/eraPlayback.js et la page.
     */
    /** @type {{ year: number, years: number[], todayEra: number, openKm?: number|null, playing?: boolean, ended?: boolean, speed?: string, stepOnly?: boolean, status?: string, onToggle?: () => void, onPrev?: () => void, onNext?: () => void, onSpeed?: (speed: string) => void, onClose?: () => void, playButton?: HTMLButtonElement }} */
    let {
        year,
        years,
        todayEra,
        openKm = null,
        playing = false,
        ended = false,
        speed = 'normal',
        stepOnly = false,
        status = '',
        onToggle = () => {},
        onPrev = () => {},
        onNext = () => {},
        onSpeed = () => {},
        onClose = () => {},
        playButton = $bindable(),
    } = $props();

    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const thisYear = new Date().getFullYear();
    const isToday = $derived(year === todayEra);
    const shownYear = $derived(isToday ? thisYear : year);
    const km = $derived(openKm ? formatNumber(openKm, $i18n.language) : null);
    const detail = $derived(
        km
            ? t(isToday ? 'maps.playback.km_today' : 'maps.playback.km', { km })
            : isToday
              ? t('maps.timeline.today_caption')
              : '',
    );
    const playLabel = $derived(
        playing
            ? t('maps.playback.pause')
            : ended
              ? t('maps.playback.replay')
              : t('maps.playback.play'),
    );
    const canPrev = $derived(stepYear(years, year, -1) !== null);
    const canNext = $derived(stepYear(years, year, 1) !== null);
    // Avancement (décoratif : l'année est lue à chaque pas manuel et à chaque pause).
    const progress = $derived.by(() => {
        if (years.length < 2) return 100;
        const first = years[0];
        const last = years[years.length - 1];
        return Math.max(0, Math.min(100, ((year - first) / (last - first || 1)) * 100));
    });

    // Raccourcis de la barre (Espace, flèches, Échap) : écoutés sur la barre, les boutons gardant leur rôle.
    let barEl = $state();
    $effect(() => {
        const el = barEl;
        if (!el) return;
        el.addEventListener('keydown', onKeydown);
        return () => el.removeEventListener('keydown', onKeydown);
    });

    function onKeydown(/** @type {KeyboardEvent} */ e) {
        if (e.key === ' ' || e.key === 'Spacebar') {
            e.preventDefault();
            if (!stepOnly) onToggle();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            onPrev();
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            onNext();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            onClose();
        }
    }
</script>

<div
    class="era-player"
    role="group"
    aria-label={t('maps.playback.label')}
    data-playback-control
    data-year={year}
    data-playing={playing}
    bind:this={barEl}
>
    <p class="player-year">
        {#key shownYear}
            <span class="year">{shownYear}</span>
        {/key}
        {#if detail}<span class="detail">{detail}</span>{/if}
    </p>

    <div class="progress" aria-hidden="true">
        <div class="progress-rail">
            <div class="progress-fill" style:width="{progress}%"></div>
        </div>
        <div class="progress-ends">
            <span>{years[0]}</span>
            <span>{t('maps.timeline.today')}</span>
        </div>
    </div>

    <div class="steps">
        <button
            type="button"
            class="icon-btn"
            aria-label={t('maps.playback.prev')}
            title={t('maps.playback.prev')}
            aria-keyshortcuts="ArrowLeft"
            disabled={!canPrev}
            onclick={onPrev}
        >
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>
        {#if !stepOnly}
            <button
                type="button"
                class="icon-btn play"
                aria-label={playLabel}
                title={playLabel}
                aria-keyshortcuts="Space"
                bind:this={playButton}
                onclick={onToggle}
            >
                <i
                    class="bi {playing
                        ? 'bi-pause-fill'
                        : ended
                          ? 'bi-arrow-counterclockwise'
                          : 'bi-play-fill'}"
                    aria-hidden="true"
                ></i>
            </button>
        {/if}
        <button
            type="button"
            class="icon-btn"
            aria-label={t('maps.playback.next')}
            title={t('maps.playback.next')}
            aria-keyshortcuts="ArrowRight"
            disabled={!canNext}
            onclick={onNext}
        >
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
    </div>

    {#if stepOnly}
        <p class="step-note">{t('maps.playback.step_only')}</p>
    {:else}
        <div class="speeds" role="group" aria-label={t('maps.playback.speed')}>
            {#each ['slow', 'normal'] as option (option)}
                <button
                    type="button"
                    class="speed"
                    aria-pressed={speed === option}
                    onclick={() => onSpeed(option)}
                >
                    {t(`maps.playback.speed_${option}`)}
                </button>
            {/each}
        </div>
    {/if}

    <button
        type="button"
        class="icon-btn close"
        aria-label={t('maps.playback.close')}
        title={t('maps.playback.close')}
        onclick={onClose}
    >
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    <p class="vff-sr-only" role="status">{status}</p>
</div>

<style>
    /* Barre sous la carte, à la place de la frise (mêmes filet, fond et marges). Une rangée sur
       ordinateur : année, avancement, pas, vitesse, quitter. */
    .era-player {
        display: grid;
        grid-template-columns: 13rem minmax(0, 1fr) auto auto auto;
        grid-template-areas: 'year progress steps speeds close';
        align-items: center;
        gap: 12px 24px;
        flex: none;
        padding: 14px 16px 16px 24px;
        border-top: 1px solid var(--vff-border);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
    }
    .player-year {
        grid-area: year;
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        margin: 0;
    }
    /* L'année en grand, sans couleur d'accent : la carte reste le sujet. */
    .year {
        font-family: var(--vff-font-display);
        font-size: clamp(2rem, 1.7rem + 0.9vw, 2.75rem);
        font-weight: 600;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        animation: year-in 400ms ease both;
    }
    .detail {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    @keyframes year-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .year {
            animation: none;
        }
        .progress-fill {
            transition: none;
        }
    }

    /* Avancement : le rail de la frise, sans ses arrêts. */
    .progress {
        grid-area: progress;
        min-width: 0;
    }
    .progress-rail {
        position: relative;
        height: 4px;
        border-radius: 2px;
        background-color: var(--vff-border-strong);
    }
    .progress-fill {
        position: absolute;
        inset: 0 auto 0 0;
        border-radius: 2px;
        background-color: var(--vff-accent);
        transition: width 400ms ease;
    }
    .progress-ends {
        display: flex;
        justify-content: space-between;
        margin-top: 8px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
        color: var(--vff-text-muted);
    }

    .steps {
        grid-area: steps;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .icon-btn {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        padding: 0;
        border: 1px solid var(--vff-border-control);
        border-radius: 50%;
        background-color: transparent;
        color: var(--vff-text);
        font-size: 18px;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .icon-btn:hover:not(:disabled) {
        background-color: var(--vff-bg-muted);
    }
    .icon-btn:disabled {
        opacity: 0.45;
        cursor: default;
    }
    .icon-btn.play {
        width: 48px;
        height: 48px;
        border-color: var(--vff-accent);
        background-color: var(--vff-accent);
        color: var(--vff-on-accent);
        font-size: 24px;
    }
    .icon-btn.play:hover {
        background-color: var(--vff-accent-hover);
    }
    .icon-btn.close {
        grid-area: close;
        border-color: transparent;
        font-size: 17px;
    }

    /* Vitesse : boutons à bascule, même dessin que les périodes de la frise. */
    .speeds {
        grid-area: speeds;
        display: flex;
        gap: 6px;
    }
    .speed {
        min-height: 40px;
        padding: 6px 14px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background-color: transparent;
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        white-space: nowrap;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .speed:hover {
        background-color: var(--vff-bg-muted);
    }
    .speed[aria-pressed='true'] {
        border-color: var(--vff-accent);
        background-color: var(--vff-bg-accent);
        color: var(--vff-accent-ink);
        font-weight: 600;
    }
    .step-note {
        grid-area: speeds;
        max-width: 16rem;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }

    /* Jusqu'à 1 180 px (comme la frise) : l'avancement passe sur sa propre rangée, en dessous. */
    @media (max-width: 1180px) {
        .era-player {
            grid-template-columns: minmax(0, 1fr) auto auto auto;
            grid-template-areas:
                'year steps speeds close'
                'progress progress progress progress';
        }
    }
    @media (max-width: 900px) {
        .era-player {
            padding: 12px 8px 14px 16px;
        }
    }
    /* Téléphone : année et quitter, puis les commandes, puis l'avancement. */
    @media (max-width: 600px) {
        .era-player {
            grid-template-columns: auto minmax(0, 1fr) auto;
            grid-template-areas:
                'year year close'
                'steps speeds speeds'
                'progress progress progress';
            gap: 10px 12px;
        }
        .speeds {
            justify-content: flex-end;
        }
        .speed {
            min-height: 44px;
            padding-inline: 12px;
        }
        .step-note {
            max-width: none;
        }
    }
    /* 360 px et moins : la vitesse passe sous les pas plutôt que de déborder. */
    @media (max-width: 360px) {
        .era-player {
            grid-template-areas:
                'year year close'
                'steps steps steps'
                'speeds speeds speeds'
                'progress progress progress';
        }
        .speeds {
            justify-content: flex-start;
        }
    }
    /* Cadre bas (téléphone à l'horizontale) : la barre se tasse pour laisser voir la carte. */
    @media (max-height: 520px) {
        .era-player {
            padding-block: 8px 10px;
        }
        .year {
            font-size: 1.75rem;
        }
    }
</style>
