<script>
    import { i18n } from '$lib/i18n.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import { firstOfMonth, formatLocale, formatNumber, tieUnits } from '$lib/typo.js';

    /**
     * Chronologie détaillée, portion par portion : TOUS les événements de la ligne (rien
     * n'est caché), avec leur statut (retenu pour dater la carte, concorde, diverge, sans
     * effet, écarté, non localisé) et leur appel de note. Données : App\Services\LineChronicle.
     * Description et citation arrivent dans la langue de la page (App\Support\EventText) ;
     * description_lang / quote_lang = « fr » pour ce qui reste une citation en français.
     */
    let { chronicle } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const dateOf = (iso, precision) =>
        firstOfMonth(formatEventDate(iso, precision, formatLocale(lang)), lang);
    // Statuts : libellés propres à la fiche quand ils existent (lines.sheet.chronicle.*).
    const statusLabel = (st) => {
        const key = `lines.sheet.chronicle.${st}`;
        const label = $i18n.t(key);
        return label === key ? $i18n.t(`lines.chronicle.status.${st}`) : label;
    };
    const STATUSES = [
        'retained',
        'agrees',
        'conflict',
        'unused',
        'ignored',
        'unlocated',
        'other_line',
        'context',
    ];
    const BADGE = { retained: 'accent', agrees: 'success', conflict: 'warning' };
    // Légende : seulement les statuts présents sur cette fiche.
    const usedStatuses = $derived(
        chronicle?.statuses ??
            STATUSES.filter((st) => (chronicle?.events ?? []).some((e) => e.status === st)),
    );
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
        {#each usedStatuses as st (st)}
            <div>
                <dt>
                    <span class="vff-badge {BADGE[st] ?? 'muted'}">{statusLabel(st)}</span>
                </dt>
                <dd>{$i18n.t(`lines.chronicle.status_help.${st}`)}</dd>
            </div>
        {/each}
    </dl>

    {#snippet groupBlock(g, open)}
        <details class="group" {open}>
            <summary>
                <i class="bi bi-chevron-right chev" aria-hidden="true"></i>
                <span class="label">{g.title ?? groupLabel(g)}</span>
                <span class="nums">
                    {#if g.length_m}<span class="len"
                            >{formatNumber(g.length_m / 1000, lang, {
                                maximumFractionDigits: 1,
                            })}&nbsp;km</span
                        >{/if}
                    <span class="count"
                        >{$i18n.t('lines.sheet.chronicle.event_count', {
                            count: g.event_ids.length,
                        })}</span
                    >
                </span>
            </summary>
            <table>
                <thead>
                    <tr>
                        <th scope="col">{$i18n.t('lines.chronicle.col_date')}</th>
                        <th scope="col">{$i18n.t('lines.chronicle.col_fact')}</th>
                        <th scope="col">{$i18n.t('lines.sheet.chronicle.col_status')}</th>
                    </tr>
                </thead>
                <tbody>
                    {#each g.event_ids as id (id)}
                        {@const e = byId[id]}
                        {#if e}
                            <tr class="st-row-{e.status}">
                                <td class="date">{dateOf(e.date, e.precision)}</td>
                                <td class="fact">
                                    <span
                                        class="vff-badge {BADGE[e.status] ?? 'muted'} inline-status"
                                        >{statusLabel(e.status)}</span
                                    >
                                    {eventLabel(e.type)}<sup
                                        ><a href="#note-{e.note}">[{e.note}]</a></sup
                                    >
                                    {#if e.description}<span class="desc" lang={e.description_lang}
                                            >{tieUnits(firstOfMonth(e.description, lang))}</span
                                        >{/if}
                                    {#if e.quote && e.quote_kind === 'data'}<span
                                            class="quote"
                                            lang={e.quote_lang}>{tieUnits(e.quote)}</span
                                        >{:else if e.quote}<q class="quote" lang={e.quote_lang}
                                            >{e.quote}</q
                                        >{/if}
                                    {#if e.correction}<span
                                            class="correction"
                                            lang={e.correction_lang}>{e.correction}</span
                                        >{/if}
                                    {#if e.conflicts_with.length}
                                        <span class="vs">
                                            {$i18n.t('lines.chronicle.differs_from')}
                                            {#each e.conflicts_with as o, n (o)}
                                                {#if byId[o]}{dateOf(
                                                        byId[o].date,
                                                        byId[o].precision,
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
                                    ><span class="vff-badge {BADGE[e.status] ?? 'muted'}"
                                        >{statusLabel(e.status)}</span
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
                <span class="nums">
                    <span class="count"
                        >{$i18n.t('lines.sheet.chronicle.event_count', {
                            count: nearGroups.reduce((a, g) => a + g.event_ids.length, 0),
                        })}</span
                    >
                </span>
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
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .legend {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 8px 24px;
        margin: 0 0 var(--vff-space-5);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
    }
    .legend div {
        display: flex;
        align-items: baseline;
        gap: 8px;
    }
    .legend dt {
        flex-shrink: 0;
    }
    .legend dd {
        margin: 0;
        color: var(--vff-text-muted);
    }
    .group {
        padding: 2px 0;
        border-top: 1px solid var(--vff-border);
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
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 12px;
        min-height: 44px;
        padding: 10px 0;
        list-style: none;
        cursor: pointer;
    }
    summary::-webkit-details-marker {
        display: none;
    }
    .chev {
        flex-shrink: 0;
        align-self: center;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    @media (prefers-reduced-motion: no-preference) {
        .chev {
            transition: transform var(--vff-transition);
        }
    }
    details[open] > summary .chev {
        transform: rotate(90deg);
    }
    summary:hover .label {
        color: var(--vff-accent-ink);
    }
    .label {
        flex: 1 1 16em;
        min-width: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
    }
    /* Longueur et compteur : jamais coupés, ensemble à droite (dessous sur téléphone). */
    .nums {
        display: inline-flex;
        gap: 16px;
        margin-left: auto;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    table {
        width: 100%;
        margin: 0 0 12px;
        border-collapse: collapse;
        table-layout: fixed;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    th {
        padding: 4px 8px 6px 0;
        border-bottom: 1px solid var(--vff-border);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        text-align: left;
        color: var(--vff-text-muted);
    }
    td {
        padding: 10px 8px 10px 0;
        border-bottom: 1px solid var(--vff-border);
        vertical-align: top;
    }
    td.date,
    th:nth-child(1) {
        width: 10em;
    }
    th:nth-child(3) {
        width: 9em;
    }
    td.date {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    td.fact {
        line-height: 1.5;
    }
    .desc {
        display: block;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-xs);
    }
    .quote {
        display: block;
        margin-top: 4px;
        padding-left: 10px;
        border-left: 2px solid var(--vff-border-strong);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    /* Guillemets français avec leurs espaces insécables. */
    .quote:lang(fr) {
        quotes: '«\00a0' '\00a0»';
    }
    .correction {
        display: block;
        margin-top: 4px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .vs {
        display: block;
        margin-top: 2px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-warning);
    }
    tr.st-row-ignored td,
    tr.st-row-unused td {
        color: var(--vff-text-muted);
    }
    sup {
        line-height: 0;
    }
    /* Appel de note : cible de 24 px (le texte reste en exposant). */
    sup a {
        /* Remplissage en ligne : la zone cliquable grandit sans écarter les lignes du texte. */
        padding: 5px 4px;
        color: var(--vff-link);
        font-size: var(--vff-fs-xs);
        text-decoration: none;
    }
    .vff-badge {
        white-space: normal;
    }
    .inline-status {
        display: none;
    }
    /* Téléphone : la colonne du rôle disparaît, le statut passe dans la cellule du fait. */
    @media (max-width: 600px) {
        .inline-status {
            display: inline-flex;
            margin: 0 6px 4px 0;
        }
        td.date,
        th:nth-child(1) {
            width: 6.5em;
        }
        td.date {
            font-size: var(--vff-fs-xs);
        }
        th:nth-child(3),
        td:nth-child(3) {
            display: none;
        }
        .nums {
            flex-basis: 100%;
            margin-left: 25px;
        }
    }
</style>
