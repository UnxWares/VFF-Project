<script>
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Recherche de la carte : lignes, gares et matériel roulant (GET /api/v1/search).
     * Combobox ARIA : le focus reste dans le champ, les flèches parcourent les résultats
     * (aria-activedescendant), Entrée choisit, Échap ferme la liste puis efface le texte.
     * Le nombre de résultats, l'absence de résultat et les erreurs sont annoncés (zone d'état
     * toujours présente) et affichés sous le champ.
     *
     * Props : onPick({ type, ...résultat }) ; sans onPick, une ligne ouvre sa fiche.
     */
    let { onPick = null } = $props();

    const uid = $props.id();
    const inputId = `${uid}-input`;
    const listId = `${uid}-list`;

    let q = $state('');
    let results = $state.raw({ lines: [], stations: [], materials: [] });
    // idle | loading | done | error
    let phase = $state('idle');
    let searched = $state('');
    let isOpen = $state(false);
    let activeIndex = $state(-1);
    let abortCtrl = null;
    let debounceTimer = null;

    /** Traduction (toujours une chaîne : i18next la type aussi en objet). */
    const t = (/** @type {string} */ key, /** @type {Record<string, unknown>} */ params = {}) =>
        String($i18n.t(key, params));

    // Résultats à plat, avec leur identifiant d'option (aria-activedescendant).
    const options = $derived(
        [
            ...results.lines.map((item) => ({ type: 'line', item })),
            ...results.stations.map((item) => ({ type: 'station', item })),
            ...results.materials.map((item) => ({ type: 'material', item })),
        ].map((o, index) => ({ ...o, index, id: `${uid}-opt-${index}` })),
    );
    const groups = $derived(
        [
            { type: 'line', icon: 'bi-bezier', label: t('maps.search.lines') },
            { type: 'station', icon: 'bi-pin-map-fill', label: t('maps.search.stations') },
            { type: 'material', icon: 'bi-train-front-fill', label: t('maps.search.materials') },
        ]
            .map((g) => ({ ...g, items: options.filter((o) => o.type === g.type) }))
            .filter((g) => g.items.length),
    );
    const listShown = $derived(isOpen && options.length > 0);
    // Message visible sous le champ : aucun résultat ou erreur (sinon lu seulement).
    const messageShown = $derived(
        isOpen && (phase === 'error' || (phase === 'done' && options.length === 0)),
    );
    const statusText = $derived(
        phase === 'loading'
            ? t('maps.search.loading')
            : phase === 'error'
              ? t('maps.search.error')
              : phase === 'done'
                ? options.length
                    ? t('maps.search.results', { count: options.length })
                    : t('maps.search.no_results', { query: searched })
                : '',
    );

    async function fetchResults(query) {
        abortCtrl?.abort();
        abortCtrl = new AbortController();
        phase = 'loading';
        try {
            const res = await fetch(`/api/v1/search?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: abortCtrl.signal,
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            results = {
                lines: data.lines ?? [],
                stations: data.stations ?? [],
                materials: data.materials ?? [],
            };
            searched = query;
            activeIndex = -1;
            phase = 'done';
            isOpen = true;
        } catch (e) {
            if (e.name === 'AbortError') return;
            results = { lines: [], stations: [], materials: [] };
            phase = 'error';
            isOpen = true;
        }
    }

    function reset() {
        clearTimeout(debounceTimer);
        abortCtrl?.abort();
        results = { lines: [], stations: [], materials: [] };
        phase = 'idle';
        activeIndex = -1;
    }

    function onInput() {
        const query = q.trim();
        if (query.length < 2) {
            reset();
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchResults(query), 250);
    }

    function pick(option) {
        isOpen = false;
        activeIndex = -1;
        q = option.item.name;
        if (option.type === 'material') {
            router.visit(`/materials/${option.item.slug}`);
        } else if (onPick) {
            onPick({ ...option.item, type: option.type });
        } else if (option.type === 'line') {
            router.visit(`/lines/${option.item.slug}`);
        }
    }

    function move(delta) {
        if (!options.length) return;
        isOpen = true;
        activeIndex =
            activeIndex < 0
                ? delta > 0
                    ? 0
                    : options.length - 1
                : (activeIndex + delta + options.length) % options.length;
        document.getElementById(options[activeIndex].id)?.scrollIntoView({ block: 'nearest' });
    }

    function onKeydown(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            move(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            move(-1);
        } else if (e.key === 'Enter') {
            if (!listShown) return;
            e.preventDefault();
            pick(options[activeIndex >= 0 ? activeIndex : 0]);
        } else if (e.key === 'Escape') {
            if (isOpen && (options.length || messageShown)) {
                e.preventDefault();
                isOpen = false;
                activeIndex = -1;
            } else if (q) {
                e.preventDefault();
                clear();
            }
        }
    }

    function clear() {
        q = '';
        reset();
        isOpen = false;
        document.getElementById(inputId)?.focus();
    }

    function lineDetail(line) {
        return line.code ?? null;
    }

    function stationDetail(station) {
        const place = station.city && station.city !== station.name ? station.city : null;
        const dept = station.department_code ? `(${station.department_code})` : null;
        return [place, dept].filter(Boolean).join(' ') || null;
    }
</script>

<div class="search-box" class:open={listShown || messageShown}>
    <label class="vff-sr-only" for={inputId}>{t('maps.search.label')}</label>
    <i class="bi bi-search search-icon" aria-hidden="true"></i>
    <input
        id={inputId}
        type="search"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={listShown}
        aria-controls={listId}
        aria-activedescendant={listShown && activeIndex >= 0 ? options[activeIndex]?.id : undefined}
        bind:value={q}
        oninput={onInput}
        onfocus={() => (isOpen = true)}
        onblur={() => (isOpen = false)}
        onkeydown={onKeydown}
        placeholder={t('maps.search.placeholder')}
        autocomplete="off"
        spellcheck="false"
        enterkeyhint="search"
    />
    {#if phase === 'loading'}
        <i class="bi bi-arrow-repeat spin" aria-hidden="true"></i>
    {/if}
    {#if q}
        <button
            type="button"
            class="clear-btn"
            onmousedown={(e) => e.preventDefault()}
            onclick={clear}
            aria-label={t('maps.search.clear')}
            title={t('maps.search.clear')}
        >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    {/if}

    <div
        class="results"
        id={listId}
        role="listbox"
        aria-label={t('maps.search.label')}
        hidden={!listShown}
    >
        {#each groups as g (g.type)}
            <div role="group" aria-label={g.label}>
                <p class="group-label" aria-hidden="true">{g.label}</p>
                {#each g.items as o (o.id)}
                    {@const detail =
                        o.type === 'line'
                            ? lineDetail(o.item)
                            : o.type === 'station'
                              ? stationDetail(o.item)
                              : (o.item.builder ?? null)}
                    <div
                        id={o.id}
                        role="option"
                        tabindex="-1"
                        aria-selected={activeIndex === o.index}
                        class="result"
                        class:active={activeIndex === o.index}
                        onmousedown={(e) => e.preventDefault()}
                        onclick={() => pick(o)}
                        onkeydown={() => {}}
                    >
                        <i class="bi {g.icon}" aria-hidden="true"></i>
                        <span class="result-name">{o.item.name}</span>
                        {#if detail}
                            <span
                                class={o.type === 'line'
                                    ? 'vff-plate result-code'
                                    : 'result-detail'}>{detail}</span
                            >
                        {/if}
                    </div>
                {/each}
            </div>
        {/each}
    </div>

    <p class="search-status" class:shown={messageShown} role="status">{statusText}</p>
</div>

<style>
    .search-box {
        position: relative;
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 44px;
        padding: 0 4px 0 12px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
    }
    .search-box:focus-within {
        border-color: var(--vff-focus);
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 0;
    }
    .search-icon {
        color: var(--vff-text-muted);
        font-size: 15px;
    }
    input {
        flex: 1;
        min-width: 0;
        padding: 10px 0;
        border: 0;
        background: transparent;
        color: inherit;
        font-family: inherit;
        font-size: var(--vff-fs-md);
    }
    /* Le contour est porté par le cadre (focus-within). */
    input:focus-visible {
        outline: none;
    }
    input::placeholder {
        color: var(--vff-text-muted);
        opacity: 1;
    }
    /* Une seule croix d'effacement : celle du site (la croix native de type=search est masquée). */
    input::-webkit-search-cancel-button,
    input::-webkit-search-decoration {
        appearance: none;
    }

    .clear-btn {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text-muted);
        font-size: 14px;
        cursor: pointer;
        transition:
            background-color var(--vff-transition),
            color var(--vff-transition);
    }
    .clear-btn:hover {
        background-color: var(--vff-bg-muted);
        color: var(--vff-text);
    }
    .spin {
        color: var(--vff-text-muted);
        font-size: 15px;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Liste et message : panneau flottant sous le champ. */
    .results,
    .search-status.shown {
        position: absolute;
        top: calc(100% + 4px);
        right: -1px;
        left: -1px;
        z-index: 20;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
    }
    .results {
        max-height: min(380px, 60vh);
        padding: 4px 0;
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    .results[hidden] {
        display: none;
    }
    .group-label {
        margin: 0;
        padding: 10px 14px 4px;
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .result {
        display: flex;
        align-items: baseline;
        gap: 10px;
        min-height: 40px;
        padding: 9px 14px;
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        cursor: pointer;
    }
    .result:hover,
    .result.active {
        background-color: var(--vff-bg-accent);
    }
    .result.active {
        box-shadow: inset 3px 0 0 var(--vff-accent);
    }
    .result .bi {
        flex-shrink: 0;
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    .result-name {
        flex: 1;
        min-width: 0;
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .result-detail {
        flex-shrink: 0;
        max-width: 45%;
        color: var(--vff-text-muted);
        text-align: right;
        overflow-wrap: anywhere;
    }
    .result-code {
        flex-shrink: 0;
        font-size: var(--vff-fs-xs);
    }

    /* Zone d'état : lue seulement, sauf « aucun résultat » et erreur, affichés. */
    .search-status {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: 0;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }
    .search-status.shown {
        width: auto;
        height: auto;
        padding: 12px 14px;
        overflow: visible;
        clip-path: none;
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text);
        white-space: normal;
    }
</style>
