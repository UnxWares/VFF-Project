<script>
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';

    const features = [
        { icon: 'bi-clock-history', tKey: 'f1' },
        { icon: 'bi-bezier', tKey: 'f2' },
        { icon: 'bi-train-front', tKey: 'f3' },
        { icon: 'bi-images', tKey: 'f4' },
        { icon: 'bi-people-fill', tKey: 'f5' },
        { icon: 'bi-cloud-arrow-down', tKey: 'f6' },
    ];
</script>

<section id="features" class="features-section" use:scrollReveal={{ threshold: 0.15 }}>
    <div class="features-inner">
        <div class="features-head">
            <span class="eyebrow">{$i18n.t('home.features.eyebrow')}</span>
            <h2>{$i18n.t('home.features.title')}</h2>
            <p class="lead">{$i18n.t('home.features.subtitle')}</p>
        </div>
        <ul class="features-grid">
            {#each features as f}
                <li class="feature">
                    <div class="ico"><i class="bi {f.icon}"></i></div>
                    <h3>{$i18n.t(`home.features.${f.tKey}_title`)}</h3>
                    <p>{$i18n.t(`home.features.${f.tKey}_text`)}</p>
                </li>
            {/each}
        </ul>
    </div>
</section>

<style>
    .features-section {
        padding: 40px 16px 80px;
        background-color: var(--vff-bg);
    }
    .features-inner {
        max-width: 1180px;
        margin: 0 auto;
    }
    .features-head {
        text-align: center;
        max-width: 720px;
        margin: 0 auto 56px;
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
    .features-head h2 {
        margin: 0 0 16px;
        font-family: var(--vff-font-heading);
        font-size: clamp(28px, 3.5vw, 36px);
        color: var(--vff-text);
    }
    .lead {
        font-family: var(--vff-font-body);
        font-size: 19px;
        line-height: 1.55;
        color: var(--vff-text-muted);
        margin: 0;
    }
    .features-grid {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 28px;
    }
    .feature {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: 14px;
        padding: 32px 28px;
        transition: transform 200ms ease, box-shadow 200ms ease, border-color 200ms ease;
    }
    .feature:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 36px var(--vff-shadow);
        border-color: var(--vff-accent);
    }
    .ico {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background-color: var(--vff-accent-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
    }
    .ico i {
        font-size: 26px;
        color: var(--vff-accent);
    }
    .feature h3 {
        font-family: var(--vff-font-display);
        font-size: 20px;
        margin: 0 0 8px;
        color: var(--vff-text);
    }
    .feature p {
        font-family: var(--vff-font-body);
        font-size: 16px;
        line-height: 1.6;
        margin: 0;
        color: var(--vff-text-muted);
    }
    @media (max-width: 960px) {
        .features-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 560px) {
        .features-grid {
            grid-template-columns: 1fr;
        }
        .features-section {
            padding: 56px 16px;
        }
    }
</style>
