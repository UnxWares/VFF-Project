// Livret de parcours (page /routebook, réponse de GET /api/v1/routebook) : calculs purs de la page.
//
// - adresse de la page pour deux gares ;
// - parcours vu comme une seule « ligne » au format du contrat de track-layout, pour le composant du schéma des
//   voies : l'axe est le kilomètre parcouru (pk_m = mètres parcourus depuis le départ), le PK de la ligne de chaque
//   objet reste lisible (line_pk_m, line_code), les rangées sont celles du sens de marche (route_lane) et les sens
//   « up » / « down » valent pour le sens de marche ;
// - portions sans schéma détaillé, regroupées par ligne, pour le dire honnêtement.

/** Adresse de la page pour deux gares (slug ou texte libre) ; sans gare, la page vide. */
export function routebookHref(from, to) {
    const params = new URLSearchParams();
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    const qs = params.toString();
    return qs ? `/routebook?${qs}` : '/routebook';
}

const km = (v) => Math.round(Number(v) * 1000);

/** Sens de marche d'un objet « up » / « down » de sa ligne, pour une portion parcourue dans `direction`. */
export function travelSense(sense, direction) {
    if (sense !== 'up' && sense !== 'down') return null;
    if (direction !== 'down') return sense;
    return sense === 'up' ? 'down' : 'up';
}

/**
 * Le parcours au format du contrat de `track-layout`, sur l'axe des mètres parcourus.
 *
 * @param {any} book réponse de GET /api/v1/routebook
 */
export function asLayout(book) {
    if (!book) return null;
    const direction = new Map((book.legs ?? []).map((l) => [l.index, l.direction]));
    const point = (o) => ({
        ...o,
        pk_m: km(o.route_km),
        line_pk_m: o.pk_m,
    });
    // Étendue : début et fin dans l'ordre de marche ; à PK décroissants, le PK de ligne du début est le plus grand.
    const range = (o) => {
        const down = direction.get(o.leg) === 'down';
        return {
            ...o,
            pk_from_m: km(o.route_from_km),
            pk_to_m: km(o.route_to_km),
            line_pk_from_m: (down ? o.pk_to_m : o.pk_from_m) ?? null,
            line_pk_to_m: (down ? o.pk_from_m : o.pk_to_m) ?? null,
        };
    };
    return {
        line: { code: null, slug: null, name: `${book.from?.name} - ${book.to?.name}` },
        pk: { start_m: 0, end_m: book.distance_m },
        sources: book.sources ?? [],
        operational_points: (book.operational_points ?? []).map(point),
        tracks: (book.tracks ?? []).map((t) => ({ ...range(t), lane: t.route_lane ?? t.lane })),
        switches: (book.switches ?? []).map((s) => ({
            ...point(s),
            facing: travelSense(s.facing, direction.get(s.leg)),
        })),
        signals: (book.signals ?? []).map((s) => ({
            ...point(s),
            direction: travelSense(s.direction, direction.get(s.leg)),
        })),
        crossings: (book.crossings ?? []).map(point),
        strip: {
            speeds: (book.strip?.speeds ?? []).map(range),
            gradients: (book.strip?.gradients ?? []).map(range),
        },
        coverage: {
            ...book.coverage,
            missing: (book.coverage?.missing ?? []).map(range),
        },
    };
}

/**
 * Longueur sans schéma détaillé (m) et lignes concernées, d'après `coverage.missing` (portions `all` seulement :
 * une portion qui a des voies mais pas de signaux n'est pas « sans schéma »).
 *
 * @param {any} book
 * @returns {{ metres: number, lines: string[], complete: boolean }}
 */
export function missingSummary(book) {
    const all = (book?.coverage?.missing ?? []).filter((m) => m.what === 'all');
    const metres = all.reduce((sum, m) => sum + km(m.route_to_km) - km(m.route_from_km), 0);
    const lines = [...new Set(all.map((m) => m.line_code ?? m.line_slug))];
    return { metres, lines, complete: metres >= (book?.distance_m ?? 0) && metres > 0 };
}

/** Bout d'une portion : nom de la gare, ou null pour un changement de ligne en pleine voie. */
export function endName(end) {
    return end?.kind === 'station' ? end.name : null;
}

/**
 * Gares des deux bouts d'une ligne pour son livret de parcours : la première et la dernière gare (dans l'ordre de la
 * fiche, celui des PK) ouvertes aux voyageurs aujourd'hui ; null s'il y en a moins de deux.
 *
 * @param {{ slug: string, name: string, status_today?: string|null }[]} stations
 */
export function routebookEndsOf(stations) {
    const open = (stations ?? []).filter((s) => s.status_today === 'active');
    if (open.length < 2) return null;
    return { from: open[0], to: open.at(-1) };
}
