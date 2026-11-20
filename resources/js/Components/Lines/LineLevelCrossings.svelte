<script>
    import { i18n } from '$lib/i18n.js';
    import { NBSP } from '$lib/typo.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { LEVEL_CROSSING_GROUPS } from '$lib/levelCrossingMap.js';

    /**
     * Section « Passages à niveau » de la fiche ligne (LineLevelCrossings::forSheet côté serveur) :
     * nombre de passages par type dans un petit tableau, puis la liste complète repliée (élément
     * natif `details`, trouvée par la recherche du navigateur) dans l'ordre des points
     * kilométriques. Données SNCF Réseau, état actuel seulement.
     *
     * @type {{ crossings: { total: number, summary: Array<{ group: string, count: number }>,
     *   items: Array<{ id: string, label: string|null, class: number|null, group: string|null,
     *   pk: string|null, commune: string|null, crossed_way: string|null }> } }}
     */
    let { crossings } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const SWATCH = Object.fromEntries(LEVEL_CROSSING_GROUPS.map((g) => [g.key, g.color]));

    const hasSecondary = $derived(crossings.summary.some((s) => s.group === 'secondary'));
    // Au plus quelques centaines de passages par ligne : le nombre n'a pas besoin de séparateur.
    const intro = $derived(
        t('level_crossings.line.intro', { count: crossings.total }) +
            (hasSecondary ? ` ${t('level_crossings.line.secondary_note')}` : ''),
    );
    // Le groupe est nul pour une classe absente ou hors de la liste officielle : pas de définition.
    const classLabel = (c) =>
        c.group === null
            ? t('level_crossings.line.unknown')
            : t(`level_crossings.classes.${c.class}`);
</script>

<SheetSection
    id="level-crossings"
    title={t('level_crossings.line.title')}
    count={crossings.total}
    {intro}
>
    <div class="crossings">
        <!-- Deux colonnes courtes : le tableau tient à 320 px, pas de défilement à rendre focalisable. -->
        <div class="vff-table-wrap summary">
            <table class="vff-table">
                <caption class="vff-sr-only">{t('level_crossings.line.summary_caption')}</caption>
                <thead>
                    <tr>
                        <th scope="col">{t('level_crossings.line.type')}</th>
                        <th scope="col" class="num">{t('level_crossings.line.count')}</th>
                    </tr>
                </thead>
                <tbody>
                    {#each crossings.summary as s (s.group)}
                        <tr>
                            <th scope="row">
                                <span class="group">
                                    {#if SWATCH[s.group]}<span
                                            class="dot"
                                            style:--dot={SWATCH[s.group]}
                                            aria-hidden="true"
                                        ></span>{/if}
                                    {t(`level_crossings.groups.${s.group}`)}
                                </span>
                            </th>
                            <td class="num">{s.count}</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <details class="list">
            <summary>
                {t('level_crossings.line.show_list', { count: crossings.total })}
            </summary>
            <!-- Cadre défilant : focalisable pour défiler au clavier (GUIDE 5.8). -->
            <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
            <div
                class="vff-table-wrap"
                role="region"
                aria-label={t('level_crossings.line.list_caption')}
                tabindex="0"
            >
                <table class="vff-table">
                    <caption class="vff-sr-only">{t('level_crossings.line.list_caption')}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="num">{t('level_crossings.line.pk')}</th>
                            <th scope="col">{t('level_crossings.line.name')}</th>
                            <th scope="col">{t('level_crossings.line.commune')}</th>
                            <th scope="col">{t('level_crossings.line.class')}</th>
                            <th scope="col">{t('level_crossings.line.crossed_way')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each crossings.items as c (c.id)}
                            <tr>
                                <td class="num pk">{c.pk ?? '-'}</td>
                                <th scope="row">{c.label ?? c.id}</th>
                                <td>{c.commune ?? t('level_crossings.line.unknown')}</td>
                                <td class="cls">
                                    {#if c.class !== null}<span class="cls-num"
                                            >{c.class === 0 ? '00' : c.class}{NBSP}</span
                                        >{/if}{classLabel(c)}
                                </td>
                                <td>{c.crossed_way ?? t('level_crossings.line.unknown')}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </details>

        <p class="source">{t('level_crossings.line.source')}</p>
    </div>
</SheetSection>

<style>
    .crossings {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-5);
    }
    .summary {
        width: auto;
        min-width: min(100%, 22rem);
    }
    .group {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    /* Même teinte que les points du calque « Passages à niveau » de la carte ; le texte dit le type. */
    .dot {
        flex-shrink: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: var(--dot);
        box-shadow: 0 0 0 1.5px var(--vff-bg-elevated);
    }
    .list {
        width: 100%;
    }
    .list summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .list summary:hover {
        background-color: var(--vff-bg-muted);
    }
    .list summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .list[open] summary {
        margin-bottom: var(--vff-space-4);
    }
    .pk {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .cls {
        min-width: 16rem;
    }
    .cls-num {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }
    .source {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
</style>
