<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { scrollReveal } from '$lib/actions/scrollReveal.js';
    import vffLogo from '$images/vff.png';
</script>

<section id="whoarewe" class="noTranslate" use:scrollReveal>
    <div class="vff-title">
        <img src={vffLogo} alt={$i18n.t('common.app_full')} />
        <h1>{$i18n.t('home.whoarewe.title')}</h1>
    </div>
    <p>{$i18n.t('home.whoarewe.paragraph')}</p>
    <div class="whoarewe-buttons">
        <Link href="/whoarewe"><i class="bi bi-people"></i> {$i18n.t('home.whoarewe.cta_history')}</Link>
        <Link href="/donate"><i class="bi bi-balloon-heart"></i> {$i18n.t('home.whoarewe.cta_donate')}</Link>
    </div>
</section>
