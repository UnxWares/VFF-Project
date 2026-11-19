// Réputation des membres : mise en forme commune au profil, à l'espace de validation et à la page d'aide.
// Les chiffres et le niveau viennent du serveur (App\Services\Reputation\ReputationLevels) ; ici, seulement du texte.

/**
 * Phrase « il manque ... » pour atteindre le niveau suivant, ou null s'il n'y a rien à dire.
 * `t` : fonction de traduction ; `language` : code de langue ; `next` : { level, missing } du serveur.
 *
 * @param {(key: string, options?: object) => string} t
 * @param {string} language
 * @param {{ level: string, missing: { applied: number, reviews: number, acceptance: number|null } }|null} next
 * @returns {string|null}
 */
export function nextLevelSentence(t, language, next) {
    if (!next) return null;
    const { applied, reviews, acceptance } = next.missing;
    /** @type {string[]} */
    const parts = [];
    if (applied > 0) parts.push(t('reputation.profile.missing_applied', { count: applied }));
    if (reviews > 0) parts.push(t('reputation.profile.missing_reviews', { count: reviews }));
    if (acceptance !== null)
        parts.push(t('reputation.profile.missing_acceptance', { count: acceptance }));
    if (parts.length === 0) return null;
    const conditions = new Intl.ListFormat(language, { style: 'long', type: 'conjunction' }).format(
        parts,
    );
    return t('reputation.profile.next', {
        level: t(`reputation.levels.${next.level}`),
        conditions,
    });
}
