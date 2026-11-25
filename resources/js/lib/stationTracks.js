// Voies d'une gare (GET /api/v1/stations/{station}/track-layout) : lecture, regroupement des bords de quai en quais,
// formes des quais dans le dessin du schéma (TrackDiagram) et résumé de la section de la fiche.

/**
 * Adresse de l'API des voies d'une gare.
 *
 * @param {string} slug
 * @param {{ kinds?: string[], line?: string }} [params]
 */
export function stationTracksUrl(slug, params = {}) {
    const q = new URLSearchParams();
    if (params.line) q.set('line', params.line);
    if (params.kinds?.length) q.set('kinds', params.kinds.join(','));
    const s = q.toString();
    return `/api/v1/stations/${encodeURIComponent(slug)}/track-layout${s ? `?${s}` : ''}`;
}

/**
 * Lit les voies d'une gare.
 *
 * @param {string} slug
 * @param {typeof fetch} [fetchImpl]
 * @param {{ kinds?: string[], line?: string }} [params]
 */
export async function fetchStationTracks(slug, fetchImpl = fetch, params = {}) {
    const res = await fetchImpl(stationTracksUrl(slug, params), {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

/** La réponse a-t-elle quelque chose à montrer (une ligne avec des voies, ou des quais) ? */
export function hasStationTracks(data) {
    return Boolean(data && ((data.lines?.length ?? 0) > 0 || (data.platforms?.length ?? 0) > 0));
}

/** Clé de tri naturelle : « Quai 2 » avant « Quai 10 ». */
const natural = (a, b) =>
    String(a ?? '').localeCompare(String(b ?? ''), 'fr', { numeric: true, sensitivity: 'base' });

/**
 * Quais d'une gare : les bords de quai (un par voie) regroupés par ligne et par nom de quai. Un quai central entre
 * deux voies a deux bords et une seule rangée : ses voies, sa plus grande longueur, ses hauteurs (une seule le plus
 * souvent), ses sources, ses PK extrêmes.
 *
 * @param {Array<any>} platforms bords de quai de la réponse
 * @returns {Array<{ key: string, ref: string, line_code: string|null, tracks: string[], length_m: number|null,
 *   heights_mm: number[], sources: string[], pk_from_m: number|null, pk_to_m: number|null, approx: boolean,
 *   placed: boolean }>} `placed` : un bord au moins a une voie dessinée et des PK (le quai est dans le schéma)
 */
export function platformGroups(platforms = []) {
    /** @type {Map<string, any>} */
    const groups = new Map();
    for (const p of platforms) {
        const key = `${p.line_code ?? ''}|${String(p.ref).replace(/\s+/g, '').toLowerCase()}`;
        const g = groups.get(key) ?? {
            key,
            ref: String(p.ref).replace(/\s*\/\s*/g, '/'),
            line_code: p.line_code ?? null,
            tracks: [],
            length_m: null,
            heights_mm: [],
            sources: [],
            pk_from_m: null,
            pk_to_m: null,
            approx: false,
            placed: false,
        };
        if (p.track_ref && !g.tracks.includes(p.track_ref)) g.tracks.push(p.track_ref);
        if (p.length_m != null) g.length_m = Math.max(g.length_m ?? 0, p.length_m);
        if (p.height_mm != null && !g.heights_mm.includes(p.height_mm))
            g.heights_mm.push(p.height_mm);
        // Sources des données : celle du bord et celle de ses PK (une position approchée par VFF est dite par `approx`).
        for (const s of [p.source, p.pk_source]) {
            if (s && s !== 'vff' && !g.sources.includes(s)) g.sources.push(s);
        }
        if (p.pk_from_m != null) {
            g.pk_from_m = g.pk_from_m === null ? p.pk_from_m : Math.min(g.pk_from_m, p.pk_from_m);
            g.pk_to_m = g.pk_to_m === null ? p.pk_to_m : Math.max(g.pk_to_m, p.pk_to_m);
        }
        if (p.precision === 'approximate') g.approx = true;
        if (p.track != null && p.pk_from_m != null) g.placed = true;
        groups.set(key, g);
    }
    for (const g of groups.values()) {
        g.tracks.sort(natural);
        g.heights_mm.sort((a, b) => a - b);
    }
    return [...groups.values()].sort(
        (a, b) => natural(a.line_code, b.line_code) || natural(a.ref, b.ref),
    );
}

/**
 * Formes des quais dans le dessin : un quai dont les deux bords longent deux voies de rangées voisines est dessiné
 * ENTRE elles (quai central) ; un bord seul, le long de sa voie, du côté opposé à la voie principale la plus proche
 * (au-dessus des rangées hautes, au-dessous des autres). Seuls les bords placés (PK connus) d'une voie dessinée
 * comptent ; `rowOf` rend la rangée d'une voie dessinée, ou null (voie absente ou repliée).
 *
 * @param {Array<any>} edges bords de quai d'une ligne
 * @param {(trackId: string) => number|null} rowOf
 * @param {number} pivot rangée (éventuellement fractionnaire) qui sépare le haut du bas : entre les voies 1 et 2
 * @returns {Array<{ key: string, ref: string, from_m: number, to_m: number, row: number, side: 'between'|'above'|'below', approx: boolean }>}
 */
export function platformShapes(edges, rowOf, pivot = 0.5) {
    /** @type {Map<string, Array<any>>} */
    const byRef = new Map();
    for (const e of edges) {
        if (e.track == null || e.pk_from_m == null || e.pk_to_m == null) continue;
        const row = rowOf(e.track);
        if (row === null || row === undefined) continue;
        const key = `${e.line_code ?? ''}|${String(e.ref).replace(/\s+/g, '').toLowerCase()}`;
        const list = byRef.get(key) ?? [];
        list.push({ ...e, row });
        byRef.set(key, list);
    }
    /** @type {Array<any>} */
    const out = [];
    for (const [key, list] of byRef) {
        list.sort((a, b) => a.row - b.row);
        const [a, b] = list;
        const overlap =
            list.length === 2 &&
            Math.min(a.pk_to_m, b.pk_to_m) > Math.max(a.pk_from_m, b.pk_from_m);
        if (overlap && b.row - a.row === 1) {
            out.push({
                key,
                ref: a.ref,
                from_m: Math.min(a.pk_from_m, b.pk_from_m),
                to_m: Math.max(a.pk_to_m, b.pk_to_m),
                row: a.row + 0.5,
                side: 'between',
                approx: a.precision !== 'exact' || b.precision !== 'exact',
            });
            continue;
        }
        for (const e of list) {
            out.push({
                key: `${key}|${e.id}`,
                ref: e.ref,
                from_m: e.pk_from_m,
                to_m: e.pk_to_m,
                row: e.row,
                side: e.row < pivot ? 'above' : 'below',
                approx: e.precision !== 'exact',
            });
        }
    }
    return out.sort((x, y) => x.from_m - y.from_m || x.row - y.row);
}

/**
 * Bords de quai d'une ligne de la réponse.
 *
 * @param {any} data réponse de l'API
 * @param {string|null} lineCode
 */
export function linePlatforms(data, lineCode) {
    return (data?.platforms ?? []).filter((p) => p.line_code === lineCode);
}

/** Hauteur de quai en centimètres, arrondie au centimètre (« 55 cm »). */
export function heightCm(mm) {
    return Math.round(mm / 10);
}

/**
 * Adresse du schéma de toute une ligne, ouvert sur le PK de la gare.
 *
 * @param {string} lineSlug
 * @param {number|null} pk
 */
export function lineTracksHref(lineSlug, pk) {
    const base = `/lines/${encodeURIComponent(lineSlug)}/voies`;
    return pk === null || pk === undefined ? base : `${base}?pk=${Math.round(pk)}`;
}
