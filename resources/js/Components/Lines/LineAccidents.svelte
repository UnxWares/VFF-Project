<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { accidentDate, CONFIDENCE_TONE, tollText } from '$lib/accidentText.js';

    /**
     * Section « Accidents » de la fiche ligne : accidents rattachés à la ligne (LineAccidents côté
     * serveur), du plus ancien au plus récent. Chaque entrée donne la date (partie connue
     * seulement), le nom et le lien vers la fiche, la nature, le lieu et le bilan ; un badge dit
     * quand le rattachement à cette ligne n'est pas sûr. Ton sobre : ni couleur vive ni icône.
     * Au-delà de VISIBLE accidents, la suite se déplie (bouton à aria-expanded).
     *
     * @type {{ accidents: Array<{ slug: string, name: string, date: string, date_precision: 'day'|'month'|'year',
     *   kind: string, place: string|null, department: string|null, deaths: number|null,
     *   injuries: number|null, confidence: 'high'|'medium'|'low' }>, lineSlug: string }}
     */
    let { accidents, lineSlug } = $props();

    const VISIBLE = 5;
    const listId = $props.id();
    let expanded = $state(false);

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const shown = $derived(expanded ? accidents : accidents.slice(0, VISIBLE));
    const hiddenCount = $derived(accidents.length - VISIBLE);

    const placeOf = (a) =>
        a.place ? `${a.place}${a.department ? ` (${a.department})` : ''}` : null;
    // « Collision, Juvisy-sur-Orge (91). 17 morts, 85 blessés » : nature et lieu, puis le bilan.
    function factsOf(a) {
        const what = [t(`accidents.kinds.${a.kind}`), placeOf(a)].filter(Boolean).join(', ');
        return `${what}. ${tollText(a.deaths, a.injuries, $i18n.t)}`;
    }
</script>

<SheetSection
    id="accidents"
    title={t('accidents.line.title')}
    count={accidents.length}
    intro={t('accidents.line.intro')}
>
    <ol class="accident-list" id={listId}>
        {#each shown as a (a.slug)}
            <li>
                <time datetime={a.date}>{accidentDate(a.date, a.date_precision, lang)}</time>
                <div class="body">
                    <Link href="/accidents/{a.slug}" class="accident-name">{a.name}</Link>
                    <span class="facts">{factsOf(a)}</span>
                    {#if a.confidence !== 'high'}
                        <span class="vff-badge {CONFIDENCE_TONE[a.confidence]}"
                            >{t(`accidents.confidence.${a.confidence}`)}</span
                        >
                    {/if}
                </div>
            </li>
        {/each}
    </ol>
    <div class="actions">
        {#if hiddenCount > 0}
            <button
                type="button"
                class="vff-btn secondary small"
                aria-expanded={expanded}
                aria-controls={listId}
                onclick={() => (expanded = !expanded)}
            >
                {expanded
                    ? t('accidents.line.show_less')
                    : t('accidents.line.show_more', { count: hiddenCount })}
            </button>
        {/if}
        <Link href="/accidents?line={encodeURIComponent(lineSlug)}" class="vff-link"
            >{t('accidents.line.in_list')}</Link
        >
    </div>
</SheetSection>

<style>
    /* Liste datée à filets, comme un registre : date en colonne fixe, le reste à sa droite. */
    .accident-list {
        max-width: 860px;
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .accident-list li {
        display: grid;
        grid-template-columns: 11rem minmax(0, 1fr);
        gap: 4px 24px;
        padding: 14px 0;
        border-bottom: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    time {
        color: var(--vff-text);
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }
    .body {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        min-width: 0;
    }
    .body :global(.accident-name) {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-link);
        text-underline-offset: 0.18em;
    }
    .body :global(.accident-name:hover) {
        text-decoration-thickness: 2px;
    }
    .facts {
        color: var(--vff-text-muted);
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 24px;
        margin-top: var(--vff-space-4);
    }
    @media (max-width: 600px) {
        .accident-list li {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
