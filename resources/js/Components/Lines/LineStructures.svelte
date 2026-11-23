<script>
    import { i18n } from '$lib/i18n.js';
    import { formatLocale, formatNumber, NBSP } from '$lib/typo.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import {
        dateParts,
        pkText,
        structureName,
        summaryList,
        visibleItems,
    } from '$lib/lineStructures.js';

    /**
     * Section « Ouvrages d'art » de la fiche ligne (App\Services\Structures\LineStructures::forSheet) :
     * tunnels, viaducs et grands ponts dans l'ordre des PK, avec longueur, dates, protection, liens et
     * photo libre de Commons (auteur et licence sous l'image). Au-delà de FOLD_AFTER ouvrages, seuls
     * les principaux restent visibles et la liste complète est repliée (élément natif `details`).
     *
     * @type {{ structures: { summary: { count: number, by_kind: Record<string, number>, tunnel_length_m: number },
     *   items: Array<{ id: number, kind: 'tunnel'|'viaduct'|'bridge', name: string|null, length_m: number|null,
     *     pk_start_label: string|null, pk_end_label: string|null,
     *     built: { date: string, precision: string }|null, opened: { date: string, precision: string }|null,
     *     closed: { date: string, precision: string }|null, heritage: 'classified'|'registered'|'protected'|null,
     *     merimee_ref: string|null, wikidata_id: string|null, wikipedia_url: string|null, current: boolean,
     *     image: { thumb_url: string, width: number|null, height: number|null, author: string|null,
     *       license: string|null, source_url: string|null }|null }> } }}
     */
    let { structures } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const locale = $derived(formatLocale(lang));

    const view = $derived(visibleItems(structures.items));
    // Ligne fermée ou déposée partout : dit une fois dans le chapeau, pas sur chaque ouvrage.
    const allClosed = $derived(structures.items.every((s) => !s.current));
    const intro = $derived.by(() => {
        const s = structures.summary;
        let text = `${t('structures.line.intro')} ${t('structures.line.summary', { list: summaryList(s, t, locale) })}`;
        if (s.by_kind.tunnel > 1 && s.tunnel_length_m > 0) {
            text = text.replace(
                /\.$/,
                `, ${t('structures.line.tunnels_length', { length: lengthText(s.tunnel_length_m) })}.`,
            );
        }
        return allClosed ? `${text} ${t('structures.line.all_closed')}` : text;
    });

    function lengthText(m) {
        return m >= 10000
            ? `${formatNumber(m / 1000, lang, { maximumFractionDigits: 1 })}${NBSP}km`
            : `${formatNumber(m, lang)}${NBSP}m`;
    }

    /** « Viaduc, 565 m, construit en 1880, classé monument historique ». */
    function meta(s) {
        return [
            t(`structures.line.kind.${s.kind}`),
            s.length_m ? lengthText(s.length_m) : null,
            ...dateParts(s, t, locale),
            s.heritage ? t(`structures.line.heritage.${s.heritage}`) : null,
            s.current || allClosed ? null : t('structures.line.not_current'),
        ]
            .filter(Boolean)
            .join(', ');
    }

    /** « Photo Père Igor, CC BY-SA 4.0 » ; sans auteur connu, la licence seule. */
    function credit(image) {
        return image.author
            ? t('structures.line.photo_credit', {
                  author: image.author,
                  license: image.license ?? '',
              })
            : t('structures.line.photo_license', { license: image.license ?? '' });
    }

    function links(s) {
        return [
            s.wikipedia_url
                ? { href: s.wikipedia_url, label: t('structures.line.wikipedia') }
                : null,
            s.merimee_ref
                ? {
                      href: `https://pop.culture.gouv.fr/notice/merimee/${encodeURIComponent(s.merimee_ref)}`,
                      label: t('structures.line.merimee'),
                  }
                : null,
            s.wikidata_id
                ? {
                      href: `https://www.wikidata.org/wiki/${s.wikidata_id}`,
                      label: t('structures.line.wikidata'),
                  }
                : null,
        ].filter(Boolean);
    }
</script>

{#snippet entry(s, withPhoto)}
    <li class="item" class:with-photo={withPhoto && s.image}>
        <div class="text">
            {#if pkText(s, t)}<p class="pk">{pkText(s, t)}</p>{/if}
            <p class="name">{structureName(s, t)}</p>
            <p class="meta">{meta(s)}</p>
            {#if links(s).length}
                <p class="links">
                    {#each links(s) as l, i (l.href)}{#if i > 0}<span class="sep" aria-hidden="true"
                                >·</span
                            >{/if}<a
                            class="vff-link"
                            href={l.href}
                            target="_blank"
                            rel="noopener noreferrer"
                            >{l.label}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"
                            ></i><span class="vff-sr-only"> ({t('common.new_tab')})</span></a
                        >{/each}
                </p>
            {/if}
        </div>
        {#if withPhoto && s.image}
            <figure class="photo">
                <img
                    src={s.image.thumb_url}
                    width={s.image.width ?? 500}
                    height={s.image.height ?? 333}
                    loading="lazy"
                    decoding="async"
                    alt={t('structures.line.photo_alt', { name: structureName(s, t) })}
                />
                <figcaption>
                    {#if s.image.source_url}<a
                            class="vff-link"
                            href={s.image.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            >{credit(s.image)}<span class="vff-sr-only">
                                ({t('common.new_tab')})</span
                            ></a
                        >{:else}{credit(s.image)}{/if}
                </figcaption>
            </figure>
        {/if}
    </li>
{/snippet}

<SheetSection
    id="structures"
    title={t('structures.line.title')}
    count={structures.summary.count}
    {intro}
>
    <div class="structures">
        {#if view.folded}<p class="note">{t('structures.line.notable')}</p>{/if}
        <ol class="list">
            {#each view.items as s (s.id)}
                {@render entry(s, true)}
            {/each}
        </ol>

        {#if view.folded}
            <details class="all">
                <summary
                    >{t('structures.line.show_all', { count: structures.items.length })}</summary
                >
                <ol class="list compact" aria-label={t('structures.line.list_label')}>
                    {#each structures.items as s (s.id)}
                        {@render entry(s, false)}
                    {/each}
                </ol>
            </details>
        {/if}

        <p class="source">{t('structures.line.source')}</p>
    </div>
</SheetSection>

<style>
    .structures {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
    }
    .note,
    .source {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .list {
        display: flex;
        flex-direction: column;
        max-width: 60rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .item {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: var(--vff-space-4);
        padding-block: var(--vff-space-4);
        border-top: 1px solid var(--vff-border);
    }
    .item:first-child {
        border-top: 0;
        padding-top: 0;
    }
    .item.with-photo {
        grid-template-columns: minmax(0, 1fr) 11rem;
        align-items: start;
    }
    .text p {
        margin: 0;
    }
    .pk {
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
    }
    .name {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        color: var(--vff-text);
        text-wrap: balance;
    }
    .meta {
        margin-top: 2px;
        text-wrap: pretty;
    }
    .text .links {
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-sm);
    }
    .sep {
        margin-inline: 0.5em;
        color: var(--vff-text-muted);
    }
    .photo {
        margin: 0;
    }
    .photo img {
        display: block;
        width: 100%;
        height: auto;
        aspect-ratio: 3 / 2;
        object-fit: cover;
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-muted);
    }
    .photo figcaption {
        margin-top: 4px;
        font-size: var(--vff-fs-xs);
        line-height: 1.35;
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .compact .item {
        padding-block: var(--vff-space-3);
    }
    .all summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
        cursor: pointer;
        transition: background-color var(--vff-transition);
    }
    .all summary:hover {
        background-color: var(--vff-bg-muted);
    }
    .all summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .all[open] summary {
        margin-bottom: var(--vff-space-4);
    }
    @media (max-width: 560px) {
        .item.with-photo {
            grid-template-columns: minmax(0, 1fr);
        }
        .photo {
            max-width: 16rem;
        }
    }
</style>
