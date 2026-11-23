<script>
    import { i18n } from '$lib/i18n.js';
    import { commonsSrc } from '$lib/commonsImage.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import { firstOfMonth, formatLocale, formatNumber, tieUnits } from '$lib/typo.js';
    import {
        mediaHeading,
        mediaLicenseHref,
        mediaLicenseLabel,
        mediaSourceHref,
    } from '$lib/mediaCredits.js';
    import { conciseAlt } from '$lib/altText.js';
    import { storyLinks } from '$lib/eventStory.js';

    /**
     * Chronologie détaillée, portion par portion : TOUS les événements de la ligne (rien
     * n'est caché), avec leur statut (retenu pour dater la carte, concorde, diverge, sans
     * effet, écarté, non localisé) et leur appel de note. Données : App\Services\LineChronicle.
     * Description et citation arrivent dans la langue de la page (App\Support\EventText) ;
     * description_lang / quote_lang = « fr » pour ce qui reste une citation en français.
     * Images d'un événement (`e.media`, publiées après validation) : petite vignette chargée au
     * besoin (groupes repliés, `loading="lazy"`), lien vers l'image en grand et crédit complet.
     * `stories` : histoires de la ligne (App\Services\Stories\EventStories), une liste d'événements par
     * fait, celui de l'adresse d'abord. Un seul lien « Voir l'histoire » par histoire, même quand
     * plusieurs sources rapportent le fait (storyLinks) ; jamais sous un doublon d'un fait retenu (statut
     * agrees) ni sous le fait d'une autre ligne. `lineSlug` construit l'adresse /lines/{slug}/stories/{id}.
     * Lien « Corriger » sous chaque fait : formulaire de correction prérempli
     * (/contribute/event?line={slug}&event={id}), sauf pour un accident (fiche vérifiée à part) et un
     * fait déjà neutralisé par une correction sourcée (`other` avec `correction`).
     */
    let { chronicle, stories = [], lineSlug = null } = $props();
    const storyOf = $derived(lineSlug ? storyLinks(chronicle?.events ?? [], stories) : new Map());

    const lang = $derived($i18n.language ?? 'fr');
    const dateOf = (iso, precision) =>
        firstOfMonth(formatEventDate(iso, precision, formatLocale(lang)), lang);
    // Statuts : libellés propres à la fiche quand ils existent (lines.sheet.chronicle.*).
    const statusLabel = (st) => {
        const key = `lines.sheet.chronicle.${st}`;
        const label = $i18n.t(key);
        return label === key ? $i18n.t(`lines.chronicle.status.${st}`) : label;
    };
    const STATUSES = [
        'retained',
        'agrees',
        'conflict',
        'unused',
        'ignored',
        'unlocated',
        'other_line',
        'context',
    ];
    const BADGE = { retained: 'accent', agrees: 'success', conflict: 'warning' };
    // Légende : seulement les statuts présents sur cette fiche.
    const usedStatuses = $derived(
        chronicle?.statuses ??
            STATUSES.filter((st) => (chronicle?.events ?? []).some((e) => e.status === st)),
    );
    const byId = $derived(Object.fromEntries((chronicle?.events ?? []).map((e) => [e.id, e])));
    const groups = $derived(chronicle?.groups ?? []);
    // Portions désignées par un seul lieu (« près de X ») : regroupées quand elles sont nombreuses
    // (LGV : un tracé OpenHistoricalMap par tronçon), pour garder la chronique lisible.
    const isNear = (g) =>
        !['whole', 'traced', 'unspecified'].includes(g.key) && !g.key.includes('|');
    const nearGroups = $derived(groups.filter(isNear));
    const foldNear = $derived(nearGroups.length > 6);
    const mainGroups = $derived(foldNear ? groups.filter((g) => !isNear(g)) : groups);
    const named = $derived(
        mainGroups.filter((g) => !['traced', 'unspecified'].includes(g.key)).length,
    );

    function groupLabel(g) {
        if (g.key === 'whole') return $i18n.t('lines.chronicle.whole_line');
        if (g.key === 'traced') return $i18n.t('lines.chronicle.traced');
        if (g.key === 'unspecified') return $i18n.t('lines.chronicle.unspecified');
        if (g.from && g.to && g.from !== g.to)
            return $i18n.t('lines.event_section.between', { from: g.from, to: g.to });
        return $i18n.t('lines.event_section.near', { place: g.from ?? g.to });
    }

    function eventLabel(type) {
        const key = `lines.event_types.${type}`;
        const label = $i18n.t(key);
        return label === key ? type : label;
    }

    const t = (key, opts) => String($i18n.t(key, opts));

    /** @param {{ type: string, correction?: string|null }} e */
    const correctable = (e) =>
        lineSlug !== null && e.type !== 'accident' && !(e.type === 'other' && e.correction);
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

{#snippet eventMedia(list)}
    <ul class="ev-media" aria-label={t('media.event_images')}>
        {#each list as m (m.id)}
            <li>
                <a
                    class="ev-thumb"
                    href={m.display_url ?? m.thumb_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    ><img
                        src={commonsSrc(m.thumb_url)}
                        alt={t('media.event_open', { title: conciseAlt(mediaHeading(m), 100) })}
                        loading="lazy"
                        decoding="async"
                        width="120"
                        height="90"
                    /><span class="vff-sr-only"> ({t('common.new_tab')})</span></a
                >
                <span class="ev-credit">
                    <span class="ev-title">{mediaHeading(m)}</span>
                    {m.author ?? t('media.author_unknown')},
                    {#if mediaLicenseHref(m)}<a
                            href={mediaLicenseHref(m)}
                            target="_blank"
                            rel="noopener noreferrer license"
                            >{mediaLicenseLabel(m, t)}{@render ext()}</a
                        >{:else}{mediaLicenseLabel(m, t)}{/if}{#if mediaSourceHref(m)},
                        <a href={mediaSourceHref(m)} target="_blank" rel="noopener noreferrer"
                            >{t('media.via', { provider: m.provider_label })}{@render ext()}</a
                        >{/if}{#if m.contribution_id},
                        {t('media.contributed')}{/if}
                </span>
            </li>
        {/each}
    </ul>
{/snippet}

<div class="chronicle">
    <p class="intro">{$i18n.t('lines.chronicle.intro')}</p>
    <dl class="legend">
        {#each usedStatuses as st (st)}
            <div>
                <dt>
                    <span class="vff-badge {BADGE[st] ?? 'muted'}">{statusLabel(st)}</span>
                </dt>
                <dd>{$i18n.t(`lines.chronicle.status_help.${st}`)}</dd>
            </div>
        {/each}
    </dl>

    {#snippet groupBlock(g, open)}
        <details class="group" {open}>
            <summary>
                <i class="bi bi-chevron-right chev" aria-hidden="true"></i>
                <span class="label">{g.title ?? groupLabel(g)}</span>
                <span class="nums">
                    {#if g.length_m}<span class="len"
                            >{formatNumber(g.length_m / 1000, lang, {
                                maximumFractionDigits: 1,
                            })}&nbsp;km</span
                        >{/if}
                    <span class="count"
                        >{$i18n.t('lines.sheet.chronicle.event_count', {
                            count: g.event_ids.length,
                        })}</span
                    >
                </span>
            </summary>
            <table>
                <thead>
                    <tr>
                        <th scope="col">{$i18n.t('lines.chronicle.col_date')}</th>
                        <th scope="col">{$i18n.t('lines.chronicle.col_fact')}</th>
                        <th scope="col">{$i18n.t('lines.sheet.chronicle.col_status')}</th>
                    </tr>
                </thead>
                <tbody>
                    {#each g.event_ids as id (id)}
                        {@const e = byId[id]}
                        {#if e}
                            <tr class="st-row-{e.status}">
                                <td class="date">{dateOf(e.date, e.precision)}</td>
                                <td class="fact">
                                    <span
                                        class="vff-badge {BADGE[e.status] ?? 'muted'} inline-status"
                                        >{statusLabel(e.status)}</span
                                    >
                                    {eventLabel(e.type)}<sup
                                        ><a href="#note-{e.note}"
                                            ><span class="vff-sr-only"
                                                >{`${$i18n.t('common.note_ref')} `}
                                            </span>[{e.note}]</a
                                        ></sup
                                    >
                                    {#if e.description}<span class="desc" lang={e.description_lang}
                                            >{tieUnits(firstOfMonth(e.description, lang))}</span
                                        >{/if}
                                    {#if e.quote && e.quote_kind === 'data'}<span
                                            class="quote"
                                            lang={e.quote_lang}>{tieUnits(e.quote)}</span
                                        >{:else if e.quote}<q class="quote" lang={e.quote_lang}
                                            >{e.quote}</q
                                        >{/if}
                                    {#if e.correction}<span
                                            class="correction"
                                            lang={e.correction_lang}>{e.correction}</span
                                        >{/if}
                                    {#if e.conflicts_with.length}
                                        <span class="vs">
                                            {$i18n.t('lines.chronicle.differs_from')}
                                            {#each e.conflicts_with as o, n (o)}
                                                {#if byId[o]}{dateOf(
                                                        byId[o].date,
                                                        byId[o].precision,
                                                    )}<sup
                                                        ><a href="#note-{byId[o].note}"
                                                            ><span class="vff-sr-only"
                                                                >{`${$i18n.t('common.note_ref')} `}
                                                            </span>[{byId[o].note}]</a
                                                        ></sup
                                                    >{n < e.conflicts_with.length - 1
                                                        ? ', '
                                                        : ''}{/if}
                                            {/each}
                                        </span>
                                    {/if}
                                    {#if e.media?.length}{@render eventMedia(e.media)}{/if}
                                    {#if storyOf.has(e.id)}<a
                                            class="ev-story"
                                            href="/lines/{lineSlug}/stories/{storyOf.get(e.id)}"
                                            aria-label={t('stories.link_label', {
                                                event: eventLabel(e.type),
                                                date: dateOf(e.date, e.precision),
                                            })}>{t('stories.link')}</a
                                        >{/if}
                                    {#if correctable(e)}<a
                                            class="ev-correct"
                                            href="/contribute/event?line={lineSlug}&event={e.id}"
                                            aria-label={t('lines.chronicle.correct_label', {
                                                event: eventLabel(e.type),
                                                date: dateOf(e.date, e.precision),
                                            })}>{t('lines.chronicle.correct')}</a
                                        >{/if}
                                </td>
                                <td
                                    ><span class="vff-badge {BADGE[e.status] ?? 'muted'}"
                                        >{statusLabel(e.status)}</span
                                    ></td
                                >
                            </tr>
                        {/if}
                    {/each}
                </tbody>
            </table>
        </details>
    {/snippet}

    {#each mainGroups as g (g.key)}
        {@render groupBlock(g, !['traced', 'unspecified'].includes(g.key) && named <= 8)}
    {/each}
    {#if foldNear}
        <details class="group near-fold">
            <summary>
                <i class="bi bi-chevron-right chev" aria-hidden="true"></i>
                <span class="label">{$i18n.t('lines.chronicle.near_groups')}</span>
                <span class="nums">
                    <span class="count"
                        >{$i18n.t('lines.sheet.chronicle.event_count', {
                            count: nearGroups.reduce((a, g) => a + g.event_ids.length, 0),
                        })}</span
                    >
                </span>
            </summary>
            <div class="nested">
                {#each nearGroups as g (g.key)}
                    {@render groupBlock(g, false)}
                {/each}
            </div>
        </details>
    {/if}
</div>

<style>
    .intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .legend {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 8px 24px;
        margin: 0 0 var(--vff-space-5);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
    }
    .legend div {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px;
    }
    .legend dt {
        flex-shrink: 0;
    }
    /* Le texte passe sous l'étiquette quand la colonne est trop étroite (mots longs en allemand). */
    .legend dd {
        flex: 1 1 10rem;
        min-width: 0;
        margin: 0;
        color: var(--vff-text-muted);
    }
    .group {
        padding: 2px 0;
        border-top: 1px solid var(--vff-border);
    }
    .nested {
        padding-left: 18px;
    }
    .group:last-of-type {
        border-bottom: 1px solid var(--vff-border);
    }
    /* display:flex retire le triangle natif de <summary> : un chevron le remplace. */
    summary {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 12px;
        min-height: 44px;
        padding: 10px 0;
        list-style: none;
        cursor: pointer;
    }
    summary::-webkit-details-marker {
        display: none;
    }
    .chev {
        flex-shrink: 0;
        align-self: center;
        font-size: 13px;
        color: var(--vff-text-muted);
    }
    @media (prefers-reduced-motion: no-preference) {
        .chev {
            transition: transform var(--vff-transition);
        }
    }
    details[open] > summary .chev {
        transform: rotate(90deg);
    }
    summary:hover .label {
        color: var(--vff-accent-ink);
    }
    .label {
        flex: 1 1 16em;
        min-width: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
    }
    /* Longueur et compteur : jamais coupés, ensemble à droite (dessous sur téléphone). */
    .nums {
        display: inline-flex;
        gap: 16px;
        margin-left: auto;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    table {
        width: 100%;
        margin: 0 0 12px;
        border-collapse: collapse;
        table-layout: fixed;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    th {
        padding: 4px 8px 6px 0;
        border-bottom: 1px solid var(--vff-border);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        text-align: left;
        color: var(--vff-text-muted);
    }
    td {
        padding: 10px 8px 10px 0;
        border-bottom: 1px solid var(--vff-border);
        vertical-align: top;
    }
    td.date,
    th:nth-child(1) {
        width: 10em;
    }
    th:nth-child(3) {
        width: 9em;
    }
    td.date {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    td.fact {
        line-height: 1.5;
        /* Un mot trop long pour la colonne (allemand, 320 px) se coupe au lieu de déborder. */
        overflow-wrap: break-word;
    }
    .desc {
        display: block;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
    }
    .quote {
        display: block;
        margin-top: 4px;
        padding-left: 10px;
        border-left: 2px solid var(--vff-border-strong);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    /* Guillemets français avec leurs espaces insécables. */
    .quote:lang(fr) {
        quotes: '«\00a0' '\00a0»';
    }
    .correction {
        display: block;
        margin-top: 4px;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    /* Lien vers l'histoire de l'événement (images de l'époque, archives), sous son texte. */
    /* Cible d'au moins 24 px de haut (WCAG 2.5.8). */
    .ev-story {
        display: inline-block;
        min-height: 24px;
        margin-top: 6px;
        font-weight: 600;
    }
    /* Proposer une correction du fait : discret (texte secondaire), cible de 24 px au moins (WCAG 2.5.8). */
    .ev-correct {
        display: inline-block;
        min-height: 24px;
        margin: 6px 0 0;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        text-decoration: underline;
        text-decoration-color: var(--vff-border-strong);
    }
    .ev-story + .ev-correct {
        margin-left: 16px;
    }
    .ev-correct:hover {
        color: var(--vff-accent-ink);
    }
    @media print {
        .ev-correct {
            display: none;
        }
    }
    /* Images d'un événement : vignette de 120 px et crédit à côté (dessous sur téléphone). */
    .ev-media {
        display: grid;
        gap: 12px;
        margin: 10px 0 2px;
        padding: 0;
        list-style: none;
    }
    .ev-media li {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }
    .ev-thumb {
        flex: none;
        display: block;
        width: 120px;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        border-radius: var(--vff-radius-sm);
        background: var(--vff-bg-muted);
    }
    .ev-thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ev-thumb:hover img {
        filter: brightness(0.92);
    }
    .ev-credit {
        min-width: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.45;
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .ev-title {
        display: block;
        color: var(--vff-text);
        font-weight: 600;
    }
    .ev-credit a {
        color: inherit;
        text-decoration-color: var(--vff-border-strong);
    }
    .ev-credit a:hover {
        color: var(--vff-accent-ink);
    }
    .vs {
        display: block;
        margin-top: 2px;
        font-size: var(--vff-fs-xs);
        color: var(--vff-warning);
    }
    tr.st-row-ignored td,
    tr.st-row-unused td {
        color: var(--vff-text-muted);
    }
    sup {
        line-height: 0;
    }
    /* Appel de note : cible de 24 px (le texte reste en exposant). */
    sup a {
        /* Remplissage en ligne : la zone cliquable grandit sans écarter les lignes du texte
           (« [1] » en 14 px, environ 14 px de large, + 2 × 6 px : 24 px au moins). */
        padding: 5px 6px;
        color: var(--vff-link);
        font-size: var(--vff-fs-xs);
        /* Soulignée : un lien noyé dans du texte ne se distingue pas par la seule couleur (RGAA 10.6). */
        text-decoration: underline;
    }
    .vff-badge {
        white-space: normal;
    }
    .inline-status {
        display: none;
    }
    /* Téléphone : la colonne du rôle disparaît, le statut passe dans la cellule du fait. */
    @media (max-width: 600px) {
        .inline-status {
            display: inline-flex;
            margin: 0 6px 4px 0;
        }
        td.date,
        th:nth-child(1) {
            width: 6.5em;
        }
        td.date {
            font-size: var(--vff-fs-xs);
        }
        th:nth-child(3),
        td:nth-child(3) {
            display: none;
        }
        .nums {
            flex-basis: 100%;
            margin-left: 25px;
        }
        .ev-media li {
            flex-direction: column;
            gap: 6px;
        }
    }
</style>
