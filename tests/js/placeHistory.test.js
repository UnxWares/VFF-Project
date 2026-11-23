import { describe, expect, it, vi } from 'vitest';
import {
    chronology,
    cellCenter,
    createLongPress,
    hasWithheld,
    lineDistance,
    lineKm,
    pastEraFor,
    placeKey,
    placeUrl,
    switchTarget,
    validPoint,
    yearParam,
} from '$lib/placeHistory.js';

describe('case de grille', () => {
    it('ramène un point au centre de sa case de 0,001 degré, comme le serveur', () => {
        expect(cellCenter(48.85301)).toBe(48.8535);
        expect(cellCenter(48.85349)).toBe(48.8535);
        expect(cellCenter(2.24001)).toBe(2.2405);
        expect(cellCenter(-4.4901)).toBe(-4.4905);
    });

    it('donne la même clé de cache aux points d une même case, pas à des époques ou langues différentes', () => {
        const a = placeKey({ lng: 2.24001, lat: 48.85301 }, 1930, 'fr');
        expect(placeKey({ lng: 2.24049, lat: 48.85349 }, 1930, 'fr')).toBe(a);
        expect(placeKey({ lng: 2.24001, lat: 48.85301 }, 1960, 'fr')).not.toBe(a);
        expect(placeKey({ lng: 2.24001, lat: 48.85301 }, 1930, 'en')).not.toBe(a);
    });
});

describe('adresse de l appel', () => {
    it('envoie le centre de la case, l année de l ère passée et la langue', () => {
        const url = placeUrl({ lng: -4.49, lat: 48.39 }, 1930, 2030, 'de', 'abc');
        const query = new URL(url, 'https://vff.test').searchParams;
        expect(url.startsWith('/api/v1/what-was-here?')).toBe(true);
        expect(query.get('lat')).toBe('48.3905');
        expect(query.get('lng')).toBe('-4.4895');
        expect(query.get('year')).toBe('1930');
        expect(query.get('lang')).toBe('de');
        expect(query.get('v')).toBe('abc');
    });

    it('ne donne pas d année pour aujourd hui', () => {
        expect(yearParam(2030, 2030)).toBeNull();
        expect(
            new URL(
                placeUrl({ lng: 2, lat: 48 }, 2030, 2030, 'fr'),
                'https://x.test',
            ).searchParams.has('year'),
        ).toBe(false);
    });
});

describe('point', () => {
    it('refuse ce qui n est pas un lieu', () => {
        expect(validPoint(null)).toBeNull();
        expect(validPoint({ lng: 'x', lat: 48 })).toBeNull();
        expect(validPoint({ lng: 2, lat: 95 })).toBeNull();
        expect(validPoint({ lng: '2.5', lat: '48.1' })).toEqual({ lng: 2.5, lat: 48.1 });
    });
});

describe('droits des tracés', () => {
    it('ne donne pas de distance pour un tracé réservé et marque le PK comme approché', () => {
        const withheld = { geometry_withheld: true, distance_m: null, pk_m: 12300 };
        expect(lineDistance(withheld)).toBeNull();
        expect(lineKm(withheld)).toEqual({ text: '12+300', approximate: true });
        const open = { geometry_withheld: false, distance_m: 17, pk_m: 12345 };
        expect(lineDistance(open)).toBe(17);
        expect(lineKm(open)).toEqual({ text: '12+345', approximate: false });
        expect(lineKm({ pk_m: null })).toBeNull();
        expect(hasWithheld([open, withheld])).toBe(true);
        expect(hasWithheld([open])).toBe(false);
    });

    it('ne montre jamais la distance même si le serveur en envoyait une pour un tracé réservé', () => {
        expect(lineDistance({ geometry_withheld: true, distance_m: 40 })).toBeNull();
    });
});

describe('chronologie des faits', () => {
    const data = {
        lines: [
            {
                slug: 'a',
                name: 'Ligne A',
                events: [
                    {
                        id: 2,
                        date: '1930-05-01',
                        when: 'mai 1930',
                        label: 'Électrification',
                        source: 'wikipedia',
                        source_url: 'https://fr.wikipedia.org/x',
                        text: null,
                        section: null,
                    },
                    {
                        id: 1,
                        date: '1930-02-01',
                        when: 'février 1930',
                        label: 'Ouverture',
                        source: 'manual',
                        source_url: null,
                    },
                ],
            },
            {
                slug: 'b',
                name: 'Ligne B',
                events: [{ id: 2, date: '1930-05-01', when: 'mai 1930', label: 'Électrification' }],
            },
        ],
        accidents: [
            {
                slug: '1930-03-04-x',
                name: 'Déraillement',
                date: '1930-03-04',
                when: '4 mars 1930',
                kind: 'derailment',
                kind_label: 'Déraillement',
                review_status: 'to_review',
            },
        ],
    };

    it('trie du plus ancien au plus récent et ne répète pas un événement partagé', () => {
        const facts = chronology(data);
        expect(facts.map((f) => f.key)).toEqual(['e1', 'a1930-03-04-x', 'e2']);
        expect(facts[1]).toMatchObject({ href: '/accidents/1930-03-04-x', toReview: true });
        expect(facts[2]).toMatchObject({
            href: '/lines/a',
            sourceUrl: 'https://fr.wikipedia.org/x',
        });
    });

    it('supporte l absence de listes', () => {
        expect(chronology({})).toEqual([]);
    });
});

describe('bascule d époque', () => {
    it('propose 1930 si elle existe, sinon la plus ancienne ère passée', () => {
        expect(pastEraFor([1830, 1930, 2030], 2030)).toBe(1930);
        expect(pastEraFor([1990, 2000, 2030], 2030)).toBe(1990);
        expect(pastEraFor([2030], 2030)).toBeNull();
    });

    it('bascule vers aujourd hui depuis le passé et inversement', () => {
        expect(switchTarget(1930, 2030, 1930)).toBe(2030);
        expect(switchTarget(2030, 2030, 1930)).toBe(1930);
    });
});

describe('appui long', () => {
    it('ouvre après le délai, une seule fois', () => {
        vi.useFakeTimers();
        const onPress = vi.fn();
        const press = createLongPress({ onPress, delay: 600 });
        press.start(10, 20);
        vi.advanceTimersByTime(599);
        expect(onPress).not.toHaveBeenCalled();
        vi.advanceTimersByTime(2);
        expect(onPress).toHaveBeenCalledOnce();
        expect(onPress).toHaveBeenCalledWith(10, 20);
        vi.advanceTimersByTime(2000);
        expect(onPress).toHaveBeenCalledOnce();
        vi.useRealTimers();
    });

    it('n ouvre rien si le doigt bouge, se lève ou s ajoute un second doigt', () => {
        vi.useFakeTimers();
        const onPress = vi.fn();
        const press = createLongPress({ onPress, delay: 600, slop: 10 });
        press.start(0, 0);
        press.move(3, 4);
        expect(press.pending).toBe(true);
        press.move(30, 0);
        expect(press.pending).toBe(false);
        press.start(0, 0);
        press.cancel();
        press.start(0, 0, 2);
        vi.advanceTimersByTime(5000);
        expect(onPress).not.toHaveBeenCalled();
        vi.useRealTimers();
    });
});
