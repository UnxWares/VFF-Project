// Fond de carte libre auto-hébergé (resources/js/lib/basemap.js, pmtilesProtocol.js) et son emploi hors
// ligne (offlineMap.svelte.js) : style sans ressource tierce ni pictogramme, orange réservé à VFF.
import { describe, expect, it, vi } from 'vitest';
import {
    BASEMAP_GLYPHS,
    BASEMAP_MAX_ZOOM,
    MAP_MAX_ZOOM,
    BASEMAP_SOURCE_ID,
    PAPER,
    applyBasemapTheme,
    basemapLang,
    basemapLayers,
    basemapStyle,
    pmtilesUrl,
    usesBasemap,
} from '$lib/basemap.js';
import { NEUTRAL_BACKGROUND, neutralMapStyle } from '$lib/offlineMap.svelte.js';
import { registerPmtilesProtocol } from '$lib/pmtilesProtocol.js';

const ORIGIN = 'https://vff-project.org';
const BASEMAP = {
    url: '/basemap/france-20261120.pmtiles',
    online: false,
    attribution: '<a href="https://protomaps.com">Protomaps</a> © OpenStreetMap',
};

/** Toutes les chaînes d'une valeur (couleurs, adresses, expressions), en profondeur. */
function strings(value) {
    if (typeof value === 'string') return [value];
    if (Array.isArray(value)) return value.flatMap(strings);
    if (value && typeof value === 'object') return Object.values(value).flatMap(strings);
    return [];
}

/** Teinte (degrés) et saturation d'une couleur #rrggbb. */
function hueSaturation(hex) {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;
    if (delta === 0) return { hue: 0, saturation: 0 };
    const saturation = delta / (1 - Math.abs(max + min - 1));
    let hue;
    if (max === r) hue = ((g - b) / delta) % 6;
    else if (max === g) hue = (b - r) / delta + 2;
    else hue = (r - g) / delta + 4;
    return { hue: (hue * 60 + 360) % 360, saturation };
}

describe('basemapStyle', () => {
    const style = basemapStyle({ ...BASEMAP, origin: ORIGIN });

    it('sur-zoome les tuiles du niveau 15 jusqu au niveau 19 sans demander de tuile plus fine', () => {
        expect(style.sources[BASEMAP_SOURCE_ID].maxzoom).toBe(BASEMAP_MAX_ZOOM);
        expect(BASEMAP_MAX_ZOOM).toBe(15);
        expect(MAP_MAX_ZOOM).toBe(19);
    });

    it('lit l archive de VFF par le protocole pmtiles://, avec le crédit OpenStreetMap', () => {
        expect(style.sources[BASEMAP_SOURCE_ID]).toEqual({
            type: 'vector',
            url: `pmtiles://${ORIGIN}/basemap/france-20261120.pmtiles`,
            maxzoom: BASEMAP_MAX_ZOOM,
            attribution: BASEMAP.attribution,
        });
    });

    it('prend ses polices chez VFF', () => {
        expect(style.glyphs).toBe(`${ORIGIN}${BASEMAP_GLYPHS}`);
        expect(style.glyphs).toContain('{fontstack}/{range}.pbf');
    });

    it('ne demande rien à un autre serveur', () => {
        const urls = strings(style).filter((s) => /^(?:https?:|pmtiles:)/.test(s));
        for (const url of urls) {
            expect(url.replace(/^pmtiles:\/\//, '').startsWith(ORIGIN), url).toBe(true);
        }
        expect(style.sprite).toBeUndefined();
    });

    it('ne dessine aucun pictogramme (aucune planche de sprites)', () => {
        for (const dark of [false, true]) {
            for (const layer of basemapLayers({ dark })) {
                const keys = Object.keys(layer.layout ?? {});
                expect(
                    keys.filter((key) => key.startsWith('icon-')),
                    layer.id,
                ).toEqual([]);
            }
            const ids = basemapLayers({ dark }).map((l) => l.id);
            expect(ids).not.toContain('pois');
            expect(ids).not.toContain('roads_shields');
            expect(ids).toContain('places_locality');
        }
    });

    it('réserve l orange aux tracés de VFF : aucune couleur orange vive dans le fond', () => {
        for (const dark of [false, true]) {
            const colours = strings(basemapLayers({ dark })).filter((s) =>
                /^#[0-9a-f]{6}$/i.test(s),
            );
            expect(colours.length).toBeGreaterThan(20);
            for (const colour of colours) {
                const { hue, saturation } = hueSaturation(colour);
                const orange = hue >= 15 && hue <= 45 && saturation > 0.6;
                expect(orange, colour).toBe(false);
            }
            expect(colours.map((c) => c.toLowerCase())).not.toContain('#ff6500');
        }
    });

    it('part du fond « papier » du site, dans chaque thème', () => {
        const earth = (dark) => basemapLayers({ dark }).find((l) => l.id === 'earth').paint;
        expect(earth(false)['fill-color']).toBe(PAPER.light);
        expect(earth(true)['fill-color']).toBe(PAPER.dark);
        expect(PAPER).toEqual(NEUTRAL_BACKGROUND);
    });

    it('écrit les noms dans la langue de la page, le français sinon', () => {
        expect(basemapLang('de')).toBe('de');
        expect(basemapLang('en-GB')).toBe('en');
        expect(basemapLang('it')).toBe('fr');
        expect(basemapLang(undefined)).toBe('fr');
        const label = basemapLayers({ lang: 'de' }).find((l) => l.id === 'places_locality');
        expect(JSON.stringify(label.layout['text-field'])).toContain('name:de');
    });

    it('fait une adresse absolue pour le protocole', () => {
        expect(pmtilesUrl('/basemap/a.pmtiles', ORIGIN)).toBe(
            `pmtiles://${ORIGIN}/basemap/a.pmtiles`,
        );
    });
});

describe('applyBasemapTheme', () => {
    it('change seulement les peintures des couches du fond présentes', () => {
        const present = new Set(['earth', 'water']);
        const map = {
            getLayer: (id) => (present.has(id) ? { id } : undefined),
            setPaintProperty: vi.fn(),
        };
        applyBasemapTheme(map, { dark: true });
        const touched = new Set(map.setPaintProperty.mock.calls.map(([id]) => id));
        expect([...touched].sort()).toEqual(['earth', 'water']);
        expect(map.setPaintProperty).toHaveBeenCalledWith('earth', 'fill-color', PAPER.dark);
    });
});

describe('usesBasemap', () => {
    it('sert hors ligne dès qu une archive existe, en ligne seulement sur option', () => {
        expect(usesBasemap(BASEMAP, true)).toBe(true);
        expect(usesBasemap(BASEMAP, false)).toBe(false);
        expect(usesBasemap({ ...BASEMAP, online: true }, false)).toBe(true);
        expect(usesBasemap(null, true)).toBe(false);
        expect(usesBasemap({ online: true }, false)).toBe(false);
    });
});

describe('neutralMapStyle avec le fond libre', () => {
    it('donne le fond libre de VFF quand la page en décrit un', () => {
        const style = neutralMapStyle({ dark: true, basemap: BASEMAP, lang: 'en' });
        expect(style.sources[BASEMAP_SOURCE_ID].url).toMatch(/^pmtiles:\/\/.+france-20261120/);
        expect(style.glyphs).toContain('/basemap/fonts/');
    });

    it('garde le fond uni sans archive', () => {
        const style = neutralMapStyle({ basemap: null });
        expect(style.sources).toEqual({});
        expect(style.layers).toHaveLength(1);
    });
});

describe('registerPmtilesProtocol', () => {
    it('enregistre le protocole une seule fois par instance de MapLibre', () => {
        const maplibre = { addProtocol: vi.fn() };
        expect(registerPmtilesProtocol(maplibre)).toBe(true);
        expect(registerPmtilesProtocol(maplibre)).toBe(true);
        expect(maplibre.addProtocol).toHaveBeenCalledOnce();
        expect(maplibre.addProtocol.mock.calls[0][0]).toBe('pmtiles');
        expect(typeof maplibre.addProtocol.mock.calls[0][1]).toBe('function');
    });

    it('ne fait rien sans addProtocol', () => {
        expect(registerPmtilesProtocol({})).toBe(false);
        expect(registerPmtilesProtocol(null)).toBe(false);
    });
});

describe('police grasse des étiquettes', () => {
    it('prend Medium sur les polices de VFF (pas de Bold) et Bold ailleurs', async () => {
        const { boldLabelFont } = await import('$lib/basemap.js');
        expect(
            boldLabelFont('https://vff-project.org/basemap/fonts/{fontstack}/{range}.pbf'),
        ).toEqual(['Noto Sans Medium']);
        expect(
            boldLabelFont('https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf'),
        ).toEqual(['Noto Sans Bold']);
        expect(boldLabelFont(undefined)).toEqual(['Noto Sans Bold']);
    });
});
