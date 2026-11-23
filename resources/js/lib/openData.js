// Page « Données ouvertes » : mise en forme des entrées du catalogue (OpenDataController).

/**
 * Taille lisible d'un fichier dans la langue de la page (octets, ko, Mo, Go, en base 1 024).
 *
 * @param {number} bytes
 * @param {string} lang
 * @returns {string}
 */
export function formatBytes(bytes, lang = 'fr') {
    const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte'];
    let value = Math.max(0, Number(bytes) || 0);
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }
    return new Intl.NumberFormat(lang, {
        style: 'unit',
        unit: units[i],
        unitDisplay: 'short',
        maximumFractionDigits: i === 0 || value >= 100 ? 0 : 1,
    }).format(value);
}

/**
 * Nombre de lignes, de sections et de gares d'un jeu du réseau, lu dans ses fichiers CSV.
 *
 * @param {Array<{name: string, rows: number|null}>} files
 * @returns {{lines: number, sections: number, stations: number}}
 */
export function networkCounts(files = []) {
    const rows = (name) => files.find((f) => f.name === name)?.rows ?? 0;
    return {
        lines: rows('lignes.csv'),
        sections: rows('sections.csv'),
        stations: rows('gares.csv'),
    };
}

/**
 * Lignes du tableau « ce qui n'entre pas » : une par donnée, triées par nombre décroissant, sans
 * les données à zéro.
 *
 * @param {Record<string, {removed?: number, replaced?: number}>} withheld
 * @returns {Array<{key: string, removed: number, replaced: number}>}
 */
export function withheldRows(withheld = {}) {
    return Object.entries(withheld ?? {})
        .map(([key, v]) => ({ key, removed: v?.removed ?? 0, replaced: v?.replaced ?? 0 }))
        .filter((r) => r.removed + r.replaced > 0)
        .sort(
            (a, b) =>
                b.removed + b.replaced - (a.removed + a.replaced) || a.key.localeCompare(b.key),
        );
}
