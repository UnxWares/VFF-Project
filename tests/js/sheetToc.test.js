import { describe, expect, it } from 'vitest';
import { currentSection, hiddenEdges, revealScrollLeft } from '$lib/sheetToc.js';

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
