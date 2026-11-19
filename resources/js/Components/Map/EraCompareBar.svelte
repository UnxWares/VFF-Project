<script>
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import { swatchStyle } from '$lib/railStyle.js';
    import { compareSummary, orderPair } from '$lib/epochViews.js';

    /**
     * Comparaison de deux décennies, sous la carte (à la place de la frise) : choix des deux
     * décennies et résumé chiffré des différences, lu par les lecteurs d'écran à chaque
     * changement (zone d'état). Chiffres : GET /api/v1/eras/{from}/compare/{to}.
     *
     * Props : eras (décennies, croissantes), currentEra, from et to (bindables, from < to).
     */
    let { eras = [], currentEra = null, from = $bindable(), to = $bindable() } = $props();

    const uid = $props.id();
    const lang = $derived($i18n.language);
    const km = (n) => formatNumber(n, lang);
    const yearOption = (y) => (y === currentEra ? $i18n.t('maps.timeline.today') : String(y));
    const yearInline = (y) => (y === currentEra ? $i18n.t('maps.compare.today_inline') : String(y));

    /** Résumé de la paire (null si les chiffres ne sont pas disponibles). */
    async function loadSummary(a, b) {
        const response = await fetch(`/api/v1/eras/${a}/compare/${b}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return null;
        return compareSummary((await response.json()).data);
    }

    // Une requête par paire affichée ; seule la plus récente est rendue ({#await}).
    const request = $derived(loadSummary(from, to).catch(() => null));

    function announce(summary) {
        if (!summary) return $i18n.t('maps.compare.error');
        const value = (key) => km(summary.rows.find((r) => r.key === key).km);
        return $i18n.t('maps.compare.announce', {
            from,
            to: yearInline(to),
            opened: value('opened'),
            closed: value('closed'),
            removed: value('removed'),
            before: km(summary.openBefore),
            after: km(summary.openAfter),
        });
    }

    function pick(which, value) {
        const year = Number(value);
        const pair = which === 'from' ? orderPair(year, to, eras) : orderPair(from, year, eras);
        if (pair) [from, to] = pair;
    }
</script>

<section class="compare-bar" aria-label={$i18n.t('maps.compare.aria_label')}>
    <div class="pickers">
        <label for={`${uid}-from`}>{$i18n.t('maps.compare.from')}</label>
        <select
            id={`${uid}-from`}
            class="vff-select"
            value={from}
            onchange={(e) => pick('from', e.currentTarget.value)}
        >
            {#each eras as y (y)}
                <option value={y}>{yearOption(y)}</option>
            {/each}
        </select>
        <label for={`${uid}-to`}>{$i18n.t('maps.compare.to')}</label>
        <select
            id={`${uid}-to`}
            class="vff-select"
            value={to}
            onchange={(e) => pick('to', e.currentTarget.value)}
        >
            {#each eras as y (y)}
                <option value={y}>{yearOption(y)}</option>
            {/each}
        </select>
    </div>

    <div class="figures">
        {#await request}
            <p class="note">{$i18n.t('maps.compare.loading')}</p>
        {:then summary}
            {#if !summary}
                <p class="note">{$i18n.t('maps.compare.error')}</p>
            {:else}
                {#if summary.unchanged}
                    <p class="note">{$i18n.t('maps.compare.none')}</p>
                {:else}
                    <ul>
                        {#each summary.rows.filter((r) => r.key !== 'kept') as r (r.key)}
                            <li>
                                <i
                                    class="swatch"
                                    style={swatchStyle(r.color, r.dash, true)}
                                    aria-hidden="true"
                                ></i>
                                <span class="figure">{km(r.km)}&nbsp;km</span>
                                <span class="what">{$i18n.t(`maps.compare.${r.key}`)}</span>
                            </li>
                        {/each}
                    </ul>
                {/if}
                <p class="note">
                    {$i18n.t('maps.compare.balance', {
                        before: km(summary.openBefore),
                        after: km(summary.openAfter),
                        from,
                        to: yearInline(to),
                    })}
                </p>
            {/if}
        {/await}
    </div>

    <!-- Résumé en une phrase pour les lecteurs d'écran, annoncé à chaque nouvelle paire (zone
         présente dès le départ, seul son texte change). -->
    <p class="vff-sr-only" role="status">
        {#await request then summary}{announce(summary)}{/await}
    </p>
</section>

<style>
    .compare-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 32px;
        padding: 12px 16px;
        border-top: 1px solid var(--vff-border);
        background-color: var(--vff-bg-elevated);
    }
    .pickers {
        display: flex;
        flex: none;
        align-items: center;
        gap: 8px;
    }
    .pickers label {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
    }
    .pickers select {
        width: auto;
        min-width: 7.5rem;
    }
    .figures {
        flex: 1 1 360px;
        min-width: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
    }
    .figures ul {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 24px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .figures li {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .swatch {
        display: inline-block;
        flex: none;
        width: 22px;
        border-radius: 2px;
    }
    .figure {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .what {
        color: var(--vff-text-muted);
    }
    .note {
        margin: 4px 0 0;
        color: var(--vff-text-muted);
    }
    .figures > .note:first-child {
        margin-top: 0;
    }
    @media (max-width: 600px) {
        .compare-bar {
            gap: 8px;
            padding: 8px 12px;
        }
        .pickers {
            width: 100%;
        }
        .pickers select {
            flex: 1;
            min-width: 0;
        }
        .figures ul {
            gap: 2px 16px;
        }
    }
</style>
