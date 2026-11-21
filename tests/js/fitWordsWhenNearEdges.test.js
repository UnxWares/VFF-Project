import { describe, expect, it } from 'vitest';
import { fitWords } from '$lib/fitWords.js';
import { INTERACTION_EVENTS, whenNear } from '$lib/whenNear.js';

const measure = (text) => text.length * 10;

describe('fitWords : cas limites', () => {
    it('rend tel quel un texte vide, absent ou une largeur inutilisable', () => {
        expect(fitWords('', 100, measure)).toBe('');
        expect(fitWords(null, 100, measure)).toBeNull();
        expect(fitWords(undefined, 100, measure)).toBeUndefined();
        expect(fitWords('un deux trois', NaN, measure)).toBe('un deux trois');
        expect(fitWords('un deux trois', -5, measure)).toBe('un deux trois');
        expect(fitWords('un deux trois', undefined, measure)).toBe('un deux trois');
    });

    it('garde le texte quand il tient pile, le coupe dès qu’un pixel manque', () => {
        expect(fitWords('un deux', 70, measure)).toBe('un deux');
        expect(fitWords('un deux', 69, measure)).toBe('un…');
    });

    it('retire la ponctuation de fin du dernier mot gardé, point-virgule et deux-points compris', () => {
        expect(fitWords('gare; ligne : tracé final', 100, measure)).toBe('gare…');
        expect(fitWords('gare, ligne, tracé final de la ligne', 130, measure)).toBe('gare, ligne…');
        expect(fitWords('un : deux trois quatre', 60, measure)).toBe('un…');
    });

    it('mesure le texte coupé avec « … » et choisit le plus long qui rentre', () => {
        const seen = [];
        const spy = (text) => {
            seen.push(text);
            return measure(text);
        };
        expect(fitWords('aa bb cc dd', 90, spy)).toBe('aa bb cc…');
        expect(seen[0]).toBe('aa bb cc dd');
        expect(seen[1]).toBe('aa bb cc…');
    });

    it('n’invente pas de coupure quand même le premier mot suivi de « … » ne rentre pas', () => {
        expect(fitWords('Extraordinairement long', 30, measure)).toBe('Extraordinairement long');
        expect(fitWords('mot', 1, measure)).toBe('mot');
    });
});

describe('whenNear : cas limites', () => {
    const settled = async (promise) => {
        let done = false;
        promise.then(() => (done = true));
        await new Promise((r) => setTimeout(r, 0));
        return done;
    };

    class FakeObserver {
        static last = null;
        constructor(callback, options) {
            this.callback = callback;
            this.options = options;
            this.disconnected = 0;
            FakeObserver.last = this;
        }
        observe() {}
        disconnect() {
            this.disconnected += 1;
        }
    }

    it('liste les cinq interactions qui démarrent la carte', () => {
        expect(INTERACTION_EVENTS).toEqual(['scroll', 'wheel', 'pointerdown', 'touchstart', 'keydown']);
    });

    it('transmet la marge demandée à l’observateur, 0 px par défaut', () => {
        whenNear({}, { Observer: FakeObserver, target: new EventTarget() });
        expect(FakeObserver.last.options).toEqual({ rootMargin: '0px' });
        whenNear({}, { Observer: FakeObserver, target: new EventTarget(), rootMargin: '300px' });
        expect(FakeObserver.last.options).toEqual({ rootMargin: '300px' });
    });

    it('écoute les évènements en capture et sans bloquer le défilement', () => {
        const calls = [];
        const target = {
            addEventListener: (name, fn, options) => calls.push(['add', name, options]),
            removeEventListener: (name, fn, options) => calls.push(['remove', name, options]),
        };
        const { cancel } = whenNear({}, { Observer: FakeObserver, target, events: ['scroll', 'keydown'] });
        expect(calls).toEqual([
            ['add', 'scroll', { capture: true, passive: true }],
            ['add', 'keydown', { capture: true, passive: true }],
        ]);
        cancel();
        expect(calls.slice(2)).toEqual([
            ['remove', 'scroll', { capture: true, passive: true }],
            ['remove', 'keydown', { capture: true, passive: true }],
        ]);
    });

    it('ne réagit qu’aux évènements demandés', async () => {
        const target = new EventTarget();
        const { promise } = whenNear({}, { Observer: FakeObserver, target, events: ['scroll'] });
        target.dispatchEvent(new Event('keydown'));
        expect(await settled(promise)).toBe(false);
        target.dispatchEvent(new Event('scroll'));
        expect(await settled(promise)).toBe(true);
    });

    it('ne déconnecte l’observateur qu’une fois, même avec deux déclencheurs et une annulation tardive', async () => {
        const target = new EventTarget();
        const { promise, cancel } = whenNear({}, { Observer: FakeObserver, target });
        const observer = FakeObserver.last;
        observer.callback([{ isIntersecting: true }]);
        target.dispatchEvent(new Event('scroll'));
        cancel();
        expect(await settled(promise)).toBe(true);
        expect(observer.disconnected).toBe(1);
    });

    it('fonctionne sans cible d’évènements (rendu serveur) et annule sans effet quand il a démarré tout de suite', async () => {
        const { promise, cancel } = whenNear({}, { Observer: FakeObserver, target: null });
        FakeObserver.last.callback([{ isIntersecting: false }, { isIntersecting: true }]);
        expect(await settled(promise)).toBe(true);
        expect(() => cancel()).not.toThrow();
        const immediate = whenNear(null, { Observer: FakeObserver });
        expect(await settled(immediate.promise)).toBe(true);
        expect(() => immediate.cancel()).not.toThrow();
    });
});
