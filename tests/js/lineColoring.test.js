import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    COLORING_LABEL_LAYER_ID,
    COLORING_LABEL_MIN_ZOOM,
    DEFAULT_COLORING,
    coloringAvailable,
    coloringClass,
    coloringLabelLayer,
    coloringNotice,
    coloringStyles,
    coloringYears,
    drawnColoring,
    formatColoringYears,
    readColoring,
    voltageText,
    writeColoring,
} from '$lib/lineColoring.js';
import {
    MAP_COLORINGS,
    MAP_MODES,
    SIGNALLING_BASE_COLOR,
    SPEED_STYLES,
    modeColorExpr,
    speedColorExpr,
} from '$lib/railStyle.js';
import { listingUrl } from '$lib/mapListing.js';
import { colorOf, evaluate, matches, spec } from './support/maplibre.js';

const ERAS = [
    { year: 1950, has_data: true, colorings: ['electrification'] },
    { year: 2010, has_data: true, colorings: ['electrification', 'signalling'] },
    { year: 2020, has_data: true, colorings: ['electrification', 'speed', 'signalling'] },
    { year: 2030, has_data: true, colorings: ['electrification', 'speed', 'signalling'] },
];
const t = (key, params = {}) =>
    key === 'maps.compare.today_inline'
        ? "aujourd'hui"
        : `${key}${Object.keys(params).length ? JSON.stringify(params) : ''}`;

describe('adresse partageable', () => {
    it('lit ?color= et retombe sur le trafic pour une valeur absente ou inconnue', () => {
        expect(readColoring('?color=speed')).toBe('speed');
        expect(readColoring('?era=1950&color=signalling')).toBe('signalling');
        expect(readColoring('')).toBe(DEFAULT_COLORING);
        expect(readColoring('?color=rainbow')).toBe(DEFAULT_COLORING);
    });

    it('écrit la coloration en gardant les autres paramètres, et la retire pour le trafic', () => {
        const withSpeed = writeColoring('https://vff.test/maps?era=2020&layer=accidents', 'speed');
        expect(new URL(withSpeed).searchParams.get('color')).toBe('speed');
        expect(new URL(withSpeed).searchParams.get('era')).toBe('2020');
        expect(new URL(withSpeed).searchParams.get('layer')).toBe('accidents');
        expect(writeColoring(withSpeed, 'traffic')).not.toContain('color=');
        expect(writeColoring(withSpeed, 'inconnu')).not.toContain('color=');
    });
});

describe('colorations possibles à une époque', () => {
    it('la carte intégrable garde ses modes ; /maps ajoute vitesse et signalisation', () => {
        expect(MAP_MODES.every((m) => MAP_COLORINGS.includes(m))).toBe(true);
        expect(MAP_COLORINGS).toEqual([
            'traffic',
            'speed',
            'electrification',
            'signalling',
            'gauge',
        ]);
    });

    it('années de chaque coloration, d’après les données datées des ères', () => {
        expect(coloringYears('speed', ERAS)).toEqual([2020, 2030]);
        expect(coloringYears('signalling', ERAS)).toEqual([2010, 2020, 2030]);
        expect(coloringYears('traffic', ERAS)).toEqual([1950, 2010, 2020, 2030]);
        expect(coloringYears('speed', null)).toEqual([]);
    });

    it('trafic et écartement toujours possibles ; vitesse seulement si chaque époque affichée la connaît', () => {
        expect(coloringAvailable('traffic', [1950], ERAS)).toBe(true);
        expect(coloringAvailable('gauge', [1950], ERAS)).toBe(true);
        expect(coloringAvailable('speed', [2030], ERAS)).toBe(true);
        expect(coloringAvailable('speed', [1950], ERAS)).toBe(false);
        expect(coloringAvailable('speed', [2020, 2030], ERAS)).toBe(true);
        expect(coloringAvailable('speed', [1950, 2030], ERAS)).toBe(false);
        expect(coloringAvailable('speed', [], ERAS)).toBe(false);
        expect(coloringAvailable('inconnu', [2030], ERAS)).toBe(false);
    });

    it('la signalisation se dessine sur une seule époque (pas avec le rideau)', () => {
        expect(coloringAvailable('signalling', [2010], ERAS)).toBe(true);
        expect(coloringAvailable('signalling', [2020, 2030], ERAS)).toBe(false);
        expect(coloringNotice('signalling', [2020, 2030], ERAS, String)?.key).toBe(
            'maps.coloring.unavailable.one_era',
        );
    });

    it('une coloration impossible est remplacée par le trafic sur la carte', () => {
        expect(drawnColoring('speed', [1950], ERAS)).toBe('traffic');
        expect(drawnColoring('speed', [2030], ERAS)).toBe('speed');
        expect(drawnColoring('gauge', [1950], ERAS)).toBe('gauge');
    });

    it('explication : années où la coloration existe, ou « pas encore connue »', () => {
        expect(coloringNotice('speed', [2030], ERAS, String)).toBeNull();
        expect(
            coloringNotice('speed', [1950], ERAS, (y) => formatColoringYears(y, 2030, t)),
        ).toEqual({
            key: 'maps.coloring.unavailable.speed',
            params: { years: `maps.coloring.years_and{"head":"2020","last":"aujourd'hui"}` },
        });
        expect(coloringNotice('speed', [1950], [{ year: 1950 }], String)?.key).toBe(
            'maps.coloring.unavailable.speed_none',
        );
    });

    it('années en mots : une, deux ou trois années, puis un intervalle', () => {
        expect(formatColoringYears([], 2030, t)).toBe('');
        expect(formatColoringYears([2030], 2030, t)).toBe("aujourd'hui");
        expect(formatColoringYears([2010, 2020, 2030], 2030, t)).toBe(
            `maps.coloring.years_and{"head":"2010, 2020","last":"aujourd'hui"}`,
        );
        expect(formatColoringYears([1950, 1960, 1970, 1980], 2030, t)).toBe(
            'maps.coloring.years_range{"from":"1950","to":"1980"}',
        );
    });
});

describe('couleurs de la vitesse et de la signalisation', () => {
    const byKey = Object.fromEntries(SPEED_STYLES.map((s) => [s.key, s.color]));

    it('une couleur par classe de vitesse, distincte des autres', () => {
        expect(colorOf(speedColorExpr(), { speed_class: 1 })).toBe(byKey.v100);
        expect(colorOf(speedColorExpr(), { speed_class: 2 })).toBe(byKey.v160);
        expect(colorOf(speedColorExpr(), { speed_class: 3 })).toBe(byKey.v220);
        expect(colorOf(speedColorExpr(), { speed_class: 4 })).toBe(byKey.v220_plus);
        expect(colorOf(speedColorExpr(), {})).toBe(byKey.unknown);
        expect(new Set(SPEED_STYLES.map((s) => s.color)).size).toBe(SPEED_STYLES.length);
    });

    it('mêmes bornes que App\\Support\\SpeedClass', () => {
        const php = readFileSync(
            new URL('../../app/Support/SpeedClass.php', import.meta.url),
            'utf8',
        );
        expect(php).toContain('UPPER_BOUNDS = [1 => 100, 2 => 160, 3 => 220]');
        const keys = SPEED_STYLES.filter((s) => s.cls !== null).map(
            (s) => `${s.cls} => '${s.key}'`,
        );
        expect(php).toContain(`KEYS = [${keys.join(', ')}]`);
    });

    it('modeColorExpr : vitesse par classe, signalisation sur un réseau en teinte neutre', () => {
        expect(modeColorExpr('speed', 'x')).toEqual(speedColorExpr());
        expect(modeColorExpr('signalling', 'x')).toBe(SIGNALLING_BASE_COLOR);
    });

    it('chaque coloration a ses lignes de légende, avec une clé de traduction', () => {
        for (const m of MAP_COLORINGS) {
            const rows = coloringStyles(m);
            expect(rows.length).toBeGreaterThan(1);
            for (const r of rows) expect(r.labelKey).toMatch(/^maps\./);
        }
        expect(coloringClass('speed', 'v160')?.labelKey).toBe('maps.coloring.speed.v160');
        expect(coloringClass('signalling', 'cab')?.labelKey).toBe('maps.signalling.families.cab');
        expect(coloringClass('electrification', 'ac25')).not.toBeNull();
        expect(coloringClass('speed', 'inconnu')).toBeNull();
    });
});

describe('libellés écrits le long des tracés', () => {
    const font = ['Noto Sans Bold'];
    const textField = spec('layout_symbol', 'text-field');

    it('vitesse : la classe en texte, jamais sur une portion recouverte', () => {
        const layer = coloringLabelLayer('speed', { font });
        expect(layer.id).toBe(COLORING_LABEL_LAYER_ID);
        expect(layer.minzoom).toBe(COLORING_LABEL_MIN_ZOOM);
        expect(layer.layout['text-size']).toBeGreaterThanOrEqual(13);
        expect(evaluate(layer.layout['text-field'], textField, { speed_class: 3 }).toString()).toBe(
            '160-220 km/h',
        );
        expect(evaluate(layer.layout['text-field'], textField, { speed_class: 1 }).toString()).toBe(
            '0-100 km/h',
        );
        expect(matches(layer.filter, { speed_class: 2 })).toBe(true);
        expect(matches(layer.filter, { speed_class: 2, covered: true })).toBe(false);
        expect(matches(layer.filter, {})).toBe(false);
    });

    it('électrification : la tension, selon la langue', () => {
        const fr = coloringLabelLayer('electrification', { font, lang: 'fr' });
        expect(evaluate(fr.layout['text-field'], textField, { voltage_v: 1500 }).toString()).toBe(
            '1,5 kV',
        );
        const en = coloringLabelLayer('electrification', { font, lang: 'en' });
        expect(evaluate(en.layout['text-field'], textField, { voltage_v: 1500 }).toString()).toBe(
            '1.5 kV',
        );
        expect(matches(fr.filter, { electrified: true, voltage_v: 25000 })).toBe(true);
        expect(matches(fr.filter, {})).toBe(false);
    });

    it('signalisation : le code du mode de cantonnement, sur les tuiles du calque', () => {
        const layer = coloringLabelLayer('signalling', { font });
        expect(layer.source).toBe('vff-signalling');
        expect(
            evaluate(layer.layout['text-field'], textField, { signalling: 'BAL' }).toString(),
        ).toBe('BAL');
    });

    it('aucun libellé pour le trafic et l’écartement', () => {
        expect(coloringLabelLayer('traffic', { font })).toBeNull();
        expect(coloringLabelLayer('gauge', { font })).toBeNull();
    });

    it('tension en mots courts', () => {
        expect(voltageText(25000)).toBe('25 kV');
        expect(voltageText(750)).toBe('750 V');
        expect(voltageText(0)).toBe('');
    });
});

describe('liste de ce qui est affiché', () => {
    it('demande les classes de la coloration, pas pour le trafic', () => {
        expect(listingUrl(2030, [1, 2, 3, 4], 'v1', 'speed')).toBe(
            '/api/v1/eras/2030/in-view?bbox=1%2C2%2C3%2C4&color=speed&v=v1',
        );
        expect(listingUrl(2030, [1, 2, 3, 4], 'v1', 'traffic')).not.toContain('color=');
        expect(listingUrl(2030, [1, 2, 3, 4])).not.toContain('color=');
    });
});
