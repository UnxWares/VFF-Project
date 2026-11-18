// Thème clair / sombre des pages de documentation de l'API (/docs/api, /docs/swagger).
// Même cookie `theme` que le reste du site (cf. lib/stores/theme.svelte.js) :
// un choix fait ici vaut aussi pour vff-project.org, et inversement.

const COOKIE = 'theme';

export function isDark() {
    return document.documentElement.dataset.theme === 'dark';
}

function writeCookie(value) {
    const expires = new Date(Date.now() + 365 * 86400000).toUTCString();
    document.cookie = `${COOKIE}=${value}; expires=${expires}; path=/; SameSite=Lax`;
}

/**
 * Branche le bouton [data-theme-toggle] de la barre de navigation.
 * @param {(dark: boolean) => void} [onChange] rappel après chaque bascule
 */
export function setupThemeToggle(onChange) {
    const button = document.querySelector('[data-theme-toggle]');
    if (!button) return;
    button.setAttribute('aria-pressed', String(isDark()));
    button.addEventListener('click', () => {
        const dark = !isDark();
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        button.setAttribute('aria-pressed', String(dark));
        writeCookie(dark ? 'dark' : 'light');
        onChange?.(dark);
    });
}
