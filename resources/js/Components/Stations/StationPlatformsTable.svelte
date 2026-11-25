<script>
    import { formatNumber } from '$lib/typo.js';
    import { heightCm, platformGroups } from '$lib/stationTracks.js';

    /**
     * Quais d'une gare (section « Voies de la gare » de la fiche et page /stations/{slug}/voies) : un quai par rangée,
     * avec les voies qu'il borde (un quai central en borde deux), sa longueur utile et sa hauteur au-dessus du rail
     * (RINF), et ses sources. Les PK ne sont pas répétés : le schéma les montre et l'API les donne. C'est aussi
     * l'équivalent textuel des quais dessinés dans le schéma.
     *
     * Un tableau quand la place le permet ; dans un cadre étroit (téléphone), une liste d'une ligne par quai, au lieu
     * d'un tableau qui défilerait en largeur avec des rangées hautes. Une seule des deux formes est affichée (requête
     * de conteneur), l'autre est masquée aussi pour les lecteurs d'écran.
     *
     * `drawing` : la page a un schéma (un quai sans voie dessinée ni PK y est dit « non dessiné »). La colonne de la
     * ligne n'apparaît que si les quais sont sur plusieurs lignes. Le titre « Quais » est posé au-dessus par la page :
     * la légende du tableau n'est lue que par les lecteurs d'écran.
     *
     * @type {{ platforms: Array<any>, lang: string, t: (key: string, options?: object) => string,
     *   drawing?: boolean }}
     */
    let { platforms, lang, t, drawing = false } = $props();

    const groups = $derived(platformGroups(platforms));
    const showLine = $derived(new Set(platforms.map((p) => p.line_code)).size > 1);
    const sourceNames = (g) =>
        g.sources
            .map((s) => t(`tracks.station.platforms.source_names.${s}`, { defaultValue: s }))
            .join(', ');
    const length = (g) =>
        g.length_m === null
            ? ''
            : t('tracks.station.platforms.metres', { value: formatNumber(g.length_m, lang) });
    const heights = (g) =>
        g.heights_mm
            .map((h) =>
                t('tracks.station.platforms.cm', { value: formatNumber(heightCm(h), lang) }),
            )
            .join(', ');
    /** Remarque sur la position : placé à peu près, ou pas dans le schéma. */
    const remark = (g) =>
        g.approx
            ? t('tracks.station.platforms.approx')
            : drawing && !g.placed
              ? t('tracks.station.platforms.unplaced')
              : '';
</script>

<div class="platforms">
    <!-- svelte-ignore a11y_no_noninteractive_tabindex (tableau qui peut défiler en largeur, atteint au clavier) -->
    <div
        class="vff-table-wrap wide"
        role="region"
        tabindex="0"
        aria-label={t('tracks.station.platforms.caption')}
    >
        <table class="vff-table">
            <caption class="vff-sr-only">{t('tracks.station.platforms.caption')}</caption>
            <thead>
                <tr>
                    <th scope="col">{t('tracks.station.platforms.platform')}</th>
                    <th scope="col">{t('tracks.station.platforms.tracks')}</th>
                    {#if showLine}<th scope="col">{t('tracks.station.platforms.line')}</th>{/if}
                    <th scope="col" class="num">{t('tracks.station.platforms.length')}</th>
                    <th scope="col" class="num">{t('tracks.station.platforms.height')}</th>
                    <th scope="col">{t('tracks.station.platforms.source')}</th>
                </tr>
            </thead>
            <tbody>
                {#each groups as g (g.key)}
                    <tr>
                        <th scope="row">{g.ref}</th>
                        <td>{g.tracks.join(', ')}</td>
                        {#if showLine}<td class="num">{g.line_code ?? ''}</td>{/if}
                        <td class="num">{length(g)}</td>
                        <td class="num">{heights(g)}</td>
                        <td class="src">
                            {sourceNames(g)}{#if remark(g)}<span class="note">{remark(g)}</span
                                >{/if}
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <ul class="narrow" aria-label={t('tracks.station.platforms.caption')}>
        {#each groups as g (g.key)}
            <li>
                <span class="name">{g.ref}</span>
                <span class="facts"
                    >{[
                        t('tracks.station.platforms.on_tracks', {
                            count: g.tracks.length,
                            tracks: g.tracks.join(', '),
                        }),
                        showLine && g.line_code
                            ? t('tracks.station.platforms.on_line', { line: g.line_code })
                            : '',
                        length(g),
                        heights(g),
                    ]
                        .filter(Boolean)
                        .join(' · ')}</span
                >
                <span class="src">{[sourceNames(g), remark(g)].filter(Boolean).join(', ')}</span>
            </li>
        {/each}
    </ul>
</div>

<style>
    .platforms {
        max-width: 56rem;
        container-type: inline-size;
    }
    .wide:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    th[scope='row'] {
        font-weight: 700;
        white-space: nowrap;
    }
    .src {
        color: var(--vff-text-muted);
    }
    .note {
        display: block;
        font-size: var(--vff-fs-xs);
    }
    .narrow {
        display: none;
        margin: 0;
        padding: 0;
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .narrow li {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 10px 16px;
    }
    .narrow li + li {
        border-top: 1px solid var(--vff-border);
    }
    .narrow .name {
        font-weight: 700;
    }
    .narrow .facts {
        font-variant-numeric: tabular-nums;
    }
    .narrow .src {
        font-size: var(--vff-fs-xs);
    }
    /* Cadre étroit : la liste à la place du tableau. */
    @container (max-width: 36rem) {
        .wide {
            display: none;
        }
        .narrow {
            display: block;
        }
    }
</style>
