<script>
    import { untrack } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        NBSP,
        colon,
        firstOfMonth,
        formatLocale,
        formatNumber,
        quoted,
        semicolon,
        tieUnits,
        withUnit,
    } from '$lib/typo.js';
    import { cleanUrl, linkLabel } from '$lib/linkLabel.js';
    import LinePhaseMap from '$components/Lines/LinePhaseMap.svelte';
    import LinePhaseSelector from '$components/Lines/LinePhaseSelector.svelte';
    import LinePhaseText from '$components/Lines/LinePhaseText.svelte';
    import LineSpaceTime from '$components/Lines/LineSpaceTime.svelte';
    import LineChronicle from '$components/Lines/LineChronicle.svelte';
    import LineDecades from '$components/Lines/LineDecades.svelte';
    import LineStations from '$components/Lines/LineStations.svelte';
    import LineSourceNotes from '$components/Lines/LineSourceNotes.svelte';
    import SheetGallery from '$components/Sheet/SheetGallery.svelte';
    import LineAccidents from '$components/Lines/LineAccidents.svelte';
    import LineLevelCrossings from '$components/Lines/LineLevelCrossings.svelte';
    import LineGreenways from '$components/Lines/LineGreenways.svelte';
    import LineArchives from '$components/Lines/LineArchives.svelte';
    import LineSignallingHistory from '$components/Lines/LineSignallingHistory.svelte';
    import LineMaterials from '$components/Lines/LineMaterials.svelte';
    import LineProfile from '$components/Lines/LineProfile.svelte';
    import LineSpeeds from '$components/Lines/LineSpeeds.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { Link } from '@inertiajs/svelte';
    import TraceDownload from '$components/Lines/TraceDownload.svelte';
    import ReportError from '$components/Sheet/ReportError.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import SheetToc from '$components/Sheet/SheetToc.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { breadcrumbSchema, lineSchema } from '$lib/structuredData.js';
    import { signallingSystems, signallingWithGaps, systemsLabel } from '$lib/signallingMap.js';
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
        stageNotes,
    } from '$lib/linePhases.js';

    /** Props de LineController::show (les médias sont chargés à part par SheetGallery, via l'API).
     * @type {{
     *   line: Record<string, any>, stats: Record<string, any>, segments: Array<any>, stations: Array<any>,
     *   events: Array<any>, materials: Array<any>, signalling?: Array<any>, sections?: Array<any>,
     *   era_summary?: Array<any>, heritage?: Array<any>, sources?: Array<any>,
     *   portions_count?: number, portions_url?: string|null, phases?: Array<any>, best_phase?: number|null,
     *   phases_unexplained?: Array<{ from: string, to: string, portions: Array<number>, length_m: number }>,
     *   decades?: Array<any>, chronicle?: { events: Array<any>, groups: Array<any>, notes: Array<any>, conflicts: Array<any> },
     *   accidents?: Array<any>,
     *   level_crossings?: { total: number, summary: Array<any>, items: Array<any> },
     *   greenways?: { total_length_m: number, osm_date: string|null, items: Array<any> },
     *   signalling_history?: Array<any>,
     *   profile?: any, speeds?: any, trace_export_eras?: Array<number>,
     *   archives?: { total: number, fetched_on: string|null, items: Array<any> },
     *   archive_records?: { total: number, fetched_on: string|null, items: Array<any> },
     *   story_events?: Array<Array<number>>,
     *   journal?: Array<{ slug: string, title: string, lang: string }>,
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
        // Portions de la carte : leur nombre ici, leur géométrie lue à part quand la carte s'affiche
        // (App\Http\Controllers\LinePortionsController, lib/linePortions.js).
        portions_count = 0,
        portions_url = null,
        phases = [],
        phases_unexplained = [],
        best_phase = null,
        decades = [],
        chronicle = { events: [], groups: [], notes: [], conflicts: [] },
        // Accidents rattachés à la ligne (App\Services\Accidents\LineAccidents).
        accidents = [],
        // Passages à niveau de la ligne (App\Services\LevelCrossings\LineLevelCrossings).
        level_crossings = { total: 0, summary: [], items: [] },
        // Voies vertes OSM sur l'ancienne emprise (App\Services\Greenways\LineGreenways::forSheet).
        greenways = { total_length_m: 0, osm_date: null, items: [] },
        // Mises en service de signalisation datées, lues dans Wikipédia (App\Services\Signalling\LineSignallingHistory::forSheet).
        signalling_history = [],
        // Profil en long, s'il est calculé (App\Services\Profiles\LineProfiles).
        profile = null,
        // Vitesses maximales par PK (SNCF Réseau) et changements datés (App\Services\Speed\LineSpeeds).
        speeds = null,
        // Années des ères dont le tracé peut être téléchargé (App\Services\TraceExport\LineTraceExport).
        trace_export_eras = [],
        // Documents de Gallica qui parlent de la ligne (App\Services\Archives\LineArchives::forSheet).
        archives = { total: 0, fetched_on: null, items: [] },
        // Notices des Archives SNCF, dans la même section (App\Services\Archives\LineArchives::recordsForSheet).
        archive_records = { total: 0, fetched_on: null, items: [] },
        // Événements qui ont une histoire (App\Services\Stories\EventStories::available) : lien de la chronologie.
        story_events = [],
        // Articles du journal de bord qui parlent de la ligne (App\Services\Journal\JournalRepository::aboutLine).
        journal = [],
    } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));

    // Sommaire collant : toutes les sections possibles, dans l'ordre de la page ; SheetToc n'affiche
    // que celles qui sont présentes.
    const TOC = [
        'trace',
        'spacetime',
        'about',
        'history',
        'decades',
        'stations',
        'sections',
        'heritage',
        'tech',
        'profile',
        'speeds',
        'level-crossings',
        'greenways',
        'chronicle',
        'materials',
        'accidents',
        'archives',
        'gallery',
        'notes',
    ];
    const tocItems = $derived(TOC.map((id) => ({ id, label: t(`lines.toc.${id}`) })));
    // Noms, textes de Wikipédia et descriptions des sources : en français, quelle que soit la page.
    const srcLang = $derived(line.name_lang ?? (lang === 'fr' ? undefined : 'fr'));
    const descLang = $derived(line.description_lang ?? srcLang);
    const km = (m) => tieUnits(formatKm(m, formatLocale(lang)));
    const dateOf = (iso, precision = 'day') =>
        firstOfMonth(formatEventDate(iso, precision, formatLocale(lang)), lang);
    const phaseDate = (p) => firstOfMonth(phaseDateLabel(p, $i18n.t, formatLocale(lang)), lang);

    // Dernière ère passée reconstituée (31/12), pour les écarts non datés de l'ère courante.
    const lastPastEra = $derived([...decades].reverse().find((d) => !d.is_current)?.year ?? null);
    const catLabel = (c) => {
        const label = t(`lines.categories.${c}`);
        return lang === 'de' ? label : label.charAt(0).toLowerCase() + label.slice(1);
    };
    /** « fret seul sur 21,2 km (voyageurs et fret dans l'état reconstitué au 31 décembre 2020) ; aucune source… » */
    function unexplainedText(u, n) {
        const text = tieUnits(
            t(
                lastPastEra
                    ? 'lines.phases.unexplained_item'
                    : 'lines.phases.unexplained_item_noyear',
                {
                    to: catLabel(u.to),
                    from: catLabel(u.from),
                    km: km(u.length_m),
                    year: lastPastEra,
                },
            ),
        );
        // Après les deux-points : minuscule ; les phrases suivantes commencent par une capitale.
        return n === 0 ? text : text.charAt(0).toUpperCase() + text.slice(1);
    }
    // Vue par défaut : la ligne entière à son plus grand développement.
    const best = $derived(bestPhaseIndex(phases, best_phase));
    let selected = $state(untrack(() => bestPhaseIndex(phases, best_phase)));
    const phase = $derived(phases[selected] ?? null);
    const eventsById = $derived(
        Object.fromEntries((chronicle?.events ?? []).map((e) => [e.id, e])),
    );
    const eraYears = $derived(
        [...new Set(stations.flatMap((s) => Object.keys(s.eras ?? {}).map(Number)))].sort(
            (a, b) => a - b,
        ),
    );

    const metaDescription = $derived(
        line.description
            ? line.description.slice(0, 300)
            : t('lines.meta_description', { name: line.name }),
    );
    // Premières images de la galerie (chargées par SheetGallery) pour les données structurées.
    let seoImages = $state.raw([]);

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
        return tieUnits(label === key ? code : label);
    }
    const isCabin = (sig) => /^(TVM-|ETCS_)/.test(sig);
    const hasCabin = $derived(signalling.some((r) => isCabin(r.signalling)));
    // Tranches à deux systèmes installés ensemble (ETCS niveau 2 et TVM sur les LGV récentes).
    const hasDual = $derived(signalling.some((r) => signallingSystems(r).length > 1));
    // Tranches dans l'ordre des PK, avec les trous des données intercalés (clé stable par tranche).
    const signallingRows = $derived(
        signallingWithGaps(signalling.map((r, i) => ({ ...r, key: `r-${i}` }))),
    );
    const pkWord = $derived(t('lines.sheet.tech.pk'));
    const pkRange = (a, b) => `${pkWord}${NBSP}${a} - ${pkWord}${NBSP}${b}`;

    function heritagePeriod(p) {
        const from = dateOf(p.start.date, p.start.precision);
        if (p.end)
            return t('lines.heritage.period', { from, to: dateOf(p.end.date, p.end.precision) });
        return p.attested
            ? t('lines.heritage.attested', { date: from })
            : t('lines.heritage.since', { date: from });
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
    // Tous les noms viennent de la même source : elle est citée une fois sous la liste.
    const namesSource = $derived(
        names.length > 1 &&
            names.every((n) => n.source === names[0].source && n.url === names[0].url)
            ? names[0]
            : null,
    );
    const HERO_NAMES = 5;
    /** Fiche : une liste longue (noms, exploitants) montre ses quatre premières entrées et replie les
     *  autres (« Afficher les 4 autres ») ; la fiche ne dépasse plus le texte de beaucoup (pas de grand
     *  vide sous le texte, à gauche). Jamais replié pour une seule entrée de plus. */
    const FICHE_LIST = 4;
    /** @template T @param {T[]} list @returns {[T[], T[]]} */
    const foldList = (list) =>
        list.length > FICHE_LIST + 1
            ? [list.slice(0, FICHE_LIST), list.slice(FICHE_LIST)]
            : [list, []];

    const heroEyebrow = $derived(
        [
            line.network ? t(`lines.networks.${line.network}`) : null,
            line.current_state === 'open'
                ? t(`lines.traffics.${line.current_traffic ?? 'unknown'}`)
                : line.current_state
                  ? t(`lines.states.${line.current_state}`)
                  : null,
        ].filter(Boolean),
    );

    const num = (n) => formatNumber(n, lang, { maximumFractionDigits: 1 });
    const pct = (n) => (lang === 'en' ? `${num(n)}%` : `${num(n)}${NBSP}%`);

    const kmText = (n) => withUnit(n, 'km', lang, { maximumFractionDigits: 1 });
    const kvText = (v) => `${formatNumber(v / 1000, lang, { maximumFractionDigits: 1 })}${NBSP}kV`;

    /** Chiffres clés de l'en-tête (StatStrip) : UNE longueur (tracé VFF, la même que le rail, les
     *  phases, les décennies et la chronique), la part en service, l'état si la ligne est fermée. */
    const statItems = $derived.by(() => {
        const items = [];
        items.push({
            value: num(stats.length_km),
            label: t('lines.sheet.stats.km_line'),
            hint: t('lines.stats.km_line_extent_hint'),
        });
        if (shutToday)
            items.push({
                icon: todayState === 'gone' ? 'bi-x-circle' : 'bi-slash-circle',
                text: t(`lines.states.${todayState}`),
                label: t('lines.sheet.stats.today'),
                tone: 'muted',
            });
        else if (stats.active_length_km != null && stats.active_length_km !== stats.length_km)
            items.push({
                value: num(stats.active_length_km),
                label: t('lines.sheet.stats.km_active'),
            });
        items.push({
            value: num(stats.station_count),
            label: t('lines.sheet.stats.stations', { count: stats.station_count ?? 0 }),
            hint: t('lines.stats.stations_hint', {
                open: stats.station_open_count ?? 0,
                count: stats.station_count ?? 0,
            }),
        });
        items.push({
            value: num(phases.length),
            label: t('lines.sheet.stats.phases', { count: phases.length }),
        });
        if (stats.max_speed_kmh)
            items.push({
                value: num(stats.max_speed_kmh),
                // Vitesse d'une partie seulement de la ligne : on dit sur combien de kilomètres.
                label:
                    stats.max_speed_km != null &&
                    stats.length_km &&
                    stats.max_speed_km < stats.length_km * 0.9
                        ? t('lines.sheet.stats.speed_max_on', { km: kmText(stats.max_speed_km) })
                        : t('lines.sheet.stats.speed_max'),
            });
        items.push(electrificationItem());
        return items;
    });

    /** Électrification (stats.electrification : none | marginal | partial | full, tensions et km).
     *  Quelques kilomètres en gare ne font pas une ligne électrifiée. */
    function electrificationItem() {
        const label = t('lines.sheet.stats.electrification');
        const e = stats.electrification ?? {
            state: stats.electrified ? 'full' : 'none',
            voltages: stats.voltage_v ? [{ voltage_v: stats.voltage_v }] : [],
        };
        const kv = (e.voltages ?? [])
            .filter((v) => v.voltage_v)
            .map((v) => kvText(v.voltage_v))
            .join(lang === 'fr' ? ' et ' : lang === 'de' ? ' und ' : ' and ');
        if (e.state === 'none' || !e.state)
            return {
                icon: 'bi-lightning-charge',
                label,
                text: t('lines.sheet.stats.not_electrified'),
                tone: 'muted',
            };
        if (e.state === 'marginal')
            return {
                icon: 'bi-lightning-charge',
                label,
                // « Non électrifiée sauf 2,3 km » en une phrase (la note séparée débordait sur téléphone).
                text: `${t('lines.sheet.stats.not_electrified')} ${t('lines.sheet.stats.electrified_bit', { pct: kmText(e.km) })}`,
                hint: t('lines.sheet.stats.electrified_bit_hint', {
                    pct: kmText(e.km),
                    kv: kv || '?',
                }),
                tone: 'muted',
            };
        const text =
            e.state === 'partial'
                ? t('lines.sheet.stats.electrified_part', { pct: pct(e.pct) })
                : t('lines.sheet.stats.electrified');
        return {
            icon: 'bi-lightning-charge-fill',
            label,
            text: kv ? `${text}, ${kv}` : text,
            tone: 'accent',
        };
    }

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
        `${t('lines.decades.today')}${colon(lang)}` +
            railStrip.map((s) => `${t(`lines.categories.${s.cat}`)} ${km(s.m)}`).join(', '),
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
        switch (f.key) {
            case 'length':
                return withUnit(f.km, 'km', lang, { maximumFractionDigits: 1 });
            case 'gauge':
                return withUnit(f.mm, 'mm', lang, { maximumFractionDigits: 0 });
            case 'tracks':
                return String(f.n);
            case 'max_speed':
                return withUnit(f.kmh, 'km/h', lang, { maximumFractionDigits: 0 });
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
        if (f.scope === 'item') return t('lines.facts.note.item', { item: f.item ?? 'Wikidata' });
        if (f.source === 'sncf') {
            return f.as_of === 'today'
                ? t('lines.facts.note.sncf_today')
                : t('lines.facts.note.sncf_era', { year: f.as_of });
        }
        if (f.source === 'vff') return t('lines.facts.note.vff_line');
        return t('lines.facts.note.wikidata_line');
    }
    const facts = $derived(
        (line.facts ?? [])
            .filter((f) => FACT_LABELS[f.key])
            .map((f) => ({
                key: f.key,
                // Libellé et précision rédigés côté serveur (LineFacts) ; repli sur l'ancien calcul.
                label: f.label ?? t(FACT_LABELS[f.key]),
                value: factValue(f),
                note: (f.note ?? factNote(f)).replaceAll("'", '’'),
                // Noms de régions et de départements : français sur toutes les pages.
                lang: ['regions', 'departements'].includes(f.key) ? srcLang : undefined,
            })),
    );

    const gaugeText = $derived(
        stats.gauge_mm
            ? withUnit(stats.gauge_mm, 'mm', lang) +
                  (stats.gauge_mm === 1435
                      ? ` (${t('lines.tech.gauge_standard')})`
                      : stats.gauge_mm === 1000
                        ? ` (${t('lines.tech.gauge_metre')})`
                        : '')
            : '',
    );
    const signallingIntro = $derived(
        t('lines.sheet.tech.signalling_intro') +
            (line.is_lgv && hasCabin ? ` ${t('lines.sheet.tech.signalling_intro_cab')}` : '') +
            (hasDual ? ` ${t('lines.sheet.tech.signalling_intro_dual')}` : '') +
            ` ${t(signalling_history.length ? 'lines.sheet.tech.signalling_today_history' : 'lines.sheet.tech.signalling_today')}`,
    );
    /** Liste « code sur N km » (stats.*_coverage), sinon la liste simple des codes. */
    function coverageText(coverage, codes) {
        if (coverage?.length)
            return coverage
                .map((c) =>
                    t('lines.sheet.stats.coverage', { label: sigLabel(c.code), km: kmText(c.km) }),
                )
                .join(semicolon(lang));
        return String(codes ?? '')
            .split(', ')
            .filter(Boolean)
            .map(sigLabel)
            .join(semicolon(lang));
    }
</script>

<svelte:head>
    <title>{line.name} - VFF</title>
</svelte:head>

<!-- Partage et données structurées : la ligne (avec ses premières images, auteur et licence) et
     le fil d'Ariane. -->
<SeoHead
    title={line.name}
    description={metaDescription}
    path={`/lines/${line.slug}`}
    type="article"
    image={seoImages[0]
        ? { url: seoImages[0].display_url ?? seoImages[0].thumb_url, alt: seoImages[0].caption }
        : null}
    jsonLd={(origin) => [
        lineSchema({ origin, line, description: line.description, images: seoImages }),
        breadcrumbSchema(origin, [
            { name: t('common.app_full'), path: '/' },
            { name: t('nav.lines'), path: '/lines' },
            { name: line.name, path: `/lines/${line.slug}` },
        ]),
    ]}
/>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[
                { href: '/maps', label: t('lines.back_to_map'), icon: 'bi-arrow-left' },
                { href: '/lines', label: t('lines.all_lines'), icon: 'bi-list-ul' },
            ]}
            crumbsLabel={t('lines.breadcrumbs')}
            plate={line.code}
            eyebrow={heroEyebrow}
            title={line.name}
            titleLang={srcLang}
            rail={railStrip.length ? heroRail : undefined}
            stats={heroStats}
        >
            {#if names.length}
                <p class="alt-names">
                    {t('lines.also_known_as')}{colon(
                        lang,
                    )}{#each names.slice(0, HERO_NAMES) as n, i (n.name)}<span
                            class="nm"
                            lang={srcLang}>{n.name}</span
                        >{i < Math.min(names.length, HERO_NAMES) - 1
                            ? ', '
                            : ''}{/each}{#if names.length > HERO_NAMES},
                        <a href="#fiche"
                            >{t('lines.sheet.also_known_more', {
                                count: names.length - HERO_NAMES,
                            })}</a
                        >{/if}
                </p>
            {/if}
            {#if line.historical_operator}
                <p class="operator">
                    <i class="bi bi-building" aria-hidden="true"></i>
                    <span lang={srcLang}>{line.historical_operator}</span>
                </p>
            {/if}
        </SheetHero>

        {#snippet heroRail()}
            <div class="today-rail">
                <span class="rail-label">{t('lines.decades.today')}</span>
                <span class="rail-track" role="img" aria-label={railLabel}>
                    {#each railStrip as s (s.cat)}<span
                            class="seg cat-{s.cat}"
                            style:width="{s.pct}%"
                            style:--c={categoryColor(s.cat)}
                        ></span>{/each}
                </span>
                <ul class="rail-legend" aria-hidden="true">
                    {#each railStrip as s (s.cat)}
                        <li>
                            <i style={categorySwatch(s.cat)}></i>{t(`lines.categories.${s.cat}`)}
                            <span class="km">{km(s.m)}</span>
                        </li>
                    {/each}
                </ul>
            </div>
        {/snippet}

        {#snippet heroStats()}
            <StatStrip items={statItems} label={t('lines.stats.title')} />
        {/snippet}

        <SheetToc items={tocItems} label={t('lines.toc.label')} />

        <!-- Le tracé dans le temps : la ligne entière, phase par phase (vraies dates). -->
        <SheetSection
            id="trace"
            title={t('lines.section.route_through_time')}
            intro={t('lines.phases.intro')}
            bind:sectionEl={mapSection}
        >
            {#if phases.length && portions_count && portions_url}
                <div class="phases-grid">
                    <div class="map-col">
                        <LinePhaseMap
                            portionsUrl={portions_url}
                            {phase}
                            {stations}
                            name={line.name}
                            eraYear={phaseEraYear(phase)}
                            isCurrent={!!phase?.is_current}
                            height="var(--phase-map-h)"
                        />
                        {#if phase}
                            <div class="phase-detail" aria-live="polite">
                                <h3>{phaseDate(phase)}</h3>
                                <LinePhaseText {phase} {eventsById} />
                            </div>
                        {/if}
                    </div>
                    <div class="selector-col">
                        <LinePhaseSelector {phases} bind:selected {best} />
                        {#if phases_unexplained.length}
                            <!-- Ère courante : écarts qu'aucun événement ne date. Pas une phase, pas de date inventée. -->
                            <p class="unexplained" role="note">
                                <strong>{t('lines.phases.unexplained_title')}</strong>{colon(
                                    lang,
                                )}{#each phases_unexplained as u, n (n)}{unexplainedText(u, n)}{n <
                                    phases_unexplained.length - 1
                                        ? ' '
                                        : ''}{/each}
                            </p>
                        {/if}
                    </div>
                </div>
            {:else}
                <p class="empty">{t('lines.phases.empty')}</p>
            {/if}
            <TraceDownload
                slug={line.slug}
                eras={trace_export_eras}
                currentYear={todayDecade?.year ?? null}
                known={portions_count > 0}
            />
        </SheetSection>

        <!-- Où et quand : la ligne déroulée de bout en bout, une bande par phase, liée à la carte. -->
        {#if phases.length > 1 && portions_count && portions_url}
            <SheetSection
                id="spacetime"
                title={t('lines.section.spacetime')}
                intro={t('lines.spacetime.intro')}
            >
                <LineSpaceTime
                    portionsUrl={portions_url}
                    {phases}
                    {stations}
                    {eventsById}
                    bind:selected
                    onshow={showPhase}
                />
            </SheetSection>
        {/if}

        <SheetSection id="about" label={t('lines.section.about')}>
            <div class="about-grid">
                <div class="about">
                    <h2 class="vff-section-title">{t('lines.section.about')}</h2>
                    {#if line.description}
                        <div class="description vff-hyphenate" lang={descLang}>
                            {#each line.description.split(/\n{2,}/) as para, i (i)}<p>
                                    {para}
                                </p>{/each}
                        </div>
                        {#if line.description_source?.type === 'wikipedia'}
                            <p class="attribution">
                                {t('lines.about.wikipedia_before')}
                                <a
                                    href={line.description_source.article_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    ><span lang={srcLang}
                                        >{quoted(line.description_source.title, lang)}</span
                                    >{@render ext()}</a
                                >
                                (<a
                                    href={line.description_source.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{t('lines.about.version', {
                                        date: dateOf(line.description_source.fetched_at),
                                    })}{@render ext()}</a
                                >), {t('lines.about.license')}
                                <a
                                    href={line.description_source.license_url}
                                    target="_blank"
                                    rel="license noopener noreferrer"
                                    >{line.description_source.license}{@render ext()}</a
                                >. {t('lines.about.adapted')}
                            </p>
                        {:else if line.description_source?.urls?.length}
                            <p class="attribution">
                                {t('lines.about.curated')}
                                {#each line.description_source.urls as u, i (u)}<a
                                        class="ref"
                                        href={u}
                                        target="_blank"
                                        rel="noopener noreferrer">[{i + 1}]{@render ext()}</a
                                    >{/each}
                            </p>
                        {/if}
                    {:else}
                        <p class="empty">{t('lines.no_description')}</p>
                    {/if}
                    {#if journal.length}
                        <p class="journal-link">
                            {t('journal.line_link', { count: journal.length })}
                            {#each journal as j, i (j.slug)}<Link
                                    href={`/journal/${j.slug}`}
                                    lang={j.lang === lang ? undefined : j.lang}>{j.title}</Link
                                >{i < journal.length - 1 ? ', ' : ''}{/each}
                        </p>
                    {/if}
                </div>

                <section class="infobox" id="fiche" aria-labelledby="fiche-title">
                    <h2 id="fiche-title">{t('lines.facts.title')}</h2>
                    <dl>
                        {#if names.length}
                            {@const [namesShown, namesMore] = foldList(names)}
                            <div class="row names">
                                <dt>{t('lines.facts.names')}</dt>
                                <dd>
                                    {#snippet nameItem(n)}
                                        <li>
                                            <span class="nm" lang={srcLang}>{n.name}</span>
                                            {#if n.from || n.to}<span class="meta"
                                                    >{n.from ?? '?'}{n.to && n.to !== n.from
                                                        ? `-${n.to}`
                                                        : ''}</span
                                                >{/if}
                                            {#if n.kind === 'section'}<span class="meta"
                                                    >{t('lines.facts.name_section')}</span
                                                >{/if}
                                            {#if n.source && !namesSource}
                                                <span class="meta">
                                                    {#if n.url}<a
                                                            href={n.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            >{sourceLabel(
                                                                n.source,
                                                            )}{@render ext()}</a
                                                        >{:else}{sourceLabel(n.source)}{/if}
                                                </span>
                                            {/if}
                                        </li>
                                    {/snippet}
                                    <ul>
                                        {#each namesShown as n (n.name)}{@render nameItem(n)}{/each}
                                    </ul>
                                    {#if namesMore.length}
                                        <details class="fold">
                                            <summary
                                                >{t('lines.facts.show_more', {
                                                    count: namesMore.length,
                                                })}</summary
                                            >
                                            <ul>
                                                {#each namesMore as n (n.name)}{@render nameItem(
                                                        n,
                                                    )}{/each}
                                            </ul>
                                        </details>
                                    {/if}
                                    {#if namesSource}
                                        <p class="meta names-src">
                                            {t('lines.sheet.names_source')}{colon(
                                                lang,
                                            )}{#if namesSource.url}<a
                                                    href={namesSource.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    >{sourceLabel(
                                                        namesSource.source,
                                                    )}{@render ext()}</a
                                                >{:else}{sourceLabel(namesSource.source)}{/if}
                                        </p>
                                    {/if}
                                </dd>
                            </div>
                        {/if}
                        {#if line.code}<div class="row">
                                <dt>{t('lines.facts.code')}</dt>
                                <dd class="tnum">{line.code}</dd>
                            </div>{/if}
                        {#if line.network}<div class="row">
                                <dt>{t('lines.facts.network')}</dt>
                                <dd>{t(`lines.networks.${line.network}`)}</dd>
                            </div>{/if}
                        {#each facts as f, i (i)}
                            <div class="row">
                                <dt>{f.label}</dt>
                                <dd>
                                    <span lang={f.lang}>{f.value}</span>{#if f.note}<span
                                            class="meta fact-note">{f.note}</span
                                        >{/if}
                                </dd>
                            </div>
                        {/each}
                        {#if line.historical_operators?.length}
                            {@const [opsShown, opsMore] = foldList(line.historical_operators)}
                            <div class="row">
                                <dt>{t('lines.facts.operators')}</dt>
                                <dd>
                                    {#snippet operatorItem(o)}
                                        <li>
                                            <span lang={srcLang}>{o.operator ?? ''}</span
                                            >{#if o.role}<span class="meta"
                                                    >{roleLabel(o.role)}</span
                                                >{/if}{#if o.ongoing && o.from_year}<span
                                                    class="meta"
                                                    >{t('lines.sheet.stats.since_year', {
                                                        year: o.from_year,
                                                    })}</span
                                                >{:else if o.from_year || o.to_year}<span
                                                    class="meta"
                                                    >{o.from_year ?? '?'}{o.to_year &&
                                                    o.to_year !== o.from_year
                                                        ? `-${o.to_year}`
                                                        : ''}</span
                                                >{/if}
                                        </li>
                                    {/snippet}
                                    <ul>
                                        {#each opsShown as o, i (i)}{@render operatorItem(o)}{/each}
                                    </ul>
                                    {#if opsMore.length}
                                        <details class="fold">
                                            <summary
                                                >{t('lines.facts.show_more', {
                                                    count: opsMore.length,
                                                })}</summary
                                            >
                                            <ul>
                                                {#each opsMore as o, i (i)}{@render operatorItem(
                                                        o,
                                                    )}{/each}
                                            </ul>
                                        </details>
                                    {/if}
                                </dd>
                            </div>
                        {/if}
                        {#if mergedNames.length}
                            <div class="row">
                                <dt>{t('lines.facts.merged')}</dt>
                                <dd lang={srcLang}>{mergedNames.map((n) => n.name).join(', ')}</dd>
                            </div>
                        {/if}
                        <div class="row links">
                            <dt>{t('lines.facts.elsewhere')}</dt>
                            <dd>
                                {#if line.wikipedia_title}
                                    <a
                                        href={`https://fr.wikipedia.org/wiki/${encodeURIComponent(line.wikipedia_title.replaceAll(' ', '_'))}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        ><i class="bi bi-wikipedia" aria-hidden="true"></i>
                                        {sourceLabel('wikipedia')}{@render ext()}</a
                                    >
                                {/if}
                                {#if line.wikidata}
                                    <a
                                        href={`https://www.wikidata.org/wiki/${line.wikidata}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >Wikidata {line.wikidata}{@render ext()}</a
                                    >
                                {/if}
                                {#if line.archeo_url}
                                    <a
                                        href={line.archeo_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >archeoferroviaire{@render ext()}</a
                                    >
                                {/if}
                            </dd>
                        </div>
                    </dl>
                </section>
                {#if line.advocacy}
                    <!-- Campagne : après le texte et la fiche (sous le texte sur grand écran, après la fiche sur téléphone), jamais avant les faits. -->
                    <section
                        class={[
                            'advocacy',
                            line.advocacy.kind === 'reopening' ? 'reopening' : 'alert',
                        ]}
                        aria-label={t('lines.sheet.advocacy_label')}
                    >
                        <i
                            class="bi {line.advocacy.kind === 'reopening'
                                ? 'bi-megaphone'
                                : 'bi-exclamation-triangle'} advocacy-icon"
                            aria-hidden="true"
                        ></i>
                        <div class="advocacy-text">
                            <p class="advocacy-title" lang={line.advocacy.lang?.title}>
                                {line.advocacy.title}
                            </p>
                            <p lang={line.advocacy.lang?.message}>{line.advocacy.message}</p>
                            {#if line.advocacy.sources?.length}
                                <p class="advocacy-sources-title">
                                    {t('lines.sheet.advocacy_sources')}
                                </p>
                                <ul class="advocacy-sources">
                                    {#each line.advocacy.sources as source (source.url)}
                                        <li>
                                            <a
                                                href={source.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                >{source.label}, {source.date}{@render ext()}</a
                                            >
                                        </li>
                                    {/each}
                                </ul>
                            {/if}
                        </div>
                        {#if line.advocacy.cta_url}
                            <a
                                class="vff-btn secondary small advocacy-cta"
                                href={line.advocacy.cta_url}
                                rel="noopener noreferrer"
                                lang={line.advocacy.lang?.cta_label}
                            >
                                {line.advocacy.cta_label || t('lines.act')}<i
                                    class="bi bi-box-arrow-up-right vff-ext"
                                    aria-hidden="true"
                                ></i><span class="vff-sr-only">
                                    ({t('common.external_same_tab')})</span
                                >
                            </a>
                        {/if}
                    </section>
                {/if}
            </div>
        </SheetSection>

        {#if phases.length}
            <SheetSection title={t('lines.section.history')} id="history">
                <ol class="history">
                    {#each phases as p, i (i)}
                        <li class={{ selected: i === selected }}>
                            <div class="h-date">
                                <span class="h-day">{phaseDate(p)}</span>
                                <button type="button" class="see-map" onclick={() => showPhase(i)}>
                                    <i class="bi bi-map" aria-hidden="true"></i>
                                    {t('lines.phases.see_on_map')}<span class="vff-sr-only">
                                        ({phaseDate(p)})</span
                                    >
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
                title={t('lines.section.evolution')}
                intro={t('lines.decades.intro')}
                id="decades"
            >
                <LineDecades {decades} {phases} onselect={showPhase} />
            </SheetSection>
        {/if}

        <SheetSection
            id="stations"
            title={t('lines.section.stations')}
            count={stations.length}
            intro={stations.length ? t('lines.stations.intro') : t('lines.stations.empty')}
        >
            {#if stations.length}
                <LineStations {stations} {eraYears} />
            {/if}
        </SheetSection>

        {#if sections.length}
            <SheetSection
                id="sections"
                title={t('lines.section.sections')}
                intro={sections.some((s) => s.stages?.length)
                    ? `${t('lines.sections.intro')} ${t('lines.sections.stages_intro')}`
                    : t('lines.sections.intro')}
            >
                <ol class="ls-list">
                    {#each sections as s, si (s.name)}
                        <li class={['ls-item', { 'with-stages': s.stages?.length }]}>
                            <div class="ls-main">
                                <p class="ls-name">
                                    <strong lang={srcLang}>{s.name}</strong>
                                    {#if s.alt_names?.length}<span class="meta" lang={srcLang}
                                            >({s.alt_names.join(', ')})</span
                                        >{/if}
                                </p>
                                <p class="ls-meta">
                                    <span title={t('lines.sheet.tech.pk_title')}
                                        >{pkRange(s.pk_start_label, s.pk_end_label)}</span
                                    >
                                    <span class="ls-len"
                                        >{withUnit(s.length_km, 'km', lang, {
                                            maximumFractionDigits: 1,
                                        })}</span
                                    >
                                    {#if s.opening_year_from}<span
                                            >{t('lines.sections.opening', {
                                                years: `${s.opening_year_from}${s.opening_year_to && s.opening_year_to !== s.opening_year_from ? `-${s.opening_year_to}` : ''}`,
                                            })}</span
                                        >{/if}
                                </p>
                                {#if s.description}<p
                                        class="ls-desc"
                                        lang={s.description_lang ?? srcLang}
                                    >
                                        {tieUnits(s.description)}
                                    </p>{/if}
                                {#if s.source_url}
                                    <a
                                        class="vff-link"
                                        href={s.source_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{t('lines.sections.source')}{@render ext()}</a
                                    >
                                {/if}
                            </div>
                            {#if s.stages?.length}
                                <!-- Étapes : phases datées des années d'ouverture de la section (LineSheet::sectionStages). -->
                                <div class="ls-stages">
                                    <p class="ls-stages-title" id="section-stages-{si}">
                                        {t('lines.sections.stages_title')}
                                    </p>
                                    <ol aria-labelledby="section-stages-{si}">
                                        {#each s.stages as st (st.phase)}
                                            {@const day = dateOf(st.date, st.precision)}
                                            <li>
                                                <span class="st-date">{day}</span>
                                                <span class="st-what"
                                                    >{tieUnits(
                                                        t('lines.changes.opening', {
                                                            km: km(st.length_m),
                                                        }),
                                                    )}{#each stageNotes(st, eventsById) as n (n)}<sup
                                                            ><a href="#note-{n}"
                                                                ><span class="vff-sr-only"
                                                                    >{`${t('common.note_ref')} `}</span
                                                                >[{n}]</a
                                                            ></sup
                                                        >{/each}</span
                                                >
                                                <button
                                                    type="button"
                                                    class="see-map"
                                                    onclick={() => showPhase(st.phase)}
                                                >
                                                    <i class="bi bi-map" aria-hidden="true"></i>
                                                    {t('lines.phases.see_on_map')}<span
                                                        class="vff-sr-only"
                                                    >
                                                        ({day})</span
                                                    >
                                                </button>
                                            </li>
                                        {/each}
                                    </ol>
                                </div>
                            {/if}
                        </li>
                    {/each}
                </ol>
            </SheetSection>
        {/if}

        {#if heritage.length}
            <SheetSection
                id="heritage"
                title={t('lines.section.heritage')}
                intro={t('lines.heritage.intro')}
            >
                <ol class="ls-list">
                    {#each heritage as h, i (i)}
                        <li class="ls-item">
                            <p class="ls-name">
                                <strong lang={h.name ? srcLang : undefined}
                                    >{h.name ?? t(`lines.heritage.kind.${h.kind}`)}</strong
                                >
                                {#if h.name}<span class="meta"
                                        >({t(`lines.heritage.kind.${h.kind}`)})</span
                                    >{/if}
                            </p>
                            <p class="ls-meta">
                                {#if h.from && h.to}<span lang={srcLang}
                                        >{t('lines.event_section.between', {
                                            from: h.from,
                                            to: h.to,
                                        })}</span
                                    >{/if}
                                {#if h.length_km}<span class="ls-len"
                                        >{withUnit(h.length_km, 'km', lang, {
                                            maximumFractionDigits: 1,
                                        })}</span
                                    >{/if}
                                <span>{h.periods.map(heritagePeriod).join(', ')}</span>
                            </p>
                            {#if h.operator}<p class="ls-desc">
                                    {t('lines.heritage.operator', { name: h.operator })}
                                </p>{/if}
                            {#if h.source_url}
                                <a
                                    class="vff-link"
                                    href={h.source_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{t('lines.heritage.source')}{@render ext()}</a
                                >
                            {/if}
                        </li>
                    {/each}
                </ol>
            </SheetSection>
        {/if}

        {#if stats.signalling || stats.train_protection || stats.gauge_mm || stats.dismantled_at_min || signalling.length || signalling_history.length}
            <SheetSection title={t('lines.tech.title')} id="tech">
                <dl class="tech-grid">
                    {#if stats.gauge_mm}
                        <div class="tech-item">
                            <dt>
                                <i class="bi bi-rulers" aria-hidden="true"></i>
                                {t('lines.tech.gauge')}
                            </dt>
                            <dd>{gaugeText}</dd>
                        </div>
                    {/if}
                    {#if stats.signalling}
                        <div class="tech-item">
                            <dt>
                                <i class="bi bi-stoplights" aria-hidden="true"></i>
                                {t('lines.tech.signalling')}
                            </dt>
                            <dd>
                                {coverageText(stats.signalling_coverage, stats.signalling)}
                            </dd>
                        </div>
                    {/if}
                    {#if stats.train_protection}
                        <div class="tech-item">
                            <dt>
                                <i class="bi bi-shield-check" aria-hidden="true"></i>
                                {t('lines.tech.train_protection')}
                            </dt>
                            <dd>
                                {coverageText(
                                    stats.train_protection_coverage,
                                    stats.train_protection,
                                )}
                            </dd>
                        </div>
                    {/if}
                    {#if stats.dismantled_at_min}
                        <div class="tech-item">
                            <dt>
                                <i class="bi bi-scissors" aria-hidden="true"></i>
                                {t('lines.tech.dismantled')}
                            </dt>
                            <dd>
                                {stats.dismantled_at_min === stats.dismantled_at_max
                                    ? new Date(stats.dismantled_at_min).getFullYear()
                                    : `${new Date(stats.dismantled_at_min).getFullYear()}-${new Date(stats.dismantled_at_max).getFullYear()}`}
                            </dd>
                        </div>
                    {/if}
                </dl>

                {#if signalling.length}
                    <h3 class="sub">{t('lines.section.signalling')}</h3>
                    <p class="section-intro">{signallingIntro}</p>
                    <p class="sig-more">
                        <a class="vff-link" href="/maps?layer=signalling"
                            >{t('lines.sheet.tech.signalling_map')}</a
                        >
                    </p>
                    <ol class="sig-ranges">
                        {#each signallingRows as r (r.key)}
                            {#if r.gap}
                                <li class="sig-range sig-gap">
                                    <p class="sig-pk">
                                        <span title={t('lines.sheet.tech.pk_title')}
                                            >{pkRange(r.from, r.to)}</span
                                        >
                                    </p>
                                    <p class="sig-body">{t('lines.sheet.tech.signalling_gap')}</p>
                                </li>
                            {:else}
                                <li class="sig-range">
                                    <p class="sig-pk">
                                        <span title={t('lines.sheet.tech.pk_title')}
                                            >{pkRange(r.pk_start_label, r.pk_end_label)}</span
                                        >
                                        <span class="sig-len"
                                            >{withUnit(r.length_m / 1000, 'km', lang, {
                                                maximumFractionDigits: 1,
                                            })}</span
                                        >
                                    </p>
                                    <div class="sig-body">
                                        <p class="sig-main">
                                            <i
                                                class="bi {isCabin(r.signalling)
                                                    ? 'bi-broadcast-pin'
                                                    : 'bi-stoplights'}"
                                                aria-hidden="true"
                                            ></i>
                                            <span
                                                ><span class="sig-kind"
                                                    >{#if r.signalling !== 'NONE'}{isCabin(r.signalling)
                                                            ? t('lines.signalling.cabin')
                                                            : t('lines.signalling.block')}{/if}</span
                                                >
                                                {systemsLabel(r, t)}</span
                                            >
                                        </p>
                                        {#if r.train_protection.length || r.kvb_equipped}
                                            <p class="sig-protection">
                                                <span class="sig-kind"
                                                    >{t('lines.signalling.protection')}</span
                                                >
                                                {#each r.train_protection as p (p)}<span
                                                        class="vff-badge">{sigLabel(p)}</span
                                                    >{/each}
                                                {#if r.kvb_equipped && !r.train_protection.includes('KVB')}<span
                                                        class="vff-badge">KVB</span
                                                    >{/if}
                                            </p>
                                        {/if}
                                    </div>
                                </li>
                            {/if}
                        {/each}
                    </ol>
                {/if}

                {#if signalling_history.length}
                    <LineSignallingHistory history={signalling_history} />
                {/if}
            </SheetSection>
        {/if}

        {#if profile}
            <LineProfile {profile} />
        {/if}

        {#if speeds && (speeds.ranges.length || speeds.history.length)}
            <LineSpeeds {speeds} />
        {/if}

        {#if level_crossings.total}
            <LineLevelCrossings crossings={level_crossings} />
        {/if}

        {#if greenways.items.length}
            <LineGreenways {greenways} />
        {/if}

        {#if chronicle?.events?.length}
            <SheetSection title={t('lines.section.chronicle')} id="chronicle">
                <LineChronicle {chronicle} stories={story_events} lineSlug={line.slug} />
            </SheetSection>
        {/if}

        {#if materials.length}
            <LineMaterials {materials} />
        {/if}

        {#if accidents.length}
            <LineAccidents {accidents} lineSlug={line.slug} />
        {/if}

        {#if archives.items.length || archive_records.items.length}
            <LineArchives {archives} records={archive_records} />
        {/if}

        <!-- Photos, cartes postales, plans : chargés à la demande (API paginée), crédits par image. -->
        <SheetGallery
            endpoint="/api/v1/lines/{encodeURIComponent(line.slug)}/media"
            intro={t('media.subtitle')}
            onitems={(list) => (seoImages = list.slice(0, 4))}
        />

        {#if chronicle?.notes?.length || sources.length}
            <SheetSection title={t('lines.section.notes')} id="notes">
                <LineSourceNotes notes={chronicle?.notes ?? []} />
                {#if sources.length}
                    <h3 class="sub">{t('lines.notes.data_sources')}</h3>
                    <ul class="data-sources">
                        {#each sources as s (s.type)}
                            <li>
                                <strong>{sourceLabel(s.type)}</strong>{#if s.url},
                                    <a
                                        href={cleanUrl(s.url)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{linkLabel(s.url, 48, lang)}{@render ext()}</a
                                    >{/if}{#if s.datasets?.length}
                                    <span class="meta">({s.datasets.join(', ')})</span
                                    >{/if}{#if s.fetched_at},
                                    <span class="meta"
                                        >{t('lines.notes.fetched', {
                                            date: dateOf(s.fetched_at),
                                        })}</span
                                    >{/if}
                            </li>
                        {/each}
                    </ul>
                {/if}
            </SheetSection>
        {/if}

        <ReportError
            type="line"
            slug={line.slug}
            name={line.name}
            proposals={[
                { href: `/contribute/event?line=${line.slug}`, label: t('proposals.sheet.event') },
                { href: `/contribute/media?line=${line.slug}`, label: t('proposals.sheet.media') },
                {
                    href: `/contribute/material?line=${line.slug}`,
                    label: t('proposals.sheet.material'),
                },
            ]}
        />
    </main>
</Layout>

<style>
    main {
        padding-bottom: var(--vff-section-space);
        background-color: var(--vff-bg);
    }
    /* Une commande amenée dans la vue (clavier, lecteur d'écran, audit) ne finit pas sous la barre fixe
       de l'en-tête ni sous le sommaire collant de la fiche (WCAG 2.4.11, 2.5.8). Aucun effet visuel. */
    main :global(:is(a, button, summary, input, select, textarea)) {
        scroll-margin-block-start: calc(var(--vff-header-h) + 72px);
    }
    /* Sections sous la carte (gares, profil, chronique…) : mises en page seulement quand elles approchent
       de l'écran. Le rendu ne change pas ; la première mise en page de la fiche, qui couvrait ses 3 000
       éléments, ne porte plus que le haut de la page. La marge
       de découpe laisse passer les anneaux de focus au bord ; les notes, en bas, dépassent de 3 px. */
    main :global(.sheet-section:not(#trace, #about, #notes)) {
        content-visibility: auto;
        /* Hauteur estimée d'après le contenu (--vff-section-h, posée par SheetSection) moins le haut de
           marge intérieure, que `contain-intrinsic-block-size` ne compte pas ; `auto` retient la vraie. */
        contain-intrinsic-block-size: auto
            max(0px, calc(var(--vff-section-h, 900px) - var(--vff-section-space)));
        overflow-clip-margin: 16px;
    }
    .meta {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .tnum {
        font-variant-numeric: tabular-nums;
    }
    .empty {
        margin: 0;
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .sub {
        margin: var(--vff-block-space) 0 var(--vff-space-2);
        font-size: var(--vff-fs-h3);
    }
    .section-intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }

    /* En-tête (SheetHero) : noms, exploitant, rail de la ligne aujourd'hui */
    .alt-names {
        max-width: 46.4em;
        margin: 4px 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .alt-names .nm {
        color: var(--vff-text);
    }
    .operator {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 8px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .today-rail {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        gap: 8px 16px;
    }
    .rail-label {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
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
        min-width: 2px;
        background: var(--c);
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
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .rail-legend li {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
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
        white-space: nowrap;
    }

    /* Le tracé dans le temps : carte et détail à gauche, phases à droite. La carte est haute de
       presque tout l'écran (sous le sommaire collant), la liste des phases a la même hauteur au
       plus (moins sa barre de boutons) : toute la chronologie se voit d'un coup d'œil tant qu'elle
       tient ; au-delà, la liste défile et dit combien de dates restent plus bas. */
    .phases-grid {
        --phase-map-h: clamp(520px, calc(100dvh - 200px), 820px);
        --phases-max: calc(var(--phase-map-h) - 46px);
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr);
        grid-template-areas:
            'map list'
            'map detail';
        grid-template-rows: auto 1fr;
        gap: var(--vff-space-5) var(--vff-space-6);
        align-items: start;
    }
    /* Sur ordinateur, le détail de la phase choisie passe sous la liste, à côté de la carte (il
       restait sous la carte, hors de l'écran une fois la carte agrandie). */
    .map-col {
        display: contents;
    }
    .map-col > :global(.phase-map-wrap) {
        grid-area: map;
    }
    .selector-col {
        grid-area: list;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
    }
    .unexplained {
        margin: 0;
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    .unexplained strong {
        color: var(--vff-text);
    }
    /* Détail de la phase : encart à fond teinté (plus de barre épaisse sur le bord gauche). */
    .phase-detail {
        grid-area: detail;
        padding: var(--vff-pad-box);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
    }
    .phase-detail h3 {
        margin: 0 0 8px;
        font-family: var(--vff-font-display);
        font-size: 22px;
        font-weight: 700;
    }

    /* À propos + fiche */
    /* Texte à gauche, fiche à droite ; la campagne remplit le bas de la colonne de texte
       (sur téléphone : texte, fiche, puis campagne). */
    /* Colonne de texte à sa largeur de lecture (34,8em, comme .description) : tout le reste va à la
       fiche, plus large, donc moins haute (moins de vide sous le texte quand la fiche est longue). */
    .about-grid {
        display: grid;
        grid-template-columns: minmax(0, var(--vff-measure)) minmax(320px, 1fr);
        grid-template-areas:
            'about fiche'
            'adv fiche';
        grid-template-rows: auto 1fr;
        gap: var(--vff-space-6) 48px;
        align-items: start;
    }
    .about {
        grid-area: about;
    }
    .infobox {
        grid-area: fiche;
    }
    .description {
        max-width: var(--vff-measure);
    }
    .description p {
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text);
    }
    .journal-link {
        max-width: var(--vff-measure);
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    .attribution {
        max-width: var(--vff-measure);
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    .attribution .ref {
        margin-left: 0.4em;
    }
    /* Rembourrage du site ; en bas, la dernière rangée apporte déjà ses 10 px. */
    .infobox {
        padding: var(--vff-pad-box) var(--vff-pad-box) calc(var(--vff-pad-box) - 10px);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
    }
    .infobox h2 {
        margin: 0 0 10px;
        font-size: 20px;
    }
    .infobox dl {
        margin: 0;
    }
    /* Fiche longue (9 rangées ou plus) dans une colonne assez large : deux colonnes, libellé au-dessus
       de la valeur, dans l'ordre de lecture (colonne par colonne). La fiche n'est plus bien plus haute
       que le texte, donc plus de grand vide sous l'introduction (mise en page seulement : mêmes
       rangées, même ordre). */
    .infobox {
        container: fiche / inline-size;
    }
    @container fiche (min-width: 28rem) {
        .infobox dl:has(> .row:nth-child(9)) {
            column-count: 2;
            column-gap: 32px;
        }
        .infobox dl:has(> .row:nth-child(9)) > .row {
            grid-template-columns: minmax(0, 1fr);
            gap: 2px;
            break-inside: avoid;
        }
    }
    .infobox .row {
        display: grid;
        grid-template-columns: minmax(0, 9.5em) minmax(0, 1fr);
        gap: 4px 14px;
        padding: 10px 0;
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .infobox dt {
        font-weight: 600;
        color: var(--vff-text-muted);
        overflow-wrap: break-word;
    }
    .infobox dd {
        min-width: 0;
        margin: 0;
        color: var(--vff-text);
        overflow-wrap: break-word;
    }
    .infobox ul {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .infobox li {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        column-gap: 8px;
        font-size: var(--vff-fs-sm);
    }
    .infobox .names .nm {
        flex-basis: 100%;
        font-weight: 600;
    }
    /* Liste longue repliée : bouton texte, la suite s'ouvre dessous. */
    .fold summary {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        margin-top: 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-link);
        text-decoration: underline;
        text-underline-offset: 0.18em;
        cursor: pointer;
    }
    .fold[open] summary {
        margin-bottom: 6px;
    }
    .names-src {
        margin: 8px 0 0;
    }
    .fact-note {
        display: block;
    }
    .infobox .links dd {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 16px;
    }
    /* Cible de 24 px au moins, sans décaler les lignes de la fiche. */
    .infobox dd a,
    .data-sources a {
        display: inline-block;
        margin: -3px 0;
        padding: 3px 0;
    }
    /* Crédit de l'extrait : liens EN LIGNE (un bloc insécable laissait « ( » seul en fin de ligne et
       ne coupait pas un long titre) ; le rembourrage vertical donne la même cible de 24 px. */
    .attribution a {
        padding: 3px 0;
    }

    /* Campagne de plaidoyer (AngouLim…) : après l'essentiel, sobre, texte en encre. */
    .advocacy {
        grid-area: adv;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: start;
        gap: var(--vff-space-3) var(--vff-space-5);
        padding: var(--vff-pad-box);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
    }
    .advocacy-icon {
        align-self: start;
        font-size: 24px;
        color: var(--vff-success);
    }
    .advocacy.alert .advocacy-icon {
        color: var(--vff-danger);
    }
    .advocacy-text p {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.55;
        color: var(--vff-text);
    }
    .advocacy-cta {
        grid-column: 2;
        justify-self: start;
    }
    /* Sources du message de soutien : sous le texte, plus petites, une par ligne. */
    .advocacy-text .advocacy-sources-title {
        margin-top: var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
    }
    .advocacy-sources {
        margin: 4px 0 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .advocacy-sources a {
        display: inline-block;
        padding: 3px 0;
    }
    .infobox .links a {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .advocacy-text .advocacy-title {
        margin-bottom: 4px;
        font-family: var(--vff-font-display);
        font-size: 20px;
        font-weight: 700;
        line-height: var(--vff-lh-heading);
    }

    /* Histoire */
    .history {
        margin: 0;
        padding: 0;
        list-style: none;
        border-left: 2px solid var(--vff-border-strong);
    }
    .history li {
        display: grid;
        grid-template-columns: 13em minmax(0, 1fr);
        gap: var(--vff-space-5);
        position: relative;
        padding: 14px 0 16px 22px;
    }
    .history li + li {
        border-top: 1px solid var(--vff-border);
    }
    .history li::before {
        content: '';
        position: absolute;
        top: 20px;
        left: -7px;
        width: 12px;
        height: 12px;
        border: 2px solid var(--vff-accent);
        border-radius: 50%;
        background: var(--vff-bg);
    }
    .history li.selected::before {
        background: var(--vff-accent);
    }
    .h-date {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .h-day {
        font-family: var(--vff-font-display);
        font-size: 18px;
        font-weight: 700;
        line-height: 1.25;
    }
    .see-map {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 24px;
        padding: 0;
        border: 0;
        background: none;
        color: var(--vff-link);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        text-decoration: underline;
        text-underline-offset: 0.18em;
        cursor: pointer;
    }
    .see-map:hover {
        text-decoration-thickness: 2px;
    }

    /* Sections nommées et exploitation associative : filets, pas de cartes. */
    .ls-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .ls-item {
        padding: var(--vff-space-4) 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .ls-name {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 10px;
        margin: 0;
    }
    .ls-name strong {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
    }
    .ls-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 2px 16px;
        margin: 4px 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .ls-len {
        color: var(--vff-text);
        white-space: nowrap;
    }
    .ls-desc {
        max-width: var(--vff-measure);
        margin: 8px 0 4px;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.55;
    }
    /* Section avec ses mises en service datées : le texte à sa largeur de lecture, les étapes à
       droite (plus de moitié droite vide) ; dessous sur tablette et téléphone. */
    .ls-item.with-stages {
        display: grid;
        grid-template-columns: minmax(0, var(--vff-measure)) minmax(0, 1fr);
        gap: var(--vff-space-4) 48px;
        align-items: start;
    }
    .ls-stages-title {
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .ls-stages ol {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .ls-stages li {
        display: grid;
        grid-template-columns: 10.5em minmax(0, 1fr);
        gap: 2px 16px;
        padding: 10px 0;
        border-top: 1px solid var(--vff-border);
    }
    .st-date {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        line-height: 1.4;
        font-variant-numeric: tabular-nums;
    }
    .st-what {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .ls-stages .see-map {
        grid-column: 2;
        justify-self: start;
    }
    /* Appel de note : exposant sans écarter les lignes, cible de 24 px. */
    .st-what sup {
        line-height: 0;
    }
    .st-what sup a {
        padding: 5px 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        text-decoration: underline;
    }

    /* Technique et signalisation */
    .tech-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: var(--vff-space-4) var(--vff-space-6);
        margin: 0;
    }
    .tech-item {
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border-strong);
    }
    .tech-item dt {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .tech-item dt i {
        color: var(--vff-accent-ink);
    }
    .tech-item dd {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        line-height: 1.45;
        color: var(--vff-text);
    }
    .sig-ranges {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .sig-range {
        display: grid;
        grid-template-columns: minmax(0, 15em) minmax(0, 1fr);
        gap: 4px var(--vff-space-5);
        padding: 12px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .sig-range p {
        margin: 0;
    }
    .sig-pk {
        display: flex;
        flex-direction: column;
        font-variant-numeric: tabular-nums;
    }
    .sig-len {
        color: var(--vff-text-muted);
    }
    /* Trou des données : même grille, texte secondaire, sans icône (rien n'est affirmé). */
    .sig-gap .sig-body {
        color: var(--vff-text-muted);
    }
    .sig-more {
        margin: 0 0 var(--vff-space-4);
    }
    .sig-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .sig-main {
        display: flex;
        align-items: baseline;
        gap: 8px;
        font-size: var(--vff-fs-md);
    }
    .sig-main i {
        color: var(--vff-accent-ink);
    }
    .sig-kind {
        margin-right: 4px;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .sig-protection {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    /* Libellé long (allemand à 320 px) : la pastille passe à la ligne au lieu de déborder. */
    .sig-protection :global(.vff-badge) {
        max-width: 100%;
        white-space: normal;
    }

    /* Notes */
    .data-sources {
        margin: 0;
        padding-left: 1.2em;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.6;
    }
    .data-sources a {
        overflow-wrap: anywhere;
    }

    /* Tablette et petit ordinateur (jusqu'à 1 180 px) : le texte, puis la fiche sur toute la
       largeur (en deux colonnes, cf. @container fiche), puis la campagne. À côté du texte, la fiche
       n'avait que 370 px : une seule colonne, deux fois plus haute que l'introduction, et 900 px
       de vide sous le texte de la LGV Sud-Est à 1 024 px (question Q4). Même ordre de lecture. */
    @media (max-width: 1180px) {
        .about-grid {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas: 'about' 'fiche' 'adv';
            grid-template-rows: auto;
        }
        .ls-item.with-stages {
            grid-template-columns: minmax(0, 1fr);
        }
        .ls-stages {
            max-width: var(--vff-measure);
        }
    }
    @media (max-width: 900px) {
        /* Téléphone et tablette : la carte et le détail de la phase, puis la liste complète des
           phases dessous. */
        .phases-grid {
            --phase-map-h: clamp(340px, 62dvh, 540px);
            --phases-max: none;
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas: 'map' 'detail' 'list';
            grid-template-rows: auto;
        }
        .history li {
            grid-template-columns: minmax(0, 1fr);
            gap: 6px;
        }
    }
    @media (max-width: 600px) {
        .today-rail {
            grid-template-columns: minmax(0, 1fr);
        }
        .rail-legend {
            grid-column: 1;
        }
        .advocacy {
            grid-template-columns: minmax(0, 1fr);
            padding: 16px;
        }
        .advocacy-icon {
            display: none;
        }
        .advocacy-cta {
            grid-column: 1;
        }
        .advocacy-text .advocacy-title {
            font-size: 18px;
        }
        .sig-range,
        .infobox .row {
            grid-template-columns: minmax(0, 1fr);
        }
        .history li {
            padding-left: 18px;
        }
        .ls-stages li {
            grid-template-columns: minmax(0, 1fr);
        }
        .ls-stages .see-map {
            grid-column: 1;
        }
    }
</style>
