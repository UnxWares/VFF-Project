<script>
    /**
     * Un exemplaire préservé : numéro, lieu où on le voit (avec le lien de la ligne touristique quand elle existe),
     * état, protection au titre des monuments historiques et sources. Utilisé par la fiche d'une série et par la
     * page « Matériel préservé » (`showVenue` vaut faux sous le titre d'un lieu).
     */
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { protectionText } from '$lib/preservedStock.js';
    import { colon } from '$lib/typo.js';

    // Noms propres des sources : les mêmes dans toutes les langues.
    const SOURCE_NAMES = { wikipedia: 'Wikipédia', palissy: 'Palissy', wikidata: 'Wikidata' };

    /** @type {{ piece: any, showVenue?: boolean, showSeries?: boolean }} */
    let { piece, showVenue = true, showSeries = false } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    const protection = $derived(protectionText(piece.protection, t, lang));
</script>

<li class="piece">
    <h3 class="num">
        {piece.number ?? piece.title}
        {#if piece.number && piece.title}<span class="title">{piece.title}</span>{/if}
    </h3>
    <dl>
        {#if showVenue}
            <div>
                <dt>{t('materials_preserved.place')}</dt>
                <dd>
                    {#if piece.venue}
                        <span class="venue"
                            >{piece.venue.name}{#if piece.venue.commune}, {piece.venue
                                    .commune}{/if}</span
                        >
                        <span class="kind"
                            >{t(`materials_preserved.type_one.${piece.venue.type}`)}</span
                        >
                        {#if piece.venue.tourist_railway}
                            <a class="vff-link" href={piece.venue.tourist_railway.url}
                                >{t('materials_preserved.see_tourist')}</a
                            >
                        {/if}
                    {:else if piece.where}
                        {piece.where}
                    {:else}
                        <span class="muted">{t('materials_preserved.place_unknown')}</span>
                    {/if}
                </dd>
            </div>
        {/if}
        {#if showSeries && piece.series?.length}
            <div>
                <dt>{t('materials_preserved.series_label')}</dt>
                <dd>
                    {#each piece.series as s, i (s.slug)}{#if i > 0},
                        {/if}<Link href="/materials/{s.slug}" class="vff-link">{s.name}</Link
                        >{/each}
                </dd>
            </div>
        {/if}
        {#if piece.state}
            <div>
                <dt>{t('materials_preserved.state_label')}</dt>
                <dd>{t(`materials_preserved.state.${piece.state}`)}</dd>
            </div>
        {/if}
        {#if protection}
            <div>
                <dt>{t('materials_preserved.protection_label')}</dt>
                <dd>
                    <span class="what">{protection}</span>
                    {#if piece.protection.url}
                        <a
                            class="vff-link notice"
                            href={piece.protection.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            >{t('materials_preserved.protection.notice', {
                                ref: piece.protection.ref,
                            })}<span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
                        >
                    {/if}
                </dd>
            </div>
        {/if}
    </dl>
    {#if piece.sources?.length}
        <div class="sources">
            <span>{t('materials_preserved.sources_label')}{colon(lang)}</span>
            <ul>
                {#each piece.sources as s (s.key + (s.ref ?? ''))}
                    {#if s.url}
                        <li>
                            <a
                                class="vff-link"
                                href={s.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                title="{s.label} ({s.licence})"
                                >{SOURCE_NAMES[s.key] ?? s.key}<span class="vff-sr-only">
                                    ({$i18n.t('common.new_tab')})</span
                                ></a
                            >
                        </li>
                    {/if}
                {/each}
            </ul>
        </div>
    {/if}
</li>

<style>
    .piece {
        padding: var(--vff-space-4) var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
        list-style: none;
    }
    .num {
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        line-height: 1.3;
        color: var(--vff-text);
    }
    .title {
        display: block;
        margin-top: var(--vff-space-1);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    dl {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        margin: 0;
    }
    dl > div {
        display: grid;
        grid-template-columns: minmax(0, 9rem) minmax(0, 1fr);
        column-gap: var(--vff-space-4);
    }
    dt {
        color: var(--vff-text-muted);
    }
    dd {
        margin: 0;
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .kind,
    .muted {
        color: var(--vff-text-muted);
    }
    .kind::before {
        content: '(';
    }
    .kind::after {
        content: ')';
    }
    .notice {
        display: block;
    }
    .sources {
        display: flex;
        flex-wrap: wrap;
        column-gap: var(--vff-space-2);
        margin: var(--vff-space-3) 0 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-body);
    }
    .sources ul {
        display: flex;
        flex-wrap: wrap;
        column-gap: var(--vff-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .sources li:not(:last-child)::after {
        content: ',';
    }
    @media (max-width: 600px) {
        dl > div {
            grid-template-columns: minmax(0, 1fr);
        }
        dt {
            font-size: var(--vff-fs-sm);
        }
    }
</style>
