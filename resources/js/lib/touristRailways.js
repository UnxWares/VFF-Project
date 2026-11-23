// Utilitaires de la page « Chemins de fer touristiques et vélorails » (Pages/Network/TouristRailways.svelte) :
// filtres, tri et mise en forme, sans accès au DOM. Les données viennent de App\Services\TouristRailways.

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
 * Exploitations qui répondent aux filtres : recherche dans le nom, l'exploitant, les lignes et les départements ;
 * type ; région.
 *
 * @param {Array<any>} railways
 * @param {{ q?: string, type?: string, region?: string }} filters
 */
export function filterRailways(railways, { q = '', type = '', region = '' } = {}) {
    const needle = fold(q);
    return railways.filter((r) => {
        if (type && r.type !== type) return false;
        if (region && !r.regions.includes(region)) return false;
        if (!needle) return true;
        const haystack = fold(
            [
                r.name,
                r.operator,
                ...r.departements,
                ...r.regions,
                ...r.lines.map((l) => l.name),
            ].join(' '),
        );
        return needle.split(' ').every((word) => haystack.includes(word));
    });
}

/** Voies vertes qui répondent à la recherche (nom, ligne) et à la région. */
export function filterGreenways(greenways, { q = '', region = '' } = {}) {
    const needle = fold(q);
    return greenways.filter((g) => {
        if (region && !g.regions.includes(region)) return false;
        if (!needle) return true;
        const haystack = fold(`${g.name} ${g.line.name} ${g.regions.join(' ')}`);
        return needle.split(' ').every((word) => haystack.includes(word));
    });
}

/**
 * Copie triée : `name` (alphabétique sans accents), `length` (la plus longue d'abord, longueurs inconnues en dernier),
 * `since` (la plus ancienne d'abord, années inconnues en dernier).
 *
 * @param {Array<any>} railways
 * @param {'name'|'length'|'since'} sort
 */
export function sortRailways(railways, sort = 'name') {
    const byName = (a, b) => fold(a.name).localeCompare(fold(b.name));
    const compare = {
        name: byName,
        length: (a, b) => (b.length_m ?? -1) - (a.length_m ?? -1) || byName(a, b),
        since: (a, b) => (a.start_year ?? Infinity) - (b.start_year ?? Infinity) || byName(a, b),
    }[sort];
    return [...railways].sort(compare ?? byName);
}

/** Nombre d'exploitations par type, dans l'ordre donné. */
export function countByType(railways, types) {
    return Object.fromEntries(types.map((t) => [t, railways.filter((r) => r.type === t).length]));
}

/** Kilomètres arrondis au dixième (null si inconnu). */
export function kmOf(meters) {
    return meters == null ? null : Math.round(meters / 100) / 10;
}

/** Points de la carte : exploitations qui ont des coordonnées, en GeoJSON. */
export function railwayPoints(railways) {
    return {
        type: 'FeatureCollection',
        features: railways
            .filter((r) => r.lat != null && r.lng != null)
            .map((r) => ({
                type: 'Feature',
                properties: { id: r.id, name: r.name, type: r.type },
                geometry: { type: 'Point', coordinates: [r.lng, r.lat] },
            })),
    };
}

/** Emprise [[ouest, sud], [est, nord]] des points, null s'il n'y en a pas. */
export function pointsBounds(collection) {
    const coords = collection.features.map((f) => f.geometry.coordinates);
    if (!coords.length) return null;
    const lngs = coords.map((c) => c[0]);
    const lats = coords.map((c) => c[1]);
    return [
        [Math.min(...lngs), Math.min(...lats)],
        [Math.max(...lngs), Math.max(...lats)],
    ];
}

/** Adresse de la fiche Wikidata d'un identifiant (Q…). */
export function wikidataUrl(qid) {
    return qid ? `https://www.wikidata.org/wiki/${qid}` : null;
}

/** Clé de traduction d'une saison du catalogue (texte français) : `fold` avec des tirets bas. */
export function seasonKey(season) {
    return fold(season).replaceAll(' ', '_');
}

/**
 * Faits d'exploitation affichés sous le nom, quand le catalogue les connaît (article cité par
 * `detail_source_url`) : portion exploitée, saison, jour d'ouverture. La saison est écrite en français
 * dans le catalogue : sa traduction est cherchée sous `season_text.<clé>`, sinon le texte français est
 * gardé et marqué `lang: 'fr'`.
 *
 * @param {any} r exploitation (App\Services\TouristRailways)
 * @param {(key: string, vars?: Record<string, string|number>) => string} t traduction (préfixe `tourist.` déjà mis)
 * @param {string} lang langue de la page
 * @returns {Array<{ key: string, text: string, lang: string | null }>}
 */
export function operationFacts(r, t, lang) {
    /** @type {Array<{ key: string, text: string, lang: string | null }>} */
    const facts = [];
    if (r.section?.from) {
        facts.push({
            key: 'section',
            text: r.section.to
                ? t('section', { from: r.section.from, to: r.section.to })
                : t('section_from', { from: r.section.from }),
            lang: null,
        });
    }
    if (r.season) {
        const key = `season_text.${seasonKey(r.season)}`;
        const translated = t(key);
        const known = translated !== key && translated !== `tourist.${key}`;
        facts.push({
            key: 'season',
            text: t('season', { season: known ? translated : r.season }),
            lang: known || lang === 'fr' ? null : 'fr',
        });
    }
    if (r.opened_on) {
        const d = new Date(`${String(r.opened_on).slice(0, 10)}T00:00:00Z`);
        if (!Number.isNaN(d.getTime())) {
            const date = d.toLocaleDateString(lang, {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                timeZone: 'UTC',
            });
            facts.push({ key: 'opened_on', text: t('opened_on', { date }), lang: null });
        }
    }
    return facts;
}
