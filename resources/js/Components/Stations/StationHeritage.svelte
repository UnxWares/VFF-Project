<script>
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import { builtText, headline, visibleItems } from '$lib/stationHeritage.js';
    import { i18n } from '$lib/i18n.js';

    /**
     * Patrimoine de la gare : une phrase par objet (bâtiment voyageurs, ouvrages), la construction et le style, la
     * source avec son lien (notice Mérimée ou élément Wikidata), et l'image libre de Wikimedia Commons quand Wikidata
     * en donne une. Rien n'est affiché quand VFF ne connaît aucun patrimoine pour la gare (`heritage` nul).
     */
    /** @type {{ heritage?: null | { items: Array<Record<string, any>> }, name: string }} */
    let { heritage = null, name } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));
    const items = $derived(visibleItems(heritage));
    const image = $derived(items.find((i) => i.image)?.image ?? null);

    /** Détails sous la phrase : construction, style. */
    const details = (item) =>
        [
            builtText(item, t),
            item.style ? t('stations.heritage.style', { style: item.style }) : null,
        ].filter(Boolean);
    const refLabel = (item) =>
        item.source === 'merimee'
            ? t('stations.heritage.source_merimee', { ref: item.reference.id })
            : t('stations.heritage.source_wikidata', { ref: item.reference.id });
</script>

{#if items.length > 0}
    <SheetSection id="heritage" title={t('stations.heritage.title')}>
        <div class={['heritage', { solo: !image }]}>
            <ul class="items">
                {#each items as item, i (i)}
                    <li>
                        <p class="headline">{headline(item, t)}</p>
                        {#if details(item).length}
                            <p class="detail">{details(item).join(' · ')}</p>
                        {/if}
                        <p class="source">
                            {#if item.reference.url}
                                <a
                                    href={item.reference.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{refLabel(item)}<i
                                        class="bi bi-box-arrow-up-right vff-ext"
                                        aria-hidden="true"
                                    ></i><span class="vff-sr-only">
                                        ({t('common.new_tab')})</span
                                    ></a
                                >
                            {:else}
                                {refLabel(item)}
                            {/if}
                        </p>
                    </li>
                {/each}
            </ul>
            {#if image}
                <figure class="photo">
                    <img
                        src={commonsSrc(image.url)}
                        alt={t('stations.heritage.image_alt', { name })}
                        loading="lazy"
                        decoding="async"
                    />
                    <figcaption>
                        <a href={image.source_url} target="_blank" rel="noopener noreferrer"
                            >{t('stations.heritage.image_credit', {
                                credit: [image.author, image.license].filter(Boolean).join(', '),
                            })}</a
                        >
                    </figcaption>
                </figure>
            {/if}
        </div>
    </SheetSection>
{/if}

<style>
    .heritage {
        display: grid;
        gap: var(--vff-space-4);
        max-width: 960px;
    }
    @media (min-width: 760px) {
        .heritage:not(.solo) {
            grid-template-columns: minmax(0, 1fr) minmax(240px, 340px);
            align-items: start;
        }
    }
    .items {
        display: grid;
        gap: var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .headline {
        margin: 0;
        font-size: var(--vff-fs-body);
        color: var(--vff-text);
    }
    .detail,
    .source {
        margin: var(--vff-space-1) 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .photo {
        margin: 0;
    }
    .photo img {
        display: block;
        width: 100%;
        height: auto;
        border-radius: var(--vff-radius-md);
    }
    figcaption {
        margin-top: var(--vff-space-1);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
</style>
