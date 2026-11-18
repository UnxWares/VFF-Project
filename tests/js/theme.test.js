// Store du thème clair/sombre : suit la préférence du système sans choix enregistré, lit et écrit
// le cookie `theme`, pose data-theme sur <html>. `document` et `window.matchMedia` sont simulés
// (pas de DOM complet) ; le module est réimporté à chaque test pour rejouer son initialisation.
import { afterEach, describe, expect, it, vi } from 'vitest';

function fakeDocument(cookie = '') {
    const jar = { value: cookie };
    const meta = { content: 'light dark', setAttribute: (k, v) => (meta[k] = v) };
    return {
        documentElement: { dataset: {} },
        querySelector: (sel) => (sel.includes('color-scheme') ? meta : null),
        meta,
        get cookie() {
            return jar.value;
        },
        set cookie(v) {
            jar.value = v;
        },
    };
}

/** window.matchMedia simulé : `dark` = préférence du système ; `change(bool)` la modifie. */
function fakeWindow(dark = false) {
    const listeners = [];
    const query = {
        matches: dark,
        addEventListener: (type, fn) => listeners.push(fn),
    };
    return {
        matchMedia: () => query,
        change(value) {
            query.matches = value;
            listeners.forEach((fn) => fn({ matches: value }));
        },
    };
}

async function loadTheme() {
    vi.resetModules();
    return (await import('$lib/stores/theme.svelte.js')).theme;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('thème', () => {
    it('sans cookie ni préférence sombre : clair, data-theme et color-scheme posés', async () => {
        const doc = fakeDocument('');
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', fakeWindow(false));

        const theme = await loadTheme();

        expect(theme.isDark).toBe(false);
        expect(theme.choice).toBe(null);
        expect(doc.documentElement.dataset.theme).toBe('light');
        expect(doc.meta.content).toBe('light');
    });

    it('sans cookie, système sombre : sombre', async () => {
        const doc = fakeDocument('');
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', fakeWindow(true));

        const theme = await loadTheme();

        expect(theme.isDark).toBe(true);
        expect(doc.documentElement.dataset.theme).toBe('dark');
    });

    it("suit un changement de préférence du système tant qu'aucun choix n'est fait", async () => {
        const doc = fakeDocument('');
        const win = fakeWindow(false);
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', win);
        const theme = await loadTheme();

        win.change(true);

        expect(theme.isDark).toBe(true);
        expect(doc.documentElement.dataset.theme).toBe('dark');
    });

    it("un choix enregistré l'emporte sur le système, même s'il change", async () => {
        const doc = fakeDocument('locale=fr; theme=light; other=1');
        const win = fakeWindow(true);
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', win);

        const theme = await loadTheme();
        expect(theme.isDark).toBe(false);
        expect(theme.choice).toBe('light');

        win.change(false);
        win.change(true);
        expect(theme.isDark).toBe(false);
    });

    it("relit le cookie « dark » parmi d'autres cookies", async () => {
        const doc = fakeDocument('locale=fr; theme=dark; other=1');
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', fakeWindow(false));

        const theme = await loadTheme();

        expect(theme.isDark).toBe(true);
        expect(doc.documentElement.dataset.theme).toBe('dark');
    });

    it("un cookie dont le nom finit par « theme » n'est pas confondu", async () => {
        vi.stubGlobal('document', fakeDocument('mytheme=dark'));
        vi.stubGlobal('window', fakeWindow(false));

        expect((await loadTheme()).isDark).toBe(false);
    });

    it('toggle vers le thème opposé au système : choix gardé un an (path=/, SameSite=Lax)', async () => {
        const doc = fakeDocument('');
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', fakeWindow(false));
        const theme = await loadTheme();

        theme.toggle();

        expect(theme.isDark).toBe(true);
        expect(theme.choice).toBe('dark');
        expect(doc.documentElement.dataset.theme).toBe('dark');
        expect(doc.cookie).toMatch(/^theme=dark; expires=[^;]+; path=\/; SameSite=Lax$/);
        const expires = new Date(doc.cookie.match(/expires=([^;]+)/)[1]);
        expect((expires - Date.now()) / 86_400_000).toBeGreaterThan(364);
    });

    it('toggle retour au thème du système : choix effacé (le site suit de nouveau le système)', async () => {
        const doc = fakeDocument('theme=dark');
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', fakeWindow(false));
        const theme = await loadTheme();

        theme.toggle();

        expect(theme.isDark).toBe(false);
        expect(theme.choice).toBe(null);
        expect(doc.documentElement.dataset.theme).toBe('light');
        expect(doc.cookie).toMatch(/^theme=; expires=Thu, 01 Jan 1970/);
    });

    it('côté serveur (sans document ni window) : clair, toggle sans erreur', async () => {
        vi.stubGlobal('document', undefined);
        vi.stubGlobal('window', undefined);
        const theme = await loadTheme();

        expect(theme.isDark).toBe(false);
        expect(() => theme.toggle()).not.toThrow();
        expect(theme.isDark).toBe(true);
    });
});
