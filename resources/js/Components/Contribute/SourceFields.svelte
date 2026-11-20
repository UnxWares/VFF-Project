<script>
    /**
     * Panneau « Source » d'une proposition sourcée (événement daté, matériel sur une ligne, série) :
     * nature de la source (boutons radio), titre ou référence, adresse (facultative pour un ouvrage
     * papier), licence d'un document numérisé. Mêmes champs que SourceCitation côté serveur.
     *
     * Props : `id` (préfixe des id : `{id}-source-kind-web`, `{id}-source-title`, `{id}-source-url`,
     * `{id}-source-license`), `kinds`, `licenses`, `kind`, `title`, `url`, `license` (liables),
     * `errors` (champ `source_*` → message), `licenseHint` (aide de la licence), `extra` (champs en
     * plus, après la source).
     */
    import Field from '$components/ui/Field.svelte';
    import { i18n } from '$lib/i18n.js';
    import { LIMITS } from './proposals.js';

    /** @type {{ id: string, kinds: string[], licenses: string[], kind?: string, title?: string, url?: string, license?: string, errors?: Record<string, string|null>, licenseHint?: string|null, extra?: import('svelte').Snippet }} */
    let {
        id,
        kinds,
        licenses,
        kind = $bindable('web'),
        title = $bindable(''),
        url = $bindable(''),
        license = $bindable(''),
        errors = {},
        licenseHint = null,
        extra,
    } = $props();
</script>

<fieldset class="vff-panel group">
    <legend>{$i18n.t('proposals.event.source_legend')}</legend>
    <fieldset class="choices">
        <legend class="vff-label">{$i18n.t('proposals.event.source_kind')}</legend>
        <div class="choice-list">
            {#each kinds as k (k)}
                <label class="vff-check">
                    <input
                        id="{id}-source-kind-{k}"
                        type="radio"
                        name="{id}-source-kind"
                        value={k}
                        bind:group={kind}
                    />
                    {$i18n.t(`proposals.event.source_kinds.${k}`)}
                </label>
            {/each}
        </div>
    </fieldset>
    <Field
        id="{id}-source-title"
        label={$i18n.t('proposals.event.source_title')}
        hint={$i18n.t('proposals.event.source_title_hint')}
        error={errors.source_title ?? null}
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
                bind:value={title}
            />
        {/snippet}
    </Field>
    <Field
        id="{id}-source-url"
        label={$i18n.t('proposals.event.source_url')}
        hint={$i18n.t(
            kind === 'print'
                ? 'proposals.event.source_url_hint_print'
                : 'proposals.event.source_url_hint',
        )}
        error={errors.source_url ?? null}
        required={kind !== 'print'}
    >
        {#snippet children(attrs)}
            <input
                {...attrs}
                class="vff-input"
                type="url"
                inputmode="url"
                spellcheck="false"
                maxlength={LIMITS.sourceUrl}
                required={kind !== 'print'}
                bind:value={url}
            />
        {/snippet}
    </Field>
    {#if kind === 'document'}
        <Field
            id="{id}-source-license"
            label={$i18n.t('proposals.event.source_license')}
            hint={licenseHint ?? $i18n.t('proposals.event.source_license_hint')}
            error={errors.source_license ?? null}
            required
        >
            {#snippet children(attrs)}
                <select {...attrs} class="vff-select" required bind:value={license}>
                    <option value="">{$i18n.t('proposals.event.choose_license')}</option>
                    {#each licenses as l (l)}
                        <option value={l}>{$i18n.t(`proposals.licenses.${l}`)}</option>
                    {/each}
                </select>
            {/snippet}
        </Field>
    {/if}
    {@render extra?.()}
</fieldset>
