// Actions Svelte de la page d'accueil, avec window / IntersectionObserver simulés.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { scrollReveal } from '$lib/actions/scrollReveal.js';
import { scrollToContent } from '$lib/actions/smoothScrollTo.js';

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

describe('scrollToContent', () => {
    it("défile en douceur sous l'en-tête (2 hauteurs d'écran - 130 px)", () => {
        const win = fakeWindow();
        vi.stubGlobal('window', win);

        scrollToContent();

        expect(win.scrollTo).toHaveBeenCalledWith({ top: 900 * 2 - 130, behavior: 'smooth' });
    });
});
