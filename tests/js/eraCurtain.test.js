// Rideau de comparaison de deux époques sur /maps (lib/eraCurtain.js, EraCurtain.svelte,
// EraCompareBar.svelte) : adresse partageable, découpage WebGL, couches jumelles, curseur clavier.
import { render } from 'svelte/server';
import { describe, expect, it, vi } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import {
    CLIP_LEFT_ID,
    COMPARE_MODES,
    CURTAIN_DEFAULT,
    DEFAULT_COMPARE_MODE,
    clampCurtain,
    clipBox,
    clipLayer,
    parseCurtain,
    readCompareParams,
    sideAt,
    tagFits,
    twinId,
    twinLayer,
    writeCompareParams,
} from '$lib/eraCurtain.js';
import { readViewParams, writeViewParams } from '$lib/epochViews.js';
import { accidentYears } from '$lib/accidentMap.js';
import EraCurtain from '../../resources/js/Components/Map/EraCurtain.svelte';
import EraCompareBar from '../../resources/js/Components/Map/EraCompareBar.svelte';

const BASE = 'https://vff.test/maps';
const ERAS = [1900, 1910, 1920, 1930, 1940, 1950, 1960, 1970, 1980, 1990, 2000, 2010, 2020, 2030];

setupI18n({
    locale: 'fr',
    translations: {
        'maps.curtain.label': 'Position du rideau',
        'maps.curtain.position':
            ':left à gauche, :right à droite, rideau à :percent % de la largeur',
        'maps.curtain.mode_label': 'Présentation',
        'maps.curtain.mode_curtain': 'Rideau',
        'maps.curtain.mode_changes': 'Différences',
        'maps.compare.aria_label': 'Comparaison de deux décennies',
        'maps.compare.from': 'De',
        'maps.compare.to': 'à',
        'maps.compare.loading': 'Calcul des différences…',
        'maps.timeline.today': 'Aujourd’hui',
    },
});

/** HTML sans marqueurs d'hydratation ni suffixe de classe propre au composant (svelte-xxxx). */
const clean = (body) =>
    body
        .replace(/<!--.*?-->/gs, '')
        .replace(/ class="svelte-[a-z0-9]+"/g, '')
        .replace(/ svelte-[a-z0-9]+/g, '')
        .replace(/>\s+</g, '><');
const draw = (component, props) => clean(render(component, { props }).body);

describe('Adresse de la comparaison', () => {
    it('ouvre le rideau au milieu pour ?era=1930&compare=2030', () => {
        expect(readCompareParams('?era=1930&compare=2030')).toEqual({
            mode: 'curtain',
            curtain: CURTAIN_DEFAULT,
        });
        expect(DEFAULT_COMPARE_MODE).toBe('curtain');
        expect(COMPARE_MODES).toEqual(['curtain', 'changes']);
    });

    it('lit la position du rideau et la présentation « Différences »', () => {
        expect(readCompareParams('?era=1930&compare=2030&curtain=30').curtain).toBe(30);
        expect(readCompareParams('?era=1930&compare=2030&show=changes').mode).toBe('changes');
    });

    it('borne ou ignore une position mal formée', () => {
        expect(parseCurtain('140')).toBe(100);
        expect(parseCurtain('33.6')).toBe(34);
        expect(parseCurtain('-5')).toBeNull();
        expect(parseCurtain('abc')).toBeNull();
        expect(parseCurtain(null)).toBeNull();
        expect(readCompareParams('?curtain=abc').curtain).toBe(CURTAIN_DEFAULT);
        expect(clampCurtain(Number.NaN)).toBe(CURTAIN_DEFAULT);
    });

    it('n’écrit pas le rideau au milieu : ?era=1930&compare=2030 suffit', () => {
        const url = writeViewParams(BASE, { view: 'compare', era: 1930, compare: 2030 }, 2030);
        expect(writeCompareParams(url, { mode: 'curtain', curtain: 50 })).toBe(
            `${BASE}?era=1930&compare=2030`,
        );
    });

    it('écrit la position, ou la présentation « Différences », et garde le reste', () => {
        const href = `${BASE}?center=2.5,47&zoom=6&era=1930&compare=2030`;
        expect(new URL(writeCompareParams(href, { mode: 'curtain', curtain: 27.4 })).search).toBe(
            '?center=2.5%2C47&zoom=6&era=1930&compare=2030&curtain=27',
        );
        expect(
            new URL(writeCompareParams(`${href}&curtain=27`, { mode: 'changes', curtain: 27 }))
                .search,
        ).toBe('?center=2.5%2C47&zoom=6&era=1930&compare=2030&show=changes');
    });

    it('retire show et curtain hors de la vue « Comparer »', () => {
        expect(writeCompareParams(`${BASE}?era=1950&curtain=20&show=changes`, null)).toBe(
            `${BASE}?era=1950`,
        );
    });

    it('fait l’aller-retour d’une adresse partagée', () => {
        const state = { view: 'compare', era: 1930, compare: 2030 };
        const shown = { mode: 'curtain', curtain: 35 };
        const url = new URL(writeCompareParams(writeViewParams(BASE, state, 2030), shown));
        expect(readViewParams(url.search, ERAS)).toEqual(state);
        expect(readCompareParams(url.search)).toEqual(shown);
    });
});

describe('Découpage de part et d’autre du rideau', () => {
    it('coupe le tampon de dessin à la position du rideau', () => {
        expect(clipBox('left', 50, 2000, 1000)).toEqual([0, 0, 1000, 1000]);
        expect(clipBox('right', 50, 2000, 1000)).toEqual([1000, 0, 1000, 1000]);
        expect(clipBox('left', 0, 2000, 1000)).toEqual([0, 0, 0, 1000]);
        expect(clipBox('right', 100, 2000, 1000)).toEqual([2000, 0, 0, 1000]);
    });

    it('les deux boîtes couvrent toute la largeur, sans recouvrement', () => {
        for (const pos of [0, 13, 37, 50, 99, 100]) {
            const [, , lw] = clipBox('left', pos, 1171, 640);
            const [rx, , rw] = clipBox('right', pos, 1171, 640);
            expect(rx).toBe(lw);
            expect(lw + rw).toBe(1171);
        }
    });

    it('rend le côté sous un clic', () => {
        expect(sideAt(100, 50, 400)).toBe('left');
        expect(sideAt(200, 50, 400)).toBe('right');
        expect(sideAt(10, 0, 400)).toBe('right');
    });

    it('règle le découpage WebGL sans rien dessiner', () => {
        const gl = { SCISSOR_TEST: 3089, enable: vi.fn(), disable: vi.fn(), scissor: vi.fn() };
        const left = clipLayer(CLIP_LEFT_ID, () => [0, 0, 10, 20]);
        expect(left).toMatchObject({ id: CLIP_LEFT_ID, type: 'custom', renderingMode: '2d' });
        left.render(gl);
        expect(gl.enable).toHaveBeenCalledWith(3089);
        expect(gl.scissor).toHaveBeenCalledWith(0, 0, 10, 20);
        clipLayer('fin', () => null).render(gl);
        expect(gl.disable).toHaveBeenCalledWith(3089);
    });
});

describe('Couches jumelles (époque de droite)', () => {
    const line = {
        id: 'vff-lines-active',
        type: 'line',
        source: 'vff-lines',
        'source-layer': 'vff-lines',
        filter: ['==', ['get', 'status'], 'open'],
        layout: { 'line-cap': 'round', visibility: 'none' },
        paint: { 'line-color': '#ff6500', 'line-opacity': 1 },
    };

    it('copie le dessin et le filtre sur la source de droite', () => {
        const twin = twinLayer(line);
        expect(twin.id).toBe(twinId('vff-lines-active'));
        expect(twin.source).toBe('vff-lines-right');
        expect(twin['source-layer']).toBe('vff-lines');
        expect(twin.filter).toEqual(line.filter);
        expect(twin.paint).toEqual(line.paint);
        expect(twin.layout.visibility).toBe('visible');
    });

    it('ne modifie pas la couche d’origine', () => {
        const twin = twinLayer(line);
        twin.paint['line-opacity'] = 0;
        expect(line.paint['line-opacity']).toBe(1);
        expect(line.layout.visibility).toBe('none');
    });

    it('laisse les noms de gare de droite s’afficher même là où ceux de gauche sont posés', () => {
        const labels = twinLayer({
            id: 'vff-stations-labels',
            type: 'symbol',
            source: 'vff-stations',
            layout: { 'text-field': ['get', 'name'] },
            paint: {},
        });
        expect(labels.source).toBe('vff-stations-right');
        expect(labels.layout).toMatchObject({
            'text-allow-overlap': true,
            'text-ignore-placement': true,
        });
    });
});

describe('Libellés des époques', () => {
    it('efface un libellé qui ne tient plus entre le rideau et le bord', () => {
        expect(tagFits('left', 50, 400, 60, 30)).toBe(true);
        expect(tagFits('left', 10, 400, 60, 30)).toBe(false);
        expect(tagFits('right', 90, 400, 60, 30)).toBe(false);
        expect(tagFits('right', 50, 400, 100, 30)).toBe(true);
    });

    it('efface le libellé de gauche qui passerait sous la légende ouverte', () => {
        expect(tagFits('left', 30, 1200, 60, 30, 0)).toBe(true);
        expect(tagFits('left', 30, 1200, 60, 30, 290)).toBe(false);
        expect(tagFits('right', 30, 1200, 60, 30, 290)).toBe(true);
    });
});

describe('Accidents montrés avec le rideau', () => {
    it('ceux survenus entre les deux époques, comme la carte des différences', () => {
        expect(accidentYears('curtain', 1930, 2030)).toEqual({ from: 1931, to: 2030 });
        expect(accidentYears('curtain', 1930, 2030)).toEqual(accidentYears('compare', 1930, 2030));
    });
});

describe('EraCurtain (curseur clavier RGAA)', () => {
    const body = draw(EraCurtain, {
        value: 35,
        left: '1930',
        right: 'Aujourd’hui',
        rightText: 'aujourd’hui',
    });

    it('est un curseur natif étiqueté, de 0 à 100 %', () => {
        const id = body.match(/<input id="([^"]+)" class="range"/)?.[1];
        expect(id).toBeTruthy();
        expect(body).toContain(`<label class="vff-sr-only" for="${id}">Position du rideau</label>`);
        expect(body).toMatch(/type="range" min="0" max="100" step="1" value="35"/);
    });

    it('annonce les deux époques et la position', () => {
        expect(body).toContain(
            'aria-valuetext="1930 à gauche, aujourd’hui à droite, rideau à 35 % de la largeur"',
        );
    });

    it('écrit les époques de part et d’autre, sans les lire deux fois', () => {
        expect(body).toMatch(/<span class="tag tag-left" aria-hidden="true">1930<\/span>/);
        expect(body).toMatch(/<span class="tag tag-right" aria-hidden="true">Aujourd’hui<\/span>/);
        expect(body).toMatch(/<div class="grip" aria-hidden="true">/);
        expect(body).toContain('style="--curtain: 35%;"');
    });

    it('ramène une valeur hors bornes dans le cadre', () => {
        expect(draw(EraCurtain, { value: 180, left: '1930', right: '2030' })).toContain(
            'value="100"',
        );
    });
});

describe('EraCompareBar (choix de la présentation)', () => {
    it('propose Rideau et Différences en boutons radio groupés', () => {
        const body = draw(EraCompareBar, {
            eras: ERAS,
            currentEra: 2030,
            from: 1930,
            to: 2030,
            mode: 'curtain',
        });
        expect(body).toContain('<fieldset class="modes"><legend class="modes-label">Présentation');
        const radios = [
            ...body.matchAll(/<input type="radio" name="[^"]+" value="(\w+)"([^>]*)>/g),
        ];
        expect(radios.map((r) => r[1])).toEqual(['curtain', 'changes']);
        expect(radios[0][2]).toContain('checked');
        expect(radios[1][2]).not.toContain('checked');
        expect(body).toContain('<span>Rideau</span>');
        expect(body).toContain('<span>Différences</span>');
    });
});
