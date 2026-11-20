<script>
    /**
     * État vide : une liste, une recherche ou une section sans contenu. Dit ce qui manque et ce
     * qu'on peut faire (bouton ou lien dans `children`), sans s'excuser ni faire d'humour.
     *
     *   <EmptyState title={$i18n.t('stations.no_results')} text={$i18n.t('…')} icon="bi-search">
     *       <button class="vff-btn secondary small" onclick={reset}>{$i18n.t('…')}</button>
     *   </EmptyState>
     *
     * `headingLevel` : 2 ou 3 si l'état vide remplace une section entière (le titre entre alors dans
     * le plan de la page) ; par défaut, simple paragraphe en gras.
     */
    /** @type {{ title: string, text?: string|null, icon?: string|null, headingLevel?: 2|3|4|null, children?: import('svelte').Snippet }} */
    let { title, text = null, icon = null, headingLevel = null, children } = $props();
</script>

<div class="vff-empty">
    {#if icon}<i class="bi {icon}" aria-hidden="true"></i>{/if}
    {#if headingLevel}
        <svelte:element this={`h${headingLevel}`} class="vff-empty-title">{title}</svelte:element>
    {:else}
        <p class="vff-empty-title">{title}</p>
    {/if}
    {#if text}<p class="vff-empty-text">{text}</p>{/if}
    {#if children}<div class="vff-empty-actions">{@render children()}</div>{/if}
</div>
