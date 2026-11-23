// Vitesses maximales d'une ligne (App\Services\Speed\LineSpeeds) : échelles et libellés du
// graphique en escalier de la fiche ligne (Components/Lines/LineSpeeds.svelte).
import { niceStep } from '$lib/lineProfile.js';

/**
 * PK en notation ferroviaire « km+mmm » (12 345 m : « 12+345 »).
 * @param {number|null|undefined} m
 * @returns {string}
 */
export function pkLabel(m) {
    if (m === null || m === undefined) return '-';
    const a = Math.abs(m);
    return `${m < 0 ? '-' : ''}${Math.floor(a / 1000)}+${String(a % 1000).padStart(3, '0')}`;
}

/**
 * PK le plus petit et le plus grand des tranches.
 * @param {Array<{ pk_start_m: number, pk_end_m: number }>} ranges
 * @returns {[number, number]}
 */
export function pkExtent(ranges) {
    if (ranges.length === 0) return [0, 1];
    const lo = Math.min(...ranges.map((r) => r.pk_start_m));
    const hi = Math.max(...ranges.map((r) => r.pk_end_m));
    return [lo, hi > lo ? hi : lo + 1];
}

/**
 * Graduations des vitesses, de 0 à un pas rond au-dessus de la plus haute (au moins 40 km/h).
 * @param {number} max
 * @param {number} [count]
 * @returns {number[]}
 */
export function speedTicks(max, count = 4) {
    const top = Math.max(40, max);
    const step = Math.max(10, niceStep(top, count));
    const out = [];
    for (let v = 0; v < top + step; v += step) out.push(v);
    return out;
}

/**
 * Graduations des PK (en km) entre deux PK en mètres, pas rond.
 * @param {number} fromM
 * @param {number} toM
 * @param {number} count
 * @returns {number[]}
 */
export function pkTicks(fromM, toM, count) {
    const step = niceStep((toM - fromM) / 1000, Math.max(2, count));
    const out = [];
    for (let k = Math.ceil(fromM / 1000 / step) * step; k * 1000 <= toM; k += step) {
        out.push(Math.round(k * 1e6) / 1e6 || 0);
    }
    return out;
}

/**
 * Trous entre deux tranches (PK sans vitesse publiée), plus longs que `minM`.
 * @param {Array<{ pk_start_m: number, pk_end_m: number }>} ranges triées par PK
 * @param {number} [minM]
 * @returns {Array<{ from_m: number, to_m: number }>}
 */
export function speedGaps(ranges, minM = 200) {
    const gaps = [];
    let end = null;
    for (const r of ranges) {
        if (end !== null && r.pk_start_m - end > minM)
            gaps.push({ from_m: end, to_m: r.pk_start_m });
        end = end === null ? r.pk_end_m : Math.max(end, r.pk_end_m);
    }
    return gaps;
}

/**
 * Clé de traduction du libellé d'un changement daté (relevée, abaissée, à l'ouverture, autorisée).
 * @param {{ kind: string, previous_speed_kmh: number|null }} e
 * @returns {string}
 */
export function eventKey(e) {
    const kind = ['raise', 'lower', 'opening', 'state'].includes(e.kind) ? e.kind : 'state';
    return `speeds.kind.${kind}${e.previous_speed_kmh !== null && kind !== 'opening' && kind !== 'state' ? '_from' : ''}`;
}

/**
 * Tracé en escalier des vitesses : un palier par tranche, relié à la suivante quand elles se touchent
 * (moins de `joinM` d'écart), interrompu sinon.
 * @param {Array<{ pk_start_m: number, pk_end_m: number, max_speed_kmh: number }>} ranges triées par PK
 * @param {(pk: number) => number} x
 * @param {(kmh: number) => number} y
 * @param {number} [joinM]
 * @returns {string}
 */
export function stepPath(ranges, x, y, joinM = 200) {
    let d = '';
    let prev = null;
    for (const r of ranges) {
        const yy = y(r.max_speed_kmh).toFixed(1);
        const x1 = x(r.pk_start_m).toFixed(1);
        const x2 = x(r.pk_end_m).toFixed(1);
        d +=
            prev !== null && r.pk_start_m - prev.pk_end_m <= joinM
                ? `L${x1},${yy}`
                : `M${x1},${yy}`;
        d += `L${x2},${yy}`;
        prev = r;
    }
    return d;
}

/**
 * Indice de la tranche sous un PK (la plus proche s'il tombe dans un trou), -1 sans tranche.
 * @param {Array<{ pk_start_m: number, pk_end_m: number }>} ranges
 * @param {number} pk
 * @returns {number}
 */
export function rangeAt(ranges, pk) {
    let best = -1;
    let bestDist = Infinity;
    ranges.forEach((r, i) => {
        const dist = pk < r.pk_start_m ? r.pk_start_m - pk : pk > r.pk_end_m ? pk - r.pk_end_m : 0;
        if (dist < bestDist) {
            best = i;
            bestDist = dist;
        }
    });
    return best;
}
