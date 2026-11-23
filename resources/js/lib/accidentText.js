// Textes des pages d'accidents (/accidents) : date selon sa précision, bilan, trains, distances.
import { formatStationDate } from './stationDates.js';
import { formatNumber, NBSP } from './typo.js';

/**
 * Date d'un accident selon ce que disent les sources : « 12 juillet 2013 », « juillet 2013 »,
 * « 1891 » (jamais un jour inventé).
 * @param {string} iso date AAAA-MM-JJ
 * @param {'day'|'month'|'year'} precision
 * @param {string} [lang]
 */
export function accidentDate(iso, precision, lang = 'fr') {
    return formatStationDate(iso, precision, null, (k) => k, lang);
}

/**
 * Bilan humain court : « 7 morts, 192 blessés », « 43 morts », ou le texte « inconnu ».
 * @param {number|null} deaths
 * @param {number|null} injuries
 * @param {(key: string, opts?: object) => string} t
 */
export function tollText(deaths, injuries, t) {
    const parts = [];
    if (deaths != null) {
        parts.push(t('accidents.deaths', { count: deaths }));
    }
    if (injuries != null) {
        parts.push(t('accidents.injuries', { count: injuries }));
    }
    return parts.length ? parts.join(', ') : t('accidents.toll_unknown');
}

/**
 * Libellé d'un train : service et numéro (« TER 849 902 »), sinon catégorie traduite, puis le
 * matériel entre parenthèses ; à défaut, l'exploitant seul.
 * @param {{ label?: string|null, category?: string|null, number?: string|null, material?: string|null, operator?: string|null }} train
 * @param {(key: string, opts?: object) => string} t
 */
export function trainText(train, t) {
    let name =
        train.label ?? (train.category ? t(`accidents.train_categories.${train.category}`) : null);
    if (name && train.number) name = `${name} ${train.number}`;
    if (!name) return train.material ?? train.operator ?? t('accidents.train_categories.unknown');
    return train.material ? `${name} (${train.material})` : name;
}

/**
 * Distance lisible : mètres sous 1 km (« 924 m »), kilomètres au-delà (« 1,2 km »).
 * @param {number|null} m
 * @param {string} [lang]
 */
export function distanceText(m, lang = 'fr') {
    if (m == null) return '';
    if (m < 1000) return `${formatNumber(Math.round(m), lang)}${NBSP}m`;
    return `${formatNumber(m / 1000, lang, { maximumFractionDigits: 1 })}${NBSP}km`;
}

/** Ton du badge de confiance d'un rattachement : le texte dit la confiance, la couleur la souligne. */
export const CONFIDENCE_TONE = { high: 'success', medium: 'info', low: 'warning' };

/** Clé de traduction d'un fournisseur de source (`bea-tt` → `accidents.providers.bea_tt`). */
export function providerKey(provider) {
    return `accidents.providers.${String(provider).replaceAll('-', '_')}`;
}

/**
 * Heure locale dans la langue : « 17 h 14 », « 17:14 », « 17:14 Uhr ».
 * @param {string|null} time HH:MM
 * @param {string} [lang]
 */
export function timeText(time, lang = 'fr') {
    if (!time) return '';
    const [h, m] = time.split(':');
    if (lang === 'fr') return `${Number(h)}${NBSP}h${NBSP}${m}`;
    return lang === 'de' ? `${h}:${m}${NBSP}Uhr` : `${h}:${m}`;
}

/** Ordre d'affichage des familles de sources qui confirment une fiche (accidents.verification.families). */
const FAMILY_ORDER = ['official', 'wikipedia', 'wikidata', 'wikipedia-edition', 'press'];

/**
 * Familles de sources qui ont confirmé à la fois la date et la commune d'une fiche relue (`review.status` = `ok`
 * et une règle satisfaite), dans un ordre stable ; Wikidata avec ou sans référence n'est citée qu'une fois.
 * @param {{status: string, verification?: {rule?: string, families?: {date?: string[], place?: string[]}}|null}} review
 * @returns {string[]}
 */
export function confirmingFamilies(review) {
    const v = review?.verification;
    if (review?.status !== 'ok' || !v?.rule) return [];
    const norm = (f) => (f === 'wikidata-bare' ? 'wikidata' : f);
    const place = new Set((v.families?.place ?? []).map(norm));
    const both = new Set((v.families?.date ?? []).map(norm).filter((f) => place.has(f)));
    return FAMILY_ORDER.filter((f) => both.has(f));
}
