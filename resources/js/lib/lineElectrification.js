// Histoire de l'électrification d'une ligne (App\Services\Traction\LineElectrificationHistory) : tension,
// voies du diagramme et libellés de la section de la fiche (Components/Lines/LineElectrification.svelte).
import { pkExtent } from '$lib/lineSpeeds.js';
import { NBSP } from '$lib/typo.js';

export { pkLabel, pkTicks } from '$lib/lineSpeeds.js';

/** Nombre maximal de portions datées tracées sous la bande (les autres restent dans la liste). */
export const MAX_LANES = 12;

/**
 * Tension lisible : « 1 500 V », « 25 kV » (les valeurs d'au moins 10 000 V sont en kV).
 * @param {number|null} v volts
 * @param {(n: number) => string} fmt formateur de nombre selon la langue
 * @returns {string|null}
 */
export function voltageText(v, fmt) {
    if (v === null || v === undefined) return null;
    return v >= 10000 ? `${fmt(v / 1000)}${NBSP}kV` : `${fmt(v)}${NBSP}V`;
}

/**
 * Clé de traduction du courant d'une tension (« continu », « alternatif », « troisième rail »).
 * @param {string|null|undefined} system
 * @returns {string|null}
 */
export function systemKey(system) {
    return ['dc', 'ac', 'third_rail'].includes(system ?? '')
        ? `electrification.system.${system}`
        : null;
}

/**
 * Clé de traduction d'un fait daté, selon sa nature et la présence d'une tension.
 * @param {{ kind: string, voltage_v: number|null }} e
 * @returns {string}
 */
export function eventKey(e) {
    const kind = ['electrification', 'conversion', 'deelectrification'].includes(e.kind)
        ? e.kind
        : 'electrification';
    return `electrification.kind.${kind}${e.voltage_v !== null && kind !== 'deelectrification' ? '_volt' : ''}`;
}

/**
 * Portions datées placées sur l'axe des PK, une voie chacune, de la plus ancienne à la plus récente.
 * @param {Array<{ kind: string, date: string, scope: string,
 *   section: { pk_start_m: number|null, pk_end_m: number|null }|null }>} history
 * @param {number} [max]
 * @returns {Array<{ index: number, pk_start_m: number, pk_end_m: number }>}
 */
export function lanes(history, max = MAX_LANES) {
    const out = [];
    history.forEach((e, index) => {
        const s = e.section;
        if (e.kind === 'deelectrification' || e.scope !== 'section' || !s) return;
        if (s.pk_start_m === null || s.pk_end_m === null || s.pk_end_m <= s.pk_start_m) return;
        out.push({ index, pk_start_m: s.pk_start_m, pk_end_m: s.pk_end_m });
    });
    return out.slice(0, max);
}

/**
 * Étendue des PK du diagramme : tranches SNCF et portions datées.
 * @param {Array<{ pk_start_m: number, pk_end_m: number }>} ranges
 * @param {Array<{ pk_start_m: number, pk_end_m: number }>} laneList
 * @returns {[number, number]}
 */
export function extent(ranges, laneList) {
    return pkExtent([...ranges, ...laneList]);
}

/**
 * Années de la première et de la dernière mise sous tension datée (conversions comprises), ou null.
 * @param {Array<{ kind: string, date: string }>} history
 * @returns {{ first: string, last: string }|null}
 */
export function span(history) {
    const dates = history
        .filter((e) => e.kind !== 'deelectrification')
        .map((e) => e.date)
        .sort();
    return dates.length ? { first: dates[0], last: dates[dates.length - 1] } : null;
}

/**
 * Motif de remplissage d'un courant : continu plein, alternatif hachuré, troisième rail pointillé
 * (le diagramme ne repose pas sur la seule couleur).
 * @param {string} system
 * @returns {'solid'|'hatch'|'dots'}
 */
export function fillKind(system) {
    return system === 'ac' ? 'hatch' : system === 'third_rail' ? 'dots' : 'solid';
}
