import { describe, expect, it } from 'vitest';
import { parseCenter, parseZoom, readMapView, writeMapView } from '$lib/mapView.js';

const BASE = 'https://vff-project.org/maps';

describe("lecture de la vue dans l'adresse", () => {
    it('lit center et zoom au format de /embed/maps', () => {
        expect(readMapView('?era=1930&center=-0.66733,48.5866&zoom=17')).toEqual({
            center: [-0.66733, 48.5866],
            zoom: 17,
        });
    });

    it("rend null quand rien n'est donné", () => {
        expect(readMapView('')).toEqual({ center: null, zoom: null });
    });

    it('ignore un centre mal formé ou hors des limites', () => {
        for (const bad of ['', 'abc', '2.5', '2.5,47,3', 'a,b', '200,10', '10,86', '1e3,4', ',']) {
            expect(parseCenter(bad)).toBeNull();
        }
        expect(parseCenter(null)).toBeNull();
        expect(parseCenter('180,-85')).toEqual([180, -85]);
    });

    it('borne le zoom entre 0 et 19 et ignore le reste', () => {
        expect(parseZoom('25')).toBe(19);
        expect(parseZoom('-3')).toBe(0);
        expect(parseZoom('5.5')).toBe(5.5);
        expect(parseZoom('NaN')).toBeNull();
        expect(parseZoom('')).toBeNull();
        expect(parseZoom(null)).toBeNull();
    });
});

describe("écriture de la vue dans l'adresse", () => {
    it('écrit center et zoom arrondis et garde les autres paramètres', () => {
        const href = writeMapView(`${BASE}?era=1930&layer=accidents`, {
            center: [2.3522219, 48.8566141],
            zoom: 12.3456,
        });
        expect(href).toBe(`${BASE}?era=1930&layer=accidents&center=2.35222,48.85661&zoom=12.35`);
    });

    it('remplace une vue précédente sans doublon', () => {
        const first = writeMapView(BASE, { center: [1, 2], zoom: 5 });
        const second = writeMapView(first, { center: [3, 4], zoom: 6 });
        expect(second).toBe(`${BASE}?center=3,4&zoom=6`);
    });

    it('borne le zoom et refuse un centre hors limites', () => {
        expect(writeMapView(BASE, { center: [1, 2], zoom: 30 })).toBe(`${BASE}?center=1,2&zoom=19`);
        expect(writeMapView(BASE, { center: [1, 95], zoom: 5 })).toBe(BASE);
        expect(writeMapView(BASE, { center: [1, 2], zoom: Number.NaN })).toBe(BASE);
    });

    it('retire la vue avec null', () => {
        expect(writeMapView(`${BASE}?center=1,2&zoom=5&era=1930`, null)).toBe(`${BASE}?era=1930`);
    });

    it("relit exactement ce qu'il a écrit", () => {
        const view = { center: [-1.55528, 47.20506], zoom: 15.25 };
        const href = writeMapView(BASE, view);
        expect(readMapView(new URL(href).search)).toEqual(view);
    });
});
