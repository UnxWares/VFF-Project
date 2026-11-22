<script>
    /**
     * Corps d'une proposition sourcée dans l'espace de validation : les faits proposés, la source,
     * la note de l'auteur ; pour un événement, ceux du même genre déjà connus sur la ligne ; pour une
     * image, l'image elle-même (miniature de Commons, ou fichier déposé lu par son adresse privée) et
     * les métadonnées retirées ; pour un matériel sur une ligne, l'exception aux règles d'écartement ou
     * d'électrification en évidence et les liens déjà connus ; pour une série, les valeurs proposées
     * (avant, après ; une valeur effacée est montrée comme telle), les champs changés depuis l'envoi et
     * les liens que la correction rendrait contraires aux règles. Chaque tableau a pour titre
     * l'intertitre qui le précède (aria-labelledby, RGAA 5.4).
     *
     * Props : `contribution` (élément de ContributionController::validateList).
     */
    import { i18n } from '$lib/i18n.js';
    import { commonsSrc } from '$lib/commonsImage.js';
    import Notice from '$components/ui/Notice.svelte';
    import { formatStationDate } from '$lib/stationDates.js';
    import { periodText } from '$lib/materialPeriods.js';
    import { formatLocale } from '$lib/typo.js';
    import { conciseAlt } from '$lib/altText.js';
    import {
        EVENT_TYPE,
        LINE_MATERIAL_TYPE,
        MEDIA_TYPE,
        SERIES_TYPE,
        eventTypeLabel,
        portionText,
        proposalFacts,
        removedMetadataText,
        seriesChanges,
        seriesValueText,
    } from './proposals.js';

    /** @type {{ contribution: any }} */
    let { contribution } = $props();

    const tr = (key, opts) => String($i18n.t(key, opts));
    const p = $derived(contribution.payload ?? {});
    const isEvent = $derived(contribution.type === EVENT_TYPE);
    const isMedia = $derived(contribution.type === MEDIA_TYPE);
    const isLineMaterial = $derived(contribution.type === LINE_MATERIAL_TYPE);
    const isSeries = $derived(contribution.type === SERIES_TYPE);
    // La nature de la proposition (nouvelle série, correction) est déjà dans l'en-tête de la carte.
    const facts = $derived(
        proposalFacts(contribution, tr, $i18n.language).filter((f) => f.id !== 'mode'),
    );
    const commons = $derived(isMedia && p.source === 'commons' ? (p.commons ?? {}) : null);
    const changes = $derived(isSeries ? seriesChanges(p, tr) : []);
    const context = $derived(contribution.material_context ?? { links: [], conflict_now: null });
    const seriesContext = $derived(contribution.series_context ?? { stale: [], impact: [] });

    /** Libellé d'une provenance ou d'une fiabilité de lien (repli : la valeur brute). */
    function linkLabel(group, value) {
        const key = `proposals.line_material.${group}.${value ?? 'none'}`;
        const text = tr(key);
        return text === key ? (value ?? '') : text;
    }
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
    {#if isLineMaterial}
        {#if p.exception}
            <Notice tone="warning" live="off" title={$i18n.t('proposals.review.exception_title')}>
                <p>
                    {$i18n.t(`proposals.review.exception_${p.exception.rule}`, {
                        reason: p.exception.reason,
                    })}
                </p>
            </Notice>
        {/if}
        {#if context.conflict_now && !p.exception}
            <Notice tone="danger" live="off" title={$i18n.t('proposals.review.conflict_now_title')}>
                <p>
                    {$i18n.t('proposals.review.conflict_now_text', {
                        reason: context.conflict_now,
                    })}
                </p>
            </Notice>
        {/if}
        {#if p.excluded}
            <Notice tone="warning" live="off" title={$i18n.t('proposals.review.excluded_title')}>
                <p>{p.excluded}</p>
            </Notice>
        {/if}
    {/if}

    {#if isSeries}
        {#each seriesContext.stale as st (st.field)}
            <Notice tone="danger" live="off" title={$i18n.t('proposals.review.stale_title')}>
                <p>
                    {$i18n.t('proposals.review.stale_text', {
                        field: tr(`proposals.review.fields.${st.field}`),
                        now: seriesValueText(st.field, st.now, tr),
                    })}
                </p>
            </Notice>
        {/each}
        {#if seriesContext.impact.length}
            <Notice tone="danger" live="off" title={$i18n.t('proposals.review.impact_title')}>
                <p>
                    {$i18n.t('proposals.review.impact_text', {
                        count: seriesContext.impact.length,
                    })}
                </p>
                <ul class="impact">
                    {#each seriesContext.impact as l, i (i)}
                        <li>
                            {$i18n.t('proposals.series.impact_link', {
                                line: [l.line_code, l.line_name].filter(Boolean).join(' '),
                                source: linkLabel('sources', l.source),
                                reason: l.reason,
                            })}
                        </li>
                    {/each}
                </ul>
            </Notice>
        {/if}
    {/if}

    {#if isMedia}
        {#if imageSrc}
            <!-- Pas d'élément figure : le lien qui suit n'est pas une légende de l'image (RGAA 1.9). -->
            <div class="image">
                <img
                    src={commonsSrc(imageSrc)}
                    alt={$i18n.t('proposals.review.image_alt', {
                        title: conciseAlt(imageTitle, 100),
                    })}
                    loading="lazy"
                />
                <p class="open">
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
                </p>
            </div>
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

    {#if isSeries && changes.length}
        <section class="similar" aria-labelledby="changes-{contribution.id}">
            <h3 id="changes-{contribution.id}">{$i18n.t('proposals.review.changes_title')}</h3>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
            <div
                class="vff-table-wrap"
                role="region"
                aria-labelledby="changes-{contribution.id}"
                tabindex="0"
            >
                <table class="vff-table" aria-labelledby="changes-{contribution.id}">
                    <thead>
                        <tr>
                            <th scope="col">{$i18n.t('proposals.review.field')}</th>
                            {#if p.mode !== 'new'}
                                <th scope="col">{$i18n.t('proposals.review.before')}</th>
                            {/if}
                            <th scope="col">{$i18n.t('proposals.review.after')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each changes as ch (ch.field)}
                            <tr>
                                <th scope="row">{ch.label}</th>
                                {#if p.mode !== 'new'}<td class="wrap">{ch.before}</td>{/if}
                                <td class="wrap">
                                    {#if ch.cleared}<span class="cleared"
                                            ><i class="bi bi-eraser" aria-hidden="true"></i>
                                            {ch.after}</span
                                        >{:else}{ch.after}{/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    {/if}

    {#if isMedia && p.file}
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

    {#if isLineMaterial}
        <section class="similar" aria-labelledby="links-{contribution.id}">
            <h3 id="links-{contribution.id}">{$i18n.t('proposals.review.links_title')}</h3>
            {#if context.links.length}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <div
                    class="vff-table-wrap"
                    role="region"
                    aria-labelledby="links-{contribution.id}"
                    tabindex="0"
                >
                    <table class="vff-table" aria-labelledby="links-{contribution.id}">
                        <thead>
                            <tr>
                                <th scope="col">{$i18n.t('proposals.review.link_source')}</th>
                                <th scope="col">{$i18n.t('proposals.review.link_confidence')}</th>
                                <th scope="col">{$i18n.t('proposals.review.link_periods')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each context.links as l (l.id)}
                                <tr>
                                    <th scope="row" class="wrap">
                                        {#if l.source_url}<a
                                                href={l.source_url}
                                                target="_blank"
                                                rel="noopener noreferrer nofollow"
                                                >{linkLabel('sources', l.source)}<span
                                                    class="vff-sr-only"
                                                >
                                                    ({$i18n.t('common.new_tab')})</span
                                                ><i
                                                    class="bi bi-box-arrow-up-right vff-ext"
                                                    aria-hidden="true"
                                                ></i></a
                                            >{:else}{linkLabel('sources', l.source)}{/if}
                                    </th>
                                    <td>{linkLabel('confidences', l.confidence)}</td>
                                    <td class="wrap"
                                        >{l.periods
                                            .map((period) =>
                                                periodText(
                                                    period,
                                                    tr,
                                                    formatLocale($i18n.language),
                                                ),
                                            )
                                            .join(', ')}</td
                                    >
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {:else}
                <p class="muted">{$i18n.t('proposals.review.links_none')}</p>
            {/if}
        </section>
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
                    <table class="vff-table" aria-labelledby="similar-{contribution.id}">
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
    .image .open {
        margin: 0;
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
    .cleared {
        font-weight: 600;
        color: var(--vff-danger);
    }
    .impact {
        margin: var(--vff-space-2) 0 0;
        padding-left: var(--vff-space-5);
        overflow-wrap: anywhere;
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
