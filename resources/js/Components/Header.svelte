<script>
    import { Link, page, router } from '@inertiajs/svelte';
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

    // Auth user partagé via HandleInertiaRequests. `null` = invité.
    // Inertia Svelte 3 expose `page` comme objet $state, pas un store → pas de `$`.
    let user = $derived(page.props.auth?.user ?? null);
    let accountOpen = $state(false);
    let accountWrapper = $state();
    // Menu des liens sur petit écran (≤ 1180 px, cf. header.css) ; sans effet au-dessus.
    let menuOpen = $state(false);
    let menuButton = $state();

    function toggleAccount() {
        accountOpen = !accountOpen;
    }

    function logout() {
        accountOpen = false;
        router.post('/logout');
    }

    function onClickOutsideAccount(event) {
        if (accountOpen && accountWrapper && !accountWrapper.contains(event.target)) {
            accountOpen = false;
        }
        if (menuOpen && navEl && !navEl.contains(event.target)) {
            menuOpen = false;
        }
    }

    function onKeydown(event) {
        if (event.key !== 'Escape') return;
        accountOpen = false;
        if (menuOpen) {
            menuOpen = false;
            menuButton?.focus();
        }
    }

    $effect(() => {
        document.addEventListener('click', onClickOutsideAccount);
        return () => document.removeEventListener('click', onClickOutsideAccount);
    });

    // Une navigation (lien du menu, retour arrière) referme les menus.
    $effect(() =>
        router.on('navigate', () => {
            menuOpen = false;
            accountOpen = false;
        }),
    );
</script>

<svelte:window onkeydown={onKeydown} />

<header
    class="site-header"
    class:compact
    bind:this={headerEl}
    use:navbarFade={{ nav: navEl, background: backgroundEl }}
>
    <div class="header-background" bind:this={backgroundEl}>
        <nav bind:this={navEl}>
            <Link href="/" class="nav-title" aria-label={$i18n.t('common.app_full')}>
                <figure>
                    <img src={vffLogo} alt={$i18n.t('nav.logo_alt')} />
                </figure>
                <h2>{$i18n.t('common.app_full')}</h2>
            </Link>
            <button
                type="button"
                class="nav-toggle"
                bind:this={menuButton}
                aria-expanded={menuOpen}
                aria-controls="vff-nav-links"
                aria-label={menuOpen ? $i18n.t('nav.menu_close') : $i18n.t('nav.menu_open')}
                onclick={() => (menuOpen = !menuOpen)}
            >
                <i class="bi {menuOpen ? 'bi-x-lg' : 'bi-list'}" aria-hidden="true"></i>
            </button>
            <ul class="links" id="vff-nav-links" class:open={menuOpen}>
                <li class="whoarewe">
                    <Link href="/whoarewe"
                        ><i class="bi bi-people"></i> {$i18n.t('nav.whoarewe')}</Link
                    >
                </li>
                <li class="maps">
                    <Link href="/maps"><i class="bi bi-map"></i> {$i18n.t('nav.maps')}</Link>
                </li>
                <li class="lines">
                    <Link href="/lines"><i class="bi bi-bezier"></i> {$i18n.t('nav.lines')}</Link>
                </li>
                <li class="contribute">
                    <Link href="/contribute"
                        ><i class="bi bi-geo"></i> {$i18n.t('nav.contribute')}</Link
                    >
                </li>
                <li class="donate">
                    <Link href="/donate"
                        ><i class="bi bi-balloon-heart"></i> {$i18n.t('nav.donate')}</Link
                    >
                </li>
                <ul class="user">
                    <li class="theme">
                        <button
                            class="theme-change"
                            type="button"
                            aria-label={theme.isDark
                                ? $i18n.t('nav.theme_light')
                                : $i18n.t('nav.theme_dark')}
                            onclick={() => theme.toggle()}
                        >
                            <i
                                class="bi bi-sun light-theme-icon fs-5"
                                class:hidden-button={theme.isDark}
                                title={$i18n.t('nav.theme_light')}
                            ></i>
                            <i
                                class="bi bi-moon-stars dark-theme-icon fs-5"
                                class:hidden-button={!theme.isDark}
                                title={$i18n.t('nav.theme_dark')}
                            ></i>
                        </button>
                    </li>
                    <li class="lang">
                        <LanguageSwitcher />
                    </li>
                    <li class="account" bind:this={accountWrapper}>
                        {#if user}
                            <button
                                type="button"
                                class="account-trigger"
                                aria-haspopup="menu"
                                aria-expanded={accountOpen}
                                aria-label={$i18n.t('auth.account')}
                                onclick={toggleAccount}
                            >
                                {#if user.avatar_path}
                                    <img class="avatar" src={user.avatar_path} alt={user.name} />
                                {:else}
                                    <i class="bi bi-person-circle"></i>
                                {/if}
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            {#if accountOpen}
                                <ul class="account-menu" role="menu">
                                    <li class="account-user" aria-hidden="true">
                                        <strong>{user.name}</strong>
                                        <span>{user.email}</span>
                                    </li>
                                    <li>
                                        <Link href="/profile" onclick={() => (accountOpen = false)}>
                                            <i class="bi bi-person"></i>
                                            {$i18n.t('auth.profile')}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href="/contribute/mine"
                                            onclick={() => (accountOpen = false)}
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                            {$i18n.t('auth.my_contributions')}
                                        </Link>
                                    </li>
                                    {#if user.role === 'validator' || user.role === 'admin'}
                                        <li>
                                            <Link
                                                href="/validate"
                                                onclick={() => (accountOpen = false)}
                                            >
                                                <i class="bi bi-shield-check"></i>
                                                {$i18n.t('auth.validate')}
                                            </Link>
                                        </li>
                                    {/if}
                                    <li>
                                        <button type="button" onclick={logout}>
                                            <i class="bi bi-box-arrow-right"></i>
                                            {$i18n.t('auth.logout')}
                                        </button>
                                    </li>
                                </ul>
                            {/if}
                        {:else}
                            <Link href="/login" class="account-link">
                                <i class="bi bi-box-arrow-in-right"></i>
                                <span class="account-link-label">{$i18n.t('auth.login')}</span>
                            </Link>
                        {/if}
                    </li>
                </ul>
            </ul>
        </nav>
        {#if !compact}
            <div class="header-images" use:parallaxTGV={{ left: leftTgvEl, right: rightTgvEl }}>
                <figure class="left-tgv" bind:this={leftTgvEl}>
                    <img src={tgvLeft} alt={$i18n.t('nav.tgv_tail_alt')} />
                </figure>
                <figure>
                    <img src={vffLogo} alt={$i18n.t('nav.logo_alt')} />
                </figure>
                <figure class="right-tgv" bind:this={rightTgvEl}>
                    <img src={tgvRight} alt={$i18n.t('nav.tgv_head_alt')} />
                </figure>
            </div>
            <div class="bottom-container" style="position: relative;">
                <div
                    class="scroll-button"
                    role="button"
                    tabindex="0"
                    aria-label={$i18n.t('nav.scroll_to_content')}
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
       Ne modifie pas le rendu de la home - uniquement activé via prop. */
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
    /* Petit écran : barre de 72 px (cf. header.css) ; le menu déroulant passe
       au-dessus du contenu de la page (héros, chiffres clés en z-index 2). */
    @media (max-width: 1180px) {
        header.compact,
        header.compact :global(.header-background) {
            height: 72px;
        }
        header.compact :global(.header-background) {
            z-index: 50;
        }
    }

    /* Menu compte connecté (additif - n'altère pas le bloc .user existant). */
    .account {
        position: relative;
    }
    .account-trigger {
        all: unset;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: var(--vff-radius-sm);
        transition: background-color var(--vff-transition);
    }
    .account-trigger:hover {
        background-color: var(--vff-accent-soft);
        color: var(--vff-accent);
    }
    .account-trigger .avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--vff-accent);
    }
    .account-trigger .bi-person-circle {
        font-size: 22px;
    }
    .account-trigger .bi-chevron-down {
        font-size: 12px;
    }
    .account-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        min-width: 220px;
        margin: 0;
        padding: 6px;
        list-style: none;
        background-color: var(--vff-bg);
        color: var(--vff-text);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        box-shadow: 0 12px 28px var(--vff-shadow-strong);
        z-index: var(--vff-z-overlay, 1000);
    }
    .account-menu li {
        margin: 0;
        display: block;
    }
    .account-menu .account-user {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--vff-border);
        margin-bottom: 4px;
    }
    .account-menu .account-user strong {
        font-size: 14px;
        color: var(--vff-text);
    }
    .account-menu .account-user span {
        font-size: 12px;
        color: var(--vff-text-muted);
        word-break: break-all;
    }
    .account-menu :global(a),
    .account-menu button {
        all: unset;
        display: flex;
        width: 100%;
        box-sizing: border-box;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: var(--vff-radius-sm);
        font-size: 14px;
        font-family: var(--vff-font-body);
        cursor: pointer;
        color: var(--vff-text);
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .account-menu :global(a:hover),
    .account-menu button:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-accent);
    }

    /* Lien Connexion pour les invités. */
    .account :global(a.account-link) {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-accent);
        color: var(--vff-text-inverse);
        font-family: var(--vff-font-display);
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: background-color var(--vff-transition);
    }
    .account :global(a.account-link:hover) {
        background-color: var(--vff-accent-hover);
    }
    /* Sous 1180 px, la connexion est dans le menu déroulant (cf. header.css) : le libellé
       « Connexion » y a la place, il n'est plus masqué sur téléphone. */
</style>
