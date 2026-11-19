import { describe, expect, it } from 'vitest';
import {
    LEVEL_CROSSING_GROUPS,
    LEVEL_CROSSING_MIN_ZOOM,
    LEVEL_CROSSING_SOURCE_LAYER,
    levelCrossingLayer,
    levelCrossingPopupHtml,
    levelCrossingTilesUrl,
    pkLabel,
} from '$lib/levelCrossingMap.js';
import { OVERLAY_COLORS } from '$lib/overlayPalette.js';
import { colorOf, compile, spec } from './support/maplibre.js';

/** Traduction factice : la clé seule. */
const t = (key) => key;
const circle = spec('paint_circle', 'circle-color');

describe('pkLabel', () => {
    it('met un PK en mètres en notation km+mmm', () => {
        expect(pkLabel(112866)).toBe('112+866');
        expect(pkLabel(20)).toBe('0+020');
        expect(pkLabel(-771)).toBe('-0+771');
        expect(pkLabel('19663')).toBe('19+663');
    });

    it('renvoie null pour un PK inconnu', () => {
        expect(pkLabel(null)).toBeNull();
        expect(pkLabel(undefined)).toBeNull();
        expect(pkLabel('')).toBeNull();
    });
});

describe('levelCrossingLayer', () => {
    const layer = levelCrossingLayer();

    it('lit la couche des tuiles et ne se montre qu’à partir du zoom minimal', () => {
        expect(layer['source-layer']).toBe(LEVEL_CROSSING_SOURCE_LAYER);
        expect(layer.minzoom).toBe(LEVEL_CROSSING_MIN_ZOOM);
        expect(LEVEL_CROSSING_MIN_ZOOM).toBe(9);
    });

    it('colore chaque groupe de sa teinte, avec des expressions valides', () => {
        for (const [prop, s] of Object.entries(layer.paint)) {
            compile(layer.paint[prop], spec('paint_circle', prop) ?? s);
        }
        for (const g of LEVEL_CROSSING_GROUPS) {
            expect(colorOf(layer.paint['circle-color'], { group: g.key }, circle)).toBe(g.color);
        }
        expect(colorOf(layer.paint['circle-color'], { group: 'secondary' }, circle)).toBe(
            '#64748b',
        );
    });

    it('n’emploie que la palette des calques, une teinte par groupe', () => {
        expect(LEVEL_CROSSING_GROUPS.map((g) => g.color)).toEqual(OVERLAY_COLORS);
        expect(new Set(LEVEL_CROSSING_GROUPS.map((g) => g.key)).size).toBe(5);
        expect(LEVEL_CROSSING_GROUPS.map((g) => g.key)).not.toContain('secondary');
    });
});

describe('levelCrossingTilesUrl', () => {
    it('donne une adresse absolue avec le paramètre de version', () => {
        expect(levelCrossingTilesUrl('https://vff-project.org', '?v=3')).toBe(
            'https://vff-project.org/api/v1/level-crossings/tiles/{z}/{x}/{y}.mvt?v=3',
        );
    });
});

describe('levelCrossingPopupHtml', () => {
    const props = {
        label: 'PN 32',
        class: 17,
        pk_m: 112866,
        commune: 'Courthiézy',
        crossed_way: 'VC',
        line_name: 'Ligne de Noisy-le-Sec à Strasbourg-Ville',
    };

    it('donne le type, le PK, la ligne, la commune, la voie franchie et la source', () => {
        const html = levelCrossingPopupHtml(props, t, 'fr', '<a>lien</a>');
        expect(html).toContain('PN 32');
        expect(html).toContain('level_crossings.classes.17');
        expect(html).toContain('112+866');
        expect(html).toContain('Courthiézy');
        expect(html).toContain('maps.level_crossings.source');
        expect(html).toContain('<a>lien</a>');
    });

    it('omet ce que la source ne donne pas et échappe le texte', () => {
        const html = levelCrossingPopupHtml(
            { label: '<b>PN</b>', class: null, pk_m: null },
            t,
            'en',
            '',
        );
        expect(html).toContain('&lt;b&gt;PN&lt;/b&gt;');
        expect(html).not.toContain('level_crossings.classes');
        expect(html).not.toContain('maps.level_crossings.pk');
    });

    it('accepte la classe 0 (PN secondaire)', () => {
        expect(levelCrossingPopupHtml({ class: 0 }, t, 'fr', '')).toContain(
            'level_crossings.classes.0',
        );
    });
});
