import { describe, expect, it } from 'vitest';
import {
    confidenceKey,
    fold,
    formatSourceDate,
    matchedDesignation,
    provenanceOf,
    servicePeriod,
    servicePeriodText,
    serviceState,
    splitArrows,
} from '../../resources/js/lib/materialFacts.js';

// Traduction factice : rend la clé et ses paramètres, pour vérifier quelle phrase est choisie.
const t = (key, opts = {}) => `${key}(${Object.values(opts).join(',')})`;

describe('servicePeriod', () => {
    it('donne la période quand les deux années sont connues', () => {
        expect(servicePeriod(1950, 1988)).toEqual({ kind: 'range', from: 1950, to: 1988 });
    });

    it("ne conclut jamais qu'une série sans retrait est encore en service", () => {
        expect(servicePeriod(1950, null)).toEqual({ kind: 'from', from: 1950, to: null });
    });

    it('garde le retrait seul quand la mise en service manque', () => {
        expect(servicePeriod(null, 1990)).toEqual({ kind: 'until', from: null, to: 1990 });
    });

    it("ne renvoie rien sans aucune année (pas de « null-aujourd'hui »)", () => {
        expect(servicePeriod(null, null)).toBeNull();
        expect(servicePeriod(undefined, undefined)).toBeNull();
    });
});

describe('servicePeriodText', () => {
    it('écrit la période complète en chiffres', () => {
        expect(servicePeriodText(1950, 1988, t)).toBe('1950-1988');
    });

    it("écrit une seule fois l'année quand début et fin se confondent", () => {
        expect(servicePeriodText(1962, 1962, t)).toBe('1962');
    });

    it('dit « mise en service » et non « depuis » sans année de retrait', () => {
        expect(servicePeriodText(1950, null, t)).toBe('materials.service.from(1950)');
    });

    it('dit « retrait » quand seule la fin est connue', () => {
        expect(servicePeriodText(null, 1990, t)).toBe('materials.service.until(1990)');
    });

    it("n'affiche rien quand rien n'est connu", () => {
        expect(servicePeriodText(null, null, t)).toBeNull();
    });
});

describe('confidenceKey', () => {
    it('distingue Wikidata des sections « matériel » des articles', () => {
        expect(confidenceKey('high', 'wikidata')).toBe('materials.confidence.high_wikidata');
        expect(confidenceKey('high', 'wikipedia_line_article')).toBe('materials.confidence.high');
    });

    it('traduit les autres niveaux, valeur inconnue comprise', () => {
        expect(confidenceKey('medium', 'wikipedia_line_article')).toBe(
            'materials.confidence.medium',
        );
        expect(confidenceKey('low', null)).toBe('materials.confidence.low');
        expect(confidenceKey(null, null)).toBe('materials.confidence.medium');
    });
});

describe('matchedDesignation', () => {
    const x3800 = { name: 'X 3800', designations: ['SNCF X 3800', 'Autorail Picasso', 'Picasso'] };

    it("montre l'autre nom qui a fait trouver la série", () => {
        expect(matchedDesignation(x3800, 'picasso')).toBe('Autorail Picasso');
    });

    it('ignore accents et capitales', () => {
        expect(fold('Électrique')).toBe('electrique');
        expect(matchedDesignation({ name: 'Z 2', designations: ['Élise'] }, 'elise')).toBe('Élise');
    });

    it('ne montre rien quand le nom suffit ou sans recherche', () => {
        expect(matchedDesignation(x3800, '3800')).toBeNull();
        expect(matchedDesignation(x3800, '  ')).toBeNull();
        expect(matchedDesignation({ name: 'BB 1' }, 'zzz')).toBeNull();
    });
});

describe('provenanceOf', () => {
    const wd = (field) => ({
        field,
        source: 'wikidata',
        url: 'https://www.wikidata.org/wiki/Q1',
        name: 'Wikidata',
        license: 'CC0 1.0',
    });

    it('cite une fois la source commune et à part les champs venus d’ailleurs', () => {
        const p = provenanceOf([
            wd('name'),
            wd('builder'),
            {
                field: 'image',
                source: 'commons',
                url: 'https://commons.wikimedia.org/wiki/File:X.jpg',
                name: 'Wikimedia Commons',
                license: null,
            },
        ]);
        expect(p.main).toEqual({
            source: 'wikidata',
            url: 'https://www.wikidata.org/wiki/Q1',
            name: 'Wikidata',
            license: 'CC0 1.0',
        });
        expect(Object.keys(p.byField)).toEqual(['image']);
        expect(p.byField.image.source).toBe('commons');
    });

    it('reste vide pour une fiche sans provenance', () => {
        expect(provenanceOf([])).toEqual({ main: null, byField: {} });
        expect(provenanceOf(undefined)).toEqual({ main: null, byField: {} });
    });
});

describe('splitArrows', () => {
    it('découpe une renumérotation autour de la flèche', () => {
        expect(splitArrows('PLM 1-30 → Est 601-612')).toEqual(['PLM 1-30', 'Est 601-612']);
        expect(splitArrows('A → B → C')).toEqual(['A', 'B', 'C']);
    });

    it('laisse un nom sans flèche intact', () => {
        expect(splitArrows('Picasso')).toEqual(['Picasso']);
        expect(splitArrows(null)).toEqual(['']);
    });
});

describe('formatSourceDate', () => {
    it('écrit la date selon la précision de la source', () => {
        expect(formatSourceDate('2026-07-13', 'fr')).toBe('13 juillet 2026');
        expect(formatSourceDate('2026-07', 'fr')).toBe('juillet 2026');
        expect(formatSourceDate('2026', 'fr')).toBe('2026');
        expect(formatSourceDate('2026-07-13', 'en')).toBe('13 July 2026');
        expect(formatSourceDate('2026-07-13', 'de')).toBe('13. Juli 2026');
    });

    it('ne bascule pas de jour selon le fuseau', () => {
        expect(formatSourceDate('2026-01-01', 'fr')).toBe('1 janvier 2026');
        expect(formatSourceDate('2026-12-31', 'fr')).toBe('31 décembre 2026');
    });

    it('rend null pour une valeur absente ou illisible', () => {
        expect(formatSourceDate(null, 'fr')).toBeNull();
        expect(formatSourceDate('hier', 'fr')).toBeNull();
        expect(formatSourceDate('2026-13-45', 'fr')).toBeNull();
    });
});

describe('serviceState', () => {
    it("n'affiche rien quand aucune source ne dit l'état", () => {
        expect(serviceState(null, t, 'fr')).toBeNull();
        expect(serviceState({ status: null, checked_at: '2026-11-19' }, t, 'fr')).toBeNull();
        expect(serviceState({ status: 'inconnu' }, t, 'fr')).toBeNull();
    });

    it("dit l'état avec l'effectif daté et la date de lecture de la source", () => {
        const state = serviceState(
            {
                status: 'in_service',
                units_in_service: 12,
                as_of: '2026-07-13',
                checked_at: '2026-11-19',
            },
            t,
            'fr',
        );
        expect(state.status).toBe('in_service');
        expect(state.label).toBe('materials.service_state.in_service()');
        expect(state.detail).toBe(
            'materials.service_state.fleet(12,13 juillet 2026), materials.service_state.checked(19 novembre 2026)',
        );
    });

    it("garde la date sans effectif, et un état retiré s'écrit comme tel", () => {
        const state = serviceState(
            { status: 'withdrawn', as_of: '2024', checked_at: null },
            t,
            'fr',
        );
        expect(state.label).toBe('materials.service_state.withdrawn()');
        expect(state.detail).toBe('materials.service_state.as_of(2024)');
    });

    it('rend un détail nul quand la source ne donne aucune date', () => {
        expect(serviceState({ status: 'in_service' }, t, 'fr').detail).toBeNull();
    });
});
