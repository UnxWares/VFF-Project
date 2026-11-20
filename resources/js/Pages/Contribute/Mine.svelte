<script>
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';
    import { formatKm } from '$lib/traceGeometry.js';
    import {
        changeEntries,
        eraLabel,
        formatDay,
        formatPk,
    } from '$components/Contribute/segmentChanges.js';
    import {
        EVENT_TYPE,
        LINE_MATERIAL_TYPE,
        PROPOSAL_TYPES,
        SERIES_TYPE,
        proposalFacts,
        proposalKindKey,
    } from '$components/Contribute/proposals.js';

    // Liste des changements : ceux d'un tronçon seulement. Ceux d'une série ({ before, after }) se relisent
    // dans l'espace de validation ; ici, ils sortaient en JSON brut et débordaient à 320 px (RGAA 10.11, 13.5).

    /** Faits résumés d'une proposition sourcée dans la liste, par type. */
    const SUMMARY_FACTS = {
        line_event: ['type', 'date', 'portion'],
        media: ['title', 'license'],
        line_material: ['series', 'start', 'end'],
        material_series: ['series'],
    };

    let { contributions = [] } = $props();

    // Nombre d'approbations qui publient une contribution (ContributionController::APPROVAL_THRESHOLD).
    const THRESHOLD = 2;

    // Ton du badge par statut : le texte dit l'état, la couleur le souligne.
    const STATUS_TONE = {
        pending: 'warning',
        approved: 'info',
        applied: 'success',
        rejected: 'danger',
    };
    const STATUS_ICON = {
        pending: 'bi-hourglass-split',
        approved: 'bi-check2',
        applied: 'bi-check2-all',
        rejected: 'bi-x-lg',
    };

    /** Traduction passée aux fonctions de segmentChanges.js. */
    const tr = (key, opts) => $i18n.t(key, opts);

    function statusLabel(status) {
        const key = `editor.mine.status_labels.${status}`;
        const label = $i18n.t(key);
        return label === key ? status : label;
    }

    function title(c) {
        if (c.payload?.line_name) return c.payload.line_name;
        if (c.payload?.new_line_name) return c.payload.new_line_name;
        if (c.payload?.station_name) return c.payload.station_name;
        if (c.payload?.material_name) return c.payload.material_name;
        return $i18n.t(
            c.payload?.kind === 'new_line'
                ? 'editor.validate.kind_new'
                : 'editor.validate.kind_segment',
        );
    }
</script>

<svelte:head><title>{$i18n.t('editor.mine.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="mine-page">
        <PageHero title={$i18n.t('editor.mine.title')} subtitle={$i18n.t('editor.mine.subtitle')}>
            <div class="hero-actions">
                <Link href="/contribute/trace" class="vff-btn primary">
                    <i class="bi bi-bezier2" aria-hidden="true"></i>
                    {$i18n.t('editor.mine.new_trace')}
                </Link>
                <Link href="/contribute/edit-segment" class="vff-btn secondary">
                    <i class="bi bi-rulers" aria-hidden="true"></i>
                    {$i18n.t('editor.mine.new_segment')}
                </Link>
                <Link href="/contribute/event" class="vff-btn secondary">
                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                    {$i18n.t('proposals.mine.new_event')}
                </Link>
                <Link href="/contribute/media" class="vff-btn secondary">
                    <i class="bi bi-image" aria-hidden="true"></i>
                    {$i18n.t('proposals.mine.new_media')}
                </Link>
                <Link href="/contribute/material" class="vff-btn secondary">
                    <i class="bi bi-train-front" aria-hidden="true"></i>
                    {$i18n.t('proposals.mine.new_material')}
                </Link>
            </div>
        </PageHero>

        <div class="vff-container body">
            {#if contributions.length === 0}
                <EmptyState
                    icon="bi-pencil-square"
                    title={$i18n.t('editor.mine.empty_title')}
                    text={$i18n.t('editor.mine.empty_text')}
                    headingLevel={2}
                />
            {:else}
                <ol class="list" aria-label={$i18n.t('editor.mine.list_label')}>
                    {#each contributions as c (c.id)}
                        {@const changes = PROPOSAL_TYPES.includes(c.type)
                            ? []
                            : changeEntries(c.payload?.changes, tr, $i18n.language)}
                        <li class="item" id="contribution-{c.id}">
                            <div class="head">
                                <h2>
                                    {title(c)}
                                    {#if c.payload?.line_code}
                                        <span class="vff-plate">{c.payload.line_code}</span>
                                    {/if}
                                </h2>
                                <span class="vff-badge {STATUS_TONE[c.status] ?? 'muted'}">
                                    <i
                                        class="bi {STATUS_ICON[c.status] ?? 'bi-dot'}"
                                        aria-hidden="true"
                                    ></i>
                                    {statusLabel(c.status)}
                                </span>
                            </div>

                            {#if c.type === 'line_trace'}
                                <p class="facts">
                                    <span>
                                        {$i18n.t(
                                            c.payload.kind === 'new_line'
                                                ? 'editor.validate.kind_new'
                                                : 'editor.validate.kind_correction',
                                        )}
                                    </span>
                                    <span>
                                        {$i18n.t('editor.validate.portions_count', {
                                            count: c.payload.portions?.length ?? 0,
                                        })}
                                    </span>
                                    <span class="nowrap"
                                        >{formatKm(c.payload.length_m ?? 0, $i18n.language)}</span
                                    >
                                    <span>
                                        {$i18n.t('editor.portions.events_count', {
                                            count: c.payload.events_count ?? 0,
                                        })}
                                    </span>
                                    {#if c.payload.applied?.line_slug}
                                        <Link
                                            href="/lines/{c.payload.applied.line_slug}"
                                            class="vff-link"
                                            >{$i18n.t('editor.mine.view_line')}</Link
                                        >
                                    {/if}
                                </p>
                            {:else if PROPOSAL_TYPES.includes(c.type)}
                                {@const facts = proposalFacts(c, tr, $i18n.language).filter((f) =>
                                    SUMMARY_FACTS[c.type].includes(f.id),
                                )}
                                {@const materialSlug =
                                    c.payload.applied?.material_slug ?? c.payload.material_slug}
                                <p class="facts">
                                    <span>{$i18n.t(proposalKindKey(c))}</span>
                                    {#each facts as fact (fact.id)}
                                        <span
                                            >{fact.label}{colon($i18n.language)}<span class="value"
                                                >{fact.text}</span
                                            ></span
                                        >
                                    {/each}
                                    {#if c.type === EVENT_TYPE && c.payload.applied && c.payload.line_slug}
                                        <Link href="/lines/{c.payload.line_slug}" class="vff-link"
                                            >{$i18n.t('proposals.mine.view_event')}</Link
                                        >
                                    {:else if (c.type === LINE_MATERIAL_TYPE || c.type === SERIES_TYPE) && c.payload.applied && materialSlug}
                                        <Link href="/materials/{materialSlug}" class="vff-link"
                                            >{$i18n.t('proposals.mine.view_material')}</Link
                                        >
                                    {/if}
                                </p>
                            {:else if c.payload?.pk_start_m != null}
                                <p class="facts">
                                    <span>
                                        {$i18n.t('editor.mine.portion')}{colon($i18n.language)}<span
                                            class="pk"
                                            >PK {formatPk(c.payload.pk_start_m)} → PK {formatPk(
                                                c.payload.pk_end_m,
                                            )}</span
                                        >
                                    </span>
                                    <span>
                                        {$i18n.t('editor.mine.era')}{colon($i18n.language)}<span
                                            class="nowrap">{eraLabel(c.payload.era_year, tr)}</span
                                        >
                                    </span>
                                </p>
                            {/if}

                            {#if changes.length}
                                <dl class="changes">
                                    {#each changes as ch (ch.field)}
                                        <div>
                                            <dt>{ch.label}</dt>
                                            <dd>{ch.value}</dd>
                                        </div>
                                    {/each}
                                </dl>
                            {/if}

                            {#if c.status === 'rejected' && c.review_notes}
                                <div class="reject">
                                    <p class="reject-title">
                                        {$i18n.t('editor.mine.reject_reason')}
                                    </p>
                                    <p>{c.review_notes}</p>
                                </div>
                            {/if}

                            <p class="meta">
                                <span
                                    >{$i18n.t('editor.mine.submitted', {
                                        date: formatDay(c.created_at, $i18n.language),
                                    })}</span
                                >
                                {#if c.status === 'pending' || c.status === 'approved'}
                                    <span
                                        >{$i18n.t('editor.mine.approvals', {
                                            count: c.approval_count ?? 0,
                                            threshold: THRESHOLD,
                                        })}</span
                                    >
                                {/if}
                                {#if c.reviewed_at}
                                    <span
                                        >{$i18n.t('editor.mine.reviewed', {
                                            date: formatDay(c.reviewed_at, $i18n.language),
                                        })}</span
                                    >
                                {/if}
                            </p>
                        </li>
                    {/each}
                </ol>
            {/if}
        </div>
    </main>
</Layout>

<style>
    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
        margin-top: var(--vff-space-5);
    }
    .body {
        padding-block: var(--vff-section-space);
    }
    .list {
        max-width: 880px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .item {
        scroll-margin-top: calc(var(--vff-header-h) + var(--vff-space-4));
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        padding-block: var(--vff-space-5);
        border-bottom: 1px solid var(--vff-border);
    }
    .head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--vff-space-2) var(--vff-space-4);
    }
    .head h2 {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
        min-width: 0;
        margin: 0;
        font-size: var(--vff-fs-h3);
        line-height: 1.35;
        text-wrap: balance;
    }
    .head h2 :global(.vff-plate) {
        font-size: var(--vff-fs-xs);
    }
    .facts {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-1) var(--vff-space-4);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .value {
        color: var(--vff-text);
    }
    .pk,
    .nowrap {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
    }
    .changes {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: var(--vff-space-3) var(--vff-space-6);
        margin: 0;
        padding: var(--vff-space-3) var(--vff-space-4);
        background-color: var(--vff-bg-subtle);
        border-radius: var(--vff-radius-md);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .changes dt {
        color: var(--vff-text-muted);
    }
    .changes dd {
        margin: 2px 0 0;
        font-weight: 600;
        color: var(--vff-text);
    }
    .reject {
        padding: var(--vff-space-3) var(--vff-space-4);
        border: 1px solid color-mix(in srgb, var(--vff-danger) 28%, transparent);
        background-color: var(--vff-danger-bg);
        border-radius: var(--vff-radius-md);
        font-size: var(--vff-fs-sm);
    }
    .reject p {
        margin: 0;
        overflow-wrap: anywhere;
    }
    .reject-title {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        color: var(--vff-danger);
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-1) var(--vff-space-4);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    @media (max-width: 600px) {
        .changes {
            grid-template-columns: 1fr;
        }
    }
</style>
