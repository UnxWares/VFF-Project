// Périodes de circulation d'une série sur une ligne (App\Models\LineMaterialPeriod::toSheet), pour la
// section « Matériel roulant » de la fiche ligne et les lignes parcourues de la fiche d'une série.
// Logique pure, testée par tests/js/materialPeriods.test.js.
//
// Une fin absente veut dire « non donnée par la source », jamais « circule encore » : un début seul
// s'écrit « à partir de 1975 », jamais « depuis 1975 ».
import { formatEventDate } from './linePhases.js';

const year = (iso) => Number(String(iso).slice(0, 4));
const firstDate = (p) => p.start_date ?? p.end_date;

/**
 * Périodes regroupées pour une lecture rapide : les intervalles qui se chevauchent ou se touchent sont
 * réunis (« 1971-1989 » et « 1988-2001 » donnent « 1971-2001 ») ; un début seul compris dans un
 * intervalle disparaît, et seul le plus ancien des autres reste (la série a roulé au moins depuis
 * lui) ; de même pour une fin seule, dont seule la plus récente reste. Ordre chronologique.
 *
 * @param {Array<{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string}>} periods
 * @returns {Array<{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string}>}
 */
export function mergedPeriods(periods) {
    const ranges = [];
    for (const p of [...(periods ?? [])]
        .filter((p) => p.start_date && p.end_date)
        .sort((a, b) => a.start_date.localeCompare(b.start_date))) {
        const last = ranges.at(-1);
        if (last && year(p.start_date) <= year(last.end_date)) {
            if (p.end_date > last.end_date) {
                last.end_date = p.end_date;
                last.end_precision = p.end_precision;
            }
            continue;
        }
        ranges.push({ ...p });
    }
    const covered = (date) =>
        ranges.some((r) => year(date) >= year(r.start_date) && year(date) <= year(r.end_date));
    const starts = (periods ?? [])
        .filter((p) => p.start_date && !p.end_date && !covered(p.start_date))
        .sort((a, b) => a.start_date.localeCompare(b.start_date));
    const ends = (periods ?? [])
        .filter((p) => !p.start_date && p.end_date && !covered(p.end_date))
        .sort((a, b) => a.end_date.localeCompare(b.end_date));
    return [...ranges, starts[0], ends.at(-1)]
        .filter(Boolean)
        .map(({ start_date, start_precision, end_date, end_precision }) => ({
            start_date: start_date ?? null,
            start_precision: start_precision ?? null,
            end_date: end_date ?? null,
            end_precision: end_precision ?? null,
        }))
        .sort((a, b) => firstDate(a).localeCompare(firstDate(b)));
}

/**
 * Période en années : « 1971-2001 », « à partir de 1975 », « jusqu'en 2009 » (clés `materials.periods.*`).
 *
 * @param {{start_date: ?string, end_date: ?string}} p
 * @param {(key: string, opts?: object) => string} t traduction
 */
export function periodYears(p, t) {
    if (p.start_date && p.end_date) {
        const [from, to] = [year(p.start_date), year(p.end_date)];
        return from === to ? String(from) : `${from}-${to}`;
    }
    if (p.start_date) return String(t('materials.periods.from', { year: year(p.start_date) }));
    return String(t('materials.periods.until', { year: year(p.end_date) }));
}

/**
 * Relations assurées par une série sur une ligne, en texte court : « TER Paris-Montparnasse - Granville
 * (1990-2009) ». Réunit les relations de la fiche (`relations`, LineMaterial::relations(), avec leurs
 * périodes) et celles que portent les périodes (`relation` de LineMaterialPeriod::toSheet()), une par libellé.
 *
 * @param {Array<{label: string, service?: ?string, periods?: Array<any>}>|undefined} relations
 * @param {Array<{start_date: ?string, end_date: ?string, relation?: ?{label: string, service?: ?string}}>|undefined} periods
 * @param {(key: string, opts?: object) => string} t traduction
 * @returns {string[]}
 */
export function relationsList(relations, periods, t) {
    const byLabel = new Map();
    for (const r of relations ?? []) {
        byLabel.set(r.label, { label: r.label, service: r.service ?? null, periods: [...(r.periods ?? [])] });
    }
    for (const p of periods ?? []) {
        const r = p.relation;
        if (!r?.label) continue;
        const entry = byLabel.get(r.label) ?? { label: r.label, service: r.service ?? null, periods: [] };
        if (!entry.periods.some((q) => q.start_date === p.start_date && q.end_date === p.end_date)) {
            entry.periods.push(p);
        }
        byLabel.set(r.label, entry);
    }
    return [...byLabel.values()].map((e) => {
        const years = e.periods.length ? periodsSummary(e.periods, t) : '';
        return `${e.service ? `${e.service} ` : ''}${e.label}${years ? ` (${years})` : ''}`;
    });
}

/** Toutes les périodes d'une série sur la ligne, en années et regroupées : « 1924-1958, à partir de 1963 ». */
export function periodsSummary(periods, t) {
    return mergedPeriods(periods)
        .map((p) => periodYears(p, t))
        .join(', ');
}

/**
 * Période à la précision de la source : « 20 août 1982 - 30 mai 1986 », « début : juin 2009 »,
 * « fin : 1972 », suivie de la portion quand l'article la nomme.
 *
 * @param {{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string, section_from?: ?string, section_to?: ?string}} p
 * @param {(key: string, opts?: object) => string} t traduction
 * @param {string} lang langue de l'interface (fr, en, de)
 */
export function periodText(p, t, lang = 'fr') {
    const date = (iso, precision) => formatEventDate(iso, precision ?? 'year', lang);
    let text;
    if (p.start_date && p.end_date) {
        text = `${date(p.start_date, p.start_precision)} - ${date(p.end_date, p.end_precision)}`;
    } else if (p.start_date) {
        text = String(
            t('materials.periods.start', { date: date(p.start_date, p.start_precision) }),
        );
    } else {
        text = String(t('materials.periods.end', { date: date(p.end_date, p.end_precision) }));
    }
    if (p.section_from && p.section_to) {
        text += `, ${t('materials.periods.section', { from: p.section_from, to: p.section_to })}`;
    }
    return text;
}

/**
 * Séries de la ligne, celles qui ont une période datée d'abord, de la plus ancienne à la plus récente ;
 * les autres ensuite, dans l'ordre reçu (par nom).
 *
 * @template {{ periods?: Array<any> }} M
 * @param {M[]} materials
 * @returns {M[]}
 */
export function sortByFirstPeriod(materials) {
    const first = (m) => {
        const dates = (m.periods ?? []).map(firstDate).filter(Boolean).sort();
        return dates[0] ?? null;
    };
    return (materials ?? [])
        .map((m, i) => ({ m, i, d: first(m) }))
        .sort((a, b) => {
            if (a.d && b.d) return a.d.localeCompare(b.d) || a.i - b.i;
            if (a.d || b.d) return a.d ? -1 : 1;
            return a.i - b.i;
        })
        .map(({ m }) => m);
}
