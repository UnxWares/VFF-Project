// Utilitaires de la page « Chronologie du rail en France » (Pages/Network/Chronology.svelte) : adresses des
// vues filtrées, date d'un fait sous le titre de son année, parts de la frise des décennies. Sans accès au DOM.

/**
 * Adresse d'une vue de la chronologie : décennie, types (liste séparée par des virgules), région. Les filtres
 * vides sont omis.
 * @param {{ decade?: number|null, types?: string[], region?: string|null }} view
 */
export function chronologyHref({ decade = null, types = [], region = null } = {}) {
    const params = new URLSearchParams();
    if (decade !== null && decade !== undefined) params.set('decade', String(decade));
    if (types.length > 0) params.set('types', types.join(','));
    if (region) params.set('region', region);
    const query = params.toString().replaceAll('%2C', ',');

    return query === '' ? '/chronology' : `/chronology?${query}`;
}

/**
 * Date d'un fait sous le titre de son année : sans l'année (« 27 septembre », « juin »), ou le libellé
 * « dans l'année » pour une année seule.
 * @param {{ when: string, year: number, precision: string }} fact
 * @param {string} yearLabel
 */
export function dateInYear(fact, yearLabel) {
    if (fact.precision === 'year') return yearLabel;
    const stripped = fact.when.replace(new RegExp(`\\s*${fact.year}$`), '').trim();

    return stripped === '' ? yearLabel : stripped;
}

/**
 * Comptes d'une année à afficher : un élément par type, dans l'ordre reçu, sans les types sans fait détaillé.
 * @param {Record<string, { shown: number, hidden: number, km: number }>} counts
 * @returns {{ shown: Array<{ kind: string, shown: number, km: number }>, hidden: number }}
 */
export function yearTotals(counts) {
    const shown = [];
    let hidden = 0;
    for (const [kind, c] of Object.entries(counts ?? {})) {
        hidden += c.hidden;
        if (c.shown > 0) shown.push({ kind, shown: c.shown, km: c.km });
    }

    return { shown, hidden };
}

/**
 * Part de chaque décennie dans la frise (0 à 100), relative à la décennie la plus fournie ; au moins 4 % dès
 * qu'une décennie a un fait, pour rester visible.
 * @param {Array<{ decade: number, shown: number, hidden: number }>} decades
 * @returns {Record<number, number>}
 */
export function decadeShares(decades) {
    const max = Math.max(0, ...decades.map((d) => d.shown + d.hidden));
    /** @type {Record<number, number>} */
    const out = {};
    for (const d of decades) {
        const total = d.shown + d.hidden;
        out[d.decade] = max === 0 || total === 0 ? 0 : Math.max(4, Math.round((total / max) * 100));
    }

    return out;
}

/**
 * Faits d'une année rangés par type, dans l'ordre des types.
 * @param {Array<{ kind: string }>} facts
 * @param {string[]} kinds
 * @returns {Array<{ kind: string, facts: Array<object> }>}
 */
export function groupByKind(facts, kinds) {
    const groups = new Map(kinds.map((k) => [k, []]));
    for (const f of facts) {
        if (!groups.has(f.kind)) groups.set(f.kind, []);
        groups.get(f.kind).push(f);
    }

    return [...groups]
        .filter(([, list]) => list.length > 0)
        .map(([kind, list]) => ({ kind, facts: list }));
}
