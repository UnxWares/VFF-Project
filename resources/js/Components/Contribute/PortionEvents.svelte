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
        <div class="event">
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
                >
                    <i class="bi bi-trash3"></i>
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
                    <span>{$i18n.t('editor.events.date')}</span>
                    {#if ev.precision === 'day'}
                        <input type="date" bind:value={ev.date} min="1750-01-01" required />
                    {:else if ev.precision === 'month'}
                        <input
                            type="month"
                            bind:value={ev.date}
                            min="1750-01"
                            placeholder={$i18n.t('editor.events.month_placeholder')}
                            required
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
                        />
                    {/if}
                    {#if err(i, 'date')}<em class="error">{err(i, 'date')}</em>{/if}
                </label>
            </div>
            <label class="f">
                <span>{$i18n.t('editor.events.source')} <b class="req">*</b></span>
                <input
                    type="text"
                    bind:value={ev.source}
                    maxlength="500"
                    placeholder={$i18n.t('editor.events.source_placeholder')}
                    required
                />
                {#if err(i, 'source')}<em class="error">{err(i, 'source')}</em>{/if}
            </label>
            <div class="row two">
                <label class="f">
                    <span>{$i18n.t('editor.events.source_url')}</span>
                    <input
                        type="url"
                        bind:value={ev.source_url}
                        maxlength="500"
                        placeholder="https://"
                    />
                    {#if err(i, 'source_url')}<em class="error">{err(i, 'source_url')}</em>{/if}
                </label>
                <label class="f">
                    <span>{$i18n.t('editor.events.description')}</span>
                    <input type="text" bind:value={ev.description} maxlength="1000" />
                </label>
            </div>
        </div>
    {/each}
    <button type="button" class="add" onclick={add}>
        <i class="bi bi-calendar-plus"></i>
        {$i18n.t('editor.events.add')}
    </button>
</div>

<style>
    .events {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .event {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 10px;
        background: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
    }
    .row {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }
    .row.two > * {
        flex: 1 1 0;
    }
    .f {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }
    .f > span {
        font-family: var(--vff-font-display);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--vff-text-muted);
    }
    .f.type {
        flex: 1 1 auto;
    }
    .f.precision {
        flex: 0 0 110px;
    }
    .f.date {
        flex: 1 1 auto;
    }
    input,
    select {
        width: 100%;
        padding: 6px 8px;
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        font-size: 13px;
        font-family: var(--vff-font-body);
    }
    input:focus,
    select:focus {
        outline: none;
        border-color: var(--vff-accent);
        box-shadow: 0 0 0 2px var(--vff-accent-soft);
    }
    .req {
        color: #c53030;
    }
    .error {
        color: #c53030;
        font-size: 12px;
        font-style: normal;
    }
    .remove {
        flex: 0 0 auto;
        padding: 6px 8px;
        background: transparent;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        color: var(--vff-text-muted);
        cursor: pointer;
    }
    .remove:hover {
        color: #c53030;
        border-color: #c53030;
    }
    .add {
        align-self: flex-start;
        display: inline-flex;
        gap: 6px;
        align-items: center;
        padding: 6px 12px;
        background: transparent;
        border: 1px dashed var(--vff-accent);
        border-radius: var(--vff-radius-pill);
        color: var(--vff-accent);
        font-family: var(--vff-font-display);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    @media (max-width: 520px) {
        .row {
            flex-wrap: wrap;
        }
        .f.precision,
        .f.date {
            flex: 1 1 45%;
        }
    }
</style>
