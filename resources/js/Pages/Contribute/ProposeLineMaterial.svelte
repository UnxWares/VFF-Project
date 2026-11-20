<script>
    /**
     * « Proposer un matériel sur une ligne » : une série de VFF a circulé sur une ligne de VFF, d'après
     * une source citée, avec sa période de service quand la source la date. Dès que la ligne et la
     * série sont choisies, le contrôle de compatibilité (/contribute/material/check) signale un conflit
     * avec la règle d'écartement ou d'électrification : la phrase de la source devient obligatoire.
     * Contrôles avant l'envoi (proposals.js), puis MaterialContributionController::storeLink.
     */
    import { onMount } from 'svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import EntityPicker from '$components/Contribute/EntityPicker.svelte';
    import PartialDateFields from '$components/Contribute/PartialDateFields.svelte';
    import ProposalForm from '$components/Contribute/ProposalForm.svelte';
    import SourceFields from '$components/Contribute/SourceFields.svelte';
    import { i18n } from '$lib/i18n.js';
    import { LIMITS, composeDate, lineMaterialErrors } from '$components/Contribute/proposals.js';

    let {
        preselectedLine = null,
        preselectedMaterial = null,
        circulations = [],
        precisions = ['day', 'month', 'year'],
        sourceKinds = [],
        licenses = [],
    } = $props();

    /** Traduction passée aux fonctions de proposals.js. */
    const tr = (key, opts) => $i18n.t(key, opts);

    // Fiches présélectionnées depuis la fiche d'une ligne ou d'une série (valeurs initiales voulues).
    // svelte-ignore state_referenced_locally
    let line = $state(preselectedLine);
    // svelte-ignore state_referenced_locally
    let material = $state(preselectedMaterial);
    /** Bornes de la période : précision vide = date inconnue (pas de borne). */
    let start = $state({ precision: '', year: '', month: '', day: '' });
    let end = $state({ precision: '', year: '', month: '', day: '' });
    let attempted = $state(false);
    /** @type {{ showErrors: () => Promise<void> }|undefined} */
    let proposal = $state();

    /** idle | checking | done | failed */
    let checkStatus = $state('idle');
    /** @type {{ conflict: { rule: string, message: string }|null, existing: { source: string|null, confidence: string|null }|null, excluded: string|null }|null} */
    let check = $state.raw(null);

    const form = useForm({
        circulation: 'regular',
        section_from: '',
        section_to: '',
        source_kind: 'web',
        source_title: '',
        source_url: '',
        source_license: '',
        quote: '',
        notes: '',
    });

    const quoteRequired = $derived(Boolean(check?.conflict));

    /** @type {AbortController|undefined} */
    let checkController;

    /**
     * Contrôle du couple ligne × série, dès que les deux sont choisis et à chaque changement (rappelé
     * par les sélecteurs) ; une vérification en cours pour l'ancien couple est abandonnée.
     */
    async function runCheck() {
        checkController?.abort();
        check = null;
        if (!line?.slug || !material?.slug) {
            checkStatus = 'idle';
            return;
        }
        const controller = new AbortController();
        checkController = controller;
        checkStatus = 'checking';
        const params = new URLSearchParams({ line: line.slug, material: material.slug });
        try {
            const res = await fetch(`/contribute/material/check?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            check = await res.json();
            checkStatus = 'done';
        } catch (e) {
            if (!(e instanceof DOMException && e.name === 'AbortError')) checkStatus = 'failed';
        }
    }

    // Ligne et série présélectionnées depuis une fiche : contrôle dès l'ouverture.
    onMount(() => {
        runCheck();
        return () => checkController?.abort();
    });

    /** @param {{ precision: string, year: string, month: string, day: string }} bound */
    const dateOf = (bound) =>
        bound.precision ? composeDate(bound.precision, bound.year, bound.month, bound.day) : null;

    /** Proposition telle qu'elle part au serveur. */
    function values() {
        return {
            line_slug: line?.slug ?? '',
            material_slug: material?.slug ?? '',
            circulation: form.circulation,
            start_precision: start.precision || null,
            start_date: dateOf(start),
            end_precision: end.precision || null,
            end_date: dateOf(end),
            section_from: form.section_from.trim() || null,
            section_to: form.section_to.trim() || null,
            source_kind: form.source_kind,
            source_title: form.source_title.trim(),
            source_url: form.source_url.trim() || null,
            source_license: form.source_kind === 'document' ? form.source_license || null : null,
            quote: form.quote.trim() || null,
            notes: form.notes.trim() || null,
        };
    }

    const clientErrors = $derived(
        attempted ? lineMaterialErrors(values(), tr, { quoteRequired }) : {},
    );
    // Erreurs affichées : celles du navigateur d'abord, sinon celles renvoyées par le serveur.
    const errors = $derived({ ...form.errors, ...clientErrors });

    const FIELD_IDS = {
        line_slug: 'lm-line',
        material_slug: 'lm-material',
        circulation: 'lm-circulation-regular',
        start_precision: 'lm-start-year',
        start_date: 'lm-start-year',
        end_precision: 'lm-end-year',
        end_date: 'lm-end-year',
        section_from: 'lm-section-from',
        section_to: 'lm-section-to',
        source_kind: 'lm-source-kind-web',
        source_title: 'lm-source-title',
        source_url: 'lm-source-url',
        source_license: 'lm-source-license',
        quote: 'lm-quote',
        notes: 'lm-notes',
    };

    /** @param {string} key */
    function errorFor(key) {
        const e = errors[key];
        return Array.isArray(e) ? e.join(' ') : (e ?? null);
    }

    /** Libellé d'une provenance ou d'une fiabilité de lien (repli : la valeur brute). */
    function label(group, value) {
        const key = `proposals.line_material.${group}.${value ?? 'none'}`;
        const text = $i18n.t(key);
        return text === key ? (value ?? '') : text;
    }

    /** @param {SubmitEvent} e */
    function submit(e) {
        e.preventDefault();
        attempted = true;
        if (Object.keys(lineMaterialErrors(values(), tr, { quoteRequired })).length) {
            proposal?.showErrors();
            return;
        }
        form.transform(() => values()).post('/contribute/material', {
            onError: () => proposal?.showErrors(),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('proposals.line_material.meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('proposals.line_material.title')}
            subtitle={$i18n.t('proposals.line_material.subtitle')}
        >
            <p class="back">
                <Link href="/contribute/mine" class="vff-link"
                    ><i class="bi bi-arrow-left" aria-hidden="true"></i>
                    {$i18n.t('editor.thanks.mine')}</Link
                >
            </p>
        </PageHero>

        <div class="vff-container body">
            <ProposalForm bind:this={proposal} {errors} fieldIds={FIELD_IDS} onsubmit={submit}>
                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('proposals.line_material.pair_legend')}</legend>
                    <p class="intro">
                        {$i18n.t('proposals.line_material.pair_hint')}
                        <Link href="/contribute/series" class="vff-link"
                            >{$i18n.t('proposals.line_material.new_series_link')}</Link
                        >
                    </p>
                    <EntityPicker
                        kind="line"
                        id="lm-line"
                        bind:selected={line}
                        onchange={runCheck}
                        error={errorFor('line_slug')}
                    />
                    <EntityPicker
                        kind="material"
                        id="lm-material"
                        bind:selected={material}
                        onchange={runCheck}
                        error={errorFor('material_slug')}
                    />
                    <!-- Zone d'annonce présente même vide : le premier message est lu (RGAA 7.5). -->
                    <div class="check" role="status">
                        {#if checkStatus === 'checking'}
                            <p class="intro">{$i18n.t('proposals.line_material.checking')}</p>
                        {:else if checkStatus === 'failed'}
                            <Notice tone="info" live="off"
                                >{$i18n.t('proposals.line_material.check_failed')}</Notice
                            >
                        {:else if check}
                            {#if check.conflict}
                                <Notice
                                    tone="warning"
                                    live="off"
                                    title={$i18n.t('proposals.line_material.conflict_title')}
                                >
                                    <p>{check.conflict.message}</p>
                                </Notice>
                            {/if}
                            {#if check.excluded}
                                <Notice
                                    tone="danger"
                                    live="off"
                                    title={$i18n.t('proposals.line_material.excluded_title')}
                                >
                                    <p>
                                        {$i18n.t('proposals.line_material.excluded_text', {
                                            reason: check.excluded,
                                        })}
                                    </p>
                                </Notice>
                            {/if}
                            {#if check.existing}
                                <Notice
                                    tone="info"
                                    live="off"
                                    title={$i18n.t('proposals.line_material.existing_title')}
                                >
                                    <p>
                                        {$i18n.t('proposals.line_material.existing_text', {
                                            source: label('sources', check.existing.source),
                                            confidence: label(
                                                'confidences',
                                                check.existing.confidence,
                                            ),
                                        })}
                                    </p>
                                </Notice>
                            {/if}
                        {/if}
                    </div>
                </fieldset>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('proposals.line_material.circulation_legend')}</legend>
                    <fieldset class="choices" aria-describedby="lm-circulation-hint">
                        <legend class="vff-label"
                            >{$i18n.t('proposals.line_material.circulation_label')}</legend
                        >
                        <p class="vff-hint circulation-hint" id="lm-circulation-hint">
                            {$i18n.t('proposals.line_material.circulation_hint')}
                        </p>
                        <div class="choice-list">
                            {#each circulations as c (c)}
                                <label class="vff-check">
                                    <input
                                        id="lm-circulation-{c}"
                                        type="radio"
                                        name="lm-circulation"
                                        value={c}
                                        bind:group={form.circulation}
                                    />
                                    {$i18n.t(`proposals.line_material.circulations.${c}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                </fieldset>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('proposals.line_material.period_legend')}</legend>
                    <p class="intro">{$i18n.t('proposals.line_material.period_hint')}</p>
                    <PartialDateFields
                        id="lm-start"
                        legend={$i18n.t('proposals.line_material.start_legend')}
                        {precisions}
                        allowUnknown
                        unknownLabel={$i18n.t('proposals.line_material.date_unknown')}
                        bind:precision={start.precision}
                        bind:year={start.year}
                        bind:month={start.month}
                        bind:day={start.day}
                        error={errorFor('start_date') ?? errorFor('start_precision')}
                    />
                    <PartialDateFields
                        id="lm-end"
                        legend={$i18n.t('proposals.line_material.end_legend')}
                        {precisions}
                        allowUnknown
                        unknownLabel={$i18n.t('proposals.line_material.date_unknown')}
                        bind:precision={end.precision}
                        bind:year={end.year}
                        bind:month={end.month}
                        bind:day={end.day}
                        error={errorFor('end_date') ?? errorFor('end_precision')}
                    />
                    <p class="intro">{$i18n.t('proposals.line_material.section_hint')}</p>
                    <div class="grid-2">
                        <Field
                            id="lm-section-from"
                            label={$i18n.t('proposals.line_material.section_from')}
                            error={errorFor('section_from')}
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="text"
                                    maxlength="250"
                                    bind:value={form.section_from}
                                />
                            {/snippet}
                        </Field>
                        <Field
                            id="lm-section-to"
                            label={$i18n.t('proposals.line_material.section_to')}
                            error={errorFor('section_to')}
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="text"
                                    maxlength="250"
                                    bind:value={form.section_to}
                                />
                            {/snippet}
                        </Field>
                    </div>
                </fieldset>

                <SourceFields
                    id="lm"
                    kinds={sourceKinds}
                    {licenses}
                    licenseHint={$i18n.t('proposals.line_material.source_license_hint')}
                    bind:kind={form.source_kind}
                    bind:title={form.source_title}
                    bind:url={form.source_url}
                    bind:license={form.source_license}
                    errors={{
                        source_title: errorFor('source_title'),
                        source_url: errorFor('source_url'),
                        source_license: errorFor('source_license'),
                    }}
                >
                    {#snippet extra()}
                        <Field
                            id="lm-quote"
                            label={$i18n.t('proposals.line_material.quote')}
                            hint={$i18n.t(
                                quoteRequired
                                    ? 'proposals.line_material.quote_hint_required'
                                    : 'proposals.line_material.quote_hint',
                            )}
                            error={errorFor('quote')}
                            required={quoteRequired}
                        >
                            {#snippet children(attrs)}
                                <textarea
                                    {...attrs}
                                    class="vff-textarea"
                                    rows="3"
                                    maxlength={LIMITS.quote[1]}
                                    required={quoteRequired}
                                    bind:value={form.quote}
                                ></textarea>
                            {/snippet}
                        </Field>
                    {/snippet}
                </SourceFields>

                <div class="vff-panel group">
                    <Field
                        id="lm-notes"
                        label={$i18n.t('proposals.line_material.notes')}
                        hint={$i18n.t('proposals.line_material.notes_hint')}
                        error={errorFor('notes')}
                    >
                        {#snippet children(attrs)}
                            <textarea
                                {...attrs}
                                class="vff-textarea"
                                rows="4"
                                maxlength={LIMITS.notes}
                                bind:value={form.notes}
                            ></textarea>
                        {/snippet}
                    </Field>
                    <p class="intro">{$i18n.t('proposals.line_material.after')}</p>
                </div>

                {#snippet actions()}
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        {$i18n.t('proposals.line_material.submit')}
                    </button>
                {/snippet}
            </ProposalForm>
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
    .check {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
    }
    /* Vide : pas d'espace en plus au bas du panneau (écart de .group). */
    .check:empty {
        margin-top: calc(-1 * var(--vff-space-4));
    }
    .check p {
        margin: 0;
    }
    .circulation-hint {
        margin: 0 0 var(--vff-space-3);
    }
</style>
