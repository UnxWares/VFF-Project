// Fond de plan commun des cartes (resources/js/lib/mapStyle.svelte.js) : la carte /maps et les petites
// cartes des fiches prennent le fond libre de VFF en ligne seulement avec l'option `maps.basemap.online`,
// et gardent sinon leur fond habituel (tuiles OpenStreetMap ou MapTiler).
import { readFileSync } from 'node:fs';
import { describe, expect, it, vi } from 'vitest';

const BASEMAP = {
    url: '/basemap/france-20261120.pmtiles',
    online: false,
    attribution:
        '<a href="https://protomaps.com">Protomaps</a> <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap</a>',
};

// Propriété partagée `basemap` de la page courante, changée par les tests de pageMapStyle.
const pageState = vi.hoisted(() => ({ props: {} }));
vi.mock('@inertiajs/svelte', () => ({ page: pageState }));

const { BASEMAP_GLYPHS, BASEMAP_SOURCE_ID, PAPER } = await import('$lib/basemap.js');
const { MAP_GLYPHS, NEUTRAL_LAYER_ID } = await import('$lib/offlineMap.svelte.js');
const { OSM_ATTRIBUTION, OSM_LAYER_ID, OSM_TILES, mapStyle, pageMapStyle } =
    await import('$lib/mapStyle.svelte.js');

/** Couleur de la couche `earth` du fond libre (palette claire ou sombre). */
const earth = (style) => style.layers.find((l) => l.id === 'earth').paint['fill-color'];

describe('mapStyle en ligne', () => {
    it('garde les tuiles OpenStreetMap tant que l option est désactivée', () => {
        const { style, onBasemap } = mapStyle({ offline: false, basemap: BASEMAP });
        expect(onBasemap).toBe(false);
        expect(style.sources['osm-raster']).toMatchObject({
            type: 'raster',
            tiles: [OSM_TILES],
            maxzoom: 19,
            attribution: OSM_ATTRIBUTION,
        });
        expect(style.layers).toEqual([{ id: OSM_LAYER_ID, type: 'raster', source: 'osm-raster' }]);
        expect(style.glyphs).toBe(MAP_GLYPHS);
    });

    it('prend le fond libre de VFF avec l option, crédit OpenStreetMap et polices de VFF', () => {
        const basemap = { ...BASEMAP, online: true };
        const { style, onBasemap } = mapStyle({ offline: false, basemap, lang: 'de' });
        expect(onBasemap).toBe(true);
        expect(style.sources[BASEMAP_SOURCE_ID].url).toMatch(/^pmtiles:\/\/.*france-20261120/);
        expect(style.sources[BASEMAP_SOURCE_ID].attribution).toBe(BASEMAP.attribution);
        expect(style.sources[BASEMAP_SOURCE_ID].attribution).toContain(
            'openstreetmap.org/copyright',
        );
        expect(style.glyphs).toContain(BASEMAP_GLYPHS);
        expect(JSON.stringify(style.layers)).toContain('name:de');
    });

    it('suit le thème clair ou sombre du site', () => {
        const basemap = { ...BASEMAP, online: true };
        expect(earth(mapStyle({ offline: false, basemap, dark: false }).style)).toBe(PAPER.light);
        expect(earth(mapStyle({ offline: false, basemap, dark: true }).style)).toBe(PAPER.dark);
    });

    it('passe avant MapTiler, qui reste le fond habituel quand une clé est posée', () => {
        const online = mapStyle({
            offline: false,
            basemap: { ...BASEMAP, online: true },
            maptilerKey: 'k',
        });
        expect(online.onBasemap).toBe(true);
        const usual = mapStyle({ offline: false, basemap: BASEMAP, maptilerKey: 'k' });
        expect(usual.style).toBe('https://api.maptiler.com/maps/basic-v2/style.json?key=k');
        expect(usual.onBasemap).toBe(false);
    });

    it('garde le fond habituel sans protocole pmtiles:// ou sans archive', () => {
        const basemap = { ...BASEMAP, online: true };
        expect(mapStyle({ offline: false, basemap, pmtiles: false }).onBasemap).toBe(false);
        expect(
            mapStyle({ offline: false, basemap: null }).style.sources['osm-raster'],
        ).toBeDefined();
    });

    it('applique la peinture demandée aux tuiles OpenStreetMap', () => {
        const osmPaint = { 'raster-saturation': -0.6 };
        const { style } = mapStyle({ offline: false, basemap: null, osmPaint });
        expect(style.layers[0].paint).toEqual(osmPaint);
    });
});

describe('mapStyle hors ligne', () => {
    it('dessine le fond libre (zones déjà vues) quand une archive est servie, option ou non', () => {
        const { style, onBasemap } = mapStyle({ offline: true, basemap: BASEMAP, dark: true });
        expect(onBasemap).toBe(true);
        expect(earth(style)).toBe(PAPER.dark);
    });

    it('reste uni sans archive ou sans protocole, sans aucune ressource d un tiers', () => {
        for (const options of [{ basemap: null }, { basemap: BASEMAP, pmtiles: false }]) {
            const { style, onBasemap } = mapStyle({ offline: true, ...options });
            expect(onBasemap).toBe(false);
            expect(style.sources).toEqual({});
            expect(style.layers[0].id).toBe(NEUTRAL_LAYER_ID);
        }
    });
});

describe('pageMapStyle', () => {
    it('lit la propriété partagée basemap de la page', () => {
        pageState.props = { basemap: { ...BASEMAP, online: true } };
        expect(pageMapStyle(false).onBasemap).toBe(true);
        pageState.props = { basemap: BASEMAP };
        expect(pageMapStyle(false).onBasemap).toBe(false);
        pageState.props = { basemap: null };
        expect(pageMapStyle(true).style.layers[0].id).toBe(NEUTRAL_LAYER_ID);
    });
});

describe('petites cartes des fiches', () => {
    const components = [
        'Lines/LinePhaseMap',
        'Stations/StationMiniMap',
        'Communes/CommuneMap',
        'Accidents/AccidentMiniMap',
        'Heritage/HeritageMap',
        'Network/TouristMap',
        'Materials/MaterialLinesMap',
        'Map/RailMap',
    ];

    it.each(components)('%s prend son fond de la fabrique commune', (name) => {
        const source = readFileSync(
            new URL(`../../resources/js/Components/${name}.svelte`, import.meta.url),
            'utf8',
        );
        expect(source).toContain('pageMapStyle(offline');
        // Plus aucun style de fond écrit à la main dans le composant.
        expect(source).not.toContain('tile.openstreetmap.org');
        expect(source).not.toContain('api.maptiler.com');
        expect(source).not.toContain('neutralMapStyle');
        if (name !== 'Map/RailMap') {
            expect(source).toContain('pmtiles: hasPmtilesProtocol()');
            expect(source).toContain('followBasemapTheme(');
        }
    });
});
