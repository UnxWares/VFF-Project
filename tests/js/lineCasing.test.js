import { describe, expect, it } from 'vitest';
import { eraTint, tintedColor } from '$lib/eraTint.js';
import {
    CASING_COLOR,
    CASING_OPACITY,
    CASING_WIDTH_STOPS,
    casedContrast,
    casingLayer,
    casingOver,
    casingPaint,
    casingWidthExpr,
} from '$lib/lineCasing.js';
import { HERITAGE_COLOR, STATUS_COLOR, TRAFFIC_COLOR } from '$lib/railStyle.js';
import { COMPARE_CHANGES, STACK_CLASSES } from '$lib/epochViews.js';

const ERAS = Array.from({ length: 21 }, (_, i) => 1830 + i * 10);

// Aplats d'OpenStreetMap (style « Carto » de tile.openstreetmap.org), comme dans eraTint.test.js.
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

// Couleurs des tracés pleins qui portent un liseré : couche « en service » (trafic, patrimoine, voie de
// service, construction), ouvertures de la comparaison et vue « toutes les époques ».
const DARK_STACK = ['y1980', 'today'];
const OPENED = COMPARE_CHANGES.find((c) => c.key === 'opened').color;
const LIGHT_LINES = {
    mixte: TRAFFIC_COLOR.mixed,
    patrimoine: HERITAGE_COLOR,
    fret: TRAFFIC_COLOR.freight,
    'voie de service': STATUS_COLOR.service_track,
    construction: STATUS_COLOR.construction,
    ouvertures: OPENED,
    ...Object.fromEntries(
        STACK_CLASSES.filter((c) => !DARK_STACK.includes(c.key)).map((c) => [
            `empilée ${c.key}`,
            c.color,
        ]),
    ),
};
// Tracés foncés : ils contrastent déjà avec le fond, le liseré ne fait que les border.
const DARK_LINES = {
    LGV: TRAFFIC_COLOR.passenger,
    'trafic inconnu': TRAFFIC_COLOR.unknown,
    ...Object.fromEntries(
        STACK_CLASSES.filter((c) => DARK_STACK.includes(c.key)).map((c) => [
            `empilée ${c.key}`,
            c.color,
        ]),
    ),
};

const eachBackground = (callback) => {
    for (const year of ERAS) {
        for (const [name, fill] of Object.entries(OSM_FILLS)) {
            callback(tintedColor(fill, eraTint(year)), `${name} en ${year}`);
        }
    }
};

describe('liseré des tracés : mesure du contraste', () => {
    it('donne au bord de chaque tracé au moins 3:1 contre le fond, à toutes les époques', () => {
        eachBackground((background, where) => {
            for (const [name, line] of [
                ...Object.entries(LIGHT_LINES),
                ...Object.entries(DARK_LINES),
            ]) {
                expect(
                    casedContrast(line, background).ringOnBackground,
                    `${name} sur ${where}`,
                ).toBeGreaterThanOrEqual(3);
            }
        });
    });

    it('garde 3:1 entre une couleur claire et son liseré, sur tous les fonds et à toutes les époques', () => {
        eachBackground((background, where) => {
            for (const [name, line] of Object.entries(LIGHT_LINES)) {
                expect(
                    casedContrast(line, background).lineOnRing,
                    `${name} sur ${where}`,
                ).toBeGreaterThanOrEqual(3);
            }
        });
    });

    it('rend à l’orange VFF et au vert patrimoine ce que le fond clair leur refuse', () => {
        // Sans liseré : sous 3:1 sur tous les aplats d'OpenStreetMap, même en 2030. Seule exception, le
        // vert sur les petites routes en blanc pur (3,3:1).
        for (const fill of Object.values(OSM_FILLS)) {
            expect(casedContrast(TRAFFIC_COLOR.mixed, fill).lineOnBackground).toBeLessThan(3);
            if (fill !== OSM_FILLS.minor) {
                expect(casedContrast(HERITAGE_COLOR, fill).lineOnBackground).toBeLessThan(3);
            }
        }
        // Avec le liseré : orange et vert dépassent 4,5:1 contre lui, et lui dépasse 5:1 contre le fond.
        eachBackground((background, where) => {
            for (const line of [TRAFFIC_COLOR.mixed, HERITAGE_COLOR]) {
                const c = casedContrast(line, background);
                expect(c.lineOnRing, where).toBeGreaterThan(4.5);
                expect(c.ringOnBackground, where).toBeGreaterThan(5);
            }
        });
    });

    it('ne change aucune couleur de tracé', () => {
        expect(TRAFFIC_COLOR.mixed).toBe('#ff6500');
        expect(HERITAGE_COLOR).toBe('#16a34a');
    });

    it('mélange le liseré à son opacité sur le fond', () => {
        expect(casingOver('#ffffff')).toBe('#242222');
        expect(casingOver(CASING_COLOR)).toBe(CASING_COLOR);
    });
});

describe('liseré des tracés : couches MapLibre', () => {
    it('reste discret : foncé, opaque à 85 à 95 %, brun-noir et non noir pur', () => {
        expect(CASING_OPACITY).toBeGreaterThanOrEqual(0.85);
        expect(CASING_OPACITY).toBeLessThanOrEqual(0.95);
        expect(CASING_COLOR).toMatch(/^#[0-9a-f]{6}$/);
        expect(CASING_COLOR).not.toBe('#000000');
    });

    it('a 1 px de chaque côté dès le zoom 12 et ne dépasse jamais 1 px', () => {
        const widths = CASING_WIDTH_STOPS.map(([, w]) => w);
        expect(widths.at(-1)).toBe(1);
        expect(CASING_WIDTH_STOPS.at(-1)[0]).toBeLessThanOrEqual(12);
        for (let i = 1; i < CASING_WIDTH_STOPS.length; i++) {
            expect(CASING_WIDTH_STOPS[i][0]).toBeGreaterThan(CASING_WIDTH_STOPS[i - 1][0]);
            expect(widths[i]).toBeGreaterThanOrEqual(widths[i - 1]);
        }
        expect(Math.max(...widths)).toBeLessThanOrEqual(1);
        expect(casingWidthExpr().slice(0, 3)).toEqual(['interpolate', ['linear'], ['zoom']]);
    });

    it('borde le tracé sans le recouvrir : la largeur du tracé reste vide', () => {
        const width = ['interpolate', ['linear'], ['zoom'], 4, 1.5, 12, 4];
        const paint = casingPaint(width);
        expect(paint['line-gap-width']).toBe(width);
        expect(paint['line-width']).toEqual(casingWidthExpr());
        expect(paint['line-color']).toBe(CASING_COLOR);
        expect(paint['line-opacity']).toBe(CASING_OPACITY);
        // Pas de motif de tirets : MapLibre le mesure en largeurs du trait de la couche.
        expect(paint).not.toHaveProperty('line-dasharray');
    });

    it('fait une seule couche par source, avec le filtre et l’ordre de dessin du tracé', () => {
        const filter = ['==', ['get', 'status'], 'in_service'];
        const layer = casingLayer({
            id: 'vff-lines-casing',
            source: 'vff-lines',
            sourceLayer: 'vff-lines',
            filter,
            sortKey: ['coalesce', ['get', 'draw_rank'], 0],
            width: 3,
            opacityTransitionMs: 350,
        });
        expect(layer.type).toBe('line');
        expect(layer.source).toBe('vff-lines');
        expect(layer.filter).toBe(filter);
        expect(layer.layout['line-sort-key']).toEqual(['coalesce', ['get', 'draw_rank'], 0]);
        expect(layer.layout['line-cap']).toBe('round');
        expect(layer.paint['line-opacity-transition']).toEqual({ duration: 350 });
    });

    it('peut n’apparaître qu’à partir d’un zoom', () => {
        const layer = casingLayer({ id: 'x', source: 's', sourceLayer: 'l', width: 2, minzoom: 9 });
        expect(layer.minzoom).toBe(9);
    });

    it('omet le filtre, le zoom minimal, l’ordre et la transition quand on n’en donne pas', () => {
        const layer = casingLayer({ id: 'x', source: 's', sourceLayer: 'l', width: 2 });
        expect(layer).not.toHaveProperty('filter');
        expect(layer).not.toHaveProperty('minzoom');
        expect(layer.layout).not.toHaveProperty('line-sort-key');
        expect(layer.paint).not.toHaveProperty('line-opacity-transition');
    });
});
