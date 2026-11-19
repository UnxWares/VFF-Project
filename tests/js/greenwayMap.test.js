import { describe, expect, it } from 'vitest';
import {
    GREENWAY_CASING_LAYER_ID,
    GREENWAY_COLOR,
    GREENWAY_LAYER_IDS,
    GREENWAY_LINE_LAYER_ID,
    GREENWAY_MIN_ZOOM,
    GREENWAY_SOURCE_ID,
    GREENWAY_SOURCE_LAYER,
    greenwayLayers,
    greenwayPopupHtml,
    greenwayTilesUrl,
} from '$lib/greenwayMap.js';
import { OVERLAY_COLORS, OVERLAY_HALO } from '$lib/overlayPalette.js';
import {
    HERITAGE_COLOR,
    LGV_COLOR,
    REOPENING_COLOR,
    STATUS_STYLES,
    THREATENED_COLOR,
    TRAFFIC_STYLES,
} from '$lib/railStyle.js';
import { compile, spec } from './support/maplibre.js';

/** Traduction factice : la clé seule. */
const t = (key) => key;

/** Rapport de contraste WCAG entre deux couleurs hexadécimales. */
function contrast(a, b) {
    const lum = (hex) => {
        const channel = (i) => {
            const v = Number.parseInt(hex.slice(1 + i * 2, 3 + i * 2), 16) / 255;
            return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * channel(0) + 0.7152 * channel(1) + 0.0722 * channel(2);
    };
    const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}

describe('greenwayLayers', () => {
    const layers = greenwayLayers();

    it('pose un liseré blanc sous un trait plein, lus dans la couche des tuiles', () => {
        expect(layers.map((l) => l.id)).toEqual([GREENWAY_CASING_LAYER_ID, GREENWAY_LINE_LAYER_ID]);
        expect(GREENWAY_LAYER_IDS).toEqual(layers.map((l) => l.id));
        for (const layer of layers) {
            expect(layer.type).toBe('line');
            expect(layer.source).toBe(GREENWAY_SOURCE_ID);
            expect(layer['source-layer']).toBe(GREENWAY_SOURCE_LAYER);
            expect(layer.minzoom).toBe(GREENWAY_MIN_ZOOM);
        }
        expect(layers[0].paint['line-color']).toBe(OVERLAY_HALO);
        expect(layers[1].paint['line-color']).toBe(GREENWAY_COLOR);
    });

    it('a des expressions MapLibre valides, un liseré plus large que le trait', () => {
        for (const layer of layers) {
            for (const [prop, value] of Object.entries(layer.paint)) {
                compile(value, spec('paint_line', prop));
            }
        }
        const width = (layer, zoom) =>
            compile(layer.paint['line-width'], spec('paint_line', 'line-width')).evaluate(
                { zoom },
                { type: 2, properties: {} },
            );
        for (const zoom of [5, 9, 13]) {
            expect(width(layers[0], zoom)).toBeGreaterThan(width(layers[1], zoom));
        }
    });

    it('n’a ni tirets ni pointillés : ils désignent les lignes fermées et déposées', () => {
        for (const layer of layers) expect(layer.paint['line-dasharray']).toBeUndefined();
    });
});

/** Teinte (0 à 360°) d'une couleur hexadécimale. */
function hue(hex) {
    const [r, g, b] = [1, 3, 5].map((i) => Number.parseInt(hex.slice(i, i + 2), 16) / 255);
    const max = Math.max(r, g, b);
    const d = max - Math.min(r, g, b);
    const h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    return (h * 60 + 360) % 360;
}

describe('rendu distinct des tracés ferroviaires et des autres calques', () => {
    const rail = [
        ...TRAFFIC_STYLES.map((s) => s.color),
        ...STATUS_STYLES.map((s) => s.color),
        HERITAGE_COLOR,
        LGV_COLOR,
        THREATENED_COLOR,
        REOPENING_COLOR,
    ].map((c) => c.toLowerCase());

    it('le rose n’est la couleur d’aucun tracé du réseau ni d’aucun autre calque', () => {
        expect(rail.length).toBeGreaterThan(5);
        expect([...rail, ...OVERLAY_COLORS.map((c) => c.toLowerCase())]).not.toContain(
            GREENWAY_COLOR,
        );
    });

    it('sa teinte est à 20° au moins de celle de chaque couleur de tracé colorée', () => {
        for (const color of rail.filter((c) => /^#[0-9a-f]{6}$/.test(c))) {
            const [r, g, b] = [1, 3, 5].map((i) => Number.parseInt(color.slice(i, i + 2), 16));
            if (Math.max(r, g, b) - Math.min(r, g, b) < 30) continue; // gris : pas de teinte
            const gap = Math.abs(hue(color) - hue(GREENWAY_COLOR));
            expect(Math.min(gap, 360 - gap), color).toBeGreaterThanOrEqual(20);
        }
    });

    it('se lit sur un fond clair : au moins 4,5:1 contre le blanc, avec un liseré blanc', () => {
        expect(contrast(GREENWAY_COLOR, '#ffffff')).toBeGreaterThanOrEqual(4.5);
        expect(OVERLAY_HALO).toBe('#ffffff');
    });
});

describe('greenwayTilesUrl', () => {
    it('donne une adresse absolue avec le paramètre de version', () => {
        expect(greenwayTilesUrl('https://vff-project.org', '?v=3')).toBe(
            'https://vff-project.org/api/v1/greenways/tiles/{z}/{x}/{y}.mvt?v=3',
        );
        expect(greenwayTilesUrl('https://vff-project.org')).toBe(
            'https://vff-project.org/api/v1/greenways/tiles/{z}/{x}/{y}.mvt',
        );
    });
});

describe('greenwayPopupHtml', () => {
    const props = {
        way_id: 840864544,
        name: 'Voie verte de Mayenne à Javron',
        shared_length_m: 15161,
        opened_year: 2006,
        line_name: 'Ligne de La Chapelle-Anthenaise à Flers',
    };

    it('donne le nom, la ligne, la longueur sur l’emprise, l’année, le lien OSM et la source', () => {
        const html = greenwayPopupHtml(props, t, 'fr', '<a>lien</a>');
        expect(html).toContain('Voie verte de Mayenne à Javron');
        expect(html).toContain('Ligne de La Chapelle-Anthenaise à Flers');
        expect(html).toContain('15,2');
        expect(html).toContain('2006');
        expect(html).toContain('href="https://www.openstreetmap.org/way/840864544"');
        expect(html).toContain('rel="noopener"');
        expect(html).toContain('maps.greenways.source');
        expect(html).toContain('<a>lien</a>');
    });

    it('omet ce que la tuile ne donne pas : année, longueur, ligne', () => {
        const html = greenwayPopupHtml({ way_id: 7 }, t, 'en', '');
        expect(html).toContain('maps.greenways.fallback_title');
        expect(html).not.toContain('maps.greenways.opened');
        expect(html).not.toContain('maps.greenways.length');
        expect(html).not.toContain('maps.greenways.line');
        expect(html).toContain('/way/7"');
    });

    it('échappe le texte et n’accepte qu’un identifiant numérique dans le lien', () => {
        const html = greenwayPopupHtml(
            { name: '<b>x</b>', way_id: '1"><script>', line_name: '<i>' },
            t,
            'fr',
            '',
        );
        expect(html).toContain('&lt;b&gt;x&lt;/b&gt;');
        expect(html).toContain('&lt;i&gt;');
        expect(html).not.toContain('<script>');
        expect(html).not.toContain('openstreetmap.org');
    });
});
