<script>
    /**
     * Livret de parcours (/routebook?from=…&to=…) : le parcours d'aujourd'hui entre deux gares, ligne par ligne,
     * dans le sens de marche (App\Services\Routebook\Routebook, même contenu que GET /api/v1/routebook). Le livret
     * est calculé par le serveur et arrive avec la page : l'adresse se partage et la page s'imprime telle quelle.
     *
     * Formulaire : deux gares (suggestions de la recherche du site), inversion, « Effacer ». Envoyer recharge la
     * page avec les deux gares dans l'adresse.
     */
    import { untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import StationInput from '$components/Map/StationInput.svelte';
    import TrackLayout from '$components/Lines/TrackLayout.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import { pkLabel } from '$lib/lineSpeeds.js';
    import { asLayout, endName, missingSummary, routebookHref } from '$lib/routebook.js';

    /** @type {{ query: { from: string, to: string }, book: any, error: { error: string, side?: string, query?: string, station?: { slug: string, name: string }, candidates?: { slug: string, name: string }[] }|null }} */
    let { query, book, error } = $props();

    const uid = $props.id();
    /** @param {string} key @param {Record<string, unknown>} [vars] */
    const t = (key, vars) => String($i18n.t(`routebook.${key}`, vars));
    /** @param {string} key @param {Record<string, unknown>} [vars] */
    const tt = (key, vars) => String($i18n.t(key, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v, digits = 0) =>
        formatNumber(v, lang, { minimumFractionDigits: digits, maximumFractionDigits: digits });

    let fromText = $state(untrack(() => book?.from.name ?? query.from));
    let fromSlug = $state(untrack(() => book?.from.slug ?? null));
    let toText = $state(untrack(() => book?.to.name ?? query.to));
    let toSlug = $state(untrack(() => book?.to.slug ?? null));
    let fieldErrors = $state({ from: null, to: null });
    let loading = $state(false);

    // Nouvelle réponse du serveur (navigation, inversion) : les champs suivent le livret affiché.
    $effect.pre(() => {
        const b = book;
        const q = query;
        untrack(() => {
            fromText = b?.from.name ?? q.from;
            fromSlug = b?.from.slug ?? null;
            toText = b?.to.name ?? q.to;
            toSlug = b?.to.slug ?? null;
        });
    });

    function go(from, to) {
        router.get(
            routebookHref(from, to),
            {},
            {
                onStart: () => (loading = true),
                onFinish: () => (loading = false),
            },
        );
    }

    function submit(event) {
        event.preventDefault();
        fieldErrors = {
            from: fromText.trim() ? null : tt('itinerary.errors.from_required'),
            to: toText.trim() ? null : tt('itinerary.errors.to_required'),
        };
        const first = ['from', 'to'].find((k) => fieldErrors[k]);
        if (first) {
            document.getElementById(`${uid}-${first}`)?.focus();
            return;
        }
        go(fromSlug ?? fromText.trim(), toSlug ?? toText.trim());
    }

    function swap() {
        [fromText, toText] = [toText, fromText];
        [fromSlug, toSlug] = [toSlug, fromSlug];
        if (book) go(fromSlug ?? fromText.trim(), toSlug ?? toText.trim());
    }

    function clear() {
        fromText = '';
        fromSlug = null;
        toText = '';
        toSlug = null;
        fieldErrors = { from: null, to: null };
        if (book || error) router.get('/routebook');
    }

    function chooseCandidate(c) {
        if (error?.side === 'to') go(fromSlug ?? fromText.trim(), c.slug);
        else go(c.slug, toSlug ?? toText.trim());
    }

    const shownErrors = $derived({
        from: fromText.trim() ? null : fieldErrors.from,
        to: toText.trim() ? null : fieldErrors.to,
    });

    const errorMessage = $derived.by(() => {
        if (!error) return '';
        const params = { query: error.query ?? '', station: error.station?.name ?? '', era: '' };
        if ($i18n.exists(`routebook.errors.${error.error}`))
            return t(`errors.${error.error}`, params);
        if ($i18n.exists(`itinerary.errors.${error.error}`))
            return tt(`itinerary.errors.${error.error}`, params);
        return tt('itinerary.errors.generic');
    });

    const legs = $derived(book?.legs ?? []);
    const missing = $derived(book ? missingSummary(book) : null);
    // Le parcours vu comme une seule ligne, sur l'axe des km parcourus, pour le schéma des voies.
    const layout = $derived(book && book.objects > 0 ? asLayout(book) : null);
    const title = $derived(
        book ? `${t('meta_title')} ${book.from.name} - ${book.to.name}` : t('meta_title'),
    );
    const path = $derived(book ? routebookHref(book.from.slug, book.to.slug) : '/routebook');
</script>

<SeoHead {title} description={t('meta_description')} {path} />
<svelte:head>
    <title>{title} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={t('hero_title')} subtitle={t('hero_subtitle')} />

        <SectionBlock maxWidth="1180px">
            <form class="route-form" novalidate onsubmit={submit}>
                <div class="from">
                    <StationInput
                        id={`${uid}-from`}
                        label={tt('itinerary.from')}
                        placeholder={tt('itinerary.placeholder_from')}
                        bind:text={fromText}
                        bind:slug={fromSlug}
                        error={shownErrors.from}
                    />
                </div>
                <button
                    type="button"
                    class="swap vff-btn secondary"
                    onclick={swap}
                    title={tt('itinerary.swap')}
                    aria-label={tt('itinerary.swap')}
                >
                    <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                </button>
                <div class="to">
                    <StationInput
                        id={`${uid}-to`}
                        label={tt('itinerary.to')}
                        placeholder={tt('itinerary.placeholder_to')}
                        bind:text={toText}
                        bind:slug={toSlug}
                        error={shownErrors.to}
                    />
                </div>
                <div class="actions">
                    <button type="submit" class="vff-btn primary" aria-disabled={loading}>
                        {t('submit')}
                    </button>
                    <button type="button" class="vff-btn secondary" onclick={clear}>
                        {t('clear')}
                    </button>
                </div>
            </form>

            <p class="status" role="status">{loading ? t('loading') : ''}</p>

            <div role="alert">
                {#if error}
                    <div class="vff-notice danger">
                        <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                        <div class="vff-notice-body">
                            <p>{errorMessage}</p>
                            {#if error.candidates?.length}
                                <div class="candidates">
                                    {#each error.candidates as c (c.slug)}
                                        <button
                                            type="button"
                                            class="vff-btn secondary small"
                                            onclick={() => chooseCandidate(c)}>{c.name}</button
                                        >
                                    {/each}
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>
        </SectionBlock>

        {#if book}
            <SectionBlock kind="alt" maxWidth="1180px" id="routebook">
                <div class="summary">
                    <div>
                        <h2 class="trip">
                            <Link href="/stations/{book.from.slug}">{book.from.name}</Link>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            <span class="vff-sr-only">-</span>
                            <Link href="/stations/{book.to.slug}">{book.to.name}</Link>
                        </h2>
                        <p class="figures">
                            {t('summary', { km: n(book.distance_km, 1), count: book.objects })}
                            <span class="sep" aria-hidden="true">·</span>
                            {t('lines', { count: legs.length })}{#if legs.length > 1}, {t(
                                    'changes',
                                    { count: legs.length - 1 },
                                )}{/if}
                        </p>
                        <p class="today">{t('today')}</p>
                    </div>
                    <button
                        type="button"
                        class="vff-btn secondary print"
                        onclick={() => window.print()}
                    >
                        <i class="bi bi-printer" aria-hidden="true"></i>
                        {t('print')}
                    </button>
                </div>

                <h3 class="block-title">{t('legs_title')}</h3>
                <p class="intro">{t('legs_intro')}</p>
                <ol class="legs">
                    {#each legs as leg (leg.index)}
                        <li class="leg">
                            <p class="leg-line">
                                {#if leg.line.code}<span class="vff-plate">{leg.line.code}</span
                                    >{/if}
                                <Link href="/lines/{leg.line.slug}" class="vff-link"
                                    >{leg.line.name}</Link
                                >
                            </p>
                            <p class="leg-ends">
                                {endName(leg.from) ?? t('junction')}
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                <span class="vff-sr-only">-</span>
                                {endName(leg.to) ?? t('junction')}
                            </p>
                            <p class="leg-meta">
                                <span
                                    >{t('leg_km', {
                                        from: n(leg.route_from_km, 1),
                                        to: n(leg.route_to_km, 1),
                                    })}</span
                                >
                                {#if leg.pk_from_m !== null && leg.pk_to_m !== null}
                                    <span
                                        >{t('leg_pk', {
                                            from: pkLabel(leg.pk_from_m),
                                            to: pkLabel(leg.pk_to_m),
                                        })}{#if leg.direction}&nbsp;({t(
                                                `direction.${leg.direction}`,
                                            )}){/if}</span
                                    >
                                {:else}
                                    <span>{t('leg_pk_unknown')}</span>
                                {/if}
                            </p>
                            {#if leg.pk_precision}
                                <p class="leg-note">{t(`precision.${leg.pk_precision}`)}</p>
                            {/if}
                            {#if !leg.has_detail}
                                <p class="leg-note">{t('no_detail')}</p>
                            {/if}
                        </li>
                    {/each}
                </ol>

                <h3 class="block-title">{t('layout_title')}</h3>
                {#if missing?.complete}
                    <p class="vff-notice info coverage">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>{t('coverage_none')}</span>
                    </p>
                {:else if missing && missing.metres > 0}
                    <p class="vff-notice info coverage">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span
                            >{t('coverage_partial', {
                                km: n(missing.metres / 1000, 1),
                                lines: missing.lines.join(', '),
                            })}</span
                        >
                    </p>
                {/if}

                {#if layout}
                    <div class="layout">
                        <TrackLayout
                            {layout}
                            slug={null}
                            lineName={`${book.from.name} - ${book.to.name}`}
                        />
                    </div>
                {/if}

                {#if book.sources.length && !layout}
                    <h3 class="block-title">{t('sources_title')}</h3>
                    <ul class="sources">
                        {#each book.sources as s (s.key)}
                            <li>
                                {#if s.url}<a
                                        href={s.url}
                                        class="vff-link"
                                        rel="noopener noreferrer">{s.label}</a
                                    >{:else}{s.label}{/if}{#if s.licence}&nbsp;({s.licence}){/if}
                            </li>
                        {/each}
                    </ul>
                {/if}
            </SectionBlock>
        {/if}
    </main>
</Layout>

<style>
    .route-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: start;
        gap: var(--vff-space-3, 16px);
    }
    .swap {
        margin-top: 30px;
        min-width: 44px;
        min-height: 44px;
    }
    .actions {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    @media (max-width: 720px) {
        .route-form {
            grid-template-columns: minmax(0, 1fr);
        }
        .swap {
            margin-top: 0;
            justify-self: start;
        }
        .swap i {
            display: inline-block;
            transform: rotate(90deg);
        }
    }
    .status:empty {
        display: none;
    }
    .candidates {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .summary {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
    }
    .trip {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0 10px;
        margin: 0 0 6px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xl, 1.5rem);
        text-wrap: balance;
    }
    .trip i {
        color: var(--vff-primary);
    }
    .figures {
        margin: 0;
        font-size: var(--vff-fs-md, 1.125rem);
        font-variant-numeric: tabular-nums;
    }
    .sep {
        margin: 0 6px;
        color: var(--vff-text-muted);
    }
    .today,
    .intro,
    .leg-note {
        margin: 4px 0 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm, 0.9375rem);
        text-wrap: pretty;
    }
    .block-title {
        margin: 28px 0 4px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-lg, 1.25rem);
    }
    .legs {
        display: grid;
        gap: 12px;
        margin: 16px 0 0;
        padding: 0;
        list-style: none;
    }
    .leg {
        padding: 14px 16px;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-lg, 12px);
        background-color: var(--vff-bg-elevated);
    }
    .leg-line {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 6px 10px;
        margin: 0;
        font-weight: 700;
    }
    .leg-ends {
        margin: 6px 0 0;
    }
    .leg-ends i {
        margin: 0 4px;
        color: var(--vff-text-muted);
    }
    .leg-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 20px;
        margin: 4px 0 0;
        font-variant-numeric: tabular-nums;
    }
    .layout {
        margin-top: 16px;
    }
    .coverage {
        margin-top: 12px;
    }
    .sources {
        margin: 8px 0 0;
        padding-left: 20px;
    }
    @media print {
        .print {
            display: none;
        }
        .leg {
            break-inside: avoid;
        }
    }
</style>
