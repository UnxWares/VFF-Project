// Quiz du rail (/quiz) : logique de la page, sans rendu (testée par tests/js/quiz.test.js).
//
// Le serveur envoie les questions sans les réponses ; la page demande la réponse à /quiz/answer après
// le choix du joueur. Seul le meilleur score est gardé, dans le navigateur (localStorage), jamais sur
// le serveur.

export const GAMES = ['lines', 'materials', 'facts'];
export const LEVELS = ['easy', 'hard'];

/** Marge autour du tracé dans le cadre SVG (unités du cadre de 1 000). */
export const TRACE_MARGIN = 40;

/** Longueurs rondes possibles pour la barre d'échelle (km). */
const SCALE_STEPS = [0.5, 1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500];

/**
 * Chemin SVG d'un tracé : une sous-ligne « M … L … » par partie.
 * @param {Array<Array<[number, number]>>} paths
 */
export function tracePath(paths) {
    return (paths ?? [])
        .filter((p) => p.length >= 2)
        .map((p) => p.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x} ${y}`).join(' '))
        .join(' ');
}

/**
 * Cadre SVG du tracé, marge comprise ; la hauteur garde la place de la barre d'échelle.
 * @param {{ width: number, height: number }} trace
 */
export function traceViewBox(trace) {
    const m = TRACE_MARGIN;
    return `${-m} ${-m} ${trace.width + 2 * m} ${trace.height + 3 * m}`;
}

/**
 * Barre d'échelle : la plus grande longueur ronde qui tient dans un quart de la largeur du tracé
 * (au moins la plus petite). `units` : longueur en unités du cadre.
 * @param {number} mPerUnit mètres par unité du cadre
 * @param {number} width largeur du tracé (unités)
 * @returns {{ km: number, units: number }|null}
 */
export function scaleBar(mPerUnit, width) {
    if (!(mPerUnit > 0) || !(width > 0)) return null;
    const maxKm = (width * mPerUnit) / 4000;
    const km = SCALE_STEPS.filter((s) => s <= maxKm).at(-1) ?? SCALE_STEPS[0];
    return { km, units: Math.round((km * 1000) / mPerUnit) };
}

/**
 * Adresse d'une série : graine et version de la série (empreinte de ses questions, calculée par le
 * serveur). Sans graine, le serveur en tire une et redirige vers l'adresse complète.
 */
export function quizHref(game, level, seed = null, version = null) {
    const p = new URLSearchParams({ game, level });
    if (seed) p.set('seed', seed);
    if (seed && version) p.set('v', version);
    return `/quiz?${p.toString()}`;
}

/**
 * Adresse de vérification d'une réponse (question `index`, proposition `choice`). La version de la série
 * accompagne la question : si la base a changé entre-temps, le serveur refuse de juger (409).
 */
export function answerHref({ game, level, seed, version }, index, choice) {
    const p = new URLSearchParams({ game, level, seed, v: version, q: String(index), choice });
    return `/quiz/answer?${p.toString()}`;
}

/** Statut HTTP d'une réponse refusée parce que la série a changé depuis l'affichage de la page. */
export const STALE_STATUS = 409;

/** Nombre au format de la langue (« 152,8 »), une décimale au plus. */
function decimal(n, lang) {
    return Number(n).toLocaleString(lang, { maximumFractionDigits: 1 });
}

/**
 * @typedef {{ id: string, label: string, value: string, lang?: string }} HintRow
 */

/**
 * Indices du jeu des lignes, en texte : rangées `{ id, label, value, lang? }`, sans celles que la
 * base ne renseigne pas (`lang` : langue de la valeur quand elle n'est pas celle de la page).
 * @returns {HintRow[]}
 */
export function lineHints(h, t, lang) {
    if (!h) return [];
    const rows = [
        h.length_km > 0 && {
            id: 'length',
            value: t('quiz.hints.km', { value: decimal(h.length_km, lang) }),
        },
        h.era_year && {
            id: 'era',
            value: h.era_is_current
                ? t('quiz.hints.era_current')
                : t('quiz.hints.era_past', { year: h.era_year }),
        },
        h.opened && { id: 'opened', value: String(h.opened) },
        h.in_service != null && {
            id: 'today',
            value: t(h.in_service ? 'quiz.hints.in_service' : 'quiz.hints.out_of_service'),
        },
        h.closed && { id: 'closed', value: String(h.closed) },
        h.departments?.length && {
            id: 'departments',
            value: h.departments.join(', '),
            lang: 'fr',
        },
    ];
    return rows.filter(Boolean).map((r) => ({ ...r, label: t(`quiz.hints.${r.id}`) }));
}

/** Période de service d'une série (« 1962-1998 », « depuis 1996 »), null si inconnue. */
function servicePeriod(from, to, t) {
    if (from && to) return from === to ? String(from) : `${from}-${to}`;
    if (from) return t('materials.service.from', { year: from });
    if (to) return t('materials.service.until', { year: to });
    return null;
}

/**
 * Indices du jeu du matériel, en texte (même forme que lineHints).
 * @returns {HintRow[]}
 */
export function materialHints(h, t, lang) {
    if (!h) return [];
    const service = servicePeriod(h.from, h.to, t);
    const rows = [
        h.type && { id: 'type', value: t(`materials.type.${h.type}`) },
        service && { id: 'service', value: service },
        h.builders?.length && { id: 'builders', value: h.builders.join(', ') },
        h.max_speed_kmh && {
            id: 'speed',
            value: t('materials.unit.kmh', { value: Number(h.max_speed_kmh).toLocaleString(lang) }),
        },
        h.units_built && { id: 'units', value: Number(h.units_built).toLocaleString(lang) },
    ];
    return rows.filter(Boolean).map((r) => ({ ...r, label: t(`quiz.hints.${r.id}`) }));
}

/**
 * Indices du jeu « Culture du rail » : le serveur les envoie déjà rédigés dans la langue de la page
 * (`{ label, value }`) ; on leur donne seulement un identifiant de liste.
 * @returns {HintRow[]}
 */
export function factHints(hints) {
    return (hints ?? []).map((h) => ({ id: h.label, label: h.label, value: h.value }));
}

/** Clé de traduction du degré de difficulté d'une question (1 facile, 2 moyenne, 3 difficile). */
export function difficultyKey(level) {
    return ['easy', 'medium', 'hard'][Math.min(Math.max(Number(level) || 2, 1), 3) - 1];
}

/** Score : nombre de réponses justes parmi les résultats reçus. */
export function scoreOf(results) {
    return Object.values(results ?? {}).filter((r) => r?.correct).length;
}

/** Clé du meilleur score dans le stockage du navigateur, par jeu et par niveau. */
export function bestKey(game, level) {
    return `vff.quiz.best.${game}.${level}`;
}

/**
 * Meilleur score gardé dans ce navigateur, ou null (stockage absent, refusé ou valeur illisible).
 * @param {Storage|null|undefined} storage
 */
export function readBest(storage, game, level) {
    try {
        const n = Number.parseInt(storage?.getItem(bestKey(game, level)) ?? '', 10);
        return Number.isFinite(n) && n >= 0 ? n : null;
    } catch {
        return null;
    }
}

/**
 * Garde le score s'il dépasse le meilleur ; rend le meilleur score après mise à jour (le score lui-même
 * quand le stockage est refusé : navigation privée, quota, cookies bloqués).
 * @param {Storage|null|undefined} storage
 */
export function saveBest(storage, game, level, score) {
    const best = readBest(storage, game, level);
    if (best !== null && best >= score) return best;
    try {
        storage?.setItem(bestKey(game, level), String(score));
    } catch {
        // Stockage refusé : le score n'est simplement pas gardé.
    }
    return score;
}
