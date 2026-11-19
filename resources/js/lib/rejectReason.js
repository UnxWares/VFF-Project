/**
 * Motif d'un refus de contribution : obligatoire, 5 caractères au moins une fois les blancs
 * retirés. Même règle que RejectContributionRequest côté serveur.
 */
export const REASON_MIN = 5;
export const REASON_MAX = 1000;

/**
 * @param {string|null|undefined} text Motif saisi.
 * @returns {boolean} Vrai si le motif manque ou est trop court pour être envoyé.
 */
export function isReasonMissing(text) {
    return String(text ?? '').trim().length < REASON_MIN;
}
