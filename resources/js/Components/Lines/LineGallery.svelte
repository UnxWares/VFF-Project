<script>
    import { onMount, tick } from 'svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Galerie « Photos & archives » d'une ligne.
     *
     * Données : GET /api/v1/lines/{slug}/media (paginé, trié par époque).
     * Les images externes (Wikimedia Commons…) sont affichées par lien direct,
     * jamais copiées : chaque vignette porte SON crédit (auteur, licence telle
     * que déclarée par la source + lien, lien vers la page du fichier). Le
     * domaine public est aussi crédité (source citée).
     *
     * Se masque entièrement si la ligne n'a aucun média.
     */
    let { slug, perPage = 24 } = $props();

    let items = $state([]);
    let total = $state(0);
    let page = $state(0);
    let lastPage = $state(1);
    let facets = $state(null);
    let loading = $state(false);
    let error = $state(false);

    let sort = $state('oldest');
    let type = $state('');
    let period = $state('');

    let current = $state(null); // index dans items de l'image ouverte
    let dialogEl = $state(null);

    const TYPES = ['photo', 'postcard', 'map', 'plan', 'document'];
    const PERIODS = ['pre1900', '1900_1944', '1945_1969', '1970_1999', 'since2000', 'undated'];

    const t = (key, opts) => $i18n.t(key, opts);

    const grandTotal = $derived(
        facets ? Object.values(facets.types ?? {}).reduce((a, b) => a + b, 0) : 0,
    );
    const providers = $derived(Object.keys(facets?.providers ?? {}).filter((p) => p !== 'upload'));
    const hasMore = $derived(page < lastPage);
    const active = $derived(current !== null ? items[current] : null);

    async function load(reset = false) {
        if (loading) return;
        loading = true;
        error = false;
        const next = reset ? 1 : page + 1;
        const params = new URLSearchParams({ page: String(next), per_page: String(perPage), sort });
        if (type) params.set('type', type);
        if (period) params.set('period', period);
        try {
            const res = await fetch(`/api/v1/lines/${encodeURIComponent(slug)}/media?${params}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            items = reset ? json.data : [...items, ...json.data];
            total = json.meta.total;
            page = json.meta.current_page;
            lastPage = json.meta.last_page;
            facets = json.facets;
        } catch (e) {
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

    onMount(() => load(true));

    function formatDay(iso) {
        try {
            return new Date(iso).toLocaleDateString($i18n.language ?? 'fr', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
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

    // Licence affichée TELLE QUE fournie par la source ; à défaut, statut de droits.
    const licenseLabel = (m) => m.license ?? t(`media.rights.${m.rights_status ?? 'unknown'}`);
    const licenseHref = (m) => m.license_url ?? m.source_url;
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
        let next = current + delta;
        if (next >= items.length && hasMore) {
            await load(false);
        }
        if (next < 0 || next >= items.length) return;
        current = next;
    }

    function onKey(e) {
        if (current === null) return;
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

{#if loading && !facets}
    <section class="gallery-section" aria-busy="true">
        <h2>{t('media.title')}</h2>
        <p class="status">{t('media.loading')}</p>
        <div class="grid" aria-hidden="true">
            {#each Array(6) as _, i (i)}
                <div class="card skeleton"><div class="thumb"></div></div>
            {/each}
        </div>
    </section>
{:else if error && !facets}
    <section class="gallery-section">
        <h2>{t('media.title')}</h2>
        <p class="status">
            {t('media.error')}
            <button type="button" class="link-btn" onclick={() => load(true)}
                >{t('media.retry')}</button
            >
        </p>
    </section>
{:else if grandTotal > 0}
    <section class="gallery-section" aria-labelledby="gallery-title">
        <!-- Pas de <header> : header.css impose header { height: 200vh } à tous les en-têtes. -->
        <div class="gallery-head">
            <div>
                <h2 id="gallery-title">{t('media.title')}</h2>
                <p class="subtitle">{t('media.subtitle')}</p>
            </div>
            <label class="sort">
                <span>{t('media.sort.label')}</span>
                <select value={sort} onchange={(e) => setFilter('sort', e.currentTarget.value)}>
                    <option value="oldest">{t('media.sort.oldest')}</option>
                    <option value="newest">{t('media.sort.newest')}</option>
                </select>
            </label>
        </div>

        <div class="filters">
            <div class="chips" role="group" aria-label={t('media.filter.type')}>
                <button
                    type="button"
                    class="chip"
                    aria-pressed={type === ''}
                    onclick={() => setFilter('type', '')}
                >
                    {t('media.filter.all')} <span class="n">{grandTotal}</span>
                </button>
                {#each TYPES.filter((k) => facets.types?.[k]) as k (k)}
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
            <div class="chips" role="group" aria-label={t('media.filter.period')}>
                {#each PERIODS.filter((k) => facets.periods?.[k]) as k (k)}
                    <button
                        type="button"
                        class="chip period"
                        aria-pressed={period === k}
                        onclick={() => setFilter('period', k)}
                    >
                        {t(`media.periods.${k}`)} <span class="n">{facets.periods[k]}</span>
                    </button>
                {/each}
            </div>
        </div>

        {#if items.length === 0 && !loading}
            <p class="status">{t('media.empty_filtered')}</p>
        {:else}
            <ul class="grid">
                {#each items as m, i (m.id)}
                    <li class="card">
                        <button
                            type="button"
                            class="thumb"
                            onclick={() => open(i)}
                            aria-label={t('media.open', { title: m.title ?? '' })}
                        >
                            <img
                                src={m.thumb_url}
                                alt={m.title ?? ''}
                                loading="lazy"
                                decoding="async"
                                width={m.width && m.width < 500 ? m.width : 500}
                                height={m.width && m.height
                                    ? Math.round((Math.min(m.width, 500) * m.height) / m.width)
                                    : 375}
                            />
                            {#if m.type !== 'photo'}
                                <span class="badge">{t(`media.types.${m.type}`)}</span>
                            {/if}
                            {#if dateLabel(m)}
                                <span class="year">{dateLabel(m)}</span>
                            {/if}
                        </button>
                        <div class="meta">
                            <p class="title" title={m.title}>{m.title}</p>
                            <p class="credit">
                                <span class="author">{m.author ?? t('media.author_unknown')}</span>
                                ·
                                <a href={licenseHref(m)} target="_blank" rel="noopener license"
                                    >{licenseLabel(m)}</a
                                >
                                {#if m.source_url}
                                    ·
                                    <a href={m.source_url} target="_blank" rel="noopener">
                                        {t('media.via', { provider: m.provider_label })}
                                    </a>
                                {/if}
                            </p>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}

        <!-- Pas de <footer> : footer.css stylise tous les pieds de page (ombre, largeur 80 %). -->
        <div class="gallery-foot">
            <span class="shown">{t('media.shown', { shown: items.length, total })}</span>
            {#if hasMore}
                <button type="button" class="more" onclick={() => load(false)} disabled={loading}>
                    {loading ? t('media.loading') : t('media.load_more')}
                </button>
            {/if}
            {#if error}
                <span class="status">{t('media.error')}</span>
            {/if}
        </div>

        {#if providers.length}
            <p class="notice">
                <i class="bi bi-info-circle"></i>
                {t('media.hosted_notice', {
                    provider: providers
                        .map((p) => (p === 'commons' ? 'Wikimedia Commons' : p))
                        .join(', '),
                })}
            </p>
        {/if}
    </section>
{/if}

<svelte:window onkeydown={onKey} />

<!-- Clic sur le fond (backdrop) = fermeture ; le clavier est géré par Échap (natif) et les flèches. -->
<dialog
    bind:this={dialogEl}
    class="lightbox"
    onclose={() => (current = null)}
    onclick={onBackdrop}
    aria-label={active?.title ?? ''}
>
    {#if active}
        <div class="lb-inner">
            <div class="lb-toolbar">
                <span class="lb-counter"
                    >{t('media.lightbox.counter', { current: current + 1, total })}</span
                >
                <button
                    type="button"
                    class="lb-btn"
                    onclick={close}
                    aria-label={t('media.lightbox.close')}
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="lb-stage">
                <button
                    type="button"
                    class="lb-nav prev"
                    onclick={() => step(-1)}
                    disabled={current === 0}
                    aria-label={t('media.lightbox.previous')}
                >
                    <i class="bi bi-chevron-left"></i>
                </button>
                <a
                    href={active.display_url}
                    target="_blank"
                    rel="noopener"
                    title={t('media.lightbox.open_original')}
                >
                    {#key active.id}
                        <img src={active.display_url} alt={active.title ?? ''} />
                    {/key}
                </a>
                <button
                    type="button"
                    class="lb-nav next"
                    onclick={() => step(1)}
                    disabled={current >= items.length - 1 && !hasMore}
                    aria-label={t('media.lightbox.next')}
                >
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            <div class="lb-caption">
                <h3>{active.title}</h3>
                <p class="lb-date">
                    {dateLabel(active) ?? t('media.date.unknown')}
                    {#if active.type !== 'photo'}
                        · {t(`media.types.${active.type}`)}{/if}
                </p>
                {#if active.caption}<p class="lb-desc">{active.caption}</p>{/if}
                <p class="lb-credit">
                    <strong>{active.author ?? t('media.author_unknown')}</strong>
                    ·
                    <a href={licenseHref(active)} target="_blank" rel="noopener license"
                        >{licenseLabel(active)}</a
                    >
                    {#if rightsLabel(active).toLowerCase() !== licenseLabel(active).toLowerCase()}
                        <span class="rights {active.rights_status}">{rightsLabel(active)}</span>
                    {/if}
                    {#if active.share_alike}<span class="flag">{t('media.share_alike')}</span>{/if}
                    {#if active.commercial_ok === false}<span class="flag"
                            >{t('media.no_commercial')}</span
                        >{/if}
                </p>
                {#if active.source_url}
                    <p class="lb-source">
                        <a href={active.source_url} target="_blank" rel="noopener">
                            <i class="bi bi-box-arrow-up-right"></i>
                            {t('media.view_source', { provider: active.provider_label })}
                        </a>
                    </p>
                {/if}
            </div>
        </div>
    {/if}
</dialog>

<style>
    /* Même gabarit que les autres sections de la fiche ligne. */
    .gallery-section {
        max-width: 1180px;
        margin: 0 auto;
        padding: 52px 24px 0;
    }
    .gallery-section h2 {
        font-family: var(--vff-font-display);
        font-size: 28px;
        line-height: 1.2;
        margin: 0 0 14px;
        color: var(--vff-text);
    }
    .gallery-section h2:after {
        display: none;
    }
    .gallery-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px 24px;
        margin-bottom: 14px;
    }
    .gallery-head h2 {
        margin: 0 0 4px;
    }
    .gallery-section > .status {
        margin: 0;
    }
    .subtitle {
        margin: 0;
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    .sort {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: var(--vff-text-muted);
    }
    .sort select {
        font: inherit;
        color: var(--vff-text);
        background: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        padding: 6px 10px;
    }

    .filters {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 18px;
    }
    .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .chip {
        font: inherit;
        font-size: 13px;
        color: var(--vff-text);
        background: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-pill);
        padding: 5px 12px;
        cursor: pointer;
        transition: var(--vff-transition);
    }
    .chip:hover {
        border-color: var(--vff-accent);
    }
    /* Filtre actif : fond orange pâle, contour orange, texte en encre (le blanc sur l'orange
       ne passe pas le contraste AA). */
    .chip[aria-pressed='true'] {
        background: var(--vff-accent-soft);
        border-color: var(--vff-accent);
        box-shadow: inset 0 0 0 1px var(--vff-accent);
        color: var(--vff-text);
        font-weight: 600;
    }
    .chip .n {
        opacity: 0.7;
        margin-left: 4px;
        font-variant-numeric: tabular-nums;
    }
    .chip.period {
        font-size: 12px;
    }

    .grid {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
    .card {
        display: flex;
        flex-direction: column;
        background: var(--vff-bg-elevated);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        overflow: hidden;
        min-width: 0;
    }
    .thumb {
        position: relative;
        display: block;
        width: 100%;
        aspect-ratio: 4 / 3;
        padding: 0;
        border: 0;
        background: var(--vff-bg-muted);
        cursor: zoom-in;
        overflow: hidden;
    }
    .thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.3s ease;
    }
    .thumb:hover img,
    .thumb:focus-visible img {
        transform: scale(1.03);
    }
    .thumb:focus-visible {
        outline: 3px solid var(--vff-accent);
        outline-offset: -3px;
    }
    .badge,
    .year {
        position: absolute;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: var(--vff-radius-pill);
        background: rgba(0, 0, 0, 0.65);
        color: #fff;
        pointer-events: none;
    }
    .badge {
        top: 8px;
        left: 8px;
    }
    .year {
        bottom: 8px;
        right: 8px;
        font-variant-numeric: tabular-nums;
    }
    .skeleton .thumb {
        animation: pulse 1.4s ease-in-out infinite;
        cursor: default;
    }
    @keyframes pulse {
        50% {
            opacity: 0.5;
        }
    }
    .meta {
        padding: 10px 12px 12px;
        min-width: 0;
    }
    .meta .title {
        margin: 0 0 4px;
        font-family: var(--vff-font-display);
        font-size: 14px;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .credit {
        margin: 0;
        font-size: 11.5px;
        line-height: 1.4;
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .credit a {
        color: inherit;
        text-decoration: underline;
        text-decoration-color: var(--vff-border-strong, currentColor);
    }
    .credit a:hover {
        color: var(--vff-accent-ink);
    }

    .gallery-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 14px;
        margin-top: 20px;
    }
    .shown {
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    .more {
        font: inherit;
        font-weight: 600;
        padding: 9px 20px;
        border-radius: var(--vff-radius-pill);
        border: 1.5px solid var(--vff-accent);
        background: transparent;
        color: var(--vff-accent-ink);
        cursor: pointer;
    }
    .more:hover:not(:disabled) {
        background: var(--vff-accent-soft);
    }
    .more:focus-visible {
        outline: 2px solid var(--vff-accent);
        outline-offset: 2px;
    }
    .more:disabled {
        opacity: 0.6;
        cursor: progress;
    }
    .status {
        color: var(--vff-text-muted);
        font-size: 14px;
    }
    .link-btn {
        font: inherit;
        background: none;
        border: 0;
        padding: 0;
        color: var(--vff-accent-ink);
        text-decoration: underline;
        cursor: pointer;
    }
    .notice {
        margin: 18px 0 0;
        font-size: 12.5px;
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* ── Visionneuse ─────────────────────────────────────────── */
    .lightbox {
        width: min(1200px, 96vw);
        max-height: 94vh;
        padding: 0;
        border: 0;
        border-radius: var(--vff-radius-lg, 12px);
        background: #111;
        color: #eee;
        overflow: auto;
    }
    .lightbox::backdrop {
        background: rgba(0, 0, 0, 0.8);
    }
    .lb-inner {
        display: flex;
        flex-direction: column;
    }
    .lb-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        font-size: 13px;
        color: #bbb;
    }
    .lb-btn,
    .lb-nav {
        background: rgba(255, 255, 255, 0.08);
        border: 0;
        color: #fff;
        border-radius: 999px;
        width: 40px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 18px;
        flex: none;
    }
    .lb-btn:hover,
    .lb-nav:hover:not(:disabled) {
        background: rgba(255, 255, 255, 0.2);
    }
    .lb-nav:disabled {
        opacity: 0.25;
        cursor: default;
    }
    .lb-stage {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 8px;
        min-height: 200px;
    }
    .lb-stage a {
        display: flex;
        justify-content: center;
        min-width: 0;
        flex: 1;
    }
    .lb-stage img {
        max-width: 100%;
        max-height: 68vh;
        object-fit: contain;
        background: #222;
    }
    .lb-caption {
        padding: 14px 20px 20px;
    }
    .lb-caption h3 {
        margin: 0 0 4px;
        font-family: var(--vff-font-display);
        font-size: 18px;
        color: #fff;
    }
    .lb-date {
        margin: 0 0 8px;
        color: #ff8a3d;
        font-size: 14px;
        font-weight: 600;
    }
    .lb-desc {
        margin: 0 0 10px;
        font-size: 14px;
        line-height: 1.5;
        color: #ccc;
    }
    .lb-credit {
        margin: 0 0 6px;
        font-size: 13px;
        color: #ccc;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    .lb-credit a,
    .lb-source a {
        color: #ffb27a;
    }
    .rights,
    .flag {
        font-size: 11px;
        padding: 1px 8px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #ddd;
    }
    .rights.public_domain {
        border-color: #6fbf73;
        color: #9be29e;
    }
    .rights.open_license {
        border-color: #6fa8dc;
        color: #a9cdf0;
    }
    .lb-source {
        margin: 0;
        font-size: 13px;
    }

    @media (max-width: 640px) {
        .gallery-section {
            padding: 40px 16px 0;
        }
        .gallery-section h2 {
            font-size: 24px;
        }
        .grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        .lb-nav {
            width: 34px;
            height: 34px;
        }
        .lb-stage {
            padding: 0 2px;
            gap: 2px;
        }
    }
</style>
