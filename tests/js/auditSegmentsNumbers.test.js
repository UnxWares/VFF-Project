// Nombres de la page d'audit des tronçons (Pages/Validate/AuditSegments.svelte), rendue côté serveur
// (svelte/server) : totaux, longueurs, parts et rapport de longueur au format du site, dans la langue
// de la page (espace insécable des milliers et virgule en français, anglais britannique, point des
// milliers en allemand), jamais l'espace fine U+202F presque invisible en Lato. Le gabarit, les liens
// Inertia et la carte sont remplacés par des coquilles qui rendent leur contenu.
import { render } from 'svelte/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { setupI18n } from '$lib/i18n.js';

// Composants serveur qui ne rendent que leur contenu (children) ; vi.mock est remonté en tête du module.
vi.mock('$components/Layout.svelte', () => ({
    default: (renderer, props) => props?.children?.(renderer),
}));
vi.mock('$components/Validate/SegmentAuditMap.svelte', () => ({ default: () => {} }));
vi.mock('@inertiajs/svelte', () => ({
    Link: (renderer, props) => props?.children?.(renderer),
    router: { get: () => {}, visit: () => {} },
    page: { url: '/validate/audit-segments', props: { auth: { user: null } } },
}));

const { default: AuditSegments } =
    await import('../../resources/js/Pages/Validate/AuditSegments.svelte');

const NBSP = ' ';

const TRANSLATIONS = {
    'audit_segments.summary_lines': ':lines lignes et :segments tronçons audités, :km km au total.',
    'audit_segments.share_good': ':pct % sûrs',
    'audit_segments.list.total': ':total lignes',
    'audit_segments.list.segments_count_one': ':shown tronçon',
    'audit_segments.list.segments_count_other': ':shown tronçons',
    'audit_segments.list.score_inline': 'score :score',
    'audit_segments.line.score': 'Score de la ligne : :score sur 100, sur :km.',
    'audit_segments.line.length_ratio':
        'Longueur : :ratio de la longueur officielle (:official km, :source).',
    'audit_segments.line.official_sources.lignes-oubliees-pk': 'PK de lignes-oubliées',
    'audit_segments.line.inversions_one': ':shown inversion de l’ordre des gares.',
    'audit_segments.line.inversions_other': ':shown inversions de l’ordre des gares.',
};

const PROPS = {
    eras: [{ year: 2030, label: 'Aujourd’hui', is_current: true }],
    year: 2030,
    filters: {},
    summary: {
        lines: 1234,
        segments: 18_167,
        length_m: 35_869_400,
        audited_at: null,
        by_check: {
            placement: {
                good: { length_m: 32_640_000, segments: 16_012 },
                fair: { length_m: 1_578_000, segments: 1_100 },
                poor: { length_m: 251_000, segments: 1_002 },
                unknown: { length_m: 950, segments: 53 },
            },
        },
    },
    lines: {
        total: 4321,
        page: 1,
        last_page: 87,
        per_page: 50,
        items: [
            {
                slug: 'rfn-654330',
                name: 'Ligne d’Oscar',
                code: '654330',
                score: 12,
                length_m: 123_456,
                segments: 1500,
                grades: {},
            },
        ],
    },
    selected: null,
    thresholds: { target_m: 1, tolerance_m: 5 },
    rule_version: '2026-11-21.4',
};

const SELECTED = {
    line: { slug: 'rfn-654330', name: 'Ligne d’Oscar', code: '654330' },
    score: 7,
    length_m: 1_234_567,
    grades: {},
    shares: { placement: { good: 0.98765 } },
    line_checks: {
        reasons: [],
        length_ratio: 1.0234,
        official_length_m: 1_206_300,
        official_source: 'lignes-oubliees-pk',
        station_order_inversions: 1200,
    },
    segments: [],
};

/** Texte de la page rendue, balises retirées, blancs ordinaires resserrés (les insécables restent). */
const draw = (lang, extra = {}) => {
    setupI18n({ locale: lang, translations: TRANSLATIONS });

    return render(AuditSegments, { props: { ...PROPS, ...extra } })
        .body.replace(/<!--.*?-->/gs, '')
        .replace(/<[^>]+>/g, ' ')
        .replace(/[ \t\r\n]+/g, ' ');
};

afterEach(() => setupI18n({ locale: 'fr', translations: {} }));

describe('AuditSegments : nombres au format du site', () => {
    it('écrit les totaux et les longueurs en français, milliers séparés par une espace insécable', () => {
        const text = draw('fr');

        expect(text).toContain(
            `1${NBSP}234 lignes et 18${NBSP}167 tronçons audités, 35${NBSP}869 km au total.`,
        );
        expect(text).toContain(`4${NBSP}321 lignes`);
        // Placement : 32 640 km sûrs sur 34 469,95 km.
        expect(text).toContain('94,7 % sûrs');
        expect(text).toContain(`32${NBSP}640${NBSP}km`);
        expect(text).toContain(`16${NBSP}012 tronçons`);
        // Moins de 100 km : un chiffre après la virgule.
        expect(text).toContain(`1${NBSP}km`);
        expect(text).toContain(`123${NBSP}km`);
        expect(text).toContain(`1${NBSP}500`);
        expect(text).not.toContain(' ');
    });

    it('écrit la fiche de la ligne au même format', () => {
        const text = draw('fr', { selected: SELECTED, selected_slug: 'rfn-654330' });

        expect(text).toContain(`Score de la ligne : 7 sur 100, sur 1${NBSP}235${NBSP}km.`);
        expect(text).toContain('98,8 % sûrs');
        expect(text).toContain(
            `Longueur : 102,3${NBSP}% de la longueur officielle (1${NBSP}206,3 km, PK de lignes-oubliées).`,
        );
        expect(text).toContain(`1${NBSP}200 inversions`);
        expect(text).not.toContain(' ');
    });

    it('suit la langue de la page', () => {
        expect(draw('en')).toContain(
            '1,234 lignes et 18,167 tronçons audités, 35,869 km au total.',
        );
        expect(draw('en')).toContain('94.7 % sûrs');
        expect(draw('en', { selected: SELECTED, selected_slug: 'rfn-654330' })).toContain(
            'Longueur : 102.3% de la longueur officielle (1,206.3 km',
        );
        expect(draw('de')).toContain(
            '1.234 lignes et 18.167 tronçons audités, 35.869 km au total.',
        );
        expect(draw('de')).toContain('94,7 % sûrs');
        expect(draw('de', { selected: SELECTED, selected_slug: 'rfn-654330' })).toContain(
            `Longueur : 102,3${NBSP}% de la longueur officielle (1.206,3 km`,
        );
    });

    it('compte un résumé sans longueur comme zéro kilomètre', () => {
        expect(draw('fr', { summary: { ...PROPS.summary, length_m: null } })).toContain(
            'tronçons audités, 0 km au total.',
        );
    });
});
