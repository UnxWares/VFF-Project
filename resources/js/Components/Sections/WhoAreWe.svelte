<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import vffLogo from '$images/vff-320.webp';
</script>

<!-- Présentation de l'accueil (dessin d'origine, styles dans css/index/main/whoarewe.css). Le logo
     précède le titre qui nomme déjà le site : image décorative. -->
<section id="whoarewe" aria-labelledby="whoarewe-title">
    <div class="vff-title">
        <img src={vffLogo} alt="" width="70" height="70" />
        <h1 id="whoarewe-title">{$i18n.t('home.whoarewe.title')}</h1>
    </div>
    <p>{$i18n.t('home.whoarewe.paragraph')}</p>
    <div class="whoarewe-buttons">
        <Link href="/whoarewe" class="vff-btn primary">{$i18n.t('home.whoarewe.cta_history')}</Link>
        <Link href="/donate" class="vff-btn secondary">{$i18n.t('home.whoarewe.cta_donate')}</Link>
    </div>
</section>
