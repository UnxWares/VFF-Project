<script>
    import { i18n } from '$lib/i18n.js';

    /**
     * Événements datés d'une portion : type, précision de date (jour / mois /
     * année), date, source citée (obligatoire), lien et description facultatifs.
     *
     * Props :
     *   events     : tableau bindable de { type, precision, date, source, source_url, description }
     *   eventTypes : types autorisés (LineEventType)
     *   precisions : ['day', 'month', 'year']
     *   errors     : erreurs serveur à plat ; `prefix` = 'portions.N.events'
     *   defaultSource : citation pré-remplie pour un nouvel événement
     *   onBeforeChange : appelé avant ajout / suppression (pile d'annulation)
     */
    let {
        events = $bindable([]),
        eventTypes = [],
        precisions = ['day', 'month', 'year'],
        errors = {},
        prefix = '',
        defaultSource = '',
        onBeforeChange = () => {},
    } = $props();

    function add() {
        onBeforeChange();
        const last = events[events.length - 1];
        events.push({
            type: events.length ? 'closure_passenger' : 'opening',
            precision: 'year',
            date: '',
            source: last?.source ?? defaultSource ?? '',
            source_url: last?.source_url ?? '',
            description: '',
        });
    }

    function remove(i) {
        onBeforeChange();
        events.splice(i, 1);
    }

    // Convertit la date saisie quand on change de précision (2026-10-15 → 2026-10 → 2026).
    function setPrecision(ev, precision) {
        const d = ev.date ?? '';
        const y = d.slice(0, 4);
        const m = d.slice(5, 7);
        if (precision === 'year') ev.date = y;
        else if (precision === 'month') ev.date = y && m ? `${y}-${m}` : y ? `${y}-01` : '';
        else ev.date = y && m ? `${y}-${m}-${d.slice(8, 10) || '01'}` : y ? `${y}-01-01` : '';
        ev.precision = precision;
    }

    function err(i, field) {
        return errors[`${prefix}.${i}.${field}`] ?? null;
    }

    function typeLabel(t) {
        const key = `editor.event_types.${t}`;
        const label = $i18n.t(key);
        return label === key ? t : label;
    }
</script>

<div class="events">
    {#each events as ev, i (i)}
        {@const id = `${prefix || 'ev'}-${i}`.replaceAll('.', '-')}
        <div
            class="event"
            role="group"
            aria-label={`${typeLabel(ev.type)} ${ev.date || ''}`.trim()}
        >
            <div class="row">
                <label class="f type">
                    <span>{$i18n.t('editor.events.type')}</span>
                    <select bind:value={ev.type}>
                        {#each eventTypes as t (t)}
                            <option value={t}>{typeLabel(t)}</option>
                        {/each}
                    </select>
                </label>
                <button
                    type="button"
                    class="remove"
                    onclick={() => remove(i)}
                    title={$i18n.t('editor.events.remove')}
                    aria-label={$i18n.t('editor.events.remove')}
                >
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>
            </div>
            <div class="row">
                <label class="f precision">
                    <span>{$i18n.t('editor.events.precision')}</span>
                    <select
                        value={ev.precision}
                        onchange={(e) => setPrecision(ev, e.currentTarget.value)}
                    >
                        {#each precisions as p (p)}
                            <option value={p}>{$i18n.t(`editor.precisions.${p}`)}</option>
                        {/each}
                    </select>
                </label>
                <label class="f date">
                    <span
                        >{$i18n.t('editor.events.date')}
                        <b class="req" aria-hidden="true">*</b></span
                    >
                    {#if ev.precision === 'day'}
                        <input
                            type="date"
                            bind:value={ev.date}
                            min="1750-01-01"
                            required
                            aria-invalid={err(i, 'date') ? 'true' : undefined}
                            aria-describedby={err(i, 'date') ? `${id}-date-err` : undefined}
                        />
                    {:else if ev.precision === 'month'}
                        <input
                            type="month"
                            bind:value={ev.date}
                            min="1750-01"
                            placeholder={$i18n.t('editor.events.month_placeholder')}
                            required
                            aria-invalid={err(i, 'date') ? 'true' : undefined}
                            aria-describedby={err(i, 'date') ? `${id}-date-err` : undefined}
                        />
                    {:else}
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern={'[0-9]{4}'}
                            maxlength="4"
                            bind:value={ev.date}
                            placeholder={$i18n.t('editor.events.year_placeholder')}
                            required
                            aria-invalid={err(i, 'date') ? 'true' : undefined}
                            aria-describedby={err(i, 'date') ? `${id}-date-err` : undefined}
                        />
                    {/if}
                    {#if err(i, 'date')}<em class="error" id="{id}-date-err"
                            ><i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            {err(i, 'date')}</em
                        >{/if}
                </label>
            </div>
            <label class="f">
                <span
                    >{$i18n.t('editor.events.source')} <b class="req" aria-hidden="true">*</b></span
                >
                <input
                    type="text"
                    bind:value={ev.source}
                    maxlength="500"
                    placeholder={$i18n.t('editor.events.source_placeholder')}
                    required
                    aria-invalid={err(i, 'source') ? 'true' : undefined}
                    aria-describedby={err(i, 'source') ? `${id}-source-err` : undefined}
                />
                {#if err(i, 'source')}<em class="error" id="{id}-source-err"
                        ><i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                        {err(i, 'source')}</em
                    >{/if}
            </label>
            <div class="row two">
                <label class="f">
                    <span>{$i18n.t('editor.events.source_url')}</span>
                    <input
                        type="url"
                        bind:value={ev.source_url}
                        maxlength="500"
                        placeholder="https://"
                        aria-invalid={err(i, 'source_url') ? 'true' : undefined}
                        aria-describedby={err(i, 'source_url') ? `${id}-url-err` : undefined}
                    />
                    {#if err(i, 'source_url')}<em class="error" id="{id}-url-err"
                            ><i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            {err(i, 'source_url')}</em
                        >{/if}
                </label>
                <label class="f">
                    <span>{$i18n.t('editor.events.description')}</span>
                    <input type="text" bind:value={ev.description} maxlength="1000" />
                </label>
            </div>
        </div>
    {/each}
    <button type="button" class="vff-btn secondary small add" onclick={add}>
        <i class="bi bi-calendar-plus" aria-hidden="true"></i>
        {$i18n.t('editor.events.add')}
    </button>
</div>

<style>
    .events {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
    }
    .event {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        padding: var(--vff-space-3);
        background: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .row {
        display: flex;
        gap: var(--vff-space-2);
        align-items: flex-end;
    }
    .row.two > * {
        flex: 1 1 0;
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
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .f.type {
        flex: 1 1 auto;
    }
    .f.precision {
        flex: 0 0 120px;
    }
    .f.date {
        flex: 1 1 auto;
    }
    input,
    select {
        width: 100%;
        min-height: 36px;
        padding: 6px 8px;
        background: var(--vff-bg);
        color: var(--vff-text);
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-size: var(--vff-fs-sm);
        font-family: var(--vff-font-heading);
    }
    input:focus-visible,
    select:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 0;
        border-color: var(--vff-focus);
    }
    input[aria-invalid='true'] {
        border-color: var(--vff-danger);
        box-shadow: 0 0 0 1px var(--vff-danger);
    }
    .req {
        color: var(--vff-danger);
    }
    .error {
        display: flex;
        gap: 4px;
        align-items: baseline;
        color: var(--vff-danger);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        font-style: normal;
    }
    .remove {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        padding: 0;
        background: var(--vff-bg);
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text-muted);
        cursor: pointer;
        transition:
            color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .remove:hover {
        color: var(--vff-danger);
        border-color: var(--vff-danger);
    }
    .add {
        align-self: flex-start;
    }
    @media (max-width: 600px) {
        .row {
            flex-wrap: wrap;
        }
        .f.precision,
        .f.date {
            flex: 1 1 45%;
        }
    }
</style>
