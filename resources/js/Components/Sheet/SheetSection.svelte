<script>
    /**
     * Section d'une fiche (ligne, gare) : titre de niveau 2, compteur facultatif,
     * chapeau (`intro`), puis le contenu. Même largeur, mêmes marges et même échelle
     * typographique sur toutes les fiches.
     */
    /** @type {{ id?: string, title?: string, count?: number|string|null, intro?: string|null, label?: string, children?: import('svelte').Snippet, actions?: import('svelte').Snippet, sectionEl?: HTMLElement }} */
    let {
        id = undefined,
        title = undefined,
        count = null,
        intro = null,
        label = undefined,
        children,
        actions,
        sectionEl = $bindable(),
    } = $props();
    const headingId = $derived(id ? `${id}-title` : undefined);
</script>

<section
    class="sheet-section"
    {id}
    bind:this={sectionEl}
    aria-labelledby={title ? headingId : undefined}
    aria-label={title ? undefined : label}
>
    {#if title}
        <div class="head">
            <h2 id={headingId}>
                {title}{#if count != null}<span class="count">{count}</span>{/if}
            </h2>
            {@render actions?.()}
        </div>
    {/if}
    {#if intro}<p class="intro">{intro}</p>{/if}
    {@render children?.()}
</section>

<style>
    .sheet-section {
        max-width: 1180px;
        margin: 0 auto;
        padding: 52px 24px 0;
        scroll-margin-top: 80px;
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px 16px;
        margin: 0 0 12px;
    }
    h2 {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 28px;
        line-height: 1.2;
        color: var(--vff-text);
    }
    h2:after {
        display: none;
    }
    .count {
        font-family: var(--vff-font-heading);
        font-size: 15px;
        font-weight: 400;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .intro {
        max-width: 72ch;
        margin: 0 0 20px;
        font-family: var(--vff-font-body);
        font-size: 15px;
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    @media (max-width: 700px) {
        .sheet-section {
            padding: 40px 16px 0;
        }
        h2 {
            font-size: 24px;
        }
    }
</style>
