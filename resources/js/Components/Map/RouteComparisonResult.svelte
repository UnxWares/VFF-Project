<script>
    import { Link } from '@inertiajs/svelte';
    import RouteCompareKey from './RouteCompareKey.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatLocale } from '$lib/typo.js';
    import { lineStateKey, sideLines, summarySentences } from '$lib/routeCompare.js';

    /**
     * Résultat d'une comparaison de trajet entre deux époques (GET /api/v1/route/compare) :
     * légende des deux tracés, résumé en phrases, puis les lignes empruntées à chaque époque
     * avec ce qu'est devenue, à l'autre époque, chaque ligne propre à l'un des deux trajets.
     */
    /** @type {{ comparison: any, mapAnchor?: string | null, onClear?: () => void }} */
    let { comparison, mapAnchor = null, onClear = () => {} } = $props();

    /** @type {Array<'earlier' | 'later'>} */
    const SIDES = ['earlier', 'later'];

    const uid = $props.id();
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));
    const km = (m) =>
        (m / 1000).toLocaleString(formatLocale($i18n.language), {
            maximumFractionDigits: m < 10000 ? 1 : 0,
        });
    const when = (side) =>
        side.era_is_current
            ? t('itinerary.compare.today')
            : t('itinerary.compare.in_year', { year: side.era_year });

    const sentences = $derived(summarySentences(comparison, t, km));
    const sides = $derived(
        SIDES.map((key) => ({
            key,
            side: comparison[key],
            other: comparison[key === 'earlier' ? 'later' : 'earlier'],
            lines: sideLines(comparison, key),
        })),
    );
    const trip = $derived({
        from: comparison.from?.name ?? comparison.earlier.route?.from.name ?? '',
        to: comparison.to?.name ?? comparison.earlier.route?.to.name ?? '',
    });
</script>

<div class="comparison">
    <div class="summary">
        {#if trip.from && trip.to}
            <p class="trip">
                <strong>{trip.from}</strong>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                <span class="vff-sr-only">-</span>
                <strong>{trip.to}</strong>
            </p>
        {/if}
        <RouteCompareKey {comparison} label={t('itinerary.compare.key_label')} />
        <div class="sentences">
            {#each sentences as sentence, i (i)}
                <p>{sentence}</p>
            {/each}
        </div>
        {#if mapAnchor}
            <a class="vff-link map-link" href={`#${mapAnchor}`}>
                <i class="bi bi-map" aria-hidden="true"></i>
                {t('itinerary.compare.see_on_map')}
            </a>
        {/if}
    </div>

    <div class="sides">
        {#each sides as s (s.key)}
            <section class="side" aria-labelledby={`${uid}-${s.key}`}>
                <h3 id={`${uid}-${s.key}`}>
                    {t('itinerary.compare.lines_title', { when: when(s.side) })}
                </h3>
                {#if s.side.route}
                    <ol class="legs">
                        {#each s.lines as line, i (i)}
                            {@const state = lineStateKey(s.key, line.otherEra)}
                            <li>
                                <span class="leg-km"
                                    >{t('itinerary.distance', { km: km(line.length_m) })}</span
                                >
                                <span class="leg-body">
                                    {#if line.line_slug}
                                        <Link href={`/lines/${line.line_slug}`}
                                            >{line.line_name}</Link
                                        >
                                    {:else}
                                        <span>{line.line_name ?? '-'}</span>
                                    {/if}
                                    {#if state}
                                        <small class="state">
                                            {t(state, { when: when(s.other) })}
                                        </small>
                                    {/if}
                                </span>
                            </li>
                        {/each}
                    </ol>
                {:else}
                    <p class="none">{t('itinerary.compare.impossible')}</p>
                {/if}
            </section>
        {/each}
    </div>

    <div class="result-actions">
        <button type="button" class="vff-btn quiet" onclick={onClear}>
            {t('itinerary.clear')}
        </button>
    </div>
</div>

<style>
    .comparison {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--vff-border);
    }
    .summary {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .trip {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: 1.25rem;
        line-height: var(--vff-lh-heading);
    }
    .trip .bi {
        color: var(--vff-text-muted);
    }
    .sentences {
        max-width: var(--vff-measure);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
    }
    .sentences p {
        margin: 0;
    }
    .sentences p + p {
        margin-top: 4px;
    }
    .sides {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px 32px;
        margin-top: 24px;
    }
    h3 {
        margin: 0 0 8px;
    }
    .legs {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .legs li {
        display: grid;
        grid-template-columns: 5.5rem minmax(0, 1fr);
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .leg-km {
        font-weight: 600;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .leg-body {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .leg-body :global(a) {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
    }
    .state {
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .none {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-danger);
    }
    .result-actions {
        margin-top: 12px;
        margin-left: -8px;
    }

    @media (max-width: 900px) {
        .sides {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        .legs li {
            grid-template-columns: 4.5rem minmax(0, 1fr);
        }
    }
</style>
