import { describe, expect, it } from 'vitest';
import {
    codeOrder,
    curvePoints,
    filterDepartments,
    fold,
    niceMax,
    sortDepartments,
} from '../../resources/js/lib/departmentSheet.js';

const rows = [
    { code: '23', name: 'Creuse', region: 'Nouvelle-Aquitaine', km_today: 10, closed_share: 0.9 },
    { code: '2A', name: 'Corse-du-Sud', region: 'Corse', km_today: null, closed_share: null },
    {
        code: '21',
        name: "Côte-d'Or",
        region: 'Bourgogne-Franche-Comté',
        km_today: 300,
        closed_share: 0.4,
    },
    { code: '14', name: 'Calvados', region: 'Normandie', km_today: 200, closed_share: 0.5 },
];

describe('fold', () => {
    it('retire accents, casse et ponctuation', () => {
        expect(fold("Côte-d'Or")).toBe('cote d or');
    });
});

describe('filterDepartments', () => {
    it('cherche par nom sans accent, par code et par région', () => {
        expect(filterDepartments(rows, "cote d'or").map((r) => r.code)).toEqual(['21']);
        expect(filterDepartments(rows, '23').map((r) => r.code)).toEqual(['23']);
        expect(filterDepartments(rows, 'normandie').map((r) => r.code)).toEqual(['14']);
        expect(filterDepartments(rows, '  ')).toHaveLength(4);
    });
});

describe('codeOrder', () => {
    it('place la Corse entre 19 et 21', () => {
        expect(codeOrder('2A')).toBeGreaterThan(codeOrder('19'));
        expect(codeOrder('2B')).toBeLessThan(codeOrder('21'));
    });
});

describe('sortDepartments', () => {
    it('trie par code dans l’ordre officiel', () => {
        expect(sortDepartments(rows, 'code', 'asc').map((r) => r.code)).toEqual([
            '14',
            '2A',
            '21',
            '23',
        ]);
    });
    it('trie les nombres et garde les valeurs absentes en dernier dans les deux sens', () => {
        expect(sortDepartments(rows, 'km_today', 'desc').map((r) => r.code)).toEqual([
            '21',
            '14',
            '23',
            '2A',
        ]);
        expect(sortDepartments(rows, 'km_today', 'asc').map((r) => r.code)).toEqual([
            '23',
            '14',
            '21',
            '2A',
        ]);
    });
    it('trie les noms sans tenir compte des accents', () => {
        expect(sortDepartments(rows, 'name', 'asc').map((r) => r.code)).toEqual([
            '14',
            '2A',
            '21',
            '23',
        ]);
    });
    it('ne modifie pas le tableau reçu', () => {
        const copy = [...rows];
        sortDepartments(rows, 'name', 'desc');
        expect(rows).toEqual(copy);
    });
});

describe('curvePoints', () => {
    it('place la première valeur à gauche et le maximum en haut', () => {
        expect(curvePoints([0, 50, 100], 100)).toBe('0,40 50,20 100,0');
    });
    it('borne les valeurs et gère le vide et la valeur unique', () => {
        expect(curvePoints([200], 100)).toBe('0,0');
        expect(curvePoints([], 100)).toBe('');
        expect(curvePoints([5, 5], 0)).toBe('0,0 100,0');
    });
});

describe('niceMax', () => {
    it('arrondit au 1, 2, 5 supérieur', () => {
        expect(niceMax(0)).toBe(1);
        expect(niceMax(87)).toBe(100);
        expect(niceMax(130)).toBe(200);
        expect(niceMax(430)).toBe(500);
        expect(niceMax(520)).toBe(1000);
    });
});
