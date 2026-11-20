// Propositions « matériel sur une ligne » et « série de matériel »
// (resources/js/Components/Contribute/proposals.js) : contrôles des formulaires avant l'envoi (mêmes
// règles que LineMaterialContributions et MaterialSeriesContributions), identifiant Wikidata, faits
// montrés aux validateurs et à l'auteur.
import { describe, expect, it } from 'vitest';
import {
    LINE_MATERIAL_TYPE,
    SERIES_TYPE,
    lineMaterialErrors,
    lineMaterialFacts,
    normalizeQid,
    proposalFacts,
    proposalHeading,
    proposalKindKey,
    seriesChanges,
    seriesErrors,
    seriesFacts,
    seriesValueText,
    sourceErrors,
} from '../../resources/js/Components/Contribute/proposals.js';
import { confidenceKey } from '../../resources/js/lib/materialFacts.js';

const DICT = {
    'editor.errors.bad_date': 'Date invalide',
    'editor.errors.bad_url': 'Lien invalide',
    'proposals.errors.line_required': 'Ligne ?',
    'proposals.errors.material_required': 'Série ?',
    'proposals.event.errors.places': 'Deux extrémités',
    'proposals.event.errors.places_same': 'Extrémités identiques',
    'proposals.event.errors.source_title': 'Titre ?',
    'proposals.event.errors.source_url': 'Adresse ?',
    'proposals.event.errors.source_license': 'Licence ?',
    'proposals.event.source_kinds.print': 'Ouvrage papier',
    'proposals.line_material.errors.end_before_start': 'Fin avant le début',
    'proposals.line_material.errors.section_without_date': 'Portion sans date',
    'proposals.line_material.errors.quote_length': 'De {min} à {max} caractères',
    'proposals.line_material.errors.quote_required': 'Phrase ?',
    'proposals.line_material.circulations.regular': 'Service régulier',
    'proposals.series.errors.material': 'Série à corriger ?',
    'proposals.series.errors.name': 'Nom ?',
    'proposals.series.errors.type': 'Catégorie ?',
    'proposals.series.errors.year': 'Année depuis {min}',
    'proposals.series.errors.years_order': 'Retrait avant la mise en service',
    'proposals.series.errors.qid_format': 'Identifiant ?',
    'proposals.series.none': 'non renseigné',
    'proposals.series.gauges.1435': '1 435 mm (voie normale)',
    'proposals.series.systems.dc_1500': '1 500 V continu',
    'proposals.series.systems.ac_25k': '25 kV 50 Hz',
    'materials.type.automotrice': 'Automotrice',
    'proposals.review.series': 'Série',
    'proposals.review.line': 'Ligne',
    'proposals.review.circulation': 'Circulation',
    'proposals.review.start': 'Début',
    'proposals.review.end': 'Fin',
    'proposals.review.section': 'Portion parcourue',
    'proposals.review.places_range': 'de {from} à {to}',
    'proposals.review.source': 'Source',
    'proposals.review.quote': 'Phrase citée',
    'proposals.review.mode': 'Proposition',
    'proposals.review.kind_line_material': 'Matériel sur une ligne',
    'proposals.review.kind_series_new': 'Nouvelle série',
    'proposals.review.kind_series_correction': 'Correction d’une série',
    'proposals.review.wikidata_item': 'Élément Wikidata',
    'proposals.review.fields.name': 'Nom',
    'proposals.review.fields.type': 'Catégorie',
    'proposals.review.fields.gauge_mm': 'Écartement',
    'proposals.review.fields.electrification': 'Électrification',
    'proposals.review.fields.in_service_from': 'Mise en service',
};
const t = (key, opts = {}) =>
    (DICT[key] ?? key).replace(/\{(\w+)\}/g, (_, k) => String(opts[k] ?? ''));
const NOW = new Date('2026-11-20T12:00:00Z');

const source = {
    source_kind: 'print',
    source_title: 'La Vie du Rail, n° 640',
    source_url: null,
    source_license: null,
};
const link = (overrides = {}) => ({
    line_slug: 'rfn-972000',
    material_slug: 'z-1200',
    circulation: 'regular',
    start_precision: 'year',
    start_date: '1913',
    end_precision: 'month',
    end_date: '1965-07',
    section_from: null,
    section_to: null,
    quote: null,
    ...source,
    ...overrides,
});

describe('source d’une proposition', () => {
    it('demande un titre, une adresse sauf pour un ouvrage papier, une licence pour un document', () => {
        expect(sourceErrors(source, t)).toEqual({});
        expect(sourceErrors({ ...source, source_kind: 'web' }, t)).toEqual({
            source_url: 'Adresse ?',
        });
        expect(
            sourceErrors({ ...source, source_kind: 'document', source_url: 'ftp://x' }, t),
        ).toEqual({ source_url: 'Lien invalide', source_license: 'Licence ?' });
        expect(sourceErrors({ ...source, source_title: 'JO' }, t)).toEqual({
            source_title: 'Titre ?',
        });
    });
});

describe('formulaire « matériel sur une ligne »', () => {
    it('accepte une proposition complète, avec ou sans période', () => {
        expect(lineMaterialErrors(link(), t, { now: NOW })).toEqual({});
        expect(
            lineMaterialErrors(
                link({
                    start_precision: null,
                    start_date: null,
                    end_precision: null,
                    end_date: null,
                }),
                t,
                { now: NOW },
            ),
        ).toEqual({});
    });

    it('demande la ligne et la série', () => {
        expect(lineMaterialErrors(link({ line_slug: '', material_slug: '' }), t)).toMatchObject({
            line_slug: 'Ligne ?',
            material_slug: 'Série ?',
        });
    });

    it('refuse une date impossible et une fin avant le début', () => {
        expect(
            lineMaterialErrors(link({ start_precision: 'day', start_date: '1913-02-30' }), t, {
                now: NOW,
            }),
        ).toEqual({ start_date: 'Date invalide' });
        expect(
            lineMaterialErrors(link({ end_precision: 'year', end_date: '1900' }), t, { now: NOW }),
        ).toEqual({
            end_date: 'Fin avant le début',
        });
        // Même année, précisions différentes : le mois de fin vient après le 1er janvier.
        expect(
            lineMaterialErrors(link({ end_precision: 'month', end_date: '1913-03' }), t, {
                now: NOW,
            }),
        ).toEqual({});
    });

    it('veut les deux extrémités d’une portion, différentes, avec une date', () => {
        expect(lineMaterialErrors(link({ section_from: 'Puteaux' }), t, { now: NOW })).toEqual({
            section_to: 'Deux extrémités',
        });
        expect(
            lineMaterialErrors(link({ section_from: 'Puteaux', section_to: 'puteaux' }), t, {
                now: NOW,
            }),
        ).toEqual({ section_to: 'Extrémités identiques' });
        expect(
            lineMaterialErrors(
                link({
                    start_precision: null,
                    start_date: null,
                    end_precision: null,
                    end_date: null,
                    section_from: 'Puteaux',
                    section_to: 'Issy-Plaine',
                }),
                t,
                { now: NOW },
            ),
        ).toEqual({ start_date: 'Portion sans date' });
    });

    it('rend la phrase obligatoire quand le lien contredit une règle, et borne sa longueur', () => {
        expect(lineMaterialErrors(link(), t, { quoteRequired: true, now: NOW })).toEqual({
            quote: 'Phrase ?',
        });
        expect(lineMaterialErrors(link({ quote: 'Trop court' }), t, { now: NOW })).toEqual({});
        expect(lineMaterialErrors(link({ quote: 'Court' }), t, { now: NOW })).toEqual({
            quote: 'De 10 à 600 caractères',
        });
    });
});

describe('identifiant Wikidata', () => {
    it('lit un identifiant seul ou l’adresse d’un élément', () => {
        expect(normalizeQid('q3574398')).toBe('Q3574398');
        expect(normalizeQid(' https://www.wikidata.org/wiki/Q3574398 ')).toBe('Q3574398');
        expect(normalizeQid('https://m.wikidata.org/wiki/Special:EntityPage/Q42#P31')).toBe('Q42');
        expect(normalizeQid('http://www.wikidata.org/entity/Q42')).toBe('Q42');
        expect(normalizeQid('Q0123')).toBeNull();
        expect(normalizeQid('https://fr.wikipedia.org/wiki/Q42')).toBeNull();
        expect(normalizeQid('')).toBeNull();
    });
});

describe('formulaire « série de matériel »', () => {
    const series = (overrides = {}) => ({
        mode: 'new',
        material_slug: null,
        name: 'Z 1200',
        type: 'automotrice',
        in_service_from: '1913',
        in_service_to: '1976',
        wikidata_id: 'Q3574398',
        ...source,
        ...overrides,
    });

    it('accepte une nouvelle série complète', () => {
        expect(seriesErrors(series(), t, { now: NOW })).toEqual({});
    });

    it('demande nom et catégorie pour une nouvelle série, la série pour une correction', () => {
        expect(seriesErrors(series({ name: 'Z', type: '' }), t, { now: NOW })).toEqual({
            name: 'Nom ?',
            type: 'Catégorie ?',
        });
        expect(
            seriesErrors(series({ mode: 'correction', name: '', type: '' }), t, { now: NOW }),
        ).toEqual({ material_slug: 'Série à corriger ?' });
    });

    it('vérifie les années et leur ordre', () => {
        expect(seriesErrors(series({ in_service_from: '1700' }), t, { now: NOW })).toEqual({
            in_service_from: 'Année depuis 1800',
        });
        expect(seriesErrors(series({ in_service_to: '19' }), t, { now: NOW })).toEqual({
            in_service_to: 'Année depuis 1800',
        });
        expect(seriesErrors(series({ in_service_to: '1900' }), t, { now: NOW })).toEqual({
            in_service_to: 'Retrait avant la mise en service',
        });
    });

    it('refuse un identifiant Wikidata illisible', () => {
        expect(seriesErrors(series({ wikidata_id: 'Z 1200' }), t, { now: NOW })).toEqual({
            wikidata_id: 'Identifiant ?',
        });
    });
});

describe('affichage des propositions', () => {
    const linkPayload = {
        line_name: 'Ligne de Puteaux à Issy-Plaine',
        line_code: '972000',
        material_name: 'Z 1200',
        circulation: 'regular',
        period: {
            start: null,
            end: { date: '1965-01-01', precision: 'year' },
            from: 'Puteaux',
            to: 'Issy-Plaine',
        },
        source: { kind: 'print', title: 'La Vie du Rail, n° 640', url: null, license: null },
        quote: 'Tous les mouvements sont assurés par les Z 1200.',
    };

    it('montre la série, la ligne, la période, la source et la phrase citée', () => {
        const facts = lineMaterialFacts(linkPayload, t, 'fr');
        expect(facts.map((f) => f.id)).toEqual([
            'series',
            'line',
            'circulation',
            'end',
            'section',
            'source',
            'quote',
        ]);
        expect(facts.find((f) => f.id === 'line').text).toBe(
            'Ligne de Puteaux à Issy-Plaine 972000',
        );
        expect(facts.find((f) => f.id === 'section').text).toBe('de Puteaux à Issy-Plaine');
        expect(facts.find((f) => f.id === 'source')).toMatchObject({
            href: null,
            note: 'Ouvrage papier',
        });
        expect(facts.find((f) => f.id === 'end').text).toContain('1965');
    });

    it('liste les valeurs d’une nouvelle série et les changements d’une correction', () => {
        expect(
            seriesChanges(
                {
                    mode: 'new',
                    values: { name: 'Z 1200', type: 'automotrice', gauge_mm: 1435 },
                },
                t,
            ),
        ).toEqual([
            { field: 'name', label: 'Nom', before: null, after: 'Z 1200' },
            { field: 'type', label: 'Catégorie', before: null, after: 'Automotrice' },
            {
                field: 'gauge_mm',
                label: 'Écartement',
                before: null,
                after: '1 435 mm (voie normale)',
            },
        ]);
        expect(
            seriesChanges(
                {
                    mode: 'correction',
                    changes: {
                        electrification: { before: null, after: ['dc_1500', 'ac_25k'] },
                        in_service_from: { before: 1920, after: 1913 },
                    },
                },
                t,
            ),
        ).toEqual([
            { field: 'in_service_from', label: 'Mise en service', before: '1920', after: '1913' },
            {
                field: 'electrification',
                label: 'Électrification',
                before: 'non renseigné',
                after: '1 500 V continu, 25 kV 50 Hz',
            },
        ]);
        expect(seriesValueText('builder', '', t)).toBe('non renseigné');
    });

    it('montre l’élément Wikidata vérifié d’une série', () => {
        const facts = seriesFacts(
            {
                mode: 'new',
                material_name: 'Z 1200',
                wikidata: { id: 'Q3574398', label: 'Z 1200', description: 'automotrices' },
                source: { kind: 'print', title: 'Référence' },
            },
            t,
        );
        expect(facts.map((f) => f.id)).toEqual(['mode', 'series', 'wikidata', 'source']);
        expect(facts[2]).toMatchObject({
            text: 'Q3574398, Z 1200',
            href: 'https://www.wikidata.org/wiki/Q3574398',
        });
    });

    it('nomme le type de chaque proposition et sa fiche', () => {
        expect(proposalKindKey({ type: LINE_MATERIAL_TYPE })).toBe(
            'proposals.review.kind_line_material',
        );
        expect(proposalKindKey({ type: SERIES_TYPE, payload: { mode: 'correction' } })).toBe(
            'proposals.review.kind_series_correction',
        );
        expect(proposalKindKey({ type: 'line_segment' })).toBeNull();
        expect(proposalHeading({ material_name: 'Z 1200' })).toBe('Z 1200');
        expect(proposalFacts({ type: 'line_trace', payload: {} }, t, 'fr')).toEqual([]);
        expect(
            proposalFacts({ type: LINE_MATERIAL_TYPE, payload: linkPayload }, t, 'fr'),
        ).toHaveLength(7);
    });
});

describe('fiabilité d’un lien proposé par un contributeur', () => {
    it('a ses propres libellés sur la fiche de la série', () => {
        expect(confidenceKey('high', 'contribution')).toBe(
            'materials.confidence.high_contribution',
        );
        expect(confidenceKey('low', 'contribution')).toBe('materials.confidence.low_contribution');
        expect(confidenceKey('high', 'wikidata')).toBe('materials.confidence.high_wikidata');
    });
});
