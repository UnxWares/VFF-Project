import { describe, expect, it } from 'vitest';
import {
    COMPARE_CHANGES,
    STACK_CLASSES,
    VIEWS,
    compareColorExpr,
    compareOpacityExpr,
    compareSortExpr,
    compareSummary,
    compareWidthExpr,
    defaultPair,
    orderPair,
    readViewParams,
    stackBounds,
    stackColorExpr,
    stackLabels,
    writeViewParams,
} from '$lib/epochViews.js';
import { colorOf, compile, evaluate, spec } from './support/maplibre.js';

const ERAS = [
    1830, 1840, 1850, 1860, 1870, 1880, 1890, 1900, 1910, 1920, 1930, 1940, 1950, 1960, 1970, 1980,
    1990, 2000, 2010, 2020, 2030,
];
const BASE = 'https://vff.test/maps';

it('propose trois vues, dans l’ordre du sélecteur', () => {
    expect(VIEWS).toEqual(['era', 'compare', 'stack']);
});

describe('orderPair', () => {
    it('remet les décennies dans l’ordre chronologique', () => {
        expect(orderPair(1980, 1930, ERAS)).toEqual([1930, 1980]);
        expect(orderPair(1930, 1980, ERAS)).toEqual([1930, 1980]);
    });

    it('choisit une autre décennie quand les deux sont égales', () => {
        expect(orderPair(1930, 1930, ERAS)).toEqual([1930, 1940]);
        expect(orderPair(2030, 2030, ERAS)).toEqual([2020, 2030]);
    });

    it('remplace une décennie inconnue par une borne de la frise', () => {
        expect(orderPair(1935, 1980, ERAS)).toEqual([1830, 1980]);
        expect(orderPair(1930, 2050, ERAS)).toEqual([1930, 2030]);
    });

    it('renvoie null sans deux décennies à comparer', () => {
        expect(orderPair(2030, 2030, [2030])).toBeNull();
    });
});

describe('defaultPair', () => {
    it('compare la décennie affichée au réseau actuel', () => {
        expect(defaultPair(1950, ERAS, 2030)).toEqual([1950, 2030]);
    });

    it('part de 1930 quand la carte montre déjà le réseau actuel', () => {
        expect(defaultPair(2030, ERAS, 2030)).toEqual([1930, 2030]);
        expect(defaultPair(2030, [1990, 2000, 2030], 2030)).toEqual([1990, 2030]);
    });
});

describe('readViewParams', () => {
    it('lit une comparaison partagée (?era=1930&compare=1980)', () => {
        expect(readViewParams('?era=1930&compare=1980', ERAS)).toEqual({
            view: 'compare',
            era: 1930,
            compare: 1980,
        });
    });

    it('remet une comparaison inversée dans l’ordre', () => {
        expect(readViewParams('?era=1980&compare=1930', ERAS)).toEqual({
            view: 'compare',
            era: 1930,
            compare: 1980,
        });
    });

    it('compare au réseau actuel sans ?era', () => {
        expect(readViewParams('?compare=1930', ERAS)).toEqual({
            view: 'compare',
            era: 1930,
            compare: 2030,
        });
    });

    it('lit la vue empilée et la décennie seule', () => {
        expect(readViewParams('?view=stack', ERAS)).toEqual({
            view: 'stack',
            era: null,
            compare: null,
        });
        expect(readViewParams('?era=1950', ERAS)).toEqual({
            view: 'era',
            era: 1950,
            compare: null,
        });
        expect(readViewParams('', ERAS)).toEqual({ view: 'era', era: null, compare: null });
    });

    it('ignore les valeurs qui ne sont pas des décennies de la carte', () => {
        expect(readViewParams('?era=1935&compare=abc', ERAS)).toEqual({
            view: 'era',
            era: null,
            compare: null,
        });
        expect(readViewParams('?compare=2050', ERAS).view).toBe('era');
        expect(readViewParams('?view=autre', ERAS).view).toBe('era');
    });
});

describe('writeViewParams', () => {
    it('écrit la comparaison et garde les autres paramètres', () => {
        const url = writeViewParams(
            `${BASE}?lang=fr#carte`,
            { view: 'compare', era: 1930, compare: 1980 },
            2030,
        );
        expect(url).toBe(`${BASE}?lang=fr&era=1930&compare=1980#carte`);
    });

    it('n’écrit pas la décennie actuelle en vue « une époque »', () => {
        expect(
            writeViewParams(`${BASE}?era=1930&compare=1980`, { view: 'era', era: 2030 }, 2030),
        ).toBe(BASE);
        expect(writeViewParams(BASE, { view: 'era', era: 1950 }, 2030)).toBe(`${BASE}?era=1950`);
    });

    it('écrit la vue empilée sans décennie', () => {
        expect(writeViewParams(`${BASE}?era=1950`, { view: 'stack', era: 1950 }, 2030)).toBe(
            `${BASE}?view=stack`,
        );
    });

    it('relit ce qu’elle écrit', () => {
        const state = { view: 'compare', era: 1880, compare: 2030 };
        const url = new URL(writeViewParams(BASE, state, 2030));
        expect(readViewParams(url.search, ERAS)).toEqual(state);
    });
});

describe('expressions MapLibre de la comparaison', () => {
    it('sont valides pour la spécification de style', () => {
        expect(() => compile(compareColorExpr(), spec('paint_line', 'line-color'))).not.toThrow();
        expect(() => compile(compareWidthExpr(), spec('paint_line', 'line-width'))).not.toThrow();
        expect(() =>
            compile(compareOpacityExpr(), spec('paint_line', 'line-opacity')),
        ).not.toThrow();
        expect(() =>
            compile(compareSortExpr(), spec('layout_line', 'line-sort-key')),
        ).not.toThrow();
    });

    it('donnent à chaque changement sa couleur de légende', () => {
        for (const c of COMPARE_CHANGES)
            expect(colorOf(compareColorExpr(), { change: c.key })).toBe(c.color);
    });

    it('dessinent les changements au-dessus des tronçons restés en service, plus épais', () => {
        const sort = (change) =>
            evaluate(compareSortExpr(), spec('layout_line', 'line-sort-key'), { change });
        expect(sort('opened')).toBeGreaterThan(sort('kept'));
        expect(sort('closed')).toBeGreaterThan(sort('kept'));
        const width = (change) =>
            evaluate(compareWidthExpr(), spec('paint_line', 'line-width'), { change });
        expect(width('opened')).toBeGreaterThan(width('kept'));
        const opacity = (change) =>
            evaluate(compareOpacityExpr(), spec('paint_line', 'line-opacity'), { change });
        expect(opacity('kept')).toBeLessThan(opacity('removed'));
    });

    it('ne portent pas l’information par la seule couleur : fermé et disparu ont leur trait', () => {
        const dash = (key) => COMPARE_CHANGES.find((c) => c.key === key).dash;
        expect(dash('opened')).toBeNull();
        expect(dash('closed')).not.toEqual(dash('removed'));
        expect(dash('closed')).not.toBeNull();
    });
});

describe('vue empilée', () => {
    it('a des classes contiguës qui finissent au réseau actuel', () => {
        const bounds = stackBounds(2030).map((c) => c.upTo);
        expect(bounds).toEqual([1930, 1950, 1970, 2020, 2030]);
    });

    it('colore selon la dernière décennie en service, du clair au foncé', () => {
        const expr = stackColorExpr(2030);
        expect(() => compile(expr, spec('paint_line', 'line-color'))).not.toThrow();
        const colour = (last_year) => colorOf(expr, { last_year });
        expect(colour(1850)).toBe(STACK_CLASSES[0].color);
        expect(colour(1930)).toBe(STACK_CLASSES[0].color);
        expect(colour(1940)).toBe(STACK_CLASSES[1].color);
        expect(colour(1950)).toBe(STACK_CLASSES[1].color);
        expect(colour(1960)).toBe(STACK_CLASSES[2].color);
        expect(colour(1980)).toBe(STACK_CLASSES[3].color);
        expect(colour(2020)).toBe(STACK_CLASSES[3].color);
        expect(colour(2030)).toBe(STACK_CLASSES[4].color);
    });

    it('a une teinte unique de clarté décroissante (pas d’arc-en-ciel)', () => {
        const lum = (hex) => {
            const [r, g, b] = [1, 3, 5].map((i) => Number.parseInt(hex.slice(i, i + 2), 16) / 255);
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        const values = STACK_CLASSES.map((c) => lum(c.color));
        for (let i = 1; i < values.length; i++) expect(values[i]).toBeLessThan(values[i - 1]);
    });

    it('nomme chaque classe par ses décennies', () => {
        expect(stackLabels(2030).map((c) => [c.labelKey, c.params])).toEqual([
            ['maps.stack.until', { year: 1930 }],
            ['maps.stack.range', { from: 1940, to: 1950 }],
            ['maps.stack.range', { from: 1960, to: 1970 }],
            ['maps.stack.range', { from: 1980, to: 2020 }],
            ['maps.stack.today', {}],
        ]);
    });
});

describe('compareSummary', () => {
    const data = {
        changes: {
            opened: { km: 479, lines: 46 },
            closed: { km: 3030, lines: 462 },
            removed: { km: 22404, lines: 901 },
            kept: { km: 33722, lines: 673 },
        },
        open_km: { from: 58678, to: 34201 },
    };

    it('suit l’ordre de la légende et calcule le solde', () => {
        const s = compareSummary(data);
        expect(s.rows.map((r) => r.key)).toEqual(['opened', 'closed', 'removed', 'kept']);
        expect(s.rows[0]).toMatchObject({ key: 'opened', km: 479, lines: 46 });
        expect([s.openBefore, s.openAfter, s.balance]).toEqual([58678, 34201, -24477]);
        expect(s.unchanged).toBe(false);
    });

    it('signale deux décennies sans changement', () => {
        const same = { changes: { kept: { km: 120, lines: 3 } }, open_km: { from: 120, to: 120 } };
        const s = compareSummary(same);
        expect(s.unchanged).toBe(true);
        expect(s.rows.find((r) => r.key === 'opened').km).toBe(0);
    });

    it('renvoie null sans réponse exploitable', () => {
        expect(compareSummary(null)).toBeNull();
        expect(compareSummary({ message: 'Unknown era' })).toBeNull();
    });
});
