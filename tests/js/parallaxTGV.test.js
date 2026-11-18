// Écartement des TGV du grand visuel de l'accueil, avec window, matchMedia, CSS et
// requestAnimationFrame simulés (environnement Node, sans DOM).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { parallaxTGV, tgvProgress, TGV_TIMELINE } from '$lib/actions/parallaxTGV.js';

/** Élément simulé : seules les propriétés personnalisées de style servent. */
function fakeNode() {
    const props = new Map();
    return {
        props,
        style: {
            setProperty: vi.fn((name, value) => props.set(name, value)),
            removeProperty: vi.fn((name) => props.delete(name)),
        },
    };
}

/** Fenêtre simulée : écouteurs par type, images en attente lancées par `flush()`. */
function fakeWindow({ scrollY = 0, innerHeight = 900, reduce = false } = {}) {
    const listeners = new Map();
    const mediaListeners = new Set();
    let frames = [];
    let nextId = 1;
    const media = {
        matches: reduce,
        addEventListener: vi.fn((_type, fn) => mediaListeners.add(fn)),
        removeEventListener: vi.fn((_type, fn) => mediaListeners.delete(fn)),
    };
    const win = {
        scrollY,
        innerHeight,
        listeners,
        media,
        addEventListener: vi.fn((type, fn, options) => listeners.set(type, { fn, options })),
        removeEventListener: vi.fn((type) => listeners.delete(type)),
        matchMedia: vi.fn(() => media),
        requestAnimationFrame: vi.fn((fn) => {
            frames.push(fn);
            return nextId++;
        }),
        cancelAnimationFrame: vi.fn(),
        /** Lance les images demandées depuis le dernier appel. */
        flush() {
            const pending = frames;
            frames = [];
            pending.forEach((fn) => fn());
        },
        pendingFrames: () => frames.length,
        /** Change la préférence de mouvement et prévient les écouteurs. */
        setReduce(value) {
            media.matches = value;
            mediaListeners.forEach((fn) => fn());
        },
        scrollTo(y) {
            win.scrollY = y;
            listeners.get('scroll')?.fn();
        },
    };
    return win;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('tgvProgress', () => {
    it('va de 0 à 1 sur une hauteur d’écran, puis reste à 1', () => {
        expect(tgvProgress(0, 900)).toBe(0);
        expect(tgvProgress(450, 900)).toBe(0.5);
        expect(tgvProgress(900, 900)).toBe(1);
        expect(tgvProgress(5000, 900)).toBe(1);
    });

    it('borne les valeurs hors limites et arrondit au millième', () => {
        expect(tgvProgress(-40, 900)).toBe(0); // rebond élastique en haut de page (iOS)
        expect(tgvProgress(1, 3)).toBe(0.333);
    });

    it('sans hauteur d’écran utilisable : rames écartées', () => {
        expect(tgvProgress(100, 0)).toBe(1);
        expect(tgvProgress(100, Number.NaN)).toBe(1);
    });
});

describe('parallaxTGV', () => {
    it('écrit --tgv-progress une fois par image, avec un écouteur passif', () => {
        const win = fakeWindow({ innerHeight: 1000 });
        vi.stubGlobal('window', win);
        const node = fakeNode();

        const cleanup = parallaxTGV(node);
        expect(win.listeners.get('scroll').options).toEqual({ passive: true });
        win.flush();
        expect(node.props.get('--tgv-progress')).toBe('0');

        // Plusieurs événements dans la même image : une seule image demandée.
        win.scrollTo(100);
        win.scrollTo(200);
        win.scrollTo(250);
        expect(win.pendingFrames()).toBe(1);
        win.flush();
        expect(node.props.get('--tgv-progress')).toBe('0.25');

        cleanup();
        expect(win.listeners.has('scroll')).toBe(false);
        expect(win.listeners.has('resize')).toBe(false);
        expect(node.props.has('--tgv-progress')).toBe(false);
    });

    it('n’écrit rien quand la valeur ne change pas (rames déjà écartées)', () => {
        const win = fakeWindow({ scrollY: 3000 });
        vi.stubGlobal('window', win);
        const node = fakeNode();

        parallaxTGV(node);
        win.flush();
        expect(node.style.setProperty).toHaveBeenCalledTimes(1);
        win.scrollTo(3500);
        win.flush();
        expect(node.style.setProperty).toHaveBeenCalledTimes(1);
        expect(node.props.get('--tgv-progress')).toBe('1');
    });

    it('mouvement réduit : rien d’écrit ni d’écouté, puis suit un changement de préférence', () => {
        const win = fakeWindow({ reduce: true, scrollY: 450 });
        vi.stubGlobal('window', win);
        const node = fakeNode();

        const cleanup = parallaxTGV(node);
        expect(win.listeners.has('scroll')).toBe(false);
        expect(win.pendingFrames()).toBe(0);
        expect(node.style.setProperty).not.toHaveBeenCalled();

        win.setReduce(false);
        win.flush();
        expect(node.props.get('--tgv-progress')).toBe('0.5');

        // Retour au mouvement réduit : la variable écrite disparaît (header.css écarte les rames).
        win.setReduce(true);
        expect(win.listeners.has('scroll')).toBe(false);
        expect(node.props.has('--tgv-progress')).toBe(false);

        cleanup();
        expect(win.media.removeEventListener).toHaveBeenCalled();
    });

    it('animation liée au défilement prise en charge par le navigateur : ne fait rien', () => {
        const win = fakeWindow();
        vi.stubGlobal('window', win);
        const supports = vi.fn(() => true);
        vi.stubGlobal('CSS', { supports });

        expect(parallaxTGV(fakeNode())).toBeUndefined();
        expect(supports).toHaveBeenCalledWith(TGV_TIMELINE);
        expect(win.addEventListener).not.toHaveBeenCalled();
        expect(win.matchMedia).not.toHaveBeenCalled();
    });

    it('nettoyage avant la première image : l’image en attente est annulée', () => {
        const win = fakeWindow();
        vi.stubGlobal('window', win);

        const cleanup = parallaxTGV(fakeNode());
        cleanup();
        expect(win.cancelAnimationFrame).toHaveBeenCalledWith(1);
    });
});
