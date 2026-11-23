// Page de résultats de la recherche (/search) : adresse partageable, onglets, détail des résultats (lib/searchPage.js)
// et accessibilité de base de la page rendue côté serveur (svelte/server).
import { render } from 'svelte/server';
import { describe, expect, it, vi } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import {
    resultDetail,
    resultHref,
    searchHref,
    searchParams,
    splitAround,
    typeTabs,
} from '$lib/searchPage.js';
import { searchPageHref } from '$lib/siteSearch.js';
import SearchIndex from '../../resources/js/Pages/Search/Index.svelte';

// Le gabarit (en-tête, pied de page) n'a rien à voir ici : la page est rendue sans lui.
vi.mock('$components/Layout.svelte', async () => ({
    default: (await import('./support/LayoutStub.svelte')).default,
}));

const t = (key, params = {}) =>
    Object.keys(params).length ? `${key}(${Object.values(params).join(',')})` : key;

describe('searchParams et searchHref', () => {
    it('retire les valeurs par défaut', () => {
        expect(searchParams({ q: ' brest ', type: '', state: '', dept: '', page: 1 })).toEqual({
            q: 'brest',
        });
        expect(searchHref({ q: 'st hilaire' })).toBe('/search?q=st+hilaire');
        expect(searchHref({})).toBe('/search');
    });
    it('état et département seulement pour les lignes et les gares', () => {
        expect(
            searchParams({ q: 'x', type: 'station', state: 'open', dept: '85', page: 2 }),
        ).toEqual({
            q: 'x',
            type: 'station',
            state: 'open',
            dept: '85',
            page: 2,
        });
        expect(searchParams({ q: 'x', type: 'accident', state: 'open', dept: '85' })).toEqual({
            q: 'x',
            type: 'accident',
        });
        expect(searchParams({ q: 'x', type: '', state: 'closed', dept: '85' })).toEqual({ q: 'x' });
    });
    it('la recherche de l en-tête mène à la même adresse', () => {
        expect(searchPageHref('Saint Lô')).toBe('/search?q=Saint%20L%C3%B4');
    });
});

describe('typeTabs', () => {
    const counts = { line: 2, station: 0, accident: 1, material: 0 };
    it('Tout, puis les types qui ont des résultats ou qui sont choisis', () => {
        const tabs = typeTabs({
            q: 'x',
            type: 'station',
            state: 'open',
            dept: '',
            counts,
            total: 3,
        });
        expect(tabs.map((tab) => [tab.type, tab.count, tab.current])).toEqual([
            ['', 3, false],
            ['line', 2, false],
            ['station', 0, true],
            ['accident', 1, false],
        ]);
        // L'état suit vers les lignes, pas vers les accidents ni « Tout ».
        expect(tabs[1].href).toBe('/search?q=x&type=line&state=open');
        expect(tabs[3].href).toBe('/search?q=x&type=accident');
        expect(tabs[0].href).toBe('/search?q=x');
    });
});

describe('splitAround', () => {
    it('coupe autour de l emplacement du lien', () => {
        expect(splitAround('Vouliez-vous dire \u0001 ?', '\u0001')).toEqual([
            'Vouliez-vous dire ',
            ' ?',
        ]);
        expect(splitAround('sans', '\u0001')).toEqual(['sans', '']);
    });
});

describe('resultDetail', () => {
    it('une partie par information connue', () => {
        expect(resultDetail('line', { state: 'closed' }, t)).toBe('lines.states.closed');
        expect(
            resultDetail(
                'station',
                {
                    name: 'Brétigny',
                    city: 'Brétigny-sur-Orge',
                    department_code: '91',
                    status: 'active',
                },
                t,
            ),
        ).toBe('Brétigny-sur-Orge (91) · stations.statuses.active');
        expect(resultDetail('material', { kind: 'autorail', builder: null }, t)).toBe(
            'materials.types.autorail',
        );
        expect(
            resultDetail(
                'accident',
                {
                    date: '2013-07-12',
                    date_precision: 'day',
                    commune: 'Brétigny-sur-Orge',
                    department_code: '91',
                    kind: 'derailment',
                },
                t,
            ),
        ).toBe('12 juillet 2013, Brétigny-sur-Orge (91) · accidents.kinds.derailment');
    });
});

describe('page de résultats rendue', () => {
    setupI18n({
        locale: 'fr',
        translations: {
            'search.page_title': 'Résultats pour « :query »',
            'search.field_label': 'Rechercher',
            'search.types_label': 'Type de résultat',
            'search.types.line': 'Lignes',
            'search.types.station': 'Gares',
        },
    });
    const line = {
        slug: 'rfn-570000',
        name: 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean',
        highlight: [{ text: 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean', mark: false }],
        code: '570000',
        code_match: true,
        state: 'open',
    };
    const station = {
        slug: 'bretigny',
        name: 'Brétigny',
        highlight: [
            { text: 'Bréti', mark: true },
            { text: 'gny', mark: false },
        ],
        city: 'Brétigny-sur-Orge',
        department_code: '91',
        status: 'active',
    };
    const group = (type, items) => ({
        type,
        total: items.length,
        approximate: false,
        current_page: 1,
        last_page: 1,
        per_page: 5,
        items,
    });
    const rendered = render(SearchIndex, {
        props: {
            query: 'breti',
            searched: true,
            filters: { type: '', state: '', dept: '' },
            groups: [group('line', [line]), group('station', [station])],
            counts: { line: 1, station: 1, accident: 0, material: 0 },
            total: 2,
        },
    });
    // Sans les marqueurs d'hydratation de Svelte.
    const head = rendered.head.replace(/<!--[^>]*-->/g, '');
    const body = rendered.body.replace(/<!--[^>]*-->/g, '');

    it('page non indexée, avec son titre', () => {
        expect(head).toContain('<meta name="robots" content="noindex"');
        expect(head).toContain('Résultats pour « breti » - VFF');
    });
    it('un seul h1, un h2 par groupe relié à sa section', () => {
        expect(body.match(/<h1[\s>]/g)).toHaveLength(1);
        expect(body).toContain('aria-labelledby="search-group-line"');
        expect(body).toMatch(/<h2 id="search-group-station"[^>]*>Gares<\/h2>/);
    });
    it('champ étiqueté, onglets nommés avec l onglet courant, zone d état', () => {
        expect(body).toMatch(/<label[^>]*for="search-page-q"/);
        expect(body).toContain('id="search-page-q"');
        expect(body).toContain('aria-label="Type de résultat"');
        expect(body).toMatch(/aria-current="page"/);
        expect(body).toContain('role="status"');
    });
    it('passage trouvé dans <mark>, liens vers les fiches, code de ligne lu', () => {
        expect(body).toMatch(/<mark[^>]*>Bréti<\/mark>gny/);
        expect(body).toContain('href="/stations/bretigny"');
        expect(body).toContain('href="/lines/rfn-570000"');
        expect(body).toContain('search.line_code');
    });
});

describe('communes', () => {
    it('onglet Communes après les autres types, détail = département', () => {
        const tabs = typeTabs({
            q: 'angou',
            type: '',
            state: '',
            dept: '',
            counts: { line: 1, station: 1, commune: 2 },
            total: 4,
        });
        expect(tabs.map((tab) => tab.type)).toEqual(['', 'line', 'station', 'commune']);
        expect(tabs.at(-1).href).toBe('/search?q=angou&type=commune');
        const item = { department_code: '16', department_name: 'Charente' };
        expect(resultDetail('commune', item, (k) => k)).toBe('Charente (16)');
    });
});

describe('glossaire', () => {
    it('onglet Glossaire avant les communes, détail = sigle seulement quand il diffère du nom', () => {
        const tabs = typeTabs({
            q: 'tvm',
            type: '',
            state: '',
            dept: '',
            counts: { line: 1, glossary: 3, commune: 2 },
            total: 6,
        });
        expect(tabs.map((tab) => tab.type)).toEqual(['', 'line', 'glossary', 'commune']);
        expect(tabs[2].href).toBe('/search?q=tvm&type=glossary');
        expect(resultDetail('glossary', { name: 'Caténaire', abbr: null }, (k) => k)).toBe('');
        expect(
            resultDetail('glossary', { name: 'Contrôle de vitesse', abbr: 'KVB' }, (k) => k),
        ).toBe('KVB');
    });
    it('un terme mène à son ancre dans /glossary, les autres types à leur fiche', () => {
        expect(resultHref('glossary', 'tvm-430')).toBe('/glossary#tvm-430');
        expect(resultHref('commune', '16015-angouleme')).toBe('/communes/16015-angouleme');
        expect(resultHref('line', 'rfn-570000')).toBe('/lines/rfn-570000');
    });
    it('la page rend le groupe avec le lien vers l ancre du terme', () => {
        const rendered = render(SearchIndex, {
            props: {
                query: 'kvb',
                searched: true,
                filters: { type: '', state: '', dept: '' },
                groups: [
                    {
                        type: 'glossary',
                        total: 1,
                        approximate: false,
                        current_page: 1,
                        last_page: 1,
                        per_page: 5,
                        items: [
                            {
                                slug: 'kvb',
                                name: 'Contrôle de vitesse par balises',
                                highlight: [
                                    { text: 'Contrôle de vitesse par balises', mark: false },
                                ],
                                abbr: 'KVB',
                            },
                        ],
                    },
                ],
                counts: { glossary: 1 },
                total: 1,
            },
        });
        const body = rendered.body.replace(/<!--[^>]*-->/g, '');
        expect(body).toContain('href="/glossary#kvb"');
        expect(body).toContain('aria-labelledby="search-group-glossary"');
    });
});
