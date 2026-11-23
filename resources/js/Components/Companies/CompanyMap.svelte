<script>
    import { projectMap } from '$lib/companySheet.js';

    /**
     * Carte sobre du réseau d'une compagnie : le tracé simplifié de ses lignes à l'ère de plus grande extension,
     * sans fond de carte (aucune tuile, aucun tiers). Le dessin est une image nommée (`role="img"`) ; la liste des
     * lignes de la page en est l'alternative textuelle complète.
     *
     * Props : `map` ({ bbox, paths } de CompanyFigures), `label` (nom accessible), `legend` (texte de la légende).
     */
    /** @type {{ map: { bbox: [number, number, number, number], paths: Array<Array<[number, number]>> }, label: string, legend: string }} */
    let { map, label, legend } = $props();

    const shape = $derived(projectMap(map));
</script>

{#if shape}
    <figure class="company-map">
        <svg
            viewBox={shape.viewBox}
            role="img"
            aria-label={label}
            preserveAspectRatio="xMidYMid meet"
            focusable="false"
        >
            <path d={shape.d} vector-effect="non-scaling-stroke" />
        </svg>
        <figcaption><span class="swatch" aria-hidden="true"></span>{legend}</figcaption>
    </figure>
{/if}

<style>
    .company-map {
        margin: 0;
        padding: var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-muted);
    }
    svg {
        display: block;
        width: 100%;
        height: auto;
        max-height: 640px;
    }
    path {
        fill: none;
        stroke: var(--vff-accent-ink);
        stroke-width: 1.4;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    figcaption {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
    }
    .swatch {
        display: inline-block;
        width: 28px;
        height: 0;
        border-top: 4px solid var(--vff-accent-ink);
        border-radius: 2px;
    }
</style>
