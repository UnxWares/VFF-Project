<script>
    import { i18n } from '$lib/i18n.js';
    import { categoryColor, formatKm, maxKnownLength, phaseStrip } from '$lib/linePhases.js';
    import { formatStationDate } from '$lib/stationDates.js';

    /**
     * Décennie par décennie : l'état de la ligne au 31 décembre de chaque décennie
     * cartographiée (au jour même pour la décennie en cours), avec les mêmes règles que
     * les phases (une voie déposée reste comptée déposée), et ses gares (station_eras,
     * la source de la carte et des fiches gares). « Voir » affiche cet état sur la carte.
     */
    let { decades = [], phases = [], onselect = () => {} } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const scale = $derived(
        Math.max(maxKnownLength(phases), ...decades.map((d) => sum(d.length_by_category))),
    );

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
</script>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th scope="col">{$i18n.t('lines.decades.col_decade')}</th>
                <th scope="col" class="strip-col">{$i18n.t('lines.decades.col_state')}</th>
                <th scope="col" class="num">{$i18n.t('lines.decades.col_active')}</th>
                <th scope="col" class="num">{$i18n.t('lines.decades.col_closed')}</th>
                <th scope="col" class="num">{$i18n.t('lines.decades.col_gone')}</th>
                <th scope="col" class="num">{$i18n.t('lines.decades.col_stations')}</th>
                <th scope="col">{$i18n.t('lines.decades.col_closures')}</th>
                <th scope="col"><span class="sr-only">{$i18n.t('lines.decades.col_map')}</span></th>
            </tr>
        </thead>
        <tbody>
            {#each decades as d (d.year)}
                {@const strip = phaseStrip({ length_by_category: d.length_by_category }, scale)}
                {@const closed = pick(d.length_by_category, [
                    'closed',
                    'velorail',
                    'service_track',
                    'construction',
                ])}
                {@const gone = pick(d.length_by_category, ['dismantled', 'razed', 'gone'])}
                {@const st = d.stations ?? {}}
                {@const target = phaseAt(d.year)}
                <tr class:current={d.is_current}>
                    <th scope="row">{d.is_current ? $i18n.t('lines.decades.today') : d.year}</th>
                    <td class="strip-col">
                        <span class="strip" aria-hidden="true">
                            {#each strip as s (s.cat)}
                                <span
                                    class="seg cat-{s.cat}"
                                    style="width: {s.pct}%; --c: {categoryColor(s.cat)}"
                                ></span>
                            {/each}
                        </span>
                    </td>
                    <td class="num">{d.active_m ? formatKm(d.active_m, lang) : '-'}</td>
                    <td class="num">{closed ? formatKm(closed, lang) : '-'}</td>
                    <td class="num">{gone ? formatKm(gone, lang) : '-'}</td>
                    <td class="num stations">
                        {#if (st.open ?? 0) + (st.closed ?? 0) + (st.uncertain ?? 0) > 0}
                            <span title={$i18n.t('lines.decades.stations_open_hint')}
                                >{st.open ?? 0}</span
                            >
                            <span
                                class="closed"
                                title={$i18n.t('lines.decades.stations_closed_hint')}
                                >/ {st.closed ?? 0}</span
                            >
                            {#if st.uncertain}<span
                                    class="uncertain"
                                    title={$i18n.t('lines.decades.stations_uncertain_hint')}
                                    >?{st.uncertain}</span
                                >{/if}
                        {:else}
                            -
                        {/if}
                    </td>
                    <td class="closures">
                        {#each (st.closures ?? []).slice(0, 4) as c, n (c.slug)}<a
                                href="/stations/{c.slug}">{c.name}</a
                            >
                            <span class="when"
                                >({formatStationDate(
                                    c.closed_at,
                                    c.closed_precision,
                                    c.closed_after,
                                    $i18n.t,
                                    lang,
                                )})</span
                            >{n < Math.min((st.closures ?? []).length, 4) - 1
                                ? ', '
                                : ''}{/each}{#if (st.closures ?? []).length > 4}, {$i18n.t(
                                'lines.decades.more_closures',
                                { count: st.closures.length - 4 },
                            )}{/if}
                    </td>
                    <td>
                        {#if target != null}
                            <button type="button" class="see" onclick={() => onselect(target)}
                                >{$i18n.t('lines.decades.see')}</button
                            >
                        {/if}
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>
<p class="foot">{$i18n.t('lines.decades.stations_legend')}</p>

<style>
    .table-wrap {
        overflow-x: auto;
        /* Repère des éléments absolus (libellé .sr-only de la dernière colonne) : sans lui,
           ils échappaient au défilement et élargissaient la page (692 px pour 360 sur téléphone). */
        position: relative;
        background:
            linear-gradient(to right, var(--vff-bg) 30%, transparent) left / 28px 100% no-repeat
                local,
            linear-gradient(to left, var(--vff-bg) 30%, transparent) right / 28px 100% no-repeat
                local,
            radial-gradient(farthest-side at 0 50%, var(--vff-shadow-strong), transparent) left /
                12px 100% no-repeat scroll,
            radial-gradient(farthest-side at 100% 50%, var(--vff-shadow-strong), transparent)
                right / 12px 100% no-repeat scroll;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    th,
    td {
        padding: 7px 10px 7px 0;
        border-bottom: 1px solid var(--vff-border);
        text-align: left;
        white-space: nowrap;
    }
    thead th {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-size: 12.5px;
        color: var(--vff-text-muted);
    }
    tbody th {
        font-family: var(--vff-font-display);
        font-size: 16px;
        font-variant-numeric: tabular-nums;
    }
    tr.current th {
        color: var(--vff-accent-ink);
    }
    .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .strip-col {
        width: 40%;
        min-width: 140px;
    }
    .strip {
        display: flex;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
    }
    .seg {
        background: var(--c);
        min-width: 1px;
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
        white-space: normal;
        min-width: 12em;
        font-size: 13px;
        line-height: 1.45;
    }
    td.closures a {
        color: var(--vff-text);
    }
    td.closures .when {
        color: var(--vff-text-muted);
    }
    .stations .closed {
        color: var(--vff-text-muted);
        padding-left: 3px;
    }
    .stations .uncertain {
        color: #64748b;
        padding-left: 3px;
    }
    .see {
        border: 1px solid var(--vff-border-strong);
        background: transparent;
        color: var(--vff-text);
        border-radius: var(--vff-radius-sm);
        padding: 3px 9px;
        font-size: 12.5px;
        cursor: pointer;
    }
    @media (max-width: 768px) {
        .see {
            min-height: 28px;
        }
    }
    .see:hover {
        border-color: var(--vff-accent);
        color: var(--vff-accent-ink);
    }
    .see:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .foot {
        margin: 8px 0 0;
        font-size: 12.5px;
        color: var(--vff-text-muted);
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
    }
</style>
