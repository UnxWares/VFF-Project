import { describe, expect, it } from 'vitest';
import {
    DATA_LAYERS,
    NO_ORM,
    ORM_LAYERS,
    layerCount,
    layerLabelKey,
    readLayers,
    toggleData,
    writeLayers,
} from '$lib/mapLayers.js';

const BASE = 'https://vff-project.org/maps';

describe('readLayers', () => {
    it('lit les anciennes adresses à un seul calque', () => {
        expect(readLayers('?layer=signalling')).toEqual({ data: ['signalling'], orm: NO_ORM });
        expect(readLayers('?layer=orm-maxspeed')).toEqual({ data: [], orm: 'orm-maxspeed' });
        expect(readLayers('?layer=accidents&era=1950')).toEqual({
            data: ['accidents'],
            orm: NO_ORM,
        });
    });

    it('lit plusieurs calques, répétés ou séparés par des virgules, dans l’ordre d’empilement', () => {
        expect(readLayers('?layer=signalling&layer=accidents&layer=orm-gauge')).toEqual({
            data: ['accidents', 'signalling'],
            orm: 'orm-gauge',
        });
        expect(readLayers('?layer=signalling,level-crossings')).toEqual({
            data: ['level-crossings', 'signalling'],
            orm: NO_ORM,
        });
        expect(readLayers('?layer=signalling%2Caccidents')).toEqual({
            data: ['accidents', 'signalling'],
            orm: NO_ORM,
        });
    });

    it('ignore les valeurs inconnues, les doublons et un second fond OpenRailwayMap', () => {
        expect(
            readLayers(
                '?layer=none&layer=foo&layer=accidents&layer=accidents&layer=orm-signals&layer=orm-gauge',
            ),
        ).toEqual({ data: ['accidents'], orm: 'orm-signals' });
        expect(readLayers('')).toEqual({ data: [], orm: NO_ORM });
        expect(readLayers('?layer=')).toEqual({ data: [], orm: NO_ORM });
    });
});

describe('writeLayers', () => {
    it('écrit un paramètre par calque et garde les autres paramètres', () => {
        expect(
            writeLayers(`${BASE}?era=1950`, {
                data: ['signalling', 'accidents'],
                orm: 'orm-standard',
            }),
        ).toBe(`${BASE}?era=1950&layer=accidents&layer=signalling&layer=orm-standard`);
    });

    it('retire le paramètre quand aucun calque n’est affiché', () => {
        expect(writeLayers(`${BASE}?layer=accidents&era=1950`, { data: [], orm: NO_ORM })).toBe(
            `${BASE}?era=1950`,
        );
    });

    it('une adresse à un seul calque reste celle d’avant', () => {
        expect(writeLayers(BASE, { data: ['signalling'], orm: NO_ORM })).toBe(
            `${BASE}?layer=signalling`,
        );
    });

    it('relit exactement ce qu’elle écrit', () => {
        const layers = { data: ['accidents', 'level-crossings', 'signalling'], orm: 'orm-signals' };
        const href = writeLayers(BASE, layers);
        expect(readLayers(new URL(href).search)).toEqual(layers);
    });

    it('n’écrit pas un fond inconnu', () => {
        expect(writeLayers(BASE, { data: [], orm: 'orm-foo' })).toBe(`${BASE}`);
    });
});

describe('toggleData et layerCount', () => {
    it('coche et décoche en gardant l’ordre d’empilement', () => {
        expect(toggleData([], 'signalling', true)).toEqual(['signalling']);
        expect(toggleData(['signalling'], 'accidents', true)).toEqual(['accidents', 'signalling']);
        expect(toggleData(['accidents', 'signalling'], 'accidents', false)).toEqual(['signalling']);
        expect(toggleData(['accidents'], 'accidents', true)).toEqual(['accidents']);
    });

    it('compte les calques de données et le fond', () => {
        expect(layerCount({ data: [], orm: NO_ORM })).toBe(0);
        expect(layerCount({ data: ['accidents', 'signalling'], orm: 'orm-gauge' })).toBe(3);
    });

    it('chaque calque de données a une clé de traduction maps.overlay.*', () => {
        expect(DATA_LAYERS.map(layerLabelKey)).toEqual([
            'maps.overlay.accidents',
            'maps.overlay.level_crossings',
            'maps.overlay.signalling',
        ]);
        expect(ORM_LAYERS).toHaveLength(5);
    });
});
