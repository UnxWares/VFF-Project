// Textes du contexte sourcé des fiches d'accidents : date d'un document selon sa précision, valeur corrigée.
import { accidentDate, timeText } from './accidentText.js';
import { formatNumber } from './typo.js';

/**
 * Date d'un document : « 14 décembre 1917 », « décembre 1917 » ou « 1917 » selon ce qui est connu.
 * @param {string|null} published AAAA, AAAA-MM ou AAAA-MM-JJ
 * @param {string} [lang]
 */
export function documentDate(published, lang = 'fr') {
    if (!published) return null;
    if (/^\d{4}$/.test(published)) return published;
    if (/^\d{4}-\d{2}$/.test(published)) return accidentDate(`${published}-01`, 'month', lang);
    return accidentDate(published, 'day', lang);
}

/**
 * Valeur d'avant ou valeur retenue d'une correction, mise en forme selon le champ.
 * @param {string} field occurred_on, occurred_at_time, deaths ou injuries
 * @param {string|number|null} value
 * @param {string} [lang]
 */
export function correctionValue(field, value, lang = 'fr') {
    if (value === null || value === undefined || value === '') return null;
    if (field === 'occurred_on') return accidentDate(String(value), 'day', lang);
    if (field === 'occurred_at_time') return timeText(String(value), lang);
    return formatNumber(Number(value), lang);
}

/**
 * Langue du titre d'un document de la liste « Documents et archives » (RGAA 8.7) : pour un article
 * d'encyclopédie, celle de l'édition de Wikipédia d'après l'adresse (`en.wikipedia.org` : anglais) ; sinon
 * le français, langue de presque tous les documents cités (un ouvrage peut renvoyer à la bibliographie d'un
 * article en anglais sans être en anglais). Un champ `lang` du document, s'il existe un jour, l'emporte.
 *
 * @param {{ lang?: string|null, kind?: string|null, url?: string|null }} ref
 * @returns {string}
 */
export function referenceLang(ref) {
    if (ref?.lang) return ref.lang;
    if (ref?.kind !== 'encyclopedia') return 'fr';
    const match = /^https?:\/\/([a-z]{2,3})\.(?:m\.)?wikipedia\.org\//i.exec(ref?.url ?? '');

    return match ? match[1].toLowerCase() : 'fr';
}
