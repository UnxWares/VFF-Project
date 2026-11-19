<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import { tick, untrack } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import EditMap from '$components/Contribute/EditMap.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { i18n } from '$lib/i18n.js';
    import { eraLabel } from '$components/Contribute/segmentChanges.js';

    let { preselectedLine = null, eras = [], statuses = [], trafficTypes = [] } = $props();

    /** Traduction passée aux fonctions de segmentChanges.js. */
    const tr = (key, opts) => $i18n.t(key, opts);

    // Saisie du PK en « kilomètre + mètres », convertie en mètres à l'envoi.
    let pkStartKm = $state(0);
    let pkStartM = $state(0);
    let pkEndKm = $state(1);
    let pkEndM = $state(0);
    let sourcesRaw = $state('');
    let heritage = $state(''); // '' (inchangé) | 'yes' | 'no'
    // Après une première tentative d'envoi, les erreurs se mettent à jour pendant la saisie.
    let attempted = $state(false);
    let summaryEl = $state();

    // Ligne : présélectionnée depuis une fiche, ou retrouvée à partir du code saisi. Valeurs
    // initiales voulues : la page est rechargée pour une autre ligne.
    // svelte-ignore state_referenced_locally
    let line = $state(preselectedLine);
    // svelte-ignore state_referenced_locally
    let lookup = $state(preselectedLine ? 'found' : 'idle'); // idle | searching | found | notfound
    let lookupTimer = null;
    let lookupSeq = 0;

    // Valeurs initiales du formulaire (lues une fois, voulu).
    const initialCode = untrack(() => preselectedLine?.code ?? '');
    const initialEra = untrack(() => eras.find((e) => e.is_current)?.year ?? 2030);
    const form = useForm({
        line_code: initialCode,
        era_year: initialEra,
        pk_start_m: 0,
        pk_end_m: 1000,
        changes: {
            status: '',
            traffic_type: '',
            voltage_v: null,
            max_speed_kmh: null,
        },
        sources: [],
        notes: '',
    });

    function setPkStart(m) {
        pkStartKm = m === null ? 0 : Math.floor(m / 1000);
        pkStartM = m === null ? 0 : m % 1000;
    }
    function setPkEnd(m) {
        pkEndKm = m === null ? 1 : Math.floor(m / 1000);
        pkEndM = m === null ? 0 : m % 1000;
    }

    const normalizeCode = (s) => String(s ?? '').replace(/\s+/g, '');

    function onLineCodeInput() {
        clearTimeout(lookupTimer);
        const code = normalizeCode(form.line_code);
        if (line && normalizeCode(line.code) === code) return;
        line = null;
        if (code.length < 3) {
            lookup = 'idle';
            return;
        }
        lookup = 'searching';
        const seq = ++lookupSeq;
        lookupTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/api/v1/search?q=${encodeURIComponent(code)}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = res.ok ? await res.json() : {};
                if (seq !== lookupSeq) return;
                const found = (data.lines ?? []).find((l) => normalizeCode(l.code) === code);
                line = found ? { slug: found.slug, code: found.code, name: found.name } : null;
                lookup = found ? 'found' : 'notfound';
            } catch (_) {
                if (seq === lookupSeq) lookup = 'notfound';
            }
        }, 350);
    }

    function changesPayload() {
        const c = {};
        if (form.changes.status) c.status = form.changes.status;
        if (form.changes.traffic_type) c.traffic_type = form.changes.traffic_type;
        if (heritage) c.is_heritage = heritage === 'yes';
        for (const k of ['max_speed_kmh', 'voltage_v']) {
            const v = form.changes[k];
            if (v !== null && v !== undefined && v !== '') c[k] = Number(v);
        }
        return c;
    }

    function sourcesList() {
        return sourcesRaw
            .split(/\n/)
            .map((s) => s.trim())
            .filter(Boolean);
    }

    const pkStart = $derived(Number(pkStartKm || 0) * 1000 + Number(pkStartM || 0));
    const pkEnd = $derived(Number(pkEndKm || 0) * 1000 + Number(pkEndM || 0));

    function validate() {
        const e = {};
        if (!normalizeCode(form.line_code))
            e.line_code = $i18n.t('editor.segment.errors.line_required');
        else if (lookup === 'notfound') e.line_code = $i18n.t('editor.segment.line_not_found');
        if (!(pkEnd > pkStart)) e.pk_end_m = $i18n.t('editor.segment.errors.pk_order');
        if (!Object.keys(changesPayload()).length)
            e.changes = $i18n.t('editor.segment.errors.no_change');
        if (!sourcesList().length) e.sources = $i18n.t('editor.segment.errors.source_required');
        return e;
    }

    const clientErrors = $derived(attempted ? validate() : {});
    // Erreurs affichées : celles du navigateur, sinon celles renvoyées par le serveur.
    const errors = $derived({ ...form.errors, ...clientErrors });
    const errorCount = $derived(Object.keys(errors).length);

    // Ordre des champs pour le récapitulatif et le focus.
    const FIELD_IDS = {
        line_code: 'line-code',
        era_year: 'era',
        pk_start_m: 'pk-start-km',
        pk_end_m: 'pk-end-km',
        changes: 'change-status',
        sources: 'sources',
        notes: 'notes',
    };

    async function showErrors() {
        await tick();
        summaryEl?.focus();
    }

    function submit(e) {
        e.preventDefault();
        attempted = true;
        if (Object.keys(validate()).length) {
            showErrors();
            return;
        }
        form.transform((data) => ({
            ...data,
            line_code: normalizeCode(data.line_code),
            pk_start_m: pkStart,
            pk_end_m: pkEnd,
            changes: changesPayload(),
            sources: sourcesList(),
            notes: data.notes?.trim() || null,
        })).post('/contribute/edit-segment', { onError: () => showErrors() });
    }

    function errorFor(key) {
        return errors[key] ?? errors[`${key}.0`] ?? null;
    }
</script>

<svelte:head>
    <title>{$i18n.t('editor.segment.meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main class="segment-page">
        <PageHero
            title={$i18n.t('editor.segment.title')}
            subtitle={$i18n.t('editor.segment.subtitle')}
        >
            <p class="back">
                <Link href="/maps" class="vff-link"
                    ><i class="bi bi-arrow-left" aria-hidden="true"></i>
                    {$i18n.t('editor.segment.back_to_map')}</Link
                >
            </p>
        </PageHero>

        <div class="vff-container body">
            <form onsubmit={submit} class="segment-form" novalidate>
                <div class="summary" tabindex="-1" bind:this={summaryEl}>
                    {#if errorCount}
                        <Notice
                            tone="danger"
                            live="assertive"
                            title={$i18n.t('editor.segment.errors.summary', { count: errorCount })}
                        >
                            <ul>
                                {#each Object.entries(errors) as [key, msg] (key)}
                                    <li>
                                        <a href="#{FIELD_IDS[key.split('.')[0]] ?? 'line-code'}"
                                            >{Array.isArray(msg) ? msg.join(' ') : msg}</a
                                        >
                                    </li>
                                {/each}
                            </ul>
                        </Notice>
                    {/if}
                </div>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('editor.segment.line_legend')}</legend>
                    <div class="grid-2">
                        <Field
                            id="line-code"
                            label={$i18n.t('editor.segment.line_code')}
                            hint={$i18n.t('editor.segment.line_code_hint')}
                            error={errorFor('line_code')}
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder={$i18n.t('editor.segment.line_code_placeholder')}
                                    bind:value={form.line_code}
                                    oninput={onLineCodeInput}
                                    required
                                />
                            {/snippet}
                        </Field>
                        <Field
                            id="era"
                            label={$i18n.t('editor.segment.era')}
                            error={errorFor('era_year')}
                        >
                            {#snippet children(attrs)}
                                <select {...attrs} class="vff-select" bind:value={form.era_year}>
                                    {#each eras as e (e.year)}
                                        <option value={e.year}>{eraLabel(e.year, tr)}</option>
                                    {/each}
                                </select>
                            {/snippet}
                        </Field>
                    </div>
                    <p class="lookup" aria-live="polite">
                        {#if lookup === 'found' && line}
                            <i class="bi bi-check2" aria-hidden="true"></i>
                            {$i18n.t('editor.segment.line_found', { name: line.name })}
                        {:else if lookup === 'searching'}
                            {$i18n.t('editor.segment.line_searching')}
                        {/if}
                    </p>
                </fieldset>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('editor.segment.pk_legend')}</legend>
                    <p class="intro">{$i18n.t('editor.segment.pk_hint')}</p>
                    {#if line?.slug}
                        {#key `${line.slug}-${form.era_year}`}
                            <EditMap
                                lineSlug={line.slug}
                                lineName={line.name}
                                eraYear={form.era_year}
                                onPkStart={setPkStart}
                                onPkEnd={setPkEnd}
                            />
                        {/key}
                    {:else}
                        <Notice tone="info" live="off"
                            >{$i18n.t('editor.segment.hint_need_line')}</Notice
                        >
                    {/if}
                    <div class="pk-row">
                        <div class="pk-block" role="group" aria-labelledby="pk-start-label">
                            <p class="vff-label" id="pk-start-label">
                                {$i18n.t('editor.segment.pk_start')}
                            </p>
                            <div class="pk-inputs">
                                <label class="pk-part">
                                    <span>{$i18n.t('editor.segment.pk_km')}</span>
                                    <input
                                        id="pk-start-km"
                                        class="vff-input"
                                        type="number"
                                        inputmode="numeric"
                                        min="0"
                                        max="9999"
                                        bind:value={pkStartKm}
                                    />
                                </label>
                                <span class="plus" aria-hidden="true">+</span>
                                <label class="pk-part">
                                    <span>{$i18n.t('editor.segment.pk_m')}</span>
                                    <input
                                        class="vff-input"
                                        type="number"
                                        inputmode="numeric"
                                        min="0"
                                        max="999"
                                        bind:value={pkStartM}
                                    />
                                </label>
                            </div>
                        </div>
                        <div class="pk-block" role="group" aria-labelledby="pk-end-label">
                            <p class="vff-label" id="pk-end-label">
                                {$i18n.t('editor.segment.pk_end')}
                            </p>
                            <div class="pk-inputs">
                                <label class="pk-part">
                                    <span>{$i18n.t('editor.segment.pk_km')}</span>
                                    <input
                                        id="pk-end-km"
                                        class="vff-input"
                                        type="number"
                                        inputmode="numeric"
                                        min="0"
                                        max="9999"
                                        bind:value={pkEndKm}
                                        aria-invalid={errorFor('pk_end_m') ? 'true' : undefined}
                                        aria-describedby="pk-error"
                                    />
                                </label>
                                <span class="plus" aria-hidden="true">+</span>
                                <label class="pk-part">
                                    <span>{$i18n.t('editor.segment.pk_m')}</span>
                                    <input
                                        class="vff-input"
                                        type="number"
                                        inputmode="numeric"
                                        min="0"
                                        max="999"
                                        bind:value={pkEndM}
                                        aria-invalid={errorFor('pk_end_m') ? 'true' : undefined}
                                        aria-describedby="pk-error"
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                    <p class="vff-error" id="pk-error" aria-live="polite">
                        {#if errorFor('pk_end_m') || errorFor('pk_start_m')}<i
                                class="bi bi-exclamation-circle"
                                aria-hidden="true"
                            ></i><span>{errorFor('pk_end_m') ?? errorFor('pk_start_m')}</span>{/if}
                    </p>
                </fieldset>

                <fieldset class="vff-panel group" aria-describedby="changes-hint changes-error">
                    <legend>{$i18n.t('editor.segment.changes_legend')}</legend>
                    <p class="intro" id="changes-hint">{$i18n.t('editor.segment.changes_hint')}</p>
                    <div class="grid-2">
                        <Field id="change-status" label={$i18n.t('editor.segment.status')}>
                            {#snippet children(attrs)}
                                <select
                                    {...attrs}
                                    class="vff-select"
                                    bind:value={form.changes.status}
                                >
                                    <option value="">{$i18n.t('editor.segment.unchanged')}</option>
                                    {#each statuses as s (s)}
                                        <option value={s}
                                            >{$i18n.t(`editor.segment.statuses.${s}`)}</option
                                        >
                                    {/each}
                                </select>
                            {/snippet}
                        </Field>
                        <Field id="change-traffic" label={$i18n.t('editor.segment.traffic')}>
                            {#snippet children(attrs)}
                                <select
                                    {...attrs}
                                    class="vff-select"
                                    bind:value={form.changes.traffic_type}
                                >
                                    <option value="">{$i18n.t('editor.segment.unchanged')}</option>
                                    {#each trafficTypes as t (t)}
                                        <option value={t}
                                            >{$i18n.t(`editor.segment.traffics.${t}`)}</option
                                        >
                                    {/each}
                                </select>
                            {/snippet}
                        </Field>
                        <Field id="change-heritage" label={$i18n.t('editor.segment.heritage')}>
                            {#snippet children(attrs)}
                                <select {...attrs} class="vff-select" bind:value={heritage}>
                                    <option value="">{$i18n.t('editor.segment.unchanged')}</option>
                                    <option value="yes"
                                        >{$i18n.t('editor.segment.values.yes')}</option
                                    >
                                    <option value="no">{$i18n.t('editor.segment.values.no')}</option
                                    >
                                </select>
                            {/snippet}
                        </Field>
                        <Field
                            id="change-speed"
                            label={$i18n.t('editor.segment.max_speed')}
                            error={errorFor('changes.max_speed_kmh')}
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    max="600"
                                    bind:value={form.changes.max_speed_kmh}
                                />
                            {/snippet}
                        </Field>
                        <Field
                            id="change-voltage"
                            label={$i18n.t('editor.segment.voltage')}
                            error={errorFor('changes.voltage_v')}
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    max="50000"
                                    bind:value={form.changes.voltage_v}
                                />
                            {/snippet}
                        </Field>
                    </div>
                    <p class="vff-error" id="changes-error" aria-live="polite">
                        {#if errorFor('changes')}<i
                                class="bi bi-exclamation-circle"
                                aria-hidden="true"
                            ></i><span>{errorFor('changes')}</span>{/if}
                    </p>
                </fieldset>

                <div class="vff-panel group">
                    <Field
                        id="sources"
                        label={$i18n.t('editor.segment.sources_label')}
                        hint={$i18n.t('editor.segment.sources_hint')}
                        error={errorFor('sources')}
                        required
                    >
                        {#snippet children(attrs)}
                            <textarea
                                {...attrs}
                                class="vff-textarea"
                                rows="3"
                                bind:value={sourcesRaw}
                                placeholder={$i18n.t('editor.segment.sources_placeholder')}
                                required
                            ></textarea>
                        {/snippet}
                    </Field>
                    <Field
                        id="notes"
                        label={$i18n.t('editor.segment.notes_label')}
                        error={errorFor('notes')}
                    >
                        {#snippet children(attrs)}
                            <textarea
                                {...attrs}
                                class="vff-textarea"
                                rows="4"
                                maxlength="2000"
                                bind:value={form.notes}
                                placeholder={$i18n.t('editor.segment.notes_placeholder')}
                            ></textarea>
                        {/snippet}
                    </Field>
                </div>

                <div class="actions">
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        {$i18n.t('editor.segment.submit')}
                    </button>
                    <p class="moderation">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        {$i18n.t('editor.form.moderation')}
                    </p>
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
        padding-block: var(--vff-section-space);
    }
    .segment-form {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        max-width: 760px;
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
        gap: var(--vff-space-4);
        min-width: 0;
        margin: 0;
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
    .intro {
        margin: 0;
        max-width: var(--vff-measure);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-4) var(--vff-space-5);
    }
    .lookup {
        margin: calc(-1 * var(--vff-space-2)) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .lookup:empty {
        display: none;
    }
    .lookup .bi {
        color: var(--vff-success);
    }
    .pk-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--vff-space-4) var(--vff-space-5);
    }
    .pk-block {
        min-width: 0;
    }
    .pk-block .vff-label {
        margin: 0 0 var(--vff-space-2);
    }
    .pk-inputs {
        display: flex;
        align-items: flex-end;
        gap: var(--vff-space-2);
    }
    .pk-part {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1 1 0;
        min-width: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .pk-part input {
        font-variant-numeric: tabular-nums;
    }
    .plus {
        padding-bottom: 10px;
        font-family: var(--vff-font-heading);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3) var(--vff-space-5);
    }
    .moderation {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .grid-2,
        .pk-row {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
