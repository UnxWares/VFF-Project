<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';

    const ways = [
        { icon: 'bi-pencil-square', tKey: 'data' },
        { icon: 'bi-images', tKey: 'media' },
        { icon: 'bi-code-slash', tKey: 'code' },
    ];
</script>

<section id="contribute-teaser" class="contrib-section" use:scrollReveal={{ threshold: 0.2 }}>
    <div class="contrib-inner">
        <div class="contrib-head">
            <span class="eyebrow">{$i18n.t('home.contribute.eyebrow')}</span>
            <h2>{$i18n.t('home.contribute.title')}</h2>
            <p class="lead">{$i18n.t('home.contribute.subtitle')}</p>
        </div>
        <ul class="contrib-grid">
            {#each ways as w}
                <li class="way">
                    <div class="ico"><i class="bi {w.icon}"></i></div>
                    <h3>{$i18n.t(`home.contribute.${w.tKey}_title`)}</h3>
                    <p>{$i18n.t(`home.contribute.${w.tKey}_text`)}</p>
                </li>
            {/each}
        </ul>
        <div class="contrib-cta">
            <Link href="/contribute" class="vff-btn primary">
                <i class="bi bi-geo"></i> {$i18n.t('home.contribute.cta')}
            </Link>
        </div>
    </div>
</section>

<style>
    .contrib-section {
        padding: 80px 16px;
        background-color: var(--vff-bg);
    }
    .contrib-inner {
        max-width: 1100px;
        margin: 0 auto;
    }
    .contrib-head {
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
    .contrib-head h2 {
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
    .contrib-grid {
        list-style: none;
        margin: 0 0 36px;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 28px;
    }
    .way {
        text-align: center;
        padding: 28px 20px;
    }
    .ico {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background-color: var(--vff-accent-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
    }
    .ico i {
        font-size: 28px;
        color: var(--vff-accent);
    }
    .way h3 {
        font-family: var(--vff-font-display);
        font-size: 22px;
        margin: 0 0 10px;
        color: var(--vff-text);
    }
    .way p {
        font-family: var(--vff-font-body);
        font-size: 16px;
        line-height: 1.6;
        margin: 0;
        color: var(--vff-text-muted);
    }
    .contrib-cta {
        text-align: center;
    }
    @media (max-width: 800px) {
        .contrib-grid {
            grid-template-columns: 1fr;
            gap: 8px;
        }
    }
</style>
