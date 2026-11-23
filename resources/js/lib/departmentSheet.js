// Utilitaires des pages « Le rail dans <département> » et du tableau des départements
// (Pages/Departments, App\Services\Departments\DepartmentFigures), sans accès au DOM.

/** Texte sans accents ni majuscules, pour chercher « cote d'or » dans « Côte-d'Or ». */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLocaleLowerCase('fr')
        .replace(/[-'’]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Départements dont le nom, le code ou la région contient la recherche.
 * @param {Array<{ code: string, name: string, region: string }>} rows
 * @param {string} query
 */
export function filterDepartments(rows, query) {
    const q = fold(query);
    if (q === '') return rows;

    return rows.filter((r) => fold(`${r.code} ${r.name} ${r.region}`).includes(q));
}

/** Ordre naturel des codes : 01 à 95, puis 2A, 2B (à leur place, entre 19 et 21), puis l'outre-mer. */
export function codeOrder(code) {
    const c = String(code).toUpperCase();
    if (c === '2A') return 19.1;
    if (c === '2B') return 19.2;

    return Number.parseInt(c, 10);
}

/**
 * Copie triée du tableau. Les valeurs absentes (null) vont toujours en dernier, quel que soit le sens.
 * @param {Array<object>} rows
 * @param {string} key colonne : `name`, `region`, `code` ou un champ numérique
 * @param {'asc'|'desc'} dir
 * @param {string} [lang]
 */
export function sortDepartments(rows, key, dir, lang = 'fr') {
    const sign = dir === 'desc' ? -1 : 1;
    const value = (r) => (key === 'code' ? codeOrder(r.code) : r[key]);

    return [...rows].sort((a, b) => {
        const x = value(a);
        const y = value(b);
        if ((x ?? null) === null && (y ?? null) === null)
            return codeOrder(a.code) - codeOrder(b.code);
        if ((x ?? null) === null) return 1;
        if ((y ?? null) === null) return -1;
        const order =
            typeof x === 'number' && typeof y === 'number'
                ? x - y
                : String(x).localeCompare(String(y), lang, { sensitivity: 'base' });

        return order === 0 ? codeOrder(a.code) - codeOrder(b.code) : sign * order;
    });
}

/**
 * Points (« x,y ») d'une courbe dans une boîte de 100 sur 40, de gauche à droite, 0 en bas.
 * @param {number[]} values une valeur par décennie
 * @param {number} max valeur du haut de la boîte
 * @returns {string}
 */
export function curvePoints(values, max, width = 100, height = 40) {
    if (values.length === 0) return '';
    const top = max > 0 ? max : 1;
    const step = values.length > 1 ? width / (values.length - 1) : 0;

    return values
        .map(
            (v, i) =>
                `${round(i * step)},${round(height - (Math.min(Math.max(v, 0), top) / top) * height)}`,
        )
        .join(' ');
}

/**
 * Valeur ronde au-dessus du maximum (1, 2, 5 fois une puissance de dix), pour le haut de l'axe.
 * @param {number} value
 */
export function niceMax(value) {
    if (!(value > 0)) return 1;
    const power = 10 ** Math.floor(Math.log10(value));
    const unit = value / power;
    const step = unit <= 1 ? 1 : unit <= 2 ? 2 : unit <= 5 ? 5 : 10;

    return step * power;
}

function round(n) {
    return Math.round(n * 100) / 100;
}
