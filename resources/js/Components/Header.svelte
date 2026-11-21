<script>
    import { onMount } from 'svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import { theme } from '$lib/stores/theme.svelte.js';
    import { i18n } from '$lib/i18n.js';
    import { navbarFade } from '$lib/actions/navbarFade.js';
    import LanguageSwitcher from './LanguageSwitcher.svelte';
    import GlobalSearch from './Search/GlobalSearch.svelte';
    import HomeHero from './HomeHero.svelte';
    import { isSearchShortcut, shortcutLabel } from '$lib/siteSearch.js';

    // Logo : 320 px pour la barre (64 px à l'écran), 1 024 px pour le disque du grand visuel (465 px au plus).
    // WebP sans perte (le 1 024 px garde les pixels de vff.png) : 53 Ko et 251 Ko au lieu de 491 Ko par page.
    import vffLogo from '$images/vff-320.webp';
    import vffHero from '$images/vff.webp';

    /**
     * En-tête du site. `compact` : pages internes (barre seule) ; sinon accueil (en-tête de 200 vh
     * et grand visuel HomeHero, dont le bouton « Rechercher » ouvre la même recherche que la barre).
     *
     * Barre : l'encyclopédie en accès direct (Carte, Lignes, Gares, Matériel roulant), le projet
     * regroupé dans un menu (Contribuer, Notre histoire, Faites un don, API), la recherche de tout le
     * site (bouton ou Ctrl K / Cmd K), puis thème, langue et compte. Sous 1 180 px, le projet et les
     * outils passent dans le panneau « Menu » ; sous 900 px, les quatre entrées aussi (le panneau
     * contient alors tout). La recherche reste dans la barre à toutes les largeurs.
     *
     * Accidents (EXTRA) : rubrique de l'encyclopédie, mais une cinquième entrée ferait passer la
     * barre sur deux lignes à 1 280 px (et dès 1 181 px en allemand). Au-dessus de 900 px, elle
     * ouvre le menu « Le projet », séparée des entrées du projet par un filet ; sous 900 px, elle
     * suit les quatre entrées de l'encyclopédie dans le panneau (jamais deux fois).
     *
     * Menus (panneau, projet, compte) : boutons à aria-expanded, Échap ferme et rend le focus au
     * bouton, un clic ou un focus hors du menu le ferme. Styles : resources/css/header/header.css.
     */
    let { compact = false } = $props();

    const MAIN = [
        { href: '/maps', key: 'nav.maps' },
        { href: '/lines', key: 'nav.lines' },
        { href: '/stations', key: 'nav.stations' },
        { href: '/materials', key: 'nav.materials' },
    ];
    const EXTRA = { href: '/accidents', key: 'nav.accidents', icon: 'bi-journal-text' };
    const PROJECT = [
        { href: '/contribute', key: 'nav.contribute', icon: 'bi-pencil-square' },
        { href: '/whoarewe', key: 'nav.whoarewe', icon: 'bi-people' },
        { href: '/donate', key: 'nav.donate', icon: 'bi-heart' },
        { href: '/api', key: 'nav.api', icon: 'bi-code-slash' },
    ];

    let navEl = $state();
    let backgroundEl = $state();
    let menuButton = $state();
    let projectWrapper = $state();
    let projectButton = $state();
    let accountWrapper = $state();
    let accountButton = $state();

    // Utilisateur partagé par HandleInertiaRequests (null = visiteur).
    const user = $derived(page.props.auth?.user ?? null);
    // Notifications non lues du membre (un simple nombre, partagé par HandleInertiaRequests).
    const unread = $derived(page.props.auth?.unread_notifications ?? 0);
    const unreadText = $derived(
        unread > 0 ? $i18n.t('notifications.unread_count', { count: unread }) : '',
    );
    const path = $derived((page.url ?? '/').split(/[?#]/)[0]);
    let accountOpen = $state(false);
    let projectOpen = $state(false);
    // Panneau des liens sous 1 180 px (cf. header.css) ; sans effet au-dessus.
    let menuOpen = $state(false);
    let searchOpen = $state(false);

    // Raccourci affiché selon le système (Cmd sur Mac et iPad, Ctrl ailleurs).
    const shortcut = shortcutLabel();

    const themeLabel = $derived(
        theme.isDark ? $i18n.t('nav.theme_light') : $i18n.t('nav.theme_dark'),
    );
    const searchTitle = $derived(
        `${$i18n.t('nav.search.button_label')} (${$i18n.t('nav.search.shortcut', { keys: shortcut })})`,
    );

    /** Le lien correspond-il à la page affichée (rubrique et ses sous-pages) ? */
    function isCurrent(href) {
        return path === href || path.startsWith(`${href}/`);
    }
    const projectCurrent = $derived([EXTRA, ...PROJECT].some((p) => isCurrent(p.href)));

    function logout() {
        accountOpen = false;
        router.post('/logout');
    }

    function openSearch() {
        menuOpen = false;
        projectOpen = false;
        accountOpen = false;
        searchOpen = true;
    }

    function onDocumentClick(event) {
        if (accountOpen && accountWrapper && !accountWrapper.contains(event.target)) {
            accountOpen = false;
        }
        if (projectOpen && projectWrapper && !projectWrapper.contains(event.target)) {
            projectOpen = false;
        }
        if (menuOpen && navEl && !navEl.contains(event.target)) {
            menuOpen = false;
        }
    }

    function onKeydown(event) {
        // Ctrl K (Cmd K) : recherche de tout le site, depuis n'importe quelle page.
        if (isSearchShortcut(event)) {
            event.preventDefault();
            openSearch();
            return;
        }
        if (event.key !== 'Escape') return;
        if (accountOpen) {
            accountOpen = false;
            accountButton?.focus();
        } else if (projectOpen) {
            projectOpen = false;
            projectButton?.focus();
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
    function onProjectFocusout(event) {
        if (projectOpen && event.relatedTarget && !projectWrapper.contains(event.relatedTarget)) {
            projectOpen = false;
        }
    }
    function onNavFocusout(event) {
        if (menuOpen && event.relatedTarget && !navEl.contains(event.relatedTarget)) {
            menuOpen = false;
        }
    }

    // Une navigation (lien du menu, retour arrière) referme les menus.
    onMount(() =>
        router.on('navigate', () => {
            menuOpen = false;
            projectOpen = false;
            accountOpen = false;
        }),
    );
</script>

<svelte:window onkeydown={onKeydown} />
<svelte:document onclick={onDocumentClick} />

{#snippet mainLinks(extraClass)}
    <ul class={['nav-main', extraClass]} aria-label={$i18n.t('nav.group_encyclopedia')}>
        {#each extraClass === 'in-panel' ? [...MAIN, EXTRA] : MAIN as item (item.href)}
            <li>
                <Link href={item.href} aria-current={isCurrent(item.href) ? 'page' : undefined}
                    >{$i18n.t(item.key)}</Link
                >
            </li>
        {/each}
    </ul>
{/snippet}

{#snippet searchButton(extraClass)}
    <button
        type="button"
        class={['search-trigger', extraClass]}
        aria-haspopup="dialog"
        aria-keyshortcuts="Control+K Meta+K"
        aria-label={$i18n.t('nav.search.button_label')}
        title={searchTitle}
        onclick={openSearch}
    >
        <i class="bi bi-search" aria-hidden="true"></i>
        <span class="search-label">{$i18n.t('nav.search.button')}</span>
    </button>
{/snippet}

<header
    class={['site-header', { compact }]}
    use:navbarFade={{ nav: navEl, background: backgroundEl }}
>
    <div class="header-background" bind:this={backgroundEl}>
        <nav bind:this={navEl} aria-label={$i18n.t('nav.main_label')} onfocusout={onNavFocusout}>
            <div class="nav-inner">
                <Link href="/" class="nav-title">
                    <span class="nav-logo"
                        ><img src={vffLogo} alt="" width="100" height="100" /></span
                    >
                    <span class="nav-title-text">{$i18n.t('common.app_full')}</span>
                </Link>

                <!-- Les quatre entrées de l'encyclopédie, dans la barre dès 901 px. -->
                {@render mainLinks('in-bar')}

                {@render searchButton('in-bar')}
                <button
                    type="button"
                    class="nav-toggle"
                    bind:this={menuButton}
                    aria-expanded={menuOpen}
                    aria-controls="vff-nav-links"
                    onclick={() => (menuOpen = !menuOpen)}
                >
                    <i class="bi {menuOpen ? 'bi-x-lg' : 'bi-list'}" aria-hidden="true"></i>
                    <span class="toggle-label">{$i18n.t('nav.menu')}</span>
                </button>

                <div class={['links', { open: menuOpen }]} id="vff-nav-links">
                    <!-- Panneau du téléphone : les quatre entrées en tête. -->
                    <p class="panel-label" aria-hidden="true">
                        {$i18n.t('nav.group_encyclopedia')}
                    </p>
                    {@render mainLinks('in-panel')}

                    <div class="project" bind:this={projectWrapper} onfocusout={onProjectFocusout}>
                        <button
                            type="button"
                            class={['project-trigger', { current: projectCurrent }]}
                            bind:this={projectButton}
                            aria-expanded={projectOpen}
                            aria-controls="vff-project-menu"
                            onclick={() => (projectOpen = !projectOpen)}
                        >
                            {$i18n.t('nav.group_project')}
                            <i class="bi bi-chevron-down chevron" aria-hidden="true"></i>
                        </button>
                        <p class="panel-label" aria-hidden="true">
                            {$i18n.t('nav.group_project')}
                        </p>
                        <ul
                            class="project-menu"
                            id="vff-project-menu"
                            aria-label={$i18n.t('nav.group_project')}
                            hidden={!projectOpen}
                        >
                            <li class="encyclopedia-extra">
                                <Link
                                    href={EXTRA.href}
                                    aria-current={isCurrent(EXTRA.href) ? 'page' : undefined}
                                >
                                    <i class="bi {EXTRA.icon}" aria-hidden="true"></i>
                                    {$i18n.t(EXTRA.key)}
                                </Link>
                            </li>
                            {#each PROJECT as item (item.href)}
                                <li>
                                    <Link
                                        href={item.href}
                                        aria-current={isCurrent(item.href) ? 'page' : undefined}
                                    >
                                        <i class="bi {item.icon}" aria-hidden="true"></i>
                                        {$i18n.t(item.key)}
                                    </Link>
                                </li>
                            {/each}
                        </ul>
                    </div>

                    <div class="tools">
                        {@render searchButton('in-tools')}
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
                            <li
                                class="account"
                                bind:this={accountWrapper}
                                onfocusout={onAccountFocusout}
                            >
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
                                        {#if unread > 0}
                                            <span class="unread-count" aria-hidden="true"
                                                >{unread.toLocaleString($i18n.language)}</span
                                            >
                                            <span class="vff-sr-only">, {unreadText}</span>
                                        {/if}
                                        <i class="bi bi-chevron-down chevron" aria-hidden="true"
                                        ></i>
                                    </button>
                                    <ul
                                        class="account-menu"
                                        id="vff-account-menu"
                                        hidden={!accountOpen}
                                    >
                                        <li class="account-user">
                                            <strong>{user.name}</strong>
                                            <span>{user.email}</span>
                                        </li>
                                        <li>
                                            <Link
                                                href="/profile"
                                                aria-current={isCurrent('/profile')
                                                    ? 'page'
                                                    : undefined}
                                            >
                                                <i class="bi bi-person" aria-hidden="true"></i>
                                                {$i18n.t('auth.profile')}
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                href="/notifications"
                                                aria-current={isCurrent('/notifications')
                                                    ? 'page'
                                                    : undefined}
                                            >
                                                <i class="bi bi-inbox" aria-hidden="true"></i>
                                                {$i18n.t('notifications.menu')}
                                                {#if unread > 0}
                                                    <span class="unread-count" aria-hidden="true"
                                                        >{unread.toLocaleString(
                                                            $i18n.language,
                                                        )}</span
                                                    >
                                                    <span class="vff-sr-only">, {unreadText}</span>
                                                {/if}
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                href="/contribute/mine"
                                                aria-current={isCurrent('/contribute/mine')
                                                    ? 'page'
                                                    : undefined}
                                            >
                                                <i class="bi bi-pencil-square" aria-hidden="true"
                                                ></i>
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
                                                    <i class="bi bi-shield-check" aria-hidden="true"
                                                    ></i>
                                                    {$i18n.t('nav.review_contributions')}
                                                </Link>
                                            </li>
                                        {/if}
                                        <li>
                                            <button type="button" onclick={logout}>
                                                <i class="bi bi-box-arrow-right" aria-hidden="true"
                                                ></i>
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
                </div>
            </div>
        </nav>
        <!-- Hors de la barre : la barre de l'accueil est transparente tant que le héros est à l'écran. -->
        <GlobalSearch bind:open={searchOpen} {shortcut} />
        {#if !compact}
            <HomeHero logo={vffHero} onsearch={openSearch} />
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

    /* Menus déroulants de la barre (projet, compte) : même cadre que le choix de la langue. */
    .project,
    .account {
        position: relative;
    }
    .project-menu,
    .account-menu {
        position: absolute;
        top: calc(100% + 8px);
        z-index: var(--vff-z-nav);
        min-width: 240px;
        max-width: calc(100vw - 32px);
        margin: 0;
        padding: 6px;
        list-style: none;
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        box-shadow: var(--vff-shadow-popover);
    }
    .project-menu {
        left: 0;
    }
    .account-menu {
        right: 0;
    }
    .project-menu[hidden],
    .account-menu[hidden] {
        display: none;
    }
    /* Accidents (rubrique de l'encyclopédie) : un filet la sépare des entrées du projet. */
    .project-menu .encyclopedia-extra {
        margin-bottom: 6px;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--vff-border);
    }
    .project-menu li,
    .account-menu li {
        display: block;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
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
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }
    .account-menu .account-user span {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .project-menu :global(a),
    .account-menu :global(a),
    .account-menu button {
        display: flex;
        width: 100%;
        min-height: 44px;
        align-items: center;
        gap: 12px;
        margin: 0;
        padding: 8px 12px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        line-height: var(--vff-lh-ui);
        text-align: left;
        text-decoration: none;
        cursor: pointer;
        color: var(--vff-text);
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .project-menu :global(a:hover),
    .account-menu :global(a:hover),
    .account-menu button:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-accent-ink);
    }
    .project-menu :global(a[aria-current='page']),
    .account-menu :global(a[aria-current='page']) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 0.2em;
    }
    .project-menu :global(i),
    .account-menu :global(i) {
        width: 18px;
        font-size: 17px;
        color: var(--vff-text-muted);
        text-align: center;
    }
    .account-trigger .avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--vff-accent);
    }
    /* Nombre de notifications non lues : discret (texte sur fond neutre, pas de pastille vive),
       le même dans le bouton du compte et dans l'entrée « Notifications ». Le texte complet est
       donné aux lecteurs d'écran (.vff-sr-only). */
    .unread-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.6em;
        padding: 1px 7px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-subtle);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        line-height: 1.4;
        font-variant-numeric: tabular-nums;
    }
    .account-menu .unread-count {
        margin-left: auto;
    }
    .account-trigger .bi-person-circle {
        font-size: 22px;
    }
    .chevron {
        font-size: 12px;
        transition: transform var(--vff-transition);
    }
    [aria-expanded='true'] > .chevron {
        transform: rotate(180deg);
    }

    /* Lien « Connexion » des visiteurs : pastille orange, texte noir (7,1:1). */
    .account :global(a.account-link) {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 6px 16px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-accent);
        color: var(--vff-on-accent);
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        white-space: nowrap;
        text-decoration: none;
        transition: background-color var(--vff-transition);
    }
    .account :global(a.account-link:hover) {
        background-color: var(--vff-accent-hover);
    }

    /* Sous 1 180 px : projet et outils sont dans le panneau, les menus se déroulent dans le flux
       (sinon ils étaient rognés par le panneau, qui défile). */
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
        .project-menu,
        .project-menu[hidden] {
            position: static;
            display: block;
            min-width: 0;
            max-width: none;
            padding: 0;
            border: 0;
            border-radius: 0;
            box-shadow: none;
            background: transparent;
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
        .project-menu li {
            border-bottom: 1px solid var(--vff-border);
        }
        .project-menu .encyclopedia-extra {
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .project-menu :global(a) {
            min-height: 48px;
            padding: 0 6px;
            border-radius: 0;
            font-family: var(--vff-font-body);
            font-size: 1.125rem;
        }
        .account-menu :global(a),
        .account-menu button {
            min-height: 44px;
        }
        .account :global(a.account-link) {
            min-height: 44px;
            margin-top: 8px;
        }
    }

    /* Sous 900 px, Accidents suit les entrées de l'encyclopédie dans le panneau. */
    @media (max-width: 900px) {
        .project-menu .encyclopedia-extra {
            display: none;
        }
    }
</style>
