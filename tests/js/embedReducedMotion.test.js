// Carte intégrée (/embed/maps) et animations réduites : même lecture que /maps (lib/eraPlayback.js,
// EraPlayer.svelte), aucun défilement automatique sans geste quand le système demande moins d'animations.
import { readFileSync } from 'node:fs';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createPlayback, isPlaybackGesture } from '$lib/eraPlayback.js';
import { scrollToContent } from '$lib/actions/smoothScrollTo.js';

const read = (path) => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');

describe('page intégrable', () => {
    const embed = read('Pages/Embed/Maps.svelte');

    it('réutilise la lecture des époques de /maps', () => {
        expect(embed).toContain("from '$lib/eraPlayback.js'");
        expect(embed).toContain('<EraPlayer');
        expect(embed).toContain('onPlay={openPlayback}');
        expect(embed).toContain('onEraShown={(year) => playback.shown(year)}');
    });

    it('ne lance aucune lecture automatique en animations réduites et s’arrête au geste', () => {
        expect(embed).toContain("'(prefers-reduced-motion: reduce)'");
        expect(embed).toContain('if (!reducedMotion.current) playback.play();');
        expect(embed).toContain('stepOnly={reducedMotion.current}');
        expect(embed).toContain('isPlaybackGesture(e)');
    });

    it('garde les paramètres d’adresse existants', () => {
        for (const p of [
            'era',
            'timeline',
            'legend',
            'attribution',
            'mode',
            'showVanished',
            'tint',
        ])
            expect(embed).toContain(`?${p}`);
    });
});

describe('lecture et gestes', () => {
    afterEach(() => vi.useRealTimers());

    it('une pause au premier geste arrête l’avance', () => {
        vi.useFakeTimers();
        let year = 1900;
        const steps = [];
        const pb = createPlayback({
            years: () => [1900, 1910, 1920],
            getYear: () => year,
            onStep: (y) => {
                year = y;
                steps.push(y);
            },
        });
        pb.play();
        vi.advanceTimersByTime(3000);
        expect(steps).toEqual([1910]);
        if (isPlaybackGesture({ type: 'pointerdown', target: {} })) pb.pause();
        vi.advanceTimersByTime(60000);
        expect(steps).toEqual([1910]);
    });

    it('les commandes de la lecture ne sont pas un geste d’arrêt', () => {
        const target = { closest: (s) => (s === '[data-playback-control]' ? {} : null) };
        expect(isPlaybackGesture({ type: 'pointerdown', target })).toBe(false);
    });
});

describe('défilement de la page sous animations réduites', () => {
    afterEach(() => vi.unstubAllGlobals());

    it.each([
        [true, 'auto'],
        [false, 'smooth'],
    ])('reduce=%s : comportement %s', (reduce, behavior) => {
        const scrollTo = vi.fn();
        vi.stubGlobal('window', {
            innerHeight: 800,
            scrollTo,
            matchMedia: () => ({ matches: reduce }),
        });
        scrollToContent();
        expect(scrollTo).toHaveBeenCalledWith(expect.objectContaining({ behavior }));
    });
});
