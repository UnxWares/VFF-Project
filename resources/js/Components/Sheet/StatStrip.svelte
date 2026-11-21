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

<div class="stat-strip-wrap" role={label ? 'group' : undefined} aria-label={label}>
    <dl class="stat-strip {size}">
        {#each items as it, i (i)}
            <div
                class={['stat', { accent: it.tone === 'accent', muted: it.tone === 'muted' }]}
                title={it.hint}
            >
                <dt>{it.label}</dt>
                <dd>
                    <!-- Valeur en phrase : l'icône entre dans le texte, qui repasse sous elle à la ligne
                         au lieu de former un bloc décalé à droite de l'icône (téléphone). -->
                    {#if it.icon && !it.text}<i class="bi {it.icon}" aria-hidden="true"></i>{/if}
                    {#if it.value != null}<span class="value">{it.value}</span>{/if}
                    {#if it.text}<span class="text"
                            >{#if it.icon}<i class="bi {it.icon}" aria-hidden="true"
                                ></i>{/if}{it.text}</span
                        >{/if}
                    {#if it.note}<abbr class="note" title={it.noteHint}>{it.note}</abbr>{/if}
                </dd>
            </div>
        {/each}
    </dl>
</div>

<style>
    /* Séparateurs : filet à gauche de chaque chiffre ; la marge négative de la liste et le
       débordement masqué du cadre cachent le filet du premier chiffre de chaque rangée (plus de
       trait orphelin en bout de ligne quand la rangée passe à la ligne). */
    .stat-strip-wrap {
        overflow: hidden;
    }
    .stat-strip {
        display: flex;
        flex-wrap: wrap;
        row-gap: 16px;
        margin: 0 0 0 -29px;
        padding: 18px 0 20px;
    }
    .stat {
        /* Libellé (dt) sous la valeur (dd) à l'écran, dans l'ordre logique pour la lecture vocale. */
        display: flex;
        flex-direction: column-reverse;
        justify-content: flex-end;
        gap: 4px;
        min-width: 0;
        padding: 2px 28px;
        border-left: 1px solid var(--vff-border-strong);
    }
    dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.35;
        color: var(--vff-text-muted);
    }
    /* La valeur occupe toujours la hauteur d'un chiffre, calée en bas : une valeur en phrase
       (« Non électrifiée ») se pose au niveau des chiffres voisins et son libellé sur la même
       ligne que les leurs, au lieu de remonter en haut de la case. */
    dd {
        --value-size: 30px;
        display: flex;
        align-items: flex-end;
        gap: 8px;
        min-height: var(--value-size);
        margin: 0;
        color: var(--vff-text);
    }
    .value {
        font-family: var(--vff-font-display);
        font-size: var(--value-size);
        font-weight: 700;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    /* Valeur en phrase : même police et même graisse que les chiffres, un peu plus petite pour
       tenir dans la case. */
    .text {
        font-family: var(--vff-font-display);
        font-size: 22px;
        font-weight: 700;
        line-height: 1.15;
    }
    .note {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        text-decoration: underline dotted;
        text-underline-offset: 3px;
        cursor: help;
    }
    /* Icône d'une valeur en phrase : de la taille et de la couleur du texte qu'elle précède. */
    dd i {
        font-size: 20px;
        line-height: 1;
        color: inherit;
    }
    .text i {
        margin-right: 8px;
        vertical-align: -0.05em;
    }
    .stat.accent dd i,
    .stat.accent .text {
        color: var(--vff-accent-ink);
    }
    .stat.muted dd {
        color: var(--vff-text-muted);
    }
    .sm dd {
        --value-size: 22px;
    }
    .sm .text {
        font-size: 17px;
    }
    @media (max-width: 700px) {
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px;
            margin: 0;
            padding: 16px 0 18px;
        }
        /* Deux colonnes : le premier chiffre de chaque rangée s'aligne sur le bord gauche de la page
           (pas de filet orphelin), seul le filet de la deuxième colonne sépare les deux. */
        .stat {
            padding: 0;
            border-left: 0;
        }
        .stat:nth-child(even) {
            padding-left: 16px;
            border-left: 1px solid var(--vff-border-strong);
        }
        dd {
            --value-size: 24px;
        }
        .text {
            font-size: 19px;
        }
        /* Demi-largeur : l'icône (décorative) restait seule sur sa ligne au-dessus du texte. */
        .text i {
            display: none;
        }
    }
</style>
