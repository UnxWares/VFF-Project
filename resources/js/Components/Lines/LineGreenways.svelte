<script>
    import { i18n } from '$lib/i18n.js';
    import { formatEventDate, formatKm } from '$lib/linePhases.js';
    import { formatLocale, tieUnits } from '$lib/typo.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';

    /**
     * Section « Voie verte » de la fiche ligne (LineGreenways::forSheet côté serveur) : les voies
     * vertes d'OpenStreetMap qui reprennent l'ancienne emprise de la ligne, chacune avec sa longueur
     * sur cette emprise et un lien vers OpenStreetMap, puis la règle de rattachement et la source
     * (ODbL). Montrée seulement s'il y a au moins un rattachement.
     *
     * @type {{ greenways: { total_length_m: number, osm_date: string|null, items: Array<{ name: string|null,
     *   shared_length_m: number, way_count: number, opened_year: number|null, osm_url: string }> } }}
     */
    let { greenways } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const km = (m) => tieUnits(formatKm(m, formatLocale(lang)));

    const count = $derived(greenways.items.length);
    const intro = $derived(
        t(count === 1 ? 'greenways.line.intro_one' : 'greenways.line.intro_other', {
            count,
            length: km(greenways.total_length_m),
        }),
    );
    const sourceNote = $derived(
        t('greenways.line.source', {
            date: greenways.osm_date
                ? formatEventDate(greenways.osm_date, 'day', formatLocale(lang))
                : '',
        }),
    );

    /** « 15,2 km sur l'ancienne emprise, ouverte en 2006 » (l'année seulement si OpenStreetMap la donne). */
    const meta = (g) =>
        [
            t('greenways.line.on_trackbed', { length: km(g.shared_length_m) }),
            g.opened_year ? t('greenways.line.opened', { year: g.opened_year }) : null,
        ]
            .filter(Boolean)
            .join(', ');
</script>

<SheetSection id="greenways" title={t('greenways.line.title')} {intro}>
    <div class="greenways">
        <ul class="list">
            {#each greenways.items as g (g.osm_url)}
                <li>
                    <a class="vff-link" href={g.osm_url} target="_blank" rel="noopener noreferrer">
                        {g.name ?? t('greenways.line.unnamed')}<i
                            class="bi bi-box-arrow-up-right vff-ext"
                            aria-hidden="true"
                        ></i><span class="vff-sr-only">
                            ({t('greenways.line.osm_hint')}, {t('common.new_tab')})</span
                        >
                    </a>
                    <span class="meta">{meta(g)}</span>
                </li>
            {/each}
        </ul>
        <p class="more">
            <a class="vff-link" href="/maps?layer=greenways">{t('greenways.line.map_link')}</a>
        </p>
        <p class="note">{t('greenways.line.method')} {sourceNote}</p>
    </div>
</SheetSection>

<style>
    .greenways {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-4);
    }
    .list {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .list li {
        display: flex;
        flex-direction: column;
        gap: 2px;
        max-width: var(--vff-measure);
    }
    .list a {
        font-size: var(--vff-fs-body);
        font-weight: 600;
    }
    .meta {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .more,
    .note {
        max-width: var(--vff-measure);
        margin: 0;
    }
    .note {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
</style>
