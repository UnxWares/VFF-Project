// Utilitaires géométriques de l'éditeur de tracé (sans dépendance).
// Coordonnées GeoJSON [lng, lat] en WGS84.

export const PORTION_COLORS = [
    '#ff6500',
    '#2563eb',
    '#16a34a',
    '#9333ea',
    '#dc2626',
    '#0891b2',
    '#ca8a04',
    '#db2777',
];

export function portionColor(index) {
    return PORTION_COLORS[index % PORTION_COLORS.length];
}

const R = 6371008.8;
const rad = (d) => (d * Math.PI) / 180;

/** Distance (m) entre deux points [lng, lat] - formule de haversine. */
export function distanceM(a, b) {
    const dLat = rad(b[1] - a[1]);
    const dLng = rad(b[0] - a[0]);
    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(rad(a[1])) * Math.cos(rad(b[1])) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}

export function lengthM(coords) {
    let total = 0;
    for (let i = 1; i < coords.length; i++) total += distanceM(coords[i - 1], coords[i]);
    return total;
}

/** Longueur lisible ; avec `locale`, le séparateur décimal suit la langue de la page. */
export function formatKm(m, locale) {
    if (m < 1000) return `${Math.round(m)} m`;
    const digits = m < 10000 ? 2 : 1;
    const km = locale
        ? (m / 1000).toLocaleString(locale, {
              minimumFractionDigits: digits,
              maximumFractionDigits: digits,
          })
        : (m / 1000).toFixed(digits);
    return `${km} km`;
}

/** Projection d'un point écran p sur le segment écran [a, b] → { t, x, y, d }. */
export function projectOnSegment(p, a, b) {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const len2 = dx * dx + dy * dy;
    let t = len2 === 0 ? 0 : ((p.x - a.x) * dx + (p.y - a.y) * dy) / len2;
    t = Math.max(0, Math.min(1, t));
    const x = a.x + t * dx;
    const y = a.y + t * dy;
    return { t, x, y, d: Math.hypot(p.x - x, p.y - y) };
}

/**
 * Point le plus proche (en pixels) d'une polyligne, pour l'accrochage.
 * `project` : fonction [lng, lat] → {x, y} (map.project).
 * Retour : { type: 'vertex' | 'edge', index, coord, d } ou null.
 */
export function nearestOnLine(coords, p, project, radius, unproject) {
    let bestV = null;
    let bestE = null;
    let prev = null;
    for (let i = 0; i < coords.length; i++) {
        const s = project(coords[i]);
        const dv = Math.hypot(p.x - s.x, p.y - s.y);
        if (dv <= radius && (!bestV || dv < bestV.d))
            bestV = { type: 'vertex', index: i, coord: coords[i], d: dv };
        if (prev) {
            const pr = projectOnSegment(p, prev, s);
            if (pr.d <= radius && (!bestE || pr.d < bestE.d)) {
                bestE = {
                    type: 'edge',
                    index: i - 1,
                    t: pr.t,
                    screen: { x: pr.x, y: pr.y },
                    d: pr.d,
                };
            }
        }
        prev = s;
    }
    // Un sommet proche l'emporte sur une arête (accrochage aux gares, aiguilles…).
    if (bestV && (!bestE || bestV.d <= bestE.d + radius * 0.5)) return bestV;
    if (bestE) {
        const a = coords[bestE.index];
        const b = coords[bestE.index + 1];
        // Interpolation en coordonnées géographiques (précision ≫ pixel à ces échelles).
        const coord = unproject
            ? unproject(bestE.screen)
            : [a[0] + (b[0] - a[0]) * bestE.t, a[1] + (b[1] - a[1]) * bestE.t];
        return { ...bestE, coord };
    }
    return null;
}

/** Emprise [[minLng, minLat], [maxLng, maxLat]] d'une liste de polylignes. */
export function boundsOf(lines) {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    for (const coords of lines) {
        for (const [x, y] of coords) {
            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (x > maxX) maxX = x;
            if (y > maxY) maxY = y;
        }
    }
    return Number.isFinite(minX)
        ? [
              [minX, minY],
              [maxX, maxY],
          ]
        : null;
}

/** Supprime les sommets consécutifs identiques. */
export function dedupe(coords) {
    const out = [];
    for (const c of coords) {
        const last = out[out.length - 1];
        if (!last || last[0] !== c[0] || last[1] !== c[1]) out.push(c);
    }
    return out;
}

export function samePoint(a, b) {
    return a && b && Math.abs(a[0] - b[0]) < 1e-9 && Math.abs(a[1] - b[1]) < 1e-9;
}

export function round7(c) {
    return [Math.round(c[0] * 1e7) / 1e7, Math.round(c[1] * 1e7) / 1e7];
}
