<script>
    import { i18n } from '$lib/i18n.js';
    import { formatEventDate } from '$lib/linePhases.js';

    /**
     * Chronologie détaillée, portion par portion : TOUS les événements de la ligne (rien
     * n'est caché), avec leur statut (retenu pour dater la carte, concorde, diverge, sans
     * effet, écarté, non localisé) et leur appel de note. Données : App\Services\LineChronicle.
     * Description et citation arrivent dans la langue de la page (App\Support\EventText) ;
     * description_lang / quote_lang = « fr » pour ce qui reste une citation en français.
     */
    let { chronicle } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const byId = $derived(Object.fromEntries((chronicle?.events ?? []).map((e) => [e.id, e])));
    const groups = $derived(chronicle?.groups ?? []);
    // Portions désignées par un seul lieu (« près de X ») : regroupées quand elles sont nombreuses
    // (LGV : un tracé OpenHistoricalMap par tronçon), pour garder la chronique lisible.
    const isNear = (g) =>
        !['whole', 'traced', 'unspecified'].includes(g.key) && !g.key.includes('|');
    const nearGroups = $derived(groups.filter(isNear));
    const foldNear = $derived(nearGroups.length > 6);
    const mainGroups = $derived(foldNear ? groups.filter((g) => !isNear(g)) : groups);
    const named = $derived(
        mainGroups.filter((g) => !['traced', 'unspecified'].includes(g.key)).length,
    );

    function groupLabel(g) {
        if (g.key === 'whole') return $i18n.t('lines.chronicle.whole_line');
        if (g.key === 'traced') return $i18n.t('lines.chronicle.traced');
        if (g.key === 'unspecified') return $i18n.t('lines.chronicle.unspecified');
        if (g.from && g.to && g.from !== g.to)
            return $i18n.t('lines.event_section.between', { from: g.from, to: g.to });
        return $i18n.t('lines.event_section.near', { place: g.from ?? g.to });
    }

    function eventLabel(type) {
        const key = `lines.event_types.${type}`;
        const label = $i18n.t(key);
        return label === key ? type : label;
    }
</script>

<div class="chronicle">
    <p class="intro">{$i18n.t('lines.chronicle.intro')}</p>
    <dl class="legend">
        {#each ['retained', 'agrees', 'conflict', 'unused', 'ignored', 'unlocated', 'context'] as st (st)}
            <div>
                <dt>
                    <span class="badge st-{st}">{$i18n.t(`lines.chronicle.status.${st}`)}</span>
                </dt>
                <dd>{$i18n.t(`lines.chronicle.status_help.${st}`)}</dd>
            </div>
        {/each}
    </dl>

    {#snippet groupBlock(g, open)}
        <details class="group" {open}>
            <summary>
                <i class="bi bi-chevron-right chev" aria-hidden="true"></i>
                <span class="label">{groupLabel(g)}</span>
                {#if g.length_m}<span class="len"
                        >{(g.length_m / 1000).toLocaleString(lang, { maximumFractionDigits: 1 })} km</span
                    >{/if}
                <span class="count"
                    >{$i18n.t('lines.chronicle.event_count', { count: g.event_ids.length })}</span
                >
            </summary>
            <table>
                <thead>
                    <tr>
                        <th scope="col">{$i18n.t('lines.chronicle.col_date')}</th>
                        <th scope="col">{$i18n.t('lines.chronicle.col_fact')}</th>
                        <th scope="col">{$i18n.t('lines.chronicle.col_status')}</th>
                    </tr>
                </thead>
                <tbody>
                    {#each g.event_ids as id (id)}
                        {@const e = byId[id]}
                        {#if e}
                            <tr class="st-row-{e.status}">
                                <td class="date">{formatEventDate(e.date, e.precision, lang)}</td>
                                <td class="fact">
                                    <span class="badge st-{e.status} inline-status"
                                        >{$i18n.t(`lines.chronicle.status.${e.status}`)}</span
                                    >
                                    {eventLabel(e.type)}<sup
                                        ><a href="#note-{e.note}">[{e.note}]</a></sup
                                    >
                                    {#if e.description}<span class="desc" lang={e.description_lang}
                                            >{e.description}</span
                                        >{/if}
                                    {#if e.quote}<q class="quote" lang={e.quote_lang}>{e.quote}</q
                                        >{/if}
                                    {#if e.conflicts_with.length}
                                        <span class="vs">
                                            {$i18n.t('lines.chronicle.differs_from')}
                                            {#each e.conflicts_with as o, n (o)}
                                                {#if byId[o]}{formatEventDate(
                                                        byId[o].date,
                                                        byId[o].precision,
                                                        lang,
                                                    )}<sup
                                                        ><a href="#note-{byId[o].note}"
                                                            >[{byId[o].note}]</a
                                                        ></sup
                                                    >{n < e.conflicts_with.length - 1
                                                        ? ', '
                                                        : ''}{/if}
                                            {/each}
                                        </span>
                                    {/if}
                                </td>
                                <td
                                    ><span class="badge st-{e.status}"
                                        >{$i18n.t(`lines.chronicle.status.${e.status}`)}</span
                                    ></td
                                >
                            </tr>
                        {/if}
                    {/each}
                </tbody>
            </table>
        </details>
    {/snippet}

    {#each mainGroups as g (g.key)}
        {@render groupBlock(g, !['traced', 'unspecified'].includes(g.key) && named <= 8)}
    {/each}
    {#if foldNear}
        <details class="group near-fold">
            <summary>
                <i class="bi bi-chevron-right chev" aria-hidden="true"></i>
                <span class="label">{$i18n.t('lines.chronicle.near_groups')}</span>
                <span class="count"
                    >{$i18n.t('lines.chronicle.event_count', {
                        count: nearGroups.reduce((a, g) => a + g.event_ids.length, 0),
                    })}</span
                >
            </summary>
            <div class="nested">
                {#each nearGroups as g (g.key)}
                    {@render groupBlock(g, false)}
                {/each}
            </div>
        </details>
    {/if}
</div>

<style>
    .intro {
        max-width: 72ch;
        margin: 0 0 12px;
        color: var(--vff-text-muted);
        font-size: 14.5px;
        line-height: 1.55;
    }
    .legend {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 6px 18px;
        margin: 0 0 20px;
        font-size: 13px;
    }
    .legend div {
        display: flex;
        gap: 8px;
        align-items: baseline;
    }
    .legend dt {
        flex-shrink: 0;
    }
    .legend dd {
        margin: 0;
        color: var(--vff-text-muted);
    }
    .group {
        border-top: 1px solid var(--vff-border);
        padding: 4px 0;
    }
    .nested {
        padding-left: 18px;
    }
    .group:last-of-type {
        border-bottom: 1px solid var(--vff-border);
    }
    /* display:flex retire le triangle natif de <summary> : un chevron le remplace. */
    summary {
        display: flex;
        align-items: baseline;
        gap: 10px;
        padding: 10px 0;
        cursor: pointer;
        list-style: none;
    }
    summary::-webkit-details-marker {
        display: none;
    }
    .chev {
        flex-shrink: 0;
        align-self: center;
        font-size: 13px;
        color: var(--vff-text-muted);
        transition: transform var(--vff-transition);
    }
    details[open] > summary .chev {
        transform: rotate(90deg);
    }
    summary:hover .label {
        color: var(--vff-accent-ink);
    }
    summary:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .label {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-size: 16px;
    }
    .len,
    .count {
        color: var(--vff-text-muted);
        font-size: 13px;
    }
    .count {
        margin-left: auto;
    }
    table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        margin: 0 0 12px;
        font-size: 14px;
    }
    th {
        text-align: left;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        font-size: 12.5px;
        color: var(--vff-text-muted);
        padding: 4px 8px 6px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    td {
        vertical-align: top;
        padding: 8px 8px 8px 0;
        border-bottom: 1px dotted var(--vff-border);
    }
    td.date,
    th:nth-child(1) {
        width: 9.5em;
    }
    th:nth-child(3) {
        width: 11em;
    }
    td.date {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }
    td.fact {
        line-height: 1.5;
    }
    .desc {
        display: block;
        color: var(--vff-text-muted);
        font-size: 13px;
    }
    .quote {
        display: block;
        margin-top: 4px;
        padding-left: 10px;
        border-left: 2px solid var(--vff-border-strong);
        color: var(--vff-text-muted);
        font-size: 13px;
        font-style: italic;
    }
    .vs {
        display: block;
        margin-top: 2px;
        font-size: 13px;
        color: #b45309;
    }
    tr.st-row-ignored td,
    tr.st-row-unused td {
        color: var(--vff-text-muted);
    }
    sup a {
        color: var(--vff-accent-ink);
        text-decoration: none;
        font-size: 11px;
        padding-left: 1px;
    }
    .badge {
        display: inline-block;
        white-space: nowrap;
        font-family: var(--vff-font-heading);
        font-size: 11.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg-muted);
        color: var(--vff-text-muted);
    }
    .badge.st-retained {
        background: var(--vff-accent-soft);
        color: #b84800;
    }
    .badge.st-agrees {
        background: rgba(22, 163, 74, 0.12);
        color: #166534;
    }
    .badge.st-conflict {
        background: rgba(217, 119, 6, 0.14);
        color: #92400e;
    }
    /* Mode sombre : teintes claires (les foncées tombaient à 3:1 sur les fonds sombres). */
    :global([data-theme='dark']) .badge.st-retained {
        color: #fdba74;
    }
    :global([data-theme='dark']) .badge.st-agrees {
        color: #86efac;
    }
    :global([data-theme='dark']) .badge.st-conflict {
        color: #fcd34d;
    }
    .inline-status {
        display: none;
    }
    /* Téléphone : la colonne « Usage pour la fiche » disparaît, le statut passe dans la cellule du fait. */
    @media (max-width: 640px) {
        .inline-status {
            display: inline-block;
            margin: 0 6px 2px 0;
        }
        td.date {
            width: auto;
        }
        th:nth-child(3),
        td:nth-child(3) {
            display: none;
        }
    }
</style>
