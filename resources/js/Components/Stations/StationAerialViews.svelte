<script>
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Vues aériennes historiques de la gare (IGN, BD ORTHO historique, Licence Ouverte 2.0), servies par le relais
     * `/media/aerial/{gare}/{période}.jpg` (AerialImages). Une période hors couverture répond 404 : sa vue est
     * retirée au chargement, et la section entière disparaît si aucune ne reste.
     */
    /** @type {{ views?: Array<{ period: string, from: number, to: number, url: string, attribution: string, license: string, license_url: string, source_url: string }>, name: string }} */
    let { views = [], name } = $props();

    const t = (key, opts) => String($i18n.t(key, opts));

    /** Périodes dont l'image n'a pas pu être chargée. */
    let missing = $state(new Set());
    const shown = $derived(views.filter((v) => !missing.has(v.period)));

    function drop(period) {
        missing = new Set([...missing, period]);
    }
</script>

{#if shown.length > 0}
    <SheetSection id="aerial" title={t('stations.aerial.title')} intro={t('stations.aerial.intro')}>
        <ul class="views">
            {#each shown as v (v.period)}
                <li>
                    <figure>
                        <img
                            src={v.url}
                            alt={t('stations.aerial.alt', { name, from: v.from, to: v.to })}
                            width="640"
                            height="384"
                            loading="lazy"
                            decoding="async"
                            onerror={() => drop(v.period)}
                        />
                        <figcaption>
                            <span class="period">{v.from}-{v.to}</span>
                            <span class="credit"
                                >{t('stations.aerial.credit', { from: v.from, to: v.to })},
                                <a
                                    href={v.license_url}
                                    target="_blank"
                                    rel="noopener noreferrer license">{v.license}</a
                                >,
                                <a href={v.source_url} target="_blank" rel="noopener noreferrer"
                                    >{t('stations.aerial.compare')}</a
                                ></span
                            >
                        </figcaption>
                    </figure>
                </li>
            {/each}
        </ul>
    </SheetSection>
{/if}

<style>
    .views {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
        gap: var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    figure {
        margin: 0;
    }
    img {
        display: block;
        width: 100%;
        height: auto;
        aspect-ratio: 5 / 3;
        object-fit: cover;
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-muted);
    }
    figcaption {
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .period {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }
</style>
