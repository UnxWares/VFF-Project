import { describe, expect, it } from 'vitest';
import {
    SITE_PAGES,
    fold,
    isSearchShortcut,
    searchGroups,
    shortcutLabel,
    stationDetail,
    stepIndex,
} from '$lib/siteSearch.js';
import { isOutside } from '$lib/lightDismiss.js';

// Traduction factice : la clé, suivie des paramètres (assez pour vérifier ce qui est demandé).
const LABELS = {
    'nav.maps': 'Carte',
    'nav.lines': 'Lignes',
    'nav.stations': 'Gares',
    'nav.materials': 'Matériel roulant',
    'nav.accidents': 'Accidents',
    'nav.api': "Accès à l'API",
    'nav.contribute': 'Contribuer',
    'nav.whoarewe': 'Notre histoire',
    'nav.donate': 'Faites un don',
};
const t = (key, params = {}) => LABELS[key] ?? (params.query ? `${key}(${params.query})` : key);
const EMPTY = { lines: [], stations: [], materials: [] };

describe('fold', () => {
    it('ignore la casse et les accents', () => {
        expect(fold('Matériel Roulant')).toBe('materiel roulant');
        expect(fold('Saint-Étienne')).toBe('saint-etienne');
    });
});

describe('stationDetail', () => {
    it('ville et département, sans répéter le nom de la gare', () => {
        expect(stationDetail({ name: 'Vire', city: 'Vire Normandie', department_code: '14' })).toBe(
            'Vire Normandie (14)',
        );
        expect(stationDetail({ name: 'Brest', city: 'Brest', department_code: '29' })).toBe('(29)');
        expect(stationDetail({ name: 'Halte', city: null, department_code: null })).toBeNull();
    });
});

describe('searchGroups', () => {
    it('champ vide : toutes les rubriques en suggestion, rien sans suggestion', () => {
        const groups = searchGroups({
            query: '',
            suggestions: true,
            results: EMPTY,
            searched: false,
            t,
            idPrefix: 's',
        });
        expect(groups.map((g) => g.type)).toEqual(['page']);
        expect(groups[0].items).toHaveLength(SITE_PAGES.length);
        expect(groups[0].items[0]).toMatchObject({ href: '/maps', name: 'Carte' });
        expect(
            searchGroups({
                query: '',
                suggestions: false,
                results: EMPTY,
                searched: false,
                t,
                idPrefix: 's',
            }),
        ).toEqual([]);
    });

    it('les rubriques suivent le texte, sans accents', () => {
        const [pages] = searchGroups({
            query: 'materiel',
            suggestions: true,
            results: EMPTY,
            searched: false,
            t,
            idPrefix: 's',
        });
        expect(pages.items.map((i) => i.href)).toEqual(['/materials']);
    });

    it('la rubrique des accidents se trouve par son nom', () => {
        const [pages] = searchGroups({
            query: 'accident',
            suggestions: true,
            results: EMPTY,
            searched: false,
            t,
            idPrefix: 's',
        });
        expect(pages.items.map((i) => i.href)).toEqual(['/accidents']);
    });

    it('résultats dans l’ordre, indices continus et identifiants uniques', () => {
        const groups = searchGroups({
            query: 'vire',
            suggestions: true,
            results: {
                lines: [{ slug: 'rfn-440000', name: 'Ligne de Vire à Romagny', code: '440000' }],
                stations: [
                    { slug: 'vire', name: 'Vire', city: 'Vire Normandie', department_code: '14' },
                ],
                materials: [{ slug: 'x', name: 'X 4630', builder: 'ANF' }],
            },
            searched: true,
            t,
            idPrefix: 'p',
        });
        expect(groups.map((g) => g.type)).toEqual(['line', 'station', 'material', 'more']);
        const options = groups.flatMap((g) => g.items);
        expect(options.map((o) => o.index)).toEqual([0, 1, 2, 3, 4]);
        expect(new Set(options.map((o) => o.id)).size).toBe(options.length);
        expect(options[0]).toMatchObject({ href: '/lines/rfn-440000', plate: '440000' });
        expect(options[1]).toMatchObject({ href: '/stations/vire', detail: 'Vire Normandie (14)' });
        expect(options[2]).toMatchObject({ href: '/materials/x', detail: 'ANF' });
        expect(groups[0].id).toBe('p-group-line');
    });

    it('liens vers les annuaires filtrés seulement après la réponse, texte encodé', () => {
        const before = searchGroups({
            query: 'Saint Lô',
            suggestions: true,
            results: EMPTY,
            searched: false,
            t,
            idPrefix: 's',
        });
        expect(before).toEqual([]);
        const after = searchGroups({
            query: 'Saint Lô',
            suggestions: true,
            results: EMPTY,
            searched: true,
            t,
            idPrefix: 's',
        });
        expect(after[0].items.map((i) => i.href)).toEqual([
            '/lines?q=Saint%20L%C3%B4',
            '/stations?q=Saint%20L%C3%B4',
        ]);
        expect(after[0].items[0].name).toBe('nav.search.all_lines(Saint Lô)');
    });
});

describe('stepIndex', () => {
    it('parcourt les options en boucle', () => {
        expect(stepIndex(-1, 1, 3)).toBe(0);
        expect(stepIndex(-1, -1, 3)).toBe(2);
        expect(stepIndex(2, 1, 3)).toBe(0);
        expect(stepIndex(0, -1, 3)).toBe(2);
        expect(stepIndex(0, 1, 0)).toBe(-1);
    });
});

describe('raccourci', () => {
    const key = (k, mods = {}) => ({
        key: k,
        ctrlKey: false,
        metaKey: false,
        altKey: false,
        shiftKey: false,
        ...mods,
    });
    it('Ctrl K ou Cmd K, sans Alt ni Maj', () => {
        expect(isSearchShortcut(key('k', { ctrlKey: true }))).toBe(true);
        expect(isSearchShortcut(key('K', { metaKey: true }))).toBe(true);
        expect(isSearchShortcut(key('k'))).toBe(false);
        expect(isSearchShortcut(key('k', { ctrlKey: true, shiftKey: true }))).toBe(false);
        expect(isSearchShortcut(key('/'))).toBe(false);
    });
    it('libellé selon le système', () => {
        expect(shortcutLabel('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0)')).toBe('⌘ K');
        expect(shortcutLabel('Mozilla/5.0 (X11; Linux x86_64)')).toBe('Ctrl K');
    });
});

describe('isOutside (fermeture d’une fenêtre au clic extérieur)', () => {
    const rect = { left: 100, right: 500, top: 50, bottom: 450 };
    it('dedans, sur le bord, dehors', () => {
        expect(isOutside(rect, 300, 200)).toBe(false);
        expect(isOutside(rect, 100, 50)).toBe(false);
        expect(isOutside(rect, 99, 200)).toBe(true);
        expect(isOutside(rect, 300, 460)).toBe(true);
    });
});
