/**
 * Accessibilité et services d'une gare (StationAccessibility.svelte) : mise en forme pure, sans DOM.
 * Les données viennent de `GET /api/v1/stations/{station}/accessibility` (SNCF Gares & Connexions, ODbL).
 */

/** Jours dans l'ordre des horaires de l'assistance. */
export const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

/** Équipements recensés, dans l'ordre d'affichage du détail. */
export const AMENITIES = [
    'step_free_entrance',
    'boarding_assistance',
    'induction_loop',
    'tactile_strips',
    'audio_information',
    'information_screens',
    'wheelchair_loan',
    'staff',
    'toilets',
    'toilets_accessible',
];

/**
 * Résumé des ascenseurs : recensés (`count`), et leur état publié quand le jeu le donne.
 * @param {{ count?: number|null, status?: null | { ok: number, ko: number, unknown: number, items?: unknown[] } } | null | undefined} elevators
 * @returns {null | { count: number, ok: number, ko: number, unknown: number, known: boolean }}
 */
export function elevatorSummary(elevators) {
    const status = elevators?.status ?? null;
    const listed = status ? status.ok + status.ko + status.unknown : 0;
    const count = Math.max(elevators?.count ?? 0, listed);
    if (count === 0) return null;
    return {
        count,
        ok: status?.ok ?? 0,
        ko: status?.ko ?? 0,
        unknown: status?.unknown ?? 0,
        known: status !== null && listed > 0,
    };
}

/**
 * Les faits de la ligne compacte : seulement ce que les jeux établissent, jamais une absence présentée comme un « non »
 * (sauf l'accès des personnes à mobilité réduite : le jeu NeTEx dit « accessible en partie » ou « non déclarée accessible »).
 * @param {object|null|undefined} data bloc `accessibility` de l'API
 * @returns {Array<{ id: string, icon: string, key: string, params?: Record<string, number|string>, alert?: boolean }>}
 */
export function highlights(data) {
    if (!data) return [];
    const out = [];
    const access = data.access ?? {};
    if (access.mobility === 'partial') {
        out.push({ id: 'mobility', icon: 'bi-person-wheelchair', key: 'partial' });
    } else if (access.mobility === 'none') {
        out.push({ id: 'mobility', icon: 'bi-slash-circle', key: 'none', alert: true });
    }
    if (access.mobility === 'partial' && access.step_free === true) {
        out.push({ id: 'step_free', icon: 'bi-universal-access', key: 'step_free' });
    }
    if (data.assistance) {
        out.push({ id: 'assistance', icon: 'bi-people', key: 'assistance' });
    }
    const lifts = elevatorSummary(data.elevators);
    if (lifts) {
        out.push(
            lifts.known && lifts.ko > 0
                ? {
                      id: 'lifts',
                      icon: 'bi-arrow-down-up',
                      key: 'lifts_status',
                      params: { ok: lifts.ok, count: lifts.count },
                      alert: true,
                  }
                : lifts.known && lifts.ok === lifts.count
                  ? {
                        id: 'lifts',
                        icon: 'bi-arrow-down-up',
                        key: 'lifts_all_ok',
                        params: { count: lifts.count },
                    }
                  : {
                        id: 'lifts',
                        icon: 'bi-arrow-down-up',
                        key: 'lifts',
                        params: { count: lifts.count },
                    },
        );
    }
    const amenities = new Set(data.amenities ?? []);
    if (amenities.has('induction_loop'))
        out.push({ id: 'induction_loop', icon: 'bi-ear', key: 'induction_loop' });
    if (amenities.has('tactile_strips'))
        out.push({ id: 'tactile_strips', icon: 'bi-grip-horizontal', key: 'tactile_strips' });
    if (data.sales?.counter)
        out.push({ id: 'counter', icon: 'bi-ticket-perforated', key: 'counter' });
    if (amenities.has('toilets') || amenities.has('toilets_accessible')) {
        out.push({ id: 'toilets', icon: 'bi-badge-wc', key: 'toilets' });
    }
    if ((data.bike_parking?.places ?? 0) > 0) {
        out.push({
            id: 'bikes',
            icon: 'bi-bicycle',
            key: 'bikes',
            params: { count: data.bike_parking.places },
        });
    }
    return out;
}

/**
 * Horaires de l'assistance, regroupés par jours consécutifs identiques : « lundi à vendredi : 08:45 - 17:00 ».
 * @param {Record<string, string|null>|null|undefined} hours
 * @returns {Array<{ from: string, to: string, value: string|null }>}
 */
export function hoursGroups(hours) {
    const groups = [];
    for (const day of DAYS) {
        const value = hours?.[day] ?? null;
        const last = groups.at(-1);
        if (last && last.value === value) last.to = day;
        else groups.push({ from: day, to: day, value });
    }
    return groups;
}

/** Date et heure de la lecture, dans la langue de la page : « 23 novembre 2026 à 10:00 ». */
export function readingTime(iso, lang) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(lang, { dateStyle: 'long', timeStyle: 'short' }).format(date);
}

/** Date seule d'un jeu (`AAAA-MM-JJ`) : « 21 mai 2026 ». */
export function datasetDate(day, lang) {
    if (!day) return '';
    const date = new Date(`${day}T12:00:00`);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(lang, { dateStyle: 'long' }).format(date);
}

/** Nom d'un jour de la semaine dans la langue de la page (`monday` donne « lundi »). */
export function dayName(day, lang) {
    const index = DAYS.indexOf(day);
    if (index < 0) return day;
    // Le 1er janvier 2024 est un lundi.
    return new Intl.DateTimeFormat(lang, { weekday: 'long' }).format(new Date(2024, 0, 1 + index));
}

/** Ordre des états dans la liste des équipements : les pannes d'abord, puis les états inconnus, puis ceux en service. */
const STATE_ORDER = { ko: 0, unknown: 1, ok: 2 };

/**
 * Équipements d'élévatique, les pannes en tête (à libellé égal, l'ordre de SNCF est gardé).
 * @param {Array<{ label: string, state: string, kind?: string, location?: string|null }>|null|undefined} items
 */
export function sortedItems(items) {
    return [...(items ?? [])].sort(
        (a, b) => (STATE_ORDER[a.state] ?? 1) - (STATE_ORDER[b.state] ?? 1),
    );
}

/** Texte de SNCF repris comme phrase : majuscule initiale, point final. */
export function sentence(text) {
    const t = String(text ?? '').trim();
    if (t === '') return '';
    const first = t.charAt(0).toUpperCase() + t.slice(1);
    return /[.!?]$/.test(first) ? first : `${first}.`;
}
