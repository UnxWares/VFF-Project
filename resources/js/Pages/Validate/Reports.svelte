<script>
    import { Link, page, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import ReviewNav from '$components/Contribute/ReviewNav.svelte';
    import Pagination from '$components/Directory/Pagination.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import { reportDate } from '$lib/errorReport.js';
    import { i18n } from '$lib/i18n.js';
    import { linkLabel } from '$lib/linkLabel.js';

    /**
     * Signalements d'erreur envoyés depuis les fiches (ErrorReportReviewController), pour les
     * validateurs et les administrateurs : filtre par état, lien vers la fiche, traitement avec
     * une note (obligatoire pour un rejet).
     */
    let { reports, status = 'open', counts = {} } = $props();

    const STATUSES = ['open', 'resolved', 'rejected'];
    const items = $derived(reports?.data ?? []);
    const flash = $derived(page.props.flash ?? {});
    const errors = $derived(page.props.errors ?? {});
    const lang = $derived($i18n.language);
    /**
     * Traduction en texte simple (les props des composants attendent une chaîne).
     * @param {string} key
     * @param {Record<string, unknown>} [opts]
     * @returns {string}
     */
    const t = (key, opts) => String($i18n.t(key, opts));

    /** Notes en cours de saisie, par signalement. */
    let notes = $state({});
    /** Signalement dont le traitement vient d'être envoyé (pour y afficher l'erreur éventuelle). */
    let active = $state(null);
    let processing = $state(false);

    function handle(report, next) {
        active = report.id;
        router.patch(
            `/validate/reports/${report.id}`,
            { status: next, note: (notes[report.id] ?? '').trim() },
            {
                preserveScroll: true,
                onStart: () => (processing = true),
                onFinish: () => (processing = false),
                onSuccess: () => {
                    if (!Object.keys(page.props.errors ?? {}).length) delete notes[report.id];
                },
                onError: () => document.getElementById(`note-${report.id}`)?.focus(),
            },
        );
    }

    function goToPage(n) {
        router.get('/validate/reports', { status, page: n });
    }
</script>

<svelte:head><title>{t('reports.review.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="reports-page">
        <PageHero title={t('reports.review.title')} subtitle={t('reports.review.subtitle')}>
            <ReviewNav current="reports" />
        </PageHero>

        <div class="vff-container body">
            <!-- Toujours présente : les messages qui y entrent sont annoncés (Notice). -->
            <div class="flash">
                {#if flash.success}<Notice tone="success">{flash.success}</Notice>{/if}
                {#if flash.error}<Notice tone="danger">{flash.error}</Notice>{/if}
            </div>

            <nav class="filters" aria-label={t('reports.review.filter_label')}>
                <ul>
                    {#each STATUSES as s (s)}
                        <li>
                            <Link
                                href="/validate/reports?status={s}"
                                aria-current={s === status ? 'page' : undefined}
                                preserveScroll
                            >
                                {t(`reports.review.filters.${s}`)}
                                <span class="count">{counts[s] ?? 0}</span>
                            </Link>
                        </li>
                    {/each}
                </ul>
            </nav>

            {#if items.length === 0}
                <EmptyState
                    icon="bi-flag"
                    title={t(`reports.review.empty.${status}`)}
                    text={status === 'open' ? t('reports.review.empty_text') : null}
                    headingLevel={2}
                />
            {:else}
                <ol class="list">
                    {#each items as r (r.id)}
                        <li class="report" id="report-{r.id}">
                            <article aria-labelledby="report-{r.id}-title">
                                <div class="head">
                                    <span class="vff-badge muted"
                                        >{t(`reports.review.subject_types.${r.subject.type}`)}</span
                                    >
                                    <h2 id="report-{r.id}-title" lang="fr">{r.subject.label}</h2>
                                </div>
                                <p class="meta">
                                    <span>{t('reports.review.report_number', { id: r.id })}</span>
                                    <span>
                                        <time datetime={r.created_at}
                                            >{t('reports.review.received', {
                                                date: reportDate(r.created_at, lang),
                                            })}</time
                                        >
                                        {r.reporter
                                            ? t('reports.review.by_member', { name: r.reporter })
                                            : t('reports.review.by_visitor')}
                                    </span>
                                    {#if r.subject.url}
                                        <Link class="vff-link" href={r.subject.url}
                                            >{t('reports.review.open_sheet')}<span
                                                class="vff-sr-only">&nbsp;: {r.subject.label}</span
                                            ></Link
                                        >
                                    {:else}
                                        <span>{t('reports.review.sheet_missing')}</span>
                                    {/if}
                                </p>

                                <p class="description" lang={r.locale}>{r.description}</p>

                                {#if r.source_url || r.contact_email}
                                    <dl class="facts">
                                        {#if r.source_url}
                                            <div>
                                                <dt>{t('reports.review.source')}</dt>
                                                <dd>
                                                    <a
                                                        href={r.source_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer nofollow"
                                                        >{linkLabel(r.source_url, 60, lang)}<i
                                                            class="bi bi-box-arrow-up-right vff-ext"
                                                            aria-hidden="true"
                                                        ></i><span class="vff-sr-only">
                                                            ({t('common.new_tab')})</span
                                                        ></a
                                                    >
                                                </dd>
                                            </div>
                                        {/if}
                                        {#if r.contact_email}
                                            <div>
                                                <dt>{t('reports.review.contact')}</dt>
                                                <dd>
                                                    <a href="mailto:{r.contact_email}"
                                                        >{r.contact_email}</a
                                                    >
                                                </dd>
                                            </div>
                                        {/if}
                                    </dl>
                                {/if}

                                {#if r.status === 'open'}
                                    <div class="handle">
                                        <Field
                                            id="note-{r.id}"
                                            label={t('reports.review.note_label')}
                                            hint={t('reports.review.note_hint')}
                                            error={active === r.id ? errors.note : null}
                                        >
                                            {#snippet children(attrs)}
                                                <textarea
                                                    {...attrs}
                                                    class="vff-textarea"
                                                    rows="3"
                                                    maxlength="2000"
                                                    bind:value={notes[r.id]}
                                                ></textarea>
                                            {/snippet}
                                        </Field>
                                        <div class="actions">
                                            <button
                                                type="button"
                                                class="vff-btn primary"
                                                disabled={processing}
                                                onclick={() => handle(r, 'resolved')}
                                            >
                                                {t('reports.review.mark_resolved')}<span
                                                    class="vff-sr-only"
                                                    >&nbsp;: {t('reports.review.report_number', {
                                                        id: r.id,
                                                    })}</span
                                                >
                                            </button>
                                            <button
                                                type="button"
                                                class="vff-btn danger"
                                                disabled={processing}
                                                onclick={() => handle(r, 'rejected')}
                                            >
                                                {t('reports.review.reject')}<span
                                                    class="vff-sr-only"
                                                    >&nbsp;: {t('reports.review.report_number', {
                                                        id: r.id,
                                                    })}</span
                                                >
                                            </button>
                                        </div>
                                    </div>
                                {:else}
                                    <div class="outcome">
                                        <p class="meta">
                                            <span
                                                class={[
                                                    'vff-badge',
                                                    r.status === 'resolved' ? 'success' : 'muted',
                                                ]}>{t(`reports.review.statuses.${r.status}`)}</span
                                            >
                                            <time datetime={r.handled_at}>
                                                {r.handler
                                                    ? t('reports.review.handled_by', {
                                                          date: reportDate(r.handled_at, lang),
                                                          name: r.handler,
                                                      })
                                                    : t('reports.review.handled', {
                                                          date: reportDate(r.handled_at, lang),
                                                      })}
                                            </time>
                                        </p>
                                        {#if r.resolution_note}
                                            <div class="note">
                                                <p class="note-title">
                                                    {t('reports.review.note')}
                                                </p>
                                                <p>{r.resolution_note}</p>
                                            </div>
                                        {/if}
                                    </div>
                                {/if}
                            </article>
                        </li>
                    {/each}
                </ol>

                {#if reports.last_page > 1}
                    <div class="pager">
                        <Pagination
                            current={reports.current_page}
                            last={reports.last_page}
                            onpage={goToPage}
                        />
                    </div>
                {/if}
            {/if}
        </div>
    </main>
</Layout>

<style>
    .body {
        padding-block: var(--vff-section-space);
    }
    .flash {
        max-width: 860px;
    }
    .flash > :global(*) {
        margin-bottom: var(--vff-space-5);
    }
    .filters ul {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2);
        margin: 0 0 var(--vff-space-6);
        padding: 0;
        list-style: none;
    }
    .filters :global(a) {
        display: inline-flex;
        align-items: center;
        gap: var(--vff-space-2);
        min-height: 40px;
        padding: 6px 16px;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-pill);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text);
        text-decoration: none;
        transition:
            background-color var(--vff-transition),
            border-color var(--vff-transition);
    }
    .filters :global(a:hover) {
        background-color: var(--vff-bg-subtle);
    }
    /* Filtre actif : contour et graisse plus forts, en plus de aria-current. */
    .filters :global(a[aria-current='page']) {
        border-color: var(--vff-text);
        background-color: var(--vff-bg-subtle);
        font-weight: 600;
    }
    .count {
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .list {
        max-width: 860px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .report {
        padding-block: var(--vff-space-6);
        border-bottom: 1px solid var(--vff-border);
        scroll-margin-top: 80px;
    }
    article {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
    }
    .head h2 {
        min-width: 0;
        margin: 0;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        line-height: 1.3;
        overflow-wrap: anywhere;
        text-wrap: balance;
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-1) var(--vff-space-4);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .description {
        max-width: 39.4em;
        margin: 0;
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        white-space: pre-line;
        overflow-wrap: anywhere;
    }
    /* Libellés dans une colonne, valeurs alignées sur un même bord. */
    .facts {
        display: grid;
        grid-template-columns: max-content minmax(0, 1fr);
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-4);
        margin: 0;
    }
    .facts div {
        display: contents;
    }
    .facts dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .facts dd {
        min-width: 0;
        margin: 0;
        overflow-wrap: anywhere;
    }
    /* Zone cliquable d'au moins 24 px de haut. */
    .facts dd a {
        display: inline-block;
        padding-block: 3px;
    }
    .handle {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        max-width: 39.4em;
        padding: var(--vff-space-4) var(--vff-space-5);
        background-color: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }
    .outcome {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
    }
    .note {
        max-width: 39.4em;
        padding: var(--vff-space-3) var(--vff-space-4);
        background-color: var(--vff-bg-subtle);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
    }
    .note p {
        margin: 0;
        overflow-wrap: anywhere;
        white-space: pre-line;
    }
    .note-title {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .pager {
        max-width: 860px;
        margin-top: var(--vff-space-6);
    }

    @media (max-width: 600px) {
        .handle {
            padding: var(--vff-space-4);
        }
        .actions :global(.vff-btn) {
            width: 100%;
        }
    }
</style>
