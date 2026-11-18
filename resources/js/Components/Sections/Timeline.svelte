<script>
    /**
     * Profil du réseau : longueur des lignes ouvertes au 31 décembre de chaque décennie (données de la
     * base, prop `profile` de HomeController), dessiné comme un profil en long : une colonne par
     * décennie, le filet orange du haut forme la ligne du réseau. Chaque colonne ouvre la carte de sa
     * décennie. Toutes les valeurs restent lisibles sans survol : nom complet de chaque lien, tableau
     * dépliable, barres horizontales chiffrées sur tablette et téléphone.
     * Sous le profil, six repères datés (faits validés de HistoricalErasSeeder).
     */
    import { Link } from '@inertiajs/svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';

    /** @type {{ profile?: Array<{ year: number, km: number, lines: number, current: boolean }> }} */
    let { profile = [] } = $props();

    const milestones = ['1827', '1842', '1879', '1930', '1938', '1981'];

    const nf = $derived(new Intl.NumberFormat($i18n.language));
    const rows = $derived(profile.filter((r) => r.km > 0));
    const peak = $derived(rows.reduce((best, r) => (best && best.km >= r.km ? best : r), null));
    // Graduation tous les 20 000 km (10 000 pour un petit réseau, en test ou en développement).
    const step = $derived(peak && peak.km > 30000 ? 20000 : 10000);
    const scaleMax = $derived(peak ? Math.max(step, Math.ceil(peak.km / step) * step) : step);
    const ticks = $derived(Array.from({ length: scaleMax / step }, (_, i) => (i + 1) * step));
    const firstYear = $derived(rows[0]?.year ?? 0);

    function km(n) {
        return $i18n.t('home.network.km', { value: nf.format(n) });
    }
    function lineCount(n) {
        return $i18n.t('home.network.lines', { count: n, value: nf.format(n) });
    }
    function barLabel(r) {
        return r.current
            ? $i18n.t('home.network.bar_today', { km: km(r.km), lines: lineCount(r.lines) })
            : $i18n.t('home.network.bar', {
                  year: r.year,
                  km: km(r.km),
                  lines: lineCount(r.lines),
              });
    }
</script>

<SectionBlock
    id="reseau"
    kind="alt"
    title={$i18n.t('home.network.title')}
    intro={$i18n.t('home.network.intro')}
    maxWidth="1180px"
>
    {#if rows.length > 1}
        <figure class="profile" style:--max={scaleMax} style:--n={rows.length}>
            <figcaption>{$i18n.t('home.network.chart_caption')}</figcaption>
            <div class="plot">
                <div class="ticks" aria-hidden="true">
                    {#each ticks as t (t)}
                        <span class="tick" style:--v={t}
                            ><span class="tick-label">{nf.format(t)}</span></span
                        >
                    {/each}
                </div>
                <ol class="bars">
                    {#each rows as r (r.year)}
                        <li
                            class={[
                                'decade',
                                {
                                    peak: r === peak,
                                    today: r.current,
                                    alternate: ((r.year - firstYear) / 10) % 2 === 1,
                                },
                            ]}
                            style:--km={r.km}
                        >
                            <Link href={`/maps?era=${r.year}`} class="bar-link">
                                <span class="vff-sr-only">{barLabel(r)}</span>
                                <span class="col" aria-hidden="true">
                                    <span class="bar"></span>
                                    <span class="value">{nf.format(r.km)}</span>
                                </span>
                                <span class="year" aria-hidden="true"
                                    >{r.current ? $i18n.t('home.network.today') : r.year}</span
                                >
                            </Link>
                        </li>
                    {/each}
                </ol>
            </div>
        </figure>

        <details class="figures">
            <summary>{$i18n.t('home.network.table_toggle')}</summary>
            <!-- Trois colonnes courtes : le tableau tient à 320 px, pas de défilement à rendre focalisable. -->
            <div class="vff-table-wrap">
                <table class="vff-table">
                    <caption class="vff-sr-only">{$i18n.t('home.network.table_caption')}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{$i18n.t('home.network.col_decade')}</th>
                            <th scope="col" class="num">{$i18n.t('home.network.col_length')}</th>
                            <th scope="col" class="num">{$i18n.t('home.network.col_lines')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each rows as r (r.year)}
                            <tr>
                                <th scope="row"
                                    >{r.current ? $i18n.t('home.network.today') : r.year}</th
                                >
                                <td class="num">{km(r.km)}</td>
                                <td class="num">{nf.format(r.lines)}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </details>
    {/if}

    <h3 class="milestones-title">{$i18n.t('home.network.milestones_title')}</h3>
    <ol class="milestones">
        {#each milestones as year (year)}
            <li>
                <span class="m-year">{year}</span>
                <p>{$i18n.t(`home.network.m${year}`)}</p>
            </li>
        {/each}
    </ol>

    {#snippet actions()}
        <Link href="/maps" class="vff-btn primary">
            <i class="bi bi-map" aria-hidden="true"></i>
            {$i18n.t('home.network.cta')}
        </Link>
    {/snippet}
</SectionBlock>

<style>
    .profile {
        margin: 0;
    }
    figcaption {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* Ordinateur : colonnes verticales, graduations à gauche. */
    .plot {
        --year-row: 32px;
        position: relative;
        padding: 36px 0 0 64px;
    }
    .ticks {
        position: absolute;
        top: 36px;
        bottom: var(--year-row);
        left: 64px;
        right: 0;
        pointer-events: none;
    }
    .ticks::after {
        /* Ligne de base du profil. */
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        border-top: 1px solid var(--vff-border-control);
    }
    .tick {
        position: absolute;
        left: 0;
        right: 0;
        bottom: calc(var(--v) / var(--max) * 100%);
        border-top: 1px dashed var(--vff-border-strong);
    }
    .tick-label {
        position: absolute;
        right: calc(100% + 10px);
        top: 0;
        transform: translateY(-50%);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        line-height: 1;
        white-space: nowrap;
        color: var(--vff-text-muted);
    }
    .bars {
        display: grid;
        grid-template-columns: repeat(var(--n), minmax(0, 1fr));
        gap: 0 6px;
        height: 300px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .decade {
        min-width: 0;
        margin: 0;
    }
    .bars :global(.bar-link) {
        display: grid;
        /* Colonne fixe : le libellé « Aujourd'hui », plus large que la colonne, déborde sans
           élargir la barre. */
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: minmax(0, 1fr) var(--year-row);
        height: 100%;
        color: inherit;
        text-decoration: none;
        border-radius: var(--vff-radius-sm);
    }
    .col {
        position: relative;
    }
    .bar {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: calc(var(--km) / var(--max) * 100%);
        background-color: var(--vff-accent-soft);
        border-top: 3px solid var(--vff-accent-strong);
        transition: background-color var(--vff-transition);
    }
    .value {
        position: absolute;
        left: 50%;
        bottom: calc(var(--km) / var(--max) * 100% + 6px);
        transform: translateX(-50%);
        padding: 0 3px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        line-height: 1.3;
        white-space: nowrap;
        color: var(--vff-text);
        background-color: var(--vff-bg-subtle);
        visibility: hidden;
    }
    .peak .value,
    .today .value,
    .decade:hover .value,
    .decade:has(:focus-visible) .value {
        visibility: visible;
    }
    .decade:hover .bar,
    .decade:has(:focus-visible) .bar {
        background-color: var(--vff-accent-strong);
    }
    .year {
        align-self: center;
        overflow: hidden;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        text-align: center;
        white-space: nowrap;
        color: var(--vff-text-muted);
    }
    .today .year {
        overflow: visible;
        font-weight: 600;
        color: var(--vff-accent-ink);
    }
    .decade:hover .year {
        color: var(--vff-text);
    }

    /* Petit ordinateur : une année sur deux sous les colonnes (les autres restent lues). */
    @media (max-width: 1180px) {
        .alternate:not(.today) .year {
            visibility: hidden;
        }
    }

    /* Tablette et téléphone : barres horizontales, toutes chiffrées. */
    @media (max-width: 900px) {
        .plot {
            padding: 0;
        }
        .ticks {
            display: none;
        }
        .bars {
            grid-template-columns: minmax(0, 1fr);
            gap: 2px;
            height: auto;
        }
        .bars :global(.bar-link) {
            grid-template-columns: 6.5rem minmax(0, 1fr);
            grid-template-rows: none;
            grid-template-areas: 'year col';
            align-items: center;
            column-gap: var(--vff-space-3);
            min-height: 32px;
        }
        .year,
        .alternate:not(.today) .year {
            grid-area: year;
            text-align: left;
            visibility: visible;
        }
        .col {
            grid-area: col;
            height: 18px;
        }
        .bar {
            top: 0;
            right: auto;
            width: calc(var(--km) / var(--max) * (100% - 4.5rem));
            height: auto;
            border-top: 0;
            border-right: 3px solid var(--vff-accent-strong);
        }
        .value {
            top: 50%;
            bottom: auto;
            left: calc(var(--km) / var(--max) * (100% - 4.5rem) + 8px);
            transform: translateY(-50%);
            padding: 0;
            font-weight: 400;
            background: none;
            visibility: visible;
        }
        .peak .value,
        .today .value {
            font-weight: 600;
        }
    }

    .figures {
        margin-top: var(--vff-space-5);
    }
    .figures summary {
        display: list-item;
        width: fit-content;
        min-height: 24px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 24px;
        color: var(--vff-link);
        text-decoration: underline;
        text-underline-offset: 0.18em;
        cursor: pointer;
    }
    .figures .vff-table-wrap {
        max-width: 560px;
        margin-top: var(--vff-space-3);
    }

    .milestones-title {
        margin: var(--vff-section-space) 0 var(--vff-space-5);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.35;
        color: var(--vff-text);
    }
    .milestones {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--vff-space-5) var(--vff-space-6);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .milestones li {
        min-width: 0;
        margin: 0;
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border-strong);
    }
    .m-year {
        display: block;
        margin-bottom: 6px;
        font-family: var(--vff-font-display);
        font-size: 1.5rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
        color: var(--vff-text);
    }
    .milestones p {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    @media (max-width: 900px) {
        .milestones {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 600px) {
        .milestones {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
