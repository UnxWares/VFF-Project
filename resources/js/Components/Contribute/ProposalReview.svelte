<script>
    /**
     * Corps d'une proposition « événement daté » ou « image » dans l'espace de validation : les faits
     * proposés, la source, la note de l'auteur ; pour un événement, ceux du même genre déjà connus
     * sur la ligne ; pour une image, l'image elle-même (miniature de Commons, ou fichier déposé lu
     * par son adresse privée) et les métadonnées retirées.
     *
     * Props : `contribution` (élément de ContributionController::validateList).
     */
    import { i18n } from '$lib/i18n.js';
    import { formatStationDate } from '$lib/stationDates.js';
    import {
        EVENT_TYPE,
        eventFacts,
        eventTypeLabel,
        mediaFacts,
        portionText,
        removedMetadataText,
    } from './proposals.js';

    /** @type {{ contribution: any }} */
    let { contribution } = $props();

    const tr = (key, opts) => String($i18n.t(key, opts));
    const p = $derived(contribution.payload ?? {});
    const isEvent = $derived(contribution.type === EVENT_TYPE);
    const facts = $derived(
        isEvent ? eventFacts(p, tr, $i18n.language) : mediaFacts(p, tr, $i18n.language),
    );
    const commons = $derived(!isEvent && p.source === 'commons' ? (p.commons ?? {}) : null);
    const imageSrc = $derived(commons ? commons.thumb_url : contribution.file_url);
    const imageHref = $derived(commons ? commons.source_url : contribution.file_url);
    const imageTitle = $derived(commons?.title ?? p.title ?? '');
    const similar = $derived(contribution.similar_events ?? []);

    /**
     * Portion d'un événement déjà connu, sous la forme des propositions (lieux, PK, toute la ligne).
     * @param {{ from: string|null, to: string|null, pk_start_m: number|null, pk_end_m: number|null }} e
     */
    function portionOf(e) {
        if (e.from && e.to) return { kind: 'places', from: e.from, to: e.to };
        if (e.pk_start_m !== null && e.pk_end_m !== null)
            return { kind: 'pk', pk_start_m: e.pk_start_m, pk_end_m: e.pk_end_m };
        return null;
    }
</script>

<div class="review">
    {#if !isEvent}
        {#if imageSrc}
            <figure class="image">
                <img
                    src={imageSrc}
                    alt={$i18n.t('proposals.review.image_alt', { title: imageTitle })}
                    loading="lazy"
                />
                <figcaption>
                    <a class="vff-link" href={imageHref} target="_blank" rel="noopener noreferrer"
                        >{$i18n.t(
                            commons
                                ? 'proposals.review.commons_page'
                                : 'proposals.review.open_full',
                        )}<span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span><i
                            class="bi bi-box-arrow-up-right vff-ext"
                            aria-hidden="true"
                        ></i></a
                    >
                </figcaption>
            </figure>
        {:else}
            <p class="muted">{$i18n.t('proposals.review.file_unavailable')}</p>
        {/if}
    {/if}

    <dl class="facts">
        {#each facts as fact (fact.id)}
            <div>
                <dt>{fact.label}</dt>
                <dd class="wrap">
                    {#if fact.href}
                        <a
                            class="vff-link"
                            href={fact.href}
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            >{fact.text}<span class="vff-sr-only">
                                ({$i18n.t('common.new_tab')})</span
                            ><i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i></a
                        >
                    {:else}
                        {fact.text}
                    {/if}{#if fact.note}<span class="note">, {fact.note}</span>{/if}
                </dd>
            </div>
        {/each}
    </dl>

    {#if !isEvent && p.file}
        <p class="muted">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            {removedMetadataText(p.file.removed, tr)}
        </p>
    {/if}

    {#if p.notes}
        <blockquote class="notes" aria-label={$i18n.t('proposals.review.notes')}>
            {p.notes}
        </blockquote>
    {/if}

    {#if isEvent}
        <section class="similar" aria-labelledby="similar-{contribution.id}">
            <h3 id="similar-{contribution.id}">{$i18n.t('proposals.review.similar_title')}</h3>
            {#if similar.length}
                <!-- Cadre défilant : focalisable pour défiler au clavier (GUIDE 5.8). -->
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <div
                    class="vff-table-wrap"
                    role="region"
                    aria-labelledby="similar-{contribution.id}"
                    tabindex="0"
                >
                    <table class="vff-table">
                        <thead>
                            <tr>
                                <th scope="col">{$i18n.t('proposals.review.type')}</th>
                                <th scope="col">{$i18n.t('proposals.review.date')}</th>
                                <th scope="col">{$i18n.t('proposals.review.portion')}</th>
                                <th scope="col">{$i18n.t('proposals.review.source')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each similar as e (e.id)}
                                <tr>
                                    <th scope="row">{eventTypeLabel(e.type, tr)}</th>
                                    <td class="nowrap"
                                        >{formatStationDate(
                                            e.date,
                                            e.precision,
                                            null,
                                            tr,
                                            $i18n.language,
                                        )}</td
                                    >
                                    <td>{portionText(portionOf(e), tr)}</td>
                                    <td class="wrap">
                                        {#if e.source_url}<a
                                                href={e.source_url}
                                                target="_blank"
                                                rel="noopener noreferrer nofollow"
                                                >{e.source ?? e.source_url}<span
                                                    class="vff-sr-only"
                                                >
                                                    ({$i18n.t('common.new_tab')})</span
                                                ><i
                                                    class="bi bi-box-arrow-up-right vff-ext"
                                                    aria-hidden="true"
                                                ></i></a
                                            >{:else}{e.source ?? ''}{/if}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {:else}
                <p class="muted">{$i18n.t('proposals.review.similar_none')}</p>
            {/if}
        </section>
    {/if}
</div>

<style>
    .review {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
    }
    .image {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        margin: 0;
    }
    .image img {
        display: block;
        width: auto;
        max-width: 100%;
        height: auto;
        max-height: 24rem;
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-muted);
    }
    .image figcaption {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .facts {
        display: grid;
        grid-template-columns: minmax(0, 12rem) minmax(0, 1fr);
        gap: var(--vff-space-2) var(--vff-space-5);
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .facts > div {
        display: contents;
    }
    .facts dt {
        color: var(--vff-text-muted);
    }
    .facts dd {
        min-width: 0;
        margin: 0;
        color: var(--vff-text);
    }
    .wrap {
        overflow-wrap: anywhere;
    }
    .note {
        color: var(--vff-text-muted);
    }
    .nowrap {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .muted {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .notes {
        margin: 0;
        padding: var(--vff-space-3) var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-subtle);
        font-size: var(--vff-fs-sm);
        white-space: pre-line;
        overflow-wrap: anywhere;
    }
    .similar {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
    }
    .similar h3 {
        margin: 0;
        font-size: var(--vff-fs-md);
    }
    @media (max-width: 600px) {
        .facts {
            grid-template-columns: minmax(0, 1fr);
            gap: 2px;
        }
        .facts dd {
            margin-bottom: var(--vff-space-2);
        }
    }
</style>
