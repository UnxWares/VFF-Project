// Store du thème clair/sombre : lit et écrit le cookie `theme`, pose data-theme
// sur <html>. `document` est simulé (pas de DOM complet) ; le module est
// réimporté à chaque test pour rejouer son initialisation.
import { afterEach, describe, expect, it, vi } from 'vitest';

function fakeDocument(cookie = '') {
    const jar = { value: cookie };
    return {
        documentElement: { dataset: {} },
        get cookie() {
            return jar.value;
        },
        set cookie(v) {
            jar.value = v;
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
    it('clair par défaut (pas d\'auto-détection), data-theme posé', async () => {
        const doc = fakeDocument('');
        vi.stubGlobal('document', doc);

        const theme = await loadTheme();

        expect(theme.isDark).toBe(false);
        expect(doc.documentElement.dataset.theme).toBe('light');
    });

    it('relit le cookie « dark » parmi d\'autres cookies', async () => {
        const doc = fakeDocument('locale=fr; theme=dark; other=1');
        vi.stubGlobal('document', doc);

        const theme = await loadTheme();

        expect(theme.isDark).toBe(true);
        expect(doc.documentElement.dataset.theme).toBe('dark');
    });

    it('un cookie dont le nom finit par « theme » n\'est pas confondu', async () => {
        vi.stubGlobal('document', fakeDocument('mytheme=dark'));

        expect((await loadTheme()).isDark).toBe(false);
    });

    it('toggle : bascule, persiste un an (path=/, SameSite=Lax) et met à jour <html>', async () => {
        const doc = fakeDocument('');
        vi.stubGlobal('document', doc);
        const theme = await loadTheme();

        theme.toggle();

        expect(theme.isDark).toBe(true);
        expect(doc.documentElement.dataset.theme).toBe('dark');
        expect(doc.cookie).toMatch(/^theme=dark; expires=[^;]+; path=\/; SameSite=Lax$/);
        const expires = new Date(doc.cookie.match(/expires=([^;]+)/)[1]);
        expect((expires - Date.now()) / 86_400_000).toBeGreaterThan(364);

        theme.toggle();
        expect(theme.isDark).toBe(false);
        expect(doc.cookie).toMatch(/^theme=light;/);
    });

    it('côté serveur (sans document) : clair, toggle sans erreur', async () => {
        vi.stubGlobal('document', undefined);
        const theme = await loadTheme();

        expect(theme.isDark).toBe(false);
        expect(() => theme.toggle()).not.toThrow();
        expect(theme.isDark).toBe(true);
    });
});
