<script>
    /**
     * Date à la précision de sa source : jour, mois ou année (et « date inconnue » si `allowUnknown`).
     * Un groupe de boutons radio pour la précision, puis l'année, le mois (liste) et le jour selon
     * la précision choisie. La date composée (« 1890 », « 1890-06 », « 1890-06-16 ») se lit avec
     * composeDate() de proposals.js.
     *
     * Props : `id` (préfixe des id ; l'année reçoit `{id}-year`, cible du récapitulatif d'erreurs),
     * `legend`, `precision`, `year`, `month`, `day` (liables), `precisions`, `allowUnknown`,
     * `unknownLabel`, `hint`, `error`.
     */
    import { i18n } from '$lib/i18n.js';
    import { monthOptions } from './proposals.js';

    /** @type {{ id: string, legend: string, precision?: string, year?: string|number, month?: string|number, day?: string|number, precisions?: string[], allowUnknown?: boolean, unknownLabel?: string, hint?: string|null, error?: string|null }} */
    let {
        id,
        legend,
        precision = $bindable('day'),
        year = $bindable(''),
        month = $bindable(''),
        day = $bindable(''),
        precisions = ['day', 'month', 'year'],
        allowUnknown = false,
        unknownLabel = '',
        hint = null,
        error = null,
    } = $props();

    const months = $derived(monthOptions($i18n.language));
    const describedBy = $derived(
        [hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') ||
            undefined,
    );
    const invalid = $derived(error ? 'true' : undefined);
</script>

<fieldset class="date" aria-describedby={describedBy}>
    <legend>{legend}</legend>
    {#if hint}<p class="vff-hint" id="{id}-hint">{hint}</p>{/if}

    <fieldset class="precisions">
        <legend class="vff-label">{$i18n.t('proposals.event.precision')}</legend>
        <div class="choices">
            {#each precisions as p (p)}
                <label class="vff-check">
                    <input type="radio" name="{id}-precision" value={p} bind:group={precision} />
                    {$i18n.t(`editor.precisions.${p}`)}
                </label>
            {/each}
            {#if allowUnknown}
                <label class="vff-check">
                    <input type="radio" name="{id}-precision" value="" bind:group={precision} />
                    {unknownLabel}
                </label>
            {/if}
        </div>
    </fieldset>

    {#if precision}
        <div class="parts">
            <label class="part">
                <span class="vff-label">{$i18n.t('proposals.event.year')}</span>
                <input
                    id="{id}-year"
                    class="vff-input year"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="4"
                    required
                    aria-invalid={invalid}
                    aria-describedby={error ? `${id}-error` : undefined}
                    bind:value={year}
                />
            </label>
            {#if precision === 'month' || precision === 'day'}
                <label class="part">
                    <span class="vff-label">{$i18n.t('proposals.event.month')}</span>
                    <select
                        class="vff-select"
                        required
                        aria-invalid={invalid}
                        aria-describedby={error ? `${id}-error` : undefined}
                        bind:value={month}
                    >
                        <option value="">{$i18n.t('proposals.event.choose_month')}</option>
                        {#each months as m (m.value)}
                            <option value={m.value}>{m.label}</option>
                        {/each}
                    </select>
                </label>
            {/if}
            {#if precision === 'day'}
                <label class="part">
                    <span class="vff-label">{$i18n.t('proposals.event.day')}</span>
                    <input
                        class="vff-input day"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="2"
                        required
                        aria-invalid={invalid}
                        aria-describedby={error ? `${id}-error` : undefined}
                        bind:value={day}
                    />
                </label>
            {/if}
        </div>
    {/if}
    <p class="vff-error" id="{id}-error" aria-live="polite">
        {#if error}<i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{error}</span
            >{/if}
    </p>
</fieldset>

<style>
    .date,
    .precisions {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .date {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
    }
    .date > legend {
        float: left;
        width: 100%;
        margin: 0 0 var(--vff-space-1);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
    }
    .date > legend + * {
        clear: both;
    }
    .precisions legend {
        margin-bottom: var(--vff-space-2);
        padding: 0;
    }
    .choices {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
    }
    .parts {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3) var(--vff-space-4);
    }
    .part {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }
    .year {
        width: 7ch;
        min-width: 6rem;
        font-variant-numeric: tabular-nums;
    }
    .day {
        width: 5ch;
        min-width: 5rem;
        font-variant-numeric: tabular-nums;
    }
</style>
