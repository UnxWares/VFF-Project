<script>
    /**
     * En-tête commun des fiches encyclopédiques (ligne, gare, matériel) : fil d'Ariane, plaque
     * (code de la ligne, type de gare), sur-titre, titre, compléments (`children`), puis le « rail »
     * (`rail` : ruban ou trait de la couleur de la carte, qui porte une information, jamais un décor)
     * et la rangée de chiffres (`stats`).
     *
     * Titre en encre sur fond plat (même famille que PageHero) : le blanc sur l'orange de la charte
     * ne passe pas le contraste AA (2,95:1) ; l'orange reste celui du rail.
     * `titleLang` : langue du titre quand elle diffère de celle de la page (nom français affiché sur
     * la page anglaise ou allemande, par exemple).
     */
    /** @type {{
     *   crumbs?: Array<{ href: string, label: string, icon?: string }>,
     *   crumbsLabel?: string,
     *   plate?: string|null,
     *   plateIcon?: string|null,
     *   eyebrow?: Array<string>,
     *   title: string,
     *   titleLang?: string,
     *   children?: import('svelte').Snippet,
     *   rail?: import('svelte').Snippet,
     *   stats?: import('svelte').Snippet,
     * }} */
    let {
        crumbs = [],
        crumbsLabel = undefined,
        plate = null,
        plateIcon = null,
        eyebrow = [],
        title,
        titleLang = undefined,
        children,
        rail,
        stats,
    } = $props();
</script>

<header class="sheet-hero">
    <div class="inner">
        {#if crumbs.length}
            <nav class="crumbs" aria-label={crumbsLabel}>
                <ol>
                    {#each crumbs as c (c.href)}
                        <li>
                            <a href={c.href}
                                >{#if c.icon}<i class="bi {c.icon}" aria-hidden="true"
                                    ></i>{/if}{c.label}</a
                            >
                        </li>
                    {/each}
                </ol>
            </nav>
        {/if}
        {#if plate || eyebrow.length}
            <p class="eyebrow">
                {#if plate}<span class="vff-plate plate"
                        >{#if plateIcon}<i class="bi {plateIcon}" aria-hidden="true"
                            ></i>{/if}{plate}</span
                    >{/if}
                {#each eyebrow as part, i (i)}<span class="part">{part}</span>{/each}
            </p>
        {/if}
        <h1 lang={titleLang}>{title}</h1>
        {@render children?.()}
        {#if rail}<div class="rail">{@render rail()}</div>{/if}
        {#if stats}<div class="stats">{@render stats()}</div>{/if}
    </div>
</header>

<style>
    .sheet-hero {
        position: relative;
        padding: 32px 0 0;
        background-color: var(--vff-bg-subtle);
        border-bottom: 1px solid var(--vff-border);
    }
    .inner {
        max-width: calc(var(--vff-container) + 2 * var(--vff-gutter));
        margin: 0 auto;
        padding: 0 var(--vff-gutter);
    }
    .crumbs ol {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 0;
        margin: 0 0 20px;
        padding: 0;
        list-style: none;
    }
    .crumbs li {
        display: inline-flex;
        align-items: center;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    /* Séparateur décoratif : non lu (texte de remplacement vide). */
    .crumbs li + li::before {
        content: '›';
        content: '›' / '';
        margin: 0 10px;
        color: var(--vff-text-muted);
    }
    .crumbs a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 24px;
        padding: 2px 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        text-decoration: underline;
        text-decoration-color: var(--vff-border-strong);
        text-underline-offset: 3px;
    }
    .crumbs a:hover {
        color: var(--vff-accent-ink);
        text-decoration-color: currentColor;
    }
    .eyebrow {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 16px;
        margin: 0 0 10px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    h1 {
        margin: 0 0 10px;
        font-family: var(--vff-font-display);
        font-size: clamp(30px, 4.4vw, 48px);
        line-height: 1.08;
        letter-spacing: -0.01em;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .rail {
        margin: 26px 0 0;
    }
    .stats {
        margin: 0;
    }
    @media (max-width: 700px) {
        .sheet-hero {
            padding-top: 24px;
        }
        .crumbs ol {
            margin-bottom: 16px;
        }
        .rail {
            margin-top: 20px;
        }
    }
</style>
