<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import vffLogo from '$images/vff.png';
    import unxwares from '$images/unxwares.png';

    const currentYear = new Date().getFullYear();
</script>

<footer>
    <div class="footer-infos">
        <div class="banner">
            <figure>
                <img src={vffLogo} alt="Logo de VFF" />
            </figure>
            <p>{$i18n.t('footer.banner')}</p>
        </div>
        <div class="footer-links">
            <ul class="general-links">
                <h3>{$i18n.t('footer.quick_links')}</h3>
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
                <hr />
                <li class="api">
                    <Link href="/api"><i class="bi bi-cursor"></i> {$i18n.t('nav.api')}</Link>
                </li>
            </ul>
            <ul class="contact-links">
                <h3>{$i18n.t('footer.contact_us')}</h3>
                <li class="discord">
                    <a href="https://discord.com/invite/6SwTfXBx4h" rel="noopener noreferrer" target="_blank">
                        <i class="bi bi-discord"></i> {$i18n.t('footer.discord')}
                    </a>
                </li>
                <li class="instagram">
                    <a href="https://www.instagram.com/vff_project" rel="noopener noreferrer" target="_blank">
                        <i class="bi bi-instagram"></i> {$i18n.t('footer.instagram')}
                    </a>
                </li>
                <li class="twitter-x">
                    <a href="https://www.x.com/vff-project" rel="noopener noreferrer" target="_blank">
                        <i class="bi bi-twitter-x"></i> {$i18n.t('footer.twitter')}
                    </a>
                </li>
                <li class="github">
                    <a href="https://www.github.com/vff-project" rel="noopener noreferrer" target="_blank">
                        <i class="bi bi-github"></i> {$i18n.t('footer.github')}
                    </a>
                </li>
                <li class="unxwares">
                    <a href="https://www.unxwares.studio/vff-project" rel="noopener noreferrer" target="_blank">
                        <img src={unxwares} alt="" /> {$i18n.t('footer.unxwares')}
                    </a>
                </li>
            </ul>
            <ul class="legal-links">
                <h3>{$i18n.t('footer.legal')}</h3>
                <li class="legal">
                    <Link href="/legal"><i class="bi bi-folder"></i> {$i18n.t('nav.legal')}</Link>
                </li>
                <li class="privacy">
                    <Link href="/privacy"><i class="bi bi-bell-slash"></i> {$i18n.t('nav.privacy')}</Link>
                </li>
                <li class="use-conditions">
                    <Link href="/use-conditions"><i class="bi bi-check2-circle"></i> {$i18n.t('nav.use_conditions')}</Link>
                </li>
            </ul>
        </div>
    </div>
    <hr />
    <div class="copyright">
        <p>{$i18n.t('footer.copyright', { start: 2023, end: currentYear })}</p>
        <p>
            {$i18n.t('footer.powered_by')}
            <a href="https://www.unxwares.studio/" rel="noopener noreferrer" target="_blank">
                <img src={unxwares} alt="UnxWares" /> UnxWares
            </a>
            · {$i18n.t('footer.made_by', { name: 'Baptiste Gosselin' })}
        </p>
    </div>
</footer>
