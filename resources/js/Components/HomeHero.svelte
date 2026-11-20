<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { parallaxTGV } from '$lib/actions/parallaxTGV.js';

    import landscape960Avif from '$images/heros/carte-paysage-960.avif';
    import landscape1600Avif from '$images/heros/carte-paysage-1600.avif';
    import landscape2400Avif from '$images/heros/carte-paysage-2400.avif';
    import landscape960Webp from '$images/heros/carte-paysage-960.webp';
    import landscape1600Webp from '$images/heros/carte-paysage-1600.webp';
    import landscape2400Webp from '$images/heros/carte-paysage-2400.webp';
    import portrait800Avif from '$images/heros/carte-portrait-800.avif';
    import portrait1200Avif from '$images/heros/carte-portrait-1200.avif';
    import portrait800Webp from '$images/heros/carte-portrait-800.webp';
    import portrait1200Webp from '$images/heros/carte-portrait-1200.webp';
    import tgvLeft from '$images/heros/tgv-gauche.webp';
    import tgvRight from '$images/heros/tgv-droite.webp';

    /**
     * Grand visuel de l'accueil, posé par Header.svelte sous la barre : carte ancienne des chemins
     * de fer en sépia sombre, disque VFF entre deux TGV qui s'écartent au défilement
     * (lib/actions/parallaxTGV.js), phrase de présentation, deux actions (la carte, la recherche de
     * tout le site), flèche vers le contenu et crédit du fond.
     * Styles : resources/css/header/header.css, section « Héros de l'accueil ».
     *
     * Fond : détail (autour de Paris) de la « Carte spéciale des chemins de fer de la France et de
     * l'Allemagne » d'Alexandre Vuillemin (Paris, A. Logerot, 1865), domaine public, exemplaire de
     * la Library of Congress publié sur Wikimedia Commons ; recadré, passé en sépia sombre (le blanc
     * y fait au moins 5:1 partout), couture de l'entoilage comblée. Cadrage paysage 3:2 sur
     * ordinateur, portrait 3:4 sur téléphone et tablette en hauteur (une tranche suffit) ; tailles
     * annoncées pour une image en `object-fit: cover`.
     *
     * @type {{ logo: string, onsearch: () => void }}
     */
    let { logo, onsearch } = $props();

    const landscapeSizes = '(min-aspect-ratio: 3/2) 100vw, 150vh';
    const portraitSizes = '(max-aspect-ratio: 3/4) 75vh, 100vw';
    const landscapeAvif = `${landscape960Avif} 960w, ${landscape1600Avif} 1600w, ${landscape2400Avif} 2400w`;
    const landscapeWebp = `${landscape960Webp} 960w, ${landscape1600Webp} 1600w, ${landscape2400Webp} 2400w`;
    const portraitAvif = `${portrait800Avif} 800w, ${portrait1200Avif} 1200w`;
    const portraitWebp = `${portrait800Webp} 800w, ${portrait1200Webp} 1200w`;

    // Flèche : descend au contenu et y place le focus (sans animation si l'utilisateur a demandé
    // moins de mouvement).
    function goToContent(/** @type {MouseEvent} */ event) {
        const target = document.getElementById('contenu');
        if (!target) return;
        event.preventDefault();
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto' });
        target.focus({ preventScroll: true });
    }
</script>

<div class="hero" {@attach parallaxTGV}>
    <picture class="hero-photo">
        <source
            type="image/avif"
            media="(max-aspect-ratio: 1/1)"
            srcset={portraitAvif}
            sizes={portraitSizes}
        />
        <source
            type="image/webp"
            media="(max-aspect-ratio: 1/1)"
            srcset={portraitWebp}
            sizes={portraitSizes}
        />
        <source type="image/avif" srcset={landscapeAvif} sizes={landscapeSizes} />
        <img
            src={landscape1600Webp}
            srcset={landscapeWebp}
            sizes={landscapeSizes}
            alt=""
            width="2400"
            height="1600"
            fetchpriority="high"
        />
    </picture>

    <div class="hero-rail">
        <img class="hero-tgv left" src={tgvLeft} alt="" width="465" height="94" />
        <img
            class="hero-disc"
            src={logo}
            alt={$i18n.t('nav.logo_alt')}
            width="1024"
            height="1024"
            fetchpriority="high"
        />
        <img class="hero-tgv right" src={tgvRight} alt="" width="465" height="94" />
    </div>

    <p class="hero-tagline">{$i18n.t('home.hero.tagline')}</p>

    <div class="hero-actions">
        <Link href="/maps" class="vff-btn primary">{$i18n.t('home.hero.open_map')}</Link>
        <button type="button" class="vff-btn secondary" aria-haspopup="dialog" onclick={onsearch}>
            {$i18n.t('home.hero.search')}
        </button>
    </div>

    <a
        class="hero-scroll"
        href="#contenu"
        aria-label={$i18n.t('nav.scroll_to_content')}
        title={$i18n.t('nav.scroll_to_content')}
        onclick={goToContent}
    >
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </a>

    <p class="hero-credit">
        <Link href="/credits/media#fond-accueil">{$i18n.t('home.hero.credit')}</Link>
    </p>
</div>
