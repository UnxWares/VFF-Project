import { describe, expect, it } from 'vitest';
import {
    countByType,
    filterGreenways,
    filterRailways,
    fold,
    kmOf,
    operationFacts,
    pointsBounds,
    railwayPoints,
    seasonKey,
    sortRailways,
    wikidataUrl,
} from '../../resources/js/lib/touristRailways.js';

const rows = [
    {
        id: 'a',
        name: 'Train à vapeur des Cévennes',
        type: 'steam',
        operator: 'CITEV',
        regions: ['Occitanie'],
        departements: ['Gard'],
        lines: [{ name: 'Ligne de Nîmes' }],
        length_m: 13700,
        start_year: 1982,
        lat: 44.1,
        lng: 4.1,
    },
    {
        id: 'b',
        name: 'Vélorail du Larzac',
        type: 'velorail',
        operator: null,
        regions: ['Occitanie'],
        departements: ['Aveyron'],
        lines: [],
        length_m: null,
        start_year: 2001,
        lat: 43.9,
        lng: 3.1,
    },
    {
        id: 'c',
        name: 'Autorail Creusois',
        type: 'railcar',
        operator: null,
        regions: ['Nouvelle-Aquitaine'],
        departements: ['Creuse'],
        lines: [],
        length_m: 4000,
        start_year: null,
        lat: null,
        lng: null,
    },
];

describe('touristRailways', () => {
    it('fold retire accents, capitales et ponctuation', () => {
        expect(fold("Vélo-Rail de l'Étang")).toBe('velo rail de l etang');
        expect(fold(null)).toBe('');
    });

    it('filtre par type, région et recherche sans accents', () => {
        expect(filterRailways(rows, { type: 'velorail' }).map((r) => r.id)).toEqual(['b']);
        expect(filterRailways(rows, { region: 'Occitanie' }).map((r) => r.id)).toEqual(['a', 'b']);
        expect(filterRailways(rows, { q: 'cevennes' }).map((r) => r.id)).toEqual(['a']);
        expect(filterRailways(rows, { q: 'gard vapeur' }).map((r) => r.id)).toEqual(['a']);
        expect(filterRailways(rows, { q: 'citev' }).map((r) => r.id)).toEqual(['a']);
        expect(filterRailways(rows, { q: 'nimes' }).map((r) => r.id)).toEqual(['a']);
        expect(filterRailways(rows, { q: 'zzz' })).toEqual([]);
        expect(filterRailways(rows)).toHaveLength(3);
    });

    it('trie par nom, longueur (inconnues en dernier) et année (inconnues en dernier)', () => {
        expect(sortRailways(rows, 'name').map((r) => r.id)).toEqual(['c', 'a', 'b']);
        expect(sortRailways(rows, 'length').map((r) => r.id)).toEqual(['a', 'c', 'b']);
        expect(sortRailways(rows, 'since').map((r) => r.id)).toEqual(['a', 'b', 'c']);
        expect(rows.map((r) => r.id)).toEqual(['a', 'b', 'c']);
    });

    it('compte par type', () => {
        expect(countByType(rows, ['steam', 'velorail', 'tram'])).toEqual({
            steam: 1,
            velorail: 1,
            tram: 0,
        });
    });

    it('filtre les voies vertes par région et recherche', () => {
        const gw = [
            {
                name: 'Via Ardèche',
                regions: ['Auvergne-Rhône-Alpes'],
                line: { name: 'Ligne de Lyon' },
            },
            {
                name: 'Voie verte du Cher',
                regions: ['Centre-Val de Loire'],
                line: { name: 'Ligne de Bourges' },
            },
        ];
        expect(filterGreenways(gw, { region: 'Centre-Val de Loire' })).toHaveLength(1);
        expect(filterGreenways(gw, { q: 'ardeche' })[0].name).toBe('Via Ardèche');
        expect(filterGreenways(gw, { q: 'bourges' })[0].name).toBe('Voie verte du Cher');
    });

    it('arrondit les kilomètres au dixième', () => {
        expect(kmOf(13700)).toBe(13.7);
        expect(kmOf(1449)).toBe(1.4);
        expect(kmOf(null)).toBeNull();
    });

    it('ne met sur la carte que les exploitations localisées, et en donne l’emprise', () => {
        const points = railwayPoints(rows);
        expect(points.features).toHaveLength(2);
        expect(points.features[0].geometry.coordinates).toEqual([4.1, 44.1]);
        expect(pointsBounds(points)).toEqual([
            [3.1, 43.9],
            [4.1, 44.1],
        ]);
        expect(pointsBounds(railwayPoints([rows[2]]))).toBeNull();
    });

    it('donne l’adresse Wikidata', () => {
        expect(wikidataUrl('Q737163')).toBe('https://www.wikidata.org/wiki/Q737163');
        expect(wikidataUrl(null)).toBeNull();
    });
});

describe('operationFacts', () => {
    // Traduction de test : la clé (préfixe tourist. compris) et ses paramètres, sauf les saisons connues.
    const seasons = { 'season_text.de_fevrier_a_decembre': 'February to December' };
    const t = (key, vars) =>
        seasons[key] ??
        (vars
            ? `${key}|${Object.entries(vars)
                  .map(([k, v]) => `${k}=${v}`)
                  .join('|')}`
            : `tourist.${key}`);
    const r = (over = {}) => ({ section: null, season: null, opened_on: null, ...over });

    it('rien quand le catalogue ne connaît ni portion, ni saison, ni ouverture', () => {
        expect(operationFacts(r(), t, 'en')).toEqual([]);
    });

    it('portion exploitée, ou seulement son départ', () => {
        expect(
            operationFacts(r({ section: { from: 'Le Crotoy', to: 'Cayeux-sur-Mer' } }), t, 'fr'),
        ).toEqual([
            { key: 'section', text: 'section|from=Le Crotoy|to=Cayeux-sur-Mer', lang: null },
        ]);
        expect(
            operationFacts(r({ section: { from: 'Les Hôpitaux-Neufs', to: null } }), t, 'fr')[0]
                .text,
        ).toBe('section_from|from=Les Hôpitaux-Neufs');
    });

    it('saison traduite, ou gardée en français et marquée comme telle', () => {
        expect(operationFacts(r({ season: 'de février à décembre' }), t, 'en')).toEqual([
            { key: 'season', text: 'season|season=February to December', lang: null },
        ]);
        expect(operationFacts(r({ season: 'les jours de fête' }), t, 'de')).toEqual([
            { key: 'season', text: 'season|season=les jours de fête', lang: 'fr' },
        ]);
        expect(operationFacts(r({ season: 'les jours de fête' }), t, 'fr')[0].lang).toBeNull();
    });

    it('jour d’ouverture dans la langue de la page', () => {
        expect(operationFacts(r({ opened_on: '2012-06-28' }), t, 'fr')[0].text).toBe(
            'opened_on|date=28 juin 2012',
        );
        expect(operationFacts(r({ opened_on: '2012-06-28' }), t, 'de')[0].text).toBe(
            'opened_on|date=28. Juni 2012',
        );
        expect(operationFacts(r({ opened_on: 'inconnu' }), t, 'fr')).toEqual([]);
    });

    it('clé de saison sans accents ni ponctuation, apostrophes droites ou typographiques', () => {
        expect(seasonKey("d'avril à octobre")).toBe('d_avril_a_octobre');
        expect(seasonKey('d’avril à octobre')).toBe('d_avril_a_octobre');
        expect(seasonKey('du 1er mai au 31 octobre')).toBe('du_1er_mai_au_31_octobre');
    });
});
