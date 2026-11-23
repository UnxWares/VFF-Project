// Gares d'une fiche de ligne : la page reçoit au premier affichage ce que lisent la carte du tracé et le diagramme
// « Où et quand » (`stations` : position, nom, terminus, états par ère ; LineSheet::PAGE_STATION_FIRST), puis, juste
// après, ce que lit la liste des gares plus bas (`station_details` : PK, commune, dates ; prop différée).

/**
 * Gares complètes de la liste : chaque gare du premier affichage avec ses détails (même `slug`). Détails pas encore
 * reçus : `null`, la liste attend.
 *
 * @param {Array<Record<string, any>>} stations
 * @param {Array<Record<string, any>> | undefined} details
 * @returns {Array<Record<string, any>> | null}
 */
export function withStationDetails(stations, details) {
    if (details === undefined) return null;
    const bySlug = new Map((details ?? []).map((d) => [d.slug, d]));
    return (stations ?? []).map((s) => ({ ...bySlug.get(s.slug), ...s }));
}
