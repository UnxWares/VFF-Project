<script>
    import { Link } from '@inertiajs/svelte';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { i18n } from '$lib/i18n.js';
    import { navbarFade } from '$lib/actions/navbarFade.js';
    import { parallaxTGV } from '$lib/actions/parallaxTGV.js';
    import { scrollToContent } from '$lib/actions/smoothScrollTo.js';
    import LanguageSwitcher from './LanguageSwitcher.svelte';

    import vffLogo from '$images/vff.png';
    import tgvLeft from '$images/tgv-left.png';
    import tgvRight from '$images/tgv-right.png';

    let { compact = false } = $props();

    let headerEl = $state();
    let navEl = $state();
    let backgroundEl = $state();
    let leftTgvEl = $state();
    let rightTgvEl = $state();
</script>

<header
    class:compact
    bind:this={headerEl}
    use:navbarFade={{ nav: navEl, background: backgroundEl }}
>
    <div class="header-background" bind:this={backgroundEl}>
        <nav bind:this={navEl}>
            <Link href="/" class="nav-title" aria-label={$i18n.t('common.app_full')}>
                <figure>
                    <img src={vffLogo} alt="Logo de VFF" />
                </figure>
                <h2>{$i18n.t('common.app_full')}</h2>
            </Link>
            <ul class="links">
                <li class="whoarewe">
                    <Link href="/whoarewe"><i class="bi bi-people"></i> {$i18n.t('nav.whoarewe')}</Link>
                </li>
                <li class="maps">
                    <Link href="/maps"><i class="bi bi-map"></i> {$i18n.t('nav.maps')}</Link>
                </li>
                <li class="contribute">
                    <Link href="/contribute"><i class="bi bi-geo"></i> {$i18n.t('nav.contribute')}</Link>
                </li>
                <li class="donate">
                    <Link href="/donate"><i class="bi bi-balloon-heart"></i> {$i18n.t('nav.donate')}</Link>
                </li>
                <ul class="user">
                    <li class="theme">
                        <button
                            class="theme-change"
                            type="button"
                            aria-label={theme.isDark ? $i18n.t('nav.theme_light') : $i18n.t('nav.theme_dark')}
                            onclick={() => theme.toggle()}
                        >
                            <i class="bi bi-sun light-theme-icon fs-5" class:hidden-button={theme.isDark} title="Thème clair"></i>
                            <i class="bi bi-moon-stars dark-theme-icon fs-5" class:hidden-button={!theme.isDark} title="Thème sombre"></i>
                        </button>
                    </li>
                    <li class="lang">
                        <LanguageSwitcher />
                    </li>
                    <li class="account">
                        <i class="bi bi-person-square"></i>
                        <i class="bi bi-chevron-down"></i>
                    </li>
                </ul>
            </ul>
        </nav>
        {#if !compact}
            <div class="header-images" use:parallaxTGV={{ left: leftTgvEl, right: rightTgvEl }}>
                <figure class="left-tgv" bind:this={leftTgvEl}>
                    <img src={tgvLeft} alt="Queue du TGV" />
                </figure>
                <figure>
                    <img src={vffLogo} alt="Logo de VFF" />
                </figure>
                <figure class="right-tgv" bind:this={rightTgvEl}>
                    <img src={tgvRight} alt="Tête du TGV" />
                </figure>
            </div>
            <div class="bottom-container" style="position: relative;">
                <div
                    class="scroll-button"
                    role="button"
                    tabindex="0"
                    aria-label="Scroll to content"
                    onclick={scrollToContent}
                    onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && scrollToContent()}
                >
                    <span><i class="bi bi-chevron-double-down"></i></span>
                </div>
            </div>
        {/if}
    </div>
</header>

<style>
    /* `compact` = pages internes : header réduit, pas de héros TGV ni de scroll button.
       Ne modifie pas le rendu de la home — uniquement activé via prop. */
    header.compact {
        height: 130px;
    }
    header.compact :global(.header-background) {
        height: 130px;
        background-image: none;
        background-color: white;
    }
    header.compact :global(nav) {
        opacity: 1 !important;
        position: relative !important;
    }
</style>
