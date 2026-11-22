<script>
    import { sectionHeight } from '$lib/actions/sectionHeight.js';
    /**
     * Section d'une fiche (ligne, gare, matériel) : titre de niveau 2, compteur facultatif,
     * chapeau (`intro`), puis le contenu. Même largeur, mêmes marges et même échelle typographique
     * sur toutes les fiches (grille de 1 180 px, bord gauche commun avec SheetHero).
     * Le compteur est lu « Titre (19) » par les lecteurs d'écran.
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
    use:sectionHeight
    aria-labelledby={title ? headingId : undefined}
    aria-label={title ? undefined : label}
>
    {#if title}
        <div class="head">
            <h2 id={headingId}>
                {title}{#if count != null}<span class="count"
                        ><span class="vff-sr-only">&nbsp;(</span>{count}<span class="vff-sr-only"
                            >)</span
                        ></span
                    >{/if}
            </h2>
            {@render actions?.()}
        </div>
    {/if}
    {#if intro}<p class="intro">{intro}</p>{/if}
    {@render children?.()}
</section>

<style>
    .sheet-section {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: var(--vff-section-space) var(--vff-gutter) 0;
        scroll-margin-top: 80px;
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px 16px;
        margin: 0 0 var(--vff-space-4);
    }
    h2 {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
        line-height: var(--vff-lh-heading);
        color: var(--vff-text);
        text-wrap: balance;
    }
    .count {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 400;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-5);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
</style>
