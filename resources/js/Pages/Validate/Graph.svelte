<script>
    import { router } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import ReviewNav from '$components/Contribute/ReviewNav.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import Field from '$components/ui/Field.svelte';
    import GraphMap from '$components/Validate/GraphMap.svelte';
    import { reportDate } from '$lib/errorReport.js';
    import {
        CHECKS_BY_LAYER,
        EDGE_STYLES,
        JUNCTION_TYPES,
        NODE_STYLES,
        checkUrl,
        exportUrl,
        detailRows,
        detailTitle,
        featureUrl,
        formatDistance,
        VISIT_OPTIONS,
        itemSummary,
        itemTarget,
        latestRequest,
        layerFigures,
        listView,
        lineColor,
        pageQuery,
    } from '$lib/graphAdmin.js';
    import { i18n } from '$lib/i18n.js';

    /**
     * Graphe ferroviaire d'une ère (NetworkGraphAdminController), pour les administrateurs : choix
     * de l'ère et de la couche, chiffres clés, carte (tuiles MVT) avec les attributs de l'élément
     * cliqué, et diagnostics en tableau paginé (JSON), du plus grave au moins grave. Le tableau
     * est l'équivalent accessible de la carte : « Voir sur la carte » recadre la carte et affiche
     * les attributs dans le panneau (ou le résumé d'une île, d'une ligne en morceaux).
     *
     * Changer d'ère ou de couche se fait par le bouton « Afficher » (une flèche sur les boutons
     * radio ne recharge rien, WCAG 3.2.2) et garde l'état de la page : la carte, son cadrage et la
     * fiche affichée restent en place.
     */
    let {
        eras = [],
        year = null,
        missing_era: missingEra = null,
        layer = 'topology',
        layers = [],
        summary = null,
        thresholds = {},
    } = $props();

    const lang = $derived($i18n.language);
    /**
     * @param {string} key
     * @param {Record<string, unknown>} [opts]
     */
    const t = (key, opts) => String($i18n.t(key, opts));
    const num = (n) => (n == null ? '' : Number(n).toLocaleString(lang));
    const dist = (m) => formatDistance(m, lang);

    const era = $derived(eras.find((e) => e.year === year) ?? null);
    const eraName = (e) =>
        e.current ? t('graph_admin.era_today') : t('graph_admin.era_option', { year: e.year });
    const layerName = $derived(t(`graph_admin.layers.${layer}`));
    const checks = $derived(CHECKS_BY_LAYER[layer] ?? []);

    /** Liste choisie ; une liste d'une autre couche cède la place à la première de la couche. */
    let chosen = $state(null);
    const check = $derived(chosen && checks.includes(chosen) ? chosen : checks[0]);
    let listPage = $state(1);
    // Filtre des jonctions : `filters` est celui de la liste affichée, les deux autres sont les
    // champs du formulaire, appliqués par « Filtrer ».
    let filters = $state({ type: '', line: '' });
    let typeChoice = $state('');
    let lineChoice = $state('');
    let list = $state(null);
    let listState = $state('idle');
    // Choix du formulaire, appliqués par « Afficher » ; ils suivent l'ère et la couche montrées.
    let eraChoice = $derived(year);
    let layerChoice = $derived(layer);

    let selected = $state(null);
    /** Élément de liste sans fiche (île, ligne en morceaux) montré dans le panneau. */
    let picked = $state(null);
    let detailState = $state('idle');
    let focus = $state(null);
    let announce = $state('');
    let detailHeading = $state();

    // Liste de diagnostics : une page à la fois, la requête précédente est abandonnée.
    $effect(() => {
        if (!year || !check) return;
        const controller = new AbortController();
        listState = 'loading';
        fetch(checkUrl(year, check, listPage, filters), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
            .then((data) => {
                list = data;
                listState = 'ready';
            })
            .catch((error) => {
                if (error?.name !== 'AbortError') listState = 'error';
            });
        return () => controller.abort();
    });

    // La page précédente reste affichée pendant le chargement de la suivante (le bouton de
    // pagination garde le focus) ; une liste d'une autre sorte attend la sienne.
    const listShown = $derived(listView(list, check, listState));
    const shownList = $derived(listShown.view === 'table' ? list : null);
    const items = $derived(shownList?.items ?? []);
    const listBusy = $derived(listShown.busy);
    const markers = $derived(
        items
            .map((i) => itemTarget(check, i))
            .map(
                (target) =>
                    target.point ??
                    (target.bbox
                        ? [
                              (target.bbox[0] + target.bbox[2]) / 2,
                              (target.bbox[1] + target.bbox[3]) / 2,
                          ]
                        : null),
            )
            .filter(Boolean),
    );

    /** Montre l'ère et la couche choisies sans recréer la page (carte, cadrage et fiche gardés). */
    function apply() {
        const eraChanged = eraChoice !== year;
        router.get(
            pageQuery(eraChoice, layerChoice),
            {},
            {
                ...VISIT_OPTIONS,
                onSuccess: () => {
                    if (!eraChanged) return;
                    listPage = 1;
                    // Une île ou une ligne d'une autre ère n'a plus de sens ; un élément est recherché
                    // dans la nouvelle ère (une gare garde son identifiant, une arête non).
                    picked = null;
                    if (selected) select({ kind: selected.kind, id: selected.id });
                },
            },
        );
    }

    function chooseCheck(c) {
        chosen = c;
        listPage = 1;
    }

    function applyFilters() {
        filters = { type: typeChoice, line: lineChoice.trim() };
        listPage = 1;
    }

    function clearFilters() {
        typeChoice = '';
        lineChoice = '';
        applyFilters();
    }

    const filtering = $derived(check === 'junctions' && Boolean(filters.type || filters.line));
    const unknownLine = $derived(
        check === 'junctions' ? (shownList?.filters?.unknown_line ?? null) : null,
    );

    // Fiche en cours : un nouveau choix l'abandonne, sa réponse ne s'affiche jamais.
    const detailRequest = latestRequest();

    async function select(request) {
        const signal = detailRequest.next();
        picked = null;
        detailState = 'loading';
        try {
            const r = await fetch(featureUrl(year, request.kind, request.id), {
                headers: { Accept: 'application/json' },
                signal,
            });
            if (!detailRequest.isCurrent(signal)) return;
            if (r.status === 404) {
                selected = null;
                detailState = 'missing';
                announce = t('graph_admin.details.missing', { year });
                return;
            }
            if (!r.ok) throw new Error(String(r.status));
            const data = await r.json();
            if (!detailRequest.isCurrent(signal)) return;
            selected = data;
            detailState = 'ready';
            announce = t('graph_admin.details.shown', { name: detailTitle(selected, t) });
        } catch {
            if (!detailRequest.isCurrent(signal)) return;
            selected = null;
            detailState = 'error';
        }
    }

    async function show(item) {
        const target = itemTarget(check, item);
        focus = { ...target };
        if (target.feature) {
            await select(target.feature);
        } else {
            // Île ou ligne en morceaux : pas de fiche, un résumé de l'élément dans le panneau.
            detailRequest.cancel();
            selected = null;
            picked = itemSummary(check, item, t, lang);
            detailState = picked ? 'summary' : 'idle';
            if (picked) announce = t('graph_admin.map.framed', { name: picked.title });
        }
        await tick();
        detailHeading?.focus();
    }

    const rows = $derived(
        detailState === 'summary' && picked
            ? picked.rows
            : selected
              ? detailRows(selected, t, lang)
              : [],
    );
    const detailHeadingText = $derived(
        detailState === 'summary' && picked
            ? picked.title
            : selected && detailState === 'ready'
              ? detailTitle(selected, t)
              : t('graph_admin.map.selection'),
    );

    /** Texte explicatif d'une liste, avec ses seuils. */
    function checkText(c) {
        return t(`graph_admin.checks.${c}.text`, {
            tol:
                c === 'junctions'
                    ? Number(thresholds.junction_tolerance_m ?? 0.01).toLocaleString(lang)
                    : Number(thresholds.length_tolerance_m ?? 5).toLocaleString(lang),
            ratio: Number((thresholds.length_tolerance_ratio ?? 0.01) * 100).toLocaleString(lang),
            detour: Number(thresholds.detour_ratio ?? 4).toLocaleString(lang),
        });
    }

    /** Lignes du tableau récapitulatif : [couche, mesure, valeur]. */
    const summaryRows = $derived.by(() => {
        if (!summary) return [];
        const s = summary;
        const share = (v) =>
            Number(v ?? 0).toLocaleString(lang, { style: 'percent', maximumFractionDigits: 1 });
        const L = (k) => t(`graph_admin.summary.${k}`);
        return [
            ['topology', L('nodes'), num(s.topology.nodes)],
            ['topology', L('edges'), num(s.topology.edges)],
            ['topology', L('components'), num(s.topology.components)],
            [
                'topology',
                L('largest'),
                t('graph_admin.summary.largest_value', {
                    nodes: num(s.topology.largest?.nodes),
                    share: share(s.topology.largest?.length_share),
                }),
            ],
            ['topology', L('islands'), num(s.topology.islands)],
            ['topology', L('isolated_nodes'), num(s.topology.isolated_nodes)],
            ['topology', L('zero_length'), num(s.topology.zero_length_edges)],
            ['topology', L('no_geometry'), num(s.topology.no_geometry_edges)],
            ['topology', L('edge_anomalies'), num(s.topology.edge_anomalies)],
            ['topology', L('connectors'), num(s.topology.connectors)],
            ['topology', L('spread'), num(s.topology.spread_junctions)],
            ['topology', L('spread_1m'), num(s.topology.spread_over_1m)],
            ['topology', L('spread_5m'), num(s.topology.spread_over_5m)],
            ['topology', L('max_spread'), dist(s.topology.max_spread_m)],
            ['topology', L('jumps'), num(s.topology.jumps)],
            ['topology', L('max_jump'), dist(s.topology.max_jump_m)],
            ['stations', L('stations'), num(s.stations.stations)],
            ['stations', L('station_edges'), num(s.stations.edges)],
            ['stations', L('components'), num(s.stations.components)],
            [
                'stations',
                L('largest'),
                t('graph_admin.summary.largest_stations', {
                    count: num(s.stations.largest?.stations),
                }),
            ],
            ['stations', L('islands'), num(s.stations.islands)],
            ['stations', L('without_edge'), num(s.stations.without_edge)],
            ['stations', L('unattached'), num(s.stations.unattached)],
            ['stations', L('edge_anomalies'), num(s.stations.edge_anomalies)],
            ['lines', L('lines'), num(s.lines.lines)],
            ['lines', L('stops'), num(s.lines.stops)],
            ['lines', L('stops_off_graph'), num(s.lines.stops_off_graph)],
            ['lines', L('fragmented'), num(s.lines.fragmented)],
        ];
    });

    const figures = $derived(
        layerFigures(summary, layer).map((f) => ({
            value: num(f.value),
            label: t(`graph_admin.summary.${f.key}`),
        })),
    );

    const lineLabel = (l) => (l.code ? `${l.name} (${l.code})` : l.name);
</script>

<svelte:head><title>{t('graph_admin.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="graph-page">
        <PageHero title={t('graph_admin.title')} subtitle={t('graph_admin.subtitle')}>
            <ReviewNav current="graph" />
        </PageHero>

        <div class="vff-container body">
            {#if !era}
                <EmptyState
                    icon="bi-diagram-3"
                    title={t('graph_admin.not_built')}
                    text={t('graph_admin.not_built_text')}
                    headingLevel={2}
                />
            {:else}
                {#if missingEra}
                    <p class="notice" role="status">
                        {t('graph_admin.missing_era', { requested: missingEra, year })}
                    </p>
                {/if}
                <form
                    class="controls"
                    aria-label={t('graph_admin.controls_label')}
                    onsubmit={(e) => {
                        e.preventDefault();
                        apply();
                    }}
                >
                    <Field id="graph-era" label={t('graph_admin.era_label')}>
                        {#snippet children(attrs)}
                            <select
                                {...attrs}
                                class="vff-select"
                                value={eraChoice}
                                onchange={(e) => (eraChoice = Number(e.currentTarget.value))}
                            >
                                {#each eras as e (e.year)}
                                    <option value={e.year}>{eraName(e)}</option>
                                {/each}
                            </select>
                        {/snippet}
                    </Field>
                    <fieldset class="layers">
                        <legend>{t('graph_admin.layer_legend')}</legend>
                        <div class="pills">
                            {#each layers as l (l)}
                                <label class={['pill', { active: l === layerChoice }]}>
                                    <input
                                        type="radio"
                                        name="layer"
                                        value={l}
                                        checked={l === layerChoice}
                                        onchange={() => (layerChoice = l)}
                                    />
                                    {t(`graph_admin.layers.${l}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                    <button type="submit" class="vff-btn primary">
                        {t('graph_admin.apply')}
                    </button>
                </form>
                <p class="note">
                    {t(`graph_admin.layer_hints.${layer}`)}
                    {#if era.built_at}
                        {t('graph_admin.built_at', { date: reportDate(era.built_at, lang) })}
                    {/if}
                </p>

                <StatStrip items={figures} label={t('graph_admin.summary.title')} size="sm" />

                <div class="workspace">
                    <GraphMap
                        {year}
                        {layer}
                        {markers}
                        {focus}
                        onselect={select}
                        label={t('graph_admin.map.title', { year, layer: layerName })}
                    />

                    <aside class="panel" aria-labelledby="graph-detail-title">
                        <h2 id="graph-detail-title" tabindex="-1" bind:this={detailHeading}>
                            {detailHeadingText}
                        </h2>
                        {#if detailState === 'loading'}
                            <p class="muted">{t('graph_admin.details.loading')}</p>
                        {:else if detailState === 'error'}
                            <p class="error">{t('graph_admin.details.error')}</p>
                        {:else if detailState === 'missing'}
                            <p class="muted">{t('graph_admin.details.missing', { year })}</p>
                        {:else if rows.length}
                            <dl class="attrs">
                                {#each rows as row, i (i)}
                                    <div>
                                        <dt>{row.label}</dt>
                                        <dd>
                                            {#each row.parts as part, j (j)}
                                                <span class="part">
                                                    {#if part.href}
                                                        <a href={part.href}>{part.text}</a>
                                                    {:else}
                                                        {part.text}
                                                    {/if}
                                                </span>
                                            {/each}
                                        </dd>
                                    </div>
                                {/each}
                            </dl>
                        {:else}
                            <p class="muted">{t('graph_admin.details.empty')}</p>
                        {/if}

                        <h3>{t('graph_admin.map.legend')}</h3>
                        <ul class="legend" aria-label={t('graph_admin.map.legend_edges')}>
                            {#if layer === 'lines'}
                                <li>
                                    <span class="lines-swatch" aria-hidden="true">
                                        {#each [1, 2, 3, 4] as n (n)}
                                            <span class="vff-swatch" style:--swatch={lineColor(n)}
                                            ></span>
                                        {/each}
                                    </span>
                                    {t('graph_admin.edge.line')}
                                </li>
                            {/if}
                            {#each EDGE_STYLES[layer] ?? [] as s (s.key)}
                                <li>
                                    <span class="vff-swatch line" style:--swatch={s.color}></span>
                                    {t(`graph_admin.edge.${s.key}`)}
                                </li>
                            {/each}
                        </ul>
                        <ul class="legend" aria-label={t('graph_admin.map.legend_nodes')}>
                            {#each NODE_STYLES[layer] ?? [] as s (s.key)}
                                <li>
                                    <span class="dot" style:--swatch={s.color}></span>
                                    {t(`graph_admin.node.${s.key}`)}
                                </li>
                            {/each}
                        </ul>
                    </aside>
                </div>
                <p class="vff-sr-only" aria-live="polite">{announce}</p>

                <section class="diagnostics" aria-labelledby="graph-checks-title">
                    <h2 id="graph-checks-title">{t('graph_admin.checks.title')}</h2>
                    <p class="note">{t('graph_admin.checks.intro')}</p>

                    <div
                        class="check-list"
                        role="group"
                        aria-label={t('graph_admin.checks.choose')}
                    >
                        {#each checks as c (c)}
                            <button
                                type="button"
                                class="check"
                                aria-pressed={c === check}
                                onclick={() => chooseCheck(c)}
                            >
                                {t(`graph_admin.checks.${c}.title`)}
                            </button>
                        {/each}
                    </div>

                    <h3>{t(`graph_admin.checks.${check}.title`)}</h3>
                    <p class="note">{checkText(check)}</p>

                    {#if check === 'junctions'}
                        <form
                            class="filters"
                            aria-label={t('graph_admin.filters.label')}
                            onsubmit={(e) => {
                                e.preventDefault();
                                applyFilters();
                            }}
                        >
                            <Field id="graph-junction-type" label={t('graph_admin.filters.type')}>
                                {#snippet children(attrs)}
                                    <select {...attrs} class="vff-select" bind:value={typeChoice}>
                                        <option value=""
                                            >{t('graph_admin.filters.all_types')}</option
                                        >
                                        {#each JUNCTION_TYPES as type (type)}
                                            <option value={type}
                                                >{t(`graph_admin.checks.types.${type}`)}</option
                                            >
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                            <Field
                                id="graph-junction-line"
                                label={t('graph_admin.filters.line')}
                                hint={t('graph_admin.filters.line_hint')}
                                error={unknownLine
                                    ? t('graph_admin.filters.unknown_line', { line: unknownLine })
                                    : null}
                            >
                                {#snippet children(attrs)}
                                    <input
                                        {...attrs}
                                        class="vff-input"
                                        type="search"
                                        autocomplete="off"
                                        spellcheck="false"
                                        maxlength="100"
                                        bind:value={lineChoice}
                                    />
                                {/snippet}
                            </Field>
                            <div class="filter-actions">
                                <button type="submit" class="vff-btn primary">
                                    {t('graph_admin.filters.apply')}
                                </button>
                                {#if filtering}
                                    <button
                                        type="button"
                                        class="vff-btn secondary"
                                        onclick={clearFilters}
                                    >
                                        {t('graph_admin.filters.clear')}
                                    </button>
                                {/if}
                            </div>
                        </form>
                    {/if}

                    <!-- État de la liste annoncé aux lecteurs d'écran (chargement, puis total). -->
                    <p class="vff-sr-only" role="status">
                        {#if listBusy}
                            {t('graph_admin.checks.loading_page', { page: listPage })}
                        {:else if listState === 'error'}
                            {t('graph_admin.checks.error')}
                        {/if}
                    </p>
                    {#if listShown.view === 'error'}
                        <p class="error">{t('graph_admin.checks.error')}</p>
                    {:else if !shownList}
                        <p class="muted">{t('graph_admin.checks.loading')}</p>
                    {:else}
                        <div class="list-head">
                            <p class="count">
                                {#if filtering}
                                    {t('graph_admin.checks.filtered', {
                                        count: num(shownList.filtered),
                                        total: num(shownList.total),
                                    })}
                                {:else}
                                    {t('graph_admin.checks.total', { total: num(shownList.total) })}
                                {/if}
                            </p>
                            {#if shownList.filtered > 0}
                                <a
                                    class="vff-btn secondary small"
                                    href={exportUrl(year, check, filters)}
                                    download
                                >
                                    <i class="bi bi-download" aria-hidden="true"></i>
                                    {t('graph_admin.checks.export')}<span class="vff-sr-only"
                                        >&nbsp;: {t(`graph_admin.checks.${check}.title`)}</span
                                    >
                                </a>
                            {/if}
                        </div>
                        {#if items.length === 0}
                            <EmptyState
                                icon="bi-check2-circle"
                                title={filtering
                                    ? t('graph_admin.checks.empty_filtered')
                                    : t('graph_admin.checks.empty')}
                            />
                        {:else}
                            <!-- Cadre défilant focalisable : tableau lisible au clavier sur téléphone (WCAG 2.1.1). -->
                            <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                            <div
                                class="vff-table-wrap list-wrap"
                                role="region"
                                aria-labelledby="graph-table-caption"
                                aria-busy={listBusy}
                                tabindex="0"
                            >
                                <table class="vff-table">
                                    <caption id="graph-table-caption" class="vff-sr-only">
                                        {t('graph_admin.checks.caption', {
                                            title: t(`graph_admin.checks.${check}.title`),
                                            page: shownList.page,
                                            last: shownList.last_page,
                                        })}
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" class="num"
                                                >{t('graph_admin.checks.columns.id')}</th
                                            >
                                            {#if check === 'islands'}
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.nodes')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.length')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.stations')}</th
                                                >
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.lines')}</th
                                                >
                                            {:else if check === 'station_islands'}
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.stations')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.length')}</th
                                                >
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.station')}</th
                                                >
                                            {:else if check === 'isolated_nodes'}
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.kind')}</th
                                                >
                                            {:else if check === 'stations_without_edge'}
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.station')}</th
                                                >
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.reason')}</th
                                                >
                                            {:else if check === 'edges'}
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.layer')}</th
                                                >
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.reason')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.length')}</th
                                                >
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.line')}</th
                                                >
                                            {:else if check === 'junctions'}
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.kind')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.distance')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.ends')}</th
                                                >
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.lines')}</th
                                                >
                                            {:else if check === 'fragmented_lines'}
                                                <th scope="col"
                                                    >{t('graph_admin.checks.columns.line')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.pieces')}</th
                                                >
                                                <th scope="col" class="num"
                                                    >{t('graph_admin.checks.columns.length')}</th
                                                >
                                            {/if}
                                            <th scope="col"
                                                >{t('graph_admin.checks.columns.map')}</th
                                            >
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each items as item, i (i)}
                                            {@const rowName = String(item.id ?? item.line_id)}
                                            <tr>
                                                <th scope="row" class="num"
                                                    >{item.id ?? item.line_id}</th
                                                >
                                                {#if check === 'islands'}
                                                    <td class="num">{num(item.nodes)}</td>
                                                    <td class="num">{dist(item.length_m)}</td>
                                                    <td class="num">{num(item.stations)}</td>
                                                    <td>
                                                        {#each item.lines ?? [] as l (l.id)}
                                                            <a
                                                                class="cell-link"
                                                                href="/lines/{l.slug}"
                                                                >{lineLabel(l)}</a
                                                            >
                                                        {/each}
                                                    </td>
                                                {:else if check === 'station_islands'}
                                                    <td class="num">{num(item.stations)}</td>
                                                    <td class="num">{dist(item.length_m)}</td>
                                                    <td>
                                                        {#each item.sample ?? [] as s (s.id)}
                                                            <a
                                                                class="cell-link"
                                                                href="/stations/{s.slug}"
                                                                >{s.name}</a
                                                            >
                                                        {/each}
                                                    </td>
                                                {:else if check === 'isolated_nodes'}
                                                    <td>{t(`graph_admin.node.${item.kind}`)}</td>
                                                {:else if check === 'stations_without_edge'}
                                                    <td
                                                        ><a href="/stations/{item.slug}"
                                                            >{item.name}</a
                                                        ></td
                                                    >
                                                    <td
                                                        >{t(
                                                            `graph_admin.checks.reasons.${item.reason}`,
                                                        )}</td
                                                    >
                                                {:else if check === 'edges'}
                                                    <td>{t(`graph_admin.layers.${item.layer}`)}</td>
                                                    <td>
                                                        {t(
                                                            `graph_admin.checks.reasons.${item.reason}`,
                                                        )}
                                                        {#if item.measured_m != null && !(item.layer === 'topology' && item.reason === 'no_geometry' && item.measured_m === 0)}
                                                            <span class="sub">
                                                                {t(
                                                                    item.layer === 'stations'
                                                                        ? 'graph_admin.checks.direct'
                                                                        : item.reason ===
                                                                            'no_geometry'
                                                                          ? 'graph_admin.checks.nodes_gap'
                                                                          : 'graph_admin.checks.measured',
                                                                    {
                                                                        distance: dist(
                                                                            item.measured_m,
                                                                        ),
                                                                    },
                                                                )}
                                                            </span>
                                                        {/if}
                                                    </td>
                                                    <td class="num">{dist(item.length_m)}</td>
                                                    <td>
                                                        {#if item.line}
                                                            <a href="/lines/{item.line.slug}"
                                                                >{lineLabel(item.line)}</a
                                                            >
                                                        {/if}
                                                    </td>
                                                {:else if check === 'junctions'}
                                                    <td>
                                                        {t(`graph_admin.checks.types.${item.type}`)}
                                                        {#if item.kind}
                                                            <span class="sub"
                                                                >{t(
                                                                    `graph_admin.node.${item.kind}`,
                                                                )}</span
                                                            >
                                                        {/if}
                                                    </td>
                                                    <td class="num">{dist(item.distance_m)}</td>
                                                    <td class="num">{num(item.ends)}</td>
                                                    <td>
                                                        {#each item.line_refs ?? [] as l (l.id)}
                                                            <a
                                                                class="cell-link"
                                                                href="/lines/{l.slug}"
                                                                >{lineLabel(l)}</a
                                                            >
                                                        {/each}
                                                    </td>
                                                {:else if check === 'fragmented_lines'}
                                                    <td>
                                                        {#if item.line}
                                                            <a href="/lines/{item.line.slug}"
                                                                >{lineLabel(item.line)}</a
                                                            >
                                                        {/if}
                                                    </td>
                                                    <td class="num">{num(item.pieces)}</td>
                                                    <td class="num">{dist(item.length_m)}</td>
                                                {/if}
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="vff-btn secondary small"
                                                        onclick={() => show(item)}
                                                    >
                                                        {t('graph_admin.checks.show')}<span
                                                            class="vff-sr-only"
                                                            >&nbsp;: {rowName}</span
                                                        >
                                                    </button>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                            {#if shownList.last_page > 1}
                                <div class="pager">
                                    <Pagination
                                        current={shownList.page}
                                        last={shownList.last_page}
                                        onpage={(n) => (listPage = n)}
                                    />
                                </div>
                            {/if}
                        {/if}
                    {/if}
                </section>

                <details class="all-figures">
                    <summary>{t('graph_admin.summary.title')}</summary>
                    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                    <div
                        class="vff-table-wrap"
                        role="region"
                        aria-labelledby="graph-summary-caption"
                        tabindex="0"
                    >
                        <table class="vff-table">
                            <caption id="graph-summary-caption" class="vff-sr-only">
                                {t('graph_admin.summary.caption', { year })}
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">{t('graph_admin.summary.layer')}</th>
                                    <th scope="col">{t('graph_admin.summary.measure')}</th>
                                    <th scope="col" class="num">{t('graph_admin.summary.value')}</th
                                    >
                                </tr>
                            </thead>
                            <tbody>
                                {#each summaryRows as [l, label, value], i (i)}
                                    <tr>
                                        <td>{t(`graph_admin.layers.${l}`)}</td>
                                        <th scope="row">{label}</th>
                                        <td class="num">{value}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </details>
            {/if}
        </div>
    </main>
</Layout>

<style>
    .body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-6);
        padding-block: var(--vff-section-space);
    }
    .controls {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-4) var(--vff-space-6);
    }
    .controls :global(.vff-select) {
        min-width: 14rem;
    }
    .controls button {
        min-height: 44px;
    }
    .layers {
        margin: 0;
        padding: 0;
        border: 0;
        min-width: 0;
    }
    .layers legend {
        margin-bottom: var(--vff-space-2);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .pills {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
    }
    .pill {
        display: inline-flex;
        align-items: center;
        gap: var(--vff-space-2);
        min-height: 44px;
        padding: 6px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .pill:hover {
        background-color: var(--vff-bg-subtle);
    }
    .pill.active {
        border-color: var(--vff-text);
        background-color: var(--vff-bg-accent);
        font-weight: 600;
    }
    .pill input {
        accent-color: var(--vff-accent);
    }
    .pill:has(input:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .note {
        max-width: var(--vff-measure);
        margin: 0;
        color: var(--vff-text-muted);
    }
    .workspace {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-5);
    }
    @media (min-width: 1024px) {
        .workspace {
            grid-template-columns: minmax(0, 1fr) minmax(300px, 360px);
            align-items: start;
        }
    }
    .panel {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        padding: var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        min-width: 0;
    }
    .panel h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    .panel h3 {
        margin: var(--vff-space-3) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
    }
    .attrs {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        margin: 0;
    }
    .attrs dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .attrs dd {
        display: flex;
        flex-direction: column;
        margin: 0;
        overflow-wrap: anywhere;
        font-variant-numeric: tabular-nums;
    }
    .muted {
        margin: 0;
        color: var(--vff-text-muted);
    }
    .error {
        margin: 0;
        color: var(--vff-danger);
    }
    .legend {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-1);
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-sm);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
    }
    .legend .line {
        width: 22px;
        height: 4px;
        border-radius: 2px;
    }
    .lines-swatch {
        display: inline-flex;
        gap: 2px;
    }
    .dot {
        display: inline-block;
        flex: none;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--swatch);
        border: 1px solid var(--vff-border-strong);
    }
    .diagnostics {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
    }
    .diagnostics h2 {
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h2);
    }
    .diagnostics h3 {
        margin: var(--vff-space-2) 0 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
    }
    .check-list {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
    }
    .check {
        min-height: 44px;
        padding: 6px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background-color: transparent;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .check:hover {
        background-color: var(--vff-bg-subtle);
    }
    .check[aria-pressed='true'] {
        border-color: var(--vff-text);
        background-color: var(--vff-bg-accent);
        font-weight: 600;
    }
    .filters {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--vff-space-4) var(--vff-space-5);
    }
    .filters :global(.vff-field) {
        flex: 1 1 14rem;
        max-width: 22rem;
    }
    .filter-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
    }
    .filter-actions button {
        min-height: 44px;
    }
    .list-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .count {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-variant-numeric: tabular-nums;
    }
    .cell-link {
        display: block;
        padding-block: 2px;
    }
    .sub {
        display: block;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .pager {
        margin-top: var(--vff-space-2);
    }
    /* Page suivante en chargement : la page affichée pâlit sans disparaître. */
    .list-wrap[aria-busy='true'] {
        opacity: 0.6;
        transition: opacity var(--vff-transition);
    }
    .notice {
        max-width: var(--vff-measure);
        margin: 0;
        padding: var(--vff-space-3) var(--vff-space-4);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-subtle);
    }
    .all-figures summary {
        cursor: pointer;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        min-height: 44px;
        display: flex;
        align-items: center;
    }
    .all-figures .vff-table-wrap {
        margin-top: var(--vff-space-3);
    }
</style>
