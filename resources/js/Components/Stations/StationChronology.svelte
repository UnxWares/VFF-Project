<script>
    import { formatStationDate, formatStationOpening } from '$lib/stationDates.js';
    import { tollText } from '$lib/accidentText.js';
    import { formatNumber } from '$lib/typo.js';
    import { precisionKey, withAerialViews } from '$lib/stationChronology.js';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Chronologie de la fiche gare (App\Services\StationChronology) : chaque date avec sa précision et
     * sa source (et les autres sources qui la confirment), les lignes qui commencent ou cessent de
     * desservir la gare, les noms portés, les accidents survenus tout près, et, à leur place dans le
     * temps, les vues aériennes de l'IGN (1950-1965, 1965-1980, 1980-1995). Une vue hors couverture
     * (image en 404) est retirée au chargement, comme dans la section « Vue du ciel ».
     *
     * @type {{ entries?: Array<any>, views?: Array<{ period: string, from: number, to: number, url: string }>, name: string }}
     */
    let { entries = [], views = [], name } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));

    /** Périodes dont l'image n'a pas pu être chargée. */
    let missing = $state(new Set());
    const items = $derived(
        withAerialViews(
            entries,
            views.filter((v) => !missing.has(v.period)),
        ),
    );
    const hasEstimated = $derived(entries.some((e) => e.estimated));
    const hasDates = $derived(entries.length > 0);

    function drop(period) {
        missing = new Set([...missing, period]);
    }

    const whenText = (e) =>
        e.type === 'opening'
            ? formatStationOpening(e.date, e.precision, e.after, t, lang)
            : formatStationDate(e.date, e.precision, e.after, t, lang);
    /** Une date encadrée ou une borne n'a pas de jour sûr : pas d'attribut datetime. */
    const machineDate = (e) =>
        ['day', 'month', 'year'].includes(e.precision)
            ? e.precision === 'day'
                ? e.date
                : e.precision === 'month'
                  ? e.date.slice(0, 7)
                  : e.date.slice(0, 4)
            : undefined;

    const sourceName = (s) =>
        $i18n.exists(`stations.chronology.sources.${s}`)
            ? t(`stations.chronology.sources.${s}`)
            : t(`stations.sources.names.${s}`, { defaultValue: s });

    function title(e) {
        if (e.kind === 'event') return t(`stations.events.${e.type}`);
        if (e.kind === 'name') return t('stations.chronology.name', { name: e.name.name });
        return t(`stations.chronology.${e.kind}`);
    }

    function namePeriod(n) {
        if (!n.from) return t('stations.chronology.name_until', { to: n.to });
        return n.to
            ? t('stations.chronology.name_from_to', { from: n.from, to: n.to })
            : t('stations.chronology.name_from', { from: n.from });
    }

    /** « Déraillement, à 1,9 km de la gare. 3 morts » : nature, distance, bilan. */
    function accidentFacts(a) {
        const where =
            a.location_precision === 'commune' || a.location_precision == null
                ? t('stations.chronology.accident_commune')
                : a.location_precision === 'station' || (a.distance_m ?? 0) < 200
                  ? t('stations.chronology.accident_at_station')
                  : t('stations.chronology.accident_distance', {
                        km: formatNumber(a.distance_m / 1000, lang, {
                            minimumFractionDigits: 1,
                            maximumFractionDigits: 1,
                        }),
                    });
        return `${t(`accidents.kinds.${a.kind}`)}, ${where}. ${tollText(a.deaths, a.injuries, $i18n.t)}`;
    }

    /**
     * Lien de la source : la page de la source (nouvel onglet), ou la chronologie de la ligne sur le
     * site pour une desserte datée par les événements de la ligne.
     */
    function sourceLink(e) {
        if (e.source_url) return { href: e.source_url, external: !e.source_url.startsWith('/') };
        if (e.source === 'line_events' && e.lines.length) {
            return { href: `/lines/${e.lines[0].slug}#chronicle`, external: false };
        }
        return null;
    }

    /** Autres sources de la même date : « Wikidata (1934), OpenHistoricalMap ». */
    function alsoText(e) {
        const list = e.also.map((a) => {
            const label = sourceName(a.source);
            return a.precision !== e.precision || a.date !== e.date
                ? `${label} (${formatStationDate(a.date, a.precision, null, t, lang)})`
                : label;
        });
        return new Intl.ListFormat(lang, { style: 'long', type: 'conjunction' }).format(list);
    }
</script>

{#snippet lineLinks(lines)}
    {#each lines as l, i (l.slug + l.role)}<a href="/lines/{l.slug}">{l.name}</a
        >{#if l.code}&nbsp;<span class="code">({l.code})</span>{/if}{i < lines.length - 1
            ? ', '
            : ''}{/each}
{/snippet}

{#if hasDates}
    <ol class="timeline">
        {#each items as item (item.kind === 'aerial' ? `aerial-${item.view.period}` : item.index)}
            {#if item.kind === 'aerial'}
                {@const v = item.view}
                <li class="aerial">
                    <p class="when">{v.from}-{v.to}</p>
                    <div class="what">
                        <p class="ev-type">
                            {t('stations.chronology.aerial', { from: v.from, to: v.to })}
                        </p>
                        <a class="aerial-link" href="#aerial">
                            <img
                                src={v.url}
                                alt={t('stations.aerial.alt', { name, from: v.from, to: v.to })}
                                width="640"
                                height="384"
                                loading="lazy"
                                decoding="async"
                                onerror={() => drop(v.period)}
                            />
                            <span class="aerial-more">{t('stations.chronology.aerial_more')}</span>
                        </a>
                        <p class="ev-source">{t('stations.chronology.aerial_hint')}</p>
                    </div>
                </li>
            {:else}
                {@const e = item.entry}
                <li
                    class={{
                        inferred: e.estimated,
                        dropped: !e.retained,
                        accident: e.kind === 'accident',
                    }}
                >
                    <p class="when">
                        <time datetime={machineDate(e)}>{whenText(e)}</time>
                    </p>
                    <div class="what">
                        <p class="ev-type">
                            {#if e.kind === 'accident'}
                                <a href="/accidents/{e.accident.slug}">{e.accident.name}</a>
                            {:else}
                                {title(e)}
                            {/if}
                            {#if e.estimated}<span class="vff-badge muted"
                                    >{t('stations.events.inferred')}</span
                                >{/if}
                            {#if !e.retained}<span class="vff-badge warning"
                                    >{t('stations.chronology.not_retained')}</span
                                >{/if}
                            {#if e.accident?.to_review}<span class="vff-badge muted"
                                    >{t('stations.chronology.to_review')}</span
                                >{/if}
                        </p>
                        {#if e.kind === 'name'}
                            <p class="ev-note">{namePeriod(e.name)}</p>
                        {:else if e.kind === 'accident'}
                            <p class="ev-note">{accidentFacts(e.accident)}</p>
                        {:else if e.kind === 'line_start' || e.kind === 'line_end'}
                            <p class="ev-note">{@render lineLinks(e.lines)}</p>
                        {/if}
                        {#if e.kind === 'event'}
                            {#each ['start', 'end'] as role (role)}
                                {@const ls = e.lines.filter((l) => l.role === role)}
                                {#if ls.length}
                                    <p class="ev-note">
                                        {t(`stations.chronology.lines_${role}`, {
                                            count: ls.length,
                                        })}
                                        {@render lineLinks(ls)}
                                    </p>
                                {/if}
                            {/each}
                        {/if}
                        {#if !e.retained}
                            <p class="ev-note muted">
                                {t('stations.chronology.not_retained_hint')}
                            </p>
                        {/if}
                        {#if e.note}<p class="ev-note" lang={e.note_lang}>{e.note}</p>{/if}
                        <p class="ev-source">
                            <span class="precision">{t(precisionKey(e))}</span>
                            {#if e.kind !== 'accident'}
                                {@const src = sourceLink(e)}
                                <span
                                    >{t('stations.chronology.source')}
                                    {#if src?.external}<a
                                            href={src.href}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            >{sourceName(e.source)}<i
                                                class="bi bi-box-arrow-up-right vff-ext"
                                                aria-hidden="true"
                                            ></i><span class="vff-sr-only">
                                                ({t('common.new_tab')})</span
                                            ></a
                                        >{:else if src}<a href={src.href}>{sourceName(e.source)}</a
                                        >{:else}{sourceName(e.source)}{/if}</span
                                >
                            {/if}
                            {#if e.also.length}
                                <span>{t('stations.chronology.also', { list: alsoText(e) })}</span>
                            {/if}
                        </p>
                    </div>
                </li>
            {/if}
        {/each}
    </ol>
    {#if hasEstimated}
        <p class="footnote">{t('stations.events.inferred_hint')}</p>
    {/if}
{:else}
    <EmptyState icon="bi-calendar-x" title={t('stations.events.none')} />
{/if}

<style>
    /* Chronologie : date à gauche, fait à droite, point orange et filet vertical dans la gouttière. */
    .timeline {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .timeline li {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 200px) minmax(0, 1fr);
        gap: 4px 32px;
        padding: 0 0 var(--vff-space-5) 28px;
    }
    .timeline li::before {
        content: '';
        position: absolute;
        top: 7px;
        left: 0;
        width: 12px;
        height: 12px;
        box-sizing: border-box;
        border: 2px solid var(--vff-accent);
        border-radius: 50%;
        background-color: var(--vff-accent);
    }
    /* Date déduite, date non retenue : point creux gris (un contour en tirets, à 12 px, se lisait
       comme un cercle cassé). */
    .timeline li.inferred::before,
    .timeline li.dropped::before {
        border-color: var(--vff-text-muted);
        background-color: var(--vff-bg);
    }
    /* Accident : point plein gris foncé, la couleur de la marque reste aux faits de la gare. */
    .timeline li.accident::before {
        border-color: var(--vff-text);
        background-color: var(--vff-text);
    }
    /* Vue aérienne : repère carré arrondi, c'est une image et non une date. */
    .timeline li.aerial::before {
        border-color: var(--vff-border-strong);
        border-radius: 3px;
        background-color: var(--vff-bg-muted);
    }
    /* Trait entre deux points, à 5 px de chacun (point de 12 px à 7 px du haut : il finit 2 px
       sous le bas de l'élément, 5 px avant le point suivant). */
    .timeline li:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 24px;
        bottom: -2px;
        left: 5px;
        width: 2px;
        background-color: var(--vff-border-strong);
    }
    .timeline p {
        margin: 0;
    }
    .when {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        font-weight: 700;
        line-height: 1.35;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .dropped .when {
        color: var(--vff-text-muted);
    }
    .what {
        min-width: 0;
    }
    .ev-type {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 12px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        line-height: 1.5;
        color: var(--vff-text);
    }
    .ev-type a {
        color: inherit;
    }
    .ev-note {
        max-width: var(--vff-measure);
        margin-top: 4px;
        font-size: var(--vff-fs-sm);
        line-height: 1.55;
        color: var(--vff-text);
        text-wrap: pretty;
    }
    .ev-note.muted {
        color: var(--vff-text-muted);
    }
    .code {
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .ev-source {
        margin-top: 4px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    /* Point médian entre les éléments, collé à l'élément qui suit : il ne reste jamais seul en fin
       de ligne. */
    .ev-source > span + span::before {
        content: '·' / '';
        margin: 0 8px;
    }
    .ev-source a {
        display: inline-block;
        min-height: 24px;
    }
    .precision {
        font-weight: 600;
    }
    .aerial-link {
        display: block;
        width: min(100%, 360px);
        margin-top: var(--vff-space-2);
        color: var(--vff-text);
        text-decoration: none;
        border-radius: var(--vff-radius-md);
    }
    .aerial-link img {
        display: block;
        width: 100%;
        height: auto;
        aspect-ratio: 5 / 3;
        object-fit: cover;
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-muted);
    }
    .aerial-more {
        display: inline-block;
        margin-top: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .aerial-link:hover .aerial-more {
        color: var(--vff-accent-ink);
    }
    .footnote {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    @media (max-width: 600px) {
        .timeline li {
            grid-template-columns: minmax(0, 1fr);
        }
        /* Petit écran : précision, source et autres sources sur une ligne chacune, sans point médian. */
        .ev-source > span {
            display: block;
        }
        .ev-source > span + span::before {
            content: none;
        }
    }
</style>
