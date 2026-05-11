// Store du toggle thème clair/sombre.
// Pose `data-theme="dark|light"` sur <html> pour que les overrides CSS
// `[data-theme="dark"]` de general.css s'activent. Persiste via cookie 1 an.
// Par défaut clair, seul un clic explicite passe en sombre (pas d'auto-détection
// prefers-color-scheme — comportement aligné sur l'origine).

const COOKIE = 'theme';
const DAYS = 365;

function readCookie(name) {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

function writeCookie(name, value, days) {
    if (typeof document === 'undefined') return;
    const expires = new Date(Date.now() + days * 86400000).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
}

function applyTheme(isDark) {
    if (typeof document === 'undefined') return;
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
}

class ThemeStore {
    isDark = $state(false);

    constructor() {
        if (typeof document !== 'undefined') {
            this.isDark = readCookie(COOKIE) === 'dark';
            applyTheme(this.isDark);
        }
    }

    toggle() {
        this.isDark = !this.isDark;
        writeCookie(COOKIE, this.isDark ? 'dark' : 'light', DAYS);
        applyTheme(this.isDark);
    }
}

export const theme = new ThemeStore();
