/**
 * Aides des fiches de compagnies (/companies, /companies/{slug}) : regroupement par genre, projection sobre du
 * tracé du réseau, découpe de la liste des lignes. Aucun calcul de chiffres : ils viennent de CompanyFigures.
 */

/** Genres de compagnies, dans l'ordre d'affichage de l'index. */
export const KINDS = ['grande-compagnie', 'etat', 'sncf', 'infrastructure', 'secondaire'];

/**
 * Les compagnies d'un genre, dans l'ordre chronologique de leur création.
 *
 * @param {Array<any>} companies
 * @param {string} kind
 */
export function companiesOfKind(companies, kind) {
    return companies
        .filter((c) => c.kind === kind)
        .sort((a, b) => a.founded.date.localeCompare(b.founded.date));
}

/** Année d'une date `AAAA-MM-JJ`. */
export const yearOf = (date) => Number(String(date).slice(0, 4));

/**
 * Projection équirectangulaire du tracé : x corrigé par le cosinus de la latitude moyenne, y vers le bas, en
 * centièmes de degré. Rend le cadre (`viewBox`) et un seul attribut `d` pour tout le réseau.
 *
 * @param {{ bbox: [number, number, number, number], paths: Array<Array<[number, number]>> } | null} map
 * @returns {{ viewBox: string, width: number, height: number, d: string } | null}
 */
export function projectMap(map) {
    if (!map || !map.paths?.length) return null;
    const [minLon, minLat, maxLon, maxLat] = map.bbox;
    const k = Math.cos((((minLat + maxLat) / 2) * Math.PI) / 180);
    const scale = 100;
    const pad = 0.15 * scale;
    const width = Math.max((maxLon - minLon) * k * scale, 1) + pad * 2;
    const height = Math.max((maxLat - minLat) * scale, 1) + pad * 2;
    const point = ([lon, lat]) =>
        `${((lon - minLon) * k * scale + pad).toFixed(1)} ${((maxLat - lat) * scale + pad).toFixed(1)}`;
    const d = map.paths.map((path) => `M${path.map(point).join('L')}`).join('');

    return {
        viewBox: `0 0 ${width.toFixed(1)} ${height.toFixed(1)}`,
        width: Number(width.toFixed(1)),
        height: Number(height.toFixed(1)),
        d,
    };
}

/**
 * Découpe une liste en une partie affichée et une partie repliée.
 *
 * @template T
 * @param {T[]} list
 * @param {number} shown
 * @returns {[T[], T[]]}
 */
export function foldLines(list, shown) {
    return [list.slice(0, shown), list.slice(shown)];
}
