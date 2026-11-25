// Voies d'une gare (lib/stationTracks.js, page /stations/{slug}/voies, section de la fiche) : quais regroupés, formes
// des quais dans le dessin (quai central entre deux voies voisines, bord seul le long de sa voie), tableau des quais,
// liens entre la gare et le schéma de ligne. Jeu d'essai réel : réponse de GET /api/v1/stations/poitiers/track-layout
// du 25/11/2026 (RINF © ERA, EUPL 1.2 ; OpenStreetMap, ODbL ; « Liste des quais » de SNCF Réseau, ODbL).
import { readFileSync } from 'node:fs';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import {
    hasStationTracks,
    heightCm,
    linePlatforms,
    lineTracksHref,
    platformGroups,
    platformShapes,
    stationTracksUrl,
} from '$lib/stationTracks.js';
import { buildAxis, foldLayout, laneRows, normalize, routeRows } from '$lib/trackLayout.js';
import TrackDiagram from '$components/Lines/TrackDiagram.svelte';
import TrackRouteTable from '$components/Lines/TrackRouteTable.svelte';
import StationPlatformsTable from '$components/Stations/StationPlatformsTable.svelte';

const poitiers = JSON.parse(
    readFileSync(new URL('./fixtures/stationTracks/poitiers.json', import.meta.url), 'utf8'),
);
const t = (k, o) => (o ? `${k} ${JSON.stringify(o)}` : k);

describe('stationTracks : adresses et contenu', () => {
    it('demande l’API de la gare, une ligne ou un aperçu', () => {
        expect(stationTracksUrl('poitiers')).toBe('/api/v1/stations/poitiers/track-layout');
        expect(stationTracksUrl('l’isle', { kinds: ['operational_points'], line: '570000' })).toBe(
            '/api/v1/stations/l%E2%80%99isle/track-layout?line=570000&kinds=operational_points',
        );
        expect(lineTracksHref('rfn-570000', 336560.4)).toBe('/lines/rfn-570000/voies?pk=336560');
        expect(lineTracksHref('rfn-570000', null)).toBe('/lines/rfn-570000/voies');
    });

    it('n’a rien à montrer sans ligne ni quai', () => {
        expect(hasStationTracks(null)).toBe(false);
        expect(hasStationTracks({ lines: [], platforms: [] })).toBe(false);
        expect(hasStationTracks({ lines: [], platforms: [{ ref: 'Quai A' }] })).toBe(true);
        expect(hasStationTracks(poitiers)).toBe(true);
    });
});

describe('stationTracks : quais', () => {
    it('regroupe les bords de quai de Poitiers en quatre quais centraux', () => {
        const groups = platformGroups(poitiers.platforms);
        expect(groups.map((g) => [g.ref, g.tracks, g.length_m, g.heights_mm])).toEqual([
            ['Quai 1', ['41', '43'], 153, [380]],
            ['Quai 2', ['V1', 'Z'], 474, [580]],
            ['Quai 3', ['4', 'V2'], 463, [380]],
            ['Quai 4', ['6', '8'], 390, [300]],
        ]);
        expect(groups.every((g) => g.placed && !g.approx)).toBe(true);
        expect(groups.every((g) => g.missing.length === 0)).toBe(true);
        expect(groups[1].sources).toEqual(['rinf', 'sncf']);
        expect(heightCm(580)).toBe(58);
        expect(linePlatforms(poitiers, '570000')).toHaveLength(8);
        expect(linePlatforms(poitiers, '999999')).toHaveLength(0);
    });

    it('dessine un quai central entre deux voies voisines, sinon un bord le long de sa voie', () => {
        const rows = { a: 2, b: 3, c: 0, d: 5, e: 1 };
        const edge = (id, ref, track, from = 100, to = 300, precision = 'exact') => ({
            id,
            ref,
            track,
            pk_from_m: from,
            pk_to_m: to,
            line_code: '1',
            precision,
        });
        const shapes = platformShapes(
            [
                edge('1', 'Quai 1', 'a'),
                edge('2', 'Quai 1', 'b', 120, 320),
                edge('3', 'Quai 2', 'c'),
                edge('4', 'Quai 2', 'd'),
                edge('5', 'Quai 3', 'e', 100, 300, 'approximate'),
                // Sans voie dessinée ou sans position : rien à dessiner.
                edge('6', 'Quai 4', 'repliée'),
                { ...edge('7', 'Quai 5', 'a'), pk_from_m: null },
            ],
            (id) => rows[id] ?? null,
            0.5,
        );
        expect(shapes.map((s) => [s.ref, s.side, s.row, s.from_m, s.to_m, s.approx])).toEqual([
            ['Quai 2', 'above', 0, 100, 300, false],
            ['Quai 3', 'below', 1, 100, 300, true],
            ['Quai 1', 'between', 2.5, 100, 320, false],
            ['Quai 2', 'below', 5, 100, 300, false],
        ]);
    });

    it('écrit le tableau des quais : voies bordées, longueur, hauteur en centimètres, sources', () => {
        const { body } = render(StationPlatformsTable, {
            props: { platforms: poitiers.platforms, lang: 'fr', t },
        });
        const rows = [...body.matchAll(/<th scope="row"[^>]*>([^<]+)<\/th>/g)].map((m) => m[1]);
        expect(rows).toEqual(['Quai 1', 'Quai 2', 'Quai 3', 'Quai 4']);
        expect(body).toContain('V1, Z');
        expect(body).toContain('tracks.station.platforms.metres {"value":"474"}');
        expect(body).toContain('tracks.station.platforms.cm {"value":"58"}');
        expect(body).toMatch(/<caption[^>]*>tracks\.station\.platforms\.caption<\/caption>/);
        expect(body).not.toContain('tracks.station.platforms.line');
        expect(body).not.toContain('tracks.station.platforms.unplaced');
    });

    it('dit la voie d’un quai que le schéma n’a pas (Paris-Austerlitz, « Quai 16/17 » : voie 16 absente d’OSM)', () => {
        // Bords du RINF de Paris-Austerlitz tels que l'API les sert le 25/11/2026.
        const platforms = [
            {
                id: 'rinf:pe:a',
                ref: 'Quai 16/17',
                track_ref: '16',
                line_code: '570000',
                track: null,
                pk_from_m: null,
                pk_to_m: null,
                length_m: 410,
                height_mm: 550,
                source: 'rinf',
                pk_source: null,
                precision: null,
            },
            {
                id: 'rinf:pe:b',
                ref: 'Quai 16/17',
                track_ref: '17',
                line_code: '570000',
                track: 'osm:way:152337196',
                pk_from_m: 131,
                pk_to_m: 550,
                length_m: 419,
                height_mm: 550,
                source: 'rinf',
                pk_source: 'vff',
                precision: 'approximate',
            },
            {
                id: 'rinf:pe:c',
                ref: 'Quai 18/19',
                track_ref: '18',
                line_code: '570000',
                track: 'osm:way:24156364',
                pk_from_m: 131,
                pk_to_m: 600,
                length_m: 469,
                height_mm: 550,
                source: 'rinf',
                pk_source: 'vff',
                precision: 'approximate',
            },
            {
                id: 'rinf:pe:d',
                ref: 'Quai 18/19',
                track_ref: '19',
                line_code: '570000',
                track: 'osm:way:256454080',
                pk_from_m: 131,
                pk_to_m: 641,
                length_m: 546,
                height_mm: 550,
                source: 'rinf',
                pk_source: 'vff',
                precision: 'approximate',
            },
        ];
        const groups = platformGroups(platforms);
        expect(groups.map((g) => [g.ref, g.placed, g.missing])).toEqual([
            ['Quai 16/17', true, ['16']],
            ['Quai 18/19', true, []],
        ]);
        const { body } = render(StationPlatformsTable, {
            props: { platforms, lang: 'fr', t, drawing: true },
        });
        expect(body).toContain(
            'tracks.station.platforms.unplaced_tracks {"count":1,"tracks":"16"}',
        );
        expect(body.match(/unplaced_tracks/g)).toHaveLength(2);
    });
});

describe('stationTracks : dessin et tableau de parcours', () => {
    const block = poitiers.lines[0];
    const data = normalize(block.track_layout);

    it('dessine les quais de Poitiers le long de leurs voies, le quai central 1 entre les voies 41 et 43 (le tableau des quais les nomme)', () => {
        const axis = buildAxis(data);
        const props = {
            data,
            axis,
            rows: routeRows(data),
            zoom: 1,
            printing: true,
            label: 'Schéma',
            helpId: 'aide',
            lang: 'fr',
            t,
            onpick: () => {},
        };
        const without = render(TrackDiagram, { props }).body;
        expect(without).not.toContain('class="platform');
        const { body } = render(TrackDiagram, {
            props: { ...props, platforms: linePlatforms(poitiers, block.line.code) },
        });
        const rects = [
            ...body.matchAll(
                /<rect class="platform[^"]*" x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="([\d.]+)"/g,
            ),
        ].map((m) => ({ x: +m[1], y: +m[2], w: +m[3], h: +m[4] }));
        const lanes = laneRows(data.tracks);
        const byId = new Map(data.tracks.map((tr) => [tr.id, tr]));
        const shapes = platformShapes(
            linePlatforms(poitiers, block.line.code),
            (id) => (byId.has(id) ? lanes.row(byId.get(id).lane) : null),
            (lanes.row(0) + lanes.row(1)) / 2,
        );
        expect(rects).toHaveLength(shapes.length);
        // Voies 41 et 43 sur deux rangées voisines : un seul quai, entre elles, plus haut qu'un bord seul.
        const island = shapes.find((s) => s.ref === 'Quai 1');
        expect(island?.side).toBe('between');
        expect(rects.filter((r) => r.h > 12)).toHaveLength(
            shapes.filter((s) => s.side === 'between').length,
        );
    });

    it('garde dessinées les voies à quai d’une grande gare, que le repli cacherait', () => {
        const zones = buildAxis(data).zones;
        const atPlatform = new Set(
            linePlatforms(poitiers, block.line.code)
                .map((p) => p.track)
                .filter(Boolean),
        );
        const drawn = (view) => new Set(view.data.tracks.map((tr) => tr.id));
        // Repli de la page d'une ligne : à Poitiers, les voies à quai Z, 4, 6, 8, 41 et 43 sont dans les groupes.
        const line = drawn(foldLayout(data, zones));
        expect([...atPlatform].filter((id) => !line.has(id)).length).toBeGreaterThanOrEqual(6);
        // Page de la gare : toutes ses voies à quai restent, les groupes gardent les autres voies.
        const station = foldLayout(data, zones, new Set(), atPlatform);
        expect([...atPlatform].every((id) => drawn(station).has(id))).toBe(true);
        expect(station.groups.length).toBeGreaterThan(0);
    });

    it('lie chaque gare du tableau à sa fiche et à ses voies, sauf la gare de la page', () => {
        const rows = routeRows(data);
        const tracksById = new Map(data.tracks.map((tr) => [tr.id, tr]));
        const props = { rows, lang: 'fr', t, onselect: () => {}, tracksById };
        const line = render(TrackRouteTable, { props }).body;
        expect(line).toContain('href="/stations/poitiers"');
        expect(line).toContain('href="/stations/poitiers/voies"');
        const station = render(TrackRouteTable, {
            props: { ...props, stationSlug: 'poitiers' },
        }).body;
        expect(station).not.toContain('href="/stations/poitiers/voies"');
        expect(station).not.toContain('href="/stations/poitiers"');
    });
});
