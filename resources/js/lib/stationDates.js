/**
 * Dates d'une gare, même libellé partout (fiche gare, fiche ligne, carte).
 *
 * Précisions : day | month | year ; fermetures imprécises des sources : decade | after | before ;
 * range = date de fermeture retenue inconnue, bornée par la frise
 * (StationTimeline::resolveClosure) : fermée après `after` (dernière année où la gare est
 * affirmée ouverte), au plus tard l'année de `iso` → « entre 1951 et 1960 ».
 *
 * @param {string|null} iso   date AAAA-MM-JJ
 * @param {string} [precision]
 * @param {number|null} [after]
 * @param {(key: string, opts?: object) => string} [t]  traduction (clés stations.dates.*)
 * @param {string} [lang]
 * @returns {string|null}
 */
export function formatStationDate(
    iso,
    precision = 'day',
    after = null,
    t = (key) => key,
    lang = 'fr',
) {
    if (!iso) return null;
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    if (precision === 'range') {
        if (after == null) return t('stations.dates.range_upper', { year: y });
        return after + 1 < y ? t('stations.dates.range', { from: after + 1, to: y }) : String(y);
    }
    if (precision === 'year') return String(y);
    if (['decade', 'after', 'before'].includes(precision))
        return t(`stations.dates.${precision}`, { year: y });
    const date = new Date(Date.UTC(y, (m || 1) - 1, d || 1));
    /** @type {Intl.DateTimeFormatOptions} */
    const opts =
        precision === 'month'
            ? { month: 'long', year: 'numeric' }
            : { day: 'numeric', month: 'long', year: 'numeric' };

    return date.toLocaleDateString(lang || 'fr', { ...opts, timeZone: 'UTC' });
}
