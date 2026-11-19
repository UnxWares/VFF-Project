<script>
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import { i18n } from '$lib/i18n.js';
    import { colon } from '$lib/typo.js';
    import { formatKm } from '$lib/traceGeometry.js';
    import {
        changeEntries,
        eraLabel,
        formatPk,
        isHttpUrl,
        readableUrl,
    } from '$components/Contribute/segmentChanges.js';
    import {
        EVENT_TYPE,
        MEDIA_TYPE,
        eventFacts,
        mediaFacts,
    } from '$components/Contribute/proposals.js';

    let { contribution } = $props();

    const p = $derived(contribution.payload ?? {});
    const isTrace = $derived(contribution.type === 'line_trace');
    const isEvent = $derived(contribution.type === EVENT_TYPE);
    const isMedia = $derived(contribution.type === MEDIA_TYPE);
    /** Traduction passée aux fonctions de segmentChanges.js. */
    const tr = (key, opts) => $i18n.t(key, opts);
    const changes = $derived(changeEntries(p.changes, tr, $i18n.language));
    // Événement daté ou image : les faits proposés, comme les voient les validateurs.
    const facts = $derived(
        isEvent
            ? eventFacts(p, tr, $i18n.language)
            : isMedia
              ? mediaFacts(p, tr, $i18n.language)
              : [],
    );
</script>

<svelte:head><title>{$i18n.t('editor.thanks.meta_title')} - VFF</title></svelte:head>

<Layout compactHeader>
    <main class="thanks-page">
        <PageHero
            title={$i18n.t('editor.thanks.title')}
            subtitle={$i18n.t('editor.thanks.subtitle')}
        />

        <div class="vff-container body">
            <section class="recap-block" aria-labelledby="recap-title">
                <h2 id="recap-title" class="vff-section-title">{$i18n.t('editor.thanks.recap')}</h2>
                <dl class="recap">
                    {#if !isMedia}
                        <div>
                            <dt>{$i18n.t('editor.thanks.line')}</dt>
                            <dd>
                                {p.line_name ?? p.new_line_name ?? ''}
                                {#if p.line_code}<span class="vff-plate">{p.line_code}</span>{/if}
                            </dd>
                        </div>
                    {/if}
                    {#if isEvent || isMedia}
                        {#each facts as fact (fact.id)}
                            <div>
                                <dt>{fact.label}</dt>
                                <dd class="wrap">
                                    {fact.text}{#if fact.note}<span class="muted"
                                            >, {fact.note}</span
                                        >{/if}
                                </dd>
                            </div>
                        {/each}
                    {:else if isTrace}
                        <div>
                            <dt>{$i18n.t('editor.thanks.kind')}</dt>
                            <dd>
                                {$i18n.t(
                                    p.kind === 'new_line'
                                        ? 'editor.validate.kind_new'
                                        : 'editor.validate.kind_correction',
                                )}, {$i18n
                                    .t(`editor.networks.${p.network}`)
                                    .toLocaleLowerCase($i18n.language)}
                            </dd>
                        </div>
                        <div>
                            <dt>{$i18n.t('editor.thanks.portions')}</dt>
                            <dd>
                                {$i18n.t('editor.validate.portions_count', {
                                    count: p.portions?.length ?? 0,
                                })},
                                <span class="nowrap"
                                    >{formatKm(p.length_m ?? 0, $i18n.language)}</span
                                >,
                                {$i18n.t('editor.portions.events_count', {
                                    count: p.events_count ?? 0,
                                })}
                            </dd>
                        </div>
                        <div>
                            <dt>{$i18n.t('editor.form.trace_source')}</dt>
                            <dd class="wrap">{p.trace_source}</dd>
                        </div>
                    {:else}
                        <div>
                            <dt>{$i18n.t('editor.thanks.era')}</dt>
                            <dd>{eraLabel(p.era_year, tr)}</dd>
                        </div>
                        <div>
                            <dt>{$i18n.t('editor.thanks.portion')}</dt>
                            <dd class="nowrap">
                                PK {formatPk(p.pk_start_m)} → PK {formatPk(p.pk_end_m)}
                            </dd>
                        </div>
                        {#if changes.length}
                            <div>
                                <dt>{$i18n.t('editor.thanks.changes')}</dt>
                                <dd>
                                    <ul class="changes">
                                        {#each changes as ch (ch.field)}
                                            <li>
                                                <span class="muted"
                                                    >{ch.label}{colon($i18n.language)}</span
                                                >{ch.value}
                                            </li>
                                        {/each}
                                    </ul>
                                </dd>
                            </div>
                        {/if}
                        {#if (p.sources ?? []).length}
                            <div>
                                <dt>{$i18n.t('editor.thanks.sources')}</dt>
                                <dd>
                                    <ul class="sources">
                                        {#each p.sources as src, i (i)}
                                            <li class="wrap">
                                                {#if isHttpUrl(src)}
                                                    <a
                                                        href={src}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        >{readableUrl(src)}<i
                                                            class="bi bi-box-arrow-up-right vff-ext"
                                                            aria-hidden="true"
                                                        ></i><span class="vff-sr-only">
                                                            ({$i18n.t('common.new_tab')})</span
                                                        ></a
                                                    >
                                                {:else}
                                                    {src}
                                                {/if}
                                            </li>
                                        {/each}
                                    </ul>
                                </dd>
                            </div>
                        {/if}
                    {/if}
                    {#if p.notes}
                        <div>
                            <dt>{$i18n.t('editor.thanks.notes')}</dt>
                            <dd class="wrap">{p.notes}</dd>
                        </div>
                    {/if}
                </dl>
                {#if isTrace}<p class="moderation">{$i18n.t('editor.thanks.moderation')}</p>{/if}
                {#if isEvent}<p class="moderation">
                        {$i18n.t('proposals.thanks.event_moderation')}
                    </p>{/if}
                {#if isMedia}<p class="moderation">
                        {$i18n.t('proposals.thanks.media_moderation')}
                    </p>{/if}
            </section>

            <div class="actions">
                {#if isEvent || isMedia}
                    <Link href="/contribute/mine" class="vff-btn primary"
                        ><i class="bi bi-list-check" aria-hidden="true"></i>
                        {$i18n.t('editor.thanks.mine')}</Link
                    >
                    <Link
                        href={isEvent ? '/contribute/event' : '/contribute/media'}
                        class="vff-btn secondary"
                        >{$i18n.t(
                            isEvent ? 'proposals.thanks.new_event' : 'proposals.thanks.new_media',
                        )}</Link
                    >
                {:else if isTrace}
                    <Link href="/contribute/mine" class="vff-btn primary"
                        ><i class="bi bi-list-check" aria-hidden="true"></i>
                        {$i18n.t('editor.thanks.mine')}</Link
                    >
                    <Link href="/contribute/trace" class="vff-btn secondary"
                        ><i class="bi bi-bezier2" aria-hidden="true"></i>
                        {$i18n.t('editor.thanks.new_trace')}</Link
                    >
                {:else}
                    <Link href="/contribute/mine" class="vff-btn primary"
                        ><i class="bi bi-list-check" aria-hidden="true"></i>
                        {$i18n.t('editor.thanks.mine')}</Link
                    >
                    <Link href="/contribute/edit-segment" class="vff-btn secondary"
                        ><i class="bi bi-rulers" aria-hidden="true"></i>
                        {$i18n.t('editor.thanks.new')}</Link
                    >
                {/if}
                <Link href="/maps" class="vff-btn quiet"
                    ><i class="bi bi-map" aria-hidden="true"></i>
                    {$i18n.t('editor.thanks.back_to_map')}</Link
                >
            </div>
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
    .recap-block {
        max-width: 760px;
    }
    .recap {
        margin: 0;
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        line-height: var(--vff-lh-ui);
    }
    .recap > div {
        display: grid;
        grid-template-columns: minmax(0, 180px) minmax(0, 1fr);
        gap: var(--vff-space-2) var(--vff-space-5);
        padding-block: var(--vff-space-3);
        border-bottom: 1px solid var(--vff-border);
    }
    .recap dt {
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text-muted);
    }
    .recap dd {
        min-width: 0;
        margin: 0;
        color: var(--vff-text);
    }
    .recap dd :global(.vff-plate) {
        margin-left: var(--vff-space-2);
        font-size: var(--vff-fs-xs);
    }
    .nowrap {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .wrap {
        overflow-wrap: anywhere;
    }
    .muted {
        color: var(--vff-text-muted);
    }
    .changes,
    .sources {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-1);
    }
    .moderation {
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }
    @media (max-width: 600px) {
        .recap > div {
            grid-template-columns: minmax(0, 1fr);
            gap: 2px;
        }
    }
</style>
