<script>
    import { onMount } from 'svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { i18n } from '$lib/i18n.js';
    import { navbarFade } from '$lib/actions/navbarFade.js';
    import { parallaxTGV } from '$lib/actions/parallaxTGV.js';
    import LanguageSwitcher from './LanguageSwitcher.svelte';

    import vffLogo from '$images/vff.png';
    import tgvLeft from '$images/tgv-left.png';
    import tgvRight from '$images/tgv-right.png';

    /**
     * En-tête du site. `compact` : pages internes (barre seule) ; sinon accueil (héros TGV de 200 vh).
     * Menus (panneau sous 1 180 px, compte) : boutons à aria-expanded, Échap ferme et rend le focus
     * au bouton, un clic ou un focus hors du menu le ferme. Styles : resources/css/header/header.css.
     */
    let { compact = false } = $props();

    const NAV = [
        { href: '/whoarewe', icon: 'bi-people', key: 'nav.whoarewe' },
        { href: '/maps', icon: 'bi-map', key: 'nav.maps' },
        { href: '/lines', icon: 'bi-bezier', key: 'nav.lines' },
        { href: '/contribute', icon: 'bi-geo', key: 'nav.contribute' },
        { href: '/donate', icon: 'bi-balloon-heart', key: 'nav.donate' },
    ];

    let navEl = $state();
    let backgroundEl = $state();
    let leftTgvEl = $state();
    let rightTgvEl = $state();
    let menuButton = $state();
    let accountWrapper = $state();
    let accountButton = $state();

    // Utilisateur partagé par HandleInertiaRequests (null = visiteur).
    const user = $derived(page.props.auth?.user ?? null);
    const path = $derived((page.url ?? '/').split(/[?#]/)[0]);
    let accountOpen = $state(false);
    // Panneau des liens sous 1 180 px (cf. header.css) ; sans effet au-dessus.
    let menuOpen = $state(false);

    const themeLabel = $derived(
        theme.isDark ? $i18n.t('nav.theme_light') : $i18n.t('nav.theme_dark'),
    );

    /** Le lien correspond-il à la page affichée (rubrique et ses sous-pages) ? */
    function isCurrent(href) {
        return path === href || path.startsWith(`${href}/`);
    }

    function logout() {
        accountOpen = false;
        router.post('/logout');
    }

    function onDocumentClick(event) {
        if (accountOpen && accountWrapper && !accountWrapper.contains(event.target)) {
            accountOpen = false;
        }
        if (menuOpen && navEl && !navEl.contains(event.target)) {
            menuOpen = false;
        }
    }

    function onKeydown(event) {
        if (event.key !== 'Escape') return;
        if (accountOpen) {
            accountOpen = false;
            accountButton?.focus();
        } else if (menuOpen) {
            menuOpen = false;
            menuButton?.focus();
        }
    }

    // Le focus quitte un menu (Tab, Maj+Tab) : il se referme.
    function onAccountFocusout(event) {
        if (accountOpen && event.relatedTarget && !accountWrapper.contains(event.relatedTarget)) {
            accountOpen = false;
        }
    }
    function onNavFocusout(event) {
        if (menuOpen && event.relatedTarget && !navEl.contains(event.relatedTarget)) {
            menuOpen = false;
        }
    }

    // Bouton du héros : descend au contenu et y place le focus (sans animation si l'utilisateur
    // a demandé moins de mouvement).
    function goToContent(event) {
        const target = document.getElementById('contenu');
        if (!target) return;
        event.preventDefault();
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto' });
        target.focus({ preventScroll: true });
    }

    // Une navigation (lien du menu, retour arrière) referme les menus.
    onMount(() =>
        router.on('navigate', () => {
            menuOpen = false;
            accountOpen = false;
        }),
    );
</script>

<svelte:window onkeydown={onKeydown} />
<svelte:document onclick={onDocumentClick} />

<header
    class={['site-header', { compact }]}
    use:navbarFade={{ nav: navEl, background: backgroundEl }}
>
    <div class="header-background" bind:this={backgroundEl}>
        <nav bind:this={navEl} aria-label={$i18n.t('nav.main_label')} onfocusout={onNavFocusout}>
            <Link href="/" class="nav-title">
                <span class="nav-logo"><img src={vffLogo} alt="" width="100" height="100" /></span>
                <span class="nav-title-text">{$i18n.t('common.app_full')}</span>
            </Link>
            <button
                type="button"
                class="nav-toggle"
                bind:this={menuButton}
                aria-expanded={menuOpen}
                aria-controls="vff-nav-links"
                aria-label={$i18n.t('nav.menu')}
                onclick={() => (menuOpen = !menuOpen)}
            >
                <i class="bi {menuOpen ? 'bi-x-lg' : 'bi-list'}" aria-hidden="true"></i>
            </button>
            <div class={['links', { open: menuOpen }]} id="vff-nav-links">
                <ul class="nav-links">
                    {#each NAV as item (item.href)}
                        <li>
                            <Link
                                href={item.href}
                                aria-current={isCurrent(item.href) ? 'page' : undefined}
                                ><i class="bi {item.icon}" aria-hidden="true"></i>
                                {$i18n.t(item.key)}</Link
                            >
                        </li>
                    {/each}
                </ul>
                <ul class="user">
                    <li class="theme">
                        <button
                            class="theme-change"
                            type="button"
                            aria-label={themeLabel}
                            title={themeLabel}
                            onclick={() => theme.toggle()}
                        >
                            <i
                                class="bi {theme.isDark ? 'bi-moon-stars' : 'bi-sun'}"
                                aria-hidden="true"
                            ></i>
                            <span class="tool-label">{themeLabel}</span>
                        </button>
                    </li>
                    <li class="lang">
                        <LanguageSwitcher />
                    </li>
                    <li class="account" bind:this={accountWrapper} onfocusout={onAccountFocusout}>
                        {#if user}
                            <button
                                type="button"
                                class="account-trigger"
                                bind:this={accountButton}
                                aria-expanded={accountOpen}
                                aria-controls="vff-account-menu"
                                onclick={() => (accountOpen = !accountOpen)}
                            >
                                {#if user.avatar_path}
                                    <img class="avatar" src={user.avatar_path} alt="" />
                                {:else}
                                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                                {/if}
                                <span class="tool-label">{$i18n.t('auth.account')}</span>
                                <i class="bi bi-chevron-down chevron" aria-hidden="true"></i>
                            </button>
                            <ul class="account-menu" id="vff-account-menu" hidden={!accountOpen}>
                                <li class="account-user">
                                    <strong>{user.name}</strong>
                                    <span>{user.email}</span>
                                </li>
                                <li>
                                    <Link
                                        href="/profile"
                                        aria-current={isCurrent('/profile') ? 'page' : undefined}
                                    >
                                        <i class="bi bi-person" aria-hidden="true"></i>
                                        {$i18n.t('auth.profile')}
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="/contribute/mine"
                                        aria-current={isCurrent('/contribute/mine')
                                            ? 'page'
                                            : undefined}
                                    >
                                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                        {$i18n.t('auth.my_contributions')}
                                    </Link>
                                </li>
                                {#if user.role === 'validator' || user.role === 'admin'}
                                    <li>
                                        <Link
                                            href="/validate"
                                            aria-current={isCurrent('/validate')
                                                ? 'page'
                                                : undefined}
                                        >
                                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                                            {$i18n.t('nav.review_contributions')}
                                        </Link>
                                    </li>
                                {/if}
                                <li>
                                    <button type="button" onclick={logout}>
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                        {$i18n.t('auth.logout')}
                                    </button>
                                </li>
                            </ul>
                        {:else}
                            <Link
                                href="/login"
                                class="account-link"
                                aria-current={isCurrent('/login') ? 'page' : undefined}
                            >
                                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                                <span>{$i18n.t('auth.login')}</span>
                            </Link>
                        {/if}
                    </li>
                </ul>
            </div>
        </nav>
        {#if !compact}
            <div class="header-images" use:parallaxTGV={{ left: leftTgvEl, right: rightTgvEl }}>
                <figure class="left-tgv" bind:this={leftTgvEl}>
                    <img src={tgvLeft} alt="" />
                </figure>
                <figure>
                    <img src={vffLogo} alt={$i18n.t('nav.logo_alt')} />
                </figure>
                <figure class="right-tgv" bind:this={rightTgvEl}>
                    <img src={tgvRight} alt="" />
                </figure>
            </div>
            <div class="bottom-container">
                <a
                    class="scroll-button"
                    href="#contenu"
                    aria-label={$i18n.t('nav.scroll_to_content')}
                    onclick={goToContent}
                >
                    <i class="bi bi-chevron-double-down" aria-hidden="true"></i>
                </a>
            </div>
        {/if}
    </div>
</header>

<style>
    /* Libellé des boutons icônes : masqué sur ordinateur (l'icône et l'infobulle suffisent, le nom
       accessible est porté par aria-label), écrit en toutes lettres dans le panneau mobile. */
    .tool-label {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }

    /* Menu du compte connecté. */
    .account {
        position: relative;
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
    .account-trigger .chevron {
        font-size: 12px;
        transition: transform var(--vff-transition);
    }
    .account-trigger[aria-expanded='true'] .chevron {
        transform: rotate(180deg);
    }
    .account-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        z-index: var(--vff-z-nav);
        min-width: 240px;
        max-width: calc(100vw - 32px);
        margin: 0;
        padding: 6px;
        list-style: none;
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        box-shadow: var(--vff-shadow-popover);
    }
    .account-menu[hidden] {
        display: none;
    }
    .account-menu li {
        display: block;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
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
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .account-menu .account-user span {
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .account-menu :global(a),
    .account-menu button {
        display: flex;
        width: 100%;
        min-height: 40px;
        align-items: center;
        gap: 10px;
        margin: 0;
        padding: 8px 12px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        text-align: left;
        text-decoration: none;
        cursor: pointer;
        color: var(--vff-text);
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .account-menu :global(a:hover),
    .account-menu button:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-accent-ink);
    }
    .account-menu :global(a[aria-current='page']) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-underline-offset: 0.2em;
    }
    .account-menu :global(i) {
        font-size: 16px;
    }

    /* Lien « Connexion » des visiteurs : pastille orange, texte noir (7,1:1). */
    .account :global(a.account-link) {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 36px;
        padding: 6px 14px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-accent);
        color: var(--vff-on-accent);
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        text-decoration: none;
        transition: background-color var(--vff-transition);
    }
    .account :global(a.account-link:hover) {
        background-color: var(--vff-accent-hover);
    }

    /* Sous 1 180 px : tout est dans le panneau, les menus se déroulent dans le flux (sinon ils
       étaient rognés par le panneau, qui défile). */
    @media (max-width: 1180px) {
        .tool-label {
            position: static;
            width: auto;
            height: auto;
            overflow: visible;
            clip-path: none;
            white-space: normal;
        }
        .account-trigger .chevron {
            margin-left: auto;
        }
        .account-menu {
            position: static;
            min-width: 0;
            max-width: none;
            margin: 4px 0 0;
            padding: 4px 0 4px 16px;
            border: 0;
            border-left: 2px solid var(--vff-border);
            border-radius: 0;
            box-shadow: none;
            background: transparent;
        }
        .account-menu :global(a),
        .account-menu button {
            min-height: 44px;
            font-size: var(--vff-fs-md);
        }
        .account :global(a.account-link) {
            min-height: 44px;
            margin-top: 8px;
            font-size: var(--vff-fs-md);
        }
    }
</style>
