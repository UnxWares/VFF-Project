<script>
    let { title = null, children, kind = 'default', maxWidth = '900px' } = $props();
</script>

<section class="section-block {kind}" style="--block-max: {maxWidth};">
    <div class="inner">
        {#if title}
            <h2 class="block-title">{title}</h2>
        {/if}
        {@render children?.()}
    </div>
</section>

<style>
    .section-block {
        padding: 64px 16px;
    }
    .section-block.alt {
        background-color: var(--vff-bg-elevated);
    }
    /* `cta` = bandeau orange dégradé. Padding réduit pour ne pas avoir trop
       de vide autour des boutons. `margin-bottom: -30px` absorbe le
       `margin-top: 30px` du <footer> d'origine → transition orange → footer
       nette, sans bande blanche entre les deux. */
    .section-block.cta {
        background: linear-gradient(135deg, var(--vff-accent) 0%, #ff8b35 100%);
        color: var(--vff-text-inverse);
        padding: 48px 16px;
        margin-bottom: -30px;
    }
    .section-block.cta :global(h2),
    .section-block.cta :global(p) {
        color: var(--vff-text-inverse);
    }
    .inner {
        max-width: var(--block-max, 900px);
        margin: 0 auto;
    }
    .block-title {
        margin: 0 0 24px;
        text-align: center;
        font-size: clamp(24px, 3vw, 32px);
    }
</style>
