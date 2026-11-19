import { afterEach, describe, expect, it, vi } from 'vitest';
import { markContentLanguage } from '../../resources/js/docs/language.js';

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
