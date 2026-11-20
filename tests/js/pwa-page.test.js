// Page hors ligne, bandeau de mise à jour et manifeste de la PWA.
import { readFileSync } from 'node:fs';
import { describe, expect, it, vi } from 'vitest';
import { renderOfflineHtml, storeOfflinePage } from '../../resources/js/pwa/offlinePage.js';
import { showUpdateNotice } from '../../resources/js/pwa/updateNotice.js';
import { buildVersion } from '../../resources/js/pwa/vite-plugin-sw.js';
import { OFFLINE_CACHE, OFFLINE_KEY } from '../../resources/js/pwa/rules.js';
import { fakeCacheStorage } from './support/pwaFakes.js';

describe('renderOfflineHtml', () => {
    const messages = {
        lang: 'de',
        documentTitle: 'Offline - Französische Bahnstrecken',
        title: 'Sie sind offline',
        text: 'Prüfen Sie Ihr Netzwerk.',
        retry: 'Erneut versuchen',
    };

    it('écrit la langue, les textes et un bouton de nouvel essai', () => {
        const html = renderOfflineHtml(messages);
        expect(html).toContain('<html lang="de">');
        expect(html).toContain('<title>Offline - Französische Bahnstrecken</title>');
        expect(html).toContain('<h1>Sie sind offline</h1>');
        expect(html).toContain('>Erneut versuchen</button>');
        expect(html).toContain('noindex');
    });

    it('échappe le HTML des textes', () => {
        const html = renderOfflineHtml({
            ...messages,
            title: '<img src=x onerror=alert(1)>',
            lang: '"><script>',
        });
        expect(html).not.toContain('<img src=x');
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
        expect(html).not.toContain('lang=""><script>');
    });

    it('prend les couleurs du site dans les deux thèmes', () => {
        const html = renderOfflineHtml(messages);
        expect(html).toContain('--bg: #f8f6f1');
        expect(html).toContain('--bg: #0f1419');
        expect(html).toContain('data-theme');
    });
});

describe('storeOfflinePage', () => {
    it('range la page sous la clé que lit le service worker', async () => {
        const cacheStorage = fakeCacheStorage();
        await storeOfflinePage(cacheStorage, '<p>hors ligne</p>');
        const stored = await (await cacheStorage.open(OFFLINE_CACHE)).match(OFFLINE_KEY);
        expect(await stored.text()).toBe('<p>hors ligne</p>');
    });
});

describe('showUpdateNotice', () => {
    function fakeDocument() {
        const make = (tag) => {
            const el = {
                tag,
                children: [],
                attributes: {},
                listeners: {},
                className: '',
                textContent: '',
                disabled: false,
                setAttribute(name, value) {
                    this.attributes[name] = value;
                },
                addEventListener(type, fn) {
                    this.listeners[type] = fn;
                },
                append(...nodes) {
                    this.children.push(...nodes);
                },
                remove: vi.fn(),
            };
            return el;
        };
        return { createElement: make, body: make('body') };
    }

    const find = (node, predicate) =>
        predicate(node) ? node : node.children.map((c) => find(c, predicate)).find(Boolean);

    it('affiche le message dans une zone d état, avec deux boutons', () => {
        const doc = fakeDocument();
        showUpdateNotice(doc, {
            message: 'Nouvelle version',
            apply: 'Recharger',
            dismiss: 'Plus tard',
            onApply: vi.fn(),
            defer: (fn) => fn(),
        });

        const status = find(doc.body, (n) => n.attributes.role === 'status');
        expect(status.textContent).toBe('Nouvelle version');
        expect(find(doc.body, (n) => n.textContent === 'Recharger').tag).toBe('button');
        expect(find(doc.body, (n) => n.textContent === 'Plus tard').tag).toBe('button');
    });

    it('insère la zone d état vide avant d y mettre le texte (annonce par les lecteurs d écran)', () => {
        const doc = fakeDocument();
        let fill;
        showUpdateNotice(doc, {
            message: 'Nouvelle version',
            apply: 'a',
            dismiss: 'b',
            onApply: vi.fn(),
            defer: (fn) => (fill = fn),
        });
        const status = find(doc.body, (n) => n.attributes.role === 'status');
        expect(status.textContent).toBe('');
        fill();
        expect(status.textContent).toBe('Nouvelle version');
    });

    it('applique la mise à jour au clic sur Recharger et se retire avec Plus tard', () => {
        const doc = fakeDocument();
        const onApply = vi.fn();
        showUpdateNotice(doc, {
            message: 'm',
            apply: 'Recharger',
            dismiss: 'Plus tard',
            onApply,
            defer: (fn) => fn(),
        });

        const applyButton = find(doc.body, (n) => n.textContent === 'Recharger');
        applyButton.listeners.click();
        expect(onApply).toHaveBeenCalledOnce();
        expect(applyButton.disabled).toBe(true);

        const root = doc.body.children[0];
        find(doc.body, (n) => n.textContent === 'Plus tard').listeners.click();
        expect(root.remove).toHaveBeenCalledOnce();
    });
});

describe('buildVersion', () => {
    it('ne dépend pas de l ordre des fichiers et change quand un fichier change', () => {
        expect(buildVersion(['b.js', 'a.js'])).toBe(buildVersion(['a.js', 'b.js']));
        expect(buildVersion(['a.js', 'b-NEW.js'])).not.toBe(buildVersion(['a.js', 'b.js']));
        expect(buildVersion(['a.js'])).toMatch(/^[0-9a-f]{12}$/);
    });
});

describe('manifeste web', () => {
    const manifest = JSON.parse(
        readFileSync(new URL('../../public/manifest.webmanifest', import.meta.url), 'utf8'),
    );

    it('porte les champs exigés pour l installation', () => {
        expect(manifest.name).toBe('VFF - Voies Ferrées de France');
        expect(manifest.short_name).toBe('VFF');
        expect(manifest.lang).toBe('fr');
        expect(manifest.id).toBe('/');
        expect(manifest.start_url).toBe('/');
        expect(manifest.scope).toBe('/');
        expect(manifest.display).toBe('standalone');
        expect(manifest.theme_color).toBe('#ff6500');
        expect(manifest.background_color).toMatch(/^#[0-9a-f]{6}$/);
    });

    it('déclare des icônes 192 et 512 px, « any » et « maskable », qui existent toutes', () => {
        for (const [size, purpose] of [
            [192, 'any'],
            [512, 'any'],
            [192, 'maskable'],
            [512, 'maskable'],
        ]) {
            const icon = manifest.icons.find(
                (i) => i.sizes === `${size}x${size}` && i.purpose === purpose,
            );
            expect(icon, `${size} ${purpose}`).toBeDefined();
            const png = readFileSync(new URL(`../../public${icon.src}`, import.meta.url));
            expect(png.readUInt32BE(16), icon.src).toBe(size); // largeur dans l'en-tête IHDR
            expect(png.readUInt32BE(20), icon.src).toBe(size);
        }
    });

    it('n utilise pas de tiret long', () => {
        expect(JSON.stringify(manifest)).not.toMatch(/[—–]|--/);
    });
});
