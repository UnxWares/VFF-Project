<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import vffLogo from '$images/vff.png';

    // Trois portes d'entrée de l'encyclopédie, chacune vers sa rubrique.
    const boxes = [
        { key: 'box1', href: '/maps', icon: 'bi-map' },
        { key: 'box2', href: '/lines', icon: 'bi-bezier2' },
        { key: 'box3', href: '/api', icon: 'bi-braces' },
    ];
</script>

<!-- Bande orange oblique et trois encadrés (dessin d'origine, styles dans css/index/main/maps.css). -->
<section id="maps">
    <div class="maps-background" aria-hidden="true"></div>
    <div class="maps-container">
        {#each boxes as box (box.key)}
            <article class="maps-box">
                <span class="maps-logo"><img src={vffLogo} alt="" width="88" height="88" /></span>
                <h2>{$i18n.t(`home.maps.${box.key}_title`)}</h2>
                <p>{$i18n.t(`home.maps.${box.key}_text`)}</p>
                <Link href={box.href} class="vff-link maps-link">
                    <i class="bi {box.icon}" aria-hidden="true"></i>
                    {$i18n.t(`home.maps.${box.key}_link`)}
                </Link>
            </article>
        {/each}
    </div>
</section>
