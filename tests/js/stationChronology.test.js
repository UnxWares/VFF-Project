// Chronologie de la fiche gare : place des vues aériennes parmi les dates, libellé de précision, et
// rendu côté serveur du composant (sources, lignes, accidents, date non retenue, vue aérienne).
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { entryYear, precisionKey, withAerialViews } from '$lib/stationChronology.js';
import StationChronology from '../../resources/js/Components/Stations/StationChronology.svelte';

const entry = (date, extra = {}) => ({
    kind: 'event',
    type: 'opening',
    date,
    precision: 'day',
    after: null,
    estimated: false,
    retained: true,
    source: 'wikidata',
    source_url: null,
    also: [],
    note: null,
    note_lang: null,
    lines: [],
    event_id: null,
    accident: null,
    name: null,
    ...extra,
});
const VIEWS = [
    { period: '1965-1980', from: 1965, to: 1980, url: '/media/aerial/x/1965-1980.jpg' },
    { period: '1950-1965', from: 1950, to: 1965, url: '/media/aerial/x/1950-1965.jpg' },
    { period: '1980-1995', from: 1980, to: 1995, url: '/media/aerial/x/1980-1995.jpg' },
];

describe('withAerialViews', () => {
    it('places each view before the first date of its period, and the later ones at the end', () => {
        const items = withAerialViews(
            [entry('1901-04-06'), entry('1949-12-31', { type: 'closure' }), entry('1970-05-01')],
            VIEWS,
        );
        expect(items.map((i) => (i.kind === 'aerial' ? i.view.period : i.entry.date))).toEqual([
            '1901-04-06',
            '1949-12-31',
            '1950-1965',
            '1965-1980',
            '1970-05-01',
            '1980-1995',
        ]);
        // L'index de chaque entrée reste celui de la liste du serveur (clé stable du rendu).
        expect(items.filter((i) => i.kind === 'entry').map((i) => i.index)).toEqual([0, 1, 2]);
    });

    it('puts every view first for a station opened after 1995, and keeps an empty list empty', () => {
        expect(withAerialViews([entry('2014-02-28')], VIEWS).map((i) => i.kind)).toEqual([
            'aerial',
            'aerial',
            'aerial',
            'entry',
        ]);
        expect(withAerialViews([], [])).toEqual([]);
    });

    it('ranks a bracketed date at its lower bound', () => {
        expect(entryYear(entry('1960-12-31', { precision: 'range', after: 1950 }))).toBe(1951);
        expect(entryYear(entry('1960-12-31', { precision: 'range', after: null }))).toBe(1960);
        expect(entryYear(entry('1934-07-23'))).toBe(1934);
    });
});

describe('precisionKey', () => {
    it('names the precision of each date', () => {
        expect(precisionKey({ precision: 'year' })).toBe('stations.chronology.precision.year');
        expect(precisionKey({ precision: 'range' })).toBe('stations.chronology.precision.range');
        expect(precisionKey({})).toBe('stations.chronology.precision.day');
    });
});

describe('StationChronology', () => {
    setupI18n({
        locale: 'fr',
        translations: {
            'stations.events.opening': 'Ouverture',
            'stations.events.closure': 'Fermeture',
            'stations.events.inferred': 'Date déduite',
            'stations.events.inferred_hint': 'Date déduite des lignes.',
            'stations.events.none': 'Aucune date connue pour cette gare.',
            'stations.chronology.line_start': 'Début de la desserte',
            'stations.chronology.lines_start_one': 'Ligne desservie :',
            'stations.chronology.lines_start_other': 'Lignes desservies :',
            'stations.chronology.not_retained': 'Date non retenue',
            'stations.chronology.not_retained_hint': 'Date avancée par cette source.',
            'stations.chronology.source': 'Source :',
            'stations.chronology.also': 'Aussi : :list',
            'stations.chronology.precision.day': 'au jour près',
            'stations.chronology.precision.year': 'à l’année près',
            'stations.chronology.sources.line_events': 'chronologie de la ligne',
            'stations.chronology.accident_at_station': 'à la gare',
            'stations.chronology.aerial': 'Vue aérienne :from-:to',
            'stations.chronology.aerial_more': 'Comparer les trois époques',
            'stations.chronology.aerial_hint': 'Photographie de l’IGN.',
            'stations.aerial.alt': 'Photographie aérienne de la gare de :name entre :from et :to',
            'stations.sources.names.wikidata': 'Wikidata',
            'stations.sources.names.ohm': 'OpenHistoricalMap',
            'accidents.kinds.collision': 'Collision',
            'accidents.deaths_one': ':count mort',
            'accidents.deaths_other': ':count morts',
            'accidents.toll_unknown': 'Bilan inconnu',
            'common.new_tab': 'nouvel onglet',
        },
    });

    const html = (props) => render(StationChronology, { props }).body;

    it('shows each date with its precision, its source, the other sources and the lines', () => {
        const body = html({
            name: 'Noyelle-Vion',
            views: [VIEWS[1]],
            entries: [
                entry('1890-08-08', { source: 'ohm', retained: false }),
                entry('1895-12-01', {
                    source: 'wikidata',
                    source_url: 'https://www.wikidata.org/wiki/Q1',
                    also: [{ source: 'ohm', date: '1895-01-01', precision: 'year' }],
                    lines: [
                        {
                            slug: 'rfn-1',
                            code: '301000',
                            name: 'Ligne de Frévent à Lens',
                            role: 'start',
                        },
                    ],
                }),
                entry('1981-12-10', {
                    kind: 'line_start',
                    type: null,
                    source: 'line_events',
                    lines: [{ slug: 'rfn-2', code: null, name: 'RER D', role: 'start' }],
                }),
                entry('1925-04-09', {
                    kind: 'accident',
                    type: null,
                    source: 'vff_accidents',
                    accident: {
                        slug: 'collision-1925',
                        name: 'Collision à Paris',
                        kind: 'collision',
                        deaths: 2,
                        injuries: null,
                        distance_m: 0,
                        location_precision: 'station',
                        to_review: false,
                    },
                }),
            ],
        });
        expect(body).toContain('1er décembre 1895');
        expect(body).toContain('datetime="1895-12-01"');
        expect(body).toContain('Date non retenue');
        expect(body).toContain('au jour près');
        expect(body).toContain('href="https://www.wikidata.org/wiki/Q1"');
        expect(body).toContain('Aussi : OpenHistoricalMap (1895)');
        expect(body).toContain('Ligne desservie :');
        expect(body).toContain('href="/lines/rfn-1"');
        // Desserte datée par la ligne : la source mène à la chronologie de la ligne, sur le site.
        expect(body).toContain('href="/lines/rfn-2#chronicle"');
        expect(body).toContain('href="/accidents/collision-1925"');
        expect(body).toContain('Collision, à la gare. 2 morts');
        expect(body).toContain('Vue aérienne 1950-1965');
        expect(body).toContain('src="/media/aerial/x/1950-1965.jpg"');
        expect(body).toContain(
            'alt="Photographie aérienne de la gare de Noyelle-Vion entre 1950 et 1965"',
        );
    });

    it('says so when no date is known', () => {
        expect(html({ name: 'X', entries: [], views: VIEWS })).toContain(
            'Aucune date connue pour cette gare.',
        );
    });
});
