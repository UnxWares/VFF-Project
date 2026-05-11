<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';
</script>

<section id="final-cta" class="cta-section" use:scrollReveal={{ threshold: 0.25 }}>
    <div class="cta-inner">
        <h2>{$i18n.t('home.final_cta.title')}</h2>
        <p>{$i18n.t('home.final_cta.subtitle')}</p>
        <div class="cta-buttons">
            <a href="https://discord.com/invite/6SwTfXBx4h" target="_blank" rel="noopener" class="vff-btn primary">
                <i class="bi bi-discord"></i> {$i18n.t('home.final_cta.discord')}
            </a>
            <a href="https://github.com/vff-project" target="_blank" rel="noopener" class="vff-btn ghost-on-cta">
                <i class="bi bi-github"></i> {$i18n.t('home.final_cta.github')}
            </a>
            <Link href="/donate" class="vff-btn ghost-on-cta">
                <i class="bi bi-balloon-heart"></i> {$i18n.t('home.final_cta.donate')}
            </Link>
        </div>
    </div>
</section>

<style>
    .cta-section {
        padding: 100px 16px;
        /* Absorbe le `margin-top: 30px` du <footer> d'origine pour une transition
           dark CTA → footer clair nette, sans bande blanche entre les deux. */
        margin-bottom: -30px;
        background-color: #0f1419;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .cta-section::before,
    .cta-section::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.5;
        pointer-events: none;
    }
    .cta-section::before {
        width: 320px;
        height: 320px;
        background-color: #ff6500;
        top: -120px;
        left: -80px;
    }
    .cta-section::after {
        width: 280px;
        height: 280px;
        background-color: #ff8b35;
        bottom: -100px;
        right: -60px;
    }
    .cta-inner {
        position: relative;
        max-width: 720px;
        margin: 0 auto;
        text-align: center;
    }
    .cta-inner h2 {
        font-family: Inter, sans-serif;
        font-size: clamp(32px, 4.5vw, 44px);
        margin: 0 0 16px;
        color: #fff;
    }
    .cta-inner p {
        font-family: Lato, sans-serif;
        font-size: 18px;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.85);
        margin: 0 auto 36px;
        max-width: 540px;
    }
    .cta-buttons {
        display: flex;
        justify-content: center;
        gap: 14px;
        flex-wrap: wrap;
    }
</style>
