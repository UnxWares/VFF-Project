<script>
    /**
     * « Proposer une image » : un fichier de Wikimedia Commons (vérifié sur Commons avant l'envoi)
     * ou un fichier déposé (aperçu, contrôles de type, taille et dimensions), rattaché à une ligne,
     * une gare, un événement daté d'une ligne ou une série de matériel. Le serveur retire les métadonnées du fichier et le
     * garde privé jusqu'à sa publication (MediaContributionController). Licence en deux temps (famille, puis
     * version), légende facultative en français, anglais et allemand ; une position GPS trouvée dans une photo
     * JPEG est lue dans le navigateur et recopiée dans les coordonnées seulement si le contributeur le demande.
     */
    import { Link, useForm } from '@inertiajs/svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import { untrack } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import EntityPicker from '$components/Contribute/EntityPicker.svelte';
    import PartialDateFields from '$components/Contribute/PartialDateFields.svelte';
    import ProposalForm from '$components/Contribute/ProposalForm.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatStationDate } from '$lib/stationDates.js';
    import { colon, formatLocale } from '$lib/typo.js';
    import { figureLabel } from '$lib/altText.js';
    import { fileExifPosition } from '$lib/exifPosition.js';
    import {
        IMAGE_MIMES,
        LIMITS,
        commonsFileTitle,
        composeDate,
        decimal,
        eventTypeLabel,
        formatBytes,
        licenseFamilies,
        mediaErrors,
    } from '$components/Contribute/proposals.js';

    /** @type {{ preselected?: { line: { slug: string, code: string|null, name: string }|null, station: { slug: string, name: string, city: string|null }|null, material?: { slug: string, name: string, type: string|null }|null, eventId: number|null }, mediaTypes?: string[], licenses?: { value: string, name: string, url: string|null, family: string, version: string|null }[], precisions?: string[], limits?: { max_bytes: number, min_side: number, max_side: number } }} */
    let {
        preselected = { line: null, station: null, material: null, eventId: null },
        mediaTypes = [],
        licenses = [],
        precisions = ['day', 'month', 'year'],
        limits = { max_bytes: 10485760, min_side: 400, max_side: 12000 },
    } = $props();

    /** Traduction passée aux fonctions de proposals.js. */
    const tr = (key, opts) => String($i18n.t(key, opts));
    const TARGETS = ['line', 'station', 'event', 'material'];
    const SOURCES = ['commons', 'upload'];
    /** Langues de la légende : le français d'abord (légende principale), puis l'anglais et l'allemand. */
    const CAPTIONS = [
        { lang: 'fr', field: 'caption' },
        { lang: 'en', field: 'caption_en' },
        { lang: 'de', field: 'caption_de' },
    ];

    // Fiche présélectionnée depuis sa page (valeurs initiales voulues).
    const initial = untrack(() => preselected);
    let targetType = $state(
        initial.eventId
            ? 'event'
            : initial.station && !initial.line
              ? 'station'
              : initial.material && !initial.line
                ? 'material'
                : 'line',
    );
    let line = $state(initial.line);
    let station = $state(initial.station);
    let material = $state(initial.material ?? null);
    let eventId = $state(initial.eventId ? String(initial.eventId) : '');

    let capturedPrecision = $state('');
    let capturedYear = $state('');
    let capturedMonth = $state('');
    let capturedDay = $state('');

    /** @type {string|null} */
    let previewUrl = $state(null);
    /** @type {{ width: number, height: number }|null} */
    let dimensions = $state(null);
    /** Position GPS lue dans la photo choisie (jamais envoyée sans le bouton « Reprendre »). */
    /** @type {{ lat: number, lng: number }|null} */
    let filePosition = $state(null);
    let positionCopied = $state(false);

    /** Vérification d'un fichier Commons : idle | checking | ok | error. */
    let commonsStatus = $state('idle');
    /** @type {any} */
    let commonsFile = $state.raw(null);
    /** @type {string|null} */
    let commonsError = $state(null);

    let attempted = $state(false);
    /** @type {{ showErrors: () => Promise<void> }|undefined} */
    let proposal = $state();

    const form = useForm({
        source: 'upload',
        commons_url: '',
        /** @type {File|null} */
        file: null,
        media_type: 'photo',
        title: '',
        caption: '',
        caption_en: '',
        caption_de: '',
        author: '',
        license: '',
        place: '',
        lat: '',
        lng: '',
        rights_confirmed: false,
        notes: '',
    });

    /**
     * Événements datés d'une ligne, pour un rattachement à un événement (API publique, trois pages
     * de 100 au plus).
     * @param {string} slug
     * @returns {Promise<any[]>}
     */
    async function fetchEvents(slug) {
        const found = [];
        /** @type {string|null} */
        let next = `/api/v1/events?line=${encodeURIComponent(slug)}&per_page=100`;
        for (let page = 0; next && page < 3; page++) {
            const res = await fetch(next, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const body = await res.json();
            found.push(...(body.data ?? []));
            next = body.links?.next ?? null;
        }
        return found;
    }

    const eventsRequest = $derived(
        targetType === 'event' && line?.slug ? fetchEvents(line.slug) : null,
    );

    /**
     * Libère l'adresse d'aperçu d'un fichier quand l'image change ou disparaît.
     * @param {string} url
     */
    function releaseOnDetach(url) {
        return () => () => URL.revokeObjectURL(url);
    }

    /** @param {any} e événement de l'API */
    function eventLabel(e) {
        const date = formatStationDate(e.date, e.date_precision, null, tr, $i18n.language) ?? '';
        const where = e.section_from && e.section_to ? ` (${e.section_from}, ${e.section_to})` : '';
        return `${eventTypeLabel(e.type, tr)}, ${date}${where}`;
    }

    /** @param {Event & { currentTarget: HTMLInputElement }} e */
    function onFile(e) {
        const file = e.currentTarget.files?.[0] ?? null;
        form.file = file;
        dimensions = null;
        filePosition = null;
        positionCopied = false;
        previewUrl = file && IMAGE_MIMES.includes(file.type) ? URL.createObjectURL(file) : null;
        if (file) {
            fileExifPosition(file).then((position) => {
                if (form.file === file) filePosition = position;
            });
        }
    }

    /** @param {number} value */
    const coordinate = (value) =>
        value.toLocaleString(formatLocale($i18n.language), {
            maximumFractionDigits: 6,
            useGrouping: false,
        });

    /** Recopie la position de la photo dans les coordonnées, à la demande du contributeur. */
    function copyPosition() {
        if (!filePosition) return;
        form.lat = coordinate(filePosition.lat);
        form.lng = coordinate(filePosition.lng);
        positionCopied = true;
    }

    const licenseGroups = $derived(licenseFamilies(licenses));
    const chosenFamily = $derived(
        licenseGroups.find((g) => g.choices.some((c) => c.value === form.license)) ?? null,
    );

    /**
     * Famille choisie : sa version la plus récente (la première de la liste), sauf si la licence
     * choisie en fait déjà partie.
     * @param {{ family: string, choices: { value: string }[] }} group
     */
    function chooseFamily(group) {
        if (!group.choices.some((c) => c.value === form.license)) {
            form.license = group.choices[0].value;
        }
    }

    /**
     * Licence montrée pour une famille : celle choisie, sinon sa version la plus récente.
     * @param {{ choices: { value: string, name: string, url: string|null }[] }} group
     */
    function shownLicense(group) {
        return group.choices.find((c) => c.value === form.license) ?? group.choices[0];
    }

    /** @param {Event & { currentTarget: HTMLImageElement }} e */
    function onPreviewLoad(e) {
        dimensions = { width: e.currentTarget.naturalWidth, height: e.currentTarget.naturalHeight };
    }

    function onCommonsInput() {
        commonsStatus = 'idle';
        commonsFile = null;
        commonsError = null;
    }

    async function checkCommons() {
        const url = form.commons_url.trim();
        if (!commonsFileTitle(url)) {
            commonsStatus = 'error';
            commonsError = $i18n.t('proposals.media.errors.commons_url');
            return;
        }
        commonsStatus = 'checking';
        try {
            const res = await fetch(`/contribute/media/commons?url=${encodeURIComponent(url)}`, {
                headers: { Accept: 'application/json' },
            });
            const body = await res.json();
            if (res.ok) {
                commonsFile = body.file;
                commonsStatus = 'ok';
                return;
            }
            commonsError =
                body.errors?.url?.[0] ?? $i18n.t('proposals.media.errors.commons_unreachable');
        } catch {
            commonsError = $i18n.t('proposals.media.errors.commons_unreachable');
        }
        commonsStatus = 'error';
    }

    /** Proposition telle qu'elle part au serveur (FormData pour un fichier déposé). */
    function values() {
        const target = {
            target_type: targetType,
            line_slug:
                targetType === 'station' || targetType === 'material' ? null : (line?.slug ?? ''),
            station_slug: targetType === 'station' ? (station?.slug ?? '') : null,
            material_slug: targetType === 'material' ? (material?.slug ?? '') : null,
            event_id: targetType === 'event' ? eventId : null,
            notes: form.notes.trim() || null,
        };
        if (form.source === 'commons') {
            return { source: 'commons', ...target, commons_url: form.commons_url.trim() };
        }
        return {
            source: 'upload',
            ...target,
            file: form.file,
            media_type: form.media_type,
            title: form.title.trim(),
            caption: form.caption.trim() || null,
            caption_en: form.caption_en.trim() || null,
            caption_de: form.caption_de.trim() || null,
            author: form.author.trim(),
            license: form.license,
            captured_precision: capturedPrecision || null,
            captured_date: capturedPrecision
                ? composeDate(capturedPrecision, capturedYear, capturedMonth, capturedDay)
                : null,
            place: form.place.trim() || null,
            lat: decimal(form.lat),
            lng: decimal(form.lng),
            rights_confirmed: form.rights_confirmed,
        };
    }

    /** Valeurs pour les contrôles du navigateur (coordonnées et date telles que saisies). */
    function checked() {
        return {
            ...values(),
            station_slug: station?.slug ?? '',
            line_slug: line?.slug ?? '',
            material_slug: material?.slug ?? '',
            lat: form.lat,
            lng: form.lng,
        };
    }

    const clientErrors = $derived(attempted ? mediaErrors(checked(), limits, tr, dimensions) : {});
    const errors = $derived({ ...form.errors, ...clientErrors });

    const FIELD_IDS = {
        target_type: 'media-target-line',
        line_slug: 'media-line',
        station_slug: 'media-station',
        material_slug: 'media-material',
        event_id: 'media-event',
        source: 'media-source-upload',
        commons_url: 'media-commons-url',
        file: 'media-file',
        media_type: 'media-type',
        title: 'media-title',
        caption: 'media-caption-fr',
        caption_en: 'media-caption-en',
        caption_de: 'media-caption-de',
        author: 'media-author',
        license: 'media-license-0',
        captured_precision: 'media-captured-year',
        captured_date: 'media-captured-year',
        place: 'media-place',
        lat: 'media-lat',
        lng: 'media-lat',
        rights_confirmed: 'media-rights',
        notes: 'media-notes',
    };

    /** @param {string} key */
    function errorFor(key) {
        const e = errors[key];
        return Array.isArray(e) ? e.join(' ') : (e ?? null);
    }

    const commonsMessage = $derived(
        errorFor('commons_url') ?? (commonsStatus === 'error' ? commonsError : null),
    );
    const fileFacts = $derived(
        form.file && dimensions
            ? $i18n.t('proposals.media.file_chosen', {
                  size: formatBytes(form.file.size, $i18n.language),
                  width: dimensions.width,
                  height: dimensions.height,
              })
            : null,
    );

    /** @param {SubmitEvent} e */
    function submit(e) {
        e.preventDefault();
        attempted = true;
        if (Object.keys(mediaErrors(checked(), limits, tr, dimensions)).length) {
            proposal?.showErrors();
            return;
        }
        form.transform(() => values()).post('/contribute/media', {
            onError: () => proposal?.showErrors(),
        });
    }
</script>

<svelte:head>
    <title>{$i18n.t('proposals.media.meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('proposals.media.title')}
            subtitle={$i18n.t('proposals.media.subtitle')}
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
                    <legend>{$i18n.t('proposals.media.target_legend')}</legend>
                    <fieldset class="choices">
                        <legend class="vff-sr-only"
                            >{$i18n.t('proposals.media.target_legend')}</legend
                        >
                        <div class="choice-list">
                            {#each TARGETS as target (target)}
                                <label class="vff-check">
                                    <input
                                        id="media-target-{target}"
                                        type="radio"
                                        name="media-target"
                                        value={target}
                                        bind:group={targetType}
                                    />
                                    {$i18n.t(`proposals.media.targets.${target}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>

                    {#if targetType === 'station'}
                        <EntityPicker
                            kind="station"
                            id="media-station"
                            bind:selected={station}
                            error={errorFor('station_slug')}
                        />
                    {:else if targetType === 'material'}
                        <EntityPicker
                            kind="material"
                            id="media-material"
                            bind:selected={material}
                            error={errorFor('material_slug')}
                        />
                    {:else}
                        <EntityPicker
                            kind="line"
                            id="media-line"
                            bind:selected={line}
                            error={errorFor('line_slug')}
                        />
                    {/if}

                    {#if eventsRequest}
                        {#await eventsRequest}
                            <p class="status" role="status">
                                {$i18n.t('proposals.media.events_loading')}
                            </p>
                        {:then events}
                            {#if events.length}
                                <Field
                                    id="media-event"
                                    label={$i18n.t('proposals.media.event')}
                                    error={errorFor('event_id')}
                                    required
                                >
                                    {#snippet children(attrs)}
                                        <select
                                            {...attrs}
                                            class="vff-select"
                                            required
                                            bind:value={eventId}
                                        >
                                            <option value=""
                                                >{$i18n.t('proposals.media.event_choose')}</option
                                            >
                                            {#each events as event (event.id)}
                                                <option value={String(event.id)}
                                                    >{eventLabel(event)}</option
                                                >
                                            {/each}
                                        </select>
                                    {/snippet}
                                </Field>
                            {:else}
                                <p class="status" role="status">
                                    {$i18n.t('proposals.media.events_none')}
                                </p>
                            {/if}
                        {:catch}
                            <p class="status" role="status">
                                {$i18n.t('proposals.media.events_failed')}
                            </p>
                        {/await}
                    {/if}
                </fieldset>

                <fieldset class="vff-panel group">
                    <legend>{$i18n.t('proposals.media.source_legend')}</legend>
                    <fieldset class="choices">
                        <legend class="vff-sr-only"
                            >{$i18n.t('proposals.media.source_legend')}</legend
                        >
                        <div class="choice-list">
                            {#each SOURCES as source (source)}
                                <label class="vff-check">
                                    <input
                                        id="media-source-{source}"
                                        type="radio"
                                        name="media-source"
                                        value={source}
                                        bind:group={form.source}
                                    />
                                    {$i18n.t(`proposals.media.sources.${source}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>

                    {#if form.source === 'commons'}
                        <Field
                            id="media-commons-url"
                            label={$i18n.t('proposals.media.commons_url')}
                            hint={$i18n.t('proposals.media.commons_hint')}
                            error={commonsMessage}
                            required
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="url"
                                    inputmode="url"
                                    spellcheck="false"
                                    maxlength="500"
                                    required
                                    bind:value={form.commons_url}
                                    oninput={onCommonsInput}
                                />
                            {/snippet}
                        </Field>
                        <div class="check-row">
                            <button
                                type="button"
                                class="vff-btn secondary small"
                                disabled={commonsStatus === 'checking'}
                                onclick={checkCommons}
                            >
                                {$i18n.t('proposals.media.commons_check')}
                            </button>
                            <!-- Zone d'annonce présente même vide : le premier message est lu (RGAA 7.5). -->
                            <p class="status" role="status">
                                {#if commonsStatus === 'checking'}
                                    {$i18n.t('proposals.media.commons_checking')}
                                {:else if commonsStatus === 'ok'}
                                    <i class="bi bi-check2 ok" aria-hidden="true"></i>
                                    {$i18n.t('proposals.media.commons_ok')}
                                {/if}
                            </p>
                        </div>
                        {#if commonsStatus === 'ok' && commonsFile}
                            <!-- Image légendée : la figure reprend sa légende (RGAA 1.9). -->
                            <figure
                                class="preview"
                                role="group"
                                aria-label={figureLabel([
                                    commonsFile.title,
                                    commonsFile.author
                                        ? `${$i18n.t('proposals.review.author')}${colon($i18n.language)}${commonsFile.author}`
                                        : null,
                                    `${$i18n.t('proposals.review.license')}${colon($i18n.language)}${commonsFile.license}`,
                                ])}
                            >
                                {#if commonsFile.thumb_url}
                                    <img
                                        src={commonsSrc(commonsFile.thumb_url)}
                                        alt={commonsFile.title ?? ''}
                                    />
                                {/if}
                                <figcaption>
                                    <strong>{commonsFile.title}</strong>
                                    {#if commonsFile.author}<span
                                            >{$i18n.t('proposals.review.author')}{colon(
                                                $i18n.language,
                                            )}{commonsFile.author}</span
                                        >{/if}
                                    <span
                                        >{$i18n.t('proposals.review.license')}{colon(
                                            $i18n.language,
                                        )}{commonsFile.license}</span
                                    >
                                </figcaption>
                            </figure>
                        {/if}
                        <p class="intro">{$i18n.t('proposals.media.commons_note')}</p>
                    {:else}
                        <Field
                            id="media-file"
                            label={$i18n.t('proposals.media.file')}
                            hint={$i18n.t('proposals.media.file_hint', {
                                mb: Math.round(limits.max_bytes / 1048576),
                                min: limits.min_side.toLocaleString(formatLocale($i18n.language)),
                                max: limits.max_side.toLocaleString(formatLocale($i18n.language)),
                            })}
                            error={errorFor('file')}
                            required
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input file"
                                    type="file"
                                    accept={IMAGE_MIMES.join(',')}
                                    required
                                    onchange={onFile}
                                />
                            {/snippet}
                        </Field>
                        {#if previewUrl}
                            <figure
                                class="preview"
                                role={fileFacts ? 'group' : undefined}
                                aria-label={fileFacts ?? undefined}
                            >
                                <img
                                    src={previewUrl}
                                    alt={$i18n.t('proposals.media.preview_alt')}
                                    onload={onPreviewLoad}
                                    {@attach releaseOnDetach(previewUrl)}
                                />
                                {#if fileFacts}<figcaption>{fileFacts}</figcaption>{/if}
                            </figure>
                        {/if}
                        {#if filePosition}
                            <div class="gps">
                                <p>
                                    {$i18n.t('proposals.media.gps_found', {
                                        lat: coordinate(filePosition.lat),
                                        lng: coordinate(filePosition.lng),
                                    })}
                                </p>
                                <button
                                    type="button"
                                    class="vff-btn secondary small"
                                    onclick={copyPosition}
                                >
                                    {$i18n.t('proposals.media.gps_use')}
                                </button>
                            </div>
                        {/if}
                        <!-- Zone d'annonce présente même vide : la recopie est lue (RGAA 7.5). -->
                        <p class="status" role="status">
                            {#if positionCopied}{$i18n.t('proposals.media.gps_copied')}{/if}
                        </p>
                        <Notice tone="info" live="off"
                            >{$i18n.t('proposals.media.privacy_note')}</Notice
                        >
                    {/if}
                </fieldset>

                {#if form.source === 'upload'}
                    <fieldset class="vff-panel group">
                        <legend>{$i18n.t('proposals.media.details_legend')}</legend>
                        <div class="grid-2">
                            <Field
                                id="media-type"
                                label={$i18n.t('proposals.media.media_type')}
                                error={errorFor('media_type')}
                                required
                            >
                                {#snippet children(attrs)}
                                    <select
                                        {...attrs}
                                        class="vff-select"
                                        required
                                        bind:value={form.media_type}
                                    >
                                        {#each mediaTypes as type (type)}
                                            <option value={type}
                                                >{$i18n.t(
                                                    `proposals.media.media_types.${type}`,
                                                )}</option
                                            >
                                        {/each}
                                    </select>
                                {/snippet}
                            </Field>
                        </div>
                        <Field
                            id="media-title"
                            label={$i18n.t('proposals.media.title_label')}
                            hint={$i18n.t('proposals.media.title_hint')}
                            error={errorFor('title')}
                            required
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="text"
                                    maxlength={LIMITS.title[1]}
                                    required
                                    bind:value={form.title}
                                />
                            {/snippet}
                        </Field>
                        <fieldset class="choices" aria-describedby="media-caption-hint">
                            <legend class="vff-label">{$i18n.t('proposals.media.caption')}</legend>
                            <p class="vff-hint" id="media-caption-hint">
                                {$i18n.t('proposals.media.caption_hint')}
                            </p>
                            {#each CAPTIONS as caption (caption.lang)}
                                <Field
                                    id="media-caption-{caption.lang}"
                                    label={$i18n.t(`proposals.media.caption_langs.${caption.lang}`)}
                                    error={errorFor(caption.field)}
                                >
                                    {#snippet children(attrs)}
                                        <textarea
                                            {...attrs}
                                            class="vff-textarea"
                                            rows="2"
                                            lang={caption.lang}
                                            maxlength={LIMITS.caption}
                                            bind:value={form[caption.field]}
                                        ></textarea>
                                    {/snippet}
                                </Field>
                            {/each}
                        </fieldset>
                    </fieldset>

                    <fieldset class="vff-panel group">
                        <legend>{$i18n.t('proposals.media.rights_legend')}</legend>
                        <Field
                            id="media-author"
                            label={$i18n.t('proposals.media.author')}
                            hint={$i18n.t('proposals.media.author_hint')}
                            error={errorFor('author')}
                            required
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="text"
                                    maxlength={LIMITS.author[1]}
                                    required
                                    bind:value={form.author}
                                />
                            {/snippet}
                        </Field>
                        <fieldset
                            class="choices"
                            aria-describedby="media-license-hint media-license-error"
                        >
                            <legend class="vff-label"
                                >{$i18n.t('proposals.media.license')}<span class="required"
                                    >{` (${$i18n.t('common.required')})`}</span
                                ></legend
                            >
                            <p class="vff-hint" id="media-license-hint">
                                {$i18n.t('proposals.media.license_hint')}
                            </p>
                            <ul class="licenses">
                                {#each licenseGroups as group, i (group.family)}
                                    {@const license = shownLicense(group)}
                                    <li>
                                        <label class="vff-check">
                                            <input
                                                id="media-license-{i}"
                                                type="radio"
                                                name="media-license"
                                                value={group.family}
                                                checked={chosenFamily?.family === group.family}
                                                onchange={() => chooseFamily(group)}
                                            />
                                            {$i18n.t(
                                                `proposals.media.license_families.${group.family}`,
                                            )}
                                        </label>
                                        {#if license.url}
                                            <a
                                                class="license-link"
                                                href={license.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                >{$i18n.t('proposals.media.license_text')}<span
                                                    class="vff-sr-only"
                                                    >{colon($i18n.language)}{license.name} ({$i18n.t(
                                                        'common.new_tab',
                                                    )})</span
                                                ><i
                                                    class="bi bi-box-arrow-up-right vff-ext"
                                                    aria-hidden="true"
                                                ></i></a
                                            >
                                        {/if}
                                        {#if chosenFamily?.family === group.family && group.choices.length > 1}
                                            <div class="version">
                                                <Field
                                                    id="media-license-version"
                                                    label={$i18n.t(
                                                        'proposals.media.license_version',
                                                    )}
                                                    hint={$i18n.t(
                                                        'proposals.media.license_version_hint',
                                                    )}
                                                >
                                                    {#snippet children(attrs)}
                                                        <select
                                                            {...attrs}
                                                            class="vff-select"
                                                            bind:value={form.license}
                                                        >
                                                            {#each group.choices as choice (choice.value)}
                                                                <option value={choice.value}
                                                                    >{choice.name}</option
                                                                >
                                                            {/each}
                                                        </select>
                                                    {/snippet}
                                                </Field>
                                            </div>
                                        {/if}
                                    </li>
                                {/each}
                            </ul>
                            <p class="vff-error" id="media-license-error" aria-live="polite">
                                {#if errorFor('license')}<i
                                        class="bi bi-exclamation-circle"
                                        aria-hidden="true"
                                    ></i><span>{errorFor('license')}</span>{/if}
                            </p>
                        </fieldset>
                        <div class="rights">
                            <label class="vff-check top">
                                <input
                                    id="media-rights"
                                    type="checkbox"
                                    required
                                    aria-invalid={errorFor('rights_confirmed') ? 'true' : undefined}
                                    aria-describedby="media-rights-error"
                                    bind:checked={form.rights_confirmed}
                                />
                                <span
                                    >{$i18n.t('proposals.media.rights_confirmed')}<span
                                        class="required">{` (${$i18n.t('common.required')})`}</span
                                    ></span
                                >
                            </label>
                            <p class="vff-error" id="media-rights-error" aria-live="polite">
                                {#if errorFor('rights_confirmed')}<i
                                        class="bi bi-exclamation-circle"
                                        aria-hidden="true"
                                    ></i><span>{errorFor('rights_confirmed')}</span>{/if}
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="vff-panel group">
                        <legend>{$i18n.t('proposals.media.when_legend')}</legend>
                        <PartialDateFields
                            id="media-captured"
                            legend={$i18n.t('proposals.event.date_legend')}
                            {precisions}
                            allowUnknown
                            unknownLabel={$i18n.t('proposals.media.captured_unknown')}
                            bind:precision={capturedPrecision}
                            bind:year={capturedYear}
                            bind:month={capturedMonth}
                            bind:day={capturedDay}
                            error={errorFor('captured_date') ?? errorFor('captured_precision')}
                        />
                        <Field
                            id="media-place"
                            label={$i18n.t('proposals.media.place')}
                            hint={$i18n.t('proposals.media.place_hint')}
                            error={errorFor('place')}
                        >
                            {#snippet children(attrs)}
                                <input
                                    {...attrs}
                                    class="vff-input"
                                    type="text"
                                    maxlength={LIMITS.place}
                                    bind:value={form.place}
                                />
                            {/snippet}
                        </Field>
                        <fieldset class="choices" aria-describedby="media-coordinates-hint">
                            <legend class="vff-label"
                                >{$i18n.t('proposals.media.coordinates')}</legend
                            >
                            <p class="vff-hint" id="media-coordinates-hint">
                                {$i18n.t('proposals.media.coordinates_hint')}
                            </p>
                            <div class="grid-2">
                                <Field
                                    id="media-lat"
                                    label={$i18n.t('proposals.media.lat')}
                                    error={errorFor('lat')}
                                >
                                    {#snippet children(attrs)}
                                        <input
                                            {...attrs}
                                            class="vff-input"
                                            type="text"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            bind:value={form.lat}
                                        />
                                    {/snippet}
                                </Field>
                                <Field
                                    id="media-lng"
                                    label={$i18n.t('proposals.media.lng')}
                                    error={errorFor('lng')}
                                >
                                    {#snippet children(attrs)}
                                        <input
                                            {...attrs}
                                            class="vff-input"
                                            type="text"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            bind:value={form.lng}
                                        />
                                    {/snippet}
                                </Field>
                            </div>
                        </fieldset>
                    </fieldset>
                {/if}

                <div class="vff-panel group">
                    <Field
                        id="media-notes"
                        label={$i18n.t('proposals.media.notes')}
                        hint={$i18n.t('proposals.media.notes_hint')}
                        error={errorFor('notes')}
                    >
                        {#snippet children(attrs)}
                            <textarea
                                {...attrs}
                                class="vff-textarea"
                                rows="3"
                                maxlength={LIMITS.notes}
                                bind:value={form.notes}
                            ></textarea>
                        {/snippet}
                    </Field>
                </div>

                {#snippet actions()}
                    <button type="submit" class="vff-btn primary" disabled={form.processing}>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        {$i18n.t('proposals.media.submit')}
                    </button>
                    {#if form.progress}
                        <label class="progress">
                            <span>{$i18n.t('proposals.media.progress')}</span>
                            <progress max="100" value={form.progress.percentage ?? 0}
                                >{form.progress.percentage ?? 0} %</progress
                            >
                        </label>
                    {/if}
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
    .status {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .ok {
        color: var(--vff-success);
    }
    .check-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .file {
        padding-block: 5px;
    }
    /* Bouton du sélecteur de fichier aux couleurs d'un bouton secondaire. */
    .file::file-selector-button {
        min-height: 32px;
        margin-inline-end: var(--vff-space-3);
        padding: 4px 14px;
        border: 1px solid var(--vff-accent-ink);
        border-radius: var(--vff-radius-sm);
        background-color: transparent;
        color: var(--vff-accent-ink);
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .file::file-selector-button:hover {
        background-color: var(--vff-accent-soft);
    }
    .preview {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        margin: 0;
    }
    .preview img {
        display: block;
        width: auto;
        max-width: 100%;
        height: auto;
        max-height: 20rem;
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-muted);
    }
    .preview figcaption {
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .preview figcaption strong {
        color: var(--vff-text);
    }
    .licenses {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        margin: var(--vff-space-2) 0 0;
        padding: 0;
        list-style: none;
    }
    .licenses li {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-4);
    }
    .version {
        flex-basis: 100%;
        max-width: 20rem;
        padding-inline-start: var(--vff-space-6);
    }
    .gps {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-2);
        padding: var(--vff-space-3) var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .gps p {
        margin: 0;
    }
    .license-link {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .required {
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .rights {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .top {
        align-items: flex-start;
    }
    .progress {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .progress progress {
        width: 10rem;
        accent-color: var(--vff-accent-ink);
    }
</style>
