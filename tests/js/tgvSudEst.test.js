// Profil à l'échelle de la rame TGV Sud-Est (clin d'œil « patrick » et fiche) : cotes, composition, poids.
import { describe, expect, it } from 'vitest';
import { CAR_LENGTHS, LIVERY, PROFILE_TOP, TRAIN_LENGTH, tgvSudEstSvg } from '$lib/tgvSudEst.js';
import { patrickSvg } from '$lib/patrickTrain.js';
import { notableProfile } from '$lib/notableUnits.js';

const count = (text, needle) => text.split(needle).length - 1;
const viewBox = (svg) =>
    svg
        .match(/viewBox="([^"]+)"/)[1]
        .split(' ')
        .map(Number);

describe('cotes de la rame', () => {
    it('2 motrices, R1 et R8, six intermédiaires : 200,19 m', () => {
        expect(CAR_LENGTHS).toEqual({ power: 221.5, end: 218.45, middle: 187 });
        expect(TRAIN_LENGTH).toBeCloseTo(2001.9, 6);
    });

    it('le dessin entier couvre toute la longueur, pantographe levé compris', () => {
        const [x, y, w, h] = viewBox(tgvSudEstSvg({ raised: true }));
        expect([x, y, w, h]).toEqual([0, 0, 2001.9, PROFILE_TOP.raised]);
        expect(viewBox(tgvSudEstSvg({ rail: true }))[3]).toBe(PROFILE_TOP.folded + 1);
    });
});

describe('composition dessinée', () => {
    const svg = tgvSudEstSvg({ raised: true });

    it('13 bogies, soit 26 essieux : 4 sous les motrices, 2 moteurs sous R1 et R8, 7 Jacobs', () => {
        expect(count(svg, `r="4.4" fill="${LIVERY.wheel}"`)).toBe(26);
    });

    it('81 baies de voyageurs et une porte par remorque', () => {
        expect(count(svg, 'rx="1.4" stroke')).toBe(81);
        expect(count(svg, 'fill="none" rx="1" stroke')).toBe(8 + 2);
    });

    it('numéro 01 sur les deux motrices, la seconde en miroir', () => {
        expect(count(svg, '>01</text>')).toBe(2);
        expect(svg).toContain('scale(-1 1)');
        expect(count(tgvSudEstSvg({ number: '16' }), '>16</text>')).toBe(2);
    });

    it('livrée orange VFF, décoratif, sans image ni lien externe, léger', () => {
        expect(svg).toContain(LIVERY.orange);
        expect(LIVERY.orange).toBe('#ff6500');
        expect(svg).toContain('aria-hidden="true"');
        expect(svg).not.toMatch(/<image|href=|<script/);
        expect(svg.length).toBeLessThan(30000);
    });
});

describe('découpe et identifiants', () => {
    it('une partie de la rame ne dessine que les caisses visibles', () => {
        const head = tgvSudEstSvg({ to: 470, rail: true });
        expect(viewBox(head)).toEqual([0, 0, 470, PROFILE_TOP.folded + 1]);
        expect(count(head, '>01</text>')).toBe(1);
        expect(head).not.toContain('scale(-1 1)');
        expect(head.length).toBeLessThan(10000);
    });

    it('identifiants de découpe uniques d’un appel à l’autre, ou fixés par `id`', () => {
        const ids = (s) => [...s.matchAll(/clipPath id="([^"]+)"/g)].map((m) => m[1]);
        const a = ids(tgvSudEstSvg());
        const b = ids(tgvSudEstSvg());
        expect(a).toHaveLength(2);
        expect(a.some((x) => b.includes(x))).toBe(false);
        expect(ids(tgvSudEstSvg({ id: 'fixe', to: 300 }))).toEqual(['fixe-a']);
    });

    it('le passage « patrick » montre la rame entière, pantographe de queue levé', () => {
        const passage = patrickSvg();
        expect(passage).toContain('class="vff-patrick-train"');
        expect(viewBox(passage)[2]).toBe(2001.9);
        expect(viewBox(passage)[3]).toBe(PROFILE_TOP.raised);
    });
});

describe('profil des rames remarquables', () => {
    it('la rame 01 du TGV Sud-Est a son profil, les autres non', () => {
        const svg = notableProfile('tgv-sud-est', '01');
        expect(svg).toContain('class="notable-profile-svg"');
        expect(svg).toContain('preserveAspectRatio="xMinYMax slice"');
        expect(svg).toContain('clipPath id="vff-notable-tgv-sud-est-01-a"');
        expect(notableProfile('tgv-sud-est', '02')).toBeNull();
        expect(notableProfile('tgv-atlantique', '01')).toBeNull();
        expect(notableProfile(null, '01')).toBeNull();
        expect(notableProfile('constructor', 'name')).toBeNull();
    });
});
