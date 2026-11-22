import { decadeOf, formatLocale } from './typo.js';

/**
 * Dates d'une gare, même libellé partout (fiche gare, fiche ligne, carte).
 *
 * Précisions : day | month | year ; fermetures imprécises des sources : decade | after | before ;
 * range = date de fermeture retenue inconnue, bornée par la frise
 * (StationTimeline::resolveClosure) : fermée après `after` (dernière année où la gare est
 * affirmée ouverte), au plus tard l'année de `iso` → « entre 1951 et 1960 ». Une borne haute qui
 * atteint l'année en cours ne date rien (la gare est fermée aujourd'hui, c'est tout ce qu'on sait) :
 * « date inconnue, après 1870 » plutôt que « entre 1871 et 2026 », comme EventText::rangeDate côté
 * serveur.
 *
 * @param {string|null} iso   date AAAA-MM-JJ
 * @param {string} [precision]
 * @param {number|null} [after]
 * @param {(key: string, opts?: object) => string} [t]  traduction (clés stations.dates.*)
 * @param {string} [lang]  langue ou locale de format (« en » est rendu en anglais britannique)
 * @param {number} [currentYear]  année en cours (borne haute « aujourd'hui » d'un intervalle)
 * @returns {string|null}
 */
export function formatStationDate(
    iso,
    precision = 'day',
    after = null,
    t = (key) => key,
    lang = 'fr',
    currentYear = new Date().getFullYear(),
) {
    if (!iso) return null;
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    if (precision === 'range') {
        if (y >= currentYear) {
            return after == null
                ? t('stations.dates.unknown')
                : t('stations.dates.unknown_after', { year: after });
        }
        if (after == null) return t('stations.dates.range_upper', { year: y });
        return after + 1 < y ? t('stations.dates.range', { from: after + 1, to: y }) : String(y);
    }
    if (precision === 'year') return String(y);
    if (precision === 'decade')
        return t('stations.dates.decade', { year: y, decade: decadeOf(y, lang) });
    if (precision === 'after' || precision === 'before')
        return t(`stations.dates.${precision}`, { year: y });
    // Date partielle (« 1954 », « 1954-05 ») : complétée au premier du mois ou de l'année.
    const day = d || 1;
    const date = new Date(Date.UTC(y, (m || 1) - 1, day));
    /** @type {Intl.DateTimeFormatOptions} */
    const opts =
        precision === 'month'
            ? { month: 'long', year: 'numeric' }
            : { day: 'numeric', month: 'long', year: 'numeric' };

    const locale = formatLocale(lang);
    const text = date.toLocaleDateString(locale, { ...opts, timeZone: 'UTC' });
    // Français : « 1er janvier 1867 » (le premier du mois prend l'ordinal).
    if (precision === 'day' && day === 1 && locale.startsWith('fr')) {
        return text.replace(/^1(?=\s)/, '1er');
    }

    return text;
}

/**
 * Date d'ouverture d'une gare : comme formatStationDate, sauf pour une ouverture encadrée
 * (précision `range`, règle G3 des ères de gares : ouverture déduite des lignes, bornée par la
 * carte). `after` est alors l'année qui précède la borne basse (`provenance.opened_after`) : la gare a
 * ouvert entre `after` + 1 et l'année de `iso`, « entre 1847 et 1860 », jamais « au plus tard en 1860 ».
 * Contrairement à une fermeture, la borne haute peut être l'année en cours (gare desservie à l'ère
 * courante) : « entre 1847 et 2026 », et non « date inconnue ». Sans `after`, ou pour toute autre
 * précision, le libellé est celui de formatStationDate.
 *
 * @param {string|null} iso   date AAAA-MM-JJ
 * @param {string} [precision]
 * @param {number|null} [after]  année qui précède la borne basse (`opened_after`)
 * @param {(key: string, opts?: object) => string} [t]  traduction (clés stations.dates.*)
 * @param {string} [lang]
 * @param {number} [currentYear]
 * @returns {string|null}
 */
export function formatStationOpening(
    iso,
    precision = 'day',
    after = null,
    t = (key) => key,
    lang = 'fr',
    currentYear = new Date().getFullYear(),
) {
    if (iso && precision === 'range' && after != null) {
        const to = Number(iso.slice(0, 4));
        return after + 1 < to ? t('stations.dates.range', { from: after + 1, to }) : String(to);
    }
    return formatStationDate(iso, precision, after, t, lang, currentYear);
}

/**
 * Ouverture d'une ligne de l'annuaire des gares : l'année seule (`opened_year`), sauf pour une
 * ouverture encadrée (`opened_precision` = range avec `opened_after`) : « entre 1955 et 1970 ».
 * Le tri de l'annuaire reste celui de `opened_at`, la borne haute.
 *
 * @param {{opened_year?: number|null, opened_precision?: string|null, opened_after?: number|null}} row
 * @param {(key: string, opts?: object) => string} [t]
 * @param {string} [lang]
 * @returns {string}
 */
export function directoryOpening(row, t = (key) => key, lang = 'fr') {
    const year = row.opened_year;
    if (!year) return '';
    if (row.opened_precision === 'range' && row.opened_after != null) {
        return (
            formatStationOpening(`${year}-12-31`, 'range', row.opened_after, t, lang) ??
            String(year)
        );
    }
    return String(year);
}
