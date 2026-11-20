// Thème des pages de documentation de l'API (resources/js/docs/theme.js) : même règle que le site
// (lib/stores/theme.svelte.js). Sans choix enregistré, le thème suit le système ; le bouton bascule vers
// l'opposé ; revenir au thème du système efface le cookie `theme`. `document` et `window.matchMedia` simulés.
import { afterEach, describe, expect, it, vi } from 'vitest';

function setup({ cookie = '', theme = 'light', systemDark = false } = {}) {
    const jar = { value: cookie };
    const handlers = {};
    const button = {
        attrs: {},
        setAttribute: (k, v) => (button.attrs[k] = v),
        addEventListener: (type, fn) => (handlers[type] = fn),
        click: () => handlers.click(),
    };
    const meta = { content: 'light dark', setAttribute: (k, v) => (meta[k] = v) };
    const doc = {
        documentElement: { dataset: { theme } },
        querySelector: (sel) =>
            sel === '[data-theme-toggle]' ? button : sel.includes('color-scheme') ? meta : null,
        get cookie() {
            return jar.value;
        },
        set cookie(v) {
            jar.value = v;
        },
    };
    const listeners = [];
    const query = { matches: systemDark, addEventListener: (t, fn) => listeners.push(fn) };
    vi.stubGlobal('document', doc);
    vi.stubGlobal('window', { matchMedia: () => query });
    return {
        doc,
        button,
        jar,
        meta,
        system: (v) => {
            query.matches = v;
            listeners.forEach((fn) => fn({ matches: v }));
        },
    };
}

async function load() {
    vi.resetModules();
    return import('../../resources/js/docs/theme.js');
}

afterEach(() => vi.unstubAllGlobals());

describe('thème des documentations', () => {
    it('bascule vers le sombre quand le système est clair : cookie dark enregistré', async () => {
        const env = setup({ theme: 'light', systemDark: false });
        const { setupThemeToggle } = await load();
        const onChange = vi.fn();
        setupThemeToggle(onChange);

        env.button.click();

        expect(env.doc.documentElement.dataset.theme).toBe('dark');
        expect(env.jar.value).toMatch(/^theme=dark;/);
        expect(env.meta.content).toBe('dark');
        expect(env.button.attrs['aria-pressed']).toBe('true');
        expect(onChange).toHaveBeenCalledWith(true);
    });

    it('revenir au thème du système efface le cookie', async () => {
        const env = setup({ cookie: 'theme=dark', theme: 'dark', systemDark: false });
        const { setupThemeToggle } = await load();
        setupThemeToggle();

        env.button.click();

        expect(env.doc.documentElement.dataset.theme).toBe('light');
        expect(env.jar.value).toMatch(/^theme=; expires=Thu, 01 Jan 1970/);
    });

    it('sans cookie, suit le système quand il change', async () => {
        const env = setup({ theme: 'light', systemDark: false });
        const { setupThemeToggle } = await load();
        const onChange = vi.fn();
        setupThemeToggle(onChange);

        env.system(true);

        expect(env.doc.documentElement.dataset.theme).toBe('dark');
        expect(onChange).toHaveBeenCalledWith(true);
    });

    it('avec un choix enregistré, ignore le système', async () => {
        const env = setup({ cookie: 'theme=light', theme: 'light', systemDark: false });
        const { setupThemeToggle } = await load();
        const onChange = vi.fn();
        setupThemeToggle(onChange);

        env.system(true);

        expect(env.doc.documentElement.dataset.theme).toBe('light');
        expect(onChange).not.toHaveBeenCalled();
    });
});
