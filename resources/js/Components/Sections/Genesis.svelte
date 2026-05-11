<script>
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';
</script>

<section id="genesis" class="genesis-section" use:scrollReveal={{ threshold: 0.2 }}>
    <div class="genesis-inner">
        <span class="eyebrow">{$i18n.t('home.genesis.eyebrow')}</span>
        <h2>{$i18n.t('home.genesis.title')}</h2>
        <article>
            <p class="lead">{$i18n.t('home.genesis.p1')}</p>
            <p>{$i18n.t('home.genesis.p2')}</p>
            <p class="signature">— {$i18n.t('home.genesis.signature')}</p>
        </article>
    </div>
</section>

<style>
    .genesis-section {
        padding: 80px 16px;
        background-color: var(--vff-bg-elevated);
        position: relative;
    }
    .genesis-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background-color: var(--vff-accent);
        border-radius: 0 0 4px 4px;
    }
    .genesis-inner {
        max-width: 680px;
        margin: 0 auto;
    }
    .eyebrow {
        display: block;
        font-family: var(--vff-font-display);
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--vff-accent);
        text-align: center;
        margin-bottom: 12px;
    }
    h2 {
        font-family: var(--vff-font-heading);
        font-size: clamp(28px, 4vw, 40px);
        text-align: center;
        margin: 0 0 36px;
        color: var(--vff-text);
        font-style: italic;
        line-height: 1.2;
    }
    h2:after {
        display: block;
        content: '';
        width: 48px;
        height: 2px;
        background-color: var(--vff-accent);
        margin: 16px auto 0;
    }
    article {
        font-family: var(--vff-font-body);
        font-size: 19px;
        line-height: 1.75;
        color: var(--vff-text);
    }
    article p {
        margin: 0 0 18px;
        text-align: left;
    }
    article p.lead::first-letter {
        font-family: var(--vff-font-display);
        font-size: 56px;
        font-weight: 600;
        float: left;
        line-height: 1;
        padding: 4px 12px 0 0;
        color: var(--vff-accent);
    }
    article p.signature {
        text-align: right;
        font-style: italic;
        color: var(--vff-text-muted);
        font-size: 16px;
        margin-top: 24px;
    }
    @media (max-width: 600px) {
        .genesis-section {
            padding: 60px 16px;
        }
        article {
            font-size: 17px;
        }
        article p.lead::first-letter {
            font-size: 44px;
        }
    }
</style>
