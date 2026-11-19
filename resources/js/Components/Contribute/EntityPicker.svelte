<script>
    /**
     * Choix d'une ligne ou d'une gare existante par la recherche du site (/api/v1/search) : un champ
     * de recherche, puis une liste de résultats en boutons (Tab et Entrée au clavier). Une fois le
     * choix fait, seul le nom choisi reste affiché, avec un bouton pour en choisir un autre.
     *
     * Props : `kind` (`line` ou `station`), `id` (id du champ de recherche, cible des liens du
     * récapitulatif d'erreurs), `selected` (liable : { slug, name, code? , city? } ou null), `error`.
     */
    import { tick } from 'svelte';
    import Field from '$components/ui/Field.svelte';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';

    /** @type {{ kind: 'line'|'station', id: string, selected?: { slug: string, name: string, code?: string|null, city?: string|null }|null, error?: string|null }} */
    let { kind, id, selected = $bindable(null), error = null } = $props();

    const MIN_QUERY = 3;

    let query = $state('');
    /** @type {{ slug: string, name: string, code?: string|null, city?: string|null, department_code?: string|null }[]} */
    let results = $state.raw([]);
    /** idle | searching | done | failed */
    let status = $state('idle');
    /** @type {ReturnType<typeof setTimeout>|undefined} */
    let timer;
    /** @type {AbortController|undefined} */
    let controller;

    const label = $derived($i18n.t(`proposals.picker.${kind}_label`));
    const hint = $derived($i18n.t(`proposals.picker.${kind}_hint`));
    const statusText = $derived(
        status === 'searching'
            ? $i18n.t('proposals.picker.searching')
            : status === 'failed'
              ? $i18n.t('proposals.picker.failed')
              : status === 'done' && results.length === 0
                ? $i18n.t('proposals.picker.none')
                : '',
    );

    function onInput() {
        clearTimeout(timer);
        controller?.abort();
        const q = query.trim();
        if (q.length < MIN_QUERY) {
            results = [];
            status = 'idle';
            return;
        }
        status = 'searching';
        timer = setTimeout(() => search(q), 300);
    }

    /** @param {string} q */
    async function search(q) {
        controller = new AbortController();
        try {
            const res = await fetch(`/api/v1/search?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            results = (kind === 'line' ? data.lines : data.stations) ?? [];
            status = 'done';
        } catch (e) {
            if (e instanceof DOMException && e.name === 'AbortError') return;
            results = [];
            status = 'failed';
        }
    }

    /** @param {{ slug: string, name: string, code?: string|null, city?: string|null }} item */
    async function choose(item) {
        selected = {
            slug: item.slug,
            name: item.name,
            code: item.code ?? null,
            city: item.city ?? null,
        };
        await tick();
        document.getElementById(`${id}-chosen`)?.focus();
    }

    async function change() {
        selected = null;
        await tick();
        document.getElementById(id)?.focus();
    }
</script>

{#if selected}
    <div class="chosen" id="{id}-chosen" tabindex="-1">
        <p>
            <span class="muted"
                >{$i18n.t(`proposals.picker.chosen_${kind}`)}{colon($i18n.language)}</span
            ><strong>{selected.name}</strong>
            {#if selected.code}<span class="vff-plate">{selected.code}</span>{/if}
            {#if selected.city && selected.city !== selected.name}<span class="muted"
                    >({selected.city})</span
                >{/if}
        </p>
        <button type="button" class="vff-btn secondary small" onclick={change}>
            {$i18n.t('proposals.picker.change')}
        </button>
    </div>
{:else}
    <div class="picker">
        <Field {id} {label} {hint} {error} required>
            {#snippet children(attrs)}
                <input
                    {...attrs}
                    class="vff-input"
                    type="search"
                    autocomplete="off"
                    spellcheck="false"
                    enterkeyhint="search"
                    required
                    bind:value={query}
                    oninput={onInput}
                />
            {/snippet}
        </Field>
        <p class="status" aria-live="polite">{statusText}</p>
        {#if results.length}
            <ul class="results" aria-label={$i18n.t('proposals.picker.results')}>
                {#each results as item (item.slug)}
                    <li>
                        <button type="button" class="result" onclick={() => choose(item)}>
                            <span class="name">{item.name}</span>
                            {#if item.code}<span class="vff-plate">{item.code}</span>{/if}
                            {#if item.city && item.city !== item.name}<span class="muted"
                                    >{item.city}{#if item.department_code}
                                        ({item.department_code}){/if}</span
                                >{/if}
                        </button>
                    </li>
                {/each}
            </ul>
        {/if}
    </div>
{/if}

<style>
    .picker {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        min-width: 0;
    }
    .status {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .status:empty {
        display: none;
    }
    .results {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-1);
        max-height: 20rem;
        margin: 0;
        padding: 0;
        overflow-y: auto;
        list-style: none;
    }
    .result {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-3);
        width: 100%;
        min-height: 44px;
        padding: var(--vff-space-2) var(--vff-space-3);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        line-height: var(--vff-lh-ui);
        text-align: start;
        cursor: pointer;
        transition:
            border-color var(--vff-transition),
            background-color var(--vff-transition);
    }
    .result:hover {
        border-color: var(--vff-accent-ink);
        background-color: var(--vff-bg-accent);
    }
    .name {
        min-width: 0;
        overflow-wrap: anywhere;
        font-weight: 600;
    }
    .chosen {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
        padding: var(--vff-space-3) var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-subtle);
    }
    .chosen:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .chosen p {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-2);
        min-width: 0;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        overflow-wrap: anywhere;
    }
    .muted {
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
    }
    .vff-plate {
        font-size: var(--vff-fs-xs);
    }
</style>
