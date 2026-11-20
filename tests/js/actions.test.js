// Actions Svelte de la page d'accueil, avec window / IntersectionObserver simulés.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { navbarFade } from '$lib/actions/navbarFade.js';
import { scrollReveal } from '$lib/actions/scrollReveal.js';
import { scrollToContent } from '$lib/actions/smoothScrollTo.js';

/** Élément simulé : style et classList (add / remove / contains), comme un DOMTokenList. */
const el = () => {
    const classes = new Set();
    return {
        style: {},
        classList: {
            add: (c) => classes.add(c),
            remove: (c) => classes.delete(c),
            contains: (c) => classes.has(c),
        },
    };
};

function fakeWindow(scrollY = 0) {
    const listeners = {};
    return {
        scrollY,
        innerHeight: 900,
        scrollTo: vi.fn(),
        addEventListener: vi.fn((type, fn) => {
            listeners[type] = fn;
        }),
        removeEventListener: vi.fn((type) => {
            delete listeners[type];
        }),
        listeners,
    };
}

/** IntersectionObserver simulé : `observers` garde chaque instance pour déclencher le rappel. */
function stubObserver() {
    const observers = [];
    class FakeObserver {
        constructor(callback, options) {
            this.callback = callback;
            this.options = options;
            this.observed = [];
            this.disconnect = vi.fn();
            observers.push(this);
        }
        observe(node) {
            this.observed.push(node);
        }
        fire(isIntersecting) {
            this.callback([{ isIntersecting }]);
        }
    }
    vi.stubGlobal('IntersectionObserver', FakeObserver);
    return observers;
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

// parallaxTGV : tests/js/parallaxTGV.test.js.

describe('scrollReveal', () => {
    it("retire .noTranslate puis ajoute .animate à l'entrée dans le viewport", () => {
        vi.useFakeTimers();
        const observers = stubObserver();
        const node = { classList: { remove: vi.fn(), add: vi.fn() } };

        const action = scrollReveal(node, { threshold: 0.2 });
        expect(observers[0].options).toEqual({ threshold: 0.2 });
        expect(observers[0].observed).toEqual([node]);

        observers[0].fire(false);
        expect(node.classList.remove).not.toHaveBeenCalled();

        observers[0].fire(true);
        expect(node.classList.remove).toHaveBeenCalledWith('noTranslate');
        expect(node.classList.add).not.toHaveBeenCalled();
        vi.advanceTimersByTime(20);
        expect(node.classList.add).toHaveBeenCalledWith('animate');

        action.destroy();
        expect(observers[0].disconnect).toHaveBeenCalled();
    });

    it('seuil par défaut : 0,5', () => {
        const observers = stubObserver();
        scrollReveal({ classList: { remove() {}, add() {} } });
        expect(observers[0].options).toEqual({ threshold: 0.5 });
    });
});

describe('navbarFade', () => {
    it("nav cachée tant que l'en-tête est visible, affichée (puis fond relatif) quand il sort", () => {
        vi.useFakeTimers();
        const observers = stubObserver();
        const nav = el();
        const background = el();

        navbarFade({}, { nav, background });
        observers[0].fire(true);
        expect(nav.classList.contains('nav-shown')).toBe(false);
        expect(nav.classList.contains('nav-hidden')).toBe(true); // invisible : ne capte plus les clics
        expect(nav.style.opacity).toBeUndefined(); // plus de style en ligne : les classes suffisent
        expect(background.style.position).toBe('sticky');

        observers[0].fire(false);
        expect(nav.classList.contains('nav-shown')).toBe(true);
        expect(nav.classList.contains('nav-hidden')).toBe(false);
        expect(background.style.position).toBe('sticky');
        vi.advanceTimersByTime(1000);
        expect(background.style.position).toBe('relative');
    });

    it('en-tête revenu avant la fin du délai : le fond reste sticky', () => {
        vi.useFakeTimers();
        const observers = stubObserver();
        const nav = el();
        const background = el();

        navbarFade({}, { nav, background });
        observers[0].fire(false);
        observers[0].fire(true);
        vi.advanceTimersByTime(1000);
        expect(background.style.position).toBe('sticky');
    });

    it('update() remplace les éléments, destroy() déconnecte', () => {
        const observers = stubObserver();
        const action = navbarFade({}, {});
        const nav = el();
        const background = el();

        observers[0].fire(true); // sans éléments : rien
        action.update({ nav, background });
        observers[0].fire(true);
        expect(nav.classList.contains('nav-hidden')).toBe(true);

        action.destroy();
        expect(observers[0].disconnect).toHaveBeenCalled();
    });
});

describe('scrollToContent', () => {
    it("défile en douceur sous l'en-tête (2 hauteurs d'écran - 130 px)", () => {
        const win = fakeWindow();
        vi.stubGlobal('window', win);

        scrollToContent();

        expect(win.scrollTo).toHaveBeenCalledWith({ top: 900 * 2 - 130, behavior: 'smooth' });
    });
});
