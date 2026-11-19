<script>
    /**
     * « Proposer un événement daté » : un événement d'une ligne existante (ouverture, fermeture,
     * électrification…), sa date à la précision de la source, la portion concernée et la source.
     * Contrôles avant l'envoi (proposals.js), puis EventContributionController::store.
     */
    import { Link, useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Field from '$components/ui/Field.svelte';
    import EntityPicker from '$components/Contribute/EntityPicker.svelte';
    import PartialDateFields from '$components/Contribute/PartialDateFields.svelte';
    import ProposalForm from '$components/Contribute/ProposalForm.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        LIMITS,
        composeDate,
        eventErrors,
        eventTypeLabel,
        pkMeters,
    } from '$components/Contribute/proposals.js';

    let {
        preselectedLine = null,
        eventTypes = [],
        precisions = ['day', 'month', 'year'],
        sourceKinds = [],
        licenses = [],
        reuses = [],
        heritageKinds = [],
    } = $props();

    /** Traduction passée aux fonctions de proposals.js. */
    const tr = (key, opts) => $i18n.t(key, opts);

    // Ligne présélectionnée depuis sa fiche (valeur initiale voulue : la page est rechargée pour une autre).
    // svelte-ignore state_referenced_locally
    let line = $state(preselectedLine);
    let precision = $state('day');
    let year = $state('');
    let month = $state('');
    let day = $state('');
    /** Points kilométriques saisis en kilomètres + mètres. */
    const PK_BOUNDS = ['start', 'end'];
    let pk = $state({ start: { km: '', m: '' }, end: { km: '', m: '' } });
    let attempted = $state(false);
    /** @type {{ showErrors: () => Promise<void> }|undefined} */
    let proposal = $state();

    const form = useForm({
        type: 'opening',
        portion: 'whole',
        from: '',
        to: '',
        reuse: '',
        heritage_kind: '',
        description: '',
        source_kind: 'web',
        source_title: '',
        source_url: '',
        source_license: '',
        notes: '',
    });

    const isHeritage = $derived(form.type === 'heritage_start' || form.type === 'heritage_end');

    /** Proposition telle qu'elle part au serveur. */
    function values() {
        return {
            line_slug: line?.slug ?? '',
            type: form.type,
            precision,
            date: composeDate(precision, year, month, day),
            portion: form.portion,
            from: form.portion === 'places' ? form.from.trim() : null,
            to: form.portion === 'places' ? form.to.trim() : null,
            pk_start_m: form.portion === 'pk' ? pkMeters(pk.start.km, pk.start.m) : null,
            pk_end_m: form.portion === 'pk' ? pkMeters(pk.end.km, pk.end.m) : null,
            reuse: form.type === 'conversion' ? form.reuse || null : null,
            heritage_kind: isHeritage ? form.heritage_kind || null : null,
            description: form.description.trim() || null,
            source_kind: form.source_kind,
            source_title: form.source_title.trim(),
            source_url: form.source_url.trim() || null,
            source_license: form.source_kind === 'document' ? form.source_license || null : null,
            notes: form.notes.trim() || null,
        };
    }

    const clientErrors = $derived(attempted ? eventErrors(values(), tr) : {});
    // Erreurs affichées : celles du navigateur d'abord, sinon celles renvoyées par le serveur.
    const errors = $derived({ ...form.errors, ...clientErrors });

    const pkInvalid = $derived(errorFor('pk_end_m') || errorFor('pk_start_m') ? 'true' : undefined);

    const FIELD_IDS = {
        line_slug: 'event-line',
        type: 'event-type',
        reuse: 'event-reuse',
        heritage_kind: 'event-heritage',
        description: 'event-description',
        precision: 'event-date-year',
        date: 'event-date-year',
        portion: 'event-portion-whole',
        from: 'event-from',
        to: 'event-to',
        pk_start_m: 'event-pk-start-km',
        pk_end_m: 'event-pk-end-km',
        source_kind: 'event-source-kind-web',
        source_title: 'event-source-title',
        source_url: 'event-source-url',
        source_license: 'event-source-license',
        notes: 'event-notes',
    };

    /** @param {string} key */
    function errorFor(key) {
        const e = errors[key];
        return Array.isArray(e) ? e.join(' ') : (e ?? null);
    }

    /** @param {SubmitEvent} e */
    function submit(e) {
        e.preventDefault();
        attempted = true;
        if (Object.keys(eventErrors(values(), tr)).length) {
            proposal?.showErrors();
            return;
        }
        form.transform(() => values()).post('/contribute/event', {
            onError: () => proposal?.showErrors(),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('proposals.event.meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('proposals.event.title')}
            subtitle={$i18n.t('proposals.event.subtitle')}
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
                    <legend>{$i18n.t('proposals.event.line_legend')}</legend>
                    <EntityPicker
                        kind="line"
                        id="event-line"
                        bind:selected={line}
                        error={errorFor('line_slug')}
                    />
                </fieldset>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('proposals.event.what_legend')}</legend>
                    <div class="grid-2">
                        <Field
                            id="event-type"
                            label={$i18n.t('proposals.event.type')}
                            error={errorFor('type')}
                            required
                        >
                            {#snippet children(attrs)}
                                <select
                                    {...attrs}
                                    class="vff-select"
                                    required
                                    bind:value={form.type}
                                >
                                    {#each eventTypes as type (type)}
                                        <option value={type}>{eventTypeLabel(type, tr)}</option>
                                    {/each}
                                </select>
                            {/snippet}
                        </Field>
                        {#if form.type === 'conversion'}
                            <Field
                                id="event-reuse"
                                label={$i18n.t('proposals.event.reuse')}
                                error={errorFor('reuse')}
                            >
                                {#snippet children(attrs)}
                                    <select {...attrs} class="vff-select" bind:value={form.reuse}>
                                        <option value=""
                                            >{$i18n.t('proposals.event.not_specified')}</option
                                        >
                                        {#each reuses as r (r)}
                                            <option value={r}
                                                >{$i18n.t(`proposals.reuses.${r}`)}</option
                                            >
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                        {:else if isHeritage}
                            <Field
                                id="event-heritage"
                                label={$i18n.t('proposals.event.heritage_kind')}
                                error={errorFor('heritage_kind')}
                            >
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        class="vff-select"
                                        bind:value={form.heritage_kind}
                                    >
                                        <option value=""
                                            >{$i18n.t('proposals.event.not_specified')}</option
                                        >
                                        {#each heritageKinds as k (k)}
                                            <option value={k}
                                                >{$i18n.t(`proposals.heritage_kinds.${k}`)}</option
                                            >
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                        {/if}
                    </div>

                    <PartialDateFields
                        id="event-date"
                        legend={$i18n.t('proposals.event.date_legend')}
                        hint={$i18n.t('proposals.event.precision_hint')}
                        {precisions}
                        bind:precision
                        bind:year
                        bind:month
                        bind:day
                        error={errorFor('date') ?? errorFor('precision')}
                    />

                    <Field
                        id="event-description"
                        label={$i18n.t('proposals.event.description')}
                        hint={$i18n.t('proposals.event.description_hint')}
                        error={errorFor('description')}
                        required={form.type === 'other'}
                    >
                        {#snippet children(attrs)}
                            <input
                                {...attrs}
                                class="vff-input"
                                type="text"
                                maxlength={LIMITS.description}
                                required={form.type === 'other'}
                                bind:value={form.description}
                            />
                        {/snippet}
                    </Field>
                </fieldset>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('proposals.event.portion_legend')}</legend>
                    <p class="intro" id="event-portion-hint">
                        {$i18n.t('proposals.event.portion_hint')}
                    </p>
                    <fieldset class="choices" aria-describedby="event-portion-hint">
                        <legend class="vff-sr-only"
                            >{$i18n.t('proposals.event.portion_legend')}</legend
                        >
                        <div class="choice-list">
                            {#each ['whole', 'places', 'pk'] as p (p)}
                                <label class="vff-check">
                                    <input
                                        id="event-portion-{p}"
                                        type="radio"
                                        name="event-portion"
                                        value={p}
                                        bind:group={form.portion}
                                    />
                                    {$i18n.t(`proposals.event.portions.${p}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>

                    {#if form.portion === 'places'}
                        <p class="intro">{$i18n.t('proposals.event.places_hint')}</p>
                        <div class="grid-2">
                            <Field
                                id="event-from"
                                label={$i18n.t('proposals.event.from')}
                                error={errorFor('from')}
                                required
                            >
                                {#snippet children(attrs)}
                                    <input
                                        {...attrs}
                                        class="vff-input"
                                        type="text"
                                        maxlength="250"
                                        required
                                        bind:value={form.from}
                                    />
                                {/snippet}
                            </Field>
                            <Field
                                id="event-to"
                                label={$i18n.t('proposals.event.to')}
                                error={errorFor('to')}
                                required
                            >
                                {#snippet children(attrs)}
                                    <input
                                        {...attrs}
                                        class="vff-input"
                                        type="text"
                                        maxlength="250"
                                        required
                                        bind:value={form.to}
                                    />
                                {/snippet}
                            </Field>
                        </div>
                    {:else if form.portion === 'pk'}
                        <div class="grid-2">
                            {#each PK_BOUNDS as bound (bound)}
                                <div
                                    class="pk"
                                    role="group"
                                    aria-labelledby="event-pk-{bound}-label"
                                >
                                    <p class="vff-label" id="event-pk-{bound}-label">
                                        {$i18n.t(`proposals.event.pk_${bound}`)}
                                    </p>
                                    <div class="pk-inputs">
                                        <label class="pk-part">
                                            <span>{$i18n.t('proposals.event.pk_km')}</span>
                                            <input
                                                id="event-pk-{bound}-km"
                                                class="vff-input"
                                                type="text"
                                                inputmode="numeric"
                                                aria-invalid={pkInvalid}
                                                aria-describedby="event-pk-error"
                                                bind:value={pk[bound].km}
                                            />
                                        </label>
                                        <span class="plus" aria-hidden="true">+</span>
                                        <label class="pk-part">
                                            <span>{$i18n.t('proposals.event.pk_m')}</span>
                                            <input
                                                class="vff-input"
                                                type="text"
                                                inputmode="numeric"
                                                aria-invalid={pkInvalid}
                                                aria-describedby="event-pk-error"
                                                bind:value={pk[bound].m}
                                            />
                                        </label>
                                    </div>
                                </div>
                            {/each}
                        </div>
                        <p class="vff-error" id="event-pk-error" aria-live="polite">
                            {#if errorFor('pk_end_m') || errorFor('pk_start_m')}<i
                                    class="bi bi-exclamation-circle"
                                    aria-hidden="true"
                                ></i><span>{errorFor('pk_end_m') ?? errorFor('pk_start_m')}</span
                                >{/if}
                        </p>
                    {/if}
                </fieldset>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('proposals.event.source_legend')}</legend>
                    <fieldset class="choices">
                        <legend class="vff-label">{$i18n.t('proposals.event.source_kind')}</legend>
                        <div class="choice-list">
                            {#each sourceKinds as kind (kind)}
                                <label class="vff-check">
                                    <input
                                        id="event-source-kind-{kind}"
                                        type="radio"
                                        name="event-source-kind"
                                        value={kind}
                                        bind:group={form.source_kind}
                                    />
                                    {$i18n.t(`proposals.event.source_kinds.${kind}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                    <Field
                        id="event-source-title"
                        label={$i18n.t('proposals.event.source_title')}
                        hint={$i18n.t('proposals.event.source_title_hint')}
                        error={errorFor('source_title')}
                        required
                    >
                        {#snippet children(attrs)}
                            <input
                                {...attrs}
                                class="vff-input"
                                type="text"
                                minlength={LIMITS.sourceTitle[0]}
                                maxlength={LIMITS.sourceTitle[1]}
                                required
                                bind:value={form.source_title}
                            />
                        {/snippet}
                    </Field>
                    <Field
                        id="event-source-url"
                        label={$i18n.t('proposals.event.source_url')}
                        hint={$i18n.t(
                            form.source_kind === 'print'
                                ? 'proposals.event.source_url_hint_print'
                                : 'proposals.event.source_url_hint',
                        )}
                        error={errorFor('source_url')}
                        required={form.source_kind !== 'print'}
                    >
                        {#snippet children(attrs)}
                            <input
                                {...attrs}
                                class="vff-input"
                                type="url"
                                inputmode="url"
                                spellcheck="false"
                                maxlength={LIMITS.sourceUrl}
                                required={form.source_kind !== 'print'}
                                bind:value={form.source_url}
                            />
                        {/snippet}
                    </Field>
                    {#if form.source_kind === 'document'}
                        <Field
                            id="event-source-license"
                            label={$i18n.t('proposals.event.source_license')}
                            hint={$i18n.t('proposals.event.source_license_hint')}
                            error={errorFor('source_license')}
                            required
                        >
                            {#snippet children(attrs)}
                                <select
                                    {...attrs}
                                    class="vff-select"
                                    required
                                    bind:value={form.source_license}
                                >
                                    <option value=""
                                        >{$i18n.t('proposals.event.choose_license')}</option
                                    >
                                    {#each licenses as l (l)}
                                        <option value={l}
                                            >{$i18n.t(`proposals.licenses.${l}`)}</option
                                        >
                                    {/each}
                                </select>
                            {/snippet}
                        </Field>
                    {/if}
                </fieldset>

                <div class="vff-panel group">
                    <Field
                        id="event-notes"
                        label={$i18n.t('proposals.event.notes')}
                        hint={$i18n.t('proposals.event.notes_hint')}
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
                    <p class="intro">{$i18n.t('proposals.event.after')}</p>
                </div>

                {#snippet actions()}
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        {$i18n.t('proposals.event.submit')}
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
    .pk {
        min-width: 0;
    }
    .pk .vff-label {
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
</style>
