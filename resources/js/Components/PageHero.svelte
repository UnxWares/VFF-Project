<script>
    /**
     * Bandeau de titre des pages de contenu (rubriques, annuaires, pages légales) : rubrique
     * facultative, titre (le seul h1 de la page), chapeau, état facultatif, compléments (`children`).
     * Aligné à gauche sur la grille de 1 180 px, comme SheetHero et les sections : un seul bord
     * gauche pour toute la page. Fond plat, filet en bas ; pas de dégradé.
     *
     * Props : `eyebrow` (rubrique, texte court, à omettre s'il répète le titre), `title`,
     * `subtitle` (chapeau), `status` (état du contenu, ex. « En construction »), `titleLang`
     * (langue du titre s'il diffère de la page), `children`.
     */
    /** @type {{ eyebrow?: string|null, title: string, subtitle?: string|null, status?: string|null, titleLang?: string, children?: import('svelte').Snippet }} */
    let {
        eyebrow = null,
        title,
        subtitle = null,
        status = null,
        titleLang = undefined,
        children,
    } = $props();
</script>

<header class="page-hero">
    <div class="inner">
        {#if eyebrow}
            <p class="eyebrow">{eyebrow}</p>
        {/if}
        <h1 lang={titleLang}>{title}</h1>
        {#if subtitle}
            <p class="subtitle">{subtitle}</p>
        {/if}
        {#if status}
            <p class="status">
                <span class="vff-badge muted">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    {status}
                </span>
            </p>
        {/if}
        {@render children?.()}
    </div>
</header>

<style>
    .page-hero {
        padding: 44px 0;
        background-color: var(--vff-bg-subtle);
        border-bottom: 1px solid var(--vff-border);
    }
    .inner {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: 0 var(--vff-gutter);
    }
    .eyebrow {
        margin: 0 0 10px;
        font-family: var(--vff-font-heading);
        font-size: 15px;
        font-weight: 600;
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    h1 {
        max-width: 24ch;
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h1);
        line-height: var(--vff-lh-tight);
        letter-spacing: -0.01em;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .subtitle {
        max-width: 60ch;
        margin: 14px 0 0;
        font-family: var(--vff-font-body);
        font-size: clamp(1.0625rem, 1rem + 0.4vw, 1.25rem);
        line-height: 1.5;
        color: var(--vff-text-muted);
        text-wrap: pretty;
    }
    .status {
        margin: 18px 0 0;
    }
    @media (max-width: 600px) {
        .page-hero {
            padding: 28px 0;
        }
    }
</style>
