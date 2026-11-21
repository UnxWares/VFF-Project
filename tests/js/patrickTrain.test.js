// Passage de « Patrick » (resources/js/lib/patrickTrain.js) : région annoncée, bulle et bouton, mouvement réduit,
// fermeture par Échap, par le bouton ou d'elle-même, et un seul passage à la fois. Document factice : juste ce
// que le module appelle (environnement Node, sans DOM).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BUBBLE_EXTRA_MS, CROSSING_MS, PATRICK_HREF, showPatrick } from '$lib/patrickTrain.js';

class FakeElement {
    constructor(doc, tag) {
        this.doc = doc;
        this.tagName = tag.toUpperCase();
        this.attributes = {};
        this.children = [];
        this.listeners = {};
        this.parent = null;
        this.textContent = '';
        this.innerHTML = '';
        this.id = '';
    }
    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }
    getAttribute(name) {
        return this.attributes[name] ?? null;
    }
    append(...nodes) {
        for (const node of nodes) {
            node.parent = this;
            this.children.push(node);
        }
    }
    remove() {
        if (this.parent) {
            this.parent.children = this.parent.children.filter((c) => c !== this);
            this.parent = null;
        }
    }
    addEventListener(type, fn) {
        (this.listeners[type] ??= []).push(fn);
    }
    click() {
        for (const fn of this.listeners.click ?? []) fn({ type: 'click' });
    }
    find(predicate) {
        for (const child of this.children) {
            if (predicate(child)) return child;
            const found = child.find(predicate);
            if (found) return found;
        }
        return null;
    }
}

function fakeDocument() {
    const doc = {
        listeners: {},
        createElement: (tag) => new FakeElement(doc, tag),
        getElementById: (id) => doc.body.find((el) => el.id === id),
        addEventListener(type, fn) {
            (doc.listeners[type] ??= []).push(fn);
        },
        removeEventListener(type, fn) {
            doc.listeners[type] = (doc.listeners[type] ?? []).filter((f) => f !== fn);
        },
        key(key) {
            for (const fn of [...(doc.listeners.keydown ?? [])]) fn({ key });
        },
    };
    doc.head = new FakeElement(doc, 'head');
    doc.body = new FakeElement(doc, 'body');
    return doc;
}

const fakeWindow = (reduced) => ({
    matchMedia: (query) => ({ matches: query === '(prefers-reduced-motion: reduce)' && reduced }),
    setTimeout: (fn, ms) => setTimeout(fn, ms),
    clearTimeout: (id) => clearTimeout(id),
});

const t = (key) => `«${key}»`;
const root = (doc) => doc.getElementById('vff-patrick');

describe('passage de Patrick', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('pose une région nommée, la rame, la bulle avec son lien et son bouton', () => {
        const doc = fakeDocument();
        const dismiss = showPatrick({ t, doc, win: fakeWindow(false) });
        expect(typeof dismiss).toBe('function');
        const region = root(doc);
        expect(region.getAttribute('role')).toBe('region');
        expect(region.getAttribute('aria-label')).toBe('«common.patrick.label»');
        expect(region.find((el) => el.className === 'vff-patrick-track').innerHTML).toContain(
            '<svg',
        );
        const link = region.find((el) => el.tagName === 'A');
        expect(link.href).toBe(PATRICK_HREF);
        expect(link.textContent).toBe('«common.patrick.bubble»');
        const close = region.find((el) => el.tagName === 'BUTTON');
        expect(close.type).toBe('button');
        expect(close.getAttribute('aria-label')).toBe('«common.patrick.close»');
        expect(doc.head.children).toHaveLength(1);
    });

    it('annonce le passage après l’insertion de la région, poliment', () => {
        const doc = fakeDocument();
        showPatrick({ t, doc, win: fakeWindow(false) });
        const live = root(doc).find((el) => el.getAttribute('aria-live') === 'polite');
        expect(live.textContent).toBe('');
        vi.advanceTimersByTime(100);
        expect(live.textContent).toBe('«common.patrick.announce»');
    });

    it('un second déclenchement pendant le passage ne fait rien', () => {
        const doc = fakeDocument();
        showPatrick({ t, doc, win: fakeWindow(false) });
        expect(showPatrick({ t, doc, win: fakeWindow(false) })).toBeNull();
        expect(doc.body.children).toHaveLength(1);
    });

    it('se retire de lui-même après la traversée et la bulle', () => {
        const doc = fakeDocument();
        showPatrick({ t, doc, win: fakeWindow(false) });
        vi.advanceTimersByTime(CROSSING_MS + BUBBLE_EXTRA_MS - 1);
        expect(root(doc)).not.toBeNull();
        vi.advanceTimersByTime(1);
        expect(root(doc)).toBeNull();
        expect(doc.head.children).toHaveLength(0);
        expect(doc.listeners.keydown).toEqual([]);
    });

    it('mouvement réduit : la bulle seule, gardée deux fois le temps de la bulle', () => {
        const doc = fakeDocument();
        showPatrick({ t, doc, win: fakeWindow(true) });
        expect(root(doc).find((el) => el.className === 'vff-patrick-track')).toBeNull();
        expect(root(doc).find((el) => el.tagName === 'A')).not.toBeNull();
        vi.advanceTimersByTime(BUBBLE_EXTRA_MS * 2 - 1);
        expect(root(doc)).not.toBeNull();
        vi.advanceTimersByTime(1);
        expect(root(doc)).toBeNull();
    });

    it('sans matchMedia : la rame défile', () => {
        const doc = fakeDocument();
        const win = { ...fakeWindow(false), matchMedia: undefined };
        showPatrick({ t, doc, win });
        expect(root(doc).find((el) => el.className === 'vff-patrick-track')).not.toBeNull();
    });

    it('Échap ferme, une autre touche non', () => {
        const doc = fakeDocument();
        showPatrick({ t, doc, win: fakeWindow(false) });
        doc.key('Enter');
        expect(root(doc)).not.toBeNull();
        doc.key('Escape');
        expect(root(doc)).toBeNull();
        expect(doc.listeners.keydown).toEqual([]);
        // Le minuteur de fermeture est annulé : rien ne se passe ensuite, et un nouveau passage est possible.
        vi.advanceTimersByTime(CROSSING_MS + BUBBLE_EXTRA_MS);
        expect(showPatrick({ t, doc, win: fakeWindow(false) })).not.toBeNull();
    });

    it('le bouton ferme', () => {
        const doc = fakeDocument();
        showPatrick({ t, doc, win: fakeWindow(false) });
        root(doc)
            .find((el) => el.tagName === 'BUTTON')
            .click();
        expect(root(doc)).toBeNull();
        expect(doc.head.children).toHaveLength(0);
    });
});
