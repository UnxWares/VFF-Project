<script>
    /**
     * Barres horizontales empilées, une ligne par décennie, sans fioritures : libellé, barre, valeur écrite.
     * Les barres sont décoratives (aria-hidden) ; chaque ligne porte un texte pour lecteur d'écran avec toutes
     * ses valeurs, et la page propose le même contenu en tableau.
     *
     * Props : `rows` [{ key, label, values (une par série), value (texte visible), sr (phrase complète) }],
     * `series` [{ label, tone }] (tone : a à e), `caption` (légende du graphique), `reserve` (rem laissés à droite pour la valeur écrite).
     */
    /** @type {{ rows: Array<{ key: string|number, label: string, values: number[], value: string, sr: string }>, series: Array<{ label: string, tone: string }>, caption: string, reserve?: number }} */
    let { rows, series, caption, reserve = 11 } = $props();

    const totals = $derived(rows.map((r) => r.values.reduce((a, b) => a + b, 0)));
    const max = $derived(Math.max(1, ...totals));
</script>

<figure class="chart" style:--max={max} style:--reserve="{reserve}rem">
    <figcaption>{caption}</figcaption>
    {#if series.length > 1}
        <ul class="legend">
            {#each series as s (s.label)}
                <li><span class="swatch tone-{s.tone}" aria-hidden="true"></span>{s.label}</li>
            {/each}
        </ul>
    {/if}
    <ol class="rows">
        {#each rows as r, i (r.key)}
            <li class="row" style:--total={totals[i]}>
                <span class="vff-sr-only">{r.sr}</span>
                <span class="label" aria-hidden="true">{r.label}</span>
                <span class="plot" aria-hidden="true">
                    <span class="bar">
                        {#each r.values as v, j (j)}
                            <span class="seg tone-{series[j]?.tone ?? 'a'}" style:flex-grow={v}
                            ></span>
                        {/each}
                    </span>
                </span>
                <span class="value" aria-hidden="true">{r.value}</span>
            </li>
        {/each}
    </ol>
</figure>

<style>
    .chart {
        margin: 0;
    }
    figcaption {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-1) var(--vff-space-5);
        margin: 0 0 var(--vff-space-4);
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
        margin: 0;
    }
    .swatch {
        flex: none;
        width: 18px;
        height: 18px;
        border-radius: var(--vff-radius-xs);
    }
    .rows {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .row {
        display: grid;
        grid-template-columns: 8.5rem minmax(0, 1fr);
        align-items: center;
        column-gap: var(--vff-space-3);
        min-height: 34px;
        margin: 0;
        position: relative;
    }
    .label {
        grid-column: 1;
        grid-row: 1;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .plot {
        grid-column: 2;
        grid-row: 1;
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
        min-width: 0;
    }
    .bar {
        display: flex;
        flex: none;
        width: calc(var(--total) / var(--max) * (100% - var(--reserve)));
        height: 20px;
        overflow: hidden;
        border-radius: var(--vff-radius-xs);
    }
    .seg {
        flex-basis: 0;
        flex-shrink: 0;
        min-width: 0;
    }
    .value {
        grid-column: 2;
        grid-row: 1;
        justify-self: start;
        margin-left: calc(var(--total) / var(--max) * (100% - var(--reserve)) + 8px);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        color: var(--vff-text);
    }
    .tone-a {
        background-color: var(--vff-accent);
    }
    .tone-b {
        background-color: var(--vff-info);
    }
    .tone-c {
        background-color: var(--vff-border-control);
    }
    .tone-d {
        background-color: var(--vff-warning);
    }
    .tone-e {
        background-color: var(--vff-text-muted);
    }

    /* Tablette et téléphone : libellé et valeur sur une ligne, barre pleine largeur dessous. */
    @media (max-width: 700px) {
        .row {
            grid-template-columns: minmax(0, 1fr) auto;
            row-gap: 2px;
            padding-block: var(--vff-space-2);
        }
        .label {
            grid-column: 1;
            grid-row: 1;
        }
        .value {
            grid-column: 2;
            grid-row: 1;
            margin-left: 0;
            justify-self: end;
        }
        .plot {
            grid-column: 1 / -1;
            grid-row: 2;
        }
        .bar {
            width: calc(var(--total) / var(--max) * 100%);
        }
    }
</style>
