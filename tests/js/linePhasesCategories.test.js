// Catégories de voie des phases d'une ligne : cohérence des listes (en service, disparue, ruban),
// des styles et des verbes de changement, pour toute paire (avant, après).
import { describe, expect, it } from 'vitest';
import {
    ACTIVE_CATEGORIES,
    CATEGORY_STYLES,
    GONE_CATEGORIES,
    PHASE_KIND_ICONS,
    STRIP_ORDER,
    categoryColor,
    changeVerbKey,
    isActiveCategory,
    phaseEraYear,
} from '$lib/linePhases.js';
import { TRAFFIC_COLOR } from '$lib/railStyle.js';

describe('catégories de voie', () => {
    it('en service et disparue ne se recoupent pas', () => {
        for (const cat of GONE_CATEGORIES) expect(ACTIVE_CATEGORIES).not.toContain(cat);
    });

    it('le ruban contient chaque catégorie dessinée, une seule fois', () => {
        expect(new Set(STRIP_ORDER).size).toBe(STRIP_ORDER.length);
        expect([...STRIP_ORDER].sort()).toEqual(Object.keys(CATEGORY_STYLES).sort());
    });

    it('le ruban range les catégories en service avant les voies disparues', () => {
        const last = Math.max(...ACTIVE_CATEGORIES.map((c) => STRIP_ORDER.indexOf(c)));
        const firstGone = Math.min(...GONE_CATEGORIES.map((c) => STRIP_ORDER.indexOf(c)));
        expect(last).toBeLessThan(firstGone);
    });

    it('isActiveCategory ne reconnaît que les catégories en service', () => {
        for (const cat of ACTIVE_CATEGORIES) expect(isActiveCategory(cat)).toBe(true);
        for (const cat of [
            ...GONE_CATEGORIES,
            'closed',
            'velorail',
            'construction',
            'none',
            null,
        ]) {
            expect(isActiveCategory(cat)).toBe(false);
        }
    });

    it('chaque style a une couleur hexadécimale, une couche et une clé de libellé', () => {
        for (const [key, style] of Object.entries(CATEGORY_STYLES)) {
            expect(style.color, key).toMatch(/^#[0-9a-f]{6}$/i);
            expect(['solid', 'velorail', 'construction', 'closed', 'gone']).toContain(style.layer);
            expect(style.labelKey).toBe(`lines.categories.${key}`);
        }
    });

    it('une catégorie inconnue prend la couleur du trafic inconnu', () => {
        expect(categoryColor('inconnue')).toBe(TRAFFIC_COLOR.unknown);
        expect(categoryColor(undefined)).toBe(TRAFFIC_COLOR.unknown);
    });
});

describe('verbe de changement', () => {
    const categories = [...STRIP_ORDER, 'none'];
    const known = new Set([
        'lines.changes.heritage',
        'lines.changes.opening',
        'lines.changes.reopening',
        'lines.changes.passenger_end',
        'lines.changes.passenger_start',
        'lines.changes.traffic',
        'lines.changes.closure_removal',
        'lines.changes.removal',
        'lines.changes.construction',
        'lines.changes.appears_closed',
        'lines.changes.closure',
    ]);

    it('rend une clé connue pour toutes les paires de catégories', () => {
        for (const from of categories) {
            for (const to of categories) {
                if (from === to) continue;
                expect(known.has(changeVerbKey(from, to)), `${from} -> ${to}`).toBe(true);
            }
        }
    });

    it('une voie qui n’existait pas puis circule est une ouverture', () => {
        expect(changeVerbKey('none', 'mixed')).toBe('lines.changes.opening');
    });

    it('une voie fermée qui recircule est une réouverture', () => {
        expect(changeVerbKey('closed', 'mixed')).toBe('lines.changes.reopening');
        expect(changeVerbKey('dismantled', 'freight')).toBe('lines.changes.reopening');
    });

    it('mixte vers fret seul est la fin des voyageurs, et l’inverse leur retour', () => {
        expect(changeVerbKey('mixed', 'freight')).toBe('lines.changes.passenger_end');
        expect(changeVerbKey('freight', 'mixed')).toBe('lines.changes.passenger_start');
    });

    it('une voie en service qui est déposée est une fermeture suivie de dépose', () => {
        expect(changeVerbKey('mixed', 'razed')).toBe('lines.changes.closure_removal');
        expect(changeVerbKey('closed', 'razed')).toBe('lines.changes.removal');
    });

    it('le patrimoine l’emporte sur toute autre lecture', () => {
        expect(changeVerbKey('closed', 'heritage')).toBe('lines.changes.heritage');
        expect(changeVerbKey('mixed', 'velorail')).toBe('lines.changes.heritage');
    });

    it('une voie en service qui ferme est une fermeture simple', () => {
        expect(changeVerbKey('mixed', 'closed')).toBe('lines.changes.closure');
    });
});

describe('phases : année et icônes', () => {
    it('l’année d’une phase est celle de sa fin, nulle si inconnue', () => {
        expect(phaseEraYear({ to_year: 1950 })).toBe(1950);
        expect(phaseEraYear({})).toBeNull();
        expect(phaseEraYear(null)).toBeNull();
    });

    it('chaque nature de phase a une icône bootstrap-icons', () => {
        expect(Object.keys(PHASE_KIND_ICONS).sort()).toEqual(
            ['decline', 'growth', 'heritage', 'none', 'removal', 'traffic'].sort(),
        );
        for (const icon of Object.values(PHASE_KIND_ICONS)) expect(icon).toMatch(/^bi-[a-z-]+$/);
    });
});
