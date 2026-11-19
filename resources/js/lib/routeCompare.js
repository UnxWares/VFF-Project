/**
 * Comparaison d'un trajet entre deux époques (GET /api/v1/route/compare) : styles des deux tracés
 * sur la carte, adresse partageable et phrases du résumé. Logique pure, partagée par le
 * planificateur d'itinéraire, la carte et sa légende ; testée dans tests/js/routeCompare.test.js.
 */

/**
 * Styles des tracés, par époque. Le trajet le plus récent garde le style de l'itinéraire simple
 * (liseré jaune, trait plein) ; le plus ancien a un liseré bleu clair plus large, dessous, et un
 * trait en tirets : sur une ligne commune, on voit les deux (le bleu déborde du jaune). Le motif
 * double toujours la couleur. `dash` : motif MapLibre en largeurs de trait (null : plein).
 */
export const ROUTE_STYLES = {
    earlier: { casing: '#7dd3fc', core: '#111827', dash: [1.4, 1.1], casingScale: 1.6 },
    later: { casing: '#fde047', core: '#111827', dash: null, casingScale: 1 },
};

/** Paramètres d'adresse de l'itinéraire (page /maps). `compare_year` : seconde époque. */
export const ROUTE_PARAMS = ['from', 'to', 'year', 'compare_year', 'heritage'];

const toYear = (value) => {
    const n = Number.parseInt(value ?? '', 10);
    return Number.isFinite(n) ? n : null;
};

/**
 * Lit l'itinéraire demandé par l'adresse : `?from=angouleme&to=limoges-benedictins&year=1930`,
 * plus `&compare_year=2026` pour une comparaison. Null sans départ ni arrivée.
 *
 * @param {string} search partie « ?… » de l'adresse
 * @returns {{ from: string, to: string, year: number | null, compareYear: number | null, heritage: boolean } | null}
 */
export function readRouteParams(search) {
    const params = new URLSearchParams(search);
    const from = params.get('from')?.trim();
    const to = params.get('to')?.trim();
    if (!from || !to) return null;
    return {
        from,
        to,
        year: toYear(params.get('year')),
        compareYear: toYear(params.get('compare_year')),
        heritage: params.get('heritage') === '1',
    };
}

/**
 * Écrit l'itinéraire affiché dans l'adresse (les autres paramètres sont gardés) ; `null` retire
 * l'itinéraire.
 *
 * @param {string} href adresse actuelle
 * @param {{ from: string, to: string, year: number, compareYear?: number | null, heritage?: boolean } | null} route
 * @returns {string} nouvelle adresse
 */
export function writeRouteParams(href, route) {
    const url = new URL(href);
    for (const key of ROUTE_PARAMS) url.searchParams.delete(key);
    if (route) {
        url.searchParams.set('from', route.from);
        url.searchParams.set('to', route.to);
        url.searchParams.set('year', String(route.year));
        if (route.compareYear != null)
            url.searchParams.set('compare_year', String(route.compareYear));
        if (route.heritage) url.searchParams.set('heritage', '1');
    }
    return url.toString();
}

/**
 * Seconde année proposée : aujourd'hui pour une année passée, 1930 (apogée du réseau) quand la
 * première année est déjà aujourd'hui.
 *
 * @param {number} year première année
 * @param {number} thisYear année en cours
 */
export function defaultCompareYear(year, thisYear) {
    return year >= thisYear ? 1930 : thisYear;
}

/**
 * Époque côté carte : celle de la première année demandée (la carte suit le champ « Année »).
 *
 * @param {{ earlier: { requested_year: number | null, era_year: number }, later: { era_year: number } }} comparison
 * @param {number} year première année demandée
 */
export function firstEra(comparison, year) {
    return comparison.earlier.requested_year === year
        ? comparison.earlier.era_year
        : comparison.later.era_year;
}

/** Écart de longueur jugé nul (m) : en deçà, « même longueur ». */
const SAME_LENGTH_M = 500;

/**
 * Phrases du résumé, dans l'ordre : longueurs et écart, ou trajet devenu impossible (avec la
 * raison), puis « mêmes gares ». `t` : traduction (clé, paramètres) ; `km` : mètres → texte.
 *
 * @param {any} c réponse de /api/v1/route/compare
 * @param {(key: string, params?: Record<string, unknown>) => string} t
 * @param {(m: number) => string} km
 * @returns {string[]}
 */
export function summarySentences(c, t, km) {
    const when = (side) =>
        side.era_is_current
            ? t('itinerary.compare.today')
            : t('itinerary.compare.in_year', { year: side.era_year });
    const earlier = when(c.earlier);
    const later = when(c.later);
    const d = c.differences;
    const out = [];
    if (d.outcome === 'both') {
        const change = d.distance_change_m;
        const lengths = {
            earlier,
            later,
            earlier_km: km(c.earlier.route.distance_m),
            later_km: km(c.later.route.distance_m),
            diff: km(Math.abs(change)),
            percent: Math.round(Math.abs(d.distance_change_percent ?? 0)),
        };
        if (Math.abs(change) < SAME_LENGTH_M) {
            out.push(t('itinerary.compare.same_length', lengths));
        } else {
            out.push(
                t(change > 0 ? 'itinerary.compare.longer' : 'itinerary.compare.shorter', lengths),
            );
        }
        if (d.same_path) out.push(t('itinerary.compare.same_path'));
    } else if (d.outcome === 'lost') {
        out.push(
            t('itinerary.compare.lost', { earlier, later, km: km(c.earlier.route.distance_m) }),
        );
        out.push(reasonSentence(c.later.unavailable, later, t));
    } else if (d.outcome === 'gained') {
        out.push(
            t('itinerary.compare.gained', { earlier, later, km: km(c.later.route.distance_m) }),
        );
        out.push(reasonSentence(c.earlier.unavailable, earlier, t));
    } else {
        out.push(t('itinerary.compare.neither', { earlier, later }));
    }
    return out.filter(Boolean);
}

/** Raison de l'absence de trajet à une époque (`when` : « aujourd'hui », « en 1930 »). */
function reasonSentence(unavailable, when, t) {
    if (unavailable?.error === 'station_not_in_network') {
        return t('itinerary.compare.reason_station', {
            station: unavailable.station?.name ?? '',
            when,
        });
    }
    if (unavailable?.error === 'no_path') return t('itinerary.compare.reason_no_path', { when });
    return '';
}

/**
 * Ce qu'est devenue une ligne empruntée à une seule des deux époques, vue de l'autre : clé de
 * traduction (sous itinerary.compare.line_state), ou null si rien à dire.
 *
 * @param {'earlier' | 'later'} side époque où la ligne est empruntée
 * @param {string | null} otherEra présence à l'autre époque : open, partly_open, closed ou none
 */
export function lineStateKey(side, otherEra) {
    if (!otherEra) return null;
    return `itinerary.compare.line_state.${side}_${otherEra}`;
}

/**
 * Lignes d'un des deux trajets, dans l'ordre, chacune avec ce qu'elle était à l'autre époque
 * (null : ligne commune aux deux trajets).
 *
 * @param {any} c réponse de /api/v1/route/compare
 * @param {'earlier' | 'later'} side
 * @returns {Array<{ line_slug: string | null, line_name: string | null, line_code: string | null, length_m: number, shared: boolean, otherEra: string | null }>}
 */
export function sideLines(c, side) {
    const route = c[side].route;
    if (!route) return [];
    const only =
        side === 'earlier' ? c.differences.lines_only_earlier : c.differences.lines_only_later;
    const byKey = new Map(only.map((l) => [lineKey(l), l]));
    const merged = new Map();
    for (const leg of route.lines) {
        const key = lineKey(leg);
        const known = merged.get(key);
        if (known) {
            known.length_m += leg.length_m;
            continue;
        }
        const diff = byKey.get(key);
        merged.set(key, {
            line_slug: leg.line_slug,
            line_name: leg.line_name,
            line_code: leg.line_code,
            length_m: leg.length_m,
            shared: !diff,
            otherEra: diff?.other_era ?? null,
        });
    }
    return [...merged.values()];
}

/** Clé de regroupement d'une ligne (même règle que le serveur : slug, sinon code, sinon nom). */
function lineKey(l) {
    return l.line_slug ?? `code:${l.line_code ?? l.line_name ?? '?'}`;
}
