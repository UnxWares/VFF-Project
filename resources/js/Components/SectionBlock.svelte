<script>
    /**
     * Section d'une page de contenu : titre de niveau 2 facultatif, chapeau (`intro`), contenu,
     * puis boutons (`actions`). Toutes les sections partagent la grille de 1 180 px et le même bord
     * gauche ; `maxWidth` limite la largeur du contenu (texte : 720px, grilles : 1100px), sans le
     * centrer.
     *
     * `kind` : 'default' (fond de page), 'alt' (bande grise pour alterner), 'cta' (invitation à agir
     * en fin de page, une seule : bande orange très pâle ouverte par un filet orange ; boutons .vff-btn
     * primary / secondary). Pas de repère décoratif au-dessus des titres : le titre et l'espace suffisent.
     * Dans la bande 'cta', les paragraphes du contenu (hors chapeau) prennent le dessin commun.
     */
    /** @type {{ title?: string|null, intro?: string|null, kind?: 'default'|'alt'|'cta', maxWidth?: string, id?: string, children?: import('svelte').Snippet, actions?: import('svelte').Snippet }} */
    let {
        title = null,
        intro = null,
        kind = 'default',
        maxWidth = '900px',
        id = undefined,
        children,
        actions,
    } = $props();

    const uid = $props.id();
    const headingId = $derived(id ? `${id}-title` : `${uid}-title`);
</script>

<section
    class="section-block {kind}"
    {id}
    aria-labelledby={title ? headingId : undefined}
    style:--block-max={maxWidth}
>
    <div class="inner">
        <div class="content">
            {#if title}
                <h2 class="block-title" id={headingId}>{title}</h2>
            {/if}
            {#if intro}
                <p class="intro">{intro}</p>
            {/if}
            {@render children?.()}
            {#if actions}
                <div class="section-actions">{@render actions()}</div>
            {/if}
        </div>
    </div>
</section>

<style>
    .section-block {
        padding: var(--vff-section-space) 0;
        scroll-margin-top: 80px;
    }
    .section-block.alt {
        background-color: var(--vff-bg-subtle);
        border-block: 1px solid var(--vff-border);
    }
    .section-block.cta {
        background-color: var(--vff-bg-accent);
        border-top: 4px solid var(--vff-accent);
    }
    .inner {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: 0 var(--vff-gutter);
    }
    .content {
        max-width: var(--block-max, 900px);
    }
    .block-title {
        margin: 0 0 var(--vff-space-5);
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        line-height: var(--vff-lh-heading);
        color: var(--vff-text);
        text-wrap: balance;
    }
    .intro {
        max-width: var(--vff-measure);
        margin: calc(-1 * var(--vff-space-2)) 0 var(--vff-block-space);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .section-block.cta .intro {
        color: var(--vff-text);
    }
    /* Chapeau seul avant les boutons : l'écart vient des boutons. */
    .section-block.cta .intro:last-child,
    .intro:has(+ .section-actions) {
        margin-bottom: 0;
    }
    .section-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
        align-items: center;
        gap: var(--vff-space-3);
        margin-top: var(--vff-block-space);
    }

    /* Bandeau d'invitation : titres et textes écrits par les pages, ramenés au dessin commun. */
    .section-block.cta :global(h2) {
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        line-height: var(--vff-lh-heading);
        text-align: left;
        color: var(--vff-text);
    }
    /* Le chapeau (.intro) garde sa marge basse : le contenu suivant ne s'y colle pas. */
    .section-block.cta :global(p:not(.intro)) {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        text-align: left;
        color: var(--vff-text);
    }
</style>
