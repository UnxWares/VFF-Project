import { describe, expect, it } from 'vitest';
import { contrastRatio } from '$lib/eraTint.js';
import { casedContrast } from '$lib/lineCasing.js';
import { mapViews, readViewParams, writeViewParams, VIEWS } from '$lib/epochViews.js';
import { mapControlsLocale } from '$lib/mapControls.js';
import { HERITAGE_COLOR, LGV_COLOR, TRAFFIC_COLOR } from '$lib/railStyle.js';
import {
    LABEL_MIN_ZOOM,
    NARROW_LABEL_ZOOM,
    SCHEMA_CATEGORIES,
    SCHEMA_LABEL_LAYERS,
    SCHEMA_LAYERS,
    SCHEMA_NODE_LAYERS,
    SCHEMA_PALETTES,
    SCHEMA_STATION_LAYERS,
    axisColorExpr,
    axisFilter,
    categoryColors,
    cityLabelMinZoom,
    drawerLine,
    lineIdOf,
    paletteFor,
    schematicLayers,
    schematicUrl,
    selectedFilter,
    stationPosition,
    summaryParts,
    tickImage,
} from '$lib/schematicMap.js';
import { stationPopupHtml } from '../../resources/js/Components/Map/lineInfo.js';

const ERAS = Array.from({ length: 21 }, (_, i) => 1830 + i * 10);

const PLAN = {
    era: 1930,
    summary: { km: { mixed: 41000, freight: 1200, lgv: 0, heritage: 30 }, stations: 6474 },
    lines: {
        12: {
            slug: 'rfn-420000',
            name: 'Ligne de Paris à Bordeaux',
            code: '420000',
            network: 'principal',
            is_lgv: false,
            max_speed_kmh: 160,
            electrified: true,
            voltage_v: 1500,
        },
        40: {
            slug: 'lgv-sud-est',
            name: 'LGV Sud-Est',
            code: '752000',
            network: 'principal',
            is_lgv: true,
        },
    },
    features: [
        {
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: [
                    [1, 47],
                    [2, 47],
                ],
            },
            properties: { kind: 'axis', line_id: 12, category: 'mixed' },
        },
        {
            type: 'Feature',
            geometry: { type: 'Point', coordinates: [2.35, 48.85] },
            properties: { kind: 'station', slug: 'paris-est', name: 'Paris-Est' },
        },
    ],
};

describe('couleurs du plan', () => {
    it('reprend les couleurs de la carte, gris éclairci sur le fond sombre', () => {
        expect(categoryColors(false)).toEqual({
            lgv: LGV_COLOR,
            mixed: TRAFFIC_COLOR.mixed,
            freight: TRAFFIC_COLOR.freight,
            unknown: TRAFFIC_COLOR.unknown,
            heritage: HERITAGE_COLOR,
        });
        expect(categoryColors(true).unknown).not.toBe(TRAFFIC_COLOR.unknown);
        expect(paletteFor(true)).toBe(SCHEMA_PALETTES.dark);
        expect(paletteFor(false)).toBe(SCHEMA_PALETTES.light);
    });

    // Le liseré foncé borde chaque axe (3:1 contre le fond). Une couleur claire (orange, vert) se
    // détache contre lui ; une couleur foncée (violet, ardoise) se détache déjà du fond « papier ».
    it('donne 3:1 au moins à chaque axe sur le fond clair, grâce au liseré', () => {
        const { background } = SCHEMA_PALETTES.light;
        for (const [key, color] of Object.entries(categoryColors(false))) {
            const c = casedContrast(color, background);
            expect(c.ringOnBackground, key).toBeGreaterThanOrEqual(3);
            expect(Math.max(c.lineOnRing, c.lineOnBackground), key).toBeGreaterThanOrEqual(3);
        }
    });

    it('donne 3:1 au moins à chaque axe sur le fond sombre, sans liseré', () => {
        const { background } = SCHEMA_PALETTES.dark;
        for (const [key, color] of Object.entries(categoryColors(true))) {
            expect(contrastRatio(color, background), key).toBeGreaterThanOrEqual(3);
        }
    });

    it('écrit les noms et dessine les gares à 4,5:1 au moins dans les deux thèmes', () => {
        for (const palette of Object.values(SCHEMA_PALETTES)) {
            expect(contrastRatio(palette.ink, palette.background)).toBeGreaterThanOrEqual(4.5);
        }
    });

    it('colore un axe selon sa catégorie', () => {
        const expr = axisColorExpr(false);
        expect(expr.slice(0, 2)).toEqual(['match', ['get', 'category']]);
        expect(expr).toContain(LGV_COLOR);
        expect(expr[expr.length - 1]).toBe(TRAFFIC_COLOR.unknown);
    });
});

describe('couches du plan', () => {
    it('masque les catégories choisies dans la légende', () => {
        expect(axisFilter([])).toEqual(['==', ['get', 'kind'], 'axis']);
        expect(axisFilter(['freight', 'service_track', 'lgv'])).toEqual([
            'all',
            ['==', ['get', 'kind'], 'axis'],
            ['!', ['in', ['get', 'category'], ['literal', ['lgv', 'freight']]]],
        ]);
    });

    it('met en évidence la ligne ouverte, rien sans ligne', () => {
        expect(selectedFilter(12)[2]).toEqual(['==', ['get', 'line_id'], 12]);
        expect(selectedFilter(null)[2]).toEqual(['==', ['get', 'line_id'], -1]);
    });

    it('empile liseré, ligne choisie, axes, gares, nœuds puis noms', () => {
        const layers = schematicLayers({ dark: false, hidden: ['freight'], selected: 12 });
        const ids = layers.map((l) => l.id);
        expect(ids.slice(0, 4)).toEqual([
            SCHEMA_LAYERS.casing,
            SCHEMA_LAYERS.selected,
            SCHEMA_LAYERS.axes,
            SCHEMA_LAYERS.stops,
        ]);
        expect(ids).toEqual(
            expect.arrayContaining([...SCHEMA_NODE_LAYERS, ...SCHEMA_LABEL_LAYERS]),
        );
        expect(ids.indexOf(SCHEMA_LABEL_LAYERS[0])).toBeGreaterThan(
            ids.indexOf(SCHEMA_NODE_LAYERS[2]),
        );
        expect(layers.find((l) => l.id === SCHEMA_LAYERS.axes).filter).toEqual(
            axisFilter(['freight']),
        );
        expect(SCHEMA_STATION_LAYERS).toContain(SCHEMA_LAYERS.stops);
    });

    it('écrit les noms en 13 px au moins, du plus important au moins important', () => {
        const labels = schematicLayers({ dark: true }).filter(
            (l) => l.type === 'symbol' && l.layout['text-field'],
        );
        expect(labels).toHaveLength(LABEL_MIN_ZOOM.length);
        for (const layer of labels) {
            expect(layer.layout['text-size']).toBeGreaterThanOrEqual(13);
            expect(layer.layout['symbol-sort-key']).toEqual(['-', 0, ['get', 'rank']]);
            expect(layer.paint['text-halo-color']).toBe(SCHEMA_PALETTES.dark.background);
        }
        expect(labels.map((l) => l.minzoom)).toEqual(LABEL_MIN_ZOOM);
    });

    it('attend le premier zoom pour les noms des grandes villes sur un plan étroit', () => {
        const narrow = schematicLayers({ dark: false, width: 375 }).find(
            (l) => l.id === SCHEMA_LABEL_LAYERS[0],
        );
        expect(narrow.minzoom).toBe(NARROW_LABEL_ZOOM);
        expect(cityLabelMinZoom(1280)).toBe(LABEL_MIN_ZOOM[0]);
        expect(cityLabelMinZoom(599)).toBe(NARROW_LABEL_ZOOM);
    });

    it('dessine le trait de gare dans l’encre du thème', () => {
        const image = tickImage('#25221e', { width: 2, height: 3 });
        expect(image.width).toBe(2);
        expect(image.height).toBe(3);
        expect(Array.from(image.data.slice(0, 4))).toEqual([0x25, 0x22, 0x1e, 255]);
        expect(image.data).toHaveLength(2 * 3 * 4);
    });
});

describe('fiche et recherche', () => {
    it('donne à la fiche latérale la ligne de l’axe cliqué', () => {
        expect(drawerLine({ line_id: 12, category: 'mixed' }, PLAN.lines, 1930)).toEqual({
            slug: 'rfn-420000',
            name: 'Ligne de Paris à Bordeaux',
            code: '420000',
            status: 'in_service',
            traffic_type: 'mixed',
            is_lgv: false,
            network: 'principal',
            heritage_kind: null,
            era: 1930,
            max_speed_kmh: 160,
            electrified: true,
            voltage_v: 1500,
        });
        expect(drawerLine({ line_id: 40, category: 'lgv' }, PLAN.lines, 2030)).toMatchObject({
            traffic_type: 'passenger',
            is_lgv: true,
        });
        expect(drawerLine({ category: 'heritage' }, PLAN.lines, 1970)).toMatchObject({
            slug: null,
            heritage_kind: 'tourist_train',
            traffic_type: 'unknown',
        });
    });

    it('retrouve une ligne par son slug et une gare sur le plan', () => {
        expect(lineIdOf('lgv-sud-est', PLAN.lines)).toBe(40);
        expect(lineIdOf('inconnue', PLAN.lines)).toBeNull();
        expect(lineIdOf(null, PLAN.lines)).toBeNull();
        expect(stationPosition(PLAN, 'paris-est')).toEqual([2.35, 48.85]);
        expect(stationPosition(PLAN, 'paris-nord')).toBeNull();
        expect(stationPosition(null, 'paris-est')).toBeNull();
    });

    it('construit la bulle d’une gare avec sa commune quand elle diffère du nom', () => {
        const html = stationPopupHtml(
            { name: 'Lyon-Perrache', city: 'Lyon 2e Arrondissement', department_code: '69' },
            '<a href="/stations/lyon-perrache">Fiche</a>',
        );
        expect(html).toContain('Lyon-Perrache</p>');
        expect(html).toContain('Lyon 2e Arrondissement (69)');
        expect(stationPopupHtml({ name: 'Nantes', city: 'Nantes' }, '')).not.toContain(
            'rail-popup-place',
        );
        expect(stationPopupHtml({ name: '<b>', city: null }, '')).toContain('&lt;b&gt;');
    });

    it('résume le plan pour les lecteurs d’écran, catégories de la plus longue à la plus courte', () => {
        expect(summaryParts(PLAN)).toEqual({
            total: 42230,
            stations: 6474,
            parts: [
                { key: 'mixed', km: 41000 },
                { key: 'freight', km: 1200 },
                { key: 'heritage', km: 30 },
            ],
        });
        expect(summaryParts(null)).toBeNull();
        expect(SCHEMA_CATEGORIES).toContain('heritage');
    });

    it('demande le plan de l’époque à l’API', () => {
        expect(schematicUrl(1930)).toBe('/api/v1/eras/1930/schematic.geojson');
    });

    it('nomme les boutons de la carte dans la langue de la page', () => {
        const locale = mapControlsLocale((key) => `«${key}»`, { 'Map.Title': 'Plan' });
        expect(locale['NavigationControl.ZoomIn']).toBe('«maps.controls.zoom_in»');
        expect(locale['Map.Title']).toBe('Plan');
    });
});

describe('adresse de la vue ferroviaire', () => {
    it('propose la vue après celles de la carte, seulement si le serveur l’active', () => {
        expect(mapViews(true)).toEqual([...VIEWS, 'schema']);
        expect(mapViews(false)).toBe(VIEWS);
    });

    it('lit ?view=schema avec son époque, ou l’ignore si la vue n’est pas proposée', () => {
        expect(readViewParams('?view=schema&era=1930', ERAS, mapViews(true))).toEqual({
            view: 'schema',
            era: 1930,
            compare: null,
        });
        expect(readViewParams('?view=schema', ERAS, mapViews(true))).toEqual({
            view: 'schema',
            era: null,
            compare: null,
        });
        expect(readViewParams('?view=schema&era=1930', ERAS)).toEqual({
            view: 'era',
            era: 1930,
            compare: null,
        });
    });

    it('écrit la vue et garde l’époque, sauf celle d’aujourd’hui', () => {
        const base = 'https://vff-project.org/maps?layer=accidents';
        expect(writeViewParams(base, { view: 'schema', era: 1930 }, 2030)).toBe(
            'https://vff-project.org/maps?layer=accidents&view=schema&era=1930',
        );
        expect(
            writeViewParams(`${base}&view=schema&era=1930`, { view: 'schema', era: 2030 }, 2030),
        ).toBe('https://vff-project.org/maps?layer=accidents&view=schema');
        expect(
            writeViewParams(`${base}&view=schema&era=1930`, { view: 'era', era: 1930 }, 2030),
        ).toBe('https://vff-project.org/maps?layer=accidents&era=1930');
    });
});
