<script>
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import ReportError from '$components/Sheet/ReportError.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import AccidentMiniMap from '$components/Accidents/AccidentMiniMap.svelte';
    import AccidentContext from '$components/Accidents/AccidentContext.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        CONFIDENCE_TONE,
        accidentDate,
        distanceText,
        providerKey,
        timeText,
        trainText,
    } from '$lib/accidentText.js';
    import { colon, formatLocale, formatNumber } from '$lib/typo.js';

    let { accident } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const name = $derived(accident.name[lang] ?? accident.name.fr);
    const summary = $derived(accident.summary[lang] ?? accident.summary.fr);
    const place = $derived(accident.place);
    const located = $derived(place.lat != null && place.lng != null);
    const primary = $derived(accident.lines.find((l) => l.is_primary) ?? null);
    const others = $derived(accident.lines.filter((l) => !l.is_primary));
    const dateText = $derived(accidentDate(accident.date, accident.date_precision, lang));
    const wikipedia = $derived(
        ['fr', 'en', 'de']
            .filter((l) => accident.wikipedia?.[l])
            .map((l) => [l, accident.wikipedia[l]]),
    );

    const unknown = $derived(String($i18n.t('accidents.show.unknown')));
    const statItems = $derived([
        {
            text: accident.time ? `${dateText}, ${timeText(accident.time, lang)}` : dateText,
            label: String($i18n.t('accidents.show.date')),
            note:
                accident.date_precision !== 'day'
                    ? String($i18n.t(`accidents.date_precision.${accident.date_precision}`))
                    : undefined,
        },
        accident.deaths != null
            ? {
                  value: formatNumber(accident.deaths, lang),
                  label: String($i18n.t('accidents.show.deaths')),
              }
            : { text: unknown, label: String($i18n.t('accidents.show.deaths')), tone: 'muted' },
        accident.injuries != null
            ? {
                  value: formatNumber(accident.injuries, lang),
                  label: String($i18n.t('accidents.show.injuries')),
              }
            : { text: unknown, label: String($i18n.t('accidents.show.injuries')), tone: 'muted' },
    ]);

    /** « Brétigny-sur-Orge (Essonne) », la commune seule, ou le département seul. */
    const placeText = $derived(
        place.commune
            ? place.department && place.department !== place.commune
                ? `${place.commune} (${place.department})`
                : place.commune
            : (place.department ?? ''),
    );
    const osmUrl = $derived(
        located
            ? `https://www.openstreetmap.org/?mlat=${place.lat}&mlon=${place.lng}#map=${place.precision === 'commune' ? 13 : 16}/${place.lat}/${place.lng}`
            : null,
    );

    function portionText(link) {
        const { from, to } = link.section;
        if (from && to) return { key: 'portion_between', from, to };
        if (from) return { key: 'portion_at', from };
        if (to) return { key: 'portion_near', from: to };
        return null;
    }

    function methodsText(link) {
        return link.method.map((m) => $i18n.t(`accidents.methods.${m}`)).join(', ');
    }

    function retrievedText(iso) {
        return new Date(iso).toLocaleDateString(formatLocale(lang), {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }
</script>

<svelte:head>
    <title>{name} - VFF</title>
</svelte:head>

<!-- Description et aperçu de partage : écrits d'abord par le serveur. -->
<SeoHead
    title={name}
    description={$i18n.t('accidents.meta_show', { name })}
    path={`/accidents/${accident.slug}`}
    type="article"
/>

{#snippet lineName(line)}
    {#if line.code}<span class="vff-plate">{line.code}</span>{/if}
    <span lang="fr">{line.name}</span>
{/snippet}

{#snippet portion(link)}
    {@const p = portionText(link)}
    {#if p}
        <div>
            <dt>{$i18n.t('accidents.show.portion')}</dt>
            <dd>
                {$i18n.t(`accidents.show.${p.key}`, {
                    from: p.from.name,
                    to: p.to?.name,
                    station: p.from.name,
                })}
            </dd>
        </div>
    {/if}
{/snippet}

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                {
                    href: '/accidents',
                    label: $i18n.t('accidents.show.back'),
                    icon: 'bi-arrow-left',
                },
            ]}
            crumbsLabel={$i18n.t('accidents.show.crumbs_label')}
            eyebrow={[$i18n.t(`accidents.kinds.${accident.kind}`), placeText].filter(Boolean)}
            title={name}
            titleLang={accident.name[lang] ? undefined : 'fr'}
            stats={heroStats}
        />

        {#snippet heroStats()}
            <StatStrip items={statItems} label={$i18n.t('accidents.show.stats_label')} />
        {/snippet}

        <SheetSection id="summary" title={$i18n.t('accidents.show.summary_title')}>
            <div class={['summary', { aside: accident.review.notes.length || wikipedia.length }]}>
                <div class="vff-prose">
                    <p>{summary}</p>
                    <p class="note">{$i18n.t('accidents.show.summary_note')}</p>
                </div>
                {#if accident.review.notes.length || wikipedia.length}
                    <div class="aside-col">
                        {#if accident.review.notes.length}
                            <Notice
                                tone={accident.review.status === 'ok' ? 'info' : 'warning'}
                                live="off"
                                title={$i18n.t(
                                    accident.review.status === 'ok'
                                        ? 'accidents.show.reserves_title'
                                        : 'accidents.show.review_title',
                                )}
                            >
                                <p>
                                    {$i18n.t(
                                        accident.review.status === 'ok'
                                            ? 'accidents.show.reserves_intro'
                                            : 'accidents.show.review_intro',
                                    )}
                                </p>
                                <ul>
                                    {#each accident.review.notes as n, i (i)}
                                        <li>
                                            {$i18n.t(`accidents.review.${n.code}`)}
                                            {#if n.detail}<span class="detail" lang="fr"
                                                    >({n.detail})</span
                                                >{/if}
                                        </li>
                                    {/each}
                                </ul>
                            </Notice>
                        {/if}
                        {#if wikipedia.length}
                            <div class="wiki-box">
                                <h3>{$i18n.t('accidents.show.wikipedia_title')}</h3>
                                <ul class="wiki">
                                    {#each wikipedia as [code, w] (code)}
                                        <li>
                                            <a
                                                href={w.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                lang={code}
                                                >{w.title}<i
                                                    class="bi bi-box-arrow-up-right vff-ext"
                                                    aria-hidden="true"
                                                ></i><span class="vff-sr-only">
                                                    ({$i18n.t('common.new_tab')})</span
                                                ></a
                                            >
                                            <span class="muted">({code})</span>
                                        </li>
                                    {/each}
                                </ul>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        </SheetSection>

        {#if accident.context}
            <AccidentContext context={accident.context} />
        {/if}

        <SheetSection id="place" title={$i18n.t('accidents.show.place_title')}>
            <div class={['place', { located }]}>
                {#if located}
                    <AccidentMiniMap
                        lat={place.lat}
                        lng={place.lng}
                        {name}
                        precision={place.precision}
                    />
                {/if}
                <dl class="facts">
                    {#if place.commune}
                        <div>
                            <dt>{$i18n.t('accidents.show.commune')}</dt>
                            <dd>{place.commune}</dd>
                        </div>
                    {/if}
                    {#if place.department}
                        <div>
                            <dt>{$i18n.t('accidents.show.department')}</dt>
                            <dd>{place.department} ({place.department_code})</dd>
                        </div>
                    {/if}
                    {#if located}
                        <div>
                            <dt>{$i18n.t('accidents.show.position')}</dt>
                            <dd>{$i18n.t(`accidents.location_precision.${place.precision}`)}</dd>
                        </div>
                        <div>
                            <dt>{$i18n.t('accidents.show.coordinates')}</dt>
                            <dd class="num">
                                {formatNumber(place.lat, lang, { maximumFractionDigits: 5 })}, {formatNumber(
                                    place.lng,
                                    lang,
                                    { maximumFractionDigits: 5 },
                                )}
                                <a
                                    class="vff-link osm"
                                    href={osmUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{$i18n.t('accidents.show.osm_link')}<i
                                        class="bi bi-box-arrow-up-right vff-ext"
                                        aria-hidden="true"
                                    ></i><span class="vff-sr-only">
                                        ({$i18n.t('common.new_tab')})</span
                                    ></a
                                >
                            </dd>
                        </div>
                    {:else}
                        <div>
                            <dt>{$i18n.t('accidents.show.position')}</dt>
                            <dd>{$i18n.t('accidents.show.no_place')}</dd>
                        </div>
                    {/if}
                    {#if accident.nearest_station}
                        <div>
                            <dt>{$i18n.t('accidents.show.nearest_station')}</dt>
                            <dd>
                                <Link href="/stations/{accident.nearest_station.slug}"
                                    >{accident.nearest_station.name}</Link
                                >
                                <span class="muted"
                                    >{$i18n.t('accidents.show.station_distance', {
                                        distance: distanceText(
                                            accident.nearest_station.distance_m,
                                            lang,
                                        ),
                                    })}</span
                                >
                            </dd>
                        </div>
                    {/if}
                </dl>
            </div>
        </SheetSection>

        <SheetSection id="line" title={$i18n.t('accidents.show.line_title')}>
            {#if primary?.line}
                <p class="lead-text">{$i18n.t('accidents.show.line_intro')}</p>
                <div class="vff-panel line-card">
                    <Link class="line-title" href="/lines/{primary.line.slug}">
                        {@render lineName(primary.line)}
                    </Link>
                    <dl class="facts">
                        {@render portion(primary)}
                        {#if primary.pk_label}
                            <div>
                                <dt>{$i18n.t('accidents.show.pk')}</dt>
                                <dd class="num">
                                    {$i18n.t('accidents.show.pk_value', { pk: primary.pk_label })}
                                </dd>
                            </div>
                        {/if}
                        <div>
                            <dt>{$i18n.t('accidents.show.confidence')}</dt>
                            <dd>
                                <span class="vff-badge {CONFIDENCE_TONE[primary.confidence]}"
                                    >{$i18n.t(`accidents.confidence.${primary.confidence}`)}</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt>{$i18n.t('accidents.show.method')}</dt>
                            <dd>{methodsText(primary)}</dd>
                        </div>
                        {#if primary.cited_as && primary.cited_url}
                            <div>
                                <dt>{$i18n.t('accidents.show.line_cited')}</dt>
                                <dd>
                                    <!-- Le lien mène à la source (Wikipédia en français) qui cite cet
                                         article : le texte masqué le dit (RGAA 6.1). -->
                                    <a
                                        href={primary.cited_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        ><span lang="fr">{primary.cited_as}</span><i
                                            class="bi bi-box-arrow-up-right vff-ext"
                                            aria-hidden="true"
                                        ></i><span class="vff-sr-only">
                                            ({$i18n.t('accidents.show.line_cited_hint')})</span
                                        ></a
                                    >
                                </dd>
                            </div>
                        {/if}
                        {#if primary.era_year}
                            <div>
                                <dt>{$i18n.t('accidents.show.era_used')}</dt>
                                <dd>
                                    {$i18n.t('accidents.show.era_value', {
                                        year: primary.era_year,
                                    })}
                                </dd>
                            </div>
                        {/if}
                        {#if primary.distance_m != null && place.precision !== 'commune'}
                            <div>
                                <dt>{$i18n.t('accidents.show.distance')}</dt>
                                <dd class="num">{distanceText(primary.distance_m, lang)}</dd>
                            </div>
                        {/if}
                    </dl>
                </div>
                {#if others.length}
                    <h3 class="sub">{$i18n.t('accidents.show.other_lines')}</h3>
                    <ul class="others">
                        {#each others as l, i (i)}
                            {#if l.line}
                                <li>
                                    <Link class="line-title small" href="/lines/{l.line.slug}">
                                        {@render lineName(l.line)}
                                    </Link>
                                    <span class="vff-badge {CONFIDENCE_TONE[l.confidence]}"
                                        >{$i18n.t(`accidents.confidence.${l.confidence}`)}</span
                                    >
                                </li>
                            {/if}
                        {/each}
                    </ul>
                {/if}
            {:else}
                <p class="lead-text">{$i18n.t('accidents.show.line_none')}</p>
            {/if}
        </SheetSection>

        {#if accident.trains.length}
            <SheetSection
                id="trains"
                title={$i18n.t('accidents.show.trains_title')}
                count={accident.trains.length}
            >
                <ul class="trains">
                    {#each accident.trains as t, i (i)}
                        <li>
                            {#if !t.label && !t.category && !t.material && t.operator}
                                <span
                                    >{$i18n.t(
                                        t.operator.includes(',')
                                            ? 'accidents.show.operators'
                                            : 'accidents.show.operator',
                                    )}{colon(lang)}{t.operator}</span
                                >
                            {:else}
                                <span lang={t.source === 'wikipedia' && !t.label ? 'fr' : undefined}
                                    >{trainText(t, $i18n.t)}</span
                                >
                            {/if}
                            {#if t.operator && (t.label || t.category || t.material)}
                                <span class="muted"
                                    >{$i18n.t('accidents.show.operator')}{colon(
                                        lang,
                                    )}{t.operator}</span
                                >
                            {/if}
                        </li>
                    {/each}
                </ul>
            </SheetSection>
        {/if}

        {#if accident.context}
            <AccidentContext context={accident.context} part="references" />
        {/if}

        <SheetSection
            id="sources"
            title={$i18n.t('accidents.show.sources_title')}
            count={accident.sources.length}
            intro={$i18n.t('accidents.show.sources_intro')}
        >
            <ul class="sources">
                {#each accident.sources as s (s.url)}
                    <li>
                        <p class="source-head">
                            <span class="provider">{$i18n.t(providerKey(s.provider))}</span>
                            <span class="muted">{$i18n.t(`accidents.roles.${s.role}`)}</span>
                            {#if accident.toll_source === s.provider}
                                <span class="vff-badge muted"
                                    >{$i18n.t('accidents.show.toll_source', {
                                        source: $i18n.t(providerKey(s.provider)),
                                    })}</span
                                >
                            {/if}
                        </p>
                        <a
                            class="source-title"
                            href={s.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            lang="fr"
                            >{s.title}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"
                            ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
                        >
                        <p class="source-meta">
                            {#if s.license_url}
                                <a href={s.license_url} target="_blank" rel="noopener noreferrer"
                                    >{$i18n.t('accidents.show.source_licence', {
                                        licence: s.license,
                                    })}<span class="vff-sr-only">
                                        ({$i18n.t('common.new_tab')})</span
                                    ></a
                                >
                            {:else}
                                {$i18n.t('accidents.show.source_licence', { licence: s.license })}
                            {/if}
                            · {$i18n.t('accidents.show.source_retrieved', {
                                date: retrievedText(s.retrieved_at),
                            })}
                        </p>
                    </li>
                {/each}
            </ul>
        </SheetSection>

        <ReportError type="accident" slug={accident.slug} {name} />

        <div class="end"></div>
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
    }
    .end {
        height: var(--vff-section-space);
    }
    /* Résumé court, plein de noms de lieux : pas de césure (« Pyrénées-Orien-tales »). */
    .summary .vff-prose p {
        hyphens: manual;
    }
    .note {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .summary {
        display: grid;
        gap: var(--vff-block-space) var(--vff-space-6);
    }
    .summary.aside {
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
        align-items: start;
    }
    .aside-col {
        display: grid;
        gap: var(--vff-space-5);
    }
    .aside-col ul {
        margin: var(--vff-space-2) 0 0;
        padding-left: 1.2em;
    }
    .detail {
        color: var(--vff-text-muted);
    }
    .place {
        display: grid;
        gap: var(--vff-space-5) var(--vff-space-6);
    }
    .place.located {
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
        align-items: start;
    }
    .facts {
        display: grid;
        gap: var(--vff-space-3);
        margin: 0;
    }
    .facts > div {
        display: grid;
        grid-template-columns: minmax(9rem, 12rem) minmax(0, 1fr);
        gap: var(--vff-space-3);
        padding-bottom: var(--vff-space-3);
        border-bottom: 1px solid var(--vff-border);
    }
    .facts > div:last-child {
        border-bottom: 0;
    }
    .place .facts > div {
        grid-template-columns: 1fr;
        gap: 2px;
    }
    dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    dd {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .num {
        font-variant-numeric: tabular-nums;
    }
    .osm {
        display: block;
        margin-top: var(--vff-space-1);
        font-size: var(--vff-fs-sm);
    }
    .muted {
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
    }
    .lead-text {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        color: var(--vff-text-muted);
    }
    .line-card {
        display: grid;
        gap: var(--vff-space-4);
        max-width: 820px;
        padding: var(--vff-pad-box);
    }
    .line-card :global(.line-title),
    .others :global(.line-title) {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 10px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        color: var(--vff-text);
        text-decoration: none;
    }
    .line-card :global(.line-title:hover),
    .others :global(.line-title:hover) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
    }
    .others :global(.line-title.small) {
        font-size: var(--vff-fs-md);
    }
    .sub {
        margin: var(--vff-block-space) 0 var(--vff-space-3);
    }
    /* Cible de 24 px au moins pour des liens empilés. */
    .wiki a {
        display: inline-block;
        padding-block: 2px;
    }
    .wiki-box h3 {
        margin: 0 0 var(--vff-space-3);
    }
    .others,
    .trains,
    .sources,
    .wiki {
        display: grid;
        gap: var(--vff-space-3);
        max-width: 820px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .others li,
    .trains li,
    .wiki li {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
    }
    .sources li {
        padding-bottom: var(--vff-space-4);
        border-bottom: 1px solid var(--vff-border);
    }
    .sources li:last-child {
        border-bottom: 0;
    }
    .source-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-3);
        margin: 0 0 var(--vff-space-1);
        font-family: var(--vff-font-heading);
    }
    .provider {
        font-weight: 600;
        color: var(--vff-text);
    }
    .source-title {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        overflow-wrap: anywhere;
    }
    .source-meta {
        margin: var(--vff-space-1) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    @media (max-width: 900px) {
        .place.located,
        .summary.aside {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 600px) {
        .facts > div {
            grid-template-columns: 1fr;
            gap: 2px;
        }
    }
</style>
