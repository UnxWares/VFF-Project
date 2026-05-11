<script>
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';

    // Objectifs — placeholders jusqu'à l'import OSM réel
    const stats = [
        { value: '30k+', labelKey: 'home.stats.km_label' },
        { value: '5', labelKey: 'home.stats.eras_label' },
        { value: '8k+', labelKey: 'home.stats.lines_label' },
        { value: '12k+', labelKey: 'home.stats.stations_label' },
    ];
</script>

<section id="stats" class="stats-section" use:scrollReveal={{ threshold: 0.2 }}>
    <div class="stats-inner">
        <div class="stats-head">
            <span class="eyebrow">{$i18n.t('home.stats.eyebrow')}</span>
            <h2>{$i18n.t('home.stats.title')}</h2>
            <p class="lead">{$i18n.t('home.stats.subtitle')}</p>
        </div>
        <div class="stats-grid">
            {#each stats as s}
                <div class="stat">
                    <span class="num">{s.value}</span>
                    <span class="label">{$i18n.t(s.labelKey)}</span>
                    <span class="goal">{$i18n.t('home.stats.goal_suffix')}</span>
                </div>
            {/each}
        </div>
    </div>
</section>

<style>
    .stats-section {
        position: relative;
        padding: 80px 16px;
        background: linear-gradient(135deg, #ff6500 0%, #ff8b35 100%);
        color: #fff;
        overflow: hidden;
    }
    .stats-section::before {
        content: '';
        position: absolute;
        inset: -40% -10% auto auto;
        width: 60%;
        aspect-ratio: 1;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.18) 0%, transparent 60%);
        pointer-events: none;
    }
    .stats-inner {
        max-width: 1180px;
        margin: 0 auto;
        position: relative;
    }
    .stats-head {
        text-align: center;
        max-width: 720px;
        margin: 0 auto 48px;
    }
    .eyebrow {
        display: inline-block;
        font-family: Gabarito, cursive;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.85);
        margin-bottom: 10px;
    }
    .stats-head h2 {
        margin: 0 0 12px;
        font-family: Inter, sans-serif;
        font-size: clamp(28px, 3.5vw, 36px);
        color: #fff;
    }
    .lead {
        font-family: Lato, sans-serif;
        font-size: 18px;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.92);
        margin: 0;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 24px;
    }
    .stat {
        background-color: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 14px;
        padding: 28px 16px;
        text-align: center;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .num {
        font-family: Gabarito, cursive;
        font-size: clamp(36px, 5vw, 48px);
        font-weight: 600;
        line-height: 1;
        color: #fff;
    }
    .label {
        font-family: Lato, sans-serif;
        font-size: 15px;
        color: rgba(255, 255, 255, 0.92);
        margin-top: 4px;
    }
    .goal {
        font-family: Gabarito, cursive;
        font-size: 11px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.65);
        margin-top: 6px;
    }
    @media (max-width: 760px) {
        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 420px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
