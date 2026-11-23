// Lecture des époques sur /maps (lib/eraPlayback.js, EraPlayer.svelte, bouton de TimelineSlider.svelte) :
// séquence des décennies, attente du dessin de la carte, vitesse, pas à pas, arrêt au geste, préchargement
// d'une seule ère (tuiles de la zone visible), chiffres déjà calculés, rendu du panneau.
import { render } from 'svelte/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import {
    DEFAULT_SPEED,
    MAX_PRELOAD_TILES,
    MAX_WAIT_MS,
    PLAYBACK_SPEEDS,
    createPlayback,
    isPlaybackGesture,
    openKmByYear,
    playbackYears,
    stepYear,
    tileUrl,
    tilesCovering,
} from '$lib/eraPlayback.js';
import EraPlayer from '../../resources/js/Components/Map/EraPlayer.svelte';
import TimelineSlider from '../../resources/js/Components/Map/TimelineSlider.svelte';

setupI18n({
    locale: 'fr',
    translations: {
        'maps.playback.label': 'Lecture des époques',
        'maps.playback.play': 'Lire les époques',
        'maps.playback.pause': 'Mettre en pause',
        'maps.playback.replay': 'Relire depuis le début',
        'maps.playback.prev': 'Décennie précédente',
        'maps.playback.next': 'Décennie suivante',
        'maps.playback.close': 'Quitter la lecture',
        'maps.playback.speed': 'Vitesse',
        'maps.playback.speed_slow': 'Lente',
        'maps.playback.speed_normal': 'Normale',
        'maps.playback.km': ':km km de lignes en service',
        'maps.playback.km_today': 'Aujourd’hui, :km km de lignes en service',
        'maps.playback.step_only': 'Animations réduites : avancez décennie par décennie.',
        'maps.timeline.today_caption': 'Le réseau aujourd’hui',
        'maps.timeline.today': 'Aujourd’hui',
        'maps.timeline.play': 'Faire défiler les décennies',
        'maps.timeline.pause': 'Arrêter le défilement',
    },
});

const clean = (body) =>
    body
        .replace(/<!--.*?-->/gs, '')
        .replace(/ class="svelte-[a-z0-9]+"/g, '')
        .replace(/ svelte-[a-z0-9]+/g, '')
        .replace(/>\s+</g, '><');
const draw = (component, props) => clean(render(component, { props }).body);

const ERAS = [
    { year: 1830, has_data: false },
    { year: 1840, has_data: true },
    { year: 1850, has_data: false },
    { year: 1860, has_data: true },
    { year: 2030, has_data: true },
];

describe('Séquence des décennies', () => {
    it('part de la première ère reconstituée et saute les décennies vides', () => {
        expect(playbackYears(ERAS)).toEqual([1840, 1860, 2030]);
    });

    it('garde toujours aujourd’hui, même sans tracé, et accepte des années seules', () => {
        expect(
            playbackYears([
                { year: 1990, has_data: true },
                { year: 2030, has_data: false },
            ]),
        ).toEqual([1990, 2030]);
        expect(playbackYears([2000, 1990, 2030])).toEqual([1990, 2000, 2030]);
        expect(playbackYears(null)).toEqual([]);
    });

    it('avance et recule d’un pas, null au bout, et rejoint la séquence depuis une décennie vide', () => {
        const years = [1840, 1860, 2030];
        expect(stepYear(years, 1840, 1)).toBe(1860);
        expect(stepYear(years, 2030, 1)).toBeNull();
        expect(stepYear(years, 1840, -1)).toBeNull();
        expect(stepYear(years, 1850, 1)).toBe(1860);
        expect(stepYear(years, 1850, -1)).toBe(1840);
        expect(stepYear([], 1850, 1)).toBeNull();
    });
});

describe('Lecture', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    function setup(start = 2030) {
        let year = start;
        const steps = [];
        const states = [];
        const playback = createPlayback({
            years: () => [1840, 1860, 1900, 2030],
            getYear: () => year,
            onStep: (y) => {
                year = y;
                steps.push(y);
            },
            onChange: (s) => states.push(s),
        });
        return { playback, steps, states, year: () => year };
    }

    it('repart de la première ère depuis aujourd’hui et attend le dessin avant de compter l’étape', () => {
        const { playback, steps } = setup();
        playback.play();
        expect(steps).toEqual([1840]);
        // Carte pas encore dessinée : rien ne bouge, même après la durée d'une étape.
        vi.advanceTimersByTime(PLAYBACK_SPEEDS.normal + 100);
        expect(steps).toEqual([1840]);
        playback.shown(1840);
        vi.advanceTimersByTime(PLAYBACK_SPEEDS.normal - 1);
        expect(steps).toEqual([1840]);
        vi.advanceTimersByTime(1);
        expect(steps).toEqual([1840, 1860]);
    });

    it('avance quand même si la carte ne répond pas', () => {
        const { playback, steps } = setup();
        playback.play();
        vi.advanceTimersByTime(MAX_WAIT_MS + PLAYBACK_SPEEDS.normal);
        expect(steps).toEqual([1840, 1860]);
    });

    it('ignore l’avis d’une ère qui n’est plus attendue', () => {
        const { playback, steps } = setup();
        playback.play();
        playback.shown(1990);
        vi.advanceTimersByTime(PLAYBACK_SPEEDS.normal);
        expect(steps).toEqual([1840]);
    });

    it('s’arrête sur aujourd’hui et signale la fin, puis relit depuis le début', () => {
        const { playback, steps, states } = setup();
        playback.play();
        for (const y of [1840, 1860, 1900, 2030]) {
            playback.shown(y);
            vi.advanceTimersByTime(PLAYBACK_SPEEDS.normal);
        }
        expect(steps).toEqual([1840, 1860, 1900, 2030]);
        expect(playback.playing).toBe(false);
        expect(states.at(-1)).toEqual({ playing: false, ended: true });
        playback.play();
        expect(steps.at(-1)).toBe(1840);
        expect(states.at(-1)).toEqual({ playing: true, ended: false });
    });

    it('reprend après une pause depuis l’ère affichée', () => {
        const { playback, steps } = setup(1860);
        playback.play();
        expect(steps).toEqual([]);
        vi.advanceTimersByTime(PLAYBACK_SPEEDS.normal);
        expect(steps).toEqual([1900]);
        playback.pause();
        vi.advanceTimersByTime(MAX_WAIT_MS * 2);
        expect(steps).toEqual([1900]);
        expect(vi.getTimerCount()).toBe(0);
    });

    it('vitesse lente : étape plus longue, appliquée à l’étape en cours', () => {
        const { playback, steps } = setup(1840);
        expect(playback.speed).toBe(DEFAULT_SPEED);
        playback.play();
        playback.setSpeed('slow');
        playback.setSpeed('turbo');
        expect(playback.speed).toBe('slow');
        vi.advanceTimersByTime(PLAYBACK_SPEEDS.normal);
        expect(steps).toEqual([]);
        vi.advanceTimersByTime(PLAYBACK_SPEEDS.slow - PLAYBACK_SPEEDS.normal);
        expect(steps).toEqual([1860]);
    });

    it('pas à pas : met en pause et avance ou recule d’une décennie, sans dépasser les bouts', () => {
        const { playback, steps } = setup(1860);
        playback.play();
        expect(playback.next()).toBe(true);
        expect(playback.playing).toBe(false);
        expect(playback.prev()).toBe(true);
        expect(playback.prev()).toBe(true);
        expect(playback.prev()).toBe(false);
        expect(steps).toEqual([1900, 1860, 1840]);
        vi.advanceTimersByTime(MAX_WAIT_MS * 2);
        expect(steps).toEqual([1900, 1860, 1840]);
    });

    it('trois boucles complètes ne laissent aucune minuterie derrière elles', () => {
        const { playback, steps } = setup();
        for (let loop = 0; loop < 3; loop++) {
            playback.play();
            for (const y of [1840, 1860, 1900, 2030]) {
                playback.shown(y);
                vi.advanceTimersByTime(PLAYBACK_SPEEDS.normal);
            }
        }
        expect(steps).toHaveLength(12);
        expect(vi.getTimerCount()).toBe(0);
        playback.play();
        playback.destroy();
        expect(vi.getTimerCount()).toBe(0);
    });
});

describe('Arrêt au moindre geste', () => {
    const inside = { closest: (sel) => (sel === '[data-playback-control]' ? {} : null) };
    const outside = { closest: () => null };

    it('arrête sur un appui, une molette ou une touche hors des commandes', () => {
        expect(isPlaybackGesture({ type: 'pointerdown', target: outside })).toBe(true);
        expect(isPlaybackGesture({ type: 'wheel', target: outside })).toBe(true);
        expect(isPlaybackGesture({ type: 'keydown', key: 'ArrowUp', target: outside })).toBe(true);
        expect(isPlaybackGesture({ type: 'keydown', key: 'Escape', target: null })).toBe(true);
    });

    it('laisse faire les commandes de la lecture et les touches neutres', () => {
        expect(isPlaybackGesture({ type: 'pointerdown', target: inside })).toBe(false);
        expect(isPlaybackGesture({ type: 'keydown', key: ' ', target: inside })).toBe(false);
        expect(isPlaybackGesture({ type: 'keydown', key: 'Tab', target: outside })).toBe(false);
        expect(isPlaybackGesture({ type: 'keydown', key: 'Shift', target: outside })).toBe(false);
        expect(isPlaybackGesture({ type: 'mousemove', target: outside })).toBe(false);
    });
});

describe('Préchargement de l’ère suivante', () => {
    const FRANCE = { west: -5.2, south: 41.3, east: 9.6, north: 51.1 };

    it('couvre la France au zoom 5 avec quelques tuiles, comme MapLibre (niveau entier)', () => {
        const tiles = tilesCovering(FRANCE, 5.6);
        expect(tiles.every((t) => t.z === 5)).toBe(true);
        expect(tiles).toContainEqual({ z: 5, x: 16, y: 11 });
        expect(tiles.length).toBeLessThanOrEqual(9);
    });

    it('borne le niveau au zoom maximal des tuiles et renonce à une zone trop grande', () => {
        const paris = { west: 2.33, south: 48.85, east: 2.36, north: 48.87 };
        expect(tilesCovering(paris, 17).every((t) => t.z === 14)).toBe(true);
        const world = { west: -180, south: -85, east: 180, north: 85 };
        expect(tilesCovering(world, 4)).toEqual([]);
        expect(tilesCovering(world, 2).length).toBeLessThanOrEqual(MAX_PRELOAD_TILES);
    });

    it('construit l’adresse depuis le gabarit de la source', () => {
        expect(
            tileUrl('https://vff.test/api/v1/eras/1930/tiles/{z}/{x}/{y}.mvt?fields=map', {
                z: 5,
                x: 16,
                y: 11,
            }),
        ).toBe('https://vff.test/api/v1/eras/1930/tiles/5/16/11.mvt?fields=map');
    });
});

describe('Longueur en service', () => {
    it('reprend les chiffres déjà calculés et ignore les ères sans chiffre', () => {
        expect(
            openKmByYear([
                { year: 1930, open_km: 60499 },
                { year: 1940, open_km: null },
                { year: 1950 },
                { year: 1830, open_km: 0 },
            ]),
        ).toEqual({ 1930: 60499 });
        expect(openKmByYear(null)).toEqual({});
    });
});

const YEARS = [1840, 1930, 2030];

describe('Barre de lecture', () => {
    it('affiche l’année en grand, la longueur en service et les commandes nommées', () => {
        const html = draw(EraPlayer, {
            years: YEARS,
            year: 1930,
            todayEra: 2030,
            openKm: 60499,
            playing: true,
        });
        expect(html).toContain('role="group" aria-label="Lecture des époques"');
        expect(html).toContain('data-playback-control');
        expect(html).toContain('<span class="year">1930</span>');
        expect(html).toMatch(/60.499 km de lignes en service/);
        expect(html).toContain('aria-label="Mettre en pause"');
        expect(html).toContain('aria-keyshortcuts="Space"');
        expect(html).toContain('aria-label="Décennie précédente"');
        expect(html).toContain('aria-label="Décennie suivante"');
        expect(html).toContain('aria-pressed="true">Normale</button>');
        expect(html).toContain('aria-pressed="false">Lente</button>');
        expect(html).toContain('<span>1840</span><span>Aujourd’hui</span>');
        expect(html).toMatch(/class="progress-fill" style="width: 47\.36\d*%;?"/);
    });

    it('aujourd’hui : l’année en cours, jamais 2030', () => {
        const html = draw(EraPlayer, {
            years: YEARS,
            year: 2030,
            todayEra: 2030,
            openKm: 27000,
        });
        expect(html).toContain(`<span class="year">${new Date().getFullYear()}</span>`);
        expect(html).not.toContain('>2030<');
        expect(html).toMatch(/Aujourd’hui, 27.000 km de lignes en service/);
    });

    it('sans chiffre calculé, rien n’est inventé', () => {
        const html = draw(EraPlayer, {
            years: YEARS,
            year: 1930,
            todayEra: 2030,
        });
        expect(html).not.toContain('km');
        expect(html).not.toContain('class="detail"');
    });

    it('animations réduites : pas de bouton lecture, pas à pas seulement', () => {
        const html = draw(EraPlayer, {
            years: YEARS,
            year: 1840,
            todayEra: 2030,
            stepOnly: true,
        });
        expect(html).not.toContain('Lire les époques');
        expect(html).not.toContain('Vitesse');
        expect(html).toContain('Animations réduites');
        expect(html).toMatch(/aria-label="Décennie précédente"[^>]*disabled/);
    });

    it('fin de la lecture : le bouton propose de relire', () => {
        const html = draw(EraPlayer, {
            years: YEARS,
            year: 2030,
            todayEra: 2030,
            ended: true,
        });
        expect(html).toContain('aria-label="Relire depuis le début"');
    });
});

describe('Bouton de la frise', () => {
    it('suit la lecture de la page quand elle lui est confiée', () => {
        const props = { eras: [1990, 2000, 2030], value: 2000, currentEra: 2030, onPlay: () => {} };
        expect(draw(TimelineSlider, { ...props, playing: true })).toContain(
            'aria-label="Arrêter le défilement"',
        );
        const idle = draw(TimelineSlider, { ...props, playing: false });
        expect(idle).toContain('aria-label="Faire défiler les décennies"');
        expect(idle).toMatch(/class="play-btn" data-playback-control/);
    });
});
