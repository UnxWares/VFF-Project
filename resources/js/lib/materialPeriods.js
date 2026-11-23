// Périodes de circulation d'une série sur une ligne (App\Models\LineMaterialPeriod::toSheet), pour la
// section « Matériel roulant » de la fiche ligne et les lignes parcourues de la fiche d'une série.
// Logique pure, testée par tests/js/materialPeriods.test.js.
//
// Une fin absente veut dire « non donnée par la source », jamais « circule encore » : un début seul
// s'écrit « à partir de 1975 », jamais « depuis 1975 ».
import { formatEventDate } from './linePhases.js';

const year = (iso) => Number(String(iso).slice(0, 4));
const firstDate = (p) => p.start_date ?? p.end_date;

/**
 * Périodes regroupées pour une lecture rapide : les intervalles qui se chevauchent ou se touchent sont
 * réunis (« 1971-1989 » et « 1988-2001 » donnent « 1971-2001 ») ; un début seul compris dans un
 * intervalle disparaît, et seul le plus ancien des autres reste (la série a roulé au moins depuis
 * lui) ; de même pour une fin seule, dont seule la plus récente reste. Ordre chronologique.
 *
 * @param {Array<{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string}>} periods
 * @returns {Array<{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string}>}
 */
export function mergedPeriods(periods) {
    const ranges = [];
    for (const p of [...(periods ?? [])]
        .filter((p) => p.start_date && p.end_date)
        .sort((a, b) => a.start_date.localeCompare(b.start_date))) {
        const last = ranges.at(-1);
        if (last && year(p.start_date) <= year(last.end_date)) {
            if (p.end_date > last.end_date) {
                last.end_date = p.end_date;
                last.end_precision = p.end_precision;
            }
            continue;
        }
        ranges.push({ ...p });
    }
    const covered = (date) =>
        ranges.some((r) => year(date) >= year(r.start_date) && year(date) <= year(r.end_date));
    const starts = (periods ?? [])
        .filter((p) => p.start_date && !p.end_date && !covered(p.start_date))
        .sort((a, b) => a.start_date.localeCompare(b.start_date));
    const ends = (periods ?? [])
        .filter((p) => !p.start_date && p.end_date && !covered(p.end_date))
        .sort((a, b) => a.end_date.localeCompare(b.end_date));
    return [...ranges, starts[0], ends.at(-1)]
        .filter(Boolean)
        .map(({ start_date, start_precision, end_date, end_precision }) => ({
            start_date: start_date ?? null,
            start_precision: start_precision ?? null,
            end_date: end_date ?? null,
            end_precision: end_precision ?? null,
        }))
        .sort((a, b) => firstDate(a).localeCompare(firstDate(b)));
}

/**
 * Période en années : « 1971-2001 », « à partir de 1975 », « jusqu'en 2009 » (clés `materials.periods.*`).
 *
 * @param {{start_date: ?string, end_date: ?string}} p
 * @param {(key: string, opts?: object) => string} t traduction
 */
export function periodYears(p, t) {
    if (p.start_date && p.end_date) {
        const [from, to] = [year(p.start_date), year(p.end_date)];
        return from === to ? String(from) : `${from}-${to}`;
    }
    if (p.start_date) return String(t('materials.periods.from', { year: year(p.start_date) }));
    return String(t('materials.periods.until', { year: year(p.end_date) }));
}

/**
 * Relations assurées par une série sur une ligne, en texte court : « TER Paris-Montparnasse - Granville
 * (1990-2009) ». Réunit les relations de la fiche (`relations`, LineMaterial::relations(), avec leurs
 * périodes) et celles que portent les périodes (`relation` de LineMaterialPeriod::toSheet()), une par libellé.
 *
 * @param {Array<{label: string, service?: ?string, periods?: Array<any>}>|undefined} relations
 * @param {Array<{start_date: ?string, end_date: ?string, relation?: ?{label: string, service?: ?string}}>|undefined} periods
 * @param {(key: string, opts?: object) => string} t traduction
 * @returns {string[]}
 */
export function relationsList(relations, periods, t) {
    const byLabel = new Map();
    for (const r of relations ?? []) {
        byLabel.set(r.label, {
            label: r.label,
            service: r.service ?? null,
            periods: [...(r.periods ?? [])],
        });
    }
    for (const p of periods ?? []) {
        const r = p.relation;
        if (!r?.label) continue;
        const entry = byLabel.get(r.label) ?? {
            label: r.label,
            service: r.service ?? null,
            periods: [],
        };
        if (
            !entry.periods.some((q) => q.start_date === p.start_date && q.end_date === p.end_date)
        ) {
            entry.periods.push(p);
        }
        byLabel.set(r.label, entry);
    }
    return [...byLabel.values()].map((e) => {
        const years = e.periods.length ? periodsSummary(e.periods, t) : '';
        return `${e.service ? `${e.service} ` : ''}${e.label}${years ? ` (${years})` : ''}`;
    });
}

/** Toutes les périodes d'une série sur la ligne, en années et regroupées : « 1924-1958, à partir de 1963 ». */
export function periodsSummary(periods, t) {
    return mergedPeriods(periods)
        .map((p) => periodYears(p, t))
        .join(', ');
}

/** Portions placées sur la ligne et montrées (LineMaterialPeriod::SHOWN_LOCATIONS) ; `line` vaut la ligne entière. */
const SHOWN_PORTIONS = ['stations', 'landmarks'];

/**
 * Portion de la ligne parcourue, placée sur le tracé (`portion` de LineMaterialPeriod::toSheet()) : « de
 * Mantes-la-Jolie à Lisieux ». En français, « de Le Havre » s'écrit « du Havre », « à Les Aubrais » « aux
 * Aubrais », « de Évreux » « d’Évreux ». Null pour une portion absente, introuvable ou qui couvre la ligne.
 *
 * @param {?{location: string, from: ?{name: string}, to: ?{name: string}}} portion
 * @param {(key: string, opts?: object) => string} t traduction
 * @param {string} lang langue de l'interface (fr, en, de)
 * @returns {?string}
 */
export function portionText(portion, t, lang = 'fr') {
    if (!SHOWN_PORTIONS.includes(portion?.location) || !portion.from || !portion.to) return null;
    const text = String(
        t('materials.periods.portion', { from: portion.from.name, to: portion.to.name }),
    );
    if (!lang.startsWith('fr')) return text;
    return text
        .replace(/^de Les /u, 'des ')
        .replace(/^de Le /u, 'du ')
        .replace(/^de (?=[AEIOUYÀÂÉÈÊÎÔH])/u, 'd’')
        .replace(/ à Les /u, ' aux ')
        .replace(/ à Le /u, ' au ');
}

/**
 * Portions de la ligne parcourues par une série, une par paire de gares, avec leurs années : « de
 * Mantes-la-Jolie à Lisieux (à partir de 2020) ». Liste vide si aucune période n'a de portion montrée.
 *
 * @param {Array<{start_date: ?string, end_date: ?string, portion?: ?object}>|undefined} periods
 * @param {(key: string, opts?: object) => string} t traduction
 * @param {string} lang langue de l'interface (fr, en, de)
 * @returns {string[]}
 */
export function portionsList(periods, t, lang = 'fr') {
    const byPortion = new Map();
    for (const p of periods ?? []) {
        const text = portionText(p.portion, t, lang);
        if (!text) continue;
        byPortion.set(text, [...(byPortion.get(text) ?? []), p]);
    }
    return [...byPortion].map(([text, list]) => `${text} (${periodsSummary(list, t)})`);
}

/**
 * Période à la précision de la source : « 20 août 1982 - 30 mai 1986 », « début : juin 2009 »,
 * « fin : 1972 », suivie de la portion : placée sur le tracé (« de Mantes-la-Jolie à Lisieux », portionText),
 * sinon telle que le titre de section de l'article la nomme (« entre X et Y »).
 *
 * @param {{start_date: ?string, start_precision: ?string, end_date: ?string, end_precision: ?string, section_from?: ?string, section_to?: ?string, portion?: ?object}} p
 * @param {(key: string, opts?: object) => string} t traduction
 * @param {string} lang langue de l'interface (fr, en, de)
 */
export function periodText(p, t, lang = 'fr') {
    const date = (iso, precision) => formatEventDate(iso, precision ?? 'year', lang);
    let text;
    if (p.start_date && p.end_date) {
        text = `${date(p.start_date, p.start_precision)} - ${date(p.end_date, p.end_precision)}`;
    } else if (p.start_date) {
        text = String(
            t('materials.periods.start', { date: date(p.start_date, p.start_precision) }),
        );
    } else {
        text = String(t('materials.periods.end', { date: date(p.end_date, p.end_precision) }));
    }
    const portion = portionText(p.portion, t, lang);
    if (portion) {
        text += `, ${portion}`;
    } else if (p.section_from && p.section_to) {
        text += `, ${t('materials.periods.section', { from: p.section_from, to: p.section_to })}`;
    }
    return text;
}

/**
 * Faits sourcés sur les relations d'une série (`notes` de LineMaterial::relations, fichier
 * database/data/material_relation_notes.php), en phrases : « Paris-Montparnasse - Granville : au biodiesel
 * B100 (colza) depuis avril 2021, bilan publié en janvier 2023 ». Une note commune à plusieurs relations
 * (même carburant, même début) n'est donnée qu'une fois, avec la première relation qui la porte.
 *
 * @param {Array<{label: string, notes?: Array<any>}>|undefined} relations
 * @param {(key: string, opts?: object) => string} t traduction
 * @param {string} lang langue de l'interface (fr, en, de)
 * @returns {Array<{label: string, text: string, sources: Array<{title: string, publisher: string, date: string, url: string}>}>}
 */
export function relationNotes(relations, t, lang = 'fr') {
    const seen = new Set();
    const out = [];
    for (const r of relations ?? []) {
        for (const n of r.notes ?? []) {
            const key = `${n.kind}|${n.fuel}|${n.since}`;
            if (n.kind !== 'fuel' || seen.has(key)) continue;
            seen.add(key);
            const text = String(
                t('materials.relations.notes.fuel', {
                    fuel: n.fuel,
                    feedstock: t(`materials.relations.feedstock.${n.feedstock}`),
                    since: formatEventDate(n.since, n.since_precision ?? 'year', lang),
                }),
            );
            const reported = n.reported
                ? String(
                      t('materials.relations.notes.reported', {
                          date: formatEventDate(n.reported, 'month', lang),
                      }),
                  )
                : '';
            out.push({
                label: r.label,
                text: reported ? `${text}, ${reported}` : text,
                sources: n.sources ?? [],
            });
        }
    }
    return out;
}

/**
 * Fin de circulation d'une série sur la ligne (`end` de LineSheet, LineMaterialEnd), en texte court :
 * « Sur la ligne jusqu’en 2019 », « Série retirée en 2019 » (fin de la série, jamais présentée comme celle
 * de la ligne), « Série encore en service », « Fin inconnue sur la ligne ». Une période ouverte sur la
 * ligne (`since`) précède l'état de la série : « Sur la ligne à partir de 2014. Série encore en service ».
 *
 * @param {{kind: string, year: ?number, since?: ?number}} end
 * @param {(key: string, opts?: object) => string} t traduction
 */
export function endText(end, t) {
    const base = endBase(end, t);
    // Période ouverte sur la ligne (début daté, sans fin) : dite avant l'état de la série, sans « depuis ».
    return end?.since && end.kind !== 'line'
        ? `${t('materials.end.since', { year: end.since })}. ${base}`
        : base;
}

function endBase(end, t) {
    switch (end?.kind) {
        case 'line':
            return String(t('materials.end.line', { year: end.year }));
        case 'series_in_service':
            return String(t('materials.end.series_in_service'));
        case 'series_withdrawn':
            return end.year
                ? String(t('materials.end.series_withdrawn_year', { year: end.year }))
                : String(t('materials.end.series_withdrawn'));
        default:
            return String(t('materials.end.unknown'));
    }
}

/** Raisons de doute connues (`link_doubt`, App\Services\Materials\LineMaterialDoubt::REASONS), plus `other`. */
const DOUBT_REASONS = [
    'trial',
    'one_off',
    'order',
    'positioning',
    'preserved',
    'possible',
    'diverted',
    'restricted',
    'component',
    'section',
    'other',
];

/**
 * Séries de la ligne qui ont assuré le service et séries citées sans service attesté (essai, présentation,
 * commande, acheminement à vide… : `link_doubt`), dans l'ordre reçu.
 *
 * @template {{ link_doubt?: ?string }} M
 * @param {M[]} materials
 * @returns {{ served: M[], doubtful: M[] }}
 */
export function splitDoubtful(materials) {
    const served = [];
    const doubtful = [];
    for (const m of materials ?? []) (m.link_doubt ? doubtful : served).push(m);
    return { served, doubtful };
}

/** Raison du doute en mots (« essais »), `other` pour une raison que cette version ne connaît pas. */
export function doubtText(reason, t) {
    const key = DOUBT_REASONS.includes(reason) ? reason : 'other';
    return String(t(`materials.doubt.reasons.${key}`));
}

/**
 * Borne de date d'une série sur la ligne (`earliest_on_line`) : « Sur la ligne au plus tôt à
 * l'électrification : juin 1996 » ; quand la première électrification est celle d'une section, elle est
 * nommée (« … à la première électrification d'une portion : 1989 (Bernay - Lisieux) »). Quand une période
 * sourcée commence avant (`conflict`), la borne n'est pas dite comme un fait : la fiche signale qu'une
 * électrification plus ancienne manque probablement. Null sans borne ou pour un motif inconnu.
 *
 * @param {?{ reason: string, date: string, precision?: ?string, section?: ?string, conflict?: boolean }} earliest
 * @param {(key: string, opts?: object) => string} t traduction
 * @param {string} lang langue de l'interface (fr, en, de)
 */
export function earliestText(earliest, t, lang = 'fr') {
    if (!earliest?.date || earliest.reason !== 'electrification') return null;
    const date = formatEventDate(earliest.date, earliest.precision ?? 'year', lang);
    if (earliest.conflict) return String(t('materials.earliest.conflict', { date }));
    return String(
        earliest.section
            ? t('materials.earliest.section', { date, section: earliest.section })
            : t('materials.earliest.electrification', { date }),
    );
}
