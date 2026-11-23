// Frise « Signalisation au fil du temps » de la fiche ligne : mises en service datées lues dans
// Wikipédia ou dans d'autres documents relevés par VFF (`source.provider` : `manual`, avec
// `source.documents`) (App\Services\Signalling\LineSignallingHistory::forSheet). Logique pure, testée
// par tests/js/signallingHistory.test.js.
//
// Le KVB est un contrôle de vitesse, jamais un cantonnement : son rôle (`speed_control`) lui donne
// le libellé « Contrôle de vitesse », comme sur la grille des tranches actuelles.
import { formatEventDate } from './linePhases.js';
import { pkLabel } from './levelCrossingMap.js';
import { tieUnits } from './typo.js';

/** Origines qui reçoivent une note sous la portion (`text` et `list` : phrase ou puce, sans note). */
const ORIGIN_NOTES = ['opening', 'infobox', 'table'];
// `document` : fait daté hors de l'article, par les documents liés sur la ligne (pas de note).

/** Libellé de rôle, déjà traduit par la grille des tranches (`lines.signalling.*`, avec les deux-points). */
const ROLE_KEYS = {
    block: 'lines.signalling.block',
    cab: 'lines.signalling.cabin',
    speed_control: 'lines.signalling.protection',
    remote_control: 'lines.signalling.remote_control',
};

/** @param {(key: string, opts?: object) => string} t fonction de traduction ($i18n.t) */
function translated(t, key) {
    const label = t(key);
    return label === key ? null : label;
}

/** Libellé d'un système : propre à l'historique (BM, CT, TVM, ETCS génériques), sinon celui des tranches, sinon le code. */
export function systemLabel(code, t) {
    return tieUnits(
        translated(t, `signalling_history.system.${code}`) ??
            translated(t, `lines.signalling.codes.${code}`) ??
            code,
    );
}

/** Portion de la mise en service : toute la ligne, lieux et PK tels que l'article les donne, sinon « non précisée ». */
export function portionText(fact, t, pkWord) {
    if (fact.scope === 'whole_line') return t('signalling_history.section.whole_line');
    const s = fact.section;
    if (fact.scope !== 'section' || !s) return t('signalling_history.section.unspecified');
    const places = s.from && s.to ? 'between' : s.from ? 'from' : s.to ? 'to' : null;
    const pk =
        s.pk_start_m != null && s.pk_end_m != null
            ? t('signalling_history.section.pk', {
                  pk: pkWord,
                  from: pkLabel(s.pk_start_m),
                  to: pkLabel(s.pk_end_m),
              })
            : null;
    const named = places
        ? t(`signalling_history.section.${places}`, { from: s.from, to: s.to })
        : null;
    return (
        [named, pk].filter(Boolean).join(' (') + (named && pk ? ')' : '') ||
        t('signalling_history.section.unspecified')
    );
}

/**
 * Lignes de la frise, du plus ancien au plus récent (l'ordre du serveur est gardé).
 *
 * @param {Array<any>} facts faits de `LineSignallingHistory::forSheet`
 * @param {{ t: (key: string, opts?: object) => string, lang: string, pkWord: string }} ctx
 */
export function historyRows(facts, { t, lang, pkWord }) {
    return facts.map((f) => ({
        key: [
            f.system,
            f.date,
            f.section?.from,
            f.section?.to,
            f.section?.pk_start_m,
            f.section?.pk_end_m,
        ].join('|'),
        when: formatEventDate(f.date, f.date_precision, lang),
        iso: f.date,
        kind: t(ROLE_KEYS[f.role] ?? ROLE_KEYS.block),
        system: systemLabel(f.system, t),
        portion: portionText(f, t, pkWord),
        url: f.source.url,
        title: f.source.title,
        // Fait daté par des documents (communiqué, rapport, presse) : un lien par document, nommé par son éditeur.
        documents:
            f.source.provider === 'manual'
                ? (f.source.documents ?? []).map((d) => ({
                      url: d.url,
                      label: d.publisher,
                      title: d.title,
                  }))
                : [],
        // Fait lu ailleurs que dans une phrase : date d'ouverture d'une LGV, infobox, tableau.
        note: ORIGIN_NOTES.includes(f.origin) ? t(`signalling_history.origin.${f.origin}`) : null,
    }));
}

/** Titres distincts des articles de Wikipédia dont viennent les faits, dans l'ordre. */
export function articleTitles(facts) {
    return [
        ...new Set(facts.filter((f) => f.source.provider !== 'manual').map((f) => f.source.title)),
    ];
}

/** Au moins un fait daté par des documents relevés par VFF (et non par Wikipédia) ? */
export function hasDocumentFacts(facts) {
    return facts.some((f) => f.source.provider === 'manual');
}

// Repères à l'époque choisie sur la fiche (phase du « tracé dans le temps ») : même règle que la carte
// par ère et que l'API avec `era` (App\Services\Signalling\SignallingHistoryState::inForce), avec en
// plus l'incertitude d'une date connue à l'année ou au mois près.

/** Tolérance (m) sur les bornes de deux portions placées (SignallingHistoryState::TOLERANCE_M). */
const TOLERANCE_M = 200;

/** Premier et dernier jour (AAAA-MM-JJ) d'une date connue au jour, au mois ou à l'année près. */
export function periodBounds(iso, precision) {
    const d = String(iso ?? '').slice(0, 10);
    const [y, m] = d.split('-');
    if (precision === 'year') return [`${y}-01-01`, `${y}-12-31`];
    if (precision === 'month') {
        const last = new Date(Date.UTC(Number(y), Number(m), 0)).getUTCDate();
        return [`${y}-${m}-01`, `${y}-${m}-${String(last).padStart(2, '0')}`];
    }
    return [d, d];
}

/**
 * Moment d'une phase de la ligne, ou null : sa date à sa précision ; un changement non daté
 * (`range`) vaut la fin de sa période (31 décembre, ou aujourd'hui pour l'ère courante), comme la carte.
 *
 * @returns {{ start: string, end: string, date: string, precision: string } | null}
 */
export function phaseMoment(phase) {
    if (!phase?.date) return null;
    const precision = phase.precision === 'range' ? 'day' : (phase.precision ?? 'day');
    const [start, end] = periodBounds(phase.date, precision);
    return { start, end, date: start, precision };
}

/** Groupe de remplacement : un cantonnement remplace un cantonnement, une TVM une TVM, le KVB le KVB. */
export function factGroup(f) {
    if (f.role === 'block') return 'block';
    if (f.role === 'cab') return String(f.system).replace(/[-_].*$/, '');
    return f.system;
}

function placedRange(f) {
    const s = f.section;
    return s?.pk_start_m != null && s?.pk_end_m != null ? [s.pk_start_m, s.pk_end_m] : null;
}

/** Le fait récent recouvre-t-il le fait ancien (toute la ligne, ou sa portion placée contient l'autre) ? */
export function factCovers(recent, old) {
    if (recent.scope === 'whole_line') return true;
    const n = placedRange(recent);
    const o = placedRange(old);
    if (!n || !o) return false;
    return n[0] <= o[0] + TOLERANCE_M && n[1] >= o[1] - TOLERANCE_M;
}

/**
 * Situation de chaque fait au moment choisi, dans l'ordre des faits :
 *   - `later` : mis en service après ce moment ;
 *   - `uncertain` : connu à l'année ou au mois près, période qui contient ce moment (avant ou après ?) ;
 *   - `replaced` (avec `by`) : un fait plus récent du même groupe, sûrement en service, le recouvre ;
 *   - `in_force` : sinon.
 * Un fait récent sans portion placée ne remplace rien : on ne sait pas où il s'applique.
 *
 * @param {Array<any>} facts faits de `LineSignallingHistory::forSheet`
 * @param {{ start: string, end: string }} moment
 * @returns {Array<{ status: 'later' | 'uncertain' | 'replaced' | 'in_force', by?: any }>}
 */
export function statusesAt(facts, moment) {
    const bounds = facts.map((f) => periodBounds(f.date, f.date_precision));
    const certain = (i) => bounds[i][1] <= moment.start;
    return facts.map((f, i) => {
        if (bounds[i][0] > moment.end) return { status: 'later' };
        if (!certain(i)) return { status: 'uncertain' };
        const by = facts.find(
            (n, j) =>
                j !== i &&
                n.date > f.date &&
                certain(j) &&
                factGroup(n) === factGroup(f) &&
                factCovers(n, f),
        );
        return by ? { status: 'replaced', by } : { status: 'in_force' };
    });
}

/**
 * Texte de la situation d'un fait au moment choisi (`signalling_history.status.*`).
 *
 * @param {{ status: string, by?: any }} s
 * @param {any} fact
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang
 */
export function statusText(s, fact, t, lang) {
    if (s.status === 'replaced') {
        return tieUnits(
            t('signalling_history.status.replaced', {
                system: s.by.system,
                when: formatEventDate(s.by.date, s.by.date_precision, lang),
            }),
        );
    }
    if (s.status === 'uncertain') {
        return t(
            fact.date_precision === 'month'
                ? 'signalling_history.status.uncertain_month'
                : 'signalling_history.status.uncertain_year',
        );
    }
    return t(`signalling_history.status.${s.status}`);
}

/** Date de publication d'un document (AAAA, AAAA-MM ou AAAA-MM-JJ) à sa précision, ou null. */
function publishedLabel(published, lang) {
    const p = String(published ?? '');
    if (/^\d{4}$/.test(p)) return p;
    if (/^\d{4}-\d{2}$/.test(p)) return formatEventDate(`${p}-01`, 'month', lang);
    if (/^\d{4}-\d{2}-\d{2}/.test(p)) return formatEventDate(p, 'day', lang);
    return null;
}

/**
 * Corrections repliées sous la frise : dates lues dans un article (ou un document) que des documents
 * contredisent (App\Services\Signalling\LineSignallingHistory::correctionsForSheet). Elles ne sont ni dans
 * la frise ni sur la carte ; chaque ligne dit la date écartée, où elle a été lue, ce que VFF en fait,
 * pourquoi (texte en français) et les documents qui la contredisent, nommés par leur éditeur.
 *
 * @param {Array<any>} corrections
 * @param {{ t: (key: string, opts?: object) => string, lang: string, pkWord: string }} ctx
 */
export function correctionRows(corrections, { t, lang, pkWord }) {
    return corrections.map((c) => ({
        key: [c.system, c.date, c.section?.from, c.section?.to, c.original?.url].join('|'),
        when: formatEventDate(c.date, c.date_precision, lang),
        iso: c.date,
        system: systemLabel(c.system, t),
        portion: c.section
            ? portionText({ scope: 'section', section: c.section }, t, pkWord)
            : null,
        action: t(
            c.action === 'replace'
                ? 'signalling_history.corrections.action.replace'
                : 'signalling_history.corrections.action.neutralize',
        ),
        reason: c.reason || null,
        original: {
            url: c.original?.url,
            title: c.original?.title,
            label:
                c.original?.provider === 'manual'
                    ? t('signalling_history.corrections.original_document')
                    : t('signalling_history.source.link'),
        },
        documents: (c.sources ?? []).map((d) => ({
            url: d.url,
            label: d.publisher,
            title: d.title,
            published: publishedLabel(d.published, lang),
        })),
    }));
}

/**
 * Mention d'une ligne classique en service dont aucun document libre consulté ne date la mise en service de la
 * signalisation (`signalling_dating.status` = `undated`, calculé côté serveur par SignallingDating), avec la date
 * du dernier passage sur les documents. Nulle pour tout autre état.
 *
 * @param {{ status?: string, checked_at?: string } | null | undefined} dating
 * @param {(key: string, opts?: object) => string} t
 * @param {string} lang locale de format des dates
 * @returns {{ message: string, checked: string } | null}
 */
export function datingNotice(dating, t, lang) {
    if (dating?.status !== 'undated') return null;
    return {
        message: t('signalling_history.dating.undated'),
        checked: t('signalling_history.dating.checked', {
            date: formatEventDate(dating.checked_at, 'day', lang),
        }),
    };
}
