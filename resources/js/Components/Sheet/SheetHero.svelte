<script>
    /**
     * En-tête commun des fiches encyclopédiques (ligne, gare) : fil d'Ariane, plaque
     * (code de la ligne, type de gare), sur-titre, titre, compléments (`children`),
     * puis le « rail » (`rail` : ruban ou trait de la couleur de la carte, qui porte
     * une information, jamais un décor) et la rangée de chiffres (`stats`).
     *
     * Titre en encre sur fond clair (même famille que PageHero) : le blanc sur l'orange
     * de la charte ne passe pas le contraste AA (2,95:1) ; l'orange reste celui du rail.
     */
    /** @type {{
     *   crumbs?: Array<{ href: string, label: string, icon?: string }>,
     *   crumbsLabel?: string,
     *   plate?: string|null,
     *   plateIcon?: string|null,
     *   eyebrow?: Array<string>,
     *   title: string,
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
        children,
        rail,
        stats,
    } = $props();
</script>

<div class="sheet-hero">
    <div class="inner">
        {#if crumbs.length}
            <nav class="crumbs" aria-label={crumbsLabel}>
                {#each crumbs as c (c.href)}
                    <a href={c.href}
                        >{#if c.icon}<i class="bi {c.icon}" aria-hidden="true"></i>{/if}{c.label}</a
                    >
                {/each}
            </nav>
        {/if}
        {#if plate || eyebrow.length}
            <p class="eyebrow">
                {#if plate}<span class="plate"
                        >{#if plateIcon}<i class="bi {plateIcon}" aria-hidden="true"
                            ></i>{/if}{plate}</span
                    >{/if}
                {#each eyebrow as part, i (i)}<span class="part">{part}</span>{/each}
            </p>
        {/if}
        <h1>{title}</h1>
        {@render children?.()}
        {#if rail}<div class="rail">{@render rail()}</div>{/if}
        {#if stats}<div class="stats">{@render stats()}</div>{/if}
    </div>
</div>

<style>
    .sheet-hero {
        position: relative;
        padding: 36px 24px 0;
        background: linear-gradient(180deg, var(--vff-accent-soft) 0%, transparent 100%);
        border-bottom: 1px solid var(--vff-border);
    }
    .inner {
        max-width: 1180px;
        margin: 0 auto;
    }
    .crumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 22px;
        margin: 0 0 22px;
    }
    .crumbs a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 24px;
        padding: 4px 0;
        box-sizing: border-box;
        font-family: var(--vff-font-heading);
        font-size: 14px;
        color: var(--vff-text-muted);
        text-decoration: none;
    }
    .crumbs a:hover {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .crumbs a:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
        border-radius: 2px;
    }
    .eyebrow {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 16px;
        margin: 0 0 10px;
        font-family: var(--vff-font-heading);
        font-size: 14px;
        line-height: 1.4;
        color: var(--vff-text-muted);
    }
    /* Plaque : le code de la ligne (ou le type de gare), comme la plaque d'un poteau kilométrique. */
    .plate {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border: 1.5px solid var(--vff-text);
        border-radius: 4px;
        font-family: var(--vff-font-heading);
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: var(--vff-text);
        font-variant-numeric: tabular-nums;
        background: var(--vff-bg);
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
    h1:after {
        display: none;
    }
    .rail {
        margin: 26px 0 0;
    }
    .stats {
        margin: 0;
    }
    @media (max-width: 700px) {
        .sheet-hero {
            padding: 24px 16px 0;
        }
        .crumbs {
            margin-bottom: 16px;
        }
        .rail {
            margin-top: 20px;
        }
    }
</style>
