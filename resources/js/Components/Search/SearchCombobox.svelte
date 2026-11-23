<script>
    import { router } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { fitWords } from '$lib/fitWords.js';
    import { searchGroups, searchPageHref, stepIndex } from '$lib/siteSearch.js';

    /**
     * Recherche de tout le site : rubriques (Carte, Lignes, Gares…), lignes, gares et matériel
     * roulant, accidents, termes du glossaire et communes qui ont du rail (GET /api/v1/search), puis « Toutes les lignes / gares / accidents contenant… » vers les annuaires.
     * Choisir un résultat ouvre directement sa page.
     *
     * Combobox ARIA 1.2 : le focus reste dans le champ, les flèches parcourent les résultats
     * (aria-activedescendant), Entrée ouvre le résultat actif, sinon la page de tous les résultats
     * (/search?q=…, deux caractères au moins ; la première rubrique pour un texte plus court). Le nombre de
     * résultats, l'absence de résultat et les erreurs sont annoncés par une zone d'état présente
     * dès le départ.
     *
     * Props :
     * - `floating` : liste en panneau flottant sous le champ (accueil) ; sinon dans le flux (fenêtre
     *   de recherche de l'en-tête). En flottant, Échap ferme la liste puis efface le texte ; dans la
     *   fenêtre, Échap est laissé à la fenêtre, qui se ferme.
     * - `suggestions` : champ vide = liste des rubriques du site.
     * - `autofocus` : le champ prend le focus à l'ouverture de la fenêtre.
     * - `label`, `labelHidden` : libellé du champ (visible ou lu seulement).
     * - `onnavigate` : appelé juste avant d'ouvrir une page (la fenêtre se ferme).
     */
    /** @type {{ floating?: boolean, suggestions?: boolean, autofocus?: boolean, label?: string, labelHidden?: boolean, onnavigate?: () => void }} */
    let {
        floating = false,
        suggestions = false,
        autofocus = false,
        label = undefined,
        labelHidden = false,
        onnavigate = undefined,
    } = $props();

    const uid = $props.id();
    const inputId = `${uid}-input`;
    const listId = `${uid}-list`;

    let q = $state('');
    /** Réponse vide de GET /api/v1/search (avant la saisie, après une erreur). */
    const emptyResults = () => ({
        lines: [],
        stations: [],
        materials: [],
        accidents: [],
        communes: [],
        pk_positions: [],
        glossary: [],
    });
    let results = $state.raw(emptyResults());
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

    // Texte indicatif : sur un champ étroit, il s'arrête à la fin d'un mot au lieu d'être coupé net.
    let inputEl = $state(null);
    let inputWidth = $state(0);
    const placeholderText = $derived.by(() => {
        const full = t('nav.search.placeholder');
        if (!inputEl || !inputWidth) return full;
        const style = getComputedStyle(inputEl);
        const ctx = document.createElement('canvas').getContext('2d');
        if (!ctx) return full;
        ctx.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
        return fitWords(full, inputWidth, (text) => ctx.measureText(text).width);
    });
    $effect(() => {
        if (!inputEl) return;
        const observer = new ResizeObserver(() => {
            const style = getComputedStyle(inputEl);
            inputWidth =
                inputEl.clientWidth -
                parseFloat(style.paddingLeft) -
                parseFloat(style.paddingRight);
        });
        observer.observe(inputEl);
        return () => observer.disconnect();
    });

    const query = $derived(q.trim());
    // Groupes affichés (rubriques, lignes, gares, matériel, annuaires) : lib/siteSearch.js.
    const groups = $derived(
        searchGroups({
            query,
            suggestions,
            results,
            // Liens vers les annuaires filtrés : seulement quand la recherche a répondu (ou échoué).
            searched: query === searched && (phase === 'done' || phase === 'error'),
            t,
            idPrefix: uid,
            lang: $i18n.language,
        }),
    );
    const options = $derived(groups.flatMap((g) => g.items));
    // Nombre de vrais résultats (sans les rubriques ni les liens vers les annuaires).
    const resultCount = $derived(
        results.lines.length +
            results.stations.length +
            results.materials.length +
            results.accidents.length +
            results.communes.length +
            results.pk_positions.length +
            results.glossary.length,
    );

    // Rien ne correspond mot pour mot : tous les résultats sont des noms approchés (fautes de frappe).
    const approximateOnly = $derived(
        resultCount > 0 &&
            [
                ...results.lines,
                ...results.stations,
                ...results.materials,
                ...results.accidents,
                ...results.communes,
            ].every((r) => r.approximate),
    );
    const listShown = $derived(options.length > 0 && (!floating || isOpen));
    // Message visible : aucun résultat ou erreur (sinon lu seulement).
    const messageShown = $derived(
        (!floating || isOpen) &&
            query.length >= 2 &&
            query === searched &&
            (phase === 'error' || (phase === 'done' && (resultCount === 0 || approximateOnly))),
    );
    const statusText = $derived(
        phase === 'loading'
            ? t('nav.search.loading')
            : phase === 'error'
              ? t('nav.search.error')
              : phase === 'done'
                ? approximateOnly
                    ? t('nav.search.approximate', { query: searched })
                    : resultCount
                      ? t('nav.search.results', { count: resultCount })
                      : t('nav.search.no_results', { query: searched })
                : '',
    );

    async function fetchResults(text) {
        abortCtrl?.abort();
        abortCtrl = new AbortController();
        phase = 'loading';
        try {
            const res = await fetch(`/api/v1/search?q=${encodeURIComponent(text)}`, {
                headers: { Accept: 'application/json' },
                signal: abortCtrl.signal,
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            results = {
                lines: data.lines ?? [],
                stations: data.stations ?? [],
                materials: data.materials ?? [],
                accidents: data.accidents ?? [],
                communes: data.communes ?? [],
                pk_positions: data.pk_positions ?? [],
                glossary: data.glossary ?? [],
            };
            phase = 'done';
        } catch (e) {
            if (e.name === 'AbortError') return;
            results = emptyResults();
            phase = 'error';
        }
        searched = text;
        activeIndex = -1;
        isOpen = true;
    }

    function reset() {
        clearTimeout(debounceTimer);
        abortCtrl?.abort();
        results = emptyResults();
        phase = 'idle';
        searched = '';
        activeIndex = -1;
    }

    function onInput() {
        isOpen = true;
        activeIndex = -1;
        if (query.length < 2) {
            reset();
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchResults(query), 250);
    }

    /** Ouvre la page de l'option (Ctrl ou Cmd : dans un nouvel onglet, comme un lien). */
    function pick(option, event = null) {
        if (!option) return;
        if (event && (event.ctrlKey || event.metaKey)) {
            window.open(option.href, '_blank', 'noopener');
            return;
        }
        isOpen = false;
        activeIndex = -1;
        onnavigate?.();
        router.visit(option.href);
    }

    function move(delta) {
        if (!options.length) return;
        isOpen = true;
        activeIndex = stepIndex(activeIndex, delta, options.length);
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
            if (listShown && activeIndex >= 0) {
                e.preventDefault();
                pick(options[activeIndex], e);
            } else if (query.length >= 2) {
                e.preventDefault();
                pick({ href: searchPageHref(query) }, e);
            } else if (listShown) {
                e.preventDefault();
                pick(options[0], e);
            }
        } else if (e.key === 'Escape' && floating) {
            if (isOpen && (listShown || messageShown)) {
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
        document.getElementById(inputId)?.focus();
    }
</script>

<div class={['search-combobox', { floating, open: listShown || messageShown }]}>
    {#if label}
        <label class={['field-label', { 'vff-sr-only': labelHidden }]} for={inputId}>{label}</label>
    {/if}
    <div class="field">
        <i class="bi bi-search field-icon" aria-hidden="true"></i>
        <!-- svelte-ignore a11y_autofocus (fenêtre de recherche ouverte à la demande : le champ est
             la seule chose à faire) -->
        <input
            id={inputId}
            type="search"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded={listShown}
            aria-controls={listId}
            aria-activedescendant={listShown && activeIndex >= 0
                ? options[activeIndex]?.id
                : undefined}
            aria-label={label ? undefined : t('nav.search.label')}
            bind:value={q}
            oninput={onInput}
            onfocus={() => (isOpen = true)}
            onblur={() => (isOpen = false)}
            onkeydown={onKeydown}
            placeholder={placeholderText}
            bind:this={inputEl}
            autocomplete="off"
            spellcheck="false"
            enterkeyhint="go"
            {autofocus}
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
                aria-label={t('nav.search.clear')}
                title={t('nav.search.clear')}
            >
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        {/if}
    </div>

    <div
        class="results"
        id={listId}
        role="listbox"
        aria-label={t('nav.search.label')}
        hidden={!listShown}
    >
        {#each groups as g (g.type)}
            <div role="group" aria-labelledby={g.id}>
                <p class="group-label" id={g.id}>{g.label}</p>
                {#each g.items as o (o.id)}
                    <!-- Motif combobox de l'APG : le clavier reste dans le champ (flèches, Entrée), les
                         options ne prennent pas le focus ; la souris les choisit d'un clic. -->
                    <!-- svelte-ignore a11y_click_events_have_key_events -->
                    <div
                        id={o.id}
                        role="option"
                        tabindex="-1"
                        aria-selected={activeIndex === o.index}
                        class={[
                            'result',
                            { active: activeIndex === o.index, more: g.type === 'more' },
                        ]}
                        onmousedown={(e) => e.preventDefault()}
                        onclick={(e) => pick(o, e)}
                    >
                        <i class="bi {o.icon}" aria-hidden="true"></i>
                        <span class="result-name">{o.name}</span>
                        {#if o.plate}
                            <span class="vff-plate result-code">{o.plate}</span>
                        {:else if o.detail}
                            <span class="result-detail">{o.detail}</span>
                        {/if}
                    </div>
                {/each}
            </div>
        {/each}
    </div>

    <p class={['search-status', { shown: messageShown }]} role="status">{statusText}</p>
</div>

<style>
    .search-combobox {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        min-height: 0;
        font-family: var(--vff-font-heading);
        color: var(--vff-text);
    }
    .field-label {
        font-size: var(--vff-fs-sm);
        font-weight: 600;
    }
    .field {
        position: relative;
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: 10px;
        min-height: 52px;
        padding: 0 6px 0 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg);
        transition: border-color var(--vff-transition);
    }
    .field:hover {
        border-color: var(--vff-text-muted);
    }
    .field:focus-within {
        border-color: var(--vff-focus);
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: 0;
    }
    .field-icon {
        color: var(--vff-text-muted);
        font-size: 18px;
    }
    input {
        flex: 1;
        min-width: 0;
        padding: 12px 0;
        border: 0;
        background: transparent;
        color: inherit;
        font-family: inherit;
        font-size: 1.0625rem;
    }
    /* Le contour est porté par le cadre (focus-within). */
    input:focus-visible {
        outline: none;
    }
    input::placeholder {
        color: var(--vff-text-muted);
        opacity: 1;
    }
    /* Une seule croix d'effacement : celle du site. */
    input::-webkit-search-cancel-button,
    input::-webkit-search-decoration {
        appearance: none;
    }
    .clear-btn {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: 0;
        border-radius: var(--vff-radius-sm);
        background: transparent;
        color: var(--vff-text-muted);
        font-size: 15px;
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
        font-size: 16px;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Résultats : dans le flux (fenêtre de recherche), ou en panneau flottant (accueil). */
    .results {
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    .results[hidden] {
        display: none;
    }
    .floating .results,
    .floating .search-status.shown {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        left: 0;
        z-index: var(--vff-z-overlay);
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        box-shadow: var(--vff-shadow-popover);
    }
    .floating .results {
        max-height: min(420px, 60vh);
        padding: 4px 0;
    }
    .group-label {
        margin: 0;
        padding: 12px 14px 4px;
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .result {
        display: flex;
        align-items: baseline;
        gap: 12px;
        min-height: 44px;
        padding: 11px 14px;
        border-radius: var(--vff-radius-sm);
        font-size: var(--vff-fs-md);
        line-height: var(--vff-lh-ui);
        cursor: pointer;
    }
    .floating .result {
        margin-inline: 4px;
    }
    .result:hover,
    .result.active {
        background-color: var(--vff-bg-accent);
    }
    /* Option active : fond teinté ET nom souligné (pas la couleur seule). */
    .result.active .result-name {
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 0.2em;
    }
    .result .bi {
        flex-shrink: 0;
        width: 18px;
        color: var(--vff-text-muted);
        font-size: 15px;
        text-align: center;
    }
    .result.active .bi {
        color: var(--vff-accent-ink);
    }
    .result-name {
        flex: 1;
        min-width: 0;
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .result.more .result-name {
        font-weight: 400;
    }
    .result-detail {
        flex-shrink: 0;
        max-width: 45%;
        font-size: var(--vff-fs-sm);
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
        position: static;
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
    .floating .search-status.shown {
        position: absolute;
    }
</style>
