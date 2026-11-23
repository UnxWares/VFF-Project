/**
 * Courbe de fréquentation d'une gare (StationAttendance.svelte) : géométrie pure, sans DOM.
 *
 * Les années de la série 2015-2024 et celles de 2025 et après viennent de méthodes de calcul différentes
 * (SNCF Gares & Connexions : « non directement comparables ») : la courbe ne les relie jamais. Chaque série
 * est un tracé à part, et la rupture est marquée entre les deux.
 */

export const WIDTH = 600;
export const HEIGHT = 150;
const PAD_X = 12;
const PAD_TOP = 12;
const PAD_BOTTOM = 12;

/**
 * Points de la courbe, tracés par série, et position de la rupture.
 * @param {Array<{ year: number, passengers: number|null, series: string }>} years années triées, voyageurs nuls écartés
 * @returns {{ segments: Array<{ series: string, points: Array<{ x: number, y: number, year: number, value: number }> }>, breakX: number|null, last: {x: number, y: number}|null, max: number }}
 */
export function chartGeometry(years) {
    const rows = years.filter((y) => y.passengers != null);
    if (rows.length === 0) return { segments: [], breakX: null, last: null, max: 0 };
    const first = rows[0].year;
    const lastYear = rows[rows.length - 1].year;
    const span = Math.max(1, lastYear - first);
    const max = Math.max(...rows.map((r) => r.passengers), 1);
    const x = (year) =>
        rows.length === 1 ? WIDTH / 2 : PAD_X + ((year - first) / span) * (WIDTH - 2 * PAD_X);
    const y = (value) => PAD_TOP + (1 - value / max) * (HEIGHT - PAD_TOP - PAD_BOTTOM);

    /** @type {Array<{ series: string, points: Array<{ x: number, y: number, year: number, value: number }> }>} */
    const segments = [];
    for (const r of rows) {
        let seg = segments[segments.length - 1];
        if (!seg || seg.series !== r.series) {
            seg = { series: r.series, points: [] };
            segments.push(seg);
        }
        seg.points.push({
            x: round(x(r.year)),
            y: round(y(r.passengers)),
            year: r.year,
            value: r.passengers,
        });
    }
    let breakX = null;
    if (segments.length > 1) {
        const before = segments[0].points[segments[0].points.length - 1];
        const after = segments[1].points[0];
        breakX = round((before.x + after.x) / 2);
    }
    const lastPoint = segments[segments.length - 1].points.at(-1);

    return { segments, breakX, last: { x: lastPoint.x, y: lastPoint.y }, max };
}

/** Attribut `points` d'une polyligne SVG. */
export function polyline(points) {
    return points.map((p) => `${p.x},${p.y}`).join(' ');
}

function round(n) {
    return Math.round(n * 10) / 10;
}
