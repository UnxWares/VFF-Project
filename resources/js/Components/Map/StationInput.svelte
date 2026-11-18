<script>
    import Field from '$components/ui/Field.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Champ « gare » de l'itinéraire : saisie libre (nom de gare ou de ville) avec suggestions
     * de gares (GET /api/v1/search). Combobox ARIA : flèches pour parcourir, Entrée pour
     * choisir, Échap pour fermer ; le nombre de suggestions est annoncé.
     *
     * Props : id, label, placeholder, text (bindable, texte saisi), slug (bindable, gare
     * choisie dans la liste, null si texte libre), error (message relié au champ).
     */
    let {
        id,
        label,
        placeholder = '',
        text = $bindable(''),
        slug = $bindable(null),
        error = null,
    } = $props();

    const listId = $derived(`${id}-list`);
    let items = $state.raw([]);
    let open = $state(false);
    let active = $state(-1);
    let announcement = $state('');
    let timer = null;
    let controller = null;

    const shown = $derived(open && items.length > 0);

    function onInput(e) {
        text = e.currentTarget.value;
        slug = null;
        clearTimeout(timer);
        const query = text.trim();
        if (query.length < 2) {
            items = [];
            open = false;
            return;
        }
        timer = setTimeout(() => search(query), 200);
    }

    async function search(query) {
        controller?.abort();
        controller = new AbortController();
        try {
            const res = await fetch(`/api/v1/search?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const json = await res.json();
            items = (json.stations ?? []).slice(0, 8);
            active = -1;
            open = true;
            announcement = items.length
                ? $i18n.t('itinerary.suggestions', { count: items.length })
                : '';
        } catch (e) {
            if (e?.name !== 'AbortError') items = [];
        }
    }

    function pick(station) {
        text = station.name;
        slug = station.slug;
        items = [];
        open = false;
        active = -1;
        announcement = '';
    }

    function onKeydown(e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!items.length) return;
            e.preventDefault();
            open = true;
            const delta = e.key === 'ArrowDown' ? 1 : -1;
            active =
                active < 0
                    ? delta > 0
                        ? 0
                        : items.length - 1
                    : (active + delta + items.length) % items.length;
            document.getElementById(`${id}-opt-${active}`)?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && shown && active >= 0) {
            e.preventDefault();
            pick(items[active]);
        } else if (e.key === 'Escape' && shown) {
            e.preventDefault();
            open = false;
            active = -1;
        }
    }

    function place(station) {
        return station.city && station.city !== station.name ? station.city : null;
    }
</script>

<div class="station-input">
    <Field {id} {label} {error}>
        {#snippet children(attrs)}
            <div class="combo">
                <input
                    {...attrs}
                    class="vff-input"
                    type="text"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded={shown}
                    aria-controls={listId}
                    aria-activedescendant={shown && active >= 0 ? `${id}-opt-${active}` : undefined}
                    value={text}
                    {placeholder}
                    autocomplete="off"
                    spellcheck="false"
                    oninput={onInput}
                    onkeydown={onKeydown}
                    onfocus={() => (open = true)}
                    onblur={() => (open = false)}
                />
                <!-- Gare choisie dans la liste (et non texte libre) : coche discrète. -->
                {#if slug}<i class="bi bi-check2 picked" aria-hidden="true"></i>{/if}
                <ul
                    class="suggestions"
                    id={listId}
                    role="listbox"
                    aria-label={label}
                    hidden={!shown}
                >
                    {#each items as s, i (s.slug)}
                        <li
                            id={`${id}-opt-${i}`}
                            role="option"
                            tabindex="-1"
                            aria-selected={i === active}
                            class:active={i === active}
                            onmousedown={(e) => e.preventDefault()}
                            onclick={() => pick(s)}
                            onkeydown={() => {}}
                        >
                            <span class="name">{s.name}</span>
                            {#if place(s)}<span class="city">{place(s)}</span>{/if}
                        </li>
                    {/each}
                </ul>
            </div>
        {/snippet}
    </Field>
    <p class="vff-sr-only" aria-live="polite">{announcement}</p>
</div>

<style>
    .station-input {
        min-width: 0;
    }
    .combo {
        position: relative;
    }
    .picked {
        position: absolute;
        top: 22px;
        right: 12px;
        transform: translateY(-50%);
        color: var(--vff-success);
        pointer-events: none;
    }
    .suggestions {
        position: absolute;
        top: calc(100% + 4px);
        right: 0;
        left: 0;
        z-index: 20;
        max-height: 320px;
        margin: 0;
        padding: 4px 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        list-style: none;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
    }
    .suggestions[hidden] {
        display: none;
    }
    /* Nom et ville sur la même ligne de base, à la taille du champ. */
    li {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        min-height: 40px;
        padding: 9px 12px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
        cursor: pointer;
    }
    li:hover,
    li.active {
        background-color: var(--vff-bg-accent);
    }
    li.active {
        box-shadow: inset 3px 0 0 var(--vff-accent);
    }
    .name {
        min-width: 0;
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .city {
        flex-shrink: 0;
        max-width: 50%;
        color: var(--vff-text-muted);
        text-align: right;
    }
</style>
