<script>
    import { Link, router, page } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import ReviewNav from '$components/Contribute/ReviewNav.svelte';
    import TraceDiffMap from '$components/Contribute/TraceDiffMap.svelte';
    import ProposalReview from '$components/Contribute/ProposalReview.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import Field from '$components/ui/Field.svelte';
    import Notice from '$components/ui/Notice.svelte';
    import ReputationInline from '$components/Reputation/ReputationInline.svelte';
    import { i18n } from '$lib/i18n.js';
    import { REASON_MAX, REASON_MIN, isReasonMissing } from '$lib/rejectReason.js';
    import { findHistoricBasemap, historicTitle } from '$lib/historicBasemaps.js';
    import { formatKm, portionColor } from '$lib/traceGeometry.js';
    import { colon } from '$lib/typo.js';
    import { formatStationDate } from '$lib/stationDates.js';
    import { EVENT_TYPE, MEDIA_TYPE } from '$components/Contribute/proposals.js';
    import {
        changeEntries,
        eraLabel,
        formatPk,
        isHttpUrl,
        readableUrl,
    } from '$components/Contribute/segmentChanges.js';

    let { contributions = [], threshold = 2 } = $props();

    const flash = $derived(page.props.flash ?? {});
    /** Traduction passée aux fonctions de segmentChanges.js. */
    const tr = (key, opts) => String($i18n.t(key, opts));

    // Refus : un seul formulaire ouvert à la fois (motif obligatoire), au lieu d'une boîte du navigateur.
    let rejecting = $state(null);
    let rejectNotes = $state('');
    let rejectAttempted = $state(false);
    /** Erreur renvoyée par le serveur pour le motif (le formulaire ouvert seulement). */
    let rejectServerError = $state(null);
    const rejectMissing = $derived(rejectAttempted && isReasonMissing(rejectNotes));
    let processing = $state(false);

    function approve(c) {
        const key =
            c.approval_count + 1 >= threshold
                ? 'editor.validate.confirm_apply'
                : 'editor.validate.confirm_approve';
        if (!confirm($i18n.t(key))) return;
        router.post(
            `/validate/${c.id}/approve`,
            {},
            {
                preserveScroll: true,
                onStart: () => (processing = true),
                onFinish: () => (processing = false),
            },
        );
    }

    async function openReject(c) {
        rejecting = c.id;
        rejectNotes = '';
        rejectAttempted = false;
        rejectServerError = null;
        await tick();
        document.getElementById(`reject-notes-${c.id}`)?.focus();
    }

    function cancelReject(c) {
        rejecting = null;
        document.getElementById(`reject-btn-${c.id}`)?.focus();
    }

    /** Message d'erreur du motif, affiché seulement sur le formulaire de refus ouvert. */
    function rejectError(id) {
        if (rejecting !== id) return null;
        if (rejectMissing)
            return $i18n.t('editor.validate.errors.reason_required', { min: REASON_MIN });
        return rejectServerError;
    }

    function submitReject(e, c) {
        e.preventDefault();
        const notes = rejectNotes.trim();
        // Le serveur exige le même motif : on évite l'aller-retour quand il manque.
        rejectAttempted = true;
        rejectServerError = null;
        if (isReasonMissing(notes)) {
            document.getElementById(`reject-notes-${c.id}`)?.focus();
            return;
        }
        router.post(
            `/validate/${c.id}/reject`,
            { notes },
            {
                preserveScroll: true,
                onStart: () => (processing = true),
                onFinish: () => (processing = false),
                onSuccess: () => (rejecting = null),
                onError: (errors) => {
                    rejectServerError = errors.notes ?? null;
                    document.getElementById(`reject-notes-${c.id}`)?.focus();
                },
            },
        );
    }

    function typeLabel(t) {
        const key = `editor.event_types.${t}`;
        const label = $i18n.t(key);
        return label === key ? t : label;
    }

    function formatDate(ev) {
        if (ev.precision === 'year') return ev.date.slice(0, 4);
        if (ev.precision === 'month') return ev.date.slice(0, 7);
        return ev.date;
    }

    function portionName(portions, key) {
        const i = (portions ?? []).findIndex((p) => p.key === key);
        return i < 0 ? key : portions[i].label || `#${i + 1}`;
    }

    function basemapLabel(key) {
        const def = findHistoricBasemap(key);
        return def ? `${def.provider} - ${historicTitle(def)}` : null;
    }
</script>

<svelte:head><title>{$i18n.t('editor.validate.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="validate-page">
        <PageHero
            title={$i18n.t('editor.validate.title')}
            subtitle={$i18n.t('editor.validate.subtitle', {
                count: contributions.length,
                threshold,
            })}
        >
            <ReviewNav current="contributions" />
        </PageHero>

        <div class="vff-container body">
            <div class="flash">
                {#if flash.success}<Notice tone="success">{flash.success}</Notice>{/if}
                {#if flash.error}<Notice tone="danger">{flash.error}</Notice>{/if}
            </div>

            {#if contributions.length === 0}
                <EmptyState
                    icon="bi-inbox"
                    title={$i18n.t('editor.validate.empty')}
                    text={$i18n.t('editor.validate.empty_text')}
                    headingLevel={2}
                />
            {:else}
                <div class="list">
                    {#each contributions as c (c.id)}
                        {@const lineName =
                            c.target?.name ??
                            c.payload.line_name ??
                            c.payload.new_line_name ??
                            c.payload.station_name}
                        {@const lineCode = c.target?.code ?? c.payload.line_code}
                        {@const lineSlug = c.target?.slug ?? c.payload.line_slug}
                        {@const sheetHref = c.payload.station_slug
                            ? `/stations/${c.payload.station_slug}`
                            : lineSlug
                              ? `/lines/${lineSlug}`
                              : null}
                        <article
                            class="contrib"
                            id="contribution-{c.id}"
                            aria-labelledby="contrib-{c.id}-title"
                        >
                            <div class="contrib-head">
                                <div class="head-text">
                                    <p class="kind">
                                        {#if c.type === 'line_trace'}
                                            <i class="bi bi-bezier2" aria-hidden="true"></i>
                                            {$i18n.t(
                                                c.payload.kind === 'new_line'
                                                    ? 'editor.validate.kind_new'
                                                    : 'editor.validate.kind_correction',
                                            )}
                                        {:else if c.type === EVENT_TYPE}
                                            <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                            {$i18n.t('proposals.review.kind_event')}
                                        {:else if c.type === MEDIA_TYPE}
                                            <i class="bi bi-image" aria-hidden="true"></i>
                                            {$i18n.t(
                                                c.payload.source === 'commons'
                                                    ? 'proposals.review.kind_media_commons'
                                                    : 'proposals.review.kind_media_upload',
                                            )}
                                        {:else}
                                            <i class="bi bi-rulers" aria-hidden="true"></i>
                                            {$i18n.t('editor.validate.kind_segment')}
                                        {/if}
                                    </p>
                                    <h2 id="contrib-{c.id}-title">
                                        {lineName}
                                        {#if lineCode}<span class="vff-plate">{lineCode}</span>{/if}
                                    </h2>
                                    <p class="meta">
                                        <span
                                            >{$i18n.t('reputation.validate.by', {
                                                name: c.user.name,
                                            })}
                                            <ReputationInline
                                                reputation={c.user.reputation}
                                            /></span
                                        >
                                        <span
                                            >{$i18n.t('editor.validate.submitted', {
                                                date: c.created_at,
                                            })}</span
                                        >
                                        <strong
                                            >{$i18n.t('editor.validate.approvals', {
                                                count: c.approval_count,
                                                threshold,
                                            })}</strong
                                        >
                                    </p>
                                </div>
                                <span class="vff-badge accent">
                                    {#if c.type === 'line_trace'}
                                        {$i18n.t(`editor.networks.${c.payload.network}`)}
                                    {:else if c.type === EVENT_TYPE}
                                        {formatStationDate(
                                            c.payload.date,
                                            c.payload.precision,
                                            null,
                                            tr,
                                            $i18n.language,
                                        )}
                                    {:else if c.type === MEDIA_TYPE}
                                        {$i18n.t(
                                            `proposals.media.targets.${c.payload.target_type ?? 'line'}`,
                                        )}
                                    {:else}
                                        {eraLabel(c.payload.era_year, tr)}
                                    {/if}
                                </span>
                            </div>

                            <div class="contrib-body">
                                {#if c.type === EVENT_TYPE || c.type === MEDIA_TYPE}
                                    <ProposalReview contribution={c} />
                                {:else if c.type === 'line_trace'}
                                    <TraceDiffMap
                                        portions={c.payload.portions ?? []}
                                        before={c.payload.before}
                                        current={c.current}
                                        basemap={c.payload.basemap}
                                    />

                                    <dl class="facts">
                                        <div>
                                            <dt>{$i18n.t('editor.validate.length')}</dt>
                                            <dd>
                                                {formatKm(c.payload.length_m ?? 0, $i18n.language)},
                                                {$i18n.t('editor.validate.portions_count', {
                                                    count: c.payload.portions?.length ?? 0,
                                                })}
                                            </dd>
                                        </div>
                                        {#if c.payload.kind === 'correction' && c.payload.line_network_before && c.payload.line_network_before !== c.payload.network}
                                            <div>
                                                <dt>{$i18n.t('editor.validate.network')}</dt>
                                                <dd class="changed">
                                                    {$i18n.t(
                                                        `editor.networks.${c.payload.line_network_before}`,
                                                    )} → {$i18n.t(
                                                        `editor.networks.${c.payload.network}`,
                                                    )}
                                                </dd>
                                            </div>
                                        {/if}
                                        <div>
                                            <dt>{$i18n.t('editor.validate.trace_source')}</dt>
                                            <dd class="wrap">{c.payload.trace_source}</dd>
                                        </div>
                                        {#if basemapLabel(c.payload.basemap)}
                                            <div>
                                                <dt>{$i18n.t('editor.validate.basemap')}</dt>
                                                <dd>{basemapLabel(c.payload.basemap)}</dd>
                                            </div>
                                        {/if}
                                    </dl>

                                    {#if (c.payload.warnings ?? []).length}
                                        <Notice tone="warning" live="off">
                                            <ul class="warnings">
                                                {#each c.payload.warnings as w, wi (wi)}
                                                    <li>
                                                        {$i18n.t(`editor.warnings.${w.code}`, {
                                                            portion: portionName(
                                                                c.payload.portions,
                                                                w.portion,
                                                            ),
                                                        })}
                                                    </li>
                                                {/each}
                                            </ul>
                                        </Notice>
                                    {/if}

                                    <!-- Cadre défilant : focalisable pour défiler au clavier (GUIDE 5.8). -->
                                    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                                    <div
                                        class="vff-table-wrap"
                                        role="region"
                                        aria-label={$i18n.t('editor.portions.events_title')}
                                        tabindex="0"
                                    >
                                        <table class="vff-table events">
                                            <caption class="vff-sr-only"
                                                >{$i18n.t('editor.portions.events_title')}</caption
                                            >
                                            <thead>
                                                <tr>
                                                    <th scope="col"
                                                        >{$i18n.t('editor.validate.portion')}</th
                                                    >
                                                    <th scope="col"
                                                        >{$i18n.t('editor.events.type')}</th
                                                    >
                                                    <th scope="col"
                                                        >{$i18n.t('editor.events.date')}</th
                                                    >
                                                    <th scope="col"
                                                        >{$i18n.t('editor.events.source')}</th
                                                    >
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {#each c.payload.portions ?? [] as p, i (p.key)}
                                                    {#if p.events.length === 0}
                                                        <tr>
                                                            <th scope="row">
                                                                <span
                                                                    class="pdot"
                                                                    style:--c={portionColor(i)}
                                                                    aria-hidden="true"
                                                                ></span>{p.label || `#${i + 1}`}
                                                                <small
                                                                    >{formatKm(
                                                                        p.length_m,
                                                                        $i18n.language,
                                                                    )}</small
                                                                >
                                                            </th>
                                                            <td colspan="3" class="muted"
                                                                >{$i18n.t(
                                                                    'editor.validate.no_event',
                                                                )}</td
                                                            >
                                                        </tr>
                                                    {/if}
                                                    {#each p.events as ev, j (j)}
                                                        <tr>
                                                            {#if j === 0}
                                                                <th
                                                                    scope="rowgroup"
                                                                    rowspan={p.events.length}
                                                                >
                                                                    <span
                                                                        class="pdot"
                                                                        style:--c={portionColor(i)}
                                                                        aria-hidden="true"
                                                                    ></span>{p.label || `#${i + 1}`}
                                                                    <small
                                                                        >{formatKm(
                                                                            p.length_m,
                                                                            $i18n.language,
                                                                        )}{#if p.from || p.to}, {p.from ??
                                                                                '?'} → {p.to ??
                                                                                '?'}{/if}</small
                                                                    >
                                                                </th>
                                                            {/if}
                                                            <td>{typeLabel(ev.type)}</td>
                                                            <td class="date"
                                                                >{formatDate(ev)}
                                                                <small
                                                                    >{$i18n.t(
                                                                        `editor.precisions.${ev.precision}`,
                                                                    )}</small
                                                                ></td
                                                            >
                                                            <td class="wrap">
                                                                {ev.source}
                                                                {#if ev.source_url}<a
                                                                        href={ev.source_url}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer nofollow"
                                                                        class="ext-link"
                                                                        ><i
                                                                            class="bi bi-box-arrow-up-right"
                                                                            aria-hidden="true"
                                                                        ></i><span
                                                                            class="vff-sr-only"
                                                                            >{$i18n.t(
                                                                                'editor.validate.event_link',
                                                                            )}
                                                                            ({$i18n.t(
                                                                                'common.new_tab',
                                                                            )})</span
                                                                        ></a
                                                                    >{/if}
                                                                {#if ev.description}<small
                                                                        >{ev.description}</small
                                                                    >{/if}
                                                            </td>
                                                        </tr>
                                                    {/each}
                                                {/each}
                                            </tbody>
                                        </table>
                                    </div>

                                    {#if (c.nearby ?? []).length}
                                        <div class="nearby">
                                            <p class="sub">{$i18n.t('editor.validate.nearby')}</p>
                                            <ul>
                                                {#each c.nearby as n, ni (ni)}
                                                    <li>
                                                        {#if n.slug}<Link href="/lines/{n.slug}"
                                                                >{n.name}</Link
                                                            >{:else}{n.name}{/if}
                                                        <small>{n.code ?? ''} · {n.via}</small>
                                                    </li>
                                                {/each}
                                            </ul>
                                        </div>
                                    {/if}

                                    {#if c.payload.notes}<blockquote class="notes">
                                            {c.payload.notes}
                                        </blockquote>{/if}
                                {:else}
                                    {@const changes = changeEntries(
                                        c.payload.changes,
                                        tr,
                                        $i18n.language,
                                        c.current_values,
                                    )}
                                    <p class="pk-line">
                                        <span
                                            >{$i18n.t('editor.validate.pk_portion')}{colon(
                                                $i18n.language,
                                            )}<strong class="nowrap"
                                                >PK {formatPk(c.payload.pk_start_m)} → PK {formatPk(
                                                    c.payload.pk_end_m,
                                                )}</strong
                                            ></span
                                        >
                                        <span
                                            >{$i18n.t('editor.validate.pk_length')}{colon(
                                                $i18n.language,
                                            )}<strong class="nowrap"
                                                >{formatKm(
                                                    Math.abs(
                                                        c.payload.pk_end_m - c.payload.pk_start_m,
                                                    ),
                                                    $i18n.language,
                                                )}</strong
                                            ></span
                                        >
                                    </p>
                                    {#if changes.length}
                                        <div>
                                            <p class="sub">{$i18n.t('editor.validate.changes')}</p>
                                            <dl class="changes">
                                                {#each changes as ch (ch.field)}
                                                    <div>
                                                        <dt>{ch.label}</dt>
                                                        <dd>
                                                            {#if ch.before}
                                                                <span class="was"
                                                                    ><span class="vff-sr-only"
                                                                        >{$i18n.t(
                                                                            'editor.validate.current_value',
                                                                        )}{colon(
                                                                            $i18n.language,
                                                                        )}</span
                                                                    >{ch.before}</span
                                                                ><span aria-hidden="true">
                                                                    →
                                                                </span><span class="vff-sr-only"
                                                                    >{$i18n.t(
                                                                        'editor.validate.proposed_value',
                                                                    )}{colon($i18n.language)}</span
                                                                >
                                                            {/if}{ch.value}
                                                        </dd>
                                                    </div>
                                                {/each}
                                            </dl>
                                        </div>
                                    {/if}
                                    {#if c.payload.notes}<blockquote class="notes">
                                            {c.payload.notes}
                                        </blockquote>{/if}
                                    {#if (c.payload.sources ?? []).length}
                                        <div>
                                            <p class="sub">{$i18n.t('editor.validate.sources')}</p>
                                            <ol class="sources">
                                                {#each c.payload.sources as src, i (i)}
                                                    <li class="wrap">
                                                        {#if isHttpUrl(src)}
                                                            <a
                                                                href={src}
                                                                target="_blank"
                                                                rel="noopener noreferrer nofollow"
                                                                >{readableUrl(src)}<i
                                                                    class="bi bi-box-arrow-up-right vff-ext"
                                                                    aria-hidden="true"
                                                                ></i><span class="vff-sr-only">
                                                                    ({$i18n.t(
                                                                        'common.new_tab',
                                                                    )})</span
                                                                ></a
                                                            >
                                                        {:else}
                                                            {src}
                                                        {/if}
                                                    </li>
                                                {/each}
                                            </ol>
                                        </div>
                                    {/if}
                                {/if}
                            </div>

                            <div class="contrib-actions">
                                {#if c.is_mine}
                                    <p class="own">
                                        <i class="bi bi-person-check" aria-hidden="true"></i>
                                        {$i18n.t('editor.validate.own')}
                                    </p>
                                {:else if c.approved_by_me}
                                    <p class="own">
                                        <i class="bi bi-check2-all" aria-hidden="true"></i>
                                        {$i18n.t('editor.validate.already')}
                                    </p>
                                {:else}
                                    <button
                                        type="button"
                                        class="vff-btn primary"
                                        onclick={() => approve(c)}
                                        disabled={processing}
                                    >
                                        <i class="bi bi-check2" aria-hidden="true"></i>
                                        {$i18n.t(
                                            c.approval_count + 1 >= threshold
                                                ? 'editor.validate.approve_apply'
                                                : 'editor.validate.approve',
                                        )}<span class="vff-sr-only"
                                            >{colon($i18n.language)}{lineName}</span
                                        >
                                    </button>
                                {/if}
                                {#if !c.is_mine && rejecting !== c.id}
                                    <button
                                        type="button"
                                        id="reject-btn-{c.id}"
                                        class="vff-btn danger"
                                        onclick={() => openReject(c)}
                                    >
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                        {$i18n.t('editor.validate.reject')}<span class="vff-sr-only"
                                            >{colon($i18n.language)}{lineName}</span
                                        >
                                    </button>
                                {/if}
                                {#if sheetHref}
                                    <Link href={sheetHref} class="vff-link sheet-link">
                                        {$i18n.t('editor.validate.view_sheet')}<span
                                            class="vff-sr-only"
                                            >{colon($i18n.language)}{lineName}</span
                                        >
                                    </Link>
                                {/if}
                            </div>

                            {#if rejecting === c.id}
                                <form
                                    class="reject-form"
                                    novalidate
                                    onsubmit={(e) => submitReject(e, c)}
                                >
                                    <Field
                                        id="reject-notes-{c.id}"
                                        label={$i18n.t('editor.validate.reject_reason')}
                                        error={rejectError(c.id)}
                                        required
                                    >
                                        {#snippet children(attrs)}
                                            <textarea
                                                {...attrs}
                                                class="vff-textarea"
                                                rows="3"
                                                minlength={REASON_MIN}
                                                maxlength={REASON_MAX}
                                                required
                                                bind:value={rejectNotes}
                                                onkeydown={(e) =>
                                                    e.key === 'Escape' && cancelReject(c)}
                                            ></textarea>
                                        {/snippet}
                                    </Field>
                                    <div class="reject-actions">
                                        <button
                                            type="submit"
                                            class="vff-btn danger"
                                            disabled={processing}
                                        >
                                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                                            {$i18n.t('editor.validate.reject_confirm')}
                                        </button>
                                        <button
                                            type="button"
                                            class="vff-btn quiet"
                                            onclick={() => cancelReject(c)}
                                        >
                                            {$i18n.t('editor.validate.cancel')}
                                        </button>
                                    </div>
                                </form>
                            {/if}
                        </article>
                    {/each}
                </div>
            {/if}
        </div>
    </main>
</Layout>

<style>
    .body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
        padding-block: var(--vff-section-space);
    }
    .flash:empty {
        display: none;
    }
    .flash {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        max-width: 760px;
    }
    .list {
        display: flex;
        flex-direction: column;
        gap: var(--vff-block-space);
    }
    .contrib {
        scroll-margin-top: calc(var(--vff-header-h) + var(--vff-space-4));
        min-width: 0;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg);
    }
    .contrib-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--vff-space-3) var(--vff-space-5);
        padding: var(--vff-space-5);
        border-bottom: 1px solid var(--vff-border);
    }
    .head-text {
        min-width: 0;
    }
    .kind {
        display: flex;
        align-items: center;
        gap: var(--vff-space-2);
        margin: 0 0 var(--vff-space-1);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-accent-ink);
    }
    .contrib-head h2 {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-2) var(--vff-space-3);
        margin: 0;
        font-size: var(--vff-fs-h3);
        line-height: 1.35;
        text-wrap: balance;
    }
    .contrib-head h2 :global(.vff-plate) {
        font-size: var(--vff-fs-xs);
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-1) var(--vff-space-4);
        margin: var(--vff-space-2) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .meta strong {
        font-weight: 600;
        color: var(--vff-text);
    }
    .contrib-body {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        padding: var(--vff-space-5);
    }
    .sub {
        margin: 0 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
    }
    .facts,
    .changes {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: var(--vff-space-3) var(--vff-space-6);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .changes {
        padding: var(--vff-space-3) var(--vff-space-4);
        background-color: var(--vff-bg-subtle);
        border-radius: var(--vff-radius-md);
    }
    .facts dt,
    .changes dt {
        color: var(--vff-text-muted);
    }
    .facts dd,
    .changes dd {
        margin: 2px 0 0;
        color: var(--vff-text);
    }
    .changes dd,
    .facts dd.changed {
        font-weight: 600;
    }
    /* Valeur actuelle de la portion, avant la flèche et la valeur proposée. */
    .changes .was {
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .warnings {
        margin: 0;
        padding-left: 1.2em;
    }
    .events th[scope='row'],
    .events th[scope='rowgroup'] {
        font-weight: 600;
        min-width: 160px;
    }
    .events small {
        display: block;
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .events .date {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .ext-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        min-height: 24px;
        margin-left: 2px;
    }
    .pdot {
        display: inline-block;
        width: 10px;
        height: 10px;
        margin-right: var(--vff-space-2);
        border-radius: 50%;
        background: var(--c);
    }
    .muted {
        color: var(--vff-text-muted);
    }
    .nearby {
        font-size: var(--vff-fs-sm);
    }
    .nearby ul,
    .sources {
        margin: 0;
        padding-left: 1.2em;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-1);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .nearby small {
        margin-left: var(--vff-space-1);
        color: var(--vff-text-muted);
    }
    .pk-line {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-1) var(--vff-space-5);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .pk-line strong {
        color: var(--vff-text);
        font-weight: 600;
    }
    .nowrap {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .wrap {
        overflow-wrap: anywhere;
    }
    .notes {
        margin: 0;
        max-width: var(--vff-measure);
        padding: var(--vff-space-3) var(--vff-space-4);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-subtle);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        overflow-wrap: anywhere;
    }
    .contrib-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
        padding: var(--vff-space-4) var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
        background-color: var(--vff-bg-subtle);
        border-radius: 0 0 var(--vff-radius-md) var(--vff-radius-md);
    }
    .own {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .contrib-actions :global(.sheet-link) {
        margin-left: auto;
    }
    .reject-form {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        max-width: 640px;
        padding: var(--vff-space-4) var(--vff-space-5) var(--vff-space-5);
        border-top: 1px solid var(--vff-border);
    }
    .reject-actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
        margin-top: var(--vff-space-2);
    }
    @media (max-width: 600px) {
        .contrib-head,
        .contrib-body {
            padding: var(--vff-space-4);
        }
        .contrib-actions {
            padding: var(--vff-space-4);
        }
        .contrib-actions :global(.sheet-link) {
            margin-left: 0;
        }
        .facts,
        .changes {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
