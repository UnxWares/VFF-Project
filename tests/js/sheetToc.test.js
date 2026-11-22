import { describe, expect, it } from 'vitest';
import { currentSection, hiddenEdges, revealScrollLeft, settleOnSection } from '$lib/sheetToc.js';

const sections = (...tops) => tops.map((top, i) => ({ id: `s${i}`, top }));
const view = { line: 160, atEnd: false, viewportHeight: 800 };

describe('currentSection', () => {
    it('aucune section avant la première', () => {
        expect(currentSection(sections(400, 1200, 2000), view)).toBeNull();
    });
    it('la dernière section passée au-dessus de la ligne de lecture', () => {
        expect(currentSection(sections(-900, 80, 700), view)).toBe('s1');
        expect(currentSection(sections(-900, -200, 160), view)).toBe('s2');
    });
    it('en bas de page, la dernière section visible devient courante', () => {
        expect(currentSection(sections(-900, -100, 500), { ...view, atEnd: true })).toBe('s2');
        expect(currentSection(sections(-900, -100, 900), { ...view, atEnd: true })).toBe('s1');
    });
    it('sans section : aucune', () => {
        expect(currentSection([], view)).toBeNull();
    });
});

describe('revealScrollLeft', () => {
    const row = { scrollLeft: 0, clientWidth: 300 };
    it('rien à faire quand l’arrêt est visible', () => {
        expect(revealScrollLeft({ left: 40, width: 80 }, row)).toBeNull();
    });
    it('fait défiler jusqu’à l’arrêt, marge comprise', () => {
        expect(revealScrollLeft({ left: 400, width: 80 }, row)).toBe(376);
        expect(
            revealScrollLeft({ left: 10, width: 80 }, { scrollLeft: 200, clientWidth: 300 }),
        ).toBe(0);
    });
});

describe('hiddenEdges', () => {
    it('rangée qui tient en entier : aucun fondu', () => {
        expect(hiddenEdges({ scrollLeft: 0, scrollWidth: 300, clientWidth: 300 })).toEqual({
            before: false,
            after: false,
        });
    });
    it('au début : arrêts cachés à droite seulement', () => {
        expect(hiddenEdges({ scrollLeft: 0, scrollWidth: 900, clientWidth: 300 })).toEqual({
            before: false,
            after: true,
        });
    });
    it('au milieu : des deux côtés ; au bout : à gauche seulement', () => {
        expect(hiddenEdges({ scrollLeft: 200, scrollWidth: 900, clientWidth: 300 })).toEqual({
            before: true,
            after: true,
        });
        expect(hiddenEdges({ scrollLeft: 600, scrollWidth: 900, clientWidth: 300 })).toEqual({
            before: true,
            after: false,
        });
    });
    it('tolère un pixel d’arrondi (zoom du navigateur)', () => {
        expect(hiddenEdges({ scrollLeft: 599.5, scrollWidth: 900, clientWidth: 300 })).toEqual({
            before: true,
            after: false,
        });
    });
});

describe('settleOnSection', () => {
    function fakeWindow({ top, margin = 80 }) {
        let scrollY = 1000;
        let currentTop = top;
        const listeners = {};
        const frames = [];
        let now = 0;
        const env = {
            performance: { now: () => now },
            get scrollY() {
                return scrollY;
            },
            document: {
                getElementById: (id) =>
                    id === 'x' ? { getBoundingClientRect: () => ({ top: currentTop }) } : null,
            },
            getComputedStyle: () => ({ scrollMarginTop: `${margin}px` }),
            scrollBy: ({ top: dy }) => {
                scrollY += dy;
                currentTop -= dy;
            },
            requestAnimationFrame: (cb) => frames.push(cb),
            cancelAnimationFrame: () => {},
            addEventListener: (name, fn) => (listeners[name] = fn),
            removeEventListener: (name) => delete listeners[name],
        };
        return {
            env,
            listeners,
            frames,
            tick: (ms = 16) => {
                now += ms;
                frames.shift()?.();
            },
            top: () => currentTop,
        };
    }

    it('corrige la position de la section jusqu a sa marge de défilement', () => {
        const w = fakeWindow({ top: 25 });
        settleOnSection('x', w.env);
        w.tick();
        expect(w.top()).toBe(80);
    });

    it('ne fait rien quand la section est déjà en place et s arrête après 10 images stables', () => {
        const w = fakeWindow({ top: 80 });
        settleOnSection('x', w.env);
        for (let i = 0; i < 30; i++) w.tick();
        expect(w.top()).toBe(80);
        expect(w.frames.length).toBe(0);
        expect(Object.keys(w.listeners)).toEqual([]);
    });

    it('s arrête sur un geste de l utilisateur', () => {
        const w = fakeWindow({ top: 25 });
        settleOnSection('x', w.env);
        w.listeners.wheel();
        expect(Object.keys(w.listeners)).toEqual([]);
    });

    it('s arrête si la section n existe pas, et au bout d une seconde', () => {
        const none = fakeWindow({ top: 25 });
        settleOnSection('absente', none.env);
        none.tick();
        expect(none.frames.length).toBe(0);

        const slow = fakeWindow({ top: 25 });
        slow.env.scrollBy = () => {};
        settleOnSection('x', slow.env);
        slow.tick(600);
        slow.tick(600);
        expect(slow.frames.length).toBe(0);
    });
});
