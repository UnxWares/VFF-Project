// Frise du matériel roulant (/materials/timeline) : logique pure, testée par tests/js/materialTimeline.test.js.
// Rien n'est estimé : une année absente reste absente (la série est alors « sans date », jamais placée sur la frise).

/** Longueur du bord estompé d'une série retirée sans date de retrait (années, purement graphique). */
export const WITHDRAWN_FADE_YEARS = 12;

/** Minuscules sans accents, pour comparer des noms. */
export function fold(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/\p{Mn}+/gu, '')
        .toLowerCase()
        .trim();
}

/**
 * La série circulait-elle cette année-là ? Même règle que MaterialTimeline::stateIn (PHP).
 * `yes` : dates connues qui l'encadrent, ou série déclarée en service ; `maybe` : mise en service passée mais
 * fin non renseignée ; `no` : avant la mise en service, après le retrait, ou sans date de mise en service.
 *
 * @param {{ in_service_from: number|null, in_service_to: number|null, service_status: string|null }} row
 * @param {number} year
 * @returns {'yes'|'maybe'|'no'}
 */
export function stateIn(row, year) {
    const { in_service_from: from, in_service_to: to } = row;
    if (!Number.isInteger(from) || year < from) return 'no';
    if (Number.isInteger(to)) return year <= to ? 'yes' : 'no';
    return row.service_status === 'in_service' ? 'yes' : 'maybe';
}

/**
 * Nature de la fin de la barre : `range` (retrait connu), `ongoing` (déclarée en service), `withdrawn`
 * (retirée sans date : bord estompé court), `unknown` (aucune source : bord estompé jusqu'à aujourd'hui).
 */
export function endKind(row) {
    if (Number.isInteger(row.in_service_to)) return 'range';
    if (row.service_status === 'in_service') return 'ongoing';
    if (row.service_status === 'withdrawn') return 'withdrawn';
    return 'unknown';
}

/**
 * Bornes de l'axe : décennie qui contient la plus ancienne mise en service, jusqu'à la décennie qui suit
 * l'année courante.
 */
export function axisOf(rows, currentYear) {
    const years = rows.map((r) => r.in_service_from).filter(Number.isInteger);
    const first = years.length ? Math.min(...years) : currentYear - 100;
    const start = Math.floor(first / 10) * 10;
    const end = Math.ceil((currentYear + 1) / 10) * 10;
    return { start, end, span: end - start };
}

/**
 * Position d'une barre en pourcentage de l'axe, ou null si la mise en service est inconnue.
 * L'année de retrait est comprise : une série 1957-2015 couvre 1957 jusqu'à la fin de 2015.
 *
 * @returns {{ left: number, width: number, kind: string, fade: number }|null} `fade` : part de la barre
 *   estompée (0 à 1), à droite.
 */
export function barOf(row, axis, currentYear) {
    if (!Number.isInteger(row.in_service_from)) return null;
    const kind = endKind(row);
    const from = row.in_service_from;
    const to =
        kind === 'range'
            ? row.in_service_to + 1
            : kind === 'withdrawn'
              ? Math.min(from + WITHDRAWN_FADE_YEARS, currentYear + 1)
              : currentYear + 1;
    const end = Math.max(to, from + 1);
    const left = ((from - axis.start) / axis.span) * 100;
    const width = ((end - from) / axis.span) * 100;
    // Retirée sans date : court, presque tout estompé ; fin non renseignée : long, estompé à moitié.
    const fade = kind === 'withdrawn' ? 0.75 : kind === 'unknown' ? 0.5 : 0;
    return { left, width, kind, fade };
}

/** Années affichées de la période : « 1957-2015 », « depuis 1971 », « jusqu'en 1993 » via les clés `t`. */
export function periodLabel(row, t) {
    const from = row.in_service_from;
    const to = row.in_service_to;
    if (Number.isInteger(from) && Number.isInteger(to))
        return from === to ? String(from) : `${from}-${to}`;
    if (Number.isInteger(from)) {
        return row.service_status === 'withdrawn'
            ? t('materials_timeline.period.withdrawn', { year: from })
            : t('materials_timeline.period.from', { year: from });
    }
    if (Number.isInteger(to)) return t('materials_timeline.period.until', { year: to });
    return t('materials_timeline.period.none');
}

/**
 * Filtre : traction, constructeur (recherche partielle sans accents) et texte du nom.
 *
 * @param {object[]} rows
 * @param {{ group?: string, builder?: string, q?: string }} f
 */
export function filterRows(rows, f = {}) {
    const builder = fold(f.builder);
    const q = fold(f.q);
    return rows.filter((r) => {
        if (f.group && r.group !== f.group) return false;
        if (builder && !fold((r.builders ?? []).join(' ')).includes(builder)) return false;
        if (q && !fold(r.name).includes(q)) return false;
        return true;
    });
}

/**
 * Regroupe par traction dans l'ordre donné ; seules les séries datées vont sur la frise (triées par mise en
 * service puis par nom). Les tractions sans série sont omises.
 *
 * @returns {{ group: string, rows: object[] }[]}
 */
export function groupDated(rows, order) {
    return order
        .map((group) => ({
            group,
            rows: rows
                .filter((r) => r.group === group && Number.isInteger(r.in_service_from))
                .sort(
                    (a, b) => a.in_service_from - b.in_service_from || a.name.localeCompare(b.name),
                ),
        }))
        .filter((g) => g.rows.length > 0);
}

/** Séries sans date de mise en service, triées par nom. */
export function undatedRows(rows) {
    return rows
        .filter((r) => !Number.isInteger(r.in_service_from))
        .sort((a, b) => a.name.localeCompare(b.name));
}

/** Liste par décennie de mise en service (téléphone) : [{ decade, rows }], de la plus ancienne à la plus récente. */
export function byDecade(rows) {
    const map = new Map();
    for (const r of rows) {
        if (!Number.isInteger(r.in_service_from)) continue;
        const decade = Math.floor(r.in_service_from / 10) * 10;
        if (!map.has(decade)) map.set(decade, []);
        map.get(decade).push(r);
    }
    return [...map.entries()]
        .sort((a, b) => a[0] - b[0])
        .map(([decade, list]) => ({
            decade,
            rows: list.sort(
                (a, b) => a.in_service_from - b.in_service_from || a.name.localeCompare(b.name),
            ),
        }));
}

/**
 * Décennies de l'axe pour le repère : [{ year, left }] ; `major` toutes les 50 ans.
 */
export function decadeTicks(axis) {
    const ticks = [];
    for (let y = axis.start; y <= axis.end; y += 10) {
        ticks.push({ year: y, left: ((y - axis.start) / axis.span) * 100, major: y % 50 === 0 });
    }
    return ticks;
}

/** Position d'une année sur l'axe (pourcentage), au milieu de l'année. */
export function yearLeft(year, axis) {
    return ((year + 0.5 - axis.start) / axis.span) * 100;
}

/**
 * Résumé d'une année : séries en service (sûres), séries possibles, somme des exemplaires construits connus
 * des séries sûres.
 */
export function yearSummary(rows, year) {
    let yes = 0;
    let maybe = 0;
    let units = 0;
    for (const r of rows) {
        const s = stateIn(r, year);
        if (s === 'yes') {
            yes += 1;
            units += Number.isInteger(r.units_built) ? r.units_built : 0;
        } else if (s === 'maybe') maybe += 1;
    }
    return { yes, maybe, units };
}

/**
 * Tri d'un tableau : clés `name`, `group`, `from`, `to`, `units` ; les valeurs absentes en dernier quel que soit
 * le sens.
 */
export function sortTable(rows, key, dir = 'asc') {
    const sign = dir === 'desc' ? -1 : 1;
    const get = {
        name: (r) => r.name,
        group: (r) => r.group,
        from: (r) => r.in_service_from,
        to: (r) => r.in_service_to,
        units: (r) => r.units_built,
    }[key];
    return [...rows].sort((a, b) => {
        const x = get(a);
        const y = get(b);
        const nx = x === null || x === undefined;
        const ny = y === null || y === undefined;
        if (nx || ny) return nx === ny ? a.name.localeCompare(b.name) : nx ? 1 : -1;
        const c = typeof x === 'number' ? x - y : String(x).localeCompare(String(y));
        return c !== 0 ? sign * c : a.name.localeCompare(b.name);
    });
}

/** Constructeurs distincts des séries, du plus fréquent au moins fréquent (suggestions du filtre). */
export function builderChoices(rows) {
    const counts = {};
    for (const r of rows) for (const b of r.builders ?? []) counts[b] = (counts[b] ?? 0) + 1;
    return Object.keys(counts).sort((a, b) => counts[b] - counts[a] || a.localeCompare(b));
}
