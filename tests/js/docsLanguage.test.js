import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    markContentLanguage,
    markPortalLanguage,
    SCALAR_SPEC_CONTENT,
} from '../../resources/js/docs/language.js';

// Environnement Node sans DOM : éléments et observateur simulés, juste ce que le module consomme.
function element(matching, children = []) {
    return {
        nodeType: 1,
        lang: '',
        matching,
        children,
        matches: (selector) => matching === selector,
        querySelectorAll: () => children,
    };
}

describe('markContentLanguage', () => {
    let notify;

    afterEach(() => vi.unstubAllGlobals());

    function stubObserver() {
        const observe = vi.fn();
        vi.stubGlobal(
            'MutationObserver',
            class {
                constructor(callback) {
                    notify = callback;
                    this.observe = observe;
                }
            },
        );

        return observe;
    }

    it('marque les éléments déjà affichés', () => {
        stubObserver();
        const a = element('.md');
        const b = element('.md');
        markContentLanguage({ ...element(null, [a, b]) }, '.md', 'fr');

        expect([a.lang, b.lang]).toEqual(['fr', 'fr']);
    });

    it('observe les ajouts sur toute la sous-arborescence', () => {
        const observe = stubObserver();
        const root = element(null);
        markContentLanguage(root, '.md', 'fr');

        expect(observe).toHaveBeenCalledWith(root, { childList: true, subtree: true });
    });

    it('marque un bloc ajouté plus tard, lui-même ou ses descendants', () => {
        stubObserver();
        markContentLanguage(element(null), '.md', 'fr');
        const inner = element('.md');
        const added = element('.md', [inner]);
        const other = element('.autre');
        notify([{ addedNodes: [added, other, { nodeType: 3 }] }]);

        expect([added.lang, inner.lang, other.lang]).toEqual(['fr', 'fr', '']);
    });
});

describe('SCALAR_SPEC_CONTENT', () => {
    const selectors = SCALAR_SPEC_CONTENT.split(', ');

    it('vise les textes de la spécification, pas les libellés de Scalar', () => {
        expect(selectors).toContain('.markdown');
        expect(selectors).toContain('a[href^="#tag/"]');
        expect(selectors).toContain('[id*="/tag/"] .section-header-label');
        // « Introduction » (#description) et « Models » (#models) sont des libellés de Scalar.
        expect(SCALAR_SPEC_CONTENT).not.toContain('#description');
        expect(SCALAR_SPEC_CONTENT).not.toContain('#models');
        expect(SCALAR_SPEC_CONTENT).not.toMatch(/(^|, )\.section-header-label/);
    });

    it('ne contient que des sélecteurs CSS valides et sans doublon', () => {
        expect(new Set(selectors).size).toBe(selectors.length);
        for (const selector of selectors) expect(selector.trim()).toBe(selector);
    });
});

describe('markPortalLanguage', () => {
    let notify;
    let observe;

    beforeEach(() => {
        observe = vi.fn();
        vi.stubGlobal(
            'MutationObserver',
            class {
                constructor(callback) {
                    notify = callback;
                    this.observe = observe;
                }
            },
        );
    });
    afterEach(() => vi.unstubAllGlobals());

    const node = (tagName, lang = '') => ({
        nodeType: 1,
        tagName,
        lang: '',
        getAttribute: (name) => (name === 'lang' ? lang : null),
    });

    it('observe seulement les enfants directs du body', () => {
        const body = {};
        markPortalLanguage(body, 'en');

        expect(observe).toHaveBeenCalledWith(body, { childList: true });
    });

    it('marque une fenêtre ajoutée au body, sans toucher aux scripts ni aux éléments déjà balisés', () => {
        markPortalLanguage({}, 'en');
        const dialog = node('DIV');
        const overlay = node('DIV');
        const script = node('SCRIPT');
        const other = node('DIV', 'de');
        notify([{ addedNodes: [dialog, overlay, script, other, { nodeType: 3 }] }]);

        expect([dialog.lang, overlay.lang, script.lang, other.lang]).toEqual(['en', 'en', '', '']);
    });
});
