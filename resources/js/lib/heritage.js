// Utilitaires de la page « Patrimoine ferroviaire protégé » (Pages/Heritage/Index.svelte) : filtres, tri, regroupement par
// architecte et points de la carte, sans accès au DOM. Les données viennent de App\Services\Heritage\HeritageIndex ;
// la logique de filtre et de tri est la même que celle de GET /api/v1/heritage.
import { pointsBounds } from './touristRailways.js';

export { pointsBounds };

/** Minuscules sans accents ni ponctuation, pour la recherche par nom. */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/**
 * Monuments qui répondent aux filtres : recherche dans le nom, la commune, la région, les architectes et la ligne ;
 * protection, objet, style, architecte, région, époque.
 *
 * @param {Array<any>} items
 * @param {{ q?: string, protection?: string, object?: string, style?: string, architect?: string, region?: string, era?: string }} filters
 */
export function filterHeritage(items, filters = {}) {
    const {
        q = '',
        protection = '',
        object = '',
        style = '',
        architect = '',
        region = '',
        era = '',
    } = filters;
    const needle = fold(q);
    return items.filter((i) => {
        if (protection && i.protection !== protection) return false;
        if (object && i.object !== object) return false;
        if (style && i.style !== style) return false;
        if (architect && !i.architects.includes(architect)) return false;
        if (region && i.region !== region) return false;
        if (era && i.era !== era) return false;
        if (!needle) return true;
        const haystack = fold(
            [
                i.name,
                i.title,
                i.station?.name,
                i.commune,
                i.region,
                i.line?.name,
                ...i.architects,
            ].join(' '),
        );
        return needle.split(' ').every((word) => haystack.includes(word));
    });
}

/**
 * Copie triée : `name` (alphabétique sans accents), `protection_date` (la plus récente d'abord), `built` (la plus
 * ancienne d'abord) ; une date inconnue passe en dernier.
 *
 * @param {Array<any>} items
 * @param {'name'|'protection_date'|'built'} sort
 */
export function sortHeritage(items, sort = 'name') {
    const byName = (a, b) => fold(a.name).localeCompare(fold(b.name));
    const compare = {
        name: byName,
        protection_date: (a, b) =>
            Number(a.protection_date === null) - Number(b.protection_date === null) ||
            String(b.protection_date ?? '').localeCompare(String(a.protection_date ?? '')) ||
            byName(a, b),
        built: (a, b) =>
            Number(a.built.year === null) - Number(b.built.year === null) ||
            (a.built.year ?? 0) - (b.built.year ?? 0) ||
            byName(a, b),
    }[sort];
    return [...items].sort(compare ?? byName);
}

/**
 * Monuments regroupés par architecte (un monument à plusieurs architectes figure dans chaque groupe), les groupes les
 * plus fournis d'abord ; les monuments sans architecte forment le dernier groupe (`name` nul).
 *
 * @param {Array<any>} items déjà filtrés et triés
 * @returns {Array<{ name: string|null, items: Array<any> }>}
 */
export function groupByArchitect(items) {
    /** @type {Map<string, Array<any>>} */
    const groups = new Map();
    const none = [];
    for (const item of items) {
        if (!item.architects.length) none.push(item);
        for (const name of item.architects) {
            if (!groups.has(name)) groups.set(name, []);
            groups.get(name).push(item);
        }
    }
    const named = [...groups.entries()]
        .map(([name, list]) => ({ name, items: list }))
        .sort(
            (a, b) => b.items.length - a.items.length || fold(a.name).localeCompare(fold(b.name)),
        );
    return none.length ? [...named, { name: null, items: none }] : named;
}

/** Identifiant d'ancre d'un groupe d'architecte. */
export function groupId(name) {
    return `architecte-${fold(name).replaceAll(' ', '-')}`;
}

/** Nombre de monuments par valeur de `key`, dans l'ordre des `values` donnés. */
export function countBy(items, key, values) {
    return Object.fromEntries(values.map((v) => [v, items.filter((i) => i[key] === v).length]));
}

/** Points GeoJSON des monuments (un point par monument). */
export function heritagePoints(items) {
    return {
        type: 'FeatureCollection',
        features: items
            .filter((i) => Number.isFinite(i.lat) && Number.isFinite(i.lng))
            .map((i) => ({
                type: 'Feature',
                geometry: { type: 'Point', coordinates: [i.lng, i.lat] },
                properties: { id: i.id, name: i.name },
            })),
    };
}

/**
 * Emprise [[ouest, sud], [est, nord]] des points de la France métropolitaine (l'outre-mer étirerait la carte à tout le
 * globe), ou de tous les points quand aucun n'y est ; null quand il n'y en a pas.
 */
export function franceBounds(collection) {
    const metro = collection.features.filter((f) => {
        const [lng, lat] = f.geometry.coordinates;
        return lng > -6 && lng < 10 && lat > 41 && lat < 52;
    });
    return pointsBounds(metro.length ? { ...collection, features: metro } : collection);
}

/** Année affichée de la construction : « 1925 », « 1914 à 1985 », ou null quand seule une période ou rien n'est connu. */
export function builtRange(built) {
    if (built.from === null) return null;
    return built.to !== null && built.to !== built.from ? [built.from, built.to] : [built.from];
}
