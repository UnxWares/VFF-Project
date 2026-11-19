/**
 * Période de desserte d'une gare par une ligne (fiche gare, liste « Lignes desservies »), avec les
 * mêmes règles que l'API (App\Enums\ServiceEnd) :
 *   - `last_year_kind` = `upper_bound` : la fin est une borne (« de 1872 à 1989 au plus tard ») ;
 *   - `last_year_kind` = `undated` : la fin n'est pas datée, l'année n'est que celle du calcul ;
 *     elle n'est pas affichée (« à partir de 1869, fin non datée ») ;
 *   - `years_source` = `station` : les années viennent des dates de la gare (`fromStation`).
 *
 * @param {{first_year?: number|null, last_year?: number|null, last_year_kind?: string|null,
 *          years_source?: string|null, never_served?: string|null}} line
 * @returns {{key: string, params: Record<string, number>, fromStation: boolean} | null}
 *   clé de traduction (stations.lines.*) et ses paramètres ; null s'il n'y a rien à dire
 */
export function servicePeriod(line) {
    const from = line.first_year || null;
    const to = line.last_year || null;
    const fromStation = line.years_source === 'station';
    if (line.never_served) {
        return {
            key: `never_served.${line.never_served}`,
            params: { from, to },
            fromStation: false,
        };
    }
    const kind = to ? (line.last_year_kind ?? 'year') : null;
    const result = (key, params = {}) => ({ key, params, fromStation });
    if (kind === 'undated') {
        return from ? result('period_from_undated', { from }) : result('period_undated');
    }
    if (kind === 'upper_bound') {
        return from
            ? result('period_from_to_bound', { from, to })
            : result('period_to_bound', { to });
    }
    if (from && to) return result('period_from_to', { from, to });
    if (from) return result('period_from', { from });
    if (to) return result('period_to', { to });

    return null;
}

/** Clé « datée » (stations.lines.dated.*) de chaque période, et le fragment de chaque borne. */
const DATED = {
    period_from_to: { key: 'from_to', from: 'from', to: 'to' },
    period_from: { key: 'from', since: 'since' },
    period_to: { key: 'to', until: 'until' },
    period_from_to_bound: { key: 'from_to_bound', from: 'from' },
    period_from_undated: { key: 'from_undated', start: 'start' },
};

/** Borne datée au jour ou au mois (service_start / service_end), sinon null : l'année suffit. */
const dated = (bound) =>
    bound?.date && (bound.precision === 'day' || bound.precision === 'month') ? bound : null;

/**
 * Phrase de la période de desserte, avec la date exacte quand l'API la donne au jour ou au mois
 * (`service_start`, `service_end` : App\Services\StationServiceDates) : « Desservie du 24 novembre
 * 1857 au 15 mai 1938 ». Sans date exacte, ou pour « jamais desservie », même phrase qu'avant, en
 * années. Une date au mois reste au mois (« d’avril 1869 »), jamais écrite au jour.
 *
 * @param {Parameters<typeof servicePeriod>[0] & {service_start?: {date: string, precision: string}|null,
 *          service_end?: {date: string, precision: string}|null}} line
 * @param {(key: string, opts?: object) => string} t  traduction
 * @param {(iso: string, precision: string) => string} formatDate  date rédigée à sa précision
 * @returns {{text: string, fromStation: boolean} | null}
 */
export function servicePeriodText(line, t, formatDate) {
    const period = servicePeriod(line);
    if (!period) return null;
    const start = dated(line.service_start);
    const end = dated(line.service_end);
    const plan = line.never_served ? null : DATED[period.key];
    if (!plan || (!start && !end)) {
        return {
            text: t(`stations.lines.${period.key}`, period.params),
            fromStation: period.fromStation,
        };
    }
    /** Fragment d'une borne : « du 24 novembre 1857 », « d’avril 1869 », « de 1857 ». */
    const fragment = (role, bound, year) => {
        if (!bound) return t(`stations.lines.bound.${role}.year`, { date: year });
        // Espaces insécables : « 15 mai 1938 » ne se coupe pas en fin de ligne.
        const date = formatDate(bound.date, bound.precision).replaceAll(' ', '\u00a0');
        const variant =
            bound.precision === 'day'
                ? 'day'
                : /^[aeiouyàâéèêîôû]/iu.test(date)
                  ? 'month_vowel'
                  : 'month';

        return t(`stations.lines.bound.${role}.${variant}`, { date });
    };
    /** @type {Record<string, number | string>} */
    const params = { ...period.params };
    for (const role of ['from', 'since', 'start']) {
        if (plan[role]) params[role] = fragment(role, start, period.params.from);
    }
    for (const role of ['to', 'until']) {
        if (plan[role]) params[role] = fragment(role, end, period.params.to);
    }

    return { text: t(`stations.lines.dated.${plan.key}`, params), fromStation: period.fromStation };
}
