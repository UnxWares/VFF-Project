// Thème clair / sombre des pages de documentation de l'API (/docs/api, /docs/swagger, /graphiql).
// Même règle que le site (resources/js/lib/stores/theme.svelte.js) : sans choix enregistré, le thème
// suit la préférence du système, y compris quand elle change pendant la visite ; le bouton bascule vers
// le thème opposé à celui affiché ; revenir au thème du système efface le cookie `theme` (dark | light),
// sinon il est gardé un an. Le thème est posé avant le premier rendu par docs/_head.blade.php.

const COOKIE = 'theme';
const DAYS = 365;
const QUERY = '(prefers-color-scheme: dark)';

export function isDark() {
    return document.documentElement.dataset.theme === 'dark';
}

function readChoice() {
    const match = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
    return match ? match[1] : null;
}

function writeCookie(value) {
    const expires = new Date(Date.now() + DAYS * 86400000).toUTCString();
    document.cookie = `${COOKIE}=${value}; expires=${expires}; path=/; SameSite=Lax`;
}

function clearCookie() {
    document.cookie = `${COOKIE}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax`;
}

function apply(dark) {
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    document
        .querySelector('meta[name="color-scheme"]')
        ?.setAttribute('content', dark ? 'dark' : 'light');
}

/**
 * Branche le bouton [data-theme-toggle] de la barre de navigation et suit le système tant qu'aucun
 * choix n'est enregistré.
 * @param {(dark: boolean) => void} [onChange] rappel après chaque changement de thème
 */
export function setupThemeToggle(onChange) {
    const button = document.querySelector('[data-theme-toggle]');
    const query = window.matchMedia?.(QUERY);

    query?.addEventListener?.('change', (event) => {
        if (readChoice()) return;
        apply(event.matches);
        button?.setAttribute('aria-pressed', String(event.matches));
        onChange?.(event.matches);
    });

    if (!button) return;
    button.setAttribute('aria-pressed', String(isDark()));
    button.addEventListener('click', () => {
        const dark = !isDark();
        if (dark === (query?.matches === true)) clearCookie();
        else writeCookie(dark ? 'dark' : 'light');
        apply(dark);
        button.setAttribute('aria-pressed', String(dark));
        onChange?.(dark);
    });
}
