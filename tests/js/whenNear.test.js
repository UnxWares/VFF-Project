import { describe, expect, it } from 'vitest';
import { whenNear } from '../../resources/js/lib/whenNear.js';

/** Observateur factice : garde le rappel pour le déclencher à la main. */
function fakeObserver() {
    const state = { callback: null, observed: [], disconnected: 0, options: null };
    class Observer {
        constructor(callback, options) {
            state.callback = callback;
            state.options = options;
        }
        observe(el) {
            state.observed.push(el);
        }
        disconnect() {
            state.disconnected += 1;
        }
    }
    return { Observer, state };
}

const settled = async (promise) => {
    let done = false;
    promise.then(() => (done = true));
    await new Promise((r) => setTimeout(r, 0));
    return done;
};

describe('whenNear', () => {
    it('attend que le cadre entre à l’écran', async () => {
        const { Observer, state } = fakeObserver();
        const target = new EventTarget();
        const el = {};
        const { promise } = whenNear(el, { Observer, target });
        expect(state.observed).toEqual([el]);
        expect(await settled(promise)).toBe(false);
        state.callback([{ isIntersecting: false }]);
        expect(await settled(promise)).toBe(false);
        state.callback([{ isIntersecting: true }]);
        expect(await settled(promise)).toBe(true);
        expect(state.disconnected).toBe(1);
    });

    it('démarre à la première interaction et retire ses écouteurs', async () => {
        const { Observer, state } = fakeObserver();
        const target = new EventTarget();
        const { promise } = whenNear({}, { Observer, target });
        target.dispatchEvent(new Event('scroll'));
        expect(await settled(promise)).toBe(true);
        expect(state.disconnected).toBe(1);
        // Les interactions suivantes ne refont rien (écouteurs retirés).
        target.dispatchEvent(new Event('keydown'));
        expect(state.disconnected).toBe(1);
    });

    it('démarre tout de suite sans observateur ou sans cadre', async () => {
        expect(await settled(whenNear(null, { Observer: fakeObserver().Observer }).promise)).toBe(
            true,
        );
        expect(await settled(whenNear({}, { Observer: undefined }).promise)).toBe(true);
    });

    it('s’annule quand le composant disparaît', async () => {
        const { Observer, state } = fakeObserver();
        const target = new EventTarget();
        const { promise, cancel } = whenNear({}, { Observer, target });
        cancel();
        expect(state.disconnected).toBe(1);
        target.dispatchEvent(new Event('pointerdown'));
        expect(await settled(promise)).toBe(false);
    });
});
