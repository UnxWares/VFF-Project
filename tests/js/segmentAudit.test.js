import { describe, expect, it } from 'vitest';
import {
    CHECKS,
    GRADE_COLORS,
    GRADES,
    checkShares,
    cleanParams,
    collectionBounds,
    colorExpression,
    currentParams,
    eventDate,
    evidenceText,
    exportUrl,
    formParams,
    formatKm,
    gradeColor,
    gradeKey,
    gradeSeverity,
    pageUrl,
    percentNumber,
    segmentCollection,
    sortSegments,
} from '../../resources/js/lib/segmentAudit.js';

// Traduction factice : la clé et ses paramètres, ou le repli s'il est donné pour une clé inconnue.
const KNOWN = new Set([
    'audit_segments.reasons.sources_agree',
    'audit_segments.reasons.rule_passenger_only_off_lgv',
    'audit_segments.statuses.in_service',
    'audit_segments.traffic.mixed',
    'audit_segments.traffic.freight',
    'audit_segments.sources.bdtopo',
    'audit_segments.sources.wikipedia',
    'audit_segments.classes.closed',
    'audit_segments.evidence.kinds.events',
    'editor.event_types.closure',
]);
const t = (key, opts = {}) => {
    const { defaultValue, ...params } = opts;
    if (
        !KNOWN.has(key) &&
        !key.startsWith('audit_segments.evidence.') &&
        defaultValue !== undefined
    ) {
        return String(defaultValue);
    }
    const args = Object.entries(params)
        .map(([k, v]) => `${k}=${v}`)
        .join('|');
    return args ? `${key}{${args}}` : key;
};
const ctx = { t, lang: 'fr', thresholds: { reference_radius_m: 50, near_date_years: 5 } };

describe('notes', () => {
    it('range les notes par gravité comme AuditGrade::severity', () => {
        expect(['poor', 'fair', 'unknown', 'good'].map(gradeSeverity)).toEqual([3, 2, 1, 0]);
        expect(gradeSeverity(null)).toBe(1);
    });

    it('ramène une note absente ou inattendue à « unknown »', () => {
        expect(gradeKey('fair')).toBe('fair');
        expect(gradeKey(null)).toBe('unknown');
        expect(gradeKey('bizarre')).toBe('unknown');
    });

    it('a une couleur par note et par thème', () => {
        for (const theme of ['light', 'dark']) {
            expect(Object.keys(GRADE_COLORS[theme]).sort()).toEqual([...GRADES].sort());
        }
        expect(gradeColor('poor', false)).toBe(GRADE_COLORS.light.poor);
        expect(gradeColor('nope', true)).toBe(GRADE_COLORS.dark.unknown);
    });

    it('colore la carte selon la note choisie, la pire par défaut', () => {
        const expr = colorExpression('placement', false);
        expect(expr.slice(0, 2)).toEqual(['match', ['get', 'placement']]);
        expect(expr.at(-1)).toBe(GRADE_COLORS.light.unknown);
        expect(colorExpression('autre', true)[1]).toEqual(['get', 'worst']);
        expect(colorExpression('worst', true)).toContain(GRADE_COLORS.dark.good);
    });
});

describe('adresses', () => {
    it('retire les paramètres vides et la page 1', () => {
        expect(
            cleanParams({ era: 1930, check: '', grade: null, q: '  Paris ', page: 1, x: 'y' }),
        ).toEqual({ era: '1930', q: 'Paris' });
    });

    it('construit l’adresse de la page et des exports', () => {
        const params = currentParams(2030, { check: 'trace', grade: 'poor', q: null }, 3);
        expect(pageUrl({ ...params, line: 'paris-lyon' })).toBe(
            '/validate/audit/segments?era=2030&check=trace&grade=poor&line=paris-lyon&page=3',
        );
        expect(exportUrl(params)).toBe(
            '/validate/audit/segments/export.csv?era=2030&check=trace&grade=poor',
        );
        expect(exportUrl({ era: 1950, line: 'x' })).toBe(
            '/validate/audit/segments/export.csv?era=1950&line=x',
        );
        expect(pageUrl({})).toBe('/validate/audit/segments');
    });

    it('lit le formulaire de filtres', () => {
        const data = new FormData();
        data.set('era', '1930');
        data.set('check', '');
        data.set('region', '75');
        data.set('q', ' 610000 ');
        expect(formParams(data)).toEqual({ era: '1930', region: '75', q: '610000' });
        expect(formParams({ grade: 'fair' })).toEqual({ grade: 'fair' });
    });
});

describe('tronçons', () => {
    const line = (coords) => ({ type: 'LineString', coordinates: coords });

    it('met le plus mal noté d’abord', () => {
        const sorted = sortSegments([
            { segment_id: 1, worst_grade: 'good', score: 0, length_m: 900 },
            { segment_id: 2, worst_grade: 'poor', score: 25, length_m: 10 },
            { segment_id: 3, worst_grade: 'poor', score: 50, length_m: 10 },
            { segment_id: 4, worst_grade: 'unknown', score: 6, length_m: 10 },
        ]);
        expect(sorted.map((s) => s.segment_id)).toEqual([3, 2, 4, 1]);
    });

    it('construit la collection GeoJSON des tronçons qui ont un tracé', () => {
        const fc = segmentCollection([
            {
                segment_id: 7,
                worst_grade: 'fair',
                grades: { classification: 'good', placement: 'fair', accuracy: null },
                geometry: line([
                    [2, 48],
                    [2.5, 48.5],
                ]),
            },
            { segment_id: 8, geometry: null },
            { segment_id: 9, geometry: line([]) },
        ]);
        expect(fc.features).toHaveLength(1);
        expect(fc.features[0].properties).toEqual({
            segment_id: 7,
            worst: 'fair',
            classification: 'good',
            placement: 'fair',
            accuracy: 'unknown',
            trace: 'unknown',
        });
    });

    it('calcule l’emprise, lignes simples et multiples', () => {
        const fc = {
            features: [
                {
                    geometry: line([
                        [1, 45],
                        [3, 47],
                    ]),
                },
                {
                    geometry: {
                        type: 'MultiLineString',
                        coordinates: [
                            [
                                [0.5, 46],
                                [2, 49],
                            ],
                        ],
                    },
                },
            ],
        };
        expect(collectionBounds(fc)).toEqual([
            [0.5, 45],
            [3, 49],
        ]);
        expect(collectionBounds({ features: [] })).toBeNull();
    });
});

describe('résumé et nombres', () => {
    it('répartit la longueur de chaque contrôle par note', () => {
        const rows = checkShares({
            classification: {
                good: { segments: 3, length_m: 750 },
                poor: { segments: 1, length_m: 250 },
            },
        });
        expect(rows.map((r) => r.check)).toEqual(CHECKS);
        expect(rows[0].total_m).toBe(1000);
        expect(rows[0].good_share).toBe(0.75);
        expect(rows[0].parts.find((p) => p.grade === 'poor')).toEqual({
            grade: 'poor',
            length_m: 250,
            segments: 1,
            share: 0.25,
        });
        expect(rows[1].good_share).toBe(0);
    });

    it('formate kilomètres, pourcentages et dates', () => {
        expect(formatKm(12345, 'fr')).toBe('12,3 km');
        expect(formatKm(512000, 'en')).toBe('512 km');
        expect(formatKm(null, 'fr')).toBe('');
        expect(percentNumber(0.934, 'fr')).toBe('93,4');
        expect(eventDate('1939-03-01', 'fr')).toBe('1 mars 1939');
        expect(eventDate('1939', 'fr')).toBe('1939');
        expect(eventDate('inconnue', 'fr')).toBe('inconnue');
    });
});

describe('preuves en phrases', () => {
    it('classement : raisons, règles, état dans VFF et avis des sources', () => {
        const out = evidenceText(
            'classification',
            {
                reasons: ['sources_agree'],
                rules: ['passenger_only_off_lgv'],
                actual: { status: 'in_service', traffic: 'mixed', heritage: true },
                verdicts: [
                    {
                        source: 'wikipedia',
                        kind: 'events',
                        expected: { class: 'closed', traffic: null },
                        agrees: false,
                        why: 'expected_closed',
                        near_date: true,
                        weak: true,
                        events: [{ id: 1, type: 'closure', date: '1939-03-01' }],
                    },
                    {
                        source: 'inconnue-x',
                        kind: 'current',
                        expected: { class: 'open', traffic: 'freight' },
                        agrees: true,
                        why: null,
                        observed: { statut: 'Exploitée', vide: '' },
                    },
                ],
            },
            ctx,
        );
        expect(out.reasons).toEqual(['audit_segments.reasons.sources_agree']);
        expect(out.facts[0]).toBe('audit_segments.reasons.rule_passenger_only_off_lgv');
        expect(out.facts[1]).toBe(
            'audit_segments.evidence.actual{value=audit_segments.statuses.in_service, audit_segments.traffic.mixed, audit_segments.evidence.heritage}',
        );
        expect(out.verdicts[0]).toContain('source=audit_segments.sources.wikipedia');
        expect(out.verdicts[0]).toContain('audit_segments.evidence.contradicts');
        expect(out.verdicts[0]).toContain('class=audit_segments.classes.closed');
        expect(out.verdicts[0]).toContain('audit_segments.evidence.near_date{years=5}');
        expect(out.verdicts[0]).toContain('editor.event_types.closure 1 mars 1939');
        // « fermé » est déjà dit par « attend : fermé » : la raison n'est pas répétée.
        expect(out.verdicts[0]).not.toContain('reasons.expected_closed');
        expect(out.verdicts[1]).toContain('source=inconnue-x');
        expect(out.verdicts[1]).toContain('class=open, audit_segments.traffic.freight');
        expect(out.verdicts[1]).toContain('observed{value=statut Exploitée}');
    });

    it('placement : référence, distances et détail par source', () => {
        const out = evidenceText(
            'placement',
            {
                reasons: [],
                reference: 'bdtopo',
                same_source: true,
                coverage: 0.9,
                median_m: 1.234,
                p95_m: 4.5,
                references: { bdtopo: { points: 40, found: 36, median_m: 1.2, p95_m: null } },
            },
            ctx,
        );
        expect(out.facts[0]).toBe(
            'audit_segments.evidence.reference{source=audit_segments.sources.bdtopo} (audit_segments.evidence.same_source)',
        );
        expect(out.facts[1]).toBe(
            'audit_segments.evidence.distances{median=1,23|p95=4,5|coverage=90|radius=50}',
        );
        expect(out.facts[2]).toContain('found=36|points=40|median=1,2|p95=-');
    });

    it('exactitude : défauts comptés, jonctions, trous, gares', () => {
        const out = evidenceText(
            'accuracy',
            {
                reasons: ['hole'],
                max_turn_deg: 32.25,
                hairpins: 0,
                kinks: 2,
                junction_gaps: [{ m: 3.5, other_line: '610000' }],
                holes: [{ m: 120, unused_traces: ['osm:1', 'osm:2'] }],
                stations: [{ slug: 'x', name: 'Confolens', distance_m: 612.4 }],
            },
            ctx,
        );
        expect(out.facts).toEqual([
            'audit_segments.evidence.turn{deg=32,3}',
            'audit_segments.evidence.kinks{count=2}',
            'audit_segments.evidence.junction_gap{m=3,5|line=610000}',
            'audit_segments.evidence.hole{m=120}',
            'audit_segments.evidence.unused{list=osm:1, osm:2}',
            'audit_segments.evidence.station{name=Confolens|m=612}',
        ]);
    });

    it('tracé : source, précision connue ou non, couverture précise', () => {
        expect(
            evidenceText('trace', { source: 'bdtopo', precision_m: 2.5, precise_coverage: 1 }, ctx)
                .facts,
        ).toEqual([
            'audit_segments.evidence.trace_source{source=audit_segments.sources.bdtopo}',
            'audit_segments.evidence.precision{m=2,5}',
            'audit_segments.evidence.precise_coverage{pct=100}',
        ]);
        expect(evidenceText('trace', { precision_m: null }, ctx).facts).toEqual([
            'audit_segments.evidence.precision_unknown',
        ]);
    });

    it('rien à dire sans preuve', () => {
        expect(evidenceText('placement', null, ctx)).toEqual({
            reasons: [],
            facts: [],
            verdicts: [],
        });
    });
});
