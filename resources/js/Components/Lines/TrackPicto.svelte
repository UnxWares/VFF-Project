<script>
    /**
     * Pictogramme d'un genre d'objet du schéma des voies (tableau de parcours, légende), dessiné comme dans le
     * schéma : le tableau et la légende emploient les mêmes signes que le dessin. Décoratif : le libellé est
     * toujours écrit à côté.
     *
     * @type {{ kind: string, size?: number }}
     */
    let { kind, size = 22 } = $props();
</script>

<svg class="picto k-{kind}" width={size} height={size} viewBox="0 0 22 22" aria-hidden="true">
    {#if kind === 'op' || kind === 'zone'}
        <rect class="zone" x="2" y="5" width="18" height="12" rx="3" />
        <line class="main" x1="0" y1="11" x2="22" y2="11" />
    {:else if kind === 'platform'}
        <line class="main" x1="1" y1="7" x2="21" y2="7" />
        <rect class="platform" x="2" y="11" width="18" height="7" rx="2" />
    {:else if kind === 'travelled'}
        <line class="travelled" x1="1" y1="11" x2="21" y2="11" />
    {:else if kind === 'main'}
        <line class="main" x1="1" y1="11" x2="21" y2="11" />
    {:else if kind === 'service'}
        <line class="service" x1="1" y1="11" x2="21" y2="11" />
    {:else if kind === 'derived'}
        <line class="main" x1="1" y1="17" x2="21" y2="17" />
        <line class="derived" x1="7" y1="17" x2="15" y2="6" />
        <line class="service" x1="15" y1="6" x2="21" y2="6" />
    {:else if kind === 'speed'}
        <path class="stroke" d="M4 15 A7 7 0 1 1 18 15" />
        <line class="stroke" x1="11" y1="15" x2="15.5" y2="8.5" />
    {:else if kind === 'gradient'}
        <path class="stroke" d="M2 17 L20 6 L20 17 Z" />
    {:else if kind === 'signal'}
        <line class="main" x1="1" y1="17" x2="21" y2="17" />
        <line class="mast" x1="9" y1="17" x2="9" y2="6" />
        <circle class="head" cx="13" cy="6" r="3.5" />
    {:else if kind === 'switch'}
        <line class="main" x1="1" y1="15" x2="21" y2="15" />
        <line class="main" x1="8" y1="15" x2="17" y2="6" />
        <circle class="switch" cx="8" cy="15" r="3.5" />
    {:else if kind === 'crossing'}
        <line class="main" x1="3" y1="4" x2="19" y2="18" />
        <line class="main" x1="3" y1="18" x2="19" y2="4" />
    {:else if kind === 'level_crossing'}
        <line class="main" x1="1" y1="11" x2="21" y2="11" />
        <line class="road" x1="8" y1="3" x2="8" y2="19" />
        <line class="road" x1="14" y1="3" x2="14" y2="19" />
    {:else if kind === 'buffer_stop'}
        <line class="main" x1="1" y1="11" x2="16" y2="11" />
        <line class="buffer" x1="17" y1="5" x2="17" y2="17" />
    {:else if kind === 'milestone'}
        <path class="stroke" d="M7 19 V8 a4 4 0 0 1 8 0 V19 Z" />
    {:else if kind === 'missing'}
        <circle class="stroke" cx="11" cy="11" r="8" />
        <line class="stroke" x1="11" y1="6" x2="11" y2="12.5" />
        <circle class="dot" cx="11" cy="15.5" r="1.2" />
    {/if}
</svg>

<style>
    .picto {
        flex-shrink: 0;
        overflow: visible;
        vertical-align: middle;
        stroke-linecap: round;
    }
    .zone {
        fill: var(--tl-zone, var(--vff-bg-muted));
        stroke: var(--vff-border-strong);
    }
    .platform {
        fill: var(--tl-platform, var(--vff-bg-muted));
        stroke: var(--tl-platform-edge, var(--vff-border-strong));
        stroke-width: 1;
    }
    .main,
    .stroke,
    .mast {
        stroke: var(--vff-text);
        stroke-width: 2;
        fill: none;
    }
    .travelled {
        stroke: var(--vff-accent-strong);
        stroke-width: 4;
    }
    .derived {
        stroke: var(--vff-text-muted);
        stroke-width: 1.5;
        stroke-dasharray: 3 3;
    }
    .service {
        stroke: var(--vff-text-muted);
        stroke-width: 2;
        stroke-dasharray: 4 3;
    }
    .switch {
        fill: var(--vff-bg-elevated);
        stroke: var(--vff-text);
        stroke-width: 1.6;
    }
    .head {
        fill: var(--vff-info);
    }
    .mast {
        stroke: var(--vff-info);
    }
    .road {
        stroke: var(--vff-text-muted);
        stroke-width: 1.6;
    }
    .buffer {
        stroke: var(--vff-text);
        stroke-width: 3.5;
    }
    .dot {
        fill: var(--vff-text);
    }
    .k-missing .stroke,
    .k-missing .dot {
        stroke: var(--vff-danger);
    }
    .k-missing .dot {
        fill: var(--vff-danger);
    }
</style>
