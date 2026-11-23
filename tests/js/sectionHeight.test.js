import { describe, expect, it } from 'vitest';
import {
    coefficients,
    estimateHeight,
    sectionFeatures,
    SECTION_MODEL,
    WIDTH_NODES,
} from '../../resources/js/lib/sectionHeight.js';

describe('estimateHeight', () => {
    it('reproduit les coefficients aux largeurs ajustées', () => {
        WIDTH_NODES.forEach((w, i) => {
            expect(coefficients('materials', w)).toEqual(SECTION_MODEL.materials[i]);
        });
    });

    it('interpole entre deux largeurs', () => {
        const [a] = SECTION_MODEL.stations[0];
        const [b] = SECTION_MODEL.stations[1];
        const mid = (WIDTH_NODES[0] + WIDTH_NODES[1]) / 2;
        expect(coefficients('stations', mid)[0]).toBeCloseTo((a + b) / 2, 5);
    });

    it('garde les valeurs des extrémités au-delà et grossit le texte sous 390 px', () => {
        expect(coefficients('gallery', 2560)).toEqual(SECTION_MODEL.gallery[2]);
        const narrow = coefficients('gallery', 195);
        expect(narrow[1]).toBeCloseTo(SECTION_MODEL.gallery[0][1] * 2, 5);
        expect(narrow[0]).toBe(SECTION_MODEL.gallery[0][0]);
    });

    it('utilise le jeu générique pour une section inconnue ou sans identifiant', () => {
        expect(coefficients('inconnue', 390)).toEqual(SECTION_MODEL['*'][0]);
        expect(coefficients('*', 1280)).toEqual(SECTION_MODEL['*'][2]);
    });

    it('retombe sur la plus petite largeur si la largeur est invalide', () => {
        expect(coefficients('stations', Number.NaN)).toEqual(SECTION_MODEL.stations[0]);
        expect(coefficients('stations', 0)).toEqual(SECTION_MODEL.stations[0]);
    });

    it('estime le matériel de 830000 à 390 px près de la mesure (27 174 px)', () => {
        const h = estimateHeight('materials', { chars: 16430, items: 50 }, 390);
        expect(h / 27174).toBeGreaterThan(0.9);
        expect(h / 27174).toBeLessThan(1.1);
    });

    it('croît avec le contenu et reste borné', () => {
        const small = estimateHeight('stations', { chars: 500, items: 5 }, 390);
        const large = estimateHeight('stations', { chars: 5000, items: 50 }, 390);
        expect(large).toBeGreaterThan(small);
        expect(estimateHeight('stations', { chars: 1e9, items: 1e9 }, 390)).toBe(40000);
        expect(estimateHeight('accidents', { chars: 0, items: 0 }, 1280)).toBeGreaterThanOrEqual(
            120,
        );
    });

    it('est plus basse sur ordinateur que sur téléphone pour le même contenu', () => {
        const f = { chars: 4636, items: 33 };
        expect(estimateHeight('stations', f, 1280)).toBeLessThan(
            estimateHeight('stations', f, 390),
        );
    });
});

describe('sectionFeatures', () => {
    it('compte les caractères (espaces réduits) et les éléments li et tr', () => {
        const el = {
            textContent: '  Titre\n  a  b\n x   y ',
            querySelectorAll: (sel) => {
                expect(sel).toBe('li, tr');
                return [1, 2, 3];
            },
        };
        expect(sectionFeatures(el)).toEqual({ chars: 'Titre a b x y'.length, items: 3 });
    });
});
