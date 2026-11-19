<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        categoryColor,
        categorySwatch,
        decadesLegend,
        formatEventDate,
        formatKm,
        maxKnownLength,
        phaseStrip,
    } from '$lib/linePhases.js';
    import { formatStationDate } from '$lib/stationDates.js';
    import { firstOfMonth, formatLocale, tieUnits } from '$lib/typo.js';

    /**
     * État de la ligne au 31 décembre de chaque décennie cartographiée (au jour même pour la
     * décennie en cours), avec les mêmes règles que les phases (une voie déposée reste comptée
     * déposée), et ses gares (station_eras, la source de la carte et des fiches gares).
     * Les années identiques qui se suivent (mêmes longueurs, mêmes comptes de gares, aucune
     * fermeture) sont regroupées sur une ligne « 1890 à 2010 ». Le bouton « Carte » affiche la
     * phase en vigueur à cette date, et son nom accessible dit laquelle. Chaque gare comptée fermée
     * pour la première fois à une date est nommée (LineStations::closureEntries), même sans date
     * retenue : l'intervalle de la frise, et « rouverte ensuite » pour une fermeture provisoire.
     * Le ruban de composition a sa légende sous le tableau et son texte pour les lecteurs d'écran.
     */
    let { decades = [], phases = [], onselect = () => {} } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));
    const km = (m) => tieUnits(formatKm(m, formatLocale(lang)));
    const scale = $derived(
        Math.max(maxKnownLength(phases), ...decades.map((d) => sum(d.length_by_category))),
    );
    const legend = $derived(decadesLegend(decades));
    const catLabel = (cat) => t(`lines.categories.${cat}`);

    function sum(o) {
        return Object.values(o ?? {}).reduce((a, b) => a + b, 0);
    }
    function pick(o, keys) {
        return keys.reduce((a, k) => a + (o?.[k] ?? 0), 0);
    }
    /** Dernière phase de la décennie ou d'avant : l'état de la ligne à cette décennie. */
    function phaseAt(year) {
        let idx = null;
        phases.forEach((p, i) => {
            if (p.to_year <= year) idx = i;
        });
        return idx;
    }
    /** Signature d'une décennie (longueurs à 100 m près, comptes de gares) pour regrouper. */
    function signature(d) {
        const lens = Object.entries(d.length_by_category ?? {})
            .filter(([, m]) => m > 0)
            .map(([c, m]) => `${c}:${Math.round(m / 100)}`)
            .sort()
            .join(',');
        const st = d.stations ?? {};
        return `${lens}|${st.open ?? 0}/${st.closed ?? 0}/${st.uncertain ?? 0}|${phaseAt(d.year)}`;
    }

    const rows = $derived.by(() => {
        const out = [];
        for (const d of decades) {
            const prev = out.at(-1);
            if (
                prev &&
                !d.is_current &&
                !prev.is_current &&
                !(d.stations?.closures ?? []).length &&
                signature(d) === prev.sig
            ) {
                prev.to = d.year;
                continue;
            }
            out.push({ ...d, from: d.year, to: d.year, sig: signature(d) });
        }
        return out;
    });

    function rowLabel(r) {
        if (r.is_current) return t('lines.decades.today');
        return r.from === r.to ? String(r.from) : t('lines.sheet.decades.span', r);
    }
    /** « 26 mai 1991 », « entre 1931 et 1940, rouverte ensuite » (fermeture provisoire). */
    function closureDate(c) {
        const date = firstOfMonth(
            formatStationDate(
                c.closed_at,
                c.closed_precision,
                c.closed_after,
                $i18n.t,
                formatLocale(lang),
            ),
            lang,
        );
        return c.reopened ? `${date}, ${t('lines.sheet.decades.reopened')}` : date;
    }
    function seeLabel(r, idx) {
        const p = phases[idx];
        const date = p
            ? firstOfMonth(formatEventDate(p.date, p.precision, formatLocale(lang)), lang)
            : '';
        return t('lines.sheet.decades.see_label', { year: rowLabel(r), date });
    }
</script>

<!-- Cadre défilant focalisable : le tableau reste lisible au clavier sur téléphone (WCAG 2.1.1). -->
<!-- svelte-ignore a11y_no_noninteractive_tabindex -->
<div class="table-wrap" role="region" aria-labelledby="decades-title" tabindex="0">
    <table class="decades">
        <thead>
            <tr>
                <th scope="col" class="sticky">{t('lines.sheet.decades.col_year')}</th>
                <th scope="col" class="strip-col">{t('lines.decades.col_state')}</th>
                <th scope="col" class="num">{t('lines.decades.col_active')}</th>
                <th scope="col" class="num">{t('lines.decades.col_closed')}</th>
                <th scope="col" class="num">{t('lines.decades.col_gone')}</th>
                <th scope="col" class="num">{t('lines.sheet.decades.col_open')}</th>
                <th scope="col" class="num">{t('lines.sheet.decades.col_closed')}</th>
                <th scope="col" class="num">{t('lines.sheet.decades.col_uncertain')}</th>
                <th scope="col">{t('lines.sheet.decades.col_closures')}</th>
                <th scope="col"><span class="vff-sr-only">{t('lines.decades.col_map')}</span></th>
            </tr>
        </thead>
        <tbody>
            {#each rows as r (r.from)}
                {@const strip = phaseStrip({ length_by_category: r.length_by_category }, scale)}
                {@const closed = pick(r.length_by_category, [
                    'closed',
                    'velorail',
                    'service_track',
                    'construction',
                ])}
                {@const gone = pick(r.length_by_category, ['dismantled', 'razed', 'gone'])}
                {@const st = r.stations ?? {}}
                {@const hasSt = (st.open ?? 0) + (st.closed ?? 0) + (st.uncertain ?? 0) > 0}
                {@const target = phaseAt(r.to)}
                <tr class={{ current: r.is_current }}>
                    <th scope="row" class="sticky">{rowLabel(r)}</th>
                    <td class="strip-col">
                        <span class="strip" aria-hidden="true">
                            {#each strip as s (s.cat)}
                                <span
                                    class="seg cat-{s.cat}"
                                    style:width="{s.pct}%"
                                    style:--c={categoryColor(s.cat)}
                                ></span>
                            {/each}
                        </span>
                        <span class="vff-sr-only"
                            >{strip.map((s) => `${catLabel(s.cat)} ${km(s.m)}`).join(', ')}</span
                        >
                    </td>
                    <td class="num">{r.active_m ? km(r.active_m) : '-'}</td>
                    <td class="num">{closed ? km(closed) : '-'}</td>
                    <td class="num">{gone ? km(gone) : '-'}</td>
                    <td class="num">{hasSt ? (st.open ?? 0) : '-'}</td>
                    <td class="num">{hasSt ? (st.closed ?? 0) : '-'}</td>
                    <td class="num">{hasSt && st.uncertain ? st.uncertain : '-'}</td>
                    <td class="closures">
                        {#each (st.closures ?? []).slice(0, 4) as c, n (c.slug)}<Link
                                href="/stations/{c.slug}">{c.name}</Link
                            >
                            <span class="when">({closureDate(c)})</span>{n <
                            Math.min((st.closures ?? []).length, 4) - 1
                                ? ', '
                                : ''}{/each}{#if (st.closures ?? []).length > 4}, {t(
                                'lines.decades.more_closures',
                                { count: st.closures.length - 4 },
                            )}{/if}
                    </td>
                    <td>
                        {#if target != null}
                            <button
                                type="button"
                                class="see"
                                onclick={() => onselect(target)}
                                aria-label={seeLabel(r, target)}
                                ><i class="bi bi-map" aria-hidden="true"></i>
                                {t('lines.sheet.decades.see')}</button
                            >
                        {/if}
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>
{#if legend.length}
    <ul class="legend" aria-label={t('lines.sheet.decades.legend')}>
        {#each legend as cat (cat)}
            <li><i class="swatch" style={categorySwatch(cat)}></i>{catLabel(cat)}</li>
        {/each}
    </ul>
{/if}
<p class="hint">{t('lines.sheet.decades.scroll_hint')}</p>
<p class="foot">{t('lines.sheet.decades.uncertain_note')}</p>

<style>
    .table-wrap {
        position: relative;
        max-width: 100%;
        overflow-x: auto;
        border-top: 1px solid var(--vff-border-strong);
    }
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    th,
    td {
        padding: 9px 12px 9px 0;
        border-bottom: 1px solid var(--vff-border);
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }
    thead th {
        vertical-align: bottom;
        font-weight: 600;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        white-space: normal;
        min-width: 5.5em;
    }
    thead th.strip-col {
        min-width: 140px;
    }
    tbody th {
        font-family: var(--vff-font-display);
        font-size: 16px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    /* Première colonne fixe : on sait toujours de quelle date parle la ligne en faisant défiler. */
    .sticky {
        position: sticky;
        left: 0;
        z-index: 1;
        padding-left: 0;
        padding-right: 16px;
        background: var(--vff-bg);
    }
    tr.current th {
        color: var(--vff-accent-ink);
    }
    .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .strip-col {
        width: 34%;
        min-width: 140px;
    }
    .strip {
        display: flex;
        height: 8px;
        overflow: hidden;
        border-radius: var(--vff-radius-xs);
    }
    .seg {
        min-width: 1px;
        background: var(--c);
    }
    .seg.cat-closed,
    .seg.cat-construction {
        background: repeating-linear-gradient(90deg, var(--c) 0 5px, transparent 5px 8px);
    }
    .seg.cat-dismantled,
    .seg.cat-razed,
    .seg.cat-gone,
    .seg.cat-velorail {
        background: repeating-linear-gradient(90deg, var(--c) 0 2px, transparent 2px 5px);
    }
    td.closures {
        min-width: 14em;
        font-size: var(--vff-fs-xs);
        line-height: 1.45;
        white-space: normal;
    }
    td.closures :global(a) {
        color: var(--vff-text);
    }
    td.closures .when {
        color: var(--vff-text-muted);
    }
    .see {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 32px;
        padding: 4px 10px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        cursor: pointer;
    }
    .see:hover {
        border-color: var(--vff-accent-ink);
        color: var(--vff-accent-ink);
    }
    /* Légende du ruban de composition : mêmes traits que la carte et le rail de l'en-tête. */
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 20px;
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .legend li {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .swatch {
        display: inline-block;
        flex-shrink: 0;
        width: 18px;
        border-radius: 2px;
    }
    .hint {
        display: none;
        margin: 8px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .foot {
        max-width: var(--vff-measure);
        margin: 10px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    @media (max-width: 1180px) {
        .hint {
            display: block;
        }
    }
    @media (max-width: 600px) {
        .strip-col,
        thead th.strip-col {
            min-width: 96px;
        }
    }
</style>
