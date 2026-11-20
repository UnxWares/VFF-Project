<script>
    /**
     * « Proposer une série de matériel » : une série absente de VFF, ou la correction d'une série de
     * VFF (`?material=<slug>` : le formulaire reprend ses valeurs actuelles). L'identifiant Wikidata se
     * vérifie avant l'envoi (/contribute/series/wikidata) : un élément déjà rattaché à une série de VFF
     * renvoie vers la correction de sa fiche, plutôt qu'un doublon ; un élément fusionné est montré,
     * et l'auteur choisit s'il prend l'identifiant final. Une correction n'envoie que les champs
     * changés, et efface une valeur que l'auteur a vidée (`clear`) ; dès qu'elle touche un champ lu
     * par les règles des liens (catégorie, années, écartements, électrification), le formulaire montre
     * les liens de la série qu'elle rendrait contraires (/contribute/series/impact). Contrôles avant
     * l'envoi (proposals.js), puis MaterialContributionController::storeSeries.
     */
    import { Link, router, useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import EntityPicker from '$components/Contribute/EntityPicker.svelte';
    import ProposalForm from '$components/Contribute/ProposalForm.svelte';
    import SourceFields from '$components/Contribute/SourceFields.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        LIMITS,
        gaugeLabel,
        normalizeQid,
        seriesCorrection,
        seriesErrors,
    } from '$components/Contribute/proposals.js';

    let {
        material = null,
        types = [],
        systems = [],
        gauges = [],
        sourceKinds = [],
        licenses = [],
        minYear = 1800,
    } = $props();

    /** Traduction passée aux fonctions de proposals.js. */
    const tr = (key, opts) => $i18n.t(key, opts);

    /** Libellé de la source d'un lien (repli : la valeur brute). */
    function sourceLabel(/** @type {string|null} */ value) {
        const key = `proposals.line_material.sources.${value ?? 'none'}`;
        const text = String($i18n.t(key));
        return text === key ? (value ?? '') : text;
    }
    const yearText = (y) => (y === null || y === undefined ? '' : String(y));

    // Série à corriger chargée par la page (valeurs initiales voulues : la page est rechargée pour une autre).
    // svelte-ignore state_referenced_locally
    let mode = $state(material ? 'correction' : 'new');
    let picked = $state(null);
    let attempted = $state(false);
    /** @type {{ showErrors: () => Promise<void> }|undefined} */
    let proposal = $state();

    /** idle | checking | done | failed */
    let qidStatus = $state('idle');
    /** @type {{ requested: string, item: { id: string, label: string|null, description: string|null }|null, existing: { slug: string, name: string }|null }|null} */
    let qidResult = $state.raw(null);
    let qidError = $state(null);

    /** Effet de la correction sur les liens de la série : idle | checking | done | failed */
    let impactStatus = $state('idle');
    /** @type {{ line_slug: string, line_code: string|null, line_name: string, source: string|null, rule: string, reason: string }[]} */
    let impact = $state.raw([]);
    let impactRequest = 0;

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: material?.name ?? '',
        type: material?.type ?? '',
        builder: material?.builder ?? '',
        in_service_from: yearText(material?.in_service_from),
        in_service_to: yearText(material?.in_service_to),
        gauges: (material?.gauges ?? []).map(String),
        electrification: [...(material?.electrification ?? [])],
        wikidata_id: material?.wikidata_id ?? '',
        source_kind: 'web',
        source_title: '',
        source_url: '',
        source_license: '',
        notes: '',
    });

    const correcting = $derived(mode === 'correction' && material);
    /** Série de VFF qui porte déjà l'élément vérifié (sauf la série corrigée elle-même). */
    const duplicate = $derived(
        qidResult?.existing && qidResult.existing.slug !== material?.slug
            ? qidResult.existing
            : null,
    );

    /** Choix d'une autre série à corriger : la page se recharge avec ses valeurs actuelles. */
    function pickMaterial() {
        if (picked?.slug) router.get('/contribute/series', { material: picked.slug });
    }

    /** @param {string} value */
    const year = (value) => (value.trim() === '' ? null : Number(value.trim()));

    /** Valeurs de la série telles que saisies (vides : null ou liste vide). */
    function entered() {
        const qid = form.wikidata_id.trim();
        return {
            name: form.name.trim() || null,
            type: form.type || null,
            builder: form.builder.trim() || null,
            in_service_from: year(form.in_service_from),
            in_service_to: year(form.in_service_to),
            gauges: form.gauges.map(Number),
            electrification: [...form.electrification],
            wikidata_id: qid ? (normalizeQid(qid) ?? qid) : null,
        };
    }

    /** Proposition telle qu'elle part au serveur : une correction ne porte que ce qui change. */
    function values() {
        const series = entered();
        const correction =
            mode === 'correction' && material ? seriesCorrection(material, series) : null;
        return {
            mode,
            material_slug: mode === 'correction' ? (material?.slug ?? '') : null,
            ...(correction
                ? { ...correction.values, clear: correction.clear }
                : {
                      ...series,
                      gauges: series.gauges.length ? series.gauges : null,
                      electrification: series.electrification.length
                          ? series.electrification
                          : null,
                  }),
            source_kind: form.source_kind,
            source_title: form.source_title.trim(),
            source_url: form.source_url.trim() || null,
            source_license: form.source_kind === 'document' ? form.source_license || null : null,
            notes: form.notes.trim() || null,
        };
    }

    /** Valeurs des contrôles : toute la saisie, années gardées en texte. */
    function checked() {
        return {
            ...values(),
            ...entered(),
            in_service_from: form.in_service_from,
            in_service_to: form.in_service_to,
        };
    }

    const clientErrors = $derived(attempted ? seriesErrors(checked(), tr, { minYear }) : {});
    const errors = $derived({ ...form.errors, ...clientErrors });

    const FIELD_IDS = {
        mode: 'series-mode-new',
        material_slug: 'series-material',
        changes: 'series-name',
        name: 'series-name',
        type: 'series-type',
        electrification: 'series-system-0',
        'electrification.0': 'series-system-0',
        gauges: 'series-gauge-0',
        'gauges.0': 'series-gauge-0',
        clear: 'series-name',
        builder: 'series-builder',
        in_service_from: 'series-from',
        in_service_to: 'series-to',
        wikidata_id: 'series-wikidata',
        source_kind: 'series-source-kind-web',
        source_title: 'series-source-title',
        source_url: 'series-source-url',
        source_license: 'series-source-license',
        notes: 'series-notes',
    };

    /** @param {string} key */
    function errorFor(key) {
        const e = errors[key];
        return Array.isArray(e) ? e.join(' ') : (e ?? null);
    }

    /** Erreur d'un champ liste (`gauges`) ou de l'un de ses éléments (`gauges.0`). */
    function listError(/** @type {string} */ key) {
        const found = Object.keys(errors).find((k) => k === key || k.startsWith(`${key}.`));
        return found ? errorFor(found) : null;
    }

    /**
     * Liens de la série que la correction rendrait contraires aux règles, relus quand un champ de
     * ces règles change (catégorie, années, écartements, électrification).
     */
    async function refreshImpact() {
        if (mode !== 'correction' || !material?.links_count) return;
        const { values: changed, clear } = seriesCorrection(material, entered());
        const fields = ['type', 'in_service_from', 'in_service_to', 'gauges', 'electrification'];
        const touched = fields.filter((f) => f in changed || clear.includes(f));
        const request = ++impactRequest;
        if (!touched.length) {
            impact = [];
            impactStatus = 'idle';
            return;
        }
        /** @type {[string, string][]} */
        const pairs = [['material', material.slug]];
        for (const f of touched) {
            const value = changed[f];
            if (Array.isArray(value)) value.forEach((v) => pairs.push([`${f}[]`, String(v)]));
            else if (value !== undefined && value !== null) pairs.push([f, String(value)]);
        }
        clear.forEach((f) => pairs.push(['clear[]', f]));
        const query = pairs.map((pair) => pair.map(encodeURIComponent).join('=')).join('&');
        impactStatus = 'checking';
        try {
            const res = await fetch(`/contribute/series/impact?${query}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (request !== impactRequest) return;
            if (res.status === 422) {
                impact = [];
                impactStatus = 'idle';
                return;
            }
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            impact = (await res.json()).links ?? [];
            impactStatus = 'done';
        } catch {
            if (request === impactRequest) impactStatus = 'failed';
        }
    }

    /** L'auteur choisit l'identifiant final d'un élément fusionné. */
    function useFinalQid() {
        if (!qidResult?.item) return;
        form.wikidata_id = qidResult.item.id;
        checkQid();
    }

    async function checkQid() {
        const qid = normalizeQid(form.wikidata_id);
        qidResult = null;
        qidError = null;
        if (!qid) {
            qidError = $i18n.t('proposals.series.errors.qid_format');
            return;
        }
        qidStatus = 'checking';
        try {
            const res = await fetch(`/contribute/series/wikidata?qid=${encodeURIComponent(qid)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (res.status === 422) {
                qidError = Object.values(data.errors ?? {}).flat()[0] ?? null;
                qidStatus = 'done';
                return;
            }
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            qidResult = data;
            if (!data.item) qidError = $i18n.t('proposals.series.errors.qid_missing', { qid });
            qidStatus = 'done';
        } catch {
            qidStatus = 'failed';
            qidError = $i18n.t('proposals.series.errors.qid_unreachable');
        }
    }

    /** @param {SubmitEvent} e */
    function submit(e) {
        e.preventDefault();
        attempted = true;
        if (Object.keys(seriesErrors(checked(), tr, { minYear })).length) {
            proposal?.showErrors();
            return;
        }
        form.transform(() => values()).post('/contribute/series', {
            onError: () => proposal?.showErrors(),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('proposals.series.meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t(
                correcting ? 'proposals.series.title_correction' : 'proposals.series.title_new',
            )}
            subtitle={$i18n.t('proposals.series.subtitle')}
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
                    <legend>{$i18n.t('proposals.series.mode_legend')}</legend>
                    <fieldset class="choices">
                        <legend class="vff-sr-only"
                            >{$i18n.t('proposals.series.mode_legend')}</legend
                        >
                        <div class="choice-list">
                            {#each ['new', 'correction'] as m (m)}
                                <label class="vff-check">
                                    <input
                                        id="series-mode-{m}"
                                        type="radio"
                                        name="series-mode"
                                        value={m}
                                        bind:group={mode}
                                    />
                                    {$i18n.t(`proposals.series.modes.${m}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                    {#if mode === 'correction'}
                        {#if material}
                            <p class="intro">
                                {$i18n.t('proposals.series.correcting', { name: material.name })}
                            </p>
                        {:else}
                            <EntityPicker
                                kind="material"
                                id="series-material"
                                bind:selected={picked}
                                onchange={pickMaterial}
                                error={errorFor('material_slug')}
                            />
                        {/if}
                    {/if}
                </fieldset>

                {#if mode === 'new' || material}
                    <fieldset class="vff-panel group">
                        <legend>{$i18n.t('proposals.series.identity_legend')}</legend>
                        <div class="grid-2">
                            <Field
                                id="series-name"
                                label={$i18n.t('proposals.series.name')}
                                hint={$i18n.t('proposals.series.name_hint')}
                                error={errorFor('name') ?? errorFor('changes')}
                                required={mode === 'new'}
                            >
                                {#snippet children(attrs)}
                                    <input
                                        {...attrs}
                                        class="vff-input"
                                        type="text"
                                        maxlength={LIMITS.seriesName[1]}
                                        required={mode === 'new'}
                                        bind:value={form.name}
                                    />
                                {/snippet}
                            </Field>
                            <Field
                                id="series-type"
                                label={$i18n.t('proposals.series.type')}
                                error={errorFor('type')}
                                required={mode === 'new'}
                            >
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        class="vff-select"
                                        required={mode === 'new'}
                                        bind:value={form.type}
                                        onchange={refreshImpact}
                                    >
                                        <option value=""
                                            >{$i18n.t('proposals.series.choose_type')}</option
                                        >
                                        {#each types as type (type)}
                                            <option value={type}
                                                >{$i18n.t(`materials.type.${type}`)}</option
                                            >
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                        </div>
                        <Field
                            id="series-builder"
                            label={$i18n.t('proposals.series.builder')}
                            hint={$i18n.t('proposals.series.builder_hint')}
                            error={errorFor('builder')}
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="text"
                                    maxlength={LIMITS.builder[1]}
                                    bind:value={form.builder}
                                />
                            {/snippet}
                        </Field>
                        <fieldset class="choices years" aria-describedby="series-years-hint">
                            <legend class="vff-label"
                                >{$i18n.t('proposals.series.years_legend')}</legend
                            >
                            <p class="vff-hint" id="series-years-hint">
                                {$i18n.t('proposals.series.years_hint')}
                            </p>
                            <div class="grid-2">
                                <Field
                                    id="series-from"
                                    label={$i18n.t('proposals.series.in_service_from')}
                                    error={errorFor('in_service_from')}
                                >
                                    {#snippet children(attrs)}
                                        <input
                                            {...attrs}
                                            class="vff-input year"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="4"
                                            bind:value={form.in_service_from}
                                            onchange={refreshImpact}
                                        />
                                    {/snippet}
                                </Field>
                                <Field
                                    id="series-to"
                                    label={$i18n.t('proposals.series.in_service_to')}
                                    error={errorFor('in_service_to')}
                                >
                                    {#snippet children(attrs)}
                                        <input
                                            {...attrs}
                                            class="vff-input year"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="4"
                                            bind:value={form.in_service_to}
                                            onchange={refreshImpact}
                                        />
                                    {/snippet}
                                </Field>
                            </div>
                        </fieldset>
                        <div class="qid">
                            <Field
                                id="series-wikidata"
                                label={$i18n.t('proposals.series.wikidata')}
                                hint={$i18n.t('proposals.series.wikidata_hint')}
                                error={errorFor('wikidata_id') ?? qidError}
                            >
                                {#snippet children(attrs)}
                                    <input
                                        {...attrs}
                                        class="vff-input"
                                        type="text"
                                        spellcheck="false"
                                        autocomplete="off"
                                        maxlength="120"
                                        bind:value={form.wikidata_id}
                                    />
                                {/snippet}
                            </Field>
                            <button
                                type="button"
                                class="vff-btn secondary"
                                disabled={qidStatus === 'checking' || !form.wikidata_id.trim()}
                                onclick={checkQid}
                            >
                                {$i18n.t('proposals.series.wikidata_check')}
                            </button>
                        </div>
                        <div class="qid-result" role="status">
                            {#if qidStatus === 'checking'}
                                <p class="intro">{$i18n.t('proposals.series.wikidata_checking')}</p>
                            {:else if duplicate}
                                <Notice tone="warning" live="off">
                                    <p>
                                        {$i18n.t('proposals.series.wikidata_existing', {
                                            name: duplicate.name,
                                        })}
                                        <Link
                                            href="/contribute/series?material={duplicate.slug}"
                                            class="vff-link"
                                            >{$i18n.t('proposals.series.correct_existing', {
                                                name: duplicate.name,
                                            })}</Link
                                        >
                                    </p>
                                </Notice>
                            {:else if qidResult?.item && qidResult.item.id !== qidResult.requested}
                                <Notice tone="info" live="off">
                                    <p>
                                        {$i18n.t('proposals.series.wikidata_merged', {
                                            requested: qidResult.requested,
                                            final: qidResult.item.id,
                                        })}
                                        {#if qidResult.item.label}({qidResult.item.label}){/if}
                                    </p>
                                    <button
                                        type="button"
                                        class="vff-btn secondary"
                                        onclick={useFinalQid}
                                    >
                                        {$i18n.t('proposals.series.wikidata_use', {
                                            final: qidResult.item.id,
                                        })}
                                    </button>
                                </Notice>
                            {:else if qidResult?.item}
                                <Notice tone="success" live="off">
                                    <p>
                                        <a
                                            class="vff-link"
                                            href="https://www.wikidata.org/wiki/{qidResult.item.id}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            >{$i18n.t('proposals.series.wikidata_found', {
                                                label: qidResult.item.label ?? qidResult.item.id,
                                            })}<span class="vff-sr-only">
                                                ({$i18n.t('common.new_tab')})</span
                                            ><i
                                                class="bi bi-box-arrow-up-right vff-ext"
                                                aria-hidden="true"
                                            ></i></a
                                        >{#if qidResult.item.description}, {qidResult.item
                                                .description}{/if}
                                    </p>
                                </Notice>
                            {/if}
                        </div>
                    </fieldset>

                    <fieldset class="vff-panel group">
                        <legend>{$i18n.t('proposals.series.traction_legend')}</legend>
                        <fieldset class="choices" aria-describedby="series-systems-hint">
                            <legend class="vff-label"
                                >{$i18n.t('proposals.series.systems_legend')}</legend
                            >
                            <p class="vff-hint" id="series-systems-hint">
                                {$i18n.t('proposals.series.systems_hint')}
                            </p>
                            <div class="choice-list systems">
                                {#each systems as system, i (system)}
                                    <label class="vff-check">
                                        <input
                                            id="series-system-{i}"
                                            type="checkbox"
                                            value={system}
                                            bind:group={form.electrification}
                                            onchange={refreshImpact}
                                        />
                                        {$i18n.t(`proposals.series.systems.${system}`)}
                                    </label>
                                {/each}
                            </div>
                            <p class="vff-error" aria-live="polite">
                                {#if listError('electrification')}<i
                                        class="bi bi-exclamation-circle"
                                        aria-hidden="true"
                                    ></i><span>{listError('electrification')}</span>{/if}
                            </p>
                        </fieldset>
                        <fieldset class="choices" aria-describedby="series-gauges-hint">
                            <legend class="vff-label"
                                >{$i18n.t('proposals.series.gauges_legend')}</legend
                            >
                            <p class="vff-hint" id="series-gauges-hint">
                                {$i18n.t('proposals.series.gauges_hint')}
                            </p>
                            <div class="choice-list systems">
                                {#each gauges as g, i (g)}
                                    <label class="vff-check">
                                        <input
                                            id="series-gauge-{i}"
                                            type="checkbox"
                                            value={String(g)}
                                            bind:group={form.gauges}
                                            onchange={refreshImpact}
                                        />
                                        {gaugeLabel(g, tr)}
                                    </label>
                                {/each}
                            </div>
                            <p class="vff-error" aria-live="polite">
                                {#if listError('gauges') ?? errorFor('gauge_mm')}<i
                                        class="bi bi-exclamation-circle"
                                        aria-hidden="true"
                                    ></i><span>{listError('gauges') ?? errorFor('gauge_mm')}</span
                                    >{/if}
                            </p>
                        </fieldset>
                        <div class="impact" role="status">
                            {#if impactStatus === 'checking'}
                                <p class="intro">{$i18n.t('proposals.series.impact_checking')}</p>
                            {:else if impactStatus === 'done' && impact.length}
                                <Notice
                                    tone="warning"
                                    live="off"
                                    title={$i18n.t('proposals.series.impact_title')}
                                >
                                    <p>
                                        {$i18n.t('proposals.series.impact_text', {
                                            count: impact.length,
                                        })}
                                    </p>
                                    <ul class="impact-list">
                                        {#each impact as l, i (i)}
                                            <li>
                                                {$i18n.t('proposals.series.impact_link', {
                                                    line: [l.line_code, l.line_name]
                                                        .filter(Boolean)
                                                        .join(' '),
                                                    source: sourceLabel(l.source),
                                                    reason: l.reason,
                                                })}
                                            </li>
                                        {/each}
                                    </ul>
                                </Notice>
                            {/if}
                        </div>
                    </fieldset>

                    <SourceFields
                        id="series"
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
                    />

                    <div class="vff-panel group">
                        <Field
                            id="series-notes"
                            label={$i18n.t('proposals.series.notes')}
                            hint={$i18n.t('proposals.series.notes_hint')}
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
                        <p class="intro">{$i18n.t('proposals.series.after')}</p>
                    </div>
                {/if}

                {#snippet actions()}
                    <button
                        type="submit"
                        class="vff-btn primary"
                        disabled={form.processing || (mode === 'correction' && !material)}
                    >
                        <i class="bi bi-send" aria-hidden="true"></i>
                        {$i18n.t('proposals.series.submit')}
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
    .years .vff-hint {
        margin: 0 0 var(--vff-space-3);
    }
    .year {
        max-width: 8rem;
        font-variant-numeric: tabular-nums;
    }
    .systems {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
    }
    .qid {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-3);
    }
    .qid > :global(:first-child) {
        align-self: stretch;
        min-width: 0;
    }
    /* Zones d'annonce présentes même vides (RGAA 7.5) : vides, pas d'espace en plus (écart de .group). */
    .qid-result:empty,
    .impact:empty {
        margin-top: calc(-1 * var(--vff-space-4));
    }
    .qid-result p {
        margin: 0 0 var(--vff-space-2);
    }
    .qid-result p:last-child {
        margin-bottom: 0;
    }
    .impact p {
        margin: 0 0 var(--vff-space-2);
    }
    .impact-list {
        margin: 0;
        padding-left: var(--vff-space-5);
        overflow-wrap: anywhere;
    }
</style>
