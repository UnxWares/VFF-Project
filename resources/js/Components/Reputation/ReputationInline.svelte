<script>
    import { i18n } from '$lib/i18n.js';

    /**
     * Réputation de l'auteur d'une contribution, en une ligne à côté de son nom (espace de
     * validation) : niveau (absent d'un compte suspendu), contributions acceptées et refusées.
     * Rien pour un compte supprimé (`reputation` null).
     * `reputation` : { applied, rejected, level } (ContributionController::validateList).
     */
    /** @type {{ reputation: { applied: number, rejected: number, level: string|null }|null }} */
    let { reputation } = $props();

    const summary = $derived(
        reputation
            ? [
                  reputation.level ? $i18n.t(`reputation.levels.${reputation.level}`) : null,
                  $i18n.t('reputation.validate.applied', { count: reputation.applied }),
                  $i18n.t('reputation.validate.rejected', { count: reputation.rejected }),
              ]
                  .filter(Boolean)
                  .join(', ')
            : null,
    );
</script>

{#if summary}
    <span>({summary})</span>
{/if}
