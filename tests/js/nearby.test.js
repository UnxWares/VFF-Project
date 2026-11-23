import { describe, expect, it } from 'vitest';
import {
    formatDistance,
    historyFacts,
    historySentence,
    lineStateKey,
    mapHref,
    nearbyUrl,
    roundCoord,
    stationFacts,
} from '../../resources/js/lib/nearby.js';

describe('roundCoord et nearbyUrl', () => {
    it('arrondit la position à 0,001 degré avant tout envoi', () => {
        expect(roundCoord(45.833612)).toBe(45.834);
        expect(roundCoord(-2.80831)).toBe(-2.808);
        const url = nearbyUrl(45.833612, 1.261149, 5000, 'fr');
        expect(url).toContain('lat=45.834');
        expect(url).toContain('lng=1.261');
        expect(url).toContain('radius_m=5000');
        expect(url).toContain('summary=1');
        expect(url).not.toContain('45.8336');
    });
});

describe('mapHref', () => {
    it('centre la carte sur le lieu, avec un zoom selon le rayon', () => {
        expect(mapHref(45.8336, 1.2611, 1000)).toBe('/maps?center=1.261,45.834&zoom=14');
        expect(mapHref(48.2818, -2.8083, 10000)).toBe('/maps?center=-2.808,48.282&zoom=11');
    });
});

describe('formatDistance', () => {
    it('écrit des mètres sous le kilomètre et des kilomètres au-delà', () => {
        expect(formatDistance(447, 'fr')).toBe('450 m');
        expect(formatDistance(2431, 'fr')).toBe('2,4 km');
        expect(formatDistance(2431, 'en')).toBe('2.4 km');
    });
});

describe('lineStateKey', () => {
    it("donne l'état d'une ligne", () => {
        expect(lineStateKey({ status: 'in_service', traffic: 'mixed' })).toBe(
            'in_service_passenger',
        );
        expect(lineStateKey({ status: 'in_service', traffic: 'freight' })).toBe(
            'in_service_freight',
        );
        expect(lineStateKey({ status: 'in_service', traffic: 'mixed', heritage: 'tourist' })).toBe(
            'tourist',
        );
        expect(lineStateKey({ status: 'abandoned', traffic: 'unknown' })).toBe('abandoned');
        expect(lineStateKey({ status: 'dismantled' })).toBe('dismantled');
        expect(lineStateKey({ status: 'autre' })).toBe('unknown');
    });
});

describe('historyFacts et historySentence', () => {
    const write = (f) => `${f.key}:${f.year}`;

    it("range les faits dans l'ordre de la phrase", () => {
        const facts = historyFacts({ closed: 2017, opened: 1871, closed_passenger: 2006 });
        expect(facts.map((f) => f.key)).toEqual(['opened', 'closed_passenger', 'closed']);
    });

    it('écarte une fermeture partielle de la même année que la fermeture complète', () => {
        const facts = historyFacts({ opened: 1925, closed_passenger: 1937, closed: 1937 });
        expect(facts.map((f) => f.key)).toEqual(['opened', 'closed']);
    });

    it('écrit une phrase ou la phrase de repli', () => {
        expect(
            historySentence(
                historyFacts({ opened: 1869, closed_passenger: 1938 }),
                (f) =>
                    f.key === 'opened'
                        ? `ouverte en ${f.year}`
                        : `fermée aux voyageurs en ${f.year}`,
                'x',
            ),
        ).toBe('Ouverte en 1869, fermée aux voyageurs en 1938.');
        expect(historySentence(historyFacts({}), write, 'histoire non documentée')).toBe(
            'Histoire non documentée.',
        );
        expect(historyFacts(null)).toEqual([]);
    });
});

describe('faits de portion', () => {
    it("n'écrit jamais une dépose de portion comme celle de la ligne", () => {
        const facts = historyFacts({
            opened: 1861,
            sections: [
                { key: 'dismantled', year: 1962, from: 'Nexon', to: 'Périgueux' },
                { key: 'closed', year: 1990, from: null, to: null },
            ],
        });
        expect(facts).toEqual([
            { key: 'opened', year: 1861 },
            { key: 'dismantled', year: 1962, section: { from: 'Nexon', to: 'Périgueux' } },
            { key: 'closed', year: 1990, section: {} },
        ]);
        expect(facts.some((f) => f.key === 'dismantled' && !f.section)).toBe(false);
    });
});

describe('stationFacts', () => {
    it("lit l'ouverture et la fermeture d'une gare", () => {
        expect(stationFacts({ opened_year: 1872, closed_year: 2011 })).toEqual([
            { key: 'station_opened', year: 1872 },
            { key: 'station_closed', year: 2011 },
        ]);
        expect(stationFacts({ opened_year: null, closed_year: null })).toEqual([]);
    });
});
