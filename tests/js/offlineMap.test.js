// Carte hors ligne (resources/js/lib/offlineMap.svelte.js) : fond neutre sans ressource tierce, signal de
// carte partielle lu par OfflineCopyNotice.
import { describe, expect, it } from 'vitest';
import {
    NEUTRAL_BACKGROUND,
    isOfflineView,
    neutralMapStyle,
    partialMaps,
    reportPartialMap,
} from '$lib/offlineMap.svelte.js';

describe('isOfflineView', () => {
    const online = { onLine: true };

    it('reconnaît une copie servie par le service worker', () => {
        expect(isOfflineView({ offline_copy: { saved_at: '2026-11-19T13:05:00Z' } }, online)).toBe(
            true,
        );
    });

    it('reconnaît un navigateur hors ligne', () => {
        expect(isOfflineView({}, { onLine: false })).toBe(true);
    });

    it('laisse le fond habituel à une page venue du réseau', () => {
        expect(isOfflineView({}, online)).toBe(false);
        expect(isOfflineView(undefined, undefined)).toBe(false);
        expect(isOfflineView({ offline_copy: { saved_at: 'hier' } }, online)).toBe(false);
    });
});

describe('neutralMapStyle', () => {
    it('ne contient aucune source : ni tuile OpenStreetMap ni style d un tiers à charger', () => {
        const style = neutralMapStyle();
        expect(style.version).toBe(8);
        expect(style.sources).toEqual({});
        expect(style.layers).toHaveLength(1);
        expect(style.layers[0].type).toBe('background');
        expect(JSON.stringify(style.layers)).not.toMatch(/openstreetmap|maptiler|openrailwaymap/);
    });

    it('suit le thème', () => {
        expect(neutralMapStyle().layers[0].paint['background-color']).toBe(
            NEUTRAL_BACKGROUND.light,
        );
        expect(neutralMapStyle({ dark: true }).layers[0].paint['background-color']).toBe(
            NEUTRAL_BACKGROUND.dark,
        );
    });

    it('déclare des glyphes, exigés par les calques de noms (police locale hors ligne)', () => {
        expect(neutralMapStyle().glyphs).toMatch(/\{fontstack\}\/\{range\}\.pbf$/);
    });
});

describe('reportPartialMap', () => {
    it('compte les cartes sur fond neutre, une seule fois chacune', () => {
        const before = partialMaps.count;
        const releaseA = reportPartialMap();
        const releaseB = reportPartialMap();
        expect(partialMaps.count).toBe(before + 2);
        releaseA();
        releaseA();
        expect(partialMaps.count).toBe(before + 1);
        releaseB();
        expect(partialMaps.count).toBe(before);
    });
});
