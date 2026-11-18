<script>
    /**
     * Rangée de chiffres clés des fiches (ligne, gare, crédits) : une liste de
     * définitions, le chiffre en grand, son libellé dessous, séparés par des filets.
     * Chaque entrée : { value?, label, hint?, icon?, text?, tone? }
     *   - value : nombre ou date (déjà formaté dans la langue de la page) ;
     *   - text  : valeur en phrase (« Partiellement électrifiée (2 %) ») quand il n'y a pas de chiffre ;
     *   - hint  : infobulle (title) ;
     *   - note  : précision discrète après la valeur (« (déduite) »), noteHint = son explication ;
     *   - tone  : 'accent' (valeur orange, ex. électrifiée) ou 'muted' (état éteint : fermée, non électrifiée).
     */
    /** @type {{ items?: Array<{ value?: string|number|null, label: string, hint?: string, icon?: string, text?: string, note?: string, noteHint?: string, tone?: string }>, label?: string, size?: 'md'|'sm' }} */
    let { items = [], label = undefined, size = 'md' } = $props();
</script>

<dl class="stat-strip {size}" aria-label={label}>
    {#each items as it, i (i)}
        <div
            class="stat"
            class:accent={it.tone === 'accent'}
            class:muted={it.tone === 'muted'}
            title={it.hint}
        >
            <dt>{it.label}</dt>
            <dd>
                {#if it.icon}<i class="bi {it.icon}" aria-hidden="true"></i>{/if}
                {#if it.value != null}<span class="value">{it.value}</span>{/if}
                {#if it.text}<span class="text">{it.text}</span>{/if}
                {#if it.note}<abbr class="note" title={it.noteHint}>{it.note}</abbr>{/if}
            </dd>
        </div>
    {/each}
</dl>

<style>
    .stat-strip {
        display: flex;
        flex-wrap: wrap;
        margin: 0;
        padding: 18px 0 20px;
    }
    .stat {
        /* Libellé (dt) sous la valeur (dd) à l'écran, dans l'ordre logique pour la lecture vocale. */
        display: flex;
        flex-direction: column-reverse;
        justify-content: flex-end;
        gap: 4px;
        padding: 2px 28px 2px 0;
        margin: 0 28px 0 0;
        border-right: 1px solid var(--vff-border);
        min-width: 0;
    }
    .stat:last-child {
        border-right: 0;
        margin-right: 0;
        padding-right: 0;
    }
    dt {
        font-family: var(--vff-font-heading);
        font-size: 13px;
        line-height: 1.35;
        color: var(--vff-text-muted);
    }
    dd {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin: 0;
        color: var(--vff-text);
    }
    .value {
        font-family: var(--vff-font-display);
        font-size: 30px;
        font-weight: 700;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .text {
        font-family: var(--vff-font-heading);
        font-size: 15px;
        font-weight: 600;
        line-height: 1.3;
    }
    .note {
        font-family: var(--vff-font-heading);
        font-size: 12.5px;
        color: var(--vff-text-muted);
        text-decoration: underline dotted;
        text-underline-offset: 3px;
        cursor: help;
    }
    dd i {
        font-size: 20px;
        line-height: 1;
        align-self: center;
        color: var(--vff-text-muted);
    }
    .stat.accent dd i,
    .stat.accent .text {
        color: var(--vff-accent-ink);
    }
    .stat.muted .text,
    .stat.muted .value {
        color: var(--vff-text-muted);
    }
    .sm .value {
        font-size: 22px;
    }
    .sm .text {
        font-size: 14px;
    }
    @media (max-width: 700px) {
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px;
            padding: 16px 0 18px;
        }
        .stat {
            border-right: 0;
            margin: 0;
            padding: 0 0 0 12px;
            border-left: 2px solid var(--vff-border);
        }
        .value {
            font-size: 24px;
        }
    }
</style>
