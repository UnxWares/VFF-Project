import { describe, expect, it } from 'vitest';
import {
    STATIONS_LOAD_ZOOM,
    STATIONS_MIN_ZOOM,
    noStations,
    stationsToLoad,
} from '$lib/mapStations.js';

const URL_2030 = '/api/v1/eras/2030/stations.geojson?v=1';
const URL_1930 = '/api/v1/eras/1930/stations.geojson?v=1';

describe('chargement différé des gares de la carte', () => {
    it('ne demande rien en vue de la France entière', () => {
        expect(stationsToLoad(4.2, URL_2030, null)).toBeNull();
        expect(stationsToLoad(STATIONS_LOAD_ZOOM - 0.01, URL_2030, null)).toBeNull();
    });

    it('demande le fichier un zoom avant que les gares ne se dessinent', () => {
        expect(STATIONS_LOAD_ZOOM).toBe(STATIONS_MIN_ZOOM - 1);
        expect(stationsToLoad(STATIONS_LOAD_ZOOM, URL_2030, null)).toBe(URL_2030);
        expect(stationsToLoad(12, URL_2030, null)).toBe(URL_2030);
    });

    it('ne recharge pas un fichier déjà posé, recharge celui d’une autre époque', () => {
        expect(stationsToLoad(9, URL_2030, URL_2030)).toBeNull();
        expect(stationsToLoad(9, URL_1930, URL_2030)).toBe(URL_1930);
        // Époque changée en vue d'ensemble : rien tant que la carte reste éloignée.
        expect(stationsToLoad(5, URL_1930, URL_2030)).toBeNull();
    });

    it('part d’une source vide, un objet neuf à chaque fois', () => {
        expect(noStations()).toEqual({ type: 'FeatureCollection', features: [] });
        expect(noStations()).not.toBe(noStations());
    });
});
