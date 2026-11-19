import { describe, expect, it } from 'vitest';
import { TRACE_FORMATS, defaultEra, traceUrl } from '$lib/traceDownload.js';

describe('defaultEra', () => {
    it('propose l’ère courante quand elle est exportable', () => {
        expect(defaultEra([1930, 1960, 2030], 2030)).toBe(2030);
    });

    it('propose la plus récente sinon', () => {
        expect(defaultEra([1900, 1930, 1960], 2030)).toBe(1960);
        expect(defaultEra([1930], null)).toBe(1930);
    });

    it('ne propose rien sans ère exportable', () => {
        expect(defaultEra([], 2030)).toBeNull();
    });
});

describe('traceUrl', () => {
    const [gpx, kml, geojson] = TRACE_FORMATS;

    it('désigne les trois formats de l’API dans l’ordre', () => {
        expect(TRACE_FORMATS.map((f) => f.key)).toEqual(['gpx', 'kml', 'geojson']);
        expect(TRACE_FORMATS.map((f) => f.label)).toEqual(['GPX', 'KML', 'GeoJSON']);
    });

    it('construit l’adresse de chaque fichier avec l’ère', () => {
        expect(traceUrl('rfn-447000', gpx, 1930)).toBe('/api/v1/lines/rfn-447000/gpx?era=1930');
        expect(traceUrl('rfn-447000', kml, 2030)).toBe('/api/v1/lines/rfn-447000/kml?era=2030');
        expect(traceUrl('rfn-447000', geojson, 1960)).toBe(
            '/api/v1/lines/rfn-447000/geojson?era=1960',
        );
    });

    it('encode le slug', () => {
        expect(traceUrl('a b/c', gpx, 1930)).toBe('/api/v1/lines/a%20b%2Fc/gpx?era=1930');
    });
});
