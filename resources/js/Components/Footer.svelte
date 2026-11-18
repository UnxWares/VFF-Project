<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import vffLogo from '$images/vff.png';
    // Fichiers officiels de la charte UnxWares (2026-11-02) : version en couleur sur fond clair,
    // version monochrome inversée (blanche) sur fond sombre. Jamais de nom retapé à côté.
    import unxLogo from '$images/unxwares/unxwares-logo-en-couleur.svg';
    import unxLogoDark from '$images/unxwares/unxwares-logo-monochrome-inverse.svg';
    import unxInsigne from '$images/unxwares/unxwares-insigne-en-couleur.svg';
    import unxInsigneDark from '$images/unxwares/unxwares-insigne-monochrome-inverse.svg';

    /**
     * Pied de page du site. Trois colonnes de liens (organisation voulue par l'utilisateur : ne pas
     * en ajouter ni en retirer sans son accord). Liens externes : nouvel onglet signalé par une
     * icône et un texte lu par les lecteurs d'écran. Styles : resources/css/footer/footer.css.
     */
    const currentYear = new Date().getFullYear();

    const QUICK = [
        { href: '/whoarewe', icon: 'bi-people', key: 'nav.whoarewe' },
        { href: '/maps', icon: 'bi-map', key: 'nav.maps' },
        { href: '/contribute', icon: 'bi-geo', key: 'nav.contribute' },
        { href: '/donate', icon: 'bi-balloon-heart', key: 'nav.donate' },
    ];
    const SOCIAL = [
        {
            href: 'https://discord.com/invite/6SwTfXBx4h',
            icon: 'bi-discord',
            key: 'footer.discord',
        },
        {
            href: 'https://www.instagram.com/vff_project',
            icon: 'bi-instagram',
            key: 'footer.instagram',
        },
        { href: 'https://www.x.com/vff-project', icon: 'bi-twitter-x', key: 'footer.twitter' },
        {
            href: 'https://github.com/UnxWares/VFF-Project',
            icon: 'bi-github',
            key: 'footer.github',
        },
    ];
    const LEGAL = [
        { href: '/legal', icon: 'bi-folder', key: 'nav.legal' },
        { href: '/privacy', icon: 'bi-bell-slash', key: 'nav.privacy' },
        { href: '/use-conditions', icon: 'bi-check2-circle', key: 'nav.use_conditions' },
        { href: '/credits/tech', icon: 'bi-code-square', key: 'nav.credits_tech' },
        { href: '/credits/media', icon: 'bi-camera', key: 'nav.credits_media' },
    ];
</script>

{#snippet newTab()}
    <i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span class="vff-sr-only"
        >({$i18n.t('common.new_tab')})</span
    >
{/snippet}

<footer class="site-footer">
    <div class="footer-infos">
        <div class="banner">
            <img
                class="vff-logo"
                src={vffLogo}
                alt={$i18n.t('nav.logo_alt')}
                width="100"
                height="100"
            />
            <p>{$i18n.t('footer.banner')}</p>
        </div>
        <nav class="footer-links" aria-label={$i18n.t('footer.nav_label')}>
            <div class="footer-col general-links">
                <h2 class="footer-title">
                    {$i18n.t('footer.quick_links')}
                </h2>
                <ul>
                    {#each QUICK as item (item.href)}
                        <li>
                            <Link href={item.href}
                                ><i class="bi {item.icon}" aria-hidden="true"></i>
                                {$i18n.t(item.key)}</Link
                            >
                        </li>
                    {/each}
                    <li class="api">
                        <Link href="/api"
                            ><i class="bi bi-cursor" aria-hidden="true"></i>
                            {$i18n.t('nav.api')}</Link
                        >
                    </li>
                </ul>
            </div>
            <div class="footer-col contact-links">
                <h2 class="footer-title">
                    {$i18n.t('footer.contact_us')}
                </h2>
                <ul>
                    {#each SOCIAL as item (item.href)}
                        <li>
                            <a href={item.href} rel="noopener noreferrer" target="_blank">
                                <i class="bi {item.icon}" aria-hidden="true"></i>
                                {$i18n.t(item.key)}{@render newTab()}
                            </a>
                        </li>
                    {/each}
                    <li class="unxwares">
                        <a
                            href="https://www.unxwares.studio/vff-project"
                            rel="noopener noreferrer"
                            target="_blank"
                        >
                            <img
                                class="unx-insigne theme-light"
                                src={unxInsigne}
                                alt=""
                                width="24"
                                height="24"
                            />
                            <img
                                class="unx-insigne theme-dark"
                                src={unxInsigneDark}
                                alt=""
                                width="24"
                                height="24"
                            />
                            {$i18n.t('footer.unxwares')}{@render newTab()}
                        </a>
                    </li>
                </ul>
            </div>
            <div class="footer-col legal-links">
                <h2 class="footer-title">{$i18n.t('footer.legal')}</h2>
                <ul>
                    {#each LEGAL as item (item.href)}
                        <li>
                            <Link href={item.href}
                                ><i class="bi {item.icon}" aria-hidden="true"></i>
                                {$i18n.t(item.key)}</Link
                            >
                        </li>
                    {/each}
                </ul>
            </div>
        </nav>
    </div>
    <hr />
    <div class="copyright">
        <p>{$i18n.t('footer.copyright', { start: 2023, end: currentYear })}</p>
        <p class="credits">
            <span class="powered">
                {$i18n.t('footer.powered_by')}
                <a
                    class="unx-logo-link"
                    href="https://www.unxwares.studio/"
                    rel="noopener noreferrer"
                    target="_blank"
                >
                    <img
                        class="unx-logo theme-light"
                        src={unxLogo}
                        alt={$i18n.t('footer.unxwares')}
                        width="140"
                        height="29"
                    />
                    <img
                        class="unx-logo theme-dark"
                        src={unxLogoDark}
                        alt={$i18n.t('footer.unxwares')}
                        width="140"
                        height="29"
                    />{@render newTab()}
                </a>
            </span>
            <span class="made-by">{$i18n.t('footer.made_by', { name: 'Baptiste Gosselin' })}</span>
        </p>
    </div>
</footer>
