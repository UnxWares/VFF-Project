// Frise « Signalisation au fil du temps » de la fiche ligne : mises en service datées lues dans
// Wikipédia (App\Services\Signalling\LineSignallingHistory::forSheet). Logique pure, testée par
// tests/js/signallingHistory.test.js.
//
// Le KVB est un contrôle de vitesse, jamais un cantonnement : son rôle (`speed_control`) lui donne
// le libellé « Contrôle de vitesse », comme sur la grille des tranches actuelles.
import { formatEventDate } from './linePhases.js';
import { pkLabel } from './levelCrossingMap.js';
import { tieUnits } from './typo.js';

/** Origines qui reçoivent une note sous la portion (`text` et `list` : phrase ou puce, sans note). */
const ORIGIN_NOTES = ['opening', 'infobox', 'table'];

/** Libellé de rôle, déjà traduit par la grille des tranches (`lines.signalling.*`, avec les deux-points). */
const ROLE_KEYS = {
    block: 'lines.signalling.block',
    cab: 'lines.signalling.cabin',
    speed_control: 'lines.signalling.protection',
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
        // Fait lu ailleurs que dans une phrase : date d'ouverture d'une LGV, infobox, tableau.
        note: ORIGIN_NOTES.includes(f.origin) ? t(`signalling_history.origin.${f.origin}`) : null,
    }));
}

/** Titres distincts des articles dont viennent les faits, dans l'ordre. */
export function articleTitles(facts) {
    return [...new Set(facts.map((f) => f.source.title))];
}
