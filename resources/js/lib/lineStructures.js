/**
 * Ouvrages d'art de la fiche ligne (App\Services\Structures\LineStructures::forSheet) : libellés et
 * choix des ouvrages mis en avant. Fonctions pures (tests : tests/js/lineStructures.test.js).
 */
import { formatEventDate } from '$lib/linePhases.js';

/** Au-delà de ce nombre d'ouvrages, la liste complète est repliée et seuls les principaux restent visibles. */
export const FOLD_AFTER = 12;

/** Longueur (m) à partir de laquelle un ouvrage sans nom est quand même mis en avant. */
export const NOTABLE_LENGTH_M = 300;

/** Types dans l'ordre du résumé. */
export const KINDS = ['tunnel', 'viaduct', 'bridge'];

/** Ouvrage mis en avant : nommé, ou long d'au moins NOTABLE_LENGTH_M. */
export function isNotable(item) {
    return item.name !== null || (item.length_m ?? 0) >= NOTABLE_LENGTH_M;
}

/**
 * Ouvrages visibles d'emblée : tous s'il y en a FOLD_AFTER ou moins, sinon les principaux (même ordre).
 * `folded` dit si la liste complète doit être proposée à part.
 */
export function visibleItems(items) {
    if (items.length <= FOLD_AFTER) return { items, folded: false };
    return { items: items.filter(isNotable), folded: true };
}

/** Nom affiché : celui de l'ouvrage, la majuscule initiale rétablie, ou « Tunnel sans nom connu ». */
export function structureName(item, t) {
    if (!item.name) return t(`structures.line.unnamed.${item.kind}`);
    return item.name.charAt(0).toLocaleUpperCase('fr') + item.name.slice(1);
}

/** PK de l'ouvrage : « PK 12+340 » ou « PK 12+340 à 12+905 » ; null sans PK. */
export function pkText(item, t) {
    if (!item.pk_start_label) return null;
    if (!item.pk_end_label || item.pk_end_label === item.pk_start_label)
        return t('structures.line.pk', { pk: item.pk_start_label });
    return t('structures.line.pk_range', { from: item.pk_start_label, to: item.pk_end_label });
}

/**
 * Dates de l'ouvrage (construction, mise en service, fermeture), chacune dans sa forme : « en 1884 »,
 * « en mars 1884 », « le 10 novembre 1888 ».
 */
export function dateParts(item, t, locale) {
    const out = [];
    for (const key of ['built', 'opened', 'closed']) {
        const d = item[key];
        if (!d) continue;
        const date = formatEventDate(d.date, d.precision, locale);
        out.push(
            t(d.precision === 'day' ? `structures.line.${key}_day` : `structures.line.${key}`, {
                date,
            }),
        );
    }
    return out;
}

/** Résumé « 3 tunnels, 2 viaducs et 1 grand pont » (liste jointe selon la langue). */
export function summaryList(summary, t, locale) {
    const parts = KINDS.filter((k) => summary.by_kind[k] > 0).map((k) =>
        t(`structures.line.count.${k}_${summary.by_kind[k] === 1 ? 'one' : 'other'}`, {
            count: summary.by_kind[k],
        }),
    );
    return new Intl.ListFormat(locale, { style: 'long', type: 'conjunction' }).format(parts);
}
