// Audit des tronçons (page /validate/audit/segments, SegmentAuditAdminController) : fonctions
// pures de la page et de sa carte. Notes calculées par `vff:audit-segments`
// (docs/AUDIT-TRONCONS.md) ; ordre de gravité repris d'App\Enums\AuditGrade::severity().
import { firstOfMonth, formatLocale, formatNumber } from './typo.js';

export const BASE_URL = '/validate/audit/segments';
export const EXPORT_URL = `${BASE_URL}/export.csv`;

/** Contrôles notés, dans l'ordre d'affichage (SegmentAudits::CHECKS). */
export const CHECKS = Object.freeze(['classification', 'placement', 'accuracy', 'trace']);

/** Notes, de la meilleure à la plus mauvaise (ordre des légendes et des barres). */
export const GRADES = Object.freeze(['good', 'unknown', 'fair', 'poor']);

/** Gravité d'une note : unknown passe après fair, avant good (AuditGrade::severity). */
const SEVERITY = Object.freeze({ good: 0, unknown: 1, fair: 2, poor: 3 });

/**
 * Couleurs des notes sur la carte et dans les pastilles, par thème. Contraste d'au moins 3:1
 * sur le fond de page et sur le fond de carte pâli (composants graphiques, WCAG 1.4.11) :
 * vert, ambre, rouge, gris. La note est toujours écrite à côté : la couleur ne fait que la
 * souligner.
 */
export const GRADE_COLORS = Object.freeze({
    light: Object.freeze({ good: '#15803d', fair: '#b45309', poor: '#b91c1c', unknown: '#6b7280' }),
    dark: Object.freeze({ good: '#4ade80', fair: '#fbbf24', poor: '#f87171', unknown: '#94a3b8' }),
});

/** Paramètres d'URL de la page (le reste est ignoré). */
const PARAMS = ['era', 'check', 'grade', 'status', 'region', 'q', 'line', 'page'];

/**
 * @param {string | null | undefined} grade
 * @returns {number}
 */
export function gradeSeverity(grade) {
    return SEVERITY[grade] ?? SEVERITY.unknown;
}

/**
 * Note connue, ou « unknown » pour une valeur absente ou inattendue.
 *
 * @param {string | null | undefined} grade
 */
export function gradeKey(grade) {
    return grade && grade in SEVERITY ? grade : 'unknown';
}

/**
 * Couleur d'une note dans un thème.
 *
 * @param {string | null | undefined} grade
 * @param {boolean} dark
 */
export function gradeColor(grade, dark) {
    return GRADE_COLORS[dark ? 'dark' : 'light'][gradeKey(grade)];
}

/**
 * Paramètres nettoyés : clés connues seulement, chaînes rognées, valeurs vides retirées,
 * page 1 omise.
 *
 * @param {Record<string, unknown>} params
 * @returns {Record<string, string>}
 */
export function cleanParams(params) {
    /** @type {Record<string, string>} */
    const out = {};
    for (const key of PARAMS) {
        const raw = params?.[key];
        if (raw === null || raw === undefined) continue;
        const value = String(raw).trim();
        if (value === '' || (key === 'page' && value === '1')) continue;
        out[key] = value;
    }
    return out;
}

/**
 * @param {string} base
 * @param {Record<string, unknown>} params
 */
function withQuery(base, params) {
    const query = new URLSearchParams(cleanParams(params)).toString();
    return query ? `${base}?${query}` : base;
}

/**
 * Adresse de la page avec ces paramètres.
 *
 * @param {Record<string, unknown>} params
 */
export function pageUrl(params) {
    return withQuery(BASE_URL, params);
}

/**
 * Adresse de l'export CSV : la liste filtrée, ou les tronçons d'une ligne si `line` est donné
 * (le numéro de page n'a pas de sens pour un export).
 *
 * @param {Record<string, unknown>} params
 */
export function exportUrl(params) {
    return withQuery(EXPORT_URL, { ...params, page: null });
}

/**
 * Paramètres de l'état affiché : époque, filtres, page.
 *
 * @param {number | null} year
 * @param {Record<string, unknown> | null} filters
 * @param {number | null} [page]
 */
export function currentParams(year, filters, page = null) {
    return {
        era: year,
        check: filters?.check ?? null,
        grade: filters?.grade ?? null,
        status: filters?.status ?? null,
        region: filters?.region ?? null,
        q: filters?.q ?? null,
        page,
    };
}

/**
 * Paramètres lus dans le formulaire de filtres (FormData ou objet simple), nettoyés.
 *
 * @param {FormData | Record<string, unknown>} data
 */
export function formParams(data) {
    const get = (key) =>
        typeof data?.get === 'function' ? data.get(key) : /** @type {any} */ (data)?.[key];
    return cleanParams({
        era: get('era'),
        check: get('check'),
        grade: get('grade'),
        status: get('status'),
        region: get('region'),
        q: get('q'),
    });
}

/**
 * Tronçons du plus mal noté au mieux noté : gravité de la pire note, score, longueur, numéro.
 *
 * @template {Record<string, any>} T
 * @param {T[]} segments
 * @returns {T[]}
 */
export function sortSegments(segments) {
    return [...(segments ?? [])].sort(
        (a, b) =>
            gradeSeverity(b.worst_grade) - gradeSeverity(a.worst_grade) ||
            (b.score ?? 0) - (a.score ?? 0) ||
            (b.length_m ?? 0) - (a.length_m ?? 0) ||
            (a.segment_id ?? 0) - (b.segment_id ?? 0),
    );
}

/**
 * Collection GeoJSON des tronçons qui ont un tracé, une entité par tronçon, avec leurs notes en
 * propriétés (`worst`, `classification`, `placement`, `accuracy`, `trace`).
 *
 * @param {Array<Record<string, any>>} segments
 */
export function segmentCollection(segments) {
    const features = [];
    for (const s of segments ?? []) {
        const g = s?.geometry;
        if (!g || !['LineString', 'MultiLineString'].includes(g.type)) continue;
        if (!Array.isArray(g.coordinates) || g.coordinates.length === 0) continue;
        features.push({
            type: 'Feature',
            id: s.segment_id,
            properties: {
                segment_id: s.segment_id,
                worst: gradeKey(s.worst_grade),
                ...Object.fromEntries(CHECKS.map((c) => [c, gradeKey(s.grades?.[c])])),
            },
            geometry: g,
        });
    }
    return { type: 'FeatureCollection', features };
}

/**
 * Emprise [[ouest, sud], [est, nord]] d'une collection de lignes, ou null si elle est vide.
 *
 * @param {{ features: Array<{ geometry: { type: string, coordinates: any } }> }} collection
 * @returns {[[number, number], [number, number]] | null}
 */
export function collectionBounds(collection) {
    let w = Infinity;
    let s = Infinity;
    let e = -Infinity;
    let n = -Infinity;
    const visit = (coords) => {
        if (typeof coords?.[0] === 'number') {
            const [x, y] = coords;
            if (!Number.isFinite(x) || !Number.isFinite(y)) return;
            w = Math.min(w, x);
            e = Math.max(e, x);
            s = Math.min(s, y);
            n = Math.max(n, y);
            return;
        }
        for (const c of coords ?? []) visit(c);
    };
    for (const f of collection?.features ?? []) visit(f.geometry?.coordinates);
    return Number.isFinite(w)
        ? [
              [w, s],
              [e, n],
          ]
        : null;
}

/**
 * Expression MapLibre de couleur d'un tronçon selon la note choisie (`worst` ou un contrôle).
 *
 * @param {string} check
 * @param {boolean} dark
 */
export function colorExpression(check, dark) {
    const key = check === 'worst' || CHECKS.includes(check) ? check : 'worst';
    const colors = GRADE_COLORS[dark ? 'dark' : 'light'];
    return ['match', ['get', key], ...GRADES.flatMap((g) => [g, colors[g]]), colors.unknown];
}

/**
 * Répartition de la longueur par note pour chaque contrôle (résumé de l'ère) : total, puis pour
 * chaque note sa longueur, son nombre de tronçons et sa part (0 à 1), et la part sûre.
 *
 * @param {Record<string, Record<string, { segments?: number, length_m?: number }>> | null | undefined} byCheck
 */
export function checkShares(byCheck) {
    return CHECKS.map((check) => {
        const raw = byCheck?.[check] ?? {};
        const total = GRADES.reduce((sum, g) => sum + Number(raw[g]?.length_m ?? 0), 0);
        const parts = GRADES.map((grade) => {
            const length = Number(raw[grade]?.length_m ?? 0);
            return {
                grade,
                length_m: length,
                segments: Number(raw[grade]?.segments ?? 0),
                share: total > 0 ? length / total : 0,
            };
        });
        return {
            check,
            total_m: total,
            parts,
            good_share: parts.find((p) => p.grade === 'good')?.share ?? 0,
        };
    });
}

/**
 * Kilomètres lisibles, avec une espace insécable : « 12,3 km » (une décimale sous 100 km).
 *
 * @param {number | null | undefined} meters
 * @param {string} lang
 */
export function formatKm(meters, lang) {
    if (meters === null || meters === undefined || !Number.isFinite(Number(meters))) return '';
    const km = Number(meters) / 1000;
    const digits = km < 100 ? 1 : 0;
    return `${formatNumber(km, lang, { maximumFractionDigits: digits })}\u00a0km`;
}

/**
 * Pourcentage sans le signe (les traductions l'écrivent) : 0,934 → « 93,4 ».
 *
 * @param {number | null | undefined} ratio
 * @param {string} lang
 */
export function percentNumber(ratio, lang) {
    if (ratio === null || ratio === undefined || !Number.isFinite(Number(ratio))) return '';
    return formatNumber(Number(ratio) * 100, lang, { maximumFractionDigits: 1 });
}

/**
 * Date d'un événement (« 1938-05-15 », « 1938-05 » ou « 1938 ») dans la langue de la page :
 * anglais britannique, « 1er mars 1939 » en français.
 *
 * @param {string | null | undefined} iso
 * @param {string} lang
 */
export function eventDate(iso, lang) {
    const value = String(iso ?? '');
    const m = /^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?/.exec(value);
    if (!m) return value;
    const [, y, mo, d] = m;
    const date = new Date(Date.UTC(Number(y), mo ? Number(mo) - 1 : 0, d ? Number(d) : 1));
    /** @type {Intl.DateTimeFormatOptions} */
    const opts = d
        ? { day: 'numeric', month: 'long', year: 'numeric' }
        : mo
          ? { month: 'long', year: 'numeric' }
          : { year: 'numeric' };
    return firstOfMonth(
        new Intl.DateTimeFormat(formatLocale(lang), { ...opts, timeZone: 'UTC' }).format(date),
        lang,
    );
}

/**
 * Phrases des preuves d'une note : raisons, faits mesurés, avis des sources (classement).
 * `t` reçoit une clé complète (« audit_segments.… ») et ses paramètres ; `defaultValue` sert de
 * repli pour une source ou un type d'événement sans traduction.
 *
 * @param {string} check
 * @param {Record<string, any> | null | undefined} evidence
 * @param {{ t: (key: string, opts?: Record<string, unknown>) => string, lang: string, thresholds?: Record<string, number> }} ctx
 * @returns {{ reasons: string[], facts: string[], verdicts: string[] }}
 */
export function evidenceText(check, evidence, ctx) {
    const { t, lang, thresholds = {} } = ctx;
    const A = (key, opts) => t(`audit_segments.${key}`, opts);
    const num = (v, digits = 2) => formatNumber(v, lang, { maximumFractionDigits: digits });
    const source = (s) => A(`sources.${s}`, { defaultValue: String(s ?? '') });
    const ev = evidence ?? {};
    const reasons = (ev.reasons ?? []).map((r) => A(`reasons.${r}`, { defaultValue: r }));
    /** @type {string[]} */
    const facts = [];
    /** @type {string[]} */
    const verdicts = [];

    if (check === 'classification') {
        for (const rule of ev.rules ?? []) {
            facts.push(A(`reasons.rule_${rule}`, { defaultValue: rule }));
        }
        if (ev.actual) {
            const parts = [
                A(`statuses.${ev.actual.status}`, { defaultValue: ev.actual.status ?? '' }),
                ev.actual.traffic
                    ? A(`traffic.${ev.actual.traffic}`, { defaultValue: ev.actual.traffic })
                    : null,
                ev.actual.heritage ? A('evidence.heritage') : null,
            ].filter(Boolean);
            facts.push(A('evidence.actual', { value: parts.join(', ') }));
        }
        for (const v of ev.verdicts ?? []) {
            verdicts.push(verdictText(v, { A, source, lang, thresholds, t }));
        }
    } else if (check === 'placement') {
        if (ev.reference) {
            facts.push(
                A('evidence.reference', { source: source(ev.reference) }) +
                    (ev.same_source ? ` (${A('evidence.same_source')})` : ''),
            );
        }
        if (ev.median_m !== null && ev.median_m !== undefined) {
            facts.push(
                A('evidence.distances', {
                    median: num(ev.median_m),
                    p95: num(ev.p95_m),
                    coverage: percentNumber(ev.coverage, lang),
                    radius: num(thresholds.reference_radius_m ?? 50, 0),
                }),
            );
        }
        for (const [name, r] of Object.entries(ev.references ?? {})) {
            facts.push(
                A('evidence.reference_detail', {
                    source: source(name),
                    found: num(r?.found, 0),
                    points: num(r?.points, 0),
                    median:
                        r?.median_m === null || r?.median_m === undefined ? '-' : num(r.median_m),
                    p95: r?.p95_m === null || r?.p95_m === undefined ? '-' : num(r.p95_m),
                }),
            );
        }
    } else if (check === 'accuracy') {
        if (ev.max_turn_deg !== null && ev.max_turn_deg !== undefined) {
            facts.push(A('evidence.turn', { deg: num(ev.max_turn_deg, 1) }));
        }
        if (ev.hairpins > 0) facts.push(A('evidence.hairpins', { count: num(ev.hairpins, 0) }));
        if (ev.kinks > 0) facts.push(A('evidence.kinks', { count: num(ev.kinks, 0) }));
        for (const g of ev.junction_gaps ?? []) {
            facts.push(A('evidence.junction_gap', { m: num(g.m), line: g.other_line ?? '' }));
        }
        for (const h of ev.holes ?? []) {
            facts.push(A('evidence.hole', { m: num(h.m) }));
            const unused = h.unused_traces ?? [];
            if (unused.length) facts.push(A('evidence.unused', { list: unused.join(', ') }));
        }
        for (const s of ev.stations ?? []) {
            facts.push(
                A('evidence.station', { name: s.name ?? s.slug ?? '', m: num(s.distance_m, 0) }),
            );
        }
    } else if (check === 'trace') {
        if (ev.source) facts.push(A('evidence.trace_source', { source: source(ev.source) }));
        facts.push(
            ev.precision_m !== null && ev.precision_m !== undefined
                ? A('evidence.precision', { m: num(ev.precision_m, 1) })
                : A('evidence.precision_unknown'),
        );
        if (ev.precise_coverage !== null && ev.precise_coverage !== undefined) {
            facts.push(
                A('evidence.precise_coverage', { pct: percentNumber(ev.precise_coverage, lang) }),
            );
        }
    }
    return { reasons, facts, verdicts };
}

/**
 * Une phrase par avis de source : « Wikipédia (événements datés) : contredit, attend : fermé,
 * avis faible, événements : fermeture 1er mars 1939 ».
 */
function verdictText(v, { A, source, lang, thresholds, t }) {
    const parts = [A(v.agrees ? 'evidence.agrees' : 'evidence.contradicts')];
    if (v.expected?.class) {
        const traffic = v.expected.traffic
            ? `, ${A(`traffic.${v.expected.traffic}`, { defaultValue: v.expected.traffic })}`
            : '';
        parts.push(
            A('evidence.expects', {
                class:
                    A(`classes.${v.expected.class}`, { defaultValue: v.expected.class }) + traffic,
            }),
        );
    }
    if (v.why && !v.agrees && !String(v.why).startsWith(`expected_${v.expected?.class}`)) {
        parts.push(A(`reasons.${v.why}`, { defaultValue: v.why }));
    }
    if (v.near_date) {
        parts.push(A('evidence.near_date', { years: thresholds.near_date_years ?? 3 }));
    }
    if (v.heritage) parts.push(A('evidence.heritage'));
    if (v.unlocated_end) parts.push(A('evidence.unlocated_end'));
    else if (v.outweighed) parts.push(A('evidence.outweighed'));
    else if (v.weak) parts.push(A('evidence.weak'));
    const observed = Object.entries(v.observed ?? {})
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([key, value]) => `${key} ${String(value)}`);
    if (observed.length) parts.push(A('evidence.observed', { value: observed.join(', ') }));
    const events = (v.events ?? []).map(
        (e) =>
            `${t(`editor.event_types.${e.type}`, { defaultValue: e.type ?? '' })} ${eventDate(e.date, lang)}`,
    );
    if (events.length) parts.push(A('evidence.events', { list: events.join(', ') }));
    return A('evidence.verdict', {
        source: source(v.source),
        kind: A(`evidence.kinds.${v.kind}`, { defaultValue: v.kind ?? '' }),
        details: parts.join(', '),
    });
}
