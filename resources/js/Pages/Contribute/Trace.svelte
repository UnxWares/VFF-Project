<script>
    import { onMount } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import TraceEditorMap from '$components/Contribute/TraceEditorMap.svelte';
    import PortionEvents from '$components/Contribute/PortionEvents.svelte';
    import { i18n } from '$lib/i18n.js';
    import { findHistoricBasemap, historicTitle } from '$lib/historicBasemaps.js';
    import { formatKm, lengthM, portionColor } from '$lib/traceGeometry.js';

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

    let kind = $state(line ? 'correction' : 'new_line');
    let attached = $state(line);
    let newLineName = $state('');
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
    let errors = $state({});
    let processing = $state(false);
    let restored = $state(false);
    let suggestLine = $state(null);
    let importing = $state(false);
    let mapCmp = $state();

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
            ? $i18n.t('editor.form.trace_source_auto', {
                  source: `${basemapDef.provider} - ${historicTitle(basemapDef)}`,
                  layer: basemapDef.layer,
              })
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
        if (Object.keys(errors).length) return;
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
            onError: (errs) => (errors = errs),
            onSuccess: () => clearDraft(),
        });
    }

    const errorList = $derived(Object.entries(errors));
    const portionHasError = (i) => errorList.some(([k]) => k.startsWith(`portions.${i}.`));
</script>

<svelte:window onkeydown={onKeydown} />

<svelte:head>
    <title>{$i18n.t('editor.meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main class="trace-page">
        <div class="page-head">
            <a href="/maps" class="back-link"
                ><i class="bi bi-arrow-left"></i> {$i18n.t('editor.back_to_map')}</a
            >
            <h1>{$i18n.t('editor.title')}</h1>
            <p class="subtitle">{$i18n.t('editor.subtitle')}</p>
        </div>

        {#if restored}
            <div class="banner">
                <i class="bi bi-cloud-check"></i>
                <span>{$i18n.t('editor.draft.restored')}</span>
                <button type="button" class="link-btn" onclick={restartFromScratch}
                    >{$i18n.t('editor.draft.discard')}</button
                >
            </div>
        {/if}

        <form class="layout" onsubmit={submit} novalidate>
            <section class="map-col">
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
                    height="min(72vh, 760px)"
                    canUndo={undoStack.length > 0}
                    onUndo={undo}
                    onBeforeChange={pushUndo}
                    onImport={importFeature}
                    {onLineLoaded}
                    {newKey}
                />
                {#if importing}<p class="importing">
                        <i class="bi bi-hourglass-split"></i>
                        {$i18n.t('editor.portions.importing')}
                    </p>{/if}
                <details class="help">
                    <summary
                        ><i class="bi bi-question-circle"></i>
                        {$i18n.t('editor.help.title')}</summary
                    >
                    <ol>
                        <li>{$i18n.t('editor.help.step_basemap')}</li>
                        <li>{$i18n.t('editor.help.step_draw')}</li>
                        <li>{$i18n.t('editor.help.step_take')}</li>
                        <li>{$i18n.t('editor.help.step_edit')}</li>
                        <li>{$i18n.t('editor.help.step_cut')}</li>
                        <li>{$i18n.t('editor.help.step_events')}</li>
                    </ol>
                </details>
            </section>

            <aside class="panel">
                <!-- 1. Ligne -->
                <fieldset>
                    <legend>{$i18n.t('editor.form.line_legend')}</legend>
                    <div class="segmented" role="radiogroup">
                        <label class:active={kind === 'new_line'}>
                            <input type="radio" bind:group={kind} value="new_line" />
                            <i class="bi bi-plus-circle"></i>
                            {$i18n.t('editor.form.kind_new')}
                        </label>
                        <label class:active={kind === 'correction'}>
                            <input type="radio" bind:group={kind} value="correction" />
                            <i class="bi bi-pencil-square"></i>
                            {$i18n.t('editor.form.kind_correction')}
                        </label>
                    </div>

                    {#if kind === 'new_line'}
                        <label class="field">
                            <span>{$i18n.t('editor.form.new_line_name')} <b class="req">*</b></span>
                            <input
                                type="text"
                                bind:value={newLineName}
                                maxlength="250"
                                placeholder={$i18n.t('editor.form.new_line_placeholder')}
                            />
                            {#if errors.new_line_name}<em class="error">{errors.new_line_name}</em
                                >{/if}
                        </label>
                        {#if suggestLine}
                            <div class="suggest">
                                <i class="bi bi-link-45deg"></i>
                                <span
                                    >{$i18n.t('editor.form.suggest_line', {
                                        name: suggestLine.name,
                                    })}</span
                                >
                                <button
                                    type="button"
                                    class="link-btn"
                                    onclick={() => attach(suggestLine)}
                                >
                                    {$i18n.t('editor.form.suggest_attach')}
                                </button>
                            </div>
                        {/if}
                    {:else if attached}
                        <div class="attached">
                            <div>
                                <strong>{attached.name}</strong>
                                {#if attached.code}<span class="code">{attached.code}</span>{/if}
                            </div>
                            <div class="attached-actions">
                                <a href="/lines/{attached.slug}" target="_blank" rel="noopener"
                                    >{$i18n.t('editor.form.view_sheet')}</a
                                >
                                <button type="button" class="link-btn" onclick={detach}
                                    >{$i18n.t('editor.form.change_line')}</button
                                >
                            </div>
                        </div>
                    {:else}
                        <label class="field">
                            <span>{$i18n.t('editor.form.search_line')} <b class="req">*</b></span>
                            <input
                                type="search"
                                bind:value={query}
                                oninput={onQuery}
                                placeholder={$i18n.t('editor.form.search_placeholder')}
                                autocomplete="off"
                            />
                        </label>
                        {#if results.length}
                            <ul class="results">
                                {#each results as r (r.slug)}
                                    <li>
                                        <button type="button" onclick={() => attach(r)}>
                                            <strong>{r.name}</strong>{#if r.code}<span class="code"
                                                    >{r.code}</span
                                                >{/if}
                                        </button>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                        {#if errors.line_slug}<em class="error">{errors.line_slug}</em>{/if}
                    {/if}
                </fieldset>

                <!-- 2. Catégorie -->
                <fieldset>
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
                            <i class="bi bi-exclamation-triangle"></i>
                            {$i18n.t('editor.form.network_change', {
                                from: $i18n.t(`editor.networks.${attached.network}`),
                                to: $i18n.t(`editor.networks.${network}`),
                            })}
                        </p>
                    {/if}
                </fieldset>

                <!-- 3. Portions -->
                <fieldset>
                    <legend>
                        {$i18n.t('editor.portions.legend')}
                        <span class="count"
                            >{portions.length} · {formatKm(totalLength, $i18n.language)} · {$i18n.t(
                                'editor.portions.events_count',
                                { count: eventsCount },
                            )}</span
                        >
                    </legend>
                    {#if !portions.length}
                        <p class="empty">{$i18n.t('editor.portions.empty')}</p>
                        {#if errors.portions}<em class="error">{errors.portions}</em>{/if}
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
                                        style="--c: {portionColor(i)}"
                                        onclick={() => selectPortion(p.key)}
                                        title={$i18n.t('editor.portions.zoom')}
                                        aria-label={$i18n.t('editor.portions.zoom')}
                                    ></button>
                                    <input
                                        class="label-input"
                                        type="text"
                                        bind:value={p.label}
                                        maxlength="250"
                                        placeholder={$i18n.t('editor.portions.label_placeholder', {
                                            n: i + 1,
                                        })}
                                        onfocus={() => (selectedKey = p.key)}
                                    />
                                    <span class="len"
                                        >{formatKm(lengthM(p.coordinates), $i18n.language)}</span
                                    >
                                    <button
                                        type="button"
                                        class="icon-btn"
                                        onclick={() => removePortion(p.key)}
                                        title={$i18n.t('editor.portions.delete')}
                                    >
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                                {#if p.key === selectedKey}
                                    <div class="portion-body">
                                        <div class="grid-2">
                                            <label class="field">
                                                <span>{$i18n.t('editor.portions.from')}</span>
                                                <input
                                                    type="text"
                                                    bind:value={p.from}
                                                    maxlength="250"
                                                />
                                            </label>
                                            <label class="field">
                                                <span>{$i18n.t('editor.portions.to')}</span>
                                                <input
                                                    type="text"
                                                    bind:value={p.to}
                                                    maxlength="250"
                                                />
                                            </label>
                                        </div>
                                        <p class="sub">{$i18n.t('editor.portions.events_title')}</p>
                                        <PortionEvents
                                            bind:events={p.events}
                                            {eventTypes}
                                            {precisions}
                                            {errors}
                                            prefix={`portions.${i}.events`}
                                            defaultSource={traceSource}
                                            onBeforeChange={pushUndo}
                                        />
                                        {#if errors[`portions.${i}.coordinates`]}<em class="error"
                                                >{errors[`portions.${i}.coordinates`]}</em
                                            >{/if}
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
                                {#each replaces.source_trace_ids as id (id)}
                                    <li>
                                        {$i18n.t('editor.portions.replaced_trace', { id })}
                                        <button
                                            type="button"
                                            class="link-btn"
                                            onclick={() => removeReplaced('source_trace_ids', id)}
                                            >×</button
                                        >
                                    </li>
                                {/each}
                                {#each replaces.line_segment_ids as id (id)}
                                    <li>
                                        {$i18n.t('editor.portions.replaced_segment', { id })}
                                        <button
                                            type="button"
                                            class="link-btn"
                                            onclick={() => removeReplaced('line_segment_ids', id)}
                                            >×</button
                                        >
                                    </li>
                                {/each}
                                {#each replaces.line_ids as id (id)}
                                    <li>
                                        {$i18n.t('editor.portions.replaced_line', { id })}
                                        <button
                                            type="button"
                                            class="link-btn"
                                            onclick={() => removeReplaced('line_ids', id)}>×</button
                                        >
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/if}
                </fieldset>

                <!-- 4. Sources -->
                <fieldset>
                    <legend>{$i18n.t('editor.form.sources_legend')}</legend>
                    <label class="field">
                        <span>{$i18n.t('editor.form.trace_source')} <b class="req">*</b></span>
                        <textarea
                            rows="2"
                            bind:value={traceSource}
                            oninput={() => (traceSourceTouched = true)}
                            maxlength="500"
                        ></textarea>
                        <small class="hint">{$i18n.t('editor.form.trace_source_hint')}</small>
                        {#if errors.trace_source}<em class="error">{errors.trace_source}</em>{/if}
                    </label>
                    <label class="field">
                        <span>{$i18n.t('editor.form.notes')}</span>
                        <textarea
                            rows="3"
                            bind:value={notes}
                            maxlength="2000"
                            placeholder={$i18n.t('editor.form.notes_placeholder')}
                        ></textarea>
                    </label>
                </fieldset>

                {#if errorList.length}
                    <div class="errors" role="alert">
                        <strong
                            >{$i18n.t('editor.errors.summary', { count: errorList.length })}</strong
                        >
                        <ul>
                            {#each errorList.slice(0, 6) as [k, msg] (k)}
                                <li><code>{k}</code> {msg}</li>
                            {/each}
                        </ul>
                    </div>
                {/if}

                <div class="actions">
                    <p class="moderation">
                        <i class="bi bi-shield-check"></i>
                        {$i18n.t('editor.form.moderation')}
                    </p>
                    <button
                        type="submit"
                        class="vff-btn primary"
                        disabled={processing || !portions.length}
                    >
                        <i class="bi bi-send-fill"></i>
                        {$i18n.t('editor.form.submit')}
                    </button>
                </div>
            </aside>
        </form>
    </main>
</Layout>

<style>
    .trace-page {
        max-width: 1560px;
        margin: 0 auto;
        padding: 32px 20px 56px;
    }
    .page-head {
        margin-bottom: 20px;
    }
    .back-link {
        color: var(--vff-text-muted);
        font-family: var(--vff-font-display);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-decoration: none;
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }
    .back-link:hover {
        color: var(--vff-accent);
    }
    h1 {
        font-family: var(--vff-font-display);
        font-size: 30px;
        margin: 12px 0 6px;
    }
    h1:after {
        display: none;
    }
    .subtitle {
        color: var(--vff-text-muted);
        font-size: 15px;
        line-height: 1.5;
        max-width: 900px;
        margin: 0;
    }
    .banner,
    .suggest {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        padding: 10px 14px;
        margin-bottom: 14px;
        background: var(--vff-accent-soft);
        border: 1px solid var(--vff-accent);
        border-radius: var(--vff-radius-sm);
        font-size: 14px;
    }
    .suggest {
        margin: 10px 0 0;
        font-size: 13px;
    }
    .layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 430px;
        gap: 20px;
        align-items: start;
    }
    .map-col {
        position: sticky;
        top: 12px;
        min-width: 0;
    }
    .importing {
        margin: 8px 0 0;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .help {
        margin-top: 12px;
        font-size: 14px;
        color: var(--vff-text-muted);
    }
    .help summary {
        cursor: pointer;
        font-family: var(--vff-font-display);
        font-weight: 600;
        color: var(--vff-text);
    }
    .help ol {
        margin: 8px 0 0;
        padding-left: 20px;
        line-height: 1.6;
    }
    .panel {
        display: flex;
        flex-direction: column;
        gap: 16px;
        min-width: 0;
    }
    fieldset {
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 14px;
        margin: 0;
        min-width: 0;
        background: var(--vff-bg-elevated);
    }
    legend {
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--vff-accent);
        padding: 0 6px;
    }
    legend .count {
        color: var(--vff-text-muted);
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
        margin-left: 6px;
    }
    .segmented {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        margin-bottom: 12px;
    }
    .segmented label,
    .network {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 10px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        cursor: pointer;
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 600;
    }
    .segmented input,
    .network input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .segmented label.active,
    .network.active {
        border-color: var(--vff-accent);
        background: var(--vff-accent-soft);
        color: var(--vff-accent);
    }
    .networks {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
    }
    .network {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        position: relative;
    }
    .network small {
        font-family: var(--vff-font-body);
        font-weight: 400;
        font-size: 11.5px;
        color: var(--vff-text-muted);
        line-height: 1.35;
    }
    .field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .field + .field {
        margin-top: 10px;
    }
    .field > span {
        font-family: var(--vff-font-display);
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted);
    }
    .field input,
    .field textarea,
    .label-input {
        padding: 8px 10px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text);
        font-family: var(--vff-font-body);
        font-size: 14px;
        min-width: 0;
    }
    .field input:focus,
    .field textarea:focus,
    .label-input:focus {
        outline: none;
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 3px var(--vff-accent-soft);
    }
    .hint {
        color: var(--vff-text-muted);
        font-size: 12px;
    }
    .req,
    .error {
        color: #c53030;
    }
    .error {
        font-size: 12.5px;
        font-style: normal;
    }
    .attached {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 10px 12px;
        background: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
    }
    .attached-actions {
        display: flex;
        gap: 14px;
        font-size: 13px;
    }
    .attached-actions a {
        color: var(--vff-accent);
    }
    .code {
        margin-left: 6px;
        font-family: var(--vff-font-secondary, monospace);
        color: var(--vff-accent);
        font-size: 13px;
    }
    .results {
        list-style: none;
        margin: 6px 0 0;
        padding: 0;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        max-height: 240px;
        overflow: auto;
    }
    .results button {
        display: block;
        width: 100%;
        text-align: left;
        padding: 8px 10px;
        background: var(--vff-bg);
        color: var(--vff-text);
        border: 0;
        border-bottom: 1px solid var(--vff-border);
        cursor: pointer;
        font-size: 13px;
    }
    .results button:hover {
        background: var(--vff-accent-soft);
    }
    .link-btn {
        background: none;
        border: 0;
        padding: 0;
        color: var(--vff-accent);
        font-weight: 600;
        cursor: pointer;
        font-size: inherit;
    }
    .note {
        margin: 10px 0 0;
        font-size: 13px;
    }
    .note.warn {
        color: #b45309;
    }
    .empty {
        margin: 0;
        font-size: 13.5px;
        color: var(--vff-text-muted);
        line-height: 1.5;
    }
    .portions {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .portion {
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg);
    }
    .portion.selected {
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 2px var(--vff-accent-soft);
    }
    .portion.has-error {
        border-color: #c53030;
    }
    .portion-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
    }
    .dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--c);
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px var(--vff-border);
        flex-shrink: 0;
        cursor: pointer;
        padding: 0;
    }
    .label-input {
        flex: 1 1 auto;
        padding: 5px 8px;
        font-size: 13.5px;
    }
    .len {
        font-family: var(--vff-font-secondary, monospace);
        font-size: 12px;
        color: var(--vff-text-muted);
        white-space: nowrap;
    }
    .icon-btn {
        background: transparent;
        border: 0;
        color: var(--vff-text-muted);
        cursor: pointer;
        padding: 4px;
    }
    .icon-btn:hover {
        color: #c53030;
    }
    .portion-body {
        padding: 0 10px 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .grid-2 .field + .field {
        margin-top: 0;
    }
    .sub {
        margin: 6px 0 0;
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text);
    }
    .events-summary {
        margin: 0;
        padding: 0 10px 8px 32px;
        display: flex;
        flex-wrap: wrap;
        gap: 4px 10px;
        font-size: 12px;
        color: var(--vff-text-muted);
    }
    .replaced ul {
        margin: 4px 0 0;
        padding-left: 18px;
        font-size: 12.5px;
        color: var(--vff-text-muted);
    }
    .errors {
        padding: 10px 14px;
        border: 1px solid #c53030;
        border-radius: var(--vff-radius-sm);
        background: rgba(197, 48, 48, 0.07);
        font-size: 13px;
    }
    .errors ul {
        margin: 6px 0 0;
        padding-left: 18px;
    }
    .errors code {
        font-size: 11px;
        color: var(--vff-text-muted);
    }
    .actions {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .moderation {
        margin: 0;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .actions .vff-btn {
        border: 0;
        cursor: pointer;
        justify-content: center;
    }
    .actions .vff-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    @media (max-width: 1100px) {
        .layout {
            grid-template-columns: minmax(0, 1fr);
        }
        .map-col {
            position: static;
        }
    }
    @media (max-width: 520px) {
        .networks,
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>
