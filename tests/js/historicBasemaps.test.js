import { describe, expect, it, vi } from 'vitest';
import {
    HISTORIC_BASEMAPS,
    HISTORIC_KEYS,
    HISTORIC_LAYER_ID,
    HISTORIC_OVERLAY_DEFS,
    HISTORIC_SOURCE_ID,
    applyHistoricBasemap,
    findHistoricBasemap,
    historicAttribution,
    historicTileUrl,
    styleReady,
} from '$lib/historicBasemaps.js';

/** Carte MapLibre factice : sources, couches ordonnées, propriétés de rendu. */
function fakeMap({ layers = [], loaded = true } = {}) {
    const sources = new Map();
    const style = { layers: layers.map((id) => ({ id })) };
    const handlers = {};
    const map = {
        style: { _loaded: loaded },
        isStyleLoaded: () => loaded,
        getStyle: () => style,
        getSource: (id) => sources.get(id),
        addSource: vi.fn((id, def) => sources.set(id, def)),
        removeSource: vi.fn((id) => sources.delete(id)),
        getLayer: (id) => style.layers.find((l) => l.id === id),
        addLayer: vi.fn((layer, beforeId) => {
            const index = beforeId ? style.layers.findIndex((l) => l.id === beforeId) : -1;
            if (index < 0) style.layers.push(layer);
            else style.layers.splice(index, 0, layer);
        }),
        removeLayer: vi.fn((id) => {
            style.layers = style.layers.filter((l) => l.id !== id);
        }),
        setPaintProperty: vi.fn((id, prop, value) => {
            const layer = style.layers.find((l) => l.id === id);
            layer.paint = { ...layer.paint, [prop]: value };
        }),
        once: vi.fn((event, fn) => {
            handlers[event] = fn;
        }),
        fire: (event) => handlers[event]?.(),
        sources,
        layerIds: () => style.layers.map((l) => l.id),
    };
    return map;
}

describe('catalogue des fonds historiques IGN', () => {
    it('clés uniques et stables, toutes IGN', () => {
        expect(new Set(HISTORIC_KEYS).size).toBe(HISTORIC_KEYS.length);
        expect(HISTORIC_KEYS).toEqual(HISTORIC_BASEMAPS.map((b) => b.key));
        for (const key of HISTORIC_KEYS) expect(key).toMatch(/^ign-[a-z0-9-]+$/);
    });

    it("le SCAN 25 (non libre) n'est jamais proposé", () => {
        for (const def of HISTORIC_BASEMAPS) expect(def.layer).not.toMatch(/SCAN25/i);
    });

    it('classées de la plus ancienne à la plus récente, périodes cohérentes', () => {
        for (const def of HISTORIC_BASEMAPS)
            expect(def.period[0]).toBeLessThanOrEqual(def.period[1]);
        const starts = HISTORIC_BASEMAPS.map((b) => b.period[0]);
        expect(starts).toEqual([...starts].sort((a, b) => a - b));
    });

    it('zooms, formats et emprises valides', () => {
        for (const def of HISTORIC_BASEMAPS) {
            expect(def.minzoom).toBeGreaterThanOrEqual(0);
            expect(def.minzoom).toBeLessThan(def.maxzoom);
            expect(def.maxzoom).toBeLessThanOrEqual(22);
            expect(['image/png', 'image/jpeg']).toContain(def.format);
            expect(def.style).toBeTruthy();
            expect(def.labelKey).toMatch(/^editor\.basemaps\./);
            if (def.bounds) {
                const [w, s, e, n] = def.bounds;
                expect(w).toBeLessThan(e);
                expect(s).toBeLessThan(n);
                expect(
                    Math.abs(w) <= 180 &&
                        Math.abs(e) <= 180 &&
                        Math.abs(s) <= 90 &&
                        Math.abs(n) <= 90,
                ).toBe(true);
                // L'emprise couvre la France métropolitaine (Paris).
                expect(w < 2.35 && e > 2.35 && s < 48.85 && n > 48.85).toBe(true);
            }
        }
    });

    it('findHistoricBasemap : définition par clé, null pour « none » ou une clé inconnue', () => {
        expect(findHistoricBasemap('ign-cassini')?.layer).toBe(
            'BNF-IGNF_GEOGRAPHICALGRIDSYSTEMS.CASSINI',
        );
        expect(findHistoricBasemap('none')).toBeNull();
        expect(findHistoricBasemap('ign-scan25')).toBeNull();
        expect(findHistoricBasemap(undefined)).toBeNull();
    });
});

describe('URL des tuiles WMTS et attribution', () => {
    it('requête GetTile Géoplateforme en Pseudo-Mercator, gabarits {z}/{y}/{x} MapLibre', () => {
        const def = findHistoricBasemap('ign-etatmajor40');
        const url = historicTileUrl(def);
        expect(
            url.startsWith('https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0'),
        ).toBe(true);
        expect(url).toContain('LAYER=GEOGRAPHICALGRIDSYSTEMS.ETATMAJOR40');
        expect(url).toContain('&STYLE=normal');
        expect(url).toContain('&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}');
        expect(url.endsWith('&FORMAT=image%2Fjpeg')).toBe(true);
    });

    it("chaque URL ne contient qu'un jeu de gabarits et aucun espace", () => {
        for (const def of HISTORIC_BASEMAPS) {
            const url = historicTileUrl(def);
            expect(url.match(/\{z\}/g)).toHaveLength(1);
            expect(url).not.toMatch(/\s/);
        }
    });

    it('mention de source obligatoire (Licence Ouverte), BnF pour Cassini', () => {
        expect(historicAttribution(findHistoricBasemap('ign-cassini'))).toBe(
            'Source : IGN / BnF - Carte de Cassini (Licence Ouverte 2.0)',
        );
        for (const def of HISTORIC_BASEMAPS) {
            expect(historicAttribution(def)).toMatch(/^Source : IGN/);
            expect(historicAttribution(def)).toContain('Licence Ouverte 2.0');
        }
    });

    it('HISTORIC_OVERLAY_DEFS : même URL, attribution, zooms et emprise que le catalogue', () => {
        expect(Object.keys(HISTORIC_OVERLAY_DEFS)).toEqual(HISTORIC_KEYS);
        for (const def of HISTORIC_BASEMAPS) {
            expect(HISTORIC_OVERLAY_DEFS[def.key]).toEqual({
                tiles: [historicTileUrl(def)],
                attribution: historicAttribution(def),
                minzoom: def.minzoom,
                maxzoom: def.maxzoom,
                bounds: def.bounds,
            });
        }
    });
});

describe('applyHistoricBasemap', () => {
    it('sans carte : ne fait rien', () => {
        expect(() => applyHistoricBasemap(null, 'ign-cassini')).not.toThrow();
    });

    it('ajoute le fond raster juste sous la première couche de données VFF', () => {
        const map = fakeMap({
            layers: ['background', 'osm', 'vff-lines', 'ref-trace', 'vff-stations'],
        });

        applyHistoricBasemap(map, 'ign-cassini');

        expect(map.layerIds()).toEqual([
            'background',
            'osm',
            HISTORIC_LAYER_ID,
            'vff-lines',
            'ref-trace',
            'vff-stations',
        ]);
        const def = findHistoricBasemap('ign-cassini');
        expect(map.sources.get(HISTORIC_SOURCE_ID)).toEqual({
            type: 'raster',
            tiles: [historicTileUrl(def)],
            tileSize: 256,
            minzoom: def.minzoom,
            maxzoom: def.maxzoom,
            bounds: def.bounds,
            attribution: historicAttribution(def),
        });
        const layer = map.getLayer(HISTORIC_LAYER_ID);
        expect(layer).toMatchObject({
            type: 'raster',
            source: HISTORIC_SOURCE_ID,
            metadata: { 'vff:key': 'ign-cassini' },
        });
        expect(layer.paint['raster-opacity']).toBe(0.8);
    });

    it('fond sans emprise déclarée : pas de clé bounds dans la source', () => {
        const map = fakeMap({ layers: ['osm'] });
        applyHistoricBasemap(map, 'ign-scan50-1950');
        expect(map.sources.get(HISTORIC_SOURCE_ID)).not.toHaveProperty('bounds');
        // Aucune couche de données : le fond va au-dessus de tout.
        expect(map.layerIds()).toEqual(['osm', HISTORIC_LAYER_ID]);
    });

    it("idempotent : même fond rappelé = seule l'opacité change", () => {
        const map = fakeMap({ layers: ['osm', 'vff-lines'] });
        applyHistoricBasemap(map, 'ign-etatmajor40', 0.8);
        applyHistoricBasemap(map, 'ign-etatmajor40', 0.5);

        expect(map.addSource).toHaveBeenCalledTimes(1);
        expect(map.addLayer).toHaveBeenCalledTimes(1);
        expect(map.setPaintProperty).toHaveBeenCalledWith(HISTORIC_LAYER_ID, 'raster-opacity', 0.5);
        expect(map.layerIds()).toEqual(['osm', HISTORIC_LAYER_ID, 'vff-lines']);
    });

    it("changement de fond : l'ancien est retiré, le nouveau reste sous les tracés", () => {
        const map = fakeMap({ layers: ['osm', 'vff-lines'] });
        applyHistoricBasemap(map, 'ign-cassini');
        applyHistoricBasemap(map, 'ign-ortho-1950-1965');

        expect(map.removeLayer).toHaveBeenCalledWith(HISTORIC_LAYER_ID);
        expect(map.removeSource).toHaveBeenCalledWith(HISTORIC_SOURCE_ID);
        expect(map.layerIds()).toEqual(['osm', HISTORIC_LAYER_ID, 'vff-lines']);
        expect(map.getLayer(HISTORIC_LAYER_ID).metadata['vff:key']).toBe('ign-ortho-1950-1965');
        expect(map.sources.get(HISTORIC_SOURCE_ID).tiles[0]).toContain(
            'ORTHOIMAGERY.ORTHOPHOTOS.1950-1965',
        );
    });

    it("« none » retire le fond (et ne plante pas s'il n'y en a pas)", () => {
        const map = fakeMap({ layers: ['osm', 'vff-lines'] });
        applyHistoricBasemap(map, 'none');
        expect(map.removeLayer).not.toHaveBeenCalled();

        applyHistoricBasemap(map, 'ign-cassini');
        applyHistoricBasemap(map, 'none');
        expect(map.layerIds()).toEqual(['osm', 'vff-lines']);
        expect(map.sources.has(HISTORIC_SOURCE_ID)).toBe(false);
    });

    it('style pas encore chargé : appliqué au chargement', () => {
        const map = fakeMap({ layers: ['osm', 'vff-lines'], loaded: false });
        applyHistoricBasemap(map, 'ign-cassini');
        expect(map.addLayer).not.toHaveBeenCalled();
        expect(map.once).toHaveBeenCalledWith('load', expect.any(Function));

        map.fire('load');
        expect(map.layerIds()).toEqual(['osm', HISTORIC_LAYER_ID, 'vff-lines']);
    });

    it('styleReady : style chargé, ou isStyleLoaded(), sinon faux', () => {
        expect(styleReady({ style: { _loaded: true }, isStyleLoaded: () => false })).toBe(true);
        expect(styleReady({ style: { _loaded: false }, isStyleLoaded: () => true })).toBe(true);
        expect(styleReady({ style: {} })).toBe(false);
        expect(styleReady(null)).toBe(false);
    });
});
