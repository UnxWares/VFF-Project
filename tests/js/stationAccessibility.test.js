import { describe, expect, it } from 'vitest';
import {
    dayName,
    elevatorSummary,
    highlights,
    hoursGroups,
    readingTime,
    sentence,
    sortedItems,
} from '../../resources/js/lib/stationAccessibility.js';

const full = {
    access: { mobility: 'partial', accessible: true, step_free: true },
    assistance: { mode: 'reserved' },
    elevators: { count: 3, status: { ok: 2, ko: 1, unknown: 0, items: [] } },
    amenities: ['induction_loop', 'tactile_strips', 'toilets_accessible'],
    sales: { counter: true, machines: [] },
    bike_parking: { places: 12 },
};

describe('highlights', () => {
    it('donne une ligne compacte dans l ordre fixe, chaque fait avec un texte', () => {
        const ids = highlights(full).map((f) => f.id);
        expect(ids).toEqual([
            'mobility',
            'step_free',
            'assistance',
            'lifts',
            'induction_loop',
            'tactile_strips',
            'counter',
            'toilets',
            'bikes',
        ]);
        expect(highlights(full).every((f) => f.icon.startsWith('bi-') && f.key)).toBe(true);
    });

    it('signale des ascenseurs en panne', () => {
        const lifts = highlights(full).find((f) => f.id === 'lifts');
        expect(lifts).toMatchObject({
            key: 'lifts_status',
            params: { ok: 2, count: 3 },
            alert: true,
        });
    });

    it('dit « tous en service » quand l état publié est partout bon', () => {
        const data = { elevators: { count: 2, status: { ok: 2, ko: 0, unknown: 0 } } };
        expect(highlights(data)[0]).toMatchObject({ key: 'lifts_all_ok', params: { count: 2 } });
    });

    it('ne parle que du nombre d ascenseurs sans état publié', () => {
        expect(highlights({ elevators: { count: 4, status: null } })[0]).toMatchObject({
            key: 'lifts',
            params: { count: 4 },
        });
    });

    it('dit « non déclarée accessible » sans jamais affirmer des marches', () => {
        const none = highlights({ access: { mobility: 'none', step_free: false } });
        expect(none.map((f) => [f.id, f.key, f.alert])).toEqual([['mobility', 'none', true]]);
        expect(highlights({ access: { mobility: null, step_free: null } })).toEqual([]);
    });

    it('n invente jamais un « non » : un équipement absent de la liste n est pas affiché', () => {
        expect(highlights({ access: { mobility: null }, amenities: [] })).toEqual([]);
        expect(highlights(null)).toEqual([]);
    });

    it('ignore un stationnement vélo sans place', () => {
        expect(highlights({ bike_parking: { places: 0 } })).toEqual([]);
    });
});

describe('elevatorSummary', () => {
    it('prend le plus grand du nombre recensé et du nombre publié', () => {
        expect(elevatorSummary({ count: 1, status: { ok: 2, ko: 0, unknown: 1 } })).toEqual({
            count: 3,
            ok: 2,
            ko: 0,
            unknown: 1,
            known: true,
        });
    });

    it('rend null sans ascenseur', () => {
        expect(elevatorSummary({ count: 0, status: null })).toBeNull();
        expect(elevatorSummary(undefined)).toBeNull();
    });
});

describe('hoursGroups', () => {
    it('regroupe les jours consécutifs identiques', () => {
        const hours = {
            monday: '08:00 - 17:00',
            tuesday: '08:00 - 17:00',
            wednesday: '08:00 - 17:00',
            thursday: '08:00 - 17:00',
            friday: '08:00 - 17:00',
            saturday: null,
            sunday: null,
        };
        expect(hoursGroups(hours)).toEqual([
            { from: 'monday', to: 'friday', value: '08:00 - 17:00' },
            { from: 'saturday', to: 'sunday', value: null },
        ]);
    });

    it('rend une liste vide sans horaires', () => {
        expect(hoursGroups(null).every((g) => g.value === null)).toBe(true);
    });
});

describe('dates', () => {
    it('nomme les jours dans la langue de la page', () => {
        expect(dayName('monday', 'fr')).toBe('lundi');
        expect(dayName('sunday', 'en')).toBe('Sunday');
    });

    it('met en forme la date de lecture, vide si invalide', () => {
        expect(readingTime('2026-11-23T10:00:00+01:00', 'fr')).toContain('2026');
        expect(readingTime(null, 'fr')).toBe('');
        expect(readingTime('pas une date', 'fr')).toBe('');
    });
});

describe('sortedItems', () => {
    it('met les pannes en tête, puis les états inconnus, puis les équipements en service', () => {
        const items = [
            { label: 'a', state: 'ok' },
            { label: 'b', state: 'ko' },
            { label: 'c', state: 'unknown' },
            { label: 'd', state: 'ok' },
        ];
        expect(sortedItems(items).map((i) => i.label)).toEqual(['b', 'c', 'a', 'd']);
        expect(sortedItems(null)).toEqual([]);
    });
});

describe('sentence', () => {
    it('met une majuscule et un point final au texte de SNCF', () => {
        expect(sentence('présence ponctuelle de dispositifs de guidage sonore')).toBe(
            'Présence ponctuelle de dispositifs de guidage sonore.',
        );
        expect(sentence('Déjà complète.')).toBe('Déjà complète.');
        expect(sentence(null)).toBe('');
    });
});
