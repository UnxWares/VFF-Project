<script>
    /**
     * Section d'une page de contenu : titre de niveau 2 facultatif, chapeau (`intro`), contenu,
     * puis boutons (`actions`). Toutes les sections partagent la grille de 1 180 px et le même bord
     * gauche ; `maxWidth` limite la largeur du contenu (texte : 720px, grilles : 1100px), sans le
     * centrer.
     *
     * `kind` : 'default' (fond de page), 'alt' (bande grise pour alterner), 'cta' (invitation à agir
     * en fin de page, une seule : bande orange très pâle ouverte par un filet orange ; boutons .vff-btn
     * primary / secondary). Le titre porte un petit filet orange (repère de début de section).
     * Anciens contenus de 'cta' (h2, p et .cta-buttons écrits par la page, boutons on-cta et
     * ghost-on-cta pensés pour l'ancien aplat orange) : remis au dessin commun ici.
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
    /* Repère de début de section : petit filet orange au-dessus du titre (pas dans le bandeau d'invitation,
       déjà ouvert par son filet). */
    .section-block:not(.cta) .block-title::before {
        content: '';
        display: block;
        width: 28px;
        height: 3px;
        margin-bottom: 14px;
        border-radius: 2px;
        background-color: var(--vff-accent);
    }
    .intro {
        max-width: var(--vff-measure);
        margin: calc(-1 * var(--vff-space-2)) 0 var(--vff-block-space);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .section-actions,
    .section-block.cta :global(.cta-buttons) {
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
    .section-block.cta :global(p) {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        text-align: left;
        color: var(--vff-text);
    }
    .section-block.cta :global(.vff-btn.on-cta) {
        background-color: var(--vff-accent);
        border-color: var(--vff-accent);
        color: var(--vff-on-accent);
    }
    .section-block.cta :global(.vff-btn.on-cta:hover) {
        background-color: var(--vff-accent-hover);
        border-color: var(--vff-accent-hover);
    }
    .section-block.cta :global(.vff-btn.ghost-on-cta) {
        background-color: transparent;
        border-color: var(--vff-accent-ink);
        color: var(--vff-accent-ink);
    }
    .section-block.cta :global(.vff-btn.ghost-on-cta:hover) {
        background-color: var(--vff-accent-soft);
    }
</style>
