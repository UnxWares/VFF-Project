<script>
    /**
     * Élément d'une liste de points (valeurs, raisons, règles, étapes) : titre précédé d'une petite
     * icône, phrase, état facultatif. Dessin de document, pas de carte : un filet au-dessus, pas de
     * fond, pas de pastille d'icône, rien qui bouge au survol (l'élément n'est pas cliquable).
     * Se range dans une grille de la page (ex. repeat(auto-fit, minmax(240px, 1fr)), gap 24px 32px).
     *
     * `headingLevel` : 3 par défaut (sous le h2 d'une SectionBlock) ; 2 si la section n'a pas de titre.
     */
    /** @type {{ icon?: string, title: string, text: string, status?: string|null, headingLevel?: 2|3|4 }} */
    let { icon = 'bi-circle', title, text, status = null, headingLevel = 3 } = $props();
</script>

<div class="icon-card">
    <svelte:element this={`h${headingLevel}`} class="title">
        <i class="bi {icon}" aria-hidden="true"></i>
        <span>{title}</span>
    </svelte:element>
    <p class="text">{text}</p>
    {#if status}
        <p class="status"><span class="vff-badge muted">{status}</span></p>
    {/if}
</div>

<style>
    .icon-card {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-top: 14px;
        border-top: 1px solid var(--vff-border-strong);
        text-align: left;
    }
    .title {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.35;
        color: var(--vff-text);
    }
    .title i {
        flex-shrink: 0;
        font-size: 18px;
        line-height: 1;
        color: var(--vff-accent-ink);
        transform: translateY(2px);
    }
    .text {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .status {
        margin: 4px 0 0;
    }
</style>
