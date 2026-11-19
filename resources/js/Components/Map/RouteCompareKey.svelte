<script>
    import { i18n } from '$lib/i18n.js';
    import { formatLocale } from '$lib/typo.js';
    import { ROUTE_STYLES } from '$lib/routeCompare.js';

    /**
     * Légende des deux trajets d'une comparaison d'époques : pour chacune, l'échantillon du tracé
     * (mêmes couleurs et motif que sur la carte), l'époque et la longueur, ou « trajet
     * impossible ». `compact` : version posée sur la carte (sans longueurs, mais « trajet
     * impossible » reste écrit).
     */
    let { comparison, compact = false, label = '' } = $props();

    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const km = (m) =>
        (m / 1000).toLocaleString(formatLocale($i18n.language), { maximumFractionDigits: 0 });

    const rows = $derived(
        ['earlier', 'later'].map((key) => ({
            key,
            side: comparison[key],
            style: ROUTE_STYLES[key],
        })),
    );
</script>

<ul class={['route-key', { compact }]} aria-label={label || undefined}>
    {#each rows as row (row.key)}
        <li>
            <svg class="swatch" width="40" height="16" viewBox="0 0 40 16" aria-hidden="true">
                <line
                    x1="4"
                    y1="8"
                    x2="36"
                    y2="8"
                    stroke={row.style.casing}
                    stroke-width={7 * row.style.casingScale}
                    stroke-linecap="round"
                />
                <line
                    x1="4"
                    y1="8"
                    x2="36"
                    y2="8"
                    stroke={row.style.core}
                    stroke-width="3"
                    stroke-dasharray={row.style.dash
                        ? row.style.dash.map((n) => n * 3).join(' ')
                        : undefined}
                    stroke-linecap={row.style.dash ? 'butt' : 'round'}
                />
            </svg>
            <span class="era">
                {row.side.era_is_current
                    ? t('itinerary.era_current')
                    : t('itinerary.era_network', { era: row.side.era_year })}
            </span>
            {#if !compact || !row.side.route}
                <span class={['figure', { missing: !row.side.route }]}>
                    {row.side.route
                        ? t('itinerary.distance', { km: km(row.side.route.distance_m) })
                        : t('itinerary.compare.impossible')}
                </span>
            {/if}
        </li>
    {/each}
</ul>

<style>
    .route-key {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    li {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 10px;
    }
    .swatch {
        flex: none;
    }
    .era {
        font-weight: 600;
        color: var(--vff-text);
    }
    .figure {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .figure.missing {
        color: var(--vff-danger);
    }
    .compact {
        gap: 4px;
        font-size: var(--vff-fs-xs);
    }
</style>
