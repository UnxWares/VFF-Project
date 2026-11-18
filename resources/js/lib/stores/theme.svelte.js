// Thème clair / sombre du site.
// Sans choix enregistré, le site suit la préférence du système (prefers-color-scheme), y compris
// quand elle change pendant la visite. Le bouton de l'en-tête bascule vers le thème opposé à celui
// affiché : si ce thème est celui du système, le choix est effacé (le site suit de nouveau le
// système) ; sinon il est gardé un an dans le cookie `theme` (dark | light), lu aussi par les pages
// de documentation de l'API (resources/js/docs/theme.js).
// Le thème est posé avant le premier rendu par le script en ligne de resources/views/app.blade.php
// (attribut data-theme sur <html>, pas de flash) ; ce module reprend l'état de ce script.

const COOKIE = 'theme';
const DAYS = 365;
const QUERY = '(prefers-color-scheme: dark)';

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

function clearCookie(name) {
    if (typeof document === 'undefined') return;
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax`;
}

function systemQuery() {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return null;
    return window.matchMedia(QUERY);
}

function applyTheme(isDark) {
    if (typeof document === 'undefined') return;
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    document
        .querySelector?.('meta[name="color-scheme"]')
        ?.setAttribute('content', isDark ? 'dark' : 'light');
}

class ThemeStore {
    /** Thème affiché. */
    isDark = $state(false);
    /** Choix enregistré : 'dark', 'light', ou null (le site suit le système). */
    choice = $state(null);

    constructor() {
        if (typeof document === 'undefined') return;
        const saved = readCookie(COOKIE);
        this.choice = saved === 'dark' || saved === 'light' ? saved : null;
        const query = systemQuery();
        this.isDark = this.choice ? this.choice === 'dark' : query?.matches === true;
        applyTheme(this.isDark);
        // Préférence du système modifiée pendant la visite : suivie tant qu'aucun choix n'est fait.
        query?.addEventListener?.('change', (event) => {
            if (this.choice) return;
            this.isDark = event.matches;
            applyTheme(this.isDark);
        });
    }

    toggle() {
        this.isDark = !this.isDark;
        const systemDark = systemQuery()?.matches === true;
        if (this.isDark === systemDark) {
            this.choice = null;
            clearCookie(COOKIE);
        } else {
            this.choice = this.isDark ? 'dark' : 'light';
            writeCookie(COOKIE, this.choice, DAYS);
        }
        applyTheme(this.isDark);
    }
}

export const theme = new ThemeStore();
