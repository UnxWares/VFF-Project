<script>
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    let { onPick = null } = $props();

    let q = $state('');
    let lines = $state([]);
    let stations = $state([]);
    let materials = $state([]);
    let isOpen = $state(false);
    let isLoading = $state(false);
    let activeIndex = $state(-1);
    let abortCtrl = null;
    let debounceTimer = null;

    const total = $derived(lines.length + stations.length + materials.length);

    async function fetchResults(query) {
        abortCtrl?.abort();
        abortCtrl = new AbortController();
        isLoading = true;
        try {
            const res = await fetch(`/api/v1/search?q=${encodeURIComponent(query)}`, {
                signal: abortCtrl.signal,
            });
            const data = await res.json();
            lines = data.lines ?? [];
            stations = data.stations ?? [];
            materials = data.materials ?? [];
            activeIndex = -1;
        } catch (e) {
            if (e.name !== 'AbortError') {
                lines = [];
                stations = [];
            }
        } finally {
            isLoading = false;
        }
    }

    function onInput() {
        clearTimeout(debounceTimer);
        const query = q.trim();
        if (query.length < 2) {
            lines = [];
            stations = [];
            materials = [];
            return;
        }
        debounceTimer = setTimeout(() => fetchResults(query), 250);
    }

    function pickLine(line) {
        isOpen = false;
        q = line.name;
        if (onPick) onPick({ type: 'line', ...line });
        else router.visit(`/lines/${line.slug}`);
    }

    function pickStation(station) {
        isOpen = false;
        q = station.name;
        if (onPick) onPick({ type: 'station', ...station });
    }

    function pickMaterial(material) {
        isOpen = false;
        q = material.name;
        router.visit(`/materials/${material.slug}`);
    }

    function pickByIndex(i) {
        if (i < lines.length) pickLine(lines[i]);
        else if (i < lines.length + stations.length) pickStation(stations[i - lines.length]);
        else pickMaterial(materials[i - lines.length - stations.length]);
    }

    function onKeydown(e) {
        if (!isOpen || total === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % total;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = activeIndex <= 0 ? total - 1 : activeIndex - 1;
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            pickByIndex(activeIndex);
        } else if (e.key === 'Escape') {
            isOpen = false;
        }
    }

    function clear() {
        q = '';
        lines = [];
        stations = [];
        materials = [];
        isOpen = false;
    }
</script>

<div class="search-box" class:open={isOpen && total > 0}>
    <i class="bi bi-search search-icon"></i>
    <input
        type="search"
        bind:value={q}
        oninput={onInput}
        onfocus={() => (isOpen = true)}
        onblur={() => setTimeout(() => (isOpen = false), 150)}
        onkeydown={onKeydown}
        placeholder={$i18n.t('maps.search.placeholder')}
        aria-label={$i18n.t('maps.search.placeholder')}
        autocomplete="off"
    />
    {#if q}
        <button
            type="button"
            class="clear-btn"
            onmousedown={clear}
            aria-label={$i18n.t('common.close')}
        >
            <i class="bi bi-x"></i>
        </button>
    {/if}
    {#if isLoading}
        <i class="bi bi-arrow-repeat spin"></i>
    {/if}

    {#if isOpen && total > 0}
        <div class="results" role="listbox">
            {#if lines.length}
                <div class="group-label">{$i18n.t('maps.search.lines')}</div>
                {#each lines as l, i}
                    <button
                        type="button"
                        class="result"
                        class:active={activeIndex === i}
                        onmousedown={() => pickLine(l)}
                    >
                        <i class="bi bi-bezier"></i>
                        <div class="result-text">
                            <strong>{l.name}</strong>
                            {#if l.code}<span class="code">{l.code}</span>{/if}
                        </div>
                    </button>
                {/each}
            {/if}
            {#if stations.length}
                <div class="group-label">{$i18n.t('maps.search.stations')}</div>
                {#each stations as s, i}
                    <button
                        type="button"
                        class="result"
                        class:active={activeIndex === lines.length + i}
                        onmousedown={() => pickStation(s)}
                    >
                        <i class="bi bi-pin-map-fill"></i>
                        <div class="result-text">
                            <strong>{s.name}</strong>
                            {#if s.city}<span class="muted">{s.city}</span>{/if}
                        </div>
                    </button>
                {/each}
            {/if}
            {#if materials.length}
                <div class="group-label">{$i18n.t('maps.search.materials')}</div>
                {#each materials as m, i}
                    <button
                        type="button"
                        class="result"
                        class:active={activeIndex === lines.length + stations.length + i}
                        onmousedown={() => pickMaterial(m)}
                    >
                        <i class="bi bi-train-front-fill"></i>
                        <div class="result-text">
                            <strong>{m.name}</strong>
                            {#if m.builder}<span class="muted">{m.builder}</span>{/if}
                        </div>
                    </button>
                {/each}
            {/if}
        </div>
    {/if}
</div>

<style>
    .search-box {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 0 10px;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-display);
        font-size: 13px;
        color: var(--vff-text);
        box-shadow: 0 4px 12px var(--vff-shadow);
        min-width: 220px;
        max-width: 320px;
        transition: all var(--vff-transition);
    }
    .search-box.open {
        border-radius: var(--vff-radius-md) var(--vff-radius-md) 0 0;
        border-bottom-color: transparent;
    }
    .search-box:focus-within {
        border-color: var(--vff-accent);
    }

    .search-icon {
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    input {
        flex: 1;
        background: transparent;
        border: 0;
        padding: 9px 0;
        font-family: inherit;
        font-size: inherit;
        color: inherit;
        outline: none;
        min-width: 0;
    }
    input::placeholder {
        color: var(--vff-text-muted);
    }

    .clear-btn {
        background: transparent;
        border: 0;
        color: var(--vff-text-muted);
        cursor: pointer;
        font-size: 16px;
        padding: 4px;
        border-radius: 50%;
        transition: all var(--vff-transition);
    }
    .clear-btn:hover {
        color: var(--vff-text);
        background-color: var(--vff-bg-muted);
    }

    .spin {
        color: var(--vff-accent);
        font-size: 14px;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .results {
        position: absolute;
        top: 100%;
        left: -1px;
        right: -1px;
        max-height: 380px;
        overflow-y: auto;
        background-color: var(--vff-bg);
        border: 1px solid var(--vff-border);
        border-top: 0;
        border-radius: 0 0 var(--vff-radius-md) var(--vff-radius-md);
        box-shadow: 0 12px 32px var(--vff-shadow-strong);
        z-index: 20;
    }

    .group-label {
        padding: 8px 14px 4px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--vff-text-muted);
        font-weight: 600;
    }

    .result {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 8px 14px;
        background: transparent;
        border: 0;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
        font-size: inherit;
        color: var(--vff-text);
        transition: background-color var(--vff-transition);
    }
    .result:hover,
    .result.active {
        background-color: var(--vff-accent-soft);
    }
    .result i {
        color: var(--vff-accent);
        font-size: 14px;
        flex-shrink: 0;
    }
    .result-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
    }
    .result-text strong {
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .result-text .code {
        font-size: 11px;
        color: var(--vff-accent);
        font-family: var(--vff-font-secondary, monospace);
    }
    .result-text .muted {
        font-size: 11px;
        color: var(--vff-text-muted);
    }
</style>
