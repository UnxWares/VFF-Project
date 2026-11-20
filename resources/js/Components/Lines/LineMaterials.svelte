<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { buildersText, servicePeriodText } from '$lib/materialFacts.js';
    import { periodsSummary, relationsList, sortByFirstPeriod } from '$lib/materialPeriods.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';

    /**
     * Section « Matériel roulant » de la fiche ligne (LineSheet::build, clé `materials`) : une tuile par
     * série, avec sa catégorie, son constructeur, ses années de service et, quand une source les date,
     * ses années de circulation sur la ligne (regroupées, cf. lib/materialPeriods.js). Les séries datées
     * viennent d'abord, de la plus ancienne à la plus récente. Montrée seulement s'il y a au moins une série.
     *
     * Constructeurs : trois noms au plus, puis « et N autres » (buildersText) ; la liste complète est
     * sur la fiche de la série.
     *
     * @type {{ materials: Array<{ slug: string, name: string, type: string, builder: string|null,
     *   builders?: string[], in_service_from: number|null, in_service_to: number|null, periods?: Array<any>, relations?: Array<any> }> }}
     */
    let { materials } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const sorted = $derived(sortByFirstPeriod(materials));
    const dated = $derived(materials.some((m) => m.periods?.length));
</script>

<SheetSection id="materials" title={t('lines.section.materials')}>
    <ul class="material-grid">
        {#each sorted as m (m.slug)}
            {@const service = servicePeriodText(m.in_service_from, m.in_service_to, t)}
            {@const builders = buildersText(m.builders, m.builder, t)}
            {@const onLine = m.periods?.length ? periodsSummary(m.periods, t) : null}
            {@const relations = relationsList(m.relations, m.periods, t)}
            <li>
                <Link class="vff-tile material-card" href="/materials/{m.slug}">
                    <strong>{m.name}</strong>
                    <span class="meta">{t(`materials.type.${m.type}`)}</span>
                    {#if builders}<span class="meta">{builders}</span>{/if}
                    {#if service}<span class="meta">{service}</span>{/if}
                    {#if onLine}<span class="on-line"
                            >{t('materials.periods.on_line', { periods: onLine })}</span
                        >{/if}
                    {#if relations.length}<span class="on-line"
                            >{t('materials.relations.on_line', {
                                relations: relations.join(' ; '),
                            })}</span
                        >{/if}
                </Link>
            </li>
        {/each}
    </ul>
    {#if dated}
        <p class="note">{t('materials.periods.note')}</p>
    {/if}
</SheetSection>

<style>
    .material-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .material-grid :global(.material-card) {
        display: flex;
        flex-direction: column;
        gap: 2px;
        height: 100%;
    }
    .meta,
    .on-line {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .on-line {
        margin-top: var(--vff-space-2);
        font-weight: 600;
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
</style>
