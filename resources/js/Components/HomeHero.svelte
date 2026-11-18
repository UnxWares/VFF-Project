<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { parallaxTGV } from '$lib/actions/parallaxTGV.js';

    import photo640Avif from '$images/heros/fond-flou-640.avif';
    import photo1280Avif from '$images/heros/fond-flou-1280.avif';
    import photo640Webp from '$images/heros/fond-flou-640.webp';
    import photo1280Webp from '$images/heros/fond-flou-1280.webp';
    import tgvLeft from '$images/heros/tgv-gauche.webp';
    import tgvRight from '$images/heros/tgv-droite.webp';

    /**
     * Grand visuel de l'accueil, posé par Header.svelte sous la barre : photo des rails, disque VFF
     * entre deux TGV qui s'écartent au défilement (lib/actions/parallaxTGV.js), phrase de
     * présentation, deux actions (la carte, la recherche de tout le site) et flèche vers le contenu.
     * Styles : resources/css/header/header.css, section « Héros de l'accueil ».
     *
     * La photo est déjà floutée dans le fichier (plus de flou calculé par le navigateur) : sans
     * détail fin, 640 px suffisent jusqu'à la tablette et 1 280 px au-delà ; le choix suit la
     * largeur de la fenêtre, pas la densité de pixels. TGV en livrée neutre, jamais agrandis
     * au-delà de leur taille d'origine (465 px).
     *
     * @type {{ logo: string, onsearch: () => void }}
     */
    let { logo, onsearch } = $props();

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
        <source type="image/avif" media="(max-width: 900px)" srcset={photo640Avif} />
        <source type="image/avif" srcset={photo1280Avif} />
        <source type="image/webp" media="(max-width: 900px)" srcset={photo640Webp} />
        <img src={photo1280Webp} alt="" width="1280" height="720" fetchpriority="high" />
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
            <i class="bi bi-search" aria-hidden="true"></i>
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
</div>
