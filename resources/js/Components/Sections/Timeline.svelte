<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';

    const eras = ['1990', '2000', '2010', '2020', '2030'];
    let selected = $state('2030');
</script>

<section id="timeline" class="timeline-section" use:scrollReveal={{ threshold: 0.15 }}>
    <div class="timeline-inner">
        <div class="timeline-head">
            <span class="eyebrow">{$i18n.t('home.timeline.eyebrow')}</span>
            <h2>{$i18n.t('home.timeline.title')}</h2>
            <p class="lead">{$i18n.t('home.timeline.subtitle')}</p>
        </div>

        <div class="rail-line" aria-hidden="true">
            <div class="rail-track"></div>
            <div class="ties">
                {#each Array(20) as _, i (i)}
                    <span class="tie"></span>
                {/each}
            </div>
        </div>

        <ul class="era-list" role="tablist" aria-label="Sélecteur d'époque">
            {#each eras as era}
                <li>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={selected === era}
                        class:active={selected === era}
                        onclick={() => (selected = era)}
                    >
                        <span class="dot"></span>
                        <span class="year">{era}</span>
                    </button>
                </li>
            {/each}
        </ul>

        <div class="era-panel" role="tabpanel" aria-live="polite">
            {#each eras as era}
                {#if selected === era}
                    <div class="era-card">
                        <div class="era-header">
                            <span class="big-year">{era}</span>
                            <h3>{$i18n.t(`home.timeline.era${era}_title`)}</h3>
                            {#if era === '2030'}
                                <span class="pill">{$i18n.t('home.timeline.current_badge')}</span>
                            {/if}
                        </div>
                        <p>{$i18n.t(`home.timeline.era${era}_text`)}</p>
                        <Link href="/maps" class="vff-btn primary">
                            <i class="bi bi-map"></i> {$i18n.t('home.timeline.cta')}
                        </Link>
                    </div>
                {/if}
            {/each}
        </div>
    </div>
</section>

<style>
    .timeline-section {
        padding: 80px 16px;
        background-color: var(--vff-bg);
        position: relative;
        overflow: hidden;
    }
    .timeline-inner {
        max-width: 980px;
        margin: 0 auto;
    }
    .timeline-head {
        text-align: center;
        margin-bottom: 48px;
    }
    .eyebrow {
        display: inline-block;
        font-family: var(--vff-font-display);
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--vff-accent);
        margin-bottom: 12px;
    }
    .timeline-head h2 {
        margin: 0 0 16px;
        font-family: var(--vff-font-heading);
        font-size: clamp(28px, 3.5vw, 36px);
        color: var(--vff-text);
    }
    .lead {
        font-family: var(--vff-font-body);
        font-size: 18px;
        line-height: 1.55;
        color: var(--vff-text-muted);
        margin: 0 auto;
        max-width: 640px;
    }

    /* Rail visuel décoratif */
    .rail-line {
        position: relative;
        height: 24px;
        margin: 0 8% 6px;
    }
    .rail-track {
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(
            to right,
            transparent 0%,
            var(--vff-accent) 12%,
            var(--vff-accent) 88%,
            transparent 100%
        );
        transform: translateY(-50%);
        border-radius: 2px;
    }
    .ties {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        justify-content: space-between;
        padding: 0 12%;
        align-items: center;
    }
    .tie {
        width: 2px;
        height: 16px;
        background-color: var(--vff-border-strong);
        border-radius: 1px;
        opacity: 0.6;
    }

    /* Sélecteur d'époques */
    .era-list {
        list-style: none;
        padding: 0;
        margin: 0 0 48px;
        display: flex;
        justify-content: space-between;
        padding: 0 8%;
    }
    .era-list li {
        margin: 0;
    }
    .era-list button {
        all: unset;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 4px;
        cursor: pointer;
        transition: transform 200ms ease;
    }
    .era-list button:hover {
        transform: translateY(-2px);
    }
    .era-list button:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 4px;
        border-radius: 4px;
    }
    .dot {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background-color: var(--vff-bg);
        border: 3px solid var(--vff-border-strong);
        transition: background-color 200ms ease, border-color 200ms ease, box-shadow 200ms ease;
    }
    .era-list button.active .dot {
        background-color: var(--vff-accent);
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 6px var(--vff-accent-soft);
    }
    .year {
        font-family: var(--vff-font-display);
        font-size: 17px;
        font-weight: 600;
        color: var(--vff-text-muted);
        transition: color 200ms ease;
    }
    .era-list button.active .year {
        color: var(--vff-accent);
    }

    /* Panneau de l'époque sélectionnée */
    .era-panel {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: 16px;
        padding: 40px 32px;
        min-height: 220px;
        text-align: center;
    }
    .era-card {
        max-width: 560px;
        margin: 0 auto;
    }
    .era-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .big-year {
        font-family: var(--vff-font-display);
        font-size: clamp(40px, 5vw, 56px);
        font-weight: 600;
        color: var(--vff-accent);
        line-height: 1;
    }
    .era-header h3 {
        font-family: var(--vff-font-heading);
        font-size: clamp(20px, 2.5vw, 24px);
        margin: 0;
        color: var(--vff-text);
    }
    .pill {
        font-family: var(--vff-font-display);
        font-size: 11px;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
        padding: 4px 10px;
        border-radius: var(--vff-radius-pill);
    }
    .era-card p {
        font-family: var(--vff-font-body);
        font-size: 17px;
        line-height: 1.6;
        color: var(--vff-text-muted);
        margin: 0 0 24px;
    }

    @media (max-width: 700px) {
        .era-list {
            padding: 0 4%;
        }
        .year {
            font-size: 14px;
        }
        .era-panel {
            padding: 28px 20px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .era-list button,
        .dot,
        .year {
            transition: none;
        }
    }
</style>
