// Utilitaires de la page « Ce que sait l'encyclopédie » (Pages/Network/Coverage.svelte) : lecture des mesures de
// CoverageFigures (App\Services\CoverageFigures), sans accès au DOM.

const NBSP = ' ';

/**
 * Part en pourcentage, avec une décimale au plus (« 62,9 % »), ou null si elle n'existe pas. Une part non nulle
 * qui arrondirait à 0 s'écrit « moins de 0,1 % » côté page : ici elle reste à 0,1.
 * @param {number|null|undefined} share part entre 0 et 1
 * @param {string} [lang]
 * @returns {string|null}
 */
export function formatShare(share, lang = 'fr') {
    if (share === null || share === undefined) return null;
    const percent = Math.round(share * 1000) / 10;
    const shown = share > 0 && percent === 0 ? 0.1 : percent;
    const locale = lang === 'en' ? 'en-GB' : lang || 'fr';
    const text = shown.toLocaleString(locale, { maximumFractionDigits: 1 });

    return lang === 'en' ? `${text}%` : `${text}${NBSP}%`;
}

/**
 * Largeur (0 à 100) d'une barre pour une part, bornée.
 * @param {number|null|undefined} share
 */
export function barWidth(share) {
    if (share === null || share === undefined || Number.isNaN(share)) return 0;

    return Math.min(100, Math.max(0, share * 100));
}

/**
 * Mesure d'une section par sa clé.
 * @param {{ metrics: Array<any> }|undefined} section
 * @param {string} key
 * @returns {any}
 */
export function metricOf(section, key) {
    return section?.metrics?.find((m) => m.key === key) ?? null;
}

/**
 * Part de la ligne d'une ère (numérateur sur dénominateur), ou null sans dénominateur.
 * @param {number} part
 * @param {number} whole
 */
export function ratio(part, whole) {
    return whole > 0 ? part / whole : null;
}
