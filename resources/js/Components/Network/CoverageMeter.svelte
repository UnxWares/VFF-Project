<script>
    /**
     * Une mesure de couverture : nom, définition, part en grand, nombres écrits, barre décorative (aria-hidden)
     * et, pour les dates, la ventilation par précision en texte. La barre est doublée par le texte et par le
     * tableau de la section.
     *
     * Props : `name`, `definition`, `shareText` (« 62,9 % » ou vide), `figure` (« 1 693 sur 2 690 lignes »),
     * `share` (0 à 1), `openText` (part des sources ouvertes), `levels` [{ label, text, share, tone }],
     * `levelsLabel`, `unavailable` (texte quand la table n'est pas importée).
     */
    import { barWidth } from '$lib/coverage.js';

    /** @type {{ name: string, definition: string, shareText?: string|null, figure?: string, share?: number|null, openText?: string, levels?: Array<{ label: string, text: string, share: number|null, tone: string }>, levelsLabel?: string, unavailable?: string|null }} */
    let {
        name,
        definition,
        shareText = null,
        figure = '',
        share = null,
        openText = '',
        levels = [],
        levelsLabel = '',
        unavailable = null,
    } = $props();
</script>

<li class="meter">
    <div class="what">
        <h3>{name}</h3>
        <p>{definition}</p>
    </div>
    <div class="how">
        {#if unavailable}
            <p class="missing">{unavailable}</p>
        {:else}
            <p class="figure">
                <strong>{shareText ?? ''}</strong>
                <span>{figure}</span>
            </p>
            {#if levels.length > 0}
                <span class="track" aria-hidden="true">
                    {#each levels as l (l.label)}
                        <span class="seg tone-{l.tone}" style:flex-grow={barWidth(l.share)}></span>
                    {/each}
                </span>
                <p class="levels-label">{levelsLabel}</p>
                <ul class="levels">
                    {#each levels as l (l.label)}
                        <li><span class="dot tone-{l.tone}" aria-hidden="true"></span>{l.text}</li>
                    {/each}
                </ul>
            {:else}
                <span class="track" aria-hidden="true">
                    <span class="seg tone-a" style:flex-grow={barWidth(share)}></span>
                    <span class="seg tone-none" style:flex-grow={100 - barWidth(share)}></span>
                </span>
            {/if}
            <p class="open">{openText}</p>
        {/if}
    </div>
</li>

<style>
    .meter {
        display: grid;
        grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
        gap: var(--vff-space-3) var(--vff-space-6);
        margin: 0;
        padding: var(--vff-space-4) 0;
        border-top: 1px solid var(--vff-border-strong);
    }
    .what,
    .how {
        min-width: 0;
    }
    h3 {
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.3;
    }
    .what p {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    .figure {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 2px var(--vff-space-3);
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.4;
    }
    .figure strong {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-figure);
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
    }
    .track {
        display: flex;
        height: 14px;
        overflow: hidden;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-border-control);
    }
    .seg {
        flex-basis: 0;
        flex-shrink: 0;
        min-width: 0;
    }
    .tone-a {
        background-color: var(--vff-accent);
    }
    .tone-b {
        background-color: var(--vff-info);
    }
    .tone-c {
        background-color: var(--vff-warning);
    }
    .tone-none {
        background-color: transparent;
    }
    .levels-label {
        margin: var(--vff-space-2) 0 2px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .levels {
        display: flex;
        flex-wrap: wrap;
        gap: 2px var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        font-variant-numeric: tabular-nums;
    }
    .levels li {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
        margin: 0;
    }
    .dot {
        flex: none;
        width: 12px;
        height: 12px;
        border-radius: var(--vff-radius-pill);
        border: 1px solid var(--vff-border-control);
    }
    .dot.tone-none {
        background-color: transparent;
    }
    .open,
    .missing {
        margin: var(--vff-space-2) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    @media (max-width: 760px) {
        .meter {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
