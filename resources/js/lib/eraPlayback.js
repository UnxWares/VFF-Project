/**
 * Lecture des époques sur /maps (EraPlayer.svelte) : la carte passe d'une décennie à la suivante, de la
 * première ère reconstituée à aujourd'hui, sans se recharger (les tracés changent en fondu, RailMap).
 *
 * - Une étape attend que la carte ait dessiné l'ère (`shown`), puis reste affichée le temps de la vitesse
 *   choisie : une connexion lente allonge l'étape au lieu de sauter des décennies. Au-delà de MAX_WAIT_MS
 *   sans réponse de la carte, la lecture avance quand même.
 * - Seule l'ère suivante est préchargée (tilesCovering : tuiles de la zone visible, cache du navigateur) :
 *   la carte ne charge jamais toutes les ères d'un coup.
 * - La lecture s'arrête au dernier pas (aujourd'hui) et au moindre geste du visiteur (isPlaybackGesture).
 * - Animations réduites demandées par le système : pas de lecture automatique, pas à pas seulement
 *   (le composant ne propose alors que « précédent » et « suivant »).
 */

/** Durée d'affichage d'une ère, une fois dessinée, selon la vitesse. */
export const PLAYBACK_SPEEDS = { slow: 5000, normal: 3000 };
export const DEFAULT_SPEED = 'normal';

/** Attente maximale du dessin d'une ère avant de passer à la suivante. */
export const MAX_WAIT_MS = 10000;

/** Au plus autant de tuiles préchargées pour une ère (zone visible d'un grand écran au zoom courant). */
export const MAX_PRELOAD_TILES = 48;

/**
 * Décennies parcourues : de la première ère qui a des tracés à la dernière (aujourd'hui), sans les
 * décennies vides (rien à montrer). Années seules acceptées aussi (repli sans prop `eras`).
 *
 * @param {Array<number|{year: number, has_data?: boolean}>} eras
 * @returns {number[]}
 */
export function playbackYears(eras) {
    const list = (eras ?? []).map((e) => (typeof e === 'number' ? { year: e, has_data: true } : e));
    const years = list
        .filter((e, i) => e.has_data !== false || i === list.length - 1)
        .map((e) => e.year);
    return [...new Set(years)].sort((a, b) => a - b);
}

/**
 * Décennie suivante (ou précédente, `delta` = -1) dans la séquence, null au bout. Une année hors de la
 * séquence (décennie vide affichée avant la lecture) mène à la plus proche dans le sens demandé.
 *
 * @param {number[]} years
 * @param {number} year
 * @param {1|-1} delta
 * @returns {number|null}
 */
export function stepYear(years, year, delta) {
    if (!years.length) return null;
    if (delta > 0) return years.find((y) => y > year) ?? null;
    return [...years].reverse().find((y) => y < year) ?? null;
}

/**
 * Tuiles (schéma XYZ) qui couvrent une emprise au zoom de la carte, comme MapLibre les demande pour une
 * source vectorielle (tuiles de 512 px : niveau = partie entière du zoom, borné à `maxzoom`).
 *
 * @param {{west: number, south: number, east: number, north: number}} bounds
 * @param {number} zoom
 * @param {number} [maxzoom]
 * @returns {Array<{z: number, x: number, y: number}>} vide si la zone dépasse MAX_PRELOAD_TILES
 */
export function tilesCovering(bounds, zoom, maxzoom = 14) {
    const z = Math.max(0, Math.min(maxzoom, Math.floor(zoom)));
    const n = 2 ** z;
    const clampLat = (lat) => Math.max(-85.0511, Math.min(85.0511, lat));
    const xOf = (lng) => Math.floor(((lng + 180) / 360) * n);
    const yOf = (lat) => {
        const r = (clampLat(lat) * Math.PI) / 180;
        return Math.floor(((1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2) * n);
    };
    const clampTile = (v) => Math.max(0, Math.min(n - 1, v));
    const x0 = clampTile(xOf(Math.max(-180, bounds.west)));
    const x1 = clampTile(xOf(Math.min(180, bounds.east)));
    const y0 = clampTile(yOf(bounds.north));
    const y1 = clampTile(yOf(bounds.south));
    if ((x1 - x0 + 1) * (y1 - y0 + 1) > MAX_PRELOAD_TILES) return [];
    const tiles = [];
    for (let x = x0; x <= x1; x++) for (let y = y0; y <= y1; y++) tiles.push({ z, x, y });
    return tiles;
}

/** Adresse d'une tuile à partir du gabarit `{z}/{x}/{y}` de la source. */
export function tileUrl(template, { z, x, y }) {
    return template.replace('{z}', String(z)).replace('{x}', String(x)).replace('{y}', String(y));
}

// Touches qui ne sont pas un geste sur la page : déplacer le focus, modificateurs seuls.
const NEUTRAL_KEYS = new Set(['Tab', 'Shift', 'Control', 'Alt', 'Meta', 'CapsLock']);

/**
 * Un évènement du visiteur doit-il arrêter la lecture ? Oui pour un appui, une molette ou une touche,
 * sauf dans les commandes de la lecture (`[data-playback-control]`) et pour les touches neutres.
 *
 * @param {{type: string, key?: string, target?: any}} event
 * @returns {boolean}
 */
export function isPlaybackGesture(event) {
    if (event.type === 'keydown' && NEUTRAL_KEYS.has(event.key ?? '')) return false;
    const target = event.target;
    if (target && typeof target.closest === 'function' && target.closest('[data-playback-control]'))
        return false;
    return ['pointerdown', 'wheel', 'keydown', 'touchstart'].includes(event.type);
}

/**
 * Longueur en service de chaque ère, d'après « Le réseau en chiffres » déjà calculé (prop `open_km` des
 * ères de la carte) ; une ère sans chiffre est absente (rien n'est affiché).
 *
 * @param {Array<{year: number, open_km?: number|null}>|null} eras
 * @returns {Record<number, number>}
 */
export function openKmByYear(eras) {
    return Object.fromEntries(
        (eras ?? [])
            .filter((e) => Number.isFinite(e.open_km) && e.open_km > 0)
            .map((e) => [e.year, e.open_km]),
    );
}

/**
 * Lecture : avance d'ère en ère, une étape après le dessin de la précédente.
 *
 * @param {object} options
 * @param {() => number[]} options.years décennies parcourues
 * @param {() => number} options.getYear décennie affichée par la carte (seule source de vérité)
 * @param {(year: number) => void} options.onStep affiche une décennie
 * @param {(state: {playing: boolean, ended: boolean}) => void} [options.onChange]
 * @param {{setTimeout: Function, clearTimeout: Function}} [options.timers]
 */
export function createPlayback({
    years,
    getYear,
    onStep,
    onChange = () => {},
    timers = globalThis,
}) {
    let playing = false;
    let ended = false;
    let speed = DEFAULT_SPEED;
    /** @type {number|null} ère demandée, pas encore dessinée */
    let pending = null;
    let dwellTimer = null;
    let waitTimer = null;

    const notify = () => onChange({ playing, ended });

    function clearTimers() {
        if (dwellTimer !== null) timers.clearTimeout(dwellTimer);
        if (waitTimer !== null) timers.clearTimeout(waitTimer);
        dwellTimer = null;
        waitTimer = null;
    }

    function show(year) {
        pending = year;
        onStep(year);
        if (playing) waitTimer = timers.setTimeout(() => shown(year), MAX_WAIT_MS);
    }

    function advance() {
        clearTimers();
        const next = stepYear(years(), getYear(), 1);
        if (next === null) {
            playing = false;
            ended = true;
            pending = null;
            notify();
            return;
        }
        show(next);
    }

    /** La carte a dessiné `year` : l'étape commence. */
    function shown(year) {
        if (pending !== year) return;
        pending = null;
        if (!playing) return;
        clearTimers();
        dwellTimer = timers.setTimeout(advance, PLAYBACK_SPEEDS[speed]);
    }

    function play() {
        if (playing) return;
        const list = years();
        if (!list.length) return;
        playing = true;
        ended = false;
        clearTimers();
        const year = getYear();
        // Au bout (aujourd'hui) ou hors de la séquence : on repart de la première ère.
        if (year === list[list.length - 1] || !list.includes(year)) {
            notify();
            show(list[0]);
            return;
        }
        pending = null;
        dwellTimer = timers.setTimeout(advance, PLAYBACK_SPEEDS[speed]);
        notify();
    }

    function pause() {
        clearTimers();
        pending = null;
        if (!playing) return;
        playing = false;
        notify();
    }

    function step(delta) {
        pause();
        const next = stepYear(years(), getYear(), delta);
        if (next === null) return false;
        ended = false;
        show(next);
        notify();
        return true;
    }

    return {
        play,
        pause,
        toggle: () => (playing ? pause() : play()),
        next: () => step(1),
        prev: () => step(-1),
        shown,
        setSpeed(next) {
            if (!(next in PLAYBACK_SPEEDS)) return;
            speed = next;
            // Étape en cours : recommencée à la nouvelle durée.
            if (playing && pending === null) {
                clearTimers();
                dwellTimer = timers.setTimeout(advance, PLAYBACK_SPEEDS[speed]);
            }
        },
        get speed() {
            return speed;
        },
        get playing() {
            return playing;
        },
        destroy() {
            clearTimers();
            playing = false;
            pending = null;
        },
    };
}
