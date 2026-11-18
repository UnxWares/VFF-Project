<script>
    import { onMount, tick } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import TraceEditorMap from '$components/Contribute/TraceEditorMap.svelte';
    import PortionEvents from '$components/Contribute/PortionEvents.svelte';
    import { i18n } from '$lib/i18n.js';
    import { findHistoricBasemap, historicTitle } from '$lib/historicBasemaps.js';
    import { formatKm, lengthM, portionColor } from '$lib/traceGeometry.js';
    import { colon } from '$lib/typo.js';

    /**
     * Éditeur de tracé : retracer une ligne disparue ou corriger une portion
     * existante sur fonds de carte historiques IGN, avec événements datés et
     * sourcés PAR PORTION. Soumis en contribution (modération à 2 validateurs).
     */
    let {
        line = null,
        currentEra = 2030,
        networks = ['principal', 'secondaire', 'militaire', 'particulier'],
        eventTypes = [],
        precisions = ['day', 'month', 'year'],
        limits = { portions: 60, points: 8000 },
    } = $props();

    const DRAFT_KEY = 'vff:trace-draft:v1';

    // Valeurs initiales tirées de la ligne passée par l'URL (lues une fois, voulu).
    // svelte-ignore state_referenced_locally
    let kind = $state(line ? 'correction' : 'new_line');
    // svelte-ignore state_referenced_locally
    let attached = $state(line);
    let newLineName = $state('');
    // svelte-ignore state_referenced_locally
    let network = $state(line?.network ?? 'secondaire');
    let networkTouched = $state(false);
    let portions = $state([]);
    let selectedKey = $state(null);
    let mode = $state('view');
    let snapEnabled = $state(true);
    let basemap = $state('ign-scan50-1950');
    let opacity = $state(0.8);
    let traceSource = $state('');
    let traceSourceTouched = $state(false);
    let notes = $state('');
    let replaces = $state({ source_trace_ids: [], line_segment_ids: [], line_ids: [] });
    let undoStack = $state([]);
    /** @type {Record<string, any>} */
    let errors = $state({});
    let processing = $state(false);
    let restored = $state(false);
    let suggestLine = $state(null);
    let importing = $state(false);
    let mapCmp = $state();
    let summaryEl = $state();

    // Recherche de ligne (correction)
    let query = $state('');
    let results = $state([]);
    let searchTimer = null;

    const totalLength = $derived(portions.reduce((s, p) => s + lengthM(p.coordinates), 0));
    const eventsCount = $derived(portions.reduce((s, p) => s + (p.events?.length ?? 0), 0));
    const replacedCount = $derived(
        replaces.source_trace_ids.length +
            replaces.line_segment_ids.length +
            replaces.line_ids.length,
    );
    const basemapDef = $derived(findHistoricBasemap(basemap));

    // Source du tracé pré-remplie avec le fond historique utilisé (modifiable).
    $effect(() => {
        if (traceSourceTouched) return;
        traceSource = basemapDef
            ? $i18n.t('editor.form.trace_source_auto', { source: historicTitle(basemapDef) })
            : '';
    });

    let keySeq = 0;
    function newKey() {
        keySeq += 1;
        return `p${Date.now().toString(36)}${keySeq}`;
    }

    // ── Annulation ─────────────────────────────────────────────────────────
    function pushUndo() {
        undoStack.push(JSON.stringify($state.snapshot({ portions, replaces, selectedKey })));
        if (undoStack.length > 60) undoStack.shift();
    }

    function undo() {
        const last = undoStack.pop();
        if (!last) return;
        const s = JSON.parse(last);
        portions = s.portions;
        replaces = s.replaces;
        selectedKey = s.selectedKey;
    }

    function onKeydown(e) {
        const tag = e.target?.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            e.preventDefault();
            undo();
        }
    }

    // ── Portions ───────────────────────────────────────────────────────────
    function removePortion(key) {
        if (!confirm($i18n.t('editor.portions.confirm_delete'))) return;
        pushUndo();
        portions = portions.filter((p) => p.key !== key);
        if (selectedKey === key) selectedKey = portions[0]?.key ?? null;
    }

    function selectPortion(key) {
        selectedKey = key;
        const p = portions.find((q) => q.key === key);
        if (p) mapCmp?.fitPortions([$state.snapshot(p.coordinates)]);
    }

    async function importFeature({ kind: fkind, id }) {
        if (portions.length >= limits.portions) return;
        importing = true;
        try {
            const res = await fetch(`/contribute/trace/feature/${fkind}/${id}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.parts?.length) return;
            pushUndo();
            const base = (data.name ?? '').slice(0, 120);
            const created = data.parts.map((coords, i) => ({
                key: newKey(),
                label: data.parts.length > 1 && base ? `${base} (${i + 1})` : base,
                from: '',
                to: '',
                coordinates: coords,
                events: [],
                origin: { kind: fkind, id },
            }));
            portions.push(...created);
            for (const k of Object.keys(replaces)) {
                for (const v of data.replaces?.[k] ?? [])
                    if (!replaces[k].includes(v)) replaces[k].push(v);
            }
            selectedKey = created[0].key;
            mode = 'edit';
            if (data.line && !attached && kind === 'new_line') suggestLine = data.line;
            mapCmp?.fitPortions(created.map((p) => p.coordinates));
        } finally {
            importing = false;
        }
    }

    function removeReplaced(k, id) {
        pushUndo();
        replaces[k] = replaces[k].filter((v) => v !== id);
    }

    // ── Ligne rattachée ─────────────────────────────────────────────────────
    function onQuery() {
        clearTimeout(searchTimer);
        const q = query.trim();
        if (q.length < 2) {
            results = [];
            return;
        }
        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/api/v1/search?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json();
                results = (data.lines ?? []).slice(0, 8);
            } catch (_) {
                results = [];
            }
        }, 250);
    }

    function attach(l) {
        attached = { slug: l.slug, name: l.name, code: l.code ?? null, network: l.network ?? null };
        kind = 'correction';
        query = '';
        results = [];
        suggestLine = null;
    }

    function detach() {
        attached = null;
    }

    function onLineLoaded(data) {
        if (!attached || data.line?.slug !== attached.slug) return;
        if (attached.id !== data.line.id || attached.network !== data.line.network) {
            attached = { ...attached, ...data.line };
        }
        if (!networkTouched && data.line.network && network !== data.line.network)
            network = data.line.network;
    }

    // ── Brouillon local ─────────────────────────────────────────────────────
    let saveTimer = null;
    $effect(() => {
        const snapshot = $state.snapshot({
            kind,
            attached,
            newLineName,
            network,
            portions,
            traceSource,
            traceSourceTouched,
            notes,
            replaces,
            basemap,
        });
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            try {
                if (snapshot.portions.length || snapshot.newLineName || snapshot.notes) {
                    localStorage.setItem(DRAFT_KEY, JSON.stringify(snapshot));
                }
            } catch (_) {
                /* stockage indisponible : pas de brouillon */
            }
        }, 600);
    });

    function clearDraft() {
        try {
            localStorage.removeItem(DRAFT_KEY);
        } catch (_) {
            /* ignore */
        }
    }

    function restartFromScratch() {
        clearDraft();
        portions = [];
        replaces = { source_trace_ids: [], line_segment_ids: [], line_ids: [] };
        notes = '';
        newLineName = '';
        selectedKey = null;
        undoStack = [];
        restored = false;
    }

    onMount(() => {
        try {
            const raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            const d = JSON.parse(raw);
            // Brouillon d'une autre ligne : on ne l'écrase pas, on l'ignore ici.
            if (line && d.attached?.slug !== line.slug) return;
            if (!d.portions?.length && !d.newLineName) return;
            kind = d.kind ?? kind;
            attached = d.attached ?? attached;
            newLineName = d.newLineName ?? '';
            network = d.network ?? network;
            portions = d.portions ?? [];
            traceSource = d.traceSource ?? '';
            traceSourceTouched = Boolean(d.traceSourceTouched);
            notes = d.notes ?? '';
            replaces = {
                source_trace_ids: [],
                line_segment_ids: [],
                line_ids: [],
                ...(d.replaces ?? {}),
            };
            basemap = d.basemap ?? basemap;
            selectedKey = portions[0]?.key ?? null;
            restored = true;
            setTimeout(() => mapCmp?.fitPortions(), 400);
        } catch (_) {
            /* brouillon illisible : ignoré */
        }
    });

    // ── Envoi ──────────────────────────────────────────────────────────────
    function clientErrors() {
        /** @type {Record<string, any>} */
        const e = {};
        if (kind === 'new_line' && newLineName.trim().length < 3)
            e.new_line_name = $i18n.t('editor.errors.name_required');
        if (kind === 'correction' && !attached)
            e.line_slug = $i18n.t('editor.errors.line_required');
        if (!portions.length) e.portions = $i18n.t('editor.errors.no_portion');
        if (traceSource.trim().length < 3)
            e.trace_source = $i18n.t('editor.errors.source_required');
        portions.forEach((p, i) => {
            (p.events ?? []).forEach((ev, j) => {
                if (!/^\d{4}(-\d{2}){0,2}$/.test(ev.date ?? ''))
                    e[`portions.${i}.events.${j}.date`] = $i18n.t('editor.errors.bad_date');
                if ((ev.source ?? '').trim().length < 3)
                    e[`portions.${i}.events.${j}.source`] = $i18n.t(
                        'editor.errors.source_required',
                    );
            });
        });
        return e;
    }

    function submit(e) {
        e.preventDefault();
        mode = 'view';
        errors = clientErrors();
        if (Object.keys(errors).length) {
            focusSummary();
            return;
        }
        const snap = $state.snapshot(portions);
        const data = {
            kind,
            line_slug: kind === 'correction' ? attached?.slug : null,
            new_line_name: kind === 'new_line' ? newLineName.trim() : null,
            network,
            basemap: basemap === 'none' ? null : basemap,
            trace_source: traceSource.trim(),
            notes: notes.trim() || null,
            portions: snap.map((p) => ({
                key: p.key,
                label: p.label || null,
                from: p.from || null,
                to: p.to || null,
                coordinates: p.coordinates,
                events: (p.events ?? []).map((ev) => ({
                    type: ev.type,
                    precision: ev.precision,
                    date: ev.date,
                    source: ev.source,
                    source_url: ev.source_url || null,
                    description: ev.description || null,
                })),
            })),
            replaces: $state.snapshot(replaces),
        };
        router.post('/contribute/trace', data, {
            preserveScroll: true,
            onStart: () => (processing = true),
            onFinish: () => (processing = false),
            onError: (errs) => {
                errors = errs;
                focusSummary();
            },
            onSuccess: () => clearDraft(),
        });
    }

    const errorList = $derived(Object.entries(errors));
    const portionHasError = (i) => errorList.some(([k]) => k.startsWith(`portions.${i}.`));

    async function focusSummary() {
        await tick();
        summaryEl?.focus();
    }

    // Récapitulatif des erreurs : nom du champ lisible (pas la clé technique) et lien vers le champ.
    function errorField(key) {
        const m = /^portions\.(\d+)\./.exec(key);
        if (m)
            return {
                label: $i18n.t('editor.errors.field_portion', { n: Number(m[1]) + 1 }),
                target: 'trace-portions',
            };
        const map = {
            new_line_name: 'trace-new-line-name',
            line_slug: 'trace-line-search',
            portions: 'trace-portions',
            trace_source: 'trace-source',
        };
        const k = `editor.errors.field_${key}`;
        const label = $i18n.t(k);
        return { label: label === k ? '' : label, target: map[key] ?? 'trace-portions' };
    }

    const errMsg = (v) => (Array.isArray(v) ? v.join(' ') : v);
</script>

<svelte:window onkeydown={onKeydown} />

<svelte:head>
    <title>{$i18n.t('editor.meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main class="trace-page">
        <PageHero title={$i18n.t('editor.title')} subtitle={$i18n.t('editor.subtitle')}>
            <p class="back">
                <Link href="/maps" class="vff-link"
                    ><i class="bi bi-arrow-left" aria-hidden="true"></i>
                    {$i18n.t('editor.back_to_map')}</Link
                >
            </p>
        </PageHero>

        <div class="vff-container body">
            {#if restored}
                <div class="restored">
                    <Notice tone="info" live="off" icon="bi-cloud-check">
                        <span>{$i18n.t('editor.draft.restored')}</span>
                        <button
                            type="button"
                            class="vff-btn quiet small"
                            onclick={restartFromScratch}>{$i18n.t('editor.draft.discard')}</button
                        >
                    </Notice>
                </div>
            {/if}

            <form class="layout" onsubmit={submit} novalidate>
                <section class="map-col" aria-label={$i18n.t('editor.toolbar.label')}>
                    <TraceEditorMap
                        bind:this={mapCmp}
                        bind:portions
                        bind:selectedKey
                        bind:mode
                        bind:snapEnabled
                        bind:basemap
                        bind:opacity
                        line={kind === 'correction' ? attached : null}
                        era={currentEra}
                        height="min(70vh, 720px)"
                        canUndo={undoStack.length > 0}
                        onUndo={undo}
                        onBeforeChange={pushUndo}
                        onImport={importFeature}
                        {onLineLoaded}
                        {newKey}
                    />
                    <p class="importing" aria-live="polite">
                        {#if importing}<i class="bi bi-hourglass-split" aria-hidden="true"></i>
                            {$i18n.t('editor.portions.importing')}{/if}
                    </p>
                    <details class="help" open>
                        <summary>{$i18n.t('editor.help.title')}</summary>
                        <div class="help-body">
                            <ol class="steps">
                                <li>{$i18n.t('editor.help.step_basemap')}</li>
                                <li>{$i18n.t('editor.help.step_draw')}</li>
                                <li>{$i18n.t('editor.help.step_take')}</li>
                                <li>{$i18n.t('editor.help.step_edit')}</li>
                                <li>{$i18n.t('editor.help.step_cut')}</li>
                                <li>{$i18n.t('editor.help.step_events')}</li>
                            </ol>
                            <div class="shortcuts">
                                <p class="sub">{$i18n.t('editor.help.shortcuts_title')}</p>
                                <dl>
                                    {#each ['enter', 'escape', 'backspace', 'undo', 'shift', 'alt'] as k (k)}
                                        <div>
                                            <dt><kbd>{$i18n.t(`editor.help.key_${k}`)}</kbd></dt>
                                            <dd>{$i18n.t(`editor.help.shortcut_${k}`)}</dd>
                                        </div>
                                    {/each}
                                </dl>
                            </div>
                        </div>
                    </details>
                </section>

                <div class="panel">
                    <div class="summary" tabindex="-1" bind:this={summaryEl}>
                        {#if errorList.length}
                            <Notice
                                tone="danger"
                                live="assertive"
                                title={$i18n.t('editor.errors.summary', {
                                    count: errorList.length,
                                })}
                            >
                                <ul>
                                    {#each errorList.slice(0, 8) as [k, msg] (k)}
                                        {@const f = errorField(k)}
                                        <li>
                                            <a href="#{f.target}"
                                                >{#if f.label}{f.label}{colon(
                                                        $i18n.language,
                                                    )}{/if}{errMsg(msg)}</a
                                            >
                                        </li>
                                    {/each}
                                </ul>
                            </Notice>
                        {/if}
                    </div>

                    <!-- 1. Ligne -->
                    <fieldset class="group">
                        <legend>{$i18n.t('editor.form.line_legend')}</legend>
                        <div
                            class="segmented"
                            role="radiogroup"
                            aria-label={$i18n.t('editor.form.kind_label')}
                        >
                            <label class:active={kind === 'new_line'}>
                                <input type="radio" bind:group={kind} value="new_line" />
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                {$i18n.t('editor.form.kind_new')}
                            </label>
                            <label class:active={kind === 'correction'}>
                                <input type="radio" bind:group={kind} value="correction" />
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                {$i18n.t('editor.form.kind_correction')}
                            </label>
                        </div>

                        {#if kind === 'new_line'}
                            <div class="vff-field">
                                <label class="vff-label" for="trace-new-line-name"
                                    >{$i18n.t('editor.form.new_line_name')}
                                    <span class="req" aria-hidden="true">*</span></label
                                >
                                <input
                                    id="trace-new-line-name"
                                    class="vff-input"
                                    type="text"
                                    bind:value={newLineName}
                                    maxlength="250"
                                    required
                                    placeholder={$i18n.t('editor.form.new_line_placeholder')}
                                    aria-invalid={errors.new_line_name ? 'true' : undefined}
                                    aria-describedby={errors.new_line_name
                                        ? 'err-new-line-name'
                                        : undefined}
                                />
                                {#if errors.new_line_name}<p
                                        class="vff-error"
                                        id="err-new-line-name"
                                    >
                                        <i class="bi bi-exclamation-circle" aria-hidden="true"
                                        ></i><span>{errMsg(errors.new_line_name)}</span>
                                    </p>{/if}
                            </div>
                            {#if suggestLine}
                                <div class="suggest">
                                    <i class="bi bi-link-45deg" aria-hidden="true"></i>
                                    <span
                                        >{$i18n.t('editor.form.suggest_line', {
                                            name: suggestLine.name,
                                        })}</span
                                    >
                                    <button
                                        type="button"
                                        class="vff-btn quiet small"
                                        onclick={() => attach(suggestLine)}
                                    >
                                        {$i18n.t('editor.form.suggest_attach')}
                                    </button>
                                </div>
                            {/if}
                        {:else if attached}
                            <div class="attached">
                                <p class="attached-name">
                                    <span class="vff-sr-only"
                                        >{$i18n.t('editor.form.line_from_sheet')}{colon(
                                            $i18n.language,
                                        )}</span
                                    >
                                    <strong>{attached.name}</strong>
                                    {#if attached.code}<span class="vff-plate">{attached.code}</span
                                        >{/if}
                                </p>
                                <div class="attached-actions">
                                    <Link href="/lines/{attached.slug}" class="vff-link"
                                        >{$i18n.t('editor.form.view_sheet')}</Link
                                    >
                                    <button
                                        type="button"
                                        class="vff-btn quiet small"
                                        onclick={detach}
                                        >{$i18n.t('editor.form.change_line')}</button
                                    >
                                </div>
                            </div>
                        {:else}
                            <div class="vff-field">
                                <label class="vff-label" for="trace-line-search"
                                    >{$i18n.t('editor.form.search_line')}
                                    <span class="req" aria-hidden="true">*</span></label
                                >
                                <input
                                    id="trace-line-search"
                                    class="vff-input"
                                    type="search"
                                    bind:value={query}
                                    oninput={onQuery}
                                    placeholder={$i18n.t('editor.form.search_placeholder')}
                                    autocomplete="off"
                                    aria-invalid={errors.line_slug ? 'true' : undefined}
                                    aria-describedby={errors.line_slug
                                        ? 'err-line-slug'
                                        : undefined}
                                />
                                {#if errors.line_slug}<p class="vff-error" id="err-line-slug">
                                        <i class="bi bi-exclamation-circle" aria-hidden="true"
                                        ></i><span>{errMsg(errors.line_slug)}</span>
                                    </p>{/if}
                            </div>
                            {#if results.length}
                                <ul class="results">
                                    {#each results as r (r.slug)}
                                        <li>
                                            <button type="button" onclick={() => attach(r)}>
                                                <span>{r.name}</span>{#if r.code}<span
                                                        class="vff-plate">{r.code}</span
                                                    >{/if}
                                            </button>
                                        </li>
                                    {/each}
                                </ul>
                            {/if}
                        {/if}
                    </fieldset>

                    <!-- 2. Catégorie -->
                    <fieldset class="group">
                        <legend>{$i18n.t('editor.form.network_legend')}</legend>
                        <div class="networks">
                            {#each networks as n (n)}
                                <label class="network" class:active={network === n}>
                                    <input
                                        type="radio"
                                        bind:group={network}
                                        value={n}
                                        onchange={() => (networkTouched = true)}
                                    />
                                    <strong>{$i18n.t(`editor.networks.${n}`)}</strong>
                                    <small>{$i18n.t(`editor.networks.${n}_hint`)}</small>
                                </label>
                            {/each}
                        </div>
                        {#if kind === 'correction' && attached?.network && attached.network !== network}
                            <p class="note warn">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                {$i18n.t('editor.form.network_change', {
                                    from: $i18n.t(`editor.networks.${attached.network}`),
                                    to: $i18n.t(`editor.networks.${network}`),
                                })}
                            </p>
                        {/if}
                    </fieldset>

                    <!-- 3. Portions -->
                    <fieldset class="group" id="trace-portions" tabindex="-1">
                        <legend>
                            {$i18n.t('editor.portions.legend')}
                            <span class="count"
                                >{$i18n.t('editor.portions.count', { count: portions.length })},
                                {formatKm(totalLength, $i18n.language)}, {$i18n.t(
                                    'editor.portions.events_count',
                                    { count: eventsCount },
                                )}</span
                            >
                        </legend>
                        {#if !portions.length}
                            <p class="empty">{$i18n.t('editor.portions.empty')}</p>
                            {#if errors.portions}<p class="vff-error">
                                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i><span
                                        >{errMsg(errors.portions)}</span
                                    >
                                </p>{/if}
                        {/if}
                        <ol class="portions">
                            {#each portions as p, i (p.key)}
                                <li
                                    class="portion"
                                    class:selected={p.key === selectedKey}
                                    class:has-error={portionHasError(i)}
                                >
                                    <div class="portion-head">
                                        <button
                                            type="button"
                                            class="dot"
                                            style:--c={portionColor(i)}
                                            onclick={() => selectPortion(p.key)}
                                            title={$i18n.t('editor.portions.zoom')}
                                            aria-label={$i18n.t('editor.portions.zoom')}
                                            aria-pressed={p.key === selectedKey}
                                        ></button>
                                        <input
                                            class="label-input"
                                            type="text"
                                            bind:value={p.label}
                                            maxlength="250"
                                            aria-label={$i18n.t('editor.errors.field_portion', {
                                                n: i + 1,
                                            })}
                                            placeholder={$i18n.t(
                                                'editor.portions.label_placeholder',
                                                {
                                                    n: i + 1,
                                                },
                                            )}
                                            onfocus={() => (selectedKey = p.key)}
                                        />
                                        <span class="len"
                                            >{formatKm(
                                                lengthM(p.coordinates),
                                                $i18n.language,
                                            )}</span
                                        >
                                        <button
                                            type="button"
                                            class="icon-btn"
                                            onclick={() => removePortion(p.key)}
                                            title={$i18n.t('editor.portions.delete')}
                                            aria-label={$i18n.t('editor.portions.delete')}
                                        >
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    {#if p.key === selectedKey}
                                        <div class="portion-body">
                                            <div class="grid-2">
                                                <label class="f">
                                                    <span>{$i18n.t('editor.portions.from')}</span>
                                                    <input
                                                        class="vff-input"
                                                        type="text"
                                                        bind:value={p.from}
                                                        maxlength="250"
                                                    />
                                                </label>
                                                <label class="f">
                                                    <span>{$i18n.t('editor.portions.to')}</span>
                                                    <input
                                                        class="vff-input"
                                                        type="text"
                                                        bind:value={p.to}
                                                        maxlength="250"
                                                    />
                                                </label>
                                            </div>
                                            <p class="sub">
                                                {$i18n.t('editor.portions.events_title')}
                                            </p>
                                            <PortionEvents
                                                bind:events={p.events}
                                                {eventTypes}
                                                {precisions}
                                                {errors}
                                                prefix={`portions.${i}.events`}
                                                defaultSource={traceSource}
                                                onBeforeChange={pushUndo}
                                            />
                                            {#if errors[`portions.${i}.coordinates`]}<p
                                                    class="vff-error"
                                                >
                                                    <i
                                                        class="bi bi-exclamation-circle"
                                                        aria-hidden="true"
                                                    ></i><span
                                                        >{errMsg(
                                                            errors[`portions.${i}.coordinates`],
                                                        )}</span
                                                    >
                                                </p>{/if}
                                        </div>
                                    {:else if p.events?.length}
                                        <p class="events-summary">
                                            {#each p.events as ev, j (j)}
                                                <span
                                                    >{$i18n.t(`editor.event_types.${ev.type}`)}
                                                    {ev.date || '?'}</span
                                                >
                                            {/each}
                                        </p>
                                    {/if}
                                </li>
                            {/each}
                        </ol>
                        {#if replacedCount}
                            <div class="replaced">
                                <p class="sub">{$i18n.t('editor.portions.replaces')}</p>
                                <ul>
                                    {#each [['source_trace_ids', 'replaced_trace'], ['line_segment_ids', 'replaced_segment'], ['line_ids', 'replaced_line']] as [k, labelKey] (k)}
                                        {#each replaces[k] as id (id)}
                                            <li>
                                                <span
                                                    >{$i18n.t(`editor.portions.${labelKey}`, {
                                                        id,
                                                    })}</span
                                                >
                                                <button
                                                    type="button"
                                                    class="icon-btn"
                                                    onclick={() => removeReplaced(k, id)}
                                                    title={$i18n.t('editor.events.remove')}
                                                    aria-label={`${$i18n.t('editor.events.remove')}${colon($i18n.language)}${$i18n.t(`editor.portions.${labelKey}`, { id })}`}
                                                    ><i class="bi bi-x-lg" aria-hidden="true"
                                                    ></i></button
                                                >
                                            </li>
                                        {/each}
                                    {/each}
                                </ul>
                            </div>
                        {/if}
                    </fieldset>

                    <!-- 4. Sources -->
                    <fieldset class="group">
                        <legend>{$i18n.t('editor.form.sources_legend')}</legend>
                        <div class="vff-field">
                            <label class="vff-label" for="trace-source"
                                >{$i18n.t('editor.form.trace_source')}
                                <span class="req" aria-hidden="true">*</span></label
                            >
                            <p class="vff-hint" id="trace-source-hint">
                                {$i18n.t('editor.form.trace_source_hint')}
                            </p>
                            <textarea
                                id="trace-source"
                                class="vff-textarea short"
                                rows="3"
                                bind:value={traceSource}
                                oninput={() => (traceSourceTouched = true)}
                                maxlength="500"
                                required
                                aria-invalid={errors.trace_source ? 'true' : undefined}
                                aria-describedby={errors.trace_source
                                    ? 'trace-source-hint err-trace-source'
                                    : 'trace-source-hint'}
                            ></textarea>
                            {#if errors.trace_source}<p class="vff-error" id="err-trace-source">
                                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i><span
                                        >{errMsg(errors.trace_source)}</span
                                    >
                                </p>{/if}
                        </div>
                        <div class="vff-field">
                            <label class="vff-label" for="trace-notes"
                                >{$i18n.t('editor.form.notes')}</label
                            >
                            <textarea
                                id="trace-notes"
                                class="vff-textarea"
                                rows="3"
                                bind:value={notes}
                                maxlength="2000"
                                placeholder={$i18n.t('editor.form.notes_placeholder')}
                            ></textarea>
                        </div>
                    </fieldset>

                    <div class="actions">
                        <button
                            type="submit"
                            class="vff-btn primary block"
                            disabled={processing}
                            aria-describedby={portions.length
                                ? 'trace-moderation'
                                : 'trace-needs-portion trace-moderation'}
                        >
                            <i class="bi bi-send" aria-hidden="true"></i>
                            {$i18n.t('editor.form.submit')}
                        </button>
                        {#if !portions.length}
                            <p class="needs" id="trace-needs-portion">
                                {$i18n.t('editor.form.submit_needs_portion')}
                            </p>
                        {/if}
                        <p class="moderation" id="trace-moderation">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            {$i18n.t('editor.form.moderation')}
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </main>
</Layout>

<style>
    .back {
        margin: var(--vff-space-4) 0 0;
    }
    .body {
        padding-block: var(--vff-block-space) var(--vff-section-space);
    }
    .restored {
        margin-bottom: var(--vff-space-5);
    }
    .restored :global(.vff-notice-body) {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 400px);
        gap: var(--vff-space-6);
        align-items: start;
    }
    .map-col {
        position: sticky;
        top: var(--vff-space-3);
        min-width: 0;
    }
    .importing {
        min-height: 1.4em;
        margin: var(--vff-space-2) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .help {
        margin-top: var(--vff-space-2);
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.55;
        color: var(--vff-text-muted);
    }
    .help summary {
        min-height: 24px;
        cursor: pointer;
        font-weight: 600;
        color: var(--vff-text);
    }
    .help-body {
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
        gap: var(--vff-space-3) var(--vff-space-6);
        margin-top: var(--vff-space-3);
    }
    .steps {
        margin: 0;
        padding-left: 1.3em;
    }
    .steps li + li {
        margin-top: 4px;
    }
    .shortcuts dl {
        display: grid;
        grid-template-columns: max-content minmax(0, 1fr);
        gap: 6px var(--vff-space-3);
        align-items: baseline;
        margin: 0;
    }
    .shortcuts dl > div {
        display: contents;
    }
    .shortcuts dd {
        margin: 0;
    }
    kbd {
        display: inline-block;
        padding: 1px 6px;
        border: 1px solid var(--vff-border-strong);
        border-bottom-width: 2px;
        border-radius: var(--vff-radius-xs);
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        white-space: nowrap;
    }
    .panel {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
    }
    .summary:empty {
        display: none;
    }
    .summary:focus {
        outline: none;
    }
    .summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .summary ul {
        margin: var(--vff-space-2) 0 0;
        padding-left: 1.2em;
    }
    .summary li a {
        display: inline-block;
        padding-block: 3px;
    }
    .group {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        min-width: 0;
        margin: 0;
        padding: var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
    }
    .group:focus {
        outline: none;
    }
    legend {
        float: left;
        width: 100%;
        margin: 0 0 var(--vff-space-1);
        padding: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        font-weight: 700;
        line-height: 1.3;
        color: var(--vff-text);
    }
    legend + * {
        clear: both;
    }
    legend .count {
        display: block;
        margin-top: 2px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .req {
        color: var(--vff-danger);
    }
    .segmented {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-2);
    }
    .segmented label,
    .network {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
        min-height: 44px;
        padding: 8px 12px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg);
        cursor: pointer;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .segmented label:hover,
    .network:hover {
        border-color: var(--vff-accent-ink);
    }
    .segmented input,
    .network input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .segmented label:has(input:focus-visible),
    .network:has(input:focus-visible) {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .segmented label.active,
    .network.active {
        border-color: var(--vff-accent);
        background: var(--vff-bg-accent);
        box-shadow: inset 0 0 0 1px var(--vff-accent);
        color: var(--vff-text);
    }
    .segmented label.active .bi {
        color: var(--vff-accent-ink);
    }
    .networks {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-2);
    }
    .network {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
    .network small {
        font-weight: 400;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        line-height: 1.35;
    }
    .suggest {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2);
        padding: var(--vff-space-2) var(--vff-space-3);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-accent);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .attached {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
    }
    .attached-name {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2);
        margin: 0;
        font-family: var(--vff-font-heading);
    }
    .attached-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .results {
        margin: 0;
        padding: 0;
        list-style: none;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        max-height: 260px;
        overflow: auto;
    }
    .results li + li {
        border-top: 1px solid var(--vff-border);
    }
    .results button {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2);
        width: 100%;
        min-height: 40px;
        padding: 8px 12px;
        text-align: left;
        background: var(--vff-bg);
        color: var(--vff-text);
        border: 0;
        cursor: pointer;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .results button:hover {
        background: var(--vff-bg-accent);
    }
    .results button:focus-visible {
        outline-offset: -2px;
    }
    .note {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .note.warn {
        color: var(--vff-warning);
    }
    .empty {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        line-height: 1.5;
    }
    .portions {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
    }
    .portions:empty {
        display: none;
    }
    .portion {
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg);
    }
    .portion.selected {
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 1px var(--vff-accent);
    }
    .portion.has-error {
        border-color: var(--vff-danger);
        box-shadow: 0 0 0 1px var(--vff-danger);
    }
    .portion-head {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
        padding: var(--vff-space-2);
    }
    .dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        padding: 0;
        background: transparent;
        border: 0;
        border-radius: 50%;
        cursor: pointer;
    }
    .dot::before {
        content: '';
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--c);
        box-shadow:
            0 0 0 2px var(--vff-bg),
            0 0 0 3px var(--vff-border-strong);
    }
    .label-input {
        flex: 1 1 auto;
        min-width: 0;
        min-height: 36px;
        padding: 6px 8px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .label-input:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 0;
        border-color: var(--vff-focus);
    }
    .len {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
        white-space: nowrap;
    }
    .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        padding: 0;
        background: transparent;
        border: 0;
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text-muted);
        cursor: pointer;
        transition: color var(--vff-transition);
    }
    .icon-btn:hover {
        color: var(--vff-danger);
        background: var(--vff-danger-bg);
    }
    .portion-body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        padding: 0 var(--vff-space-3) var(--vff-space-3);
    }
    .grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .f {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .f > span {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .f :global(.vff-input) {
        min-height: 36px;
        padding: 6px 8px;
        font-size: var(--vff-fs-sm);
    }
    .sub {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
    }
    .shortcuts .sub {
        margin-bottom: var(--vff-space-2);
    }
    .events-summary {
        margin: 0;
        padding: 0 var(--vff-space-3) var(--vff-space-2) 44px;
        display: flex;
        flex-wrap: wrap;
        gap: 4px 12px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .replaced ul {
        margin: var(--vff-space-1) 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .replaced li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2);
    }
    .vff-textarea.short {
        min-height: 88px;
    }
    .actions {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
    }
    .needs,
    .moderation {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .needs {
        color: var(--vff-text);
    }
    @media (max-width: 1180px) {
        .layout {
            grid-template-columns: minmax(0, 1fr);
        }
        .map-col {
            position: static;
        }
        .panel {
            max-width: 760px;
        }
    }
    @media (max-width: 900px) {
        .help-body {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        .networks,
        .grid-2 {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
