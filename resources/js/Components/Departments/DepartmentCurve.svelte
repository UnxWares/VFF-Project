<script>
    /**
     * Courbes d'un département, une valeur par décennie : une ligne par série sur un cadre sobre. Le tracé est
     * décoratif (aria-hidden) ; tous les textes (axes, valeurs finales) sont en HTML pour rester lisibles à toute
     * taille, et la page double chaque graphique d'un tableau (FiguresTable). `summary` est lu par les lecteurs
     * d'écran à la place du dessin.
     *
     * Props : `rows` [{ year, label, values (une par série) }], `series` [{ label, tone, end }] (tone : a à e,
     * `end` : valeur finale écrite), `caption`, `summary`, `maxLabel` (texte du haut de l'axe, déjà formaté),
     * `max` (valeur du haut de l'axe).
     */
    import { curvePoints } from '$lib/departmentSheet.js';

    /** @type {{ rows: Array<{ year: number, label: string, values: number[] }>, series: Array<{ label: string, tone: string, end: string }>, caption: string, summary: string, maxLabel: string, max: number }} */
    let { rows, series, caption, summary, maxLabel, max } = $props();

    const lines = $derived(
        series.map((s, i) => ({
            ...s,
            points: curvePoints(
                rows.map((r) => r.values[i] ?? 0),
                max,
            ),
        })),
    );
    // Années écrites sous l'axe : la première, la dernière et quelques-unes entre, pour ne rien chevaucher.
    const ticks = $derived(
        rows.filter((_, i) => i === 0 || i === rows.length - 1 || (rows.length > 6 && i % 5 === 0)),
    );
    const left = (i) => `${(rows.indexOf(ticks[i]) / Math.max(1, rows.length - 1)) * 100}%`;
</script>

<figure class="curve">
    <figcaption>{caption}</figcaption>
    <ul class="legend">
        {#each lines as s (s.label)}
            <li>
                <span class="swatch tone-{s.tone}" aria-hidden="true"></span>{s.label}
                <span class="end">{s.end}</span>
            </li>
        {/each}
    </ul>
    <p class="vff-sr-only">{summary}</p>
    <div class="frame" aria-hidden="true">
        <span class="ymax">{maxLabel}</span>
        <span class="yzero">0</span>
        <svg viewBox="0 0 100 40" preserveAspectRatio="none" focusable="false">
            <line class="grid" x1="0" y1="0" x2="100" y2="0" />
            <line class="grid" x1="0" y1="20" x2="100" y2="20" />
            <line class="grid base" x1="0" y1="40" x2="100" y2="40" />
            {#each lines as s (s.label)}
                <polyline class="tone-{s.tone}" points={s.points} />
            {/each}
        </svg>
        <div class="xaxis">
            {#each ticks as t, i (t.year)}
                <span style:left={left(i)} class={{ first: i === 0, last: i === ticks.length - 1 }}
                    >{t.label}</span
                >
            {/each}
        </div>
    </div>
</figure>

<style>
    .curve {
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
    .end {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .swatch {
        flex: none;
        width: 22px;
        height: 4px;
        border-radius: 2px;
        background-color: var(--tone);
    }
    .frame {
        position: relative;
        height: clamp(180px, 32vw, 300px);
        margin: var(--vff-space-5) 0 var(--vff-space-6) 0;
        padding: 0;
    }
    .frame svg {
        display: block;
        width: 100%;
        height: 100%;
        overflow: visible;
    }
    .grid {
        stroke: var(--vff-border);
        stroke-width: 1;
        vector-effect: non-scaling-stroke;
    }
    .grid.base {
        stroke: var(--vff-border-strong);
    }
    polyline {
        fill: none;
        stroke: var(--tone);
        stroke-width: 3;
        stroke-linejoin: round;
        stroke-linecap: round;
        vector-effect: non-scaling-stroke;
    }
    .ymax,
    .yzero {
        position: absolute;
        left: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
        background: var(--vff-bg);
        padding-inline: 4px;
        border-radius: var(--vff-radius-xs);
        line-height: 1.2;
    }
    .ymax {
        top: 0;
        transform: translateY(-110%);
    }
    .yzero {
        bottom: 0;
        transform: translateY(110%);
        display: none;
    }
    .xaxis {
        position: absolute;
        inset: 100% 0 auto 0;
        height: 1.8em;
        margin-top: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .xaxis span {
        position: absolute;
        transform: translateX(-50%);
        white-space: nowrap;
    }
    .xaxis span.first {
        transform: none;
    }
    .xaxis span.last {
        transform: translateX(-100%);
    }
    .tone-a {
        --tone: var(--vff-accent);
    }
    .tone-b {
        --tone: var(--vff-info);
    }
    .tone-c {
        --tone: var(--vff-border-control);
    }
    .tone-d {
        --tone: var(--vff-warning);
    }
    .tone-e {
        --tone: var(--vff-text-muted);
    }
</style>
