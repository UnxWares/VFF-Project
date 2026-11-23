import { describe, expect, it } from 'vitest';
import {
    VIEW_MAX_LAT,
    VIEW_MAX_LON,
    VIEW_MAX_ZOOM,
    VIEW_MIN_ZOOM,
    parseCenter,
    parseZoom,
    readMapView,
    writeMapView,
} from '$lib/mapView.js';

const BASE = 'https://vff-project.org/maps';

describe('constantes de la vue', () => {
    it('gardent les bornes du fond de carte', () => {
        expect([VIEW_MIN_ZOOM, VIEW_MAX_ZOOM, VIEW_MAX_LAT, VIEW_MAX_LON]).toEqual([
            0, 19, 85, 180,
        ]);
    });
});

describe('lecture : frontières et formes', () => {
    it('accepte les bornes exactes et refuse juste au-dessus', () => {
        expect(parseCenter('180,85')).toEqual([180, 85]);
        expect(parseCenter('-180,-85')).toEqual([-180, -85]);
        expect(parseCenter('180.0001,0')).toBeNull();
        expect(parseCenter('0,85.0001')).toBeNull();
        expect(parseCenter('-180.0001,0')).toBeNull();
        expect(parseCenter('0,-85.0001')).toBeNull();
    });

    it('lit chaque coordonnée dans son propre intervalle', () => {
        expect(parseCenter('100,10')).toEqual([100, 10]);
        expect(parseCenter('10,100')).toBeNull();
        expect(parseCenter('-100,-10')).toEqual([-100, -10]);
        expect(parseCenter('-10,-100')).toBeNull();
    });

    it('tolère les signes et les espaces autour des nombres, pas les autres formes', () => {
        expect(parseCenter(' +2.5 , -47 ')).toEqual([2.5, -47]);
        expect(parseCenter('0,0')).toEqual([0, 0]);
        for (const bad of [
            '.5,1',
            '5.,1',
            '1,',
            ',1',
            '1,2,',
            '1;2',
            '1 2',
            '0x10,1',
            'Infinity,1',
            '1,NaN',
            '--1,2',
            '1,2e1',
            '١,٢',
        ]) {
            expect(parseCenter(bad), bad).toBeNull();
        }
    });

    it('ne lit ni un nombre, ni un objet, ni un tableau', () => {
        for (const bad of [undefined, 5, {}, ['1', '2'], true]) {
            expect(parseCenter(bad)).toBeNull();
            expect(parseZoom(bad)).toBeNull();
        }
    });

    it('borne le zoom aux deux extrémités sans les dépasser', () => {
        expect(parseZoom('0')).toBe(0);
        expect(parseZoom('19')).toBe(19);
        expect(parseZoom('19.0001')).toBe(19);
        expect(parseZoom('18.9999')).toBe(18.9999);
        expect(parseZoom('-0.0001')).toBe(0);
        expect(parseZoom('0.0001')).toBe(0.0001);
        expect(parseZoom('+7')).toBe(7);
        expect(parseZoom(' 7 ')).toBe(7);
        expect(parseZoom('1e2')).toBeNull();
        expect(parseZoom('99999999999999999999')).toBe(19);
        expect(parseZoom('7,5')).toBeNull();
    });

    it('lit une chaîne de requête très longue ou répétée sans erreur', () => {
        const long = `?center=${'1'.repeat(10000)},2&zoom=${'9'.repeat(10000)}`;
        expect(readMapView(long)).toEqual({ center: null, zoom: 19 });
        expect(readMapView('?center=1,2&center=3,4&zoom=5&zoom=6')).toEqual({
            center: [1, 2],
            zoom: 5,
        });
        expect(readMapView('?center=%EF%BF%BD&zoom=%00')).toEqual({ center: null, zoom: null });
    });

    it("lit le centre et le zoom l'un sans l'autre", () => {
        expect(readMapView('?center=1,2')).toEqual({ center: [1, 2], zoom: null });
        expect(readMapView('?zoom=3')).toEqual({ center: null, zoom: 3 });
    });
});

describe("écriture : frontières, arrondis et formes de l'adresse", () => {
    it('accepte les bornes exactes du centre et refuse juste au-dessus', () => {
        expect(writeMapView(BASE, { center: [180, 85], zoom: 5 })).toBe(
            `${BASE}?center=180,85&zoom=5`,
        );
        expect(writeMapView(BASE, { center: [-180, -85], zoom: 5 })).toBe(
            `${BASE}?center=-180,-85&zoom=5`,
        );
        expect(writeMapView(BASE, { center: [180.00001, 0], zoom: 5 })).toBe(BASE);
        expect(writeMapView(BASE, { center: [0, 85.00001], zoom: 5 })).toBe(BASE);
        expect(writeMapView(BASE, { center: [0, -85.00001], zoom: 5 })).toBe(BASE);
        expect(writeMapView(BASE, { center: [-180.00001, 0], zoom: 5 })).toBe(BASE);
    });

    it('borne le zoom des deux côtés et garde un zoom nul', () => {
        expect(writeMapView(BASE, { center: [1, 2], zoom: 0 })).toBe(`${BASE}?center=1,2&zoom=0`);
        expect(writeMapView(BASE, { center: [1, 2], zoom: -4 })).toBe(`${BASE}?center=1,2&zoom=0`);
        expect(writeMapView(BASE, { center: [1, 2], zoom: 19 })).toBe(`${BASE}?center=1,2&zoom=19`);
        expect(writeMapView(BASE, { center: [1, 2], zoom: Infinity })).toBe(BASE);
        expect(writeMapView(BASE, { center: [1, 2], zoom: -Infinity })).toBe(BASE);
    });

    it('arrondit le centre à 5 décimales et le zoom à 2', () => {
        expect(writeMapView(BASE, { center: [1.234567, 2.000004], zoom: 3.004 })).toBe(
            `${BASE}?center=1.23457,2&zoom=3`,
        );
        expect(writeMapView(BASE, { center: [1.1, 2.2], zoom: 3.005 })).toMatch(/zoom=3(\.01)?$/);
        expect(writeMapView(BASE, { center: [0.999996, 0], zoom: 1.999 })).toBe(
            `${BASE}?center=1,0&zoom=2`,
        );
    });

    it('accepte un centre arrondi hors limite seulement après arrondi', () => {
        // 180.000004 arrondi à 5 décimales vaut 180 : écrit.
        expect(writeMapView(BASE, { center: [180.000004, 0], zoom: 5 })).toBe(
            `${BASE}?center=180,0&zoom=5`,
        );
    });

    it("retire la vue pour tout ce qui n'est pas un centre en tableau avec un zoom fini", () => {
        const old = `${BASE}?center=1,2&zoom=5&era=1930`;
        for (const view of [
            null,
            undefined,
            {},
            { center: null, zoom: 5 },
            { center: 'x', zoom: 5 },
            { center: [1, 2] },
            { center: [1, 2], zoom: '5' },
        ]) {
            expect(writeMapView(old, view), JSON.stringify(view)).toBe(`${BASE}?era=1930`);
        }
    });

    it('garde le fragment et ne remplace la virgule que dans center', () => {
        expect(writeMapView(`${BASE}?q=a%2Cb#carte`, { center: [1, 2], zoom: 3 })).toBe(
            `${BASE}?q=a%2Cb&center=1,2&zoom=3#carte`,
        );
        expect(writeMapView(`${BASE}#carte`, { center: [1, 2], zoom: 3 })).toBe(
            `${BASE}?center=1,2&zoom=3#carte`,
        );
    });

    it('écrit toujours une adresse que la lecture comprend, aux bornes comme ailleurs', () => {
        for (const view of [
            { center: [180, 85], zoom: 19 },
            { center: [-180, -85], zoom: 0 },
            { center: [0, 0], zoom: 0.5 },
            { center: [-0.66733, 48.5866], zoom: 17.25 },
        ]) {
            expect(readMapView(new URL(writeMapView(BASE, view)).search)).toEqual(view);
        }
    });
});
