<script>
    import { useForm } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import EditMap from '$components/Contribute/EditMap.svelte';
    import { i18n } from '$lib/i18n.js';

    let { preselectedLine, eras = [], statuses = [], trafficTypes = [] } = $props();

    // Saisie PK en "km+m" pour l'utilisateur, conversion en mètres pour la DB.
    let pkStartKm = $state(0);
    let pkStartM = $state(0);
    let pkEndKm = $state(1);
    let pkEndM = $state(0);
    let sourcesRaw = $state('');

    function setPkStart(m) {
        pkStartKm = Math.floor(m / 1000);
        pkStartM = m % 1000;
    }
    function setPkEnd(m) {
        pkEndKm = Math.floor(m / 1000);
        pkEndM = m % 1000;
    }

    const currentLineSlug = $derived(preselectedLine?.slug ?? null);

    const currentEra = $derived(eras.find((e) => e.is_current));

    const form = useForm({
        line_code: preselectedLine?.code ?? '',
        era_year: currentEra?.year ?? 2030,
        pk_start_m: 0,
        pk_end_m: 1000,
        changes: {
            status: '',
            traffic_type: '',
            electrified: null,
            voltage_v: null,
            max_speed_kmh: null,
            is_heritage: false,
        },
        sources: [],
        notes: '',
    });

    function submit(e) {
        e.preventDefault();
        // Conversion km+m → mètres
        form.pk_start_m = pkStartKm * 1000 + pkStartM;
        form.pk_end_m = pkEndKm * 1000 + pkEndM;
        // Sources : split par ligne / virgule
        form.sources = sourcesRaw
            .split(/[\n,]/)
            .map((s) => s.trim())
            .filter(Boolean);
        // Vide les changes non remplis
        Object.keys(form.changes).forEach((k) => {
            if (form.changes[k] === '' || form.changes[k] === null) {
                delete form.changes[k];
            }
        });
        form.post('/contribute/edit-segment');
    }
</script>

<svelte:head>
    <title>{$i18n.t('maps.editor.edit.meta_title')}</title>
</svelte:head>

<Layout compactHeader>
    <main class="contribute-page">
        <div class="page-head">
            <a href="/maps" class="back-link">
                <i class="bi bi-arrow-left"></i>
                {$i18n.t('maps.editor.edit.back_to_map')}
            </a>
            <h1>{$i18n.t('maps.editor.edit.title')}</h1>
            <p class="subtitle">
                {$i18n.t('maps.editor.edit.subtitle')}
            </p>
        </div>

        <form onsubmit={submit} class="contrib-form" novalidate>
            <div class="field">
                <label for="line-code">{$i18n.t('maps.editor.edit.line_code')}</label>
                <input
                    id="line-code"
                    type="text"
                    bind:value={form.line_code}
                    placeholder={$i18n.t('maps.editor.edit.line_code_placeholder')}
                    required
                />
                {#if preselectedLine}
                    <small class="hint">→ {preselectedLine.name}</small>
                {/if}
                {#if form.errors.line_code}<span class="error">{form.errors.line_code}</span>{/if}
            </div>

            <div class="field">
                <label for="era">{$i18n.t('maps.editor.edit.era')}</label>
                <select id="era" bind:value={form.era_year}>
                    {#each eras as e}
                        <option value={e.year}
                            >{e.year}{e.is_current
                                ? ` ${$i18n.t('maps.editor.edit.era_current')}`
                                : ''}</option
                        >
                    {/each}
                </select>
            </div>

            <fieldset class="pk-fieldset">
                <legend>{$i18n.t('maps.editor.edit.pk_legend')}</legend>
                {#if currentLineSlug}
                    <EditMap
                        lineSlug={currentLineSlug}
                        eraYear={form.era_year}
                        onPkStart={setPkStart}
                        onPkEnd={setPkEnd}
                        height="320px"
                    />
                {/if}
                <div class="pk-row">
                    <div class="pk-block">
                        <span class="pk-label">{$i18n.t('maps.editor.edit.pk_start')}</span>
                        <div class="pk-inputs">
                            <input type="number" bind:value={pkStartKm} min="0" max="999" />
                            {$i18n.t('maps.editor.edit.pk_unit_km')}
                            <input type="number" bind:value={pkStartM} min="0" max="999" />
                            {$i18n.t('maps.editor.edit.pk_unit_m')}
                        </div>
                    </div>
                    <div class="pk-block">
                        <span class="pk-label">{$i18n.t('maps.editor.edit.pk_end')}</span>
                        <div class="pk-inputs">
                            <input type="number" bind:value={pkEndKm} min="0" max="999" />
                            {$i18n.t('maps.editor.edit.pk_unit_km')}
                            <input type="number" bind:value={pkEndM} min="0" max="999" />
                            {$i18n.t('maps.editor.edit.pk_unit_m')}
                        </div>
                    </div>
                </div>
                <p class="pk-hint">
                    {$i18n.t('maps.editor.edit.pk_hint', { function: 'ST_LineSubstring' })}
                </p>
            </fieldset>

            <fieldset class="changes-fieldset">
                <legend>{$i18n.t('maps.editor.edit.changes_legend')}</legend>

                <div class="grid-2">
                    <label class="field">
                        <span>{$i18n.t('maps.editor.edit.status')}</span>
                        <select bind:value={form.changes.status}>
                            <option value="">{$i18n.t('maps.editor.edit.unchanged')}</option>
                            {#each statuses as s}<option value={s}>{s}</option>{/each}
                        </select>
                    </label>
                    <label class="field">
                        <span>{$i18n.t('maps.editor.edit.traffic')}</span>
                        <select bind:value={form.changes.traffic_type}>
                            <option value="">{$i18n.t('maps.editor.edit.unchanged')}</option>
                            {#each trafficTypes as t}<option value={t}>{t}</option>{/each}
                        </select>
                    </label>
                </div>

                <label class="field">
                    <input type="checkbox" bind:checked={form.changes.is_heritage} />
                    {$i18n.t('maps.editor.edit.heritage_checkbox')}
                </label>

                <div class="grid-2">
                    <label class="field">
                        <span>{$i18n.t('maps.editor.edit.max_speed')}</span>
                        <input
                            type="number"
                            bind:value={form.changes.max_speed_kmh}
                            min="0"
                            max="600"
                        />
                    </label>
                    <label class="field">
                        <span>{$i18n.t('maps.editor.edit.voltage')}</span>
                        <input
                            type="number"
                            bind:value={form.changes.voltage_v}
                            min="0"
                            max="50000"
                        />
                    </label>
                </div>
            </fieldset>

            <div class="field">
                <label for="sources">{$i18n.t('maps.editor.edit.sources_label')}</label>
                <textarea
                    id="sources"
                    rows="3"
                    bind:value={sourcesRaw}
                    placeholder={$i18n.t('maps.editor.edit.sources_placeholder')}
                ></textarea>
            </div>

            <div class="field">
                <label for="notes">{$i18n.t('maps.editor.edit.notes_label')}</label>
                <textarea
                    id="notes"
                    rows="4"
                    bind:value={form.notes}
                    placeholder={$i18n.t('maps.editor.edit.notes_placeholder')}
                ></textarea>
            </div>

            <div class="actions">
                <button type="submit" class="vff-btn primary" disabled={form.processing}>
                    <i class="bi bi-send-fill"></i>
                    {$i18n.t('maps.editor.edit.submit')}
                </button>
            </div>
        </form>
    </main>
</Layout>

<style>
    .contribute-page {
        max-width: 780px;
        margin: 0 auto;
        padding: 48px 20px;
    }
    .page-head {
        margin-bottom: 36px;
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
        font-size: 32px;
        margin: 14px 0 6px;
    }
    h1:after {
        display: none;
    }
    .subtitle {
        color: var(--vff-text-muted);
        font-size: 15px;
        line-height: 1.5;
    }

    .contrib-form {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .field label,
    .field > span {
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--vff-text-muted);
    }
    .field input,
    .field select,
    .field textarea {
        padding: 10px 12px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text);
        font-family: var(--vff-font-body);
        font-size: 15px;
    }
    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        outline: none;
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 3px var(--vff-accent-soft);
    }
    .hint {
        color: var(--vff-accent);
        font-size: 13px;
    }
    .error {
        color: #c53030;
        font-size: 13px;
    }
    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    fieldset {
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 18px;
        margin: 0;
    }
    fieldset legend {
        font-family: var(--vff-font-display);
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--vff-accent);
        padding: 0 8px;
    }
    .pk-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }
    .pk-block {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .pk-label {
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--vff-text-muted);
    }
    .pk-inputs {
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: var(--vff-font-secondary, monospace);
        font-size: 14px;
        color: var(--vff-text-muted);
    }
    .pk-inputs input {
        width: 80px;
        padding: 8px 10px;
        background-color: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text);
        font-family: var(--vff-font-secondary, monospace);
        font-size: 15px;
    }
    .pk-hint {
        margin: 14px 0 0;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .pk-hint code {
        background-color: var(--vff-bg-elevated);
        padding: 1px 6px;
        border-radius: 3px;
        font-family: var(--vff-font-secondary, monospace);
    }
    .changes-fieldset {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 8px;
    }
    .actions .vff-btn {
        border: 0;
        cursor: pointer;
    }
</style>
