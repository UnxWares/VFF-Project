<script>
    /**
     * Point d'une liste (valeurs, raisons, règles, façons de participer) : titre, phrase, état
     * facultatif, complément facultatif (`children` : un lien). Dessin de document, pas de carte :
     * une rangée par point, filet au-dessus, titre à gauche et phrase à droite sur grand écran
     * (l'un sous l'autre sur téléphone). Pas d'icône décorative, pas de fond, rien au survol.
     * Se range dans une liste `.vff-points` (general.css), jamais dans une grille de trois blocs.
     *
     * `headingLevel` : 3 par défaut (sous le h2 d'une SectionBlock) ; 2 si la section n'a pas de titre.
     */
    /** @type {{ title: string, text: string, status?: string|null, headingLevel?: 2|3|4, children?: import('svelte').Snippet }} */
    let { title, text, status = null, headingLevel = 3, children } = $props();
</script>

<div class="icon-card">
    <div class="row">
        <svelte:element this={`h${headingLevel}`} class="title">{title}</svelte:element>
        <div class="body">
            <p class="text">{text}</p>
            {#if status}
                <p class="status"><span class="vff-badge muted">{status}</span></p>
            {/if}
            {@render children?.()}
        </div>
    </div>
</div>

<style>
    .icon-card {
        container-type: inline-size;
        padding: var(--vff-space-5) 0;
        border-top: 1px solid var(--vff-border);
        text-align: left;
    }
    .row {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-2);
    }
    .title {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.35;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .body {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-3);
        min-width: 0;
    }
    .text {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text);
    }
    .status {
        margin: 0;
    }
    /* Assez de place (selon la colonne, pas l'écran) : titre et phrase sur la même rangée, comme
       une fiche. */
    @container (min-width: 560px) {
        .row {
            grid-template-columns: minmax(0, 14rem) minmax(0, 1fr);
            column-gap: var(--vff-space-6);
        }
    }
</style>
