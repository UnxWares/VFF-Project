// Rames remarquables d'une série (fiche matériel) : date d'un fait selon sa précision.
import { firstOfMonth, formatLocale } from './typo.js';

/** @type {Record<string, Intl.DateTimeFormatOptions>} */
const OPTIONS = {
    day: { day: 'numeric', month: 'long', year: 'numeric' },
    month: { month: 'long', year: 'numeric' },
    year: { year: 'numeric' },
};

/**
 * Date seule, écrite à sa précision : « 29 février 2020 », « juin 1978 », « 1981 ». Nulle sans date.
 * @param {string|null} iso AAAA-MM-JJ
 * @param {'day'|'month'|'year'|null} precision
 * @param {string} [lang]
 */
export function notableDate(iso, precision, lang = 'fr') {
    if (!iso || !precision || !OPTIONS[precision]) return null;
    const [y, m, d] = iso.split('-').map(Number);
    const date = new Date(Date.UTC(y, (m || 1) - 1, d || 1));
    const text = new Intl.DateTimeFormat(formatLocale(lang), {
        ...OPTIONS[precision],
        timeZone: 'UTC',
    }).format(date);

    return firstOfMonth(text, lang);
}

/**
 * Date d'un fait, période comprise : `range` met en forme « :from au :to ». La première date perd son année
 * quand les deux tombent la même année (« 29 février au 1er mars 2020 »).
 * @param {{ date: string|null, date_to?: string|null, precision: 'day'|'month'|'year'|null }} fact
 * @param {(key: string, opts?: object) => string} t
 * @param {string} [lang]
 */
export function notableFactDate(fact, t, lang = 'fr') {
    const end = notableDate(fact.date_to ?? null, fact.precision, lang);
    if (!end) return notableDate(fact.date, fact.precision, lang);
    let start = notableDate(fact.date, fact.precision, lang);
    if (start && fact.date?.slice(0, 4) === fact.date_to?.slice(0, 4)) {
        start = start.replace(new RegExp(`\\s*${fact.date.slice(0, 4)}$`), '');
    }

    return t('materials.notable.range', { from: start, to: end });
}
