<script>
    import Layout from '$components/Layout.svelte';
    import { i18n } from '$lib/i18n.js';
    import { quoted } from '$lib/typo.js';
    import { linkLabel } from '$lib/linkLabel.js';
    import LinePhaseMap from '$components/Lines/LinePhaseMap.svelte';
    import LinePhaseSelector from '$components/Lines/LinePhaseSelector.svelte';
    import LinePhaseText from '$components/Lines/LinePhaseText.svelte';
    import LineChronicle from '$components/Lines/LineChronicle.svelte';
    import LineDecades from '$components/Lines/LineDecades.svelte';
    import LineStations from '$components/Lines/LineStations.svelte';
    import LineSourceNotes from '$components/Lines/LineSourceNotes.svelte';
    import LineGallery from '$components/Lines/LineGallery.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import {
        bestPhaseIndex,
        categoryColor,
        categorySwatch,
        formatEventDate,
        formatKm,
        maxKnownLength,
        phaseDateLabel,
        phaseEraYear,
        phaseStrip,
    } from '$lib/linePhases.js';

    /** Props de LineController::show (les médias sont chargés à part par LineGallery, via l'API).
     * @type {{
     *   line: Record<string, any>, stats: Record<string, any>, segments: Array<any>, stations: Array<any>,
     *   events: Array<any>, materials: Array<any>, signalling?: Array<any>, sections?: Array<any>,
     *   era_summary?: Array<any>, heritage?: Array<any>, sources?: Array<any>,
     *   portions?: Array<{ length_m: number, geometry: any }>, phases?: Array<any>, best_phase?: number|null,
     *   phases_unexplained?: Array<{ from: string, to: string, portions: Array<number>, length_m: number }>,
     *   decades?: Array<any>, chronicle?: { events: Array<any>, groups: Array<any>, notes: Array<any>, conflicts: Array<any> },
     * }} */
    let {
        line,
        stats,
        stations = [],
        materials = [],
        signalling = [],
        sections = [],
        heritage = [],
        sources = [],
        portions = [],
        phases = [],
        phases_unexplained = [],
        best_phase = null,
        decades = [],
        chronicle = { events: [], groups: [], notes: [], conflicts: [] },
    } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    // Dernière ère passée reconstituée (31/12), pour les écarts non datés de l'ère courante.
    const lastPastEra = $derived([...decades].reverse().find((d) => !d.is_current)?.year ?? null);
    const catLabel = (c) => {
        const label = String($i18n.t(`lines.categories.${c}`));
        return lang === 'de' ? label : label.charAt(0).toLowerCase() + label.slice(1);
    };
    /** « fret seul sur 21,2 km (voyageurs et fret dans l'état reconstitué au 31 décembre 2020) ; aucune source… » */
    function unexplainedText(u, n) {
        const params = {
            to: catLabel(u.to),
            from: catLabel(u.from),
            km: formatKm(u.length_m, lang),
            year: lastPastEra,
        };
        const text = String(
            $i18n.t(
                lastPastEra
                    ? 'lines.phases.unexplained_item'
                    : 'lines.phases.unexplained_item_noyear',
                params,
            ),
        );
        // Après les deux-points : minuscule ; les phrases suivantes commencent par une capitale.
        return n === 0 ? text : text.charAt(0).toUpperCase() + text.slice(1);
    }
    const best = $derived(bestPhaseIndex(phases, best_phase));
    // Vue par défaut : la ligne entière à son plus grand développement.
    let selected = $state(bestPhaseIndex(phases, best_phase));
    const phase = $derived(phases[selected] ?? null);
    const eventsById = $derived(
        Object.fromEntries((chronicle?.events ?? []).map((e) => [e.id, e])),
    );
    const eraYears = $derived(
        [...new Set(stations.flatMap((s) => Object.keys(s.eras ?? {}).map(Number)))].sort(
            (a, b) => a - b,
        ),
    );

    let mapSection = $state();
    function showPhase(i) {
        selected = i;
        mapSection?.scrollIntoView({
            behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'start',
        });
    }

    /** Signalisation : libellé traduit d'un code (BAL, BAPR, TVM-300…), sinon le code. */
    function sigLabel(code) {
        const key = `lines.signalling.codes.${code}`;
        const label = $i18n.t(key);
        return label === key ? code : label;
    }
    const isCabin = (sig) => /^(TVM-|ETCS_)/.test(sig);

    function heritagePeriod(p) {
        const from = formatEventDate(p.start.date, p.start.precision, lang);
        if (p.end)
            return $i18n.t('lines.heritage.period', {
                from,
                to: formatEventDate(p.end.date, p.end.precision, lang),
            });
        return p.attested
            ? $i18n.t('lines.heritage.attested', { date: from })
            : $i18n.t('lines.heritage.since', { date: from });
    }

    /** Rôle d'un exploitant (« exploitant, propriétaire ») traduit terme à terme. */
    function roleLabel(role) {
        return String(role)
            .split(/,\s*/)
            .map((r) => {
                const key = `lines.facts.roles.${r}`;
                const label = $i18n.t(key);
                return label === key ? r : label;
            })
            .join(', ');
    }

    function sourceLabel(code) {
        const key = `lines.sources.${code}`;
        const label = $i18n.t(key);
        return label === key ? code : label;
    }

    // État de la ligne aujourd'hui (ère courante) : open | closed | gone | null.
    const todayState = $derived(line.current_state ?? null);
    const shutToday = $derived(todayState === 'closed' || todayState === 'gone');

    const names = $derived((line.names ?? []).filter((n) => n.kind !== 'merged'));
    const mergedNames = $derived((line.names ?? []).filter((n) => n.kind === 'merged'));

    const heroEyebrow = $derived(
        [
            line.network ? $i18n.t(`lines.networks.${line.network}`) : null,
            line.current_state === 'open'
                ? $i18n.t(`lines.traffics.${line.current_traffic ?? 'unknown'}`)
                : line.current_state
                  ? $i18n.t(`lines.states.${line.current_state}`)
                  : null,
        ].filter(Boolean),
    );

    const num = (n) => Number(n ?? 0).toLocaleString(lang);

    /** Chiffres clés de l'en-tête (StatStrip). Longueur : à son apogée et aujourd'hui quand elles
     *  diffèrent de plus de 5 % (length_greatest_km), sinon une seule ; ligne fermée ou déposée : l'état. */
    const statItems = $derived.by(() => {
        const t = (k, o) => String($i18n.t(k, o));
        const items = [];
        if (stats.length_greatest_km != null)
            items.push({
                value: num(stats.length_greatest_km),
                label: t('lines.stats.km_greatest'),
                hint: t('lines.stats.km_line_extent_hint'),
            });
        if (stats.length_greatest_km == null || !shutToday)
            items.push({
                value: num(stats.length_km),
                label: t(
                    stats.length_greatest_km != null
                        ? 'lines.stats.km_today'
                        : 'lines.stats.km_line',
                ),
                hint: stats.length_track_km
                    ? t('lines.stats.km_line_hint', { km: num(stats.length_track_km) })
                    : undefined,
            });
        if (shutToday)
            items.push({
                icon: todayState === 'gone' ? 'bi-x-circle' : 'bi-slash-circle',
                text: t(`lines.states.${todayState}`),
                label: t('lines.decades.today'),
                tone: 'muted',
            });
        else if (stats.active_length_km != null && stats.active_length_km !== stats.length_track_km)
            items.push({ value: num(stats.active_length_km), label: t('lines.stats.km_active') });
        items.push({
            value: num(stats.station_count),
            label: t('lines.stats.stations'),
            hint: t('lines.stats.stations_hint', {
                open: stats.station_open_count ?? 0,
                count: stats.station_count ?? 0,
            }),
        });
        items.push({ value: num(phases.length), label: t('lines.stats.phases') });
        if (stats.max_speed_kmh)
            items.push({ value: num(stats.max_speed_kmh), label: t('lines.stats.speed_max') });
        const kv = stats.voltage_v
            ? ` ${Number(stats.voltage_v / 1000).toLocaleString(lang, { maximumFractionDigits: 1 })} kV`
            : '';
        items.push({
            icon: stats.electrified ? 'bi-lightning-charge-fill' : 'bi-lightning-charge',
            label: t('lines.stats.electrification'),
            text: stats.electrified
                ? (stats.electrified_pct != null && stats.electrified_pct < 95
                      ? t('lines.stats.partially_electrified', { pct: stats.electrified_pct })
                      : t('lines.stats.electrified')) + kv
                : t('lines.stats.not_electrified'),
            tone: stats.electrified ? 'accent' : 'muted',
        });
        return items;
    });

    /** Rail de l'en-tête : la ligne aujourd'hui, catégorie par catégorie, à l'échelle de sa plus
     *  grande longueur connue (même ruban que les phases et le tableau des décennies). */
    const todayDecade = $derived(decades.find((d) => d.is_current) ?? null);
    const railScale = $derived(
        Math.max(
            maxKnownLength(phases),
            ...decades.map((d) =>
                Object.values(d.length_by_category ?? {}).reduce((a, b) => a + b, 0),
            ),
        ),
    );
    const railStrip = $derived(
        todayDecade
            ? phaseStrip({ length_by_category: todayDecade.length_by_category }, railScale)
            : [],
    );
    const railLabel = $derived(
        `${$i18n.t('lines.decades.today')}${lang === 'fr' ? ' : ' : ': '}` +
            railStrip
                .map((s) => `${$i18n.t(`lines.categories.${s.cat}`)} ${formatKm(s.m, lang)}`)
                .join(', '),
    );

    /**
     * Bloc « Fiche » (App\Services\LineFacts) : chaque valeur avec ce qu'elle décrit (ligne
     * entière, ou l'élément Wikidata quand il ne décrit qu'une partie de la ligne), sa source et
     * sa date : [libellé, valeur, précision].
     */
    const FACT_LABELS = {
        length: 'lines.facts.length_official',
        gauge: 'lines.facts.gauge',
        tracks: 'lines.facts.tracks',
        max_speed: 'lines.facts.max_speed',
        concession: 'lines.facts.concession',
        regions: 'lines.facts.regions',
        departements: 'lines.facts.departements',
    };
    function factValue(f) {
        const num = (n, d = 1) => Number(n).toLocaleString(lang, { maximumFractionDigits: d });
        switch (f.key) {
            case 'length':
                return `${num(f.km)} km`;
            case 'gauge':
                return `${num(f.mm, 0)} mm`;
            case 'tracks':
                return String(f.n);
            case 'max_speed':
                return `${num(f.kmh, 0)} km/h`;
            case 'concession':
                return String(f.year);
            case 'regions':
                return (f.list ?? []).join(', ');
            case 'departements':
                return (f.list ?? [])
                    .map((d) => (d.code ? `${d.name} (${d.code})` : d.name))
                    .join(', ');
            default:
                return '';
        }
    }
    function factNote(f) {
        if (f.scope === 'item')
            return $i18n.t('lines.facts.note.item', { item: f.item ?? 'Wikidata' });
        if (f.source === 'sncf') {
            return f.as_of === 'today'
                ? $i18n.t('lines.facts.note.sncf_today')
                : $i18n.t('lines.facts.note.sncf_era', { year: f.as_of });
        }
        if (f.source === 'vff') return $i18n.t('lines.facts.note.vff_line');
        return $i18n.t('lines.facts.note.wikidata_line');
    }
    const facts = $derived(
        (line.facts ?? [])
            .filter((f) => FACT_LABELS[f.key])
            .map((f) => [FACT_LABELS[f.key], factValue(f), factNote(f)]),
    );
</script>

<svelte:head>
    <title>{line.name} - VFF</title>
    <meta
        name="description"
        content={line.description
            ? line.description.slice(0, 300)
            : $i18n.t('lines.meta_description', { name: line.name })}
    />
</svelte:head>

<Layout compactHeader>
    <main>
        {#if line.advocacy}
            <aside class="advocacy-banner" class:reopening={line.advocacy.kind === 'reopening'}>
                <div class="advocacy-inner">
                    <i
                        class="bi {line.advocacy.kind === 'reopening'
                            ? 'bi-megaphone-fill'
                            : 'bi-exclamation-triangle-fill'} advocacy-icon"
                    ></i>
                    <div class="advocacy-text">
                        <strong lang={line.advocacy.lang?.title}>{line.advocacy.title}</strong>
                        <p lang={line.advocacy.lang?.message}>{line.advocacy.message}</p>
                    </div>
                    {#if line.advocacy.cta_url}
                        <a
                            class="advocacy-cta"
                            href={line.advocacy.cta_url}
                            target="_blank"
                            rel="noopener"
                            lang={line.advocacy.lang?.cta_label}
                        >
                            {line.advocacy.cta_label || $i18n.t('lines.act')}
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    {/if}
                </div>
            </aside>
        {/if}

        <SheetHero
            crumbs={[
                { href: '/maps', label: $i18n.t('lines.back_to_map'), icon: 'bi-arrow-left' },
                { href: '/lines', label: $i18n.t('lines.all_lines'), icon: 'bi-list-ul' },
            ]}
            crumbsLabel={$i18n.t('lines.breadcrumbs')}
            plate={line.code}
            eyebrow={heroEyebrow}
            title={line.name}
            rail={railStrip.length ? heroRail : undefined}
            stats={heroStats}
        >
            {#if names.length}
                <p class="alt-names">
                    {$i18n.t('lines.also_known_as')}{lang === 'fr' ? ' : ' : ': '}
                    {#each names.slice(0, 5) as n, i (n.name)}<em>{n.name}</em>{i <
                        Math.min(names.length, 5) - 1
                            ? ', '
                            : ''}{/each}
                </p>
            {/if}
            {#if line.historical_operator}
                <p class="operator">
                    <i class="bi bi-building" aria-hidden="true"></i>
                    {line.historical_operator}
                </p>
            {/if}
        </SheetHero>

        {#snippet heroRail()}
            <div class="today-rail">
                <span class="rail-label">{$i18n.t('lines.decades.today')}</span>
                <span class="rail-track" role="img" aria-label={railLabel}>
                    {#each railStrip as s (s.cat)}<span
                            class="seg cat-{s.cat}"
                            style="width: {s.pct}%; --c: {categoryColor(s.cat)}"
                        ></span>{/each}
                </span>
                <ul class="rail-legend" aria-hidden="true">
                    {#each railStrip as s (s.cat)}
                        <li>
                            <i style={categorySwatch(s.cat)}></i>{$i18n.t(
                                `lines.categories.${s.cat}`,
                            )}
                            <span class="km">{formatKm(s.m, lang)}</span>
                        </li>
                    {/each}
                </ul>
            </div>
        {/snippet}

        {#snippet heroStats()}
            <StatStrip items={statItems} label={$i18n.t('lines.stats.title')} />
        {/snippet}

        <!-- Le tracé dans le temps : la ligne entière, phase par phase (vraies dates). -->
        <SheetSection
            id="trace"
            title={$i18n.t('lines.section.route_through_time')}
            intro={$i18n.t('lines.phases.intro')}
            bind:sectionEl={mapSection}
        >
            {#if phases.length && portions.length}
                <div class="phases-grid">
                    <div class="map-col">
                        <LinePhaseMap
                            {portions}
                            {phase}
                            {stations}
                            eraYear={phaseEraYear(phase)}
                            isCurrent={!!phase?.is_current}
                            height="480px"
                        />
                        {#if phase}
                            <div class="phase-detail" aria-live="polite">
                                <h3>
                                    {phaseDateLabel(phase, $i18n.t, lang)}
                                    {#if selected === best}<span class="best-tag"
                                            >{$i18n.t('lines.phases.best_badge')}</span
                                        >{/if}
                                </h3>
                                <LinePhaseText {phase} {eventsById} />
                            </div>
                        {/if}
                    </div>
                    <div class="selector-col">
                        <LinePhaseSelector {phases} bind:selected {best} />
                        {#if phases_unexplained.length}
                            <!-- Ère courante : écarts qu'aucun événement ne date. Pas une phase, pas de date inventée. -->
                            <p class="unexplained" role="note">
                                <strong>{$i18n.t('lines.phases.unexplained_title')}</strong
                                >{lang === 'fr'
                                    ? ' : '
                                    : ': '}{#each phases_unexplained as u, n (n)}{unexplainedText(
                                        u,
                                        n,
                                    )}{n < phases_unexplained.length - 1 ? ' ' : ''}{/each}
                            </p>
                        {/if}
                    </div>
                </div>
            {:else}
                <p class="empty">{$i18n.t('lines.phases.empty')}</p>
            {/if}
        </SheetSection>

        <SheetSection>
            <div class="about-grid">
                <div class="about">
                    <h2>{$i18n.t('lines.section.about')}</h2>
                    {#if line.description}
                        {#each line.description.split(/\n{2,}/) as para, i (i)}<p
                                class="description"
                            >
                                {para}
                            </p>{/each}
                        {#if line.description_source?.type === 'wikipedia'}
                            <p class="attribution">
                                {$i18n.t('lines.about.wikipedia_before')}
                                <a
                                    href={line.description_source.article_url}
                                    target="_blank"
                                    rel="noopener">{quoted(line.description_source.title, lang)}</a
                                >
                                (<a
                                    href={line.description_source.url}
                                    target="_blank"
                                    rel="noopener"
                                    >{$i18n.t('lines.about.version', {
                                        date: formatEventDate(
                                            line.description_source.fetched_at,
                                            'day',
                                            lang,
                                        ),
                                    })}</a
                                >),
                                {$i18n.t('lines.about.license')}
                                <a
                                    href={line.description_source.license_url}
                                    target="_blank"
                                    rel="license noopener">{line.description_source.license}</a
                                >.
                                {$i18n.t('lines.about.adapted')}
                            </p>
                        {:else if line.description_source?.urls?.length}
                            <p class="attribution">
                                {$i18n.t('lines.about.curated')}
                                {#each line.description_source.urls as u, i (u)}<a
                                        href={u}
                                        target="_blank"
                                        rel="noopener">[{i + 1}]</a
                                    >{' '}{/each}
                            </p>
                        {/if}
                    {:else}
                        <p class="empty">{$i18n.t('lines.no_description')}</p>
                    {/if}
                </div>

                <aside class="infobox" aria-label={$i18n.t('lines.facts.title')}>
                    <h2>{$i18n.t('lines.facts.title')}</h2>
                    <dl>
                        {#if names.length}
                            <div class="row names">
                                <dt>{$i18n.t('lines.facts.names')}</dt>
                                <dd>
                                    <ul>
                                        {#each names as n (n.name)}
                                            <li>
                                                <span class="nm">{n.name}</span>
                                                {#if n.from || n.to}<span class="yr"
                                                        >{n.from ?? '?'}{n.to && n.to !== n.from
                                                            ? `-${n.to}`
                                                            : ''}</span
                                                    >{/if}
                                                {#if n.kind === 'section'}<span class="kind"
                                                        >{$i18n.t('lines.facts.name_section')}</span
                                                    >{/if}
                                                {#if n.source}
                                                    <span class="src">
                                                        {#if n.url}<a
                                                                href={n.url}
                                                                target="_blank"
                                                                rel="noopener"
                                                                >{sourceLabel(n.source)}</a
                                                            >{:else}{sourceLabel(n.source)}{/if}
                                                    </span>
                                                {/if}
                                            </li>
                                        {/each}
                                    </ul>
                                </dd>
                            </div>
                        {/if}
                        {#if line.code}<div class="row">
                                <dt>{$i18n.t('lines.facts.code')}</dt>
                                <dd>{line.code}</dd>
                            </div>{/if}
                        {#if line.network}<div class="row">
                                <dt>{$i18n.t('lines.facts.network')}</dt>
                                <dd>{$i18n.t(`lines.networks.${line.network}`)}</dd>
                            </div>{/if}
                        {#each facts as [k, v, note], i (i)}
                            <div class="row">
                                <dt>{$i18n.t(k)}</dt>
                                <dd>
                                    {v}{#if note}<small class="fact-note">{note}</small>{/if}
                                </dd>
                            </div>
                        {/each}
                        {#if line.historical_operators?.length}
                            <div class="row">
                                <dt>{$i18n.t('lines.facts.operators')}</dt>
                                <dd>
                                    <ul>
                                        {#each line.historical_operators as o, i (i)}
                                            <li>
                                                {o.operator ?? ''}{#if o.role}<span class="kind"
                                                        >{roleLabel(o.role)}</span
                                                    >{/if}{#if o.from_year || o.to_year}
                                                    <span class="yr"
                                                        >{o.from_year ?? '?'}{o.to_year
                                                            ? `-${o.to_year}`
                                                            : ''}</span
                                                    >{/if}
                                            </li>
                                        {/each}
                                    </ul>
                                </dd>
                            </div>
                        {/if}
                        {#if mergedNames.length}
                            <div class="row">
                                <dt>{$i18n.t('lines.facts.merged')}</dt>
                                <dd>{mergedNames.map((n) => n.name).join(', ')}</dd>
                            </div>
                        {/if}
                        <div class="row links">
                            <dt>{$i18n.t('lines.facts.elsewhere')}</dt>
                            <dd>
                                {#if line.wikipedia_title}
                                    <a
                                        href={`https://fr.wikipedia.org/wiki/${encodeURIComponent(line.wikipedia_title.replaceAll(' ', '_'))}`}
                                        target="_blank"
                                        rel="noopener"><i class="bi bi-wikipedia"></i> Wikipédia</a
                                    >
                                {/if}
                                {#if line.wikidata}
                                    <a
                                        href={`https://www.wikidata.org/wiki/${line.wikidata}`}
                                        target="_blank"
                                        rel="noopener">Wikidata {line.wikidata}</a
                                    >
                                {/if}
                                {#if line.archeo_url}
                                    <a href={line.archeo_url} target="_blank" rel="noopener"
                                        >archeoferroviaire</a
                                    >
                                {/if}
                            </dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </SheetSection>

        {#if phases.length}
            <SheetSection title={$i18n.t('lines.section.history')} id="history">
                <ol class="history">
                    {#each phases as p, i (i)}
                        <li class:selected={i === selected}>
                            <div class="h-date">
                                <span>{phaseDateLabel(p, $i18n.t, lang)}</span>
                                <button
                                    type="button"
                                    class="see-map"
                                    onclick={() => showPhase(i)}
                                    aria-label={$i18n.t('lines.phases.see_on_map_for', {
                                        date: phaseDateLabel(p, $i18n.t, lang),
                                    })}
                                >
                                    <i class="bi bi-map" aria-hidden="true"></i>
                                    {$i18n.t('lines.phases.see_on_map')}
                                </button>
                            </div>
                            <div class="h-text"><LinePhaseText phase={p} {eventsById} /></div>
                        </li>
                    {/each}
                </ol>
            </SheetSection>
        {/if}

        {#if decades.length}
            <SheetSection
                title={$i18n.t('lines.section.evolution')}
                intro={$i18n.t('lines.decades.intro')}
                id="decades"
            >
                <LineDecades {decades} {phases} onselect={showPhase} />
            </SheetSection>
        {/if}

        <SheetSection
            id="stations"
            title={$i18n.t('lines.section.stations')}
            count={stations.length}
            intro={stations.length
                ? $i18n.t('lines.stations.intro')
                : $i18n.t('lines.stations.empty')}
        >
            {#if stations.length}
                <LineStations {stations} {eraYears} />
            {/if}
        </SheetSection>

        {#if sections.length}
            <SheetSection
                title={$i18n.t('lines.section.sections')}
                intro={$i18n.t('lines.sections.intro')}
            >
                <ol class="line-sections-list">
                    {#each sections as s (s.name)}
                        <li class="line-section-item">
                            <div class="ls-header">
                                <strong>{s.name}</strong>
                                {#if s.alt_names?.length}<small>({s.alt_names.join(', ')})</small
                                    >{/if}
                            </div>
                            <div class="ls-pk">
                                PK {s.pk_start_label} - PK {s.pk_end_label} ({s.length_km} km)
                            </div>
                            {#if s.opening_year_from}
                                <div class="ls-years">
                                    {$i18n.t('lines.sections.opening', {
                                        years: `${s.opening_year_from}${s.opening_year_to && s.opening_year_to !== s.opening_year_from ? `-${s.opening_year_to}` : ''}`,
                                    })}
                                </div>
                            {/if}
                            {#if s.description}<p class="ls-desc">{s.description}</p>{/if}
                            {#if s.source_url}
                                <a
                                    class="ls-source"
                                    href={s.source_url}
                                    target="_blank"
                                    rel="noopener"
                                    ><i class="bi bi-box-arrow-up-right"></i>
                                    {$i18n.t('lines.sections.source')}</a
                                >
                            {/if}
                        </li>
                    {/each}
                </ol>
            </SheetSection>
        {/if}

        {#if heritage.length}
            <SheetSection
                title={$i18n.t('lines.section.heritage')}
                intro={$i18n.t('lines.heritage.intro')}
            >
                <ol class="line-sections-list">
                    {#each heritage as h, i (i)}
                        <li class="line-section-item heritage">
                            <div class="ls-header">
                                <strong>{h.name ?? $i18n.t(`lines.heritage.kind.${h.kind}`)}</strong
                                >
                                <small>({$i18n.t(`lines.heritage.kind.${h.kind}`)})</small>
                            </div>
                            {#if h.from || h.length_km}
                                <div class="ls-pk">
                                    {h.from && h.to
                                        ? $i18n.t('lines.event_section.between', {
                                              from: h.from,
                                              to: h.to,
                                          })
                                        : ''}
                                    {h.length_km ? `(${h.length_km} km)` : ''}
                                </div>
                            {/if}
                            <div class="ls-years">{h.periods.map(heritagePeriod).join(', ')}</div>
                            {#if h.operator}<p class="ls-desc">
                                    {$i18n.t('lines.heritage.operator', { name: h.operator })}
                                </p>{/if}
                            {#if h.source_url}
                                <a
                                    class="ls-source"
                                    href={h.source_url}
                                    target="_blank"
                                    rel="noopener"
                                    ><i class="bi bi-box-arrow-up-right"></i>
                                    {$i18n.t('lines.heritage.source')}</a
                                >
                            {/if}
                        </li>
                    {/each}
                </ol>
            </SheetSection>
        {/if}

        {#if stats.signalling || stats.train_protection || stats.gauge_mm || stats.dismantled_at_min || signalling.length}
            <SheetSection title={$i18n.t('lines.tech.title')} id="tech">
                <dl class="tech-grid">
                    {#if stats.gauge_mm}
                        <div class="tech-item">
                            <dt><i class="bi bi-rulers"></i> {$i18n.t('lines.tech.gauge')}</dt>
                            <dd>
                                {stats.gauge_mm} mm{stats.gauge_mm === 1435
                                    ? ` (${$i18n.t('lines.tech.gauge_standard')})`
                                    : stats.gauge_mm === 1000
                                      ? ` (${$i18n.t('lines.tech.gauge_metre')})`
                                      : ''}
                            </dd>
                        </div>
                    {/if}
                    {#if stats.signalling}
                        <div class="tech-item">
                            <dt>
                                <i class="bi bi-stoplights"></i>
                                {$i18n.t('lines.tech.signalling')}
                            </dt>
                            <dd>{stats.signalling.split(', ').map(sigLabel).join(' ; ')}</dd>
                        </div>
                    {/if}
                    {#if stats.train_protection}
                        <div class="tech-item">
                            <dt>
                                <i class="bi bi-shield-check"></i>
                                {$i18n.t('lines.tech.train_protection')}
                            </dt>
                            <dd>{stats.train_protection.split(', ').map(sigLabel).join(' ; ')}</dd>
                        </div>
                    {/if}
                    {#if stats.dismantled_at_min}
                        <div class="tech-item">
                            <dt><i class="bi bi-trash"></i> {$i18n.t('lines.tech.dismantled')}</dt>
                            <dd>
                                {stats.dismantled_at_min === stats.dismantled_at_max
                                    ? new Date(stats.dismantled_at_min).getFullYear()
                                    : `${new Date(stats.dismantled_at_min).getFullYear()} - ${new Date(stats.dismantled_at_max).getFullYear()}`}
                            </dd>
                        </div>
                    {/if}
                </dl>

                {#if signalling.length}
                    <h3 class="sub">{$i18n.t('lines.section.signalling')}</h3>
                    <p class="section-intro">{$i18n.t('lines.signalling.intro')}</p>
                    <ol class="sig-ranges">
                        {#each signalling as r, i (i)}
                            <li class="sig-range" class:cabin={isCabin(r.signalling)}>
                                <div class="sig-pk">
                                    PK {r.pk_start_label} - PK {r.pk_end_label}
                                    <small
                                        >({(r.length_m / 1000).toLocaleString(lang, {
                                            maximumFractionDigits: 1,
                                        })} km)</small
                                    >
                                </div>
                                <div class="sig-body">
                                    <div class="sig-main">
                                        <i
                                            class="bi {isCabin(r.signalling)
                                                ? 'bi-broadcast-pin'
                                                : 'bi-stoplights'}"
                                        ></i>
                                        <span
                                            ><span class="sig-kind"
                                                >{isCabin(r.signalling)
                                                    ? $i18n.t('lines.signalling.cabin')
                                                    : $i18n.t('lines.signalling.block')}</span
                                            >
                                            {sigLabel(r.signalling)}</span
                                        >
                                    </div>
                                    {#if r.train_protection.length || r.kvb_equipped}
                                        <div class="sig-protection">
                                            <i class="bi bi-shield-check"></i>
                                            {$i18n.t('lines.signalling.protection')}
                                            {#each r.train_protection as p (p)}<span
                                                    class="prot-badge"
                                                    class:kvb={p === 'KVB' || p === 'KVBP'}
                                                    >{sigLabel(p)}</span
                                                >{/each}
                                            {#if r.kvb_equipped && !r.train_protection.includes('KVB')}<span
                                                    class="prot-badge kvb">KVB</span
                                                >{/if}
                                        </div>
                                    {/if}
                                </div>
                            </li>
                        {/each}
                    </ol>
                {/if}
            </SheetSection>
        {/if}

        {#if chronicle?.events?.length}
            <SheetSection title={$i18n.t('lines.section.chronicle')} id="chronicle">
                <LineChronicle {chronicle} />
            </SheetSection>
        {/if}

        {#if materials.length}
            <SheetSection title={$i18n.t('lines.section.materials')}>
                <div class="material-grid">
                    {#each materials as m (m.slug)}
                        <a class="material-card" href="/materials/{m.slug}">
                            <i class="bi bi-train-freight-front"></i>
                            <div>
                                <strong>{m.name}</strong>
                                <span>{m.builder ?? ''}</span>
                                <small
                                    >{m.in_service_from}{m.in_service_to
                                        ? ` - ${m.in_service_to}`
                                        : ` - ${$i18n.t('lines.still_running')}`}</small
                                >
                            </div>
                        </a>
                    {/each}
                </div>
            </SheetSection>
        {/if}

        <!-- Photos, cartes postales, plans : chargés à la demande (API paginée), crédits par image. -->
        <LineGallery slug={line.slug} />

        {#if chronicle?.notes?.length || sources.length}
            <SheetSection title={$i18n.t('lines.section.notes')} id="notes">
                <LineSourceNotes notes={chronicle?.notes ?? []} />
                {#if sources.length}
                    <h3 class="sub">{$i18n.t('lines.notes.data_sources')}</h3>
                    <ul class="data-sources">
                        {#each sources as s (s.type)}
                            <li>
                                <strong>{sourceLabel(s.type)}</strong>
                                {#if s.url}<a href={s.url} target="_blank" rel="noopener"
                                        >{linkLabel(s.url, 48, lang)}</a
                                    >{/if}
                                {#if s.fetched_at}<span class="when"
                                        >{$i18n.t('lines.notes.fetched', {
                                            date: formatEventDate(s.fetched_at, 'day', lang),
                                        })}</span
                                    >{/if}
                            </li>
                        {/each}
                    </ul>
                {/if}
            </SheetSection>
        {/if}
    </main>
</Layout>

<style>
    .fact-note {
        display: block;
        font-size: 12px;
        color: var(--vff-text-muted);
    }
    main {
        background-color: var(--vff-bg);
        padding-bottom: 64px;
    }

    /* Bandeau « ligne menacée » / mobilisation */
    .advocacy-banner {
        background: linear-gradient(90deg, #b91c1c, #dc2626);
        color: #fff;
    }
    .advocacy-banner.reopening {
        background: linear-gradient(90deg, #0f766e, #0d9488);
    }
    .advocacy-inner {
        max-width: 1180px;
        margin: 0 auto;
        padding: 0.85rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .advocacy-icon {
        font-size: 1.6rem;
        flex-shrink: 0;
    }
    .advocacy-text {
        flex: 1;
        min-width: 0;
    }
    .advocacy-text strong {
        display: block;
        font-size: 1.05rem;
    }
    .advocacy-text p {
        margin: 0.2rem 0 0;
        font-size: 0.9rem;
        opacity: 0.95;
        line-height: 1.4;
    }
    .advocacy-cta {
        flex-shrink: 0;
        background: #fff;
        color: #b91c1c;
        font-weight: 700;
        padding: 0.55rem 1rem;
        border-radius: 999px;
        text-decoration: none;
        white-space: nowrap;
    }
    @media (max-width: 640px) {
        .advocacy-inner {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    /* En-tête (SheetHero) : noms, exploitant, rail de la ligne aujourd'hui */
    .alt-names {
        max-width: 90ch;
        margin: 4px 0 0;
        font-size: 15.5px;
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .alt-names em {
        font-style: italic;
        color: var(--vff-text);
    }
    .operator {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 8px 0 0;
        font-family: var(--vff-font-heading);
        font-size: 14px;
        color: var(--vff-text-muted);
    }
    .today-rail {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        gap: 8px 14px;
    }
    .rail-label {
        font-family: var(--vff-font-heading);
        font-size: 13px;
        font-weight: 600;
        color: var(--vff-text);
    }
    /* Le fond du rail = la longueur de la ligne à son apogée ; les segments = ce qui existe aujourd'hui. */
    .rail-track {
        display: flex;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
        background: linear-gradient(var(--vff-border-strong), var(--vff-border-strong)) 0 50% / 100%
            1px no-repeat;
    }
    .rail-track .seg {
        background: var(--c);
        min-width: 2px;
    }
    .rail-track .seg.cat-closed,
    .rail-track .seg.cat-construction {
        background: repeating-linear-gradient(90deg, var(--c) 0 10px, transparent 10px 14px);
    }
    .rail-track .seg.cat-dismantled,
    .rail-track .seg.cat-razed,
    .rail-track .seg.cat-gone,
    .rail-track .seg.cat-velorail {
        background: repeating-linear-gradient(90deg, var(--c) 0 3px, transparent 3px 9px);
    }
    .rail-legend {
        grid-column: 2;
        display: flex;
        flex-wrap: wrap;
        gap: 4px 20px;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .rail-legend li {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-family: var(--vff-font-heading);
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .rail-legend i {
        display: inline-block;
        width: 18px;
        border-radius: 2px;
    }
    .rail-legend .km {
        color: var(--vff-text);
        font-variant-numeric: tabular-nums;
    }
    @media (max-width: 700px) {
        .today-rail {
            grid-template-columns: 1fr;
        }
        .rail-legend {
            grid-column: 1;
        }
    }

    .sub {
        font-family: var(--vff-font-heading);
        font-size: 18px;
        font-weight: 600;
        margin: 32px 0 8px;
    }
    .empty {
        font-size: 15px;
        font-style: italic;
        color: var(--vff-text-muted);
    }
    .section-intro {
        max-width: 72ch;
        margin: 0 0 16px;
        font-size: 15px;
        line-height: 1.6;
        color: var(--vff-text-muted);
    }

    /* Le tracé dans le temps */
    .unexplained {
        margin: 12px 0 0;
        font-size: 13px;
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .phases-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(280px, 1fr);
        gap: 24px;
        align-items: start;
    }
    .selector-col {
        height: 480px;
        position: sticky;
        top: 80px;
    }
    .phase-detail {
        margin-top: 16px;
        padding: 14px 18px 16px;
        border-left: 4px solid var(--vff-accent);
        background: var(--vff-bg-elevated);
        border-radius: 0 var(--vff-radius-md) var(--vff-radius-md) 0;
    }
    .phase-detail h3 {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 10px;
        margin: 0 0 8px;
        font-family: var(--vff-font-display);
        font-size: 22px;
    }
    .best-tag {
        font-family: var(--vff-font-heading);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--vff-accent-ink);
    }

    /* À propos + fiche */
    .about-grid h2 {
        margin: 0 0 14px;
        font-family: var(--vff-font-display);
        font-size: 28px;
        line-height: 1.2;
        color: var(--vff-text);
    }
    .about-grid h2:after {
        display: none;
    }
    .about-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr);
        gap: 36px;
        align-items: start;
    }
    .description {
        max-width: 72ch;
        font-size: 16.5px;
        line-height: 1.7;
        color: var(--vff-text);
        margin: 0 0 12px;
    }
    .attribution {
        margin: 12px 0 0;
        font-size: 13px;
        color: var(--vff-text-muted);
        line-height: 1.5;
    }
    .attribution a {
        color: var(--vff-accent-ink);
    }
    .infobox {
        border: 1px solid var(--vff-border);
        border-top: 4px solid var(--vff-accent);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        padding: 16px 18px 6px;
    }
    .infobox h2 {
        font-size: 19px;
        margin-bottom: 8px;
    }
    .infobox dl {
        margin: 0;
    }
    .infobox .row {
        display: grid;
        grid-template-columns: 9.5em 1fr;
        gap: 10px;
        padding: 8px 0;
        border-top: 1px solid var(--vff-border);
        font-size: 14px;
    }
    .infobox dt {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        color: var(--vff-text-muted);
        font-size: 13px;
    }
    .infobox dd {
        margin: 0;
        color: var(--vff-text);
        min-width: 0;
    }
    .infobox li,
    .data-sources li {
        font-size: 14px;
    }
    .infobox ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .infobox .nm {
        font-weight: 700;
    }
    /* Autres noms : le nom sur sa ligne, puis ses années, sa nature et sa source en petit. */
    .infobox .names ul {
        gap: 7px;
    }
    .infobox .names li {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        column-gap: 8px;
        line-height: 1.35;
    }
    .infobox .names .nm {
        flex-basis: 100%;
    }
    .infobox .names .yr,
    .infobox .names .kind,
    .infobox .names .src {
        margin-left: 0;
    }
    .infobox .yr,
    .infobox .kind,
    .infobox .src {
        margin-left: 6px;
        font-size: 12.5px;
        color: var(--vff-text-muted);
    }
    .infobox a {
        color: var(--vff-accent-ink);
    }
    /* Cible tactile de 24 px au moins sur téléphone, sans décaler les lignes de la fiche. */
    @media (max-width: 768px) {
        .infobox dd a,
        .data-sources a {
            display: inline-block;
            padding: 4px 0;
            margin: -4px 0;
        }
    }
    .infobox .links dd {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 14px;
    }

    /* Histoire */
    .history {
        list-style: none;
        margin: 0;
        padding: 0;
        border-left: 2px solid var(--vff-border-strong);
    }
    .history li {
        font-size: 15px;
        display: grid;
        grid-template-columns: 13em minmax(0, 1fr);
        gap: 18px;
        padding: 12px 0 14px 18px;
        position: relative;
    }
    .history li::before {
        content: '';
        position: absolute;
        left: -7px;
        top: 18px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--vff-bg);
        border: 2px solid var(--vff-accent);
    }
    .history li.selected::before {
        background: var(--vff-accent);
    }
    .h-date {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: flex-start;
    }
    .h-date span {
        font-family: var(--vff-font-display);
        font-size: 18px;
        font-weight: 700;
        line-height: 1.25;
    }
    .see-map {
        display: inline-flex;
        gap: 6px;
        align-items: center;
        border: 0;
        background: none;
        padding: 5px 0;
        margin: -5px 0;
        color: var(--vff-accent-ink);
        font-size: 13px;
        cursor: pointer;
    }
    .see-map:hover {
        text-decoration: underline;
    }
    .see-map:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }

    /* Sections nommées et exploitation associative */
    .line-sections-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .line-section-item {
        border: 1px solid var(--vff-border);
        border-left: 4px solid #7c3aed;
        border-radius: var(--vff-radius-md);
        padding: 14px 18px;
        background: var(--vff-bg-elevated);
    }
    .line-section-item.heritage {
        border-left-color: #16a34a;
    }
    .ls-header strong {
        font-family: var(--vff-font-heading);
        font-size: 17px;
    }
    .ls-header small {
        color: var(--vff-text-muted);
        margin-left: 6px;
        font-size: 13px;
    }
    .ls-pk {
        font-size: 13px;
        color: var(--vff-text);
        margin-top: 4px;
        font-variant-numeric: tabular-nums;
    }
    .ls-years {
        color: var(--vff-text-muted);
        font-size: 13px;
        margin-top: 4px;
    }
    .ls-desc {
        margin: 8px 0 4px;
        font-size: 14px;
        line-height: 1.5;
    }
    .ls-source {
        font-size: 12.5px;
        color: var(--vff-accent-ink);
        text-decoration: none;
    }

    /* Technique et signalisation */
    .tech-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 14px;
        margin: 0;
    }
    .tech-item {
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 12px 16px;
    }
    .tech-item dt {
        font-family: var(--vff-font-heading);
        font-size: 13px;
        font-weight: 600;
        color: var(--vff-text-muted);
        margin: 0 0 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .tech-item dt i {
        color: var(--vff-accent-ink);
    }
    .tech-item dd {
        margin: 0;
        font-size: 15px;
        color: var(--vff-text);
        line-height: 1.45;
    }
    .sig-ranges {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .sig-range {
        display: grid;
        grid-template-columns: 230px 1fr;
        gap: 16px;
        align-items: center;
        padding: 10px 16px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-left: 3px solid var(--vff-accent);
        border-radius: var(--vff-radius-md);
    }
    .sig-range.cabin {
        border-left-color: #7c3aed;
    }
    .sig-pk {
        font-size: 13px;
        font-variant-numeric: tabular-nums;
    }
    .sig-pk small {
        color: var(--vff-text-muted);
        margin-left: 4px;
    }
    .sig-body {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .sig-main {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
    }
    .sig-main i {
        color: var(--vff-accent-ink);
        font-size: 17px;
    }
    .sig-range.cabin .sig-main i {
        color: #7c3aed;
    }
    .sig-kind {
        color: var(--vff-text-muted);
        font-size: 13px;
        margin-right: 4px;
    }
    .sig-protection {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--vff-text-muted);
        flex-wrap: wrap;
    }
    .prot-badge {
        font-family: var(--vff-font-heading);
        font-size: 11.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: var(--vff-radius-pill);
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .prot-badge.kvb {
        background-color: rgba(22, 163, 74, 0.14);
        color: #15803d;
    }

    /* Matériels */
    .material-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 14px;
    }
    .material-card {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 14px 16px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        text-decoration: none;
        color: var(--vff-text);
    }
    .material-card:hover {
        border-color: var(--vff-accent);
    }
    .material-card i {
        font-size: 20px;
        color: var(--vff-accent-ink);
    }
    .material-card div {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .material-card span,
    .material-card small {
        color: var(--vff-text-muted);
        font-size: 13px;
    }

    /* Notes */
    .data-sources {
        margin: 0;
        padding-left: 1.2em;
        font-size: 13.5px;
        line-height: 1.6;
    }
    .data-sources a {
        color: var(--vff-accent-ink);
        margin-left: 6px;
        word-break: break-all;
    }
    .data-sources .when {
        color: var(--vff-text-muted);
        margin-left: 6px;
    }

    @media (max-width: 900px) {
        .phases-grid,
        .about-grid {
            grid-template-columns: 1fr;
        }
        .selector-col {
            position: static;
            height: 420px;
        }
        .history li {
            grid-template-columns: 1fr;
            gap: 6px;
        }
    }
    @media (max-width: 700px) {
        .sig-range {
            grid-template-columns: 1fr;
        }
        .infobox .row {
            grid-template-columns: 1fr;
            gap: 2px;
        }
        .about-grid h2 {
            font-size: 24px;
        }
    }
</style>
