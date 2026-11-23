/**
 * « Qu'y avait-il ici ? » sur la carte /maps : logique pure du panneau (adresse de l'appel, case de grille,
 * chronologie des faits, textes de distance et de point kilométrique). Testée dans tests/js/placeHistory.test.js ;
 * les données viennent de `GET /api/v1/what-was-here` (App\Services\Map\WhatWasHere, le même service que l'outil
 * MCP `what_was_here`).
 */
import { pkLabel } from '$lib/lineSpeeds.js';

/** Case de la grille du serveur (degrés) : une réponse vaut pour toute la case (environ 100 m). */
export const GRID = 0.001;
/** Appui long sur téléphone (ms) et déplacement toléré pendant l'appui (px). */
export const LONG_PRESS_MS = 600;
export const LONG_PRESS_SLOP = 10;

/** Centre de la case de la grille qui contient la valeur (4 décimales, comme le serveur). */
export const cellCenter = (value) =>
    Math.round((Math.floor(value / GRID) + 0.5) * GRID * 1e4) / 1e4;

/**
 * Année à demander pour une ère : null pour l'ère actuelle (le serveur prend alors aujourd'hui, ce que
 * la carte appelle « aujourd'hui »).
 * @param {number} era
 * @param {number} currentEra
 */
export const yearParam = (era, currentEra) => (era === currentEra ? null : era);

/**
 * Point valide `{ lng, lat }` (nombres finis dans les limites), sinon null.
 * @param {{ lng: unknown, lat: unknown } | null | undefined} point
 */
export function validPoint(point) {
    if (!point) return null;
    const lng = Number(point.lng);
    const lat = Number(point.lat);
    if (!Number.isFinite(lng) || !Number.isFinite(lat)) return null;
    if (Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
    return { lng, lat };
}

/** Clé de cache d'un appel : case, ère et langue. */
export const placeKey = ({ lng, lat }, era, lang) =>
    `${cellCenter(lat)}|${cellCenter(lng)}|${era}|${lang}`;

/**
 * Adresse de l'appel.
 * @param {{ lng: number, lat: number }} point
 * @param {number} era
 * @param {number} currentEra
 * @param {string} lang
 * @param {string | null} version version des données (adresse versionnée, cache long du navigateur)
 */
export function placeUrl(point, era, currentEra, lang, version = null) {
    const query = new URLSearchParams({
        lat: String(cellCenter(point.lat)),
        lng: String(cellCenter(point.lng)),
        lang,
    });
    const year = yearParam(era, currentEra);
    if (year !== null) query.set('year', String(year));
    if (version) query.set('v', version);
    return `/api/v1/what-was-here?${query}`;
}

/** Point kilométrique d'une ligne : `{ text: '12+300', approximate }` ou null sans PK ; arrondi à 100 m si le tracé est réservé. */
export function lineKm(line) {
    if (line.pk_m === null || line.pk_m === undefined) return null;
    return { text: pkLabel(line.pk_m), approximate: Boolean(line.geometry_withheld) };
}

/** Distance du point au tracé en mètres ; null quand le tracé vient d'une source non redistribuable. */
export function lineDistance(line) {
    if (line.geometry_withheld || line.distance_m === null || line.distance_m === undefined) {
        return null;
    }
    return line.distance_m;
}

/** Au moins une ligne dont le tracé est réservé (le panneau explique alors l'absence de distance). */
export const hasWithheld = (lines) => lines.some((l) => l.geometry_withheld);

/**
 * Faits datés de l'année (événements des lignes et accidents), du plus ancien au plus récent.
 * Un événement porte la ligne qu'il concerne ; une ligne qui en a plusieurs (voies partagées) ne le
 * répète pas : un même identifiant n'apparaît qu'une fois.
 * @param {{ lines?: any[], accidents?: any[] }} data
 */
export function chronology(data) {
    const seen = new Set();
    const facts = [];
    for (const line of data.lines ?? []) {
        for (const e of line.events ?? []) {
            if (seen.has(`e${e.id}`)) continue;
            seen.add(`e${e.id}`);
            facts.push({
                key: `e${e.id}`,
                kind: 'event',
                date: e.date,
                when: e.when,
                label: e.label,
                text: e.text ?? null,
                section: e.section ?? null,
                source: e.source ?? null,
                sourceUrl: e.source_url ?? null,
                href: `/lines/${line.slug}`,
                subject: line.name,
            });
        }
    }
    for (const a of data.accidents ?? []) {
        facts.push({
            key: `a${a.slug}`,
            kind: 'accident',
            date: a.date,
            when: a.when,
            label: a.kind_label ?? a.kind,
            text: null,
            section: null,
            source: null,
            sourceUrl: null,
            href: `/accidents/${a.slug}`,
            subject: a.name,
            deaths: a.deaths ?? null,
            toReview: a.review_status === 'to_review',
        });
    }
    return facts.sort(
        (x, y) => String(x.date).localeCompare(String(y.date)) || x.key.localeCompare(y.key),
    );
}

/** Dernière ère passée proposée par le lien « voir en 1930 » : 1930 si elle existe, sinon la plus ancienne. */
export function pastEraFor(eras, currentEra, preferred = 1930) {
    const past = eras.filter((y) => y < currentEra);
    if (past.includes(preferred)) return preferred;
    return past.length ? past[0] : null;
}

/**
 * Époque vers laquelle bascule le lien du panneau : l'ère actuelle depuis une ère passée, sinon l'ère passée
 * demandée (`past`) ; null s'il n'y en a pas.
 */
export const switchTarget = (era, currentEra, past) => (era === currentEra ? past : currentEra);

/**
 * Appui long sur écran tactile, sans dépendre d'un événement `contextmenu` (absent ou tardif selon les
 * navigateurs). Un appui qui bouge, se lève ou à plusieurs doigts n'ouvre rien.
 * @param {{ onPress: (x: number, y: number) => void, delay?: number, slop?: number, setTimer?: Function, clearTimer?: Function }} options
 */
export function createLongPress({
    onPress,
    delay = LONG_PRESS_MS,
    slop = LONG_PRESS_SLOP,
    setTimer = setTimeout,
    clearTimer = clearTimeout,
}) {
    let timer = null;
    let origin = null;
    const cancel = () => {
        if (timer !== null) clearTimer(timer);
        timer = null;
        origin = null;
    };
    return {
        start(x, y, touches = 1) {
            cancel();
            if (touches !== 1) return;
            origin = { x, y };
            timer = setTimer(() => {
                const at = origin;
                timer = null;
                origin = null;
                if (at) onPress(at.x, at.y);
            }, delay);
        },
        move(x, y) {
            if (origin && Math.hypot(x - origin.x, y - origin.y) > slop) cancel();
        },
        cancel,
        get pending() {
            return timer !== null;
        },
    };
}
