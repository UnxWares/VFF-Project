<script>
    import { onMount, tick } from 'svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import { i18n } from '$lib/i18n.js';
    import { firstOfMonth, formatLocale } from '$lib/typo.js';
    import {
        mediaHeading,
        mediaLicenseHref,
        mediaLicenseLabel,
        mediaSourceHref,
    } from '$lib/mediaCredits.js';
    import { conciseAlt } from '$lib/altText.js';
    import { focusAfterStep, navDisabled } from '$lib/galleryNav.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';

    /**
     * Galerie « Photos & archives » d'une fiche : ligne, gare ou série de matériel.
     *
     * Données : `endpoint`, l'un des points d'accès de galerie de l'API (paginés, triés par
     * époque, même réponse) : /api/v1/lines/{slug}/media, /stations/{slug}/media,
     * /materials/{slug}/media. Seuls les médias publiés y figurent : une image proposée et
     * pas encore validée n'est jamais dans la table `media`.
     * Les images externes (Wikimedia Commons…) sont affichées par lien direct,
     * jamais copiées : chaque vignette porte SON crédit (auteur, licence telle
     * que déclarée par la source + lien, lien vers la page du fichier, mention
     * « contribution validée » pour une image proposée par un contributeur). Le
     * domaine public est aussi crédité (source citée).
     *
     * Titre affiché : la légende de la source quand elle existe (le titre de Commons est
     * souvent un nom de fichier). Filtres et tri seulement quand ils servent (plusieurs
     * images, plusieurs types ou époques). Se masque entièrement si la fiche n'a aucun média ;
     * `count` (nombre connu du serveur) à 0 : rien n'est chargé ni affiché.
     */
    // `intro` : phrase sous le titre ; `onitems` : reçoit les images chargées (données structurées, SeoHead).
    let { endpoint, intro = null, count = null, perPage = 24, onitems = null } = $props();

    let items = $state.raw([]);
    let total = $state(0);
    let page = $state(0);
    let lastPage = $state(1);
    let facets = $state.raw(null);
    let loading = $state(false);
    let error = $state(false);

    let sort = $state('oldest');
    let type = $state('');
    let period = $state('');

    let current = $state(null); // index dans items de l'image ouverte
    let dialogEl = $state(null);
    let previousBtn = $state(null);
    let nextBtn = $state(null);
    let imageLink = $state(null);

    const TYPES = ['photo', 'postcard', 'map', 'plan', 'document'];
    const PERIODS = ['pre1900', '1900_1944', '1945_1969', '1970_1999', 'since2000', 'undated'];

    const t = (key, opts) => String($i18n.t(key, opts));
    const lang = $derived($i18n.language ?? 'fr');

    const grandTotal = $derived(
        facets ? Object.values(facets.types ?? {}).reduce((a, b) => a + b, 0) : 0,
    );
    const typeKeys = $derived(TYPES.filter((k) => facets?.types?.[k]));
    const periodKeys = $derived(PERIODS.filter((k) => facets?.periods?.[k]));
    // Un filtre qui ne trie rien (un seul type, une seule époque, peu d'images) est masqué.
    const showTypes = $derived(grandTotal > 6 && typeKeys.length > 1);
    const showPeriods = $derived(grandTotal > 6 && periodKeys.length > 1);
    const showSort = $derived(grandTotal > 1);
    // Mention « hébergées par … en lien direct » : seulement les fournisseurs en lien direct (pas les envois ni les copies locales).
    const providers = $derived(
        Object.keys(facets?.providers ?? {}).filter(
            (p) => p !== 'upload' && p !== 'argentanwebferro',
        ),
    );
    const hasMore = $derived(page < lastPage);
    const active = $derived(current !== null ? items[current] : null);
    const single = $derived(total <= 1);
    const disabled = $derived(
        current !== null
            ? navDisabled(current, items.length, hasMore)
            : { previous: true, next: true },
    );

    async function load(reset = false) {
        if (loading) return;
        loading = true;
        error = false;
        const next = reset ? 1 : page + 1;
        const query = { page: String(next), per_page: String(perPage), sort };
        if (type) query.type = type;
        if (period) query.period = period;
        const params = new URLSearchParams(query);
        try {
            const res = await fetch(`${endpoint}?${params}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            items = reset ? json.data : [...items, ...json.data];
            total = json.meta.total;
            page = json.meta.current_page;
            lastPage = json.meta.last_page;
            facets = json.facets;
            onitems?.(items);
        } catch {
            error = true;
        } finally {
            loading = false;
        }
    }

    function setFilter(kind, value) {
        if (kind === 'type') type = type === value ? '' : value;
        if (kind === 'period') period = period === value ? '' : value;
        if (kind === 'sort') sort = value;
        current = null;
        load(true);
    }

    onMount(() => {
        if (count !== 0) load(true);
    });

    function formatDay(iso) {
        try {
            return firstOfMonth(
                new Date(iso).toLocaleDateString(formatLocale(lang), {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                }),
                lang,
            );
        } catch {
            return iso;
        }
    }

    function dateLabel(m) {
        const y = m.depicted_year;
        if (!y) return null;
        switch (m.date_precision) {
            case 'day':
                return m.captured_at ? formatDay(m.captured_at) : String(y);
            case 'circa':
                return t('media.date.circa', { year: y });
            case 'before':
                return t('media.date.before', { year: y });
            case 'after':
                return t('media.date.after', { year: y });
            case 'decade':
                return t('media.date.decade', { year: y, end: y + 9 });
            case 'range':
                return m.depicted_year_to
                    ? t('media.date.range', { from: y, to: m.depicted_year_to })
                    : String(y);
            case 'category':
            case 'title':
                return t('media.date.approx', { year: y });
            default:
                return String(y);
        }
    }

    // Titre, licence et lien de la licence : règles communes (lib/mediaCredits.js).
    // Photo de rue sans titre (Panoramax) : intitulé générique traduit, pour le texte de remplacement.
    const heading = (m) =>
        mediaHeading(m) || t('media.street_view', { provider: m?.provider_label ?? '' });
    const licenseLabel = (m) => mediaLicenseLabel(m, t);
    const licenseHref = mediaLicenseHref;
    const rightsLabel = (m) => t(`media.rights.${m.rights_status ?? 'unknown'}`);

    async function open(i) {
        current = i;
        await tick();
        if (dialogEl && !dialogEl.open) dialogEl.showModal();
    }

    function close() {
        if (dialogEl?.open) dialogEl.close();
        current = null;
    }

    async function step(delta) {
        if (current === null) return;
        const focused = document.activeElement;
        const next = current + delta;
        if (next >= items.length && hasMore) {
            await load(false);
        }
        if (next >= 0 && next < items.length) current = next;
        // Le bouton activé se désactive (première ou dernière image) : le focus passe à l'autre bouton,
        // ou à l'image, au lieu de retomber sur la page (RGAA 12.8).
        await tick();
        const target =
            current !== null ? focusAfterStep(delta, current, items.length, hasMore) : null;
        if (target && focused === (delta < 0 ? previousBtn : nextBtn)) {
            ({ previous: previousBtn, next: nextBtn, image: imageLink })[target]?.focus();
        }
    }

    function onKey(e) {
        if (current === null || single) return;
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            step(1);
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            step(-1);
        }
    }

    function onBackdrop(e) {
        if (e.target === dialogEl) close();
    }
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

<!-- Licence : lien vers son texte quand il est connu (domaine public déposé : texte seul). -->
{#snippet license(m)}{#if licenseHref(m)}<a
            href={licenseHref(m)}
            target="_blank"
            rel="noopener noreferrer license">{licenseLabel(m)}{@render ext()}</a
        >{:else}<span>{licenseLabel(m)}</span>{/if}{/snippet}

{#if loading && !facets}
    <SheetSection id="gallery" title={t('media.title')}>
        <p class="status" aria-busy="true">{t('media.loading')}</p>
        <div class="grid" aria-hidden="true">
            {#each [0, 1, 2, 3] as i (i)}
                <div class="card skeleton"><div class="thumb"></div></div>
            {/each}
        </div>
    </SheetSection>
{:else if error && !facets}
    <SheetSection id="gallery" title={t('media.title')}>
        <p class="status">
            {t('media.error')}
            <button type="button" class="vff-btn quiet small" onclick={() => load(true)}
                >{t('media.retry')}</button
            >
        </p>
    </SheetSection>
{:else if grandTotal > 0}
    <SheetSection id="gallery" title={t('media.title')} {intro}>
        {#snippet actions()}
            {#if showSort}
                <label class="sort">
                    <span>{t('media.sort.label')}</span>
                    <select
                        class="vff-select"
                        value={sort}
                        onchange={(e) => setFilter('sort', e.currentTarget.value)}
                    >
                        <option value="oldest">{t('media.sort.oldest')}</option>
                        <option value="newest">{t('media.sort.newest')}</option>
                    </select>
                </label>
            {/if}
        {/snippet}

        {#if showTypes || showPeriods}
            <div class="filters">
                {#if showTypes}
                    <div class="chips" role="group" aria-labelledby="gallery-f-type">
                        <span class="chips-label" id="gallery-f-type">{t('media.filter.type')}</span
                        >
                        <button
                            type="button"
                            class="chip"
                            aria-pressed={type === ''}
                            onclick={() => setFilter('type', '')}
                        >
                            {t('media.filter.all')} <span class="n">{grandTotal}</span>
                        </button>
                        {#each typeKeys as k (k)}
                            <button
                                type="button"
                                class="chip"
                                aria-pressed={type === k}
                                onclick={() => setFilter('type', k)}
                            >
                                {t(`media.types.${k}`)} <span class="n">{facets.types[k]}</span>
                            </button>
                        {/each}
                    </div>
                {/if}
                {#if showPeriods}
                    <div class="chips" role="group" aria-labelledby="gallery-f-period">
                        <span class="chips-label" id="gallery-f-period"
                            >{t('media.filter.period')}</span
                        >
                        {#each periodKeys as k (k)}
                            <button
                                type="button"
                                class="chip"
                                aria-pressed={period === k}
                                onclick={() => setFilter('period', k)}
                            >
                                {t(`media.periods.${k}`)} <span class="n">{facets.periods[k]}</span>
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>
        {/if}

        {#if items.length === 0 && !loading}
            <p class="status">{t('media.empty_filtered')}</p>
        {:else}
            <ul class="grid">
                {#each items as m, i (m.id)}
                    <li class="card">
                        <button type="button" class="thumb" onclick={() => open(i)}>
                            <span class="vff-sr-only">{t('media.open', { title: heading(m) })}</span
                            >
                            <img
                                src={commonsSrc(m.thumb_url)}
                                alt=""
                                class:whole={m.derivatives_ok === false}
                                loading="lazy"
                                decoding="async"
                                width={m.width && m.width < 500 ? m.width : 500}
                                height={m.width && m.height
                                    ? Math.round((Math.min(m.width, 500) * m.height) / m.width)
                                    : 375}
                            />
                            {#if m.type !== 'photo'}
                                <span class="tag type">{t(`media.types.${m.type}`)}</span>
                            {/if}
                            {#if dateLabel(m)}
                                <span class="tag year">{dateLabel(m)}</span>
                            {/if}
                        </button>
                        <div class="meta">
                            <p class="title">{heading(m)}</p>
                            <p class="credit">
                                <span class="author">{m.author ?? t('media.author_unknown')}</span>,
                                {@render license(m)}{#if mediaSourceHref(m)},
                                    <a
                                        href={mediaSourceHref(m)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{t('media.via', {
                                            provider: m.provider_label,
                                        })}{@render ext()}</a
                                    >{/if}{#if m.contribution_id},
                                    {t('media.contributed')}{/if}
                            </p>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}

        {#if !single || hasMore || error}
            <div class="gallery-foot">
                <span class="shown" aria-live="polite"
                    >{t('media.shown', { shown: items.length, total })}</span
                >
                {#if hasMore}
                    <button
                        type="button"
                        class="vff-btn secondary small"
                        onclick={() => load(false)}
                        disabled={loading}
                    >
                        {loading ? t('media.loading') : t('media.load_more')}
                    </button>
                {/if}
                {#if error}
                    <span class="status">{t('media.error')}</span>
                {/if}
            </div>
        {/if}

        {#if providers.length}
            <p class="notice">
                {t('media.hosted_notice', {
                    provider: providers
                        .map((p) => (p === 'commons' ? 'Wikimedia Commons' : p))
                        .join(', '),
                })}
            </p>
        {/if}
    </SheetSection>
{/if}

<svelte:window onkeydown={onKey} />

<!-- Clic sur le fond (backdrop) = fermeture ; le clavier est géré par Échap (natif) et les flèches. -->
<dialog
    bind:this={dialogEl}
    class="lightbox"
    onclose={() => (current = null)}
    onclick={onBackdrop}
    aria-labelledby={active ? 'lb-title' : undefined}
>
    {#if active}
        <div class="lb-inner">
            <div class="lb-toolbar">
                {#if !single}
                    <span class="lb-counter"
                        >{t('media.lightbox.counter', { current: current + 1, total })}</span
                    >
                {/if}
                <button
                    type="button"
                    class="lb-btn close"
                    onclick={close}
                    aria-label={t('media.lightbox.close')}
                >
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class={['lb-stage', { single }]}>
                {#if !single}
                    <button
                        type="button"
                        class="lb-btn"
                        bind:this={previousBtn}
                        onclick={() => step(-1)}
                        disabled={disabled.previous}
                        aria-label={t('media.lightbox.previous')}
                    >
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </button>
                {/if}
                <a
                    bind:this={imageLink}
                    href={active.display_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    title={t('media.lightbox.open_original')}
                >
                    <!-- Alternative courte (RGAA 1.3.9) : le titre complet est juste dessous (#lb-title). -->
                    <img src={commonsSrc(active.display_url)} alt={conciseAlt(heading(active))} />
                    <span class="vff-sr-only">
                        {t('media.lightbox.open_original')} ({t('common.new_tab')})</span
                    >
                </a>
                {#if !single}
                    <button
                        type="button"
                        class="lb-btn"
                        bind:this={nextBtn}
                        onclick={() => step(1)}
                        disabled={disabled.next}
                        aria-label={t('media.lightbox.next')}
                    >
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                {/if}
            </div>
            <div class="lb-caption">
                <h3 id="lb-title">{heading(active)}</h3>
                <p class="lb-date">
                    {dateLabel(active) ?? t('media.date.unknown')}{#if active.type !== 'photo'},
                        {t(`media.types.${active.type}`)}{/if}
                </p>
                {#if active.caption && heading(active) !== active.caption}<p class="lb-desc">
                        {active.caption}
                    </p>{/if}
                <p class="lb-credit">
                    <strong>{active.author ?? t('media.author_unknown')}</strong>
                    {@render license(active)}
                    {#if rightsLabel(active).toLowerCase() !== licenseLabel(active).toLowerCase()}
                        <span class="vff-badge muted">{rightsLabel(active)}</span>
                    {/if}
                    {#if active.share_alike}<span class="vff-badge muted"
                            >{t('media.share_alike')}</span
                        >{/if}
                    {#if active.commercial_ok === false}<span class="vff-badge warning"
                            >{t('media.no_commercial')}</span
                        >{/if}
                    {#if active.rights_uncertain}<span class="vff-badge warning"
                            >{t('media.rights_uncertain')}</span
                        >{/if}
                    {#if active.contribution_id}<span class="vff-badge muted"
                            >{t('media.contributed_badge')}</span
                        >{/if}
                </p>
                {#if active.title && heading(active) !== active.title}
                    <p class="lb-file">{t('media.file_name', { name: active.title })}</p>
                {/if}
                {#if mediaSourceHref(active)}
                    <p class="lb-source">
                        <a
                            class="vff-link"
                            href={mediaSourceHref(active)}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t('media.view_source', {
                                provider: active.provider_label,
                            })}{@render ext()}
                        </a>
                    </p>
                {/if}
            </div>
        </div>
    {/if}
</dialog>

<style>
    .sort {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .sort select {
        width: auto;
    }

    .filters {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: var(--vff-space-5);
    }
    .chips {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .chips-label {
        min-width: 10em;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .chip {
        min-height: 32px;
        padding: 4px 12px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-pill);
        background: var(--vff-bg);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        cursor: pointer;
    }
    .chip:hover {
        background: var(--vff-bg-muted);
    }
    /* Filtre actif : fond orange pâle, contour orange, texte en encre. */
    .chip[aria-pressed='true'] {
        border-color: var(--vff-accent);
        background: var(--vff-bg-accent);
        box-shadow: inset 0 0 0 1px var(--vff-accent);
        font-weight: 600;
    }
    .chip .n {
        margin-left: 4px;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    @media (prefers-reduced-motion: no-preference) {
        .chip {
            transition:
                background-color var(--vff-transition),
                border-color var(--vff-transition);
        }
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .card {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .thumb {
        position: relative;
        display: block;
        width: 100%;
        aspect-ratio: 4 / 3;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-muted);
        cursor: zoom-in;
    }
    .thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    /* Licence sans modification (ND) : l'image entière, réduite, jamais recadrée. */
    .thumb img.whole {
        object-fit: contain;
    }
    .thumb:hover img {
        filter: brightness(0.92);
    }
    .tag {
        position: absolute;
        padding: 2px 8px;
        border-radius: var(--vff-radius-pill);
        background: rgba(0, 0, 0, 0.72);
        color: #fff;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        pointer-events: none;
    }
    .tag.type {
        top: 8px;
        left: 8px;
    }
    .tag.year {
        right: 8px;
        bottom: 8px;
        font-variant-numeric: tabular-nums;
    }
    .skeleton .thumb {
        cursor: default;
    }
    @media (prefers-reduced-motion: no-preference) {
        .skeleton .thumb {
            animation: pulse 1.4s ease-in-out infinite;
        }
    }
    @keyframes pulse {
        50% {
            opacity: 0.5;
        }
    }
    .meta {
        min-width: 0;
        padding: 10px 0 0;
    }
    /* Titre complet : jamais coupé (les titres Commons peuvent être longs), il passe à la ligne. */
    .meta .title {
        margin: 0 0 4px;
        overflow-wrap: anywhere;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        line-height: 1.35;
    }
    .credit {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.45;
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .credit a {
        color: inherit;
        text-decoration-color: var(--vff-border-strong);
    }
    .credit a:hover {
        color: var(--vff-accent-ink);
    }

    .gallery-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
        margin-top: var(--vff-space-5);
    }
    .shown {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .status {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .notice {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* Visionneuse : fenêtre aux couleurs du thème, image sur fond neutre. */
    .lightbox {
        width: min(1200px, 96vw);
        max-height: 94vh;
        padding: 0;
        overflow: auto;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-lg);
        background: var(--vff-bg-elevated);
        color: var(--vff-text);
        box-shadow: var(--vff-shadow-popover);
    }
    .lightbox::backdrop {
        background: rgba(0, 0, 0, 0.75);
    }
    .lb-inner {
        display: flex;
        flex-direction: column;
    }
    .lb-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .lb-btn {
        display: inline-flex;
        flex: none;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 1px solid var(--vff-border-control);
        border-radius: 50%;
        background: var(--vff-bg);
        color: var(--vff-text);
        font-size: 18px;
        cursor: pointer;
    }
    .lb-btn.close {
        margin-left: auto;
    }
    .lb-btn:hover:not(:disabled) {
        background: var(--vff-bg-muted);
    }
    .lb-btn:disabled {
        border-color: var(--vff-border);
        color: var(--vff-text-muted);
        cursor: default;
    }
    .lb-stage {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 200px;
        padding: 0 8px;
    }
    .lb-stage a {
        display: flex;
        flex: 1;
        justify-content: center;
        min-width: 0;
    }
    .lb-stage img {
        max-width: 100%;
        max-height: 68vh;
        object-fit: contain;
        background: var(--vff-bg-subtle);
    }
    .lb-caption {
        max-width: 46.4em;
        padding: 16px 24px 24px;
    }
    .lb-caption h3 {
        margin: 0 0 4px;
        font-family: var(--vff-font-display);
        font-size: 20px;
        font-weight: 700;
    }
    .lb-caption p {
        margin: 0 0 8px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
    }
    .lb-date {
        font-weight: 600;
        color: var(--vff-accent-ink);
    }
    .lb-desc,
    .lb-file {
        color: var(--vff-text-muted);
    }
    .lb-credit {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
    }
    /* Lien de la licence : cible de 24 px au moins. */
    .lb-credit a {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
    }

    @media (max-width: 600px) {
        .grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: var(--vff-space-4);
        }
        .chips-label {
            flex-basis: 100%;
        }
        /* Téléphone : flèches sous l'image, la photo garde toute la largeur. */
        .lb-stage {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 0 8px;
        }
        .lb-stage a {
            grid-column: 1 / -1;
            grid-row: 1;
        }
        .lb-stage .lb-btn:last-child {
            justify-self: end;
        }
        .lb-stage.single {
            display: flex;
        }
        .lb-caption {
            padding: 12px 16px 16px;
        }
    }
</style>
