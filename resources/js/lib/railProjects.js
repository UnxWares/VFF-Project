// Utilitaires de la page « Projets : réouvertures et lignes nouvelles » (Pages/Network/Projects.svelte) : filtres, comptes
// et mise en forme des dates, sans accès au DOM. Les données viennent de App\Services\RailProjects.

import { fold } from './touristRailways.js';

/**
 * Projets qui répondent aux filtres : recherche dans le nom, les régions, les porteurs et les lignes ; état ; type ;
 * région.
 *
 * @param {Array<any>} projects
 * @param {{ q?: string, state?: string, kind?: string, region?: string }} filters
 */
export function filterProjects(projects, { q = '', state = '', kind = '', region = '' } = {}) {
    const needle = fold(q);
    return projects.filter((p) => {
        if (state && p.state !== state) return false;
        if (kind && p.kind !== kind) return false;
        if (region && !p.regions.includes(region)) return false;
        if (!needle) return true;
        const haystack = fold(
            [
                p.name,
                ...p.regions,
                ...p.porteurs.map((x) => x.name),
                ...p.lines.map((l) => l.name),
            ].join(' '),
        );
        return needle.split(' ').every((word) => haystack.includes(word));
    });
}

/**
 * Nombre de projets par valeur d'un champ (`state` ou `kind`), dans l'ordre donné.
 *
 * @param {Array<any>} projects
 * @param {'state'|'kind'} field
 * @param {Array<string>} order
 * @returns {Record<string, number>}
 */
export function countBy(projects, field, order) {
    /** @type {Record<string, number>} */
    const counts = Object.fromEntries(order.map((k) => [k, 0]));
    for (const p of projects) counts[p[field]] = (counts[p[field]] ?? 0) + 1;
    return counts;
}

/** Nombre de projets par région (un projet sur deux régions compte dans chacune). */
export function countByRegion(projects) {
    /** @type {Record<string, number>} */
    const counts = {};
    for (const p of projects) for (const r of p.regions) counts[r] = (counts[r] ?? 0) + 1;
    return counts;
}

/**
 * Date d'une étape à sa précision : « 12 mai 2023 », « mai 2023 » ou « 2023 ». Aucune précision n'est inventée.
 *
 * @param {string} date 'YYYY', 'YYYY-MM' ou 'YYYY-MM-DD'
 * @param {string} locale code de langue (fr, en, de)
 */
export function formatStageDate(date, locale = 'fr') {
    const [y, m, d] = String(date).split('-').map(Number);
    if (!m) return String(y);
    const base = new Date(Date.UTC(y, m - 1, d || 1));
    const options = /** @type {Intl.DateTimeFormatOptions} */ (
        d ? { day: 'numeric', month: 'long', year: 'numeric' } : { month: 'long', year: 'numeric' }
    );
    return new Intl.DateTimeFormat(locale, { ...options, timeZone: 'UTC' }).format(base);
}

/**
 * Libellé de la mise en service prévue : toujours « prévue … selon la source », jamais une date sèche.
 * Rend null quand aucune mise en service n'est prévue par une source.
 *
 * @param {{ planned_commissioning: any }} project
 * @param {string} locale
 */
export function plannedCommissioning(project, locale = 'fr') {
    const stage = project.planned_commissioning;
    if (!stage) return null;
    return {
        date: formatStageDate(stage.date, locale),
        source: stage.source.label,
        url: stage.source.url,
    };
}
