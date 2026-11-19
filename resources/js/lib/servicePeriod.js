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
