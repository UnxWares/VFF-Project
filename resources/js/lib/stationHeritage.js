/**
 * Patrimoine d'une gare (StationHeritage.svelte) : textes composés sans DOM, à partir du bloc `heritage`
 * de la fiche (App\Services\Heritage\StationHeritage).
 */

/** Rôles de la notice Mérimée qui désignent le concepteur du bâtiment (les sculpteurs, verriers… sont écartés). */
const DESIGNER_ROLE = /architecte|ing[ée]nieur/i;

/**
 * Noms des architectes et ingénieurs d'un objet, sans doublon.
 * @param {Record<string, any>} item
 * @returns {string[]}
 */
export function designerNames(item) {
    const names = (item.architects ?? [])
        .filter((a) => a.role && DESIGNER_ROLE.test(a.role))
        .map((a) => a.name);
    return [...new Set(names)];
}

/**
 * Année d'une date `AAAA-MM-JJ`, null si absente.
 * @param {string|null|undefined} date
 * @returns {number|null}
 */
export function yearOf(date) {
    const match = /^(\d{4})-/.exec(date ?? '');
    return match ? Number(match[1]) : null;
}

/**
 * Objets à montrer : le bâtiment voyageurs puis les ouvrages, sans le simple recensement quand un objet de la
 * même gare est classé, inscrit ou labellisé (le recensement est alors redondant).
 * @param {{ items?: Array<Record<string, any>> }|null|undefined} heritage
 * @returns {Array<Record<string, any>>}
 */
export function visibleItems(heritage) {
    const items = heritage?.items ?? [];
    const strong = items.some((i) => i.protection !== 'inventory');
    return strong ? items.filter((i) => i.protection !== 'inventory') : items.slice(0, 1);
}

/**
 * Libellé de la construction : « 1925 », « de 1896 à 1898 » ou le siècle de la notice. Null si inconnue.
 * @param {Record<string, any>} item
 * @param {(key: string, opts?: Record<string, unknown>) => string} t
 * @returns {string|null}
 */
export function builtText(item, t) {
    const built = item.built ?? {};
    if (built.from != null && built.to != null && built.to !== built.from) {
        return t('stations.heritage.built_range', { from: built.from, to: built.to });
    }
    if (built.from != null) return t('stations.heritage.built_year', { year: built.from });
    if (built.period) return t('stations.heritage.built_period', { period: built.period });
    return null;
}

/**
 * Phrase d'un objet : « Bâtiment voyageurs inscrit aux monuments historiques (1984), architecte Victor Laloux ».
 * Un ouvrage s'ouvre sur le titre de la notice : « Dépôt SNCF de Mohon : inscription aux monuments historiques (2006) ».
 * @param {Record<string, any>} item
 * @param {(key: string, opts?: Record<string, unknown>) => string} t
 * @returns {string}
 */
export function headline(item, t) {
    const partial =
        item.scope === 'partial' && ['classified', 'registered'].includes(item.protection);
    const key = `${item.protection}${partial ? '_partial' : ''}`;
    const year = yearOf(item.protection_date);
    const names = item.element === 'building' ? designerNames(item) : [];
    let text;
    if (item.element === 'building') {
        text = t(`stations.heritage.building.${key}`);
    } else {
        text = t('stations.heritage.works_line', {
            title: item.title ?? t('stations.heritage.works_fallback'),
            what: t(`stations.heritage.works.${key}`),
        });
    }
    if (year !== null && item.protection !== 'inventory') {
        text = t('stations.heritage.with_year', { text, year });
    }
    if (names.length > 0) {
        text = `${text}, ${t(
            `stations.heritage.architect_${names.length === 1 ? 'one' : 'other'}`,
            {
                names: names.join(', '),
            },
        )}`;
    }
    return text;
}
