import { describe, expect, it, vi } from 'vitest';
import {
    ACCIDENT_SOURCE_ID,
    accidentLayers,
    accidentPopupHtml,
    accidentYearFilter,
    accidentYears,
    countAccidents,
    fetchAccidents,
    isApproximate,
} from '$lib/accidentMap.js';
import { colorOf, compile, matches, spec } from './support/maplibre.js';

/** Traduction factice : la clé, et le nombre quand il y en a un. */
const t = (key, opts = {}) => (opts.count !== undefined ? `${key}:${opts.count}` : key);

const point = (year, props = {}) => ({
    type: 'Feature',
    geometry: { type: 'Point', coordinates: [2.3, 48.6] },
    properties: { year, ...props },
});
const collection = {
    type: 'FeatureCollection',
    features: [1842, 1891, 1950, 2013].map((y) => point(y)),
};

describe('accidentYears', () => {
    it('une époque : tous les accidents jusqu’à l’année de l’ère', () => {
        expect(accidentYears('era', 1950, null)).toEqual({ from: null, to: 1950 });
        expect(accidentYears('era', 2030, 1980)).toEqual({ from: null, to: 2030 });
    });

    it('comparaison : les accidents survenus entre les deux ères, dans n’importe quel ordre', () => {
        expect(accidentYears('compare', 1930, 1980)).toEqual({ from: 1931, to: 1980 });
        expect(accidentYears('compare', 1980, 1930)).toEqual({ from: 1931, to: 1980 });
    });

    it('toutes les époques, ou ère inconnue : aucune borne', () => {
        expect(accidentYears('stack', 1950, null)).toEqual({ from: null, to: null });
        expect(accidentYears('era', null, null)).toEqual({ from: null, to: null });
    });
});

describe('countAccidents', () => {
    it('compte les accidents compris dans les années, bornes incluses', () => {
        expect(countAccidents(collection, { from: null, to: 1950 })).toBe(3);
        expect(countAccidents(collection, { from: 1891, to: 1950 })).toBe(2);
        expect(countAccidents(collection, { from: null, to: null })).toBe(4);
        expect(countAccidents(null, { from: null, to: null })).toBe(0);
    });
});

describe('accidentYearFilter', () => {
    it('traduit les bornes en filtre MapLibre', () => {
        expect(accidentYearFilter({ from: null, to: 1950 })).toEqual([
            'all',
            ['<=', ['get', 'year'], 1950],
        ]);
        expect(accidentYearFilter({ from: 1931, to: 1980 })).toEqual([
            'all',
            ['>=', ['get', 'year'], 1931],
            ['<=', ['get', 'year'], 1980],
        ]);
        expect(accidentYearFilter({ from: null, to: null })).toEqual(['has', 'year']);
    });
});

describe('accidentLayers', () => {
    it('expressions valides pour MapLibre ; zone pâle pour la commune seule, point pour tous', () => {
        const [area, points] = accidentLayers();
        expect([area.id, points.id]).toEqual(['vff-accidents-area', 'vff-accidents-points']);
        expect(area.source).toBe(ACCIDENT_SOURCE_ID);
        for (const layer of [area, points]) {
            for (const [property, value] of Object.entries(layer.paint)) {
                expect(() => compile(value, spec('paint_circle', property))).not.toThrow();
            }
        }
        expect(matches(area.filter, { precision: 'commune' })).toBe(true);
        expect(matches(area.filter, { precision: 'exact' })).toBe(false);
        expect(points.filter).toBeUndefined();
        expect(matches(accidentYearFilter({ from: 1931, to: 1980 }), { year: 1950 })).toBe(true);
        expect(matches(accidentYearFilter({ from: 1931, to: 1980 }), { year: 1930 })).toBe(false);
        expect(matches(accidentYearFilter({ from: null, to: null }), { year: 1842 })).toBe(true);
    });

    it('point plein pour un lieu connu, anneau pour la commune seule', () => {
        const fill = accidentLayers()[1].paint['circle-color'];
        const circle = spec('paint_circle', 'circle-color');
        expect(colorOf(fill, { precision: 'exact' }, circle)).toBe('#334155');
        expect(colorOf(fill, { precision: 'commune' }, circle)).toBe('#ffffff');
    });

    it('n’emploie aucun rouge : une seule teinte neutre', () => {
        const colours = JSON.stringify(accidentLayers()).match(/#[0-9a-f]{6}/gi);
        expect(new Set(colours)).toEqual(new Set(['#334155', '#ffffff']));
    });
});

describe('isApproximate', () => {
    it('seule la commune est un lieu approché', () => {
        expect(isApproximate('commune')).toBe(true);
        for (const p of ['exact', 'level_crossing', 'station', null])
            expect(isApproximate(p)).toBe(false);
    });
});

describe('accidentPopupHtml', () => {
    const base = {
        slug: '2013-07-12-bretigny-sur-orge',
        date: '2013-07-12',
        date_precision: 'day',
        kind: 'derailment',
        place: 'Brétigny-sur-Orge',
        department: '91',
        precision: 'exact',
        deaths: 7,
        injuries: 192,
        to_review: false,
    };
    const link = '<a class="rail-popup-cta" href="/accidents/x">Fiche</a>';

    it('lieu, date, nature, bilan et lien', () => {
        const html = accidentPopupHtml(base, t, 'fr', link);
        expect(html).toContain('<p class="rail-popup-title">Brétigny-sur-Orge (91)</p>');
        expect(html).toContain('<li>12 juillet 2013</li>');
        expect(html).toContain('<li>accidents.kinds.derailment</li>');
        expect(html).toContain('<li>accidents.deaths:7, accidents.injuries:192</li>');
        expect(html).toContain(link);
        expect(html).not.toContain('rail-popup-note');
    });

    it('signale un lieu approché et une fiche à vérifier', () => {
        const html = accidentPopupHtml(
            { ...base, precision: 'commune', to_review: 'true', injuries: 'null' },
            t,
            'fr',
            '',
        );
        expect(html).toContain('accidents.location_precision.commune');
        expect(html).toContain('accidents.index.review_badge');
        expect(html).toContain('<li>accidents.deaths:7</li>');
    });

    it('échappe le texte des données', () => {
        const html = accidentPopupHtml(
            { ...base, place: '<b>X</b>', department: null },
            t,
            'fr',
            '',
        );
        expect(html).toContain('&lt;b&gt;X&lt;/b&gt;');
        expect(html).not.toContain('<b>');
    });
});

describe('fetchAccidents', () => {
    it('demande le GeoJSON des accidents et le rend décodé', async () => {
        const collection = { type: 'FeatureCollection', features: [point(1950)] };
        const fetcher = vi.fn().mockResolvedValue({ ok: true, json: async () => collection });
        await expect(fetchAccidents(fetcher)).resolves.toEqual(collection);
        expect(fetcher).toHaveBeenCalledWith('/api/v1/accidents.geojson', {
            headers: { Accept: 'application/json' },
        });
    });

    it('lève une erreur quand la réponse n’est pas bonne', async () => {
        const fetcher = vi.fn().mockResolvedValue({ ok: false, status: 503 });
        await expect(fetchAccidents(fetcher)).rejects.toThrow('HTTP 503');
    });
});
