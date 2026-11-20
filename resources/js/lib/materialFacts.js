// Matériel roulant (Pages/Materials/*, section « Matériel roulant » de la fiche ligne) : textes tirés
// des données sans rien inventer. Logique pure, testée par tests/js/materialFacts.test.js.

/**
 * Période de service d'une série d'après ses deux années.
 *
 * Une année absente veut dire « non renseignée par la source », jamais « en service aujourd'hui » :
 * Wikidata ne dit pas si une série circule encore (`in_service_to` est nul pour les deux cas).
 * Rien n'est donc affiché comme « depuis 1950 » ni « 1950-aujourd'hui ».
 *
 * @param {number|null|undefined} from année de mise en service
 * @param {number|null|undefined} to année de retrait
 * @returns {{ kind: 'range'|'from'|'until', from: number|null, to: number|null } | null}
 *   `range` : les deux années ; `from` : mise en service seule ; `until` : retrait seul ; null : rien de connu.
 */
export function servicePeriod(from, to) {
    const start = Number.isInteger(from) ? from : null;
    const end = Number.isInteger(to) ? to : null;
    if (start !== null && end !== null) return { kind: 'range', from: start, to: end };
    if (start !== null) return { kind: 'from', from: start, to: null };
    if (end !== null) return { kind: 'until', from: null, to: end };
    return null;
}

/**
 * Période en phrase courte : « 1950-1988 », « mise en service : 1950 », « retrait : 1990 ».
 * Clés `materials.service.*` ; une même année au début et à la fin s'écrit une fois.
 *
 * @param {number|null|undefined} from
 * @param {number|null|undefined} to
 * @param {(key: string, opts?: object) => string} t traduction
 * @returns {string|null} null si aucune année n'est connue
 */
export function servicePeriodText(from, to, t) {
    const p = servicePeriod(from, to);
    if (!p) return null;
    if (p.kind === 'range') return p.from === p.to ? String(p.from) : `${p.from}-${p.to}`;
    if (p.kind === 'from') return String(t('materials.service.from', { year: p.from }));
    return String(t('materials.service.until', { year: p.to }));
}

/** Identifiant Wikidata seul (« Q529531 ») : nom d'un élément sans libellé, jamais affiché. */
const BARE_WIKIDATA_ID = /^Q\d+$/;

/** Constructeurs nommés sur la carte d'une série (fiche ligne) avant « et N autres ». */
export const BUILDERS_SHOWN = 3;

/**
 * Constructeurs d'une série en une ligne : tous jusqu'à quatre, sinon les trois premiers et le nombre
 * des autres (« Henschel, Krupp, Borsig et 10 autres », clés `materials.facts.builders_more_*`). La
 * liste complète reste sur la fiche de la série. Sans liste, le texte joint de la base (`builder`, qui
 * peut finir par « … ») est repris. Un identifiant Wikidata brut (« Q529531 », constructeur sans libellé)
 * n'est jamais montré comme un nom.
 *
 * @param {string[]|null|undefined} names noms des constructeurs, sans doublon
 * @param {string|null|undefined} fallback texte joint (`builder`)
 * @param {(key: string, opts?: object) => string} t traduction
 * @returns {string|null} null si aucun constructeur n'est connu
 */
export function buildersText(names, fallback, t) {
    const list = (names ?? []).filter((name) => name && !BARE_WIKIDATA_ID.test(name));
    if (!list.length) {
        const joined = (fallback ?? '')
            .split(', ')
            .filter((name) => name && !BARE_WIKIDATA_ID.test(name))
            .join(', ');
        return joined || null;
    }
    if (list.length <= BUILDERS_SHOWN + 1) return list.join(', ');
    return String(
        t('materials.facts.builders_more', {
            list: list.slice(0, BUILDERS_SHOWN).join(', '),
            count: list.length - BUILDERS_SHOWN,
        }),
    );
}

/**
 * Fiabilité d'un lien ligne × série, dite en clair (clés `materials.confidence.*`).
 * `high` : Wikidata ou partie « matériel » de l'article de la ligne ; `medium` : autre section ;
 * `low` : article commun à plusieurs lignes de VFF, ou preuves qui portent toutes un doute (commande, essai…).
 *
 * @param {string|null|undefined} confidence high | medium | low
 * @param {string|null|undefined} kind nature de la preuve (wikidata, wikipedia_line_article…)
 * @returns {string} clé de traduction
 */
export function confidenceKey(confidence, kind) {
    if (kind === 'contribution')
        return confidence === 'low'
            ? 'materials.confidence.low_contribution'
            : 'materials.confidence.high_contribution';
    if (confidence === 'high') {
        return kind === 'wikidata'
            ? 'materials.confidence.high_wikidata'
            : 'materials.confidence.high';
    }
    if (confidence === 'low') return 'materials.confidence.low';
    return 'materials.confidence.medium';
}

/** Sans accents ni capitales : « evreux » trouve « Évreux ». */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

/**
 * Autre nom qui explique un résultat de recherche : « Picasso » pour l'X 3800 quand le nom de la
 * série ne contient pas le texte cherché.
 *
 * @param {{ name: string, designations?: string[] }} material
 * @param {string} query texte cherché
 * @returns {string|null}
 */
export function matchedDesignation(material, query) {
    const needle = fold(query).trim();
    if (!needle || fold(material.name).includes(needle)) return null;
    return (material.designations ?? []).find((d) => fold(d).includes(needle)) ?? null;
}

/**
 * Provenance des champs d'une fiche, pour une mention discrète : la source commune à presque tous
 * les champs (citée une fois sous la fiche) et, pour chaque champ venu d'ailleurs, sa propre source.
 *
 * @param {Array<{ field: string, source: string, url?: string|null, name?: string|null, license?: string|null }>} fieldSources
 * @returns {{ main: { source: string, url: string|null, name: string|null, license: string|null } | null,
 *   byField: Record<string, { source: string, url: string|null, name: string|null, license: string|null }> }}
 *   `byField` ne contient que les champs dont la source diffère de `main`.
 */
export function provenanceOf(fieldSources) {
    const rows = fieldSources ?? [];
    const tally = new Map();
    for (const r of rows) tally.set(r.source, (tally.get(r.source) ?? 0) + 1);
    const top = [...tally.entries()].sort((a, b) => b[1] - a[1])[0]?.[0];
    const pick = (r) => ({
        source: r.source,
        url: r.url ?? null,
        name: r.name ?? null,
        license: r.license ?? null,
    });
    const first = rows.find((r) => r.source === top);
    /** @type {Record<string, ReturnType<typeof pick>>} */
    const byField = {};
    for (const r of rows) if (r.source !== top) byField[r.field] = pick(r);

    return { main: first ? pick(first) : null, byField };
}

/**
 * Texte découpé autour des flèches de renumérotation (« PLM 1-30 → Est 601-612 », autre nom tiré
 * de Wikidata) : la page affiche la flèche, masquée aux lecteurs d'écran, avec un équivalent lu
 * (« renumérotée en ») à sa place (RGAA 13.5).
 *
 * @param {string|null|undefined} text
 * @returns {string[]} morceaux de part et d'autre des flèches (un seul s'il n'y en a pas)
 */
export function splitArrows(text) {
    return String(text ?? '')
        .split(/\s*→\s*/)
        .filter((part, i, all) => part !== '' || all.length === 1);
}

/**
 * Date d'une source écrite pour la lecture : « 13 juillet 2026 » (jour), « juillet 2026 » (mois) ou
 * « 2026 » (année), selon la précision de la source (`AAAA-MM-JJ`, `AAAA-MM`, `AAAA`).
 *
 * @param {string|null|undefined} value
 * @param {string} lang langue de l'interface
 * @returns {string|null} null si la valeur est absente ou illisible
 */
export function formatSourceDate(value, lang) {
    const m = /^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/.exec(String(value ?? ''));
    if (!m) return null;
    const year = Number(m[1]);
    if (!m[2]) return String(year);
    const month = Number(m[2]);
    const day = m[3] === undefined ? undefined : Number(m[3]);
    // Midi UTC : la date ne bascule pas d'un jour selon le fuseau du navigateur.
    const date = new Date(Date.UTC(year, month - 1, day ?? 1, 12));
    // Un mois ou un jour hors du calendrier (« 2026-13-45 ») déborderait sur la date suivante : illisible.
    if (date.getUTCMonth() !== month - 1 || (day !== undefined && date.getUTCDate() !== day)) {
        return null;
    }
    /** @type {Intl.DateTimeFormatOptions} */
    const options =
        day === undefined
            ? { month: 'long', year: 'numeric' }
            : { day: 'numeric', month: 'long', year: 'numeric' };

    // Anglais britannique (« 13 July 2026 »), comme le reste du site.
    return date.toLocaleDateString(lang === 'en' ? 'en-GB' : lang, { ...options, timeZone: 'UTC' });
}

/**
 * État d'utilisation d'une série, dit seulement quand une source le dit (bloc `service` de l'API).
 * Une date de retrait absente ne donne jamais « en service » : sans source, rien n'est affiché.
 *
 * @param {{ status?: string|null, units_in_service?: number|null, as_of?: string|null, checked_at?: string|null }|null|undefined} service
 * @param {(key: string, opts?: object) => string} t traduction
 * @param {string} lang langue de l'interface
 * @returns {{ status: string, label: string, detail: string|null } | null} `detail` : effectif daté et date de lecture
 */
export function serviceState(service, t, lang) {
    const status = service?.status;
    if (status !== 'in_service' && status !== 'withdrawn') return null;
    const asOf = formatSourceDate(service.as_of, lang);
    const checked = formatSourceDate(service.checked_at, lang);
    const parts = [];
    if (asOf && Number.isInteger(service.units_in_service)) {
        parts.push(
            t('materials.service_state.fleet', { count: service.units_in_service, date: asOf }),
        );
    } else if (asOf) {
        parts.push(t('materials.service_state.as_of', { date: asOf }));
    }
    if (checked) parts.push(t('materials.service_state.checked', { date: checked }));

    return {
        status,
        label: String(t(`materials.service_state.${status}`)),
        detail: parts.length ? parts.join(', ') : null,
    };
}
