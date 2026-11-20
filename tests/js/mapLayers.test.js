import { describe, expect, it } from 'vitest';
import {
    DATA_LAYERS,
    NO_ORM,
    ORM_LAYERS,
    activeLayers,
    layerCount,
    layerLabelKey,
    layerNotices,
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
            'maps.overlay.greenways',
        ]);
        expect(ORM_LAYERS).toHaveLength(5);
    });
});

describe('activeLayers (carte intégrable et /maps)', () => {
    const asked = readLayers(
        '?layer=signalling&layer=accidents&layer=level-crossings&layer=orm-gauge',
    );

    it('dessine tous les calques demandés sur la carte d’aujourd’hui', () => {
        expect(activeLayers(asked, true)).toEqual({
            accidents: true,
            levelCrossings: true,
            signalling: true,
            signallingHistory: false,
            greenways: false,
            todayOnly: ['level-crossings'],
            eraViewOnly: [],
            legendOverlays: ['level-crossings', 'signalling'],
            orm: 'orm-gauge',
        });
    });

    it('ne dessine pas les passages à niveau sur une autre époque, mais la signalisation datée oui', () => {
        const drawn = activeLayers(asked, false, true);
        expect(drawn).toMatchObject({
            accidents: true,
            levelCrossings: false,
            signalling: true,
            signallingHistory: true,
            legendOverlays: ['signalling'],
            orm: 'orm-gauge',
        });
        expect(drawn.todayOnly).toEqual(['level-crossings']);
        expect(drawn.eraViewOnly).toEqual([]);
    });

    it('hors de la vue « une époque » (comparaison, ères empilées), la signalisation n’est pas dessinée', () => {
        const drawn = activeLayers(asked, false, false);
        expect(drawn).toMatchObject({
            signalling: false,
            signallingHistory: false,
            legendOverlays: [],
        });
        expect(drawn.eraViewOnly).toEqual(['signalling']);
    });

    it('sans calque demandé, rien n’est dessiné', () => {
        expect(activeLayers(readLayers(''), true)).toEqual({
            accidents: false,
            levelCrossings: false,
            signalling: false,
            signallingHistory: false,
            greenways: false,
            todayOnly: [],
            eraViewOnly: [],
            legendOverlays: [],
            orm: NO_ORM,
        });
    });

    it('lit ?layer=greenways et le dessine à toutes les époques, sans message « aujourd’hui seulement »', () => {
        const asked = readLayers('?layer=greenways&layer=signalling');
        expect(asked.data).toEqual(['signalling', 'greenways']);
        const today = activeLayers(asked, true);
        expect(today).toMatchObject({ greenways: true, signalling: true });
        expect(today.legendOverlays).toEqual(['signalling', 'greenways']);
        expect(today.todayOnly).toEqual([]);

        const past = activeLayers(asked, false, true);
        expect(past).toMatchObject({ greenways: true, signalling: true, signallingHistory: true });
        expect(past.legendOverlays).toEqual(['signalling', 'greenways']);
        expect(past.todayOnly).toEqual([]);

        const only = activeLayers(readLayers('?layer=greenways'), false);
        expect(only.todayOnly).toEqual([]);
        expect(
            layerNotices(only, {
                showingToday: false,
                accidentsFailed: false,
                zoom: 5,
                minZoom: 9,
            }),
        ).toEqual([]);
    });
});

describe('layerNotices', () => {
    const state = { showingToday: true, accidentsFailed: false, zoom: 5, minZoom: 9 };

    it('ne dit rien quand tout va bien et qu’aucun calque n’est demandé', () => {
        expect(layerNotices(activeLayers(readLayers(''), true), state)).toEqual([]);
    });

    it('signale l’échec des accidents', () => {
        const active = activeLayers(readLayers('?layer=accidents'), true);
        expect(layerNotices(active, { ...state, accidentsFailed: true })).toEqual([
            { key: 'accidents', textKey: 'maps.accidents.error' },
        ]);
        expect(layerNotices(active, state)).toEqual([]);
    });

    it('choisit les phrases selon la vue : passages à niveau d’aujourd’hui, signalisation datée', () => {
        const past = { ...state, showingToday: false };
        const keys = (search, eraView) =>
            layerNotices(activeLayers(readLayers(search), false, eraView), past).map(
                (n) => n.textKey,
            );
        expect(keys('?layer=signalling', true)).toEqual(['maps.signalling.history_notice']);
        expect(keys('?layer=signalling', false)).toEqual(['maps.signalling.era_view_only']);
        expect(keys('?layer=level-crossings', true)).toEqual(['maps.level_crossings.current_only']);
        expect(keys('?layer=level-crossings&layer=signalling', true)).toEqual([
            'maps.level_crossings.current_only',
            'maps.signalling.history_notice',
        ]);
        expect(keys('?layer=accidents', true)).toEqual([]);
        // Aujourd'hui : l'état de SNCF Réseau, sans message.
        expect(layerNotices(activeLayers(readLayers('?layer=signalling'), true), state)).toEqual(
            [],
        );
    });

    it('invite à zoomer pour les passages à niveau, seulement s’ils sont dessinés', () => {
        const active = activeLayers(readLayers('?layer=level-crossings'), true);
        expect(layerNotices(active, state)).toEqual([
            { key: 'zoom', textKey: 'maps.level_crossings.zoom_hint', icon: 'bi-zoom-in' },
        ]);
        expect(layerNotices(active, { ...state, zoom: 9 })).toEqual([]);
        expect(
            layerNotices(activeLayers(readLayers('?layer=level-crossings'), false), {
                ...state,
                showingToday: false,
            }).map((n) => n.key),
        ).toEqual(['today']);
    });
});
