import { describe, expect, it } from 'vitest';
import { v8 } from '@maplibre/maplibre-gl-style-spec';
import {
    NEUTRAL_TINT,
    TINT_STOPS,
    WASH_COLOR,
    contrastRatio,
    eraTint,
    rasterTintPaint,
    readTint,
    tintStrength,
    tintedColor,
    writeTint,
} from '$lib/eraTint.js';
import { HERITAGE_COLOR, STATUS_STYLES, TRAFFIC_STYLES } from '$lib/railStyle.js';
import { COMPARE_CHANGES } from '$lib/epochViews.js';

const ERAS = Array.from({ length: 21 }, (_, i) => 1830 + i * 10);
const BASE = 'https://vff-project.org/maps';

// Aplats d'OpenStreetMap (style « Carto » de tile.openstreetmap.org) : terrain, eau, forêt, prairie,
// zone habitée, cultures, autoroute, route principale, petites routes.
const OSM_FILLS = {
    land: '#f2efe9',
    water: '#aad3df',
    forest: '#add19e',
    grass: '#cdebb0',
    residential: '#e0dfdf',
    farmland: '#eef0d5',
    motorway: '#e892a2',
    primary: '#fcd6a4',
    minor: '#ffffff',
};
// Couleurs des tracés (railStyle.js) et de la comparaison (epochViews.js).
const LINE_COLORS = [
    ...TRAFFIC_STYLES.map((t) => t.color),
    HERITAGE_COLOR,
    ...STATUS_STYLES.map((s) => s.color),
    ...COMPARE_CHANGES.map((c) => c.color),
];

describe('tintStrength', () => {
    it('ne teinte pas le réseau d’aujourd’hui ni au-delà', () => {
        expect(tintStrength(2030)).toBe(0);
        expect(tintStrength(2040)).toBe(0);
        expect(tintStrength(2020, 2020)).toBe(0);
    });

    it('donne le sépia complet de 1830 à 1860 et une teinte légère vers 1950', () => {
        expect(tintStrength(1830)).toBe(1);
        expect(tintStrength(1860)).toBe(1);
        expect(tintStrength(1800)).toBe(1);
        expect(tintStrength(1950)).toBeCloseTo(0.4);
        expect(tintStrength(1990)).toBeCloseTo(0.15);
    });

    it('décroît sans jamais remonter de 1830 à aujourd’hui, entre 0 et 1', () => {
        const values = ERAS.map((y) => tintStrength(y));
        for (const v of values) {
            expect(v).toBeGreaterThanOrEqual(0);
            expect(v).toBeLessThanOrEqual(1);
        }
        for (let i = 1; i < values.length; i++) {
            expect(values[i]).toBeLessThanOrEqual(values[i - 1]);
        }
        expect(values.at(-2)).toBeGreaterThan(0);
    });

    it('a des repères dans l’ordre des années', () => {
        for (let i = 1; i < TINT_STOPS.length; i++) {
            expect(TINT_STOPS[i][0]).toBeGreaterThan(TINT_STOPS[i - 1][0]);
        }
    });

    it('est neutre pour une année absente', () => {
        expect(tintStrength(Number.NaN)).toBe(0);
        expect(tintStrength(null)).toBe(0);
    });
});

describe('eraTint', () => {
    it('est neutre en 2030', () => {
        expect(eraTint(2030)).toEqual(NEUTRAL_TINT);
    });

    it('est neutre quand la teinte est coupée', () => {
        expect(eraTint(1850, { enabled: false })).toEqual(NEUTRAL_TINT);
    });

    it('est neutre en comparaison et en vue « toutes les époques »', () => {
        expect(eraTint(1850, { view: 'compare' })).toEqual(NEUTRAL_TINT);
        expect(eraTint(1850, { view: 'stack' })).toEqual(NEUTRAL_TINT);
    });

    it('suit la décennie d’aujourd’hui donnée par la base', () => {
        expect(eraTint(2020, { today: 2020 })).toEqual(NEUTRAL_TINT);
        expect(eraTint(2020).wash).toBeGreaterThan(0);
    });

    it('renforce chaque réglage à mesure que l’époque recule', () => {
        let prev = eraTint(2030);
        for (const year of [...ERAS].reverse().slice(1)) {
            const next = eraTint(year);
            expect(next.saturation).toBeLessThanOrEqual(prev.saturation);
            expect(next.brightnessMin).toBeGreaterThanOrEqual(prev.brightnessMin);
            expect(next.wash).toBeGreaterThanOrEqual(prev.wash);
            prev = next;
        }
        const old = eraTint(1850);
        expect(old.saturation).toBeLessThan(-0.4);
        expect(old.wash).toBeGreaterThan(0.2);
        // Discret : jamais de noir et blanc, voile toujours transparent.
        expect(old.saturation).toBeGreaterThan(-1);
        expect(old.wash).toBeLessThan(0.5);
    });

    it('n’assombrit jamais le fond (luminosité maximale et contraste inchangés)', () => {
        for (const year of ERAS) {
            const t = eraTint(year);
            expect(t.brightnessMax).toBe(1);
            expect(t.contrast).toBe(0);
        }
    });
});

describe('rasterTintPaint', () => {
    it('donne des propriétés de peinture raster valides pour MapLibre', () => {
        for (const year of [1830, 1950, 2030]) {
            const paint = rasterTintPaint(eraTint(year));
            for (const [prop, value] of Object.entries(paint)) {
                const spec = v8.paint_raster[prop];
                expect(spec, prop).toBeDefined();
                expect(spec.transition, prop).toBe(true);
                expect(value).toBeGreaterThanOrEqual(spec.minimum ?? -Infinity);
                expect(value).toBeLessThanOrEqual(spec.maximum ?? Infinity);
            }
        }
        expect(v8.paint_background['background-opacity'].transition).toBe(true);
    });

    it('rend les valeurs par défaut de MapLibre quand la teinte est neutre', () => {
        for (const [prop, value] of Object.entries(rasterTintPaint(NEUTRAL_TINT))) {
            expect(value).toBe(v8.paint_raster[prop].default);
        }
    });
});

describe('tintedColor et contraste des lignes', () => {
    it('ne change rien sans teinte', () => {
        for (const hex of Object.values(OSM_FILLS))
            expect(tintedColor(hex, NEUTRAL_TINT)).toBe(hex);
    });

    it('réchauffe et désature le fond des époques anciennes', () => {
        const water = tintedColor(OSM_FILLS.water, eraTint(1850));
        const [r, , b] = [1, 3, 5].map((i) => Number.parseInt(water.slice(i, i + 2), 16));
        expect(r).toBeGreaterThan(0xaa);
        expect(b).toBeLessThan(0xdf);
        expect(WASH_COLOR).toMatch(/^#[0-9a-f]{6}$/);
    });

    it('garde au-dessus de 3:1 toute ligne qui l’était sur le fond d’origine', () => {
        for (const year of ERAS) {
            const t = eraTint(year);
            for (const [name, fill] of Object.entries(OSM_FILLS)) {
                const tinted = tintedColor(fill, t);
                for (const line of LINE_COLORS) {
                    if (contrastRatio(line, fill) >= 3) {
                        expect(
                            contrastRatio(line, tinted),
                            `${line} sur ${name} en ${year}`,
                        ).toBeGreaterThanOrEqual(3);
                    }
                }
            }
        }
    });

    it('ne baisse le contraste d’aucune ligne de plus de 5 %, et le relève sur l’eau et les forêts', () => {
        for (const year of ERAS) {
            const t = eraTint(year);
            for (const fill of Object.values(OSM_FILLS)) {
                for (const line of LINE_COLORS) {
                    const ratio =
                        contrastRatio(line, tintedColor(fill, t)) / contrastRatio(line, fill);
                    expect(ratio).toBeGreaterThan(0.95);
                }
            }
        }
        const old = eraTint(1850);
        for (const fill of [OSM_FILLS.water, OSM_FILLS.forest, OSM_FILLS.motorway]) {
            for (const line of LINE_COLORS) {
                expect(contrastRatio(line, tintedColor(fill, old))).toBeGreaterThan(
                    contrastRatio(line, fill),
                );
            }
        }
    });

    it('calcule le rapport WCAG', () => {
        expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21);
        expect(contrastRatio('#ffffff', '#ffffff')).toBeCloseTo(1);
    });
});

describe('readTint / writeTint', () => {
    it('active la teinte par défaut et la coupe avec ?tint=off', () => {
        expect(readTint('')).toBe(true);
        expect(readTint('?era=1850')).toBe(true);
        expect(readTint('?tint=off')).toBe(false);
        expect(readTint('?tint=0')).toBe(false);
        expect(readTint('?tint=OFF')).toBe(false);
        expect(readTint('?tint=on')).toBe(true);
    });

    it('écrit ?tint=off seulement quand elle est coupée et garde les autres paramètres', () => {
        const off = writeTint(`${BASE}?era=1850&layer=accidents`, false);
        expect(off).toBe(`${BASE}?era=1850&layer=accidents&tint=off`);
        expect(readTint(new URL(off).search)).toBe(false);
        expect(writeTint(off, true)).toBe(`${BASE}?era=1850&layer=accidents`);
        expect(writeTint(BASE, true)).toBe(BASE);
    });
});
