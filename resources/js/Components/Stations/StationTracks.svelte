<script>
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import StationPlatformsTable from '$components/Stations/StationPlatformsTable.svelte';
    import { i18n } from '$lib/i18n.js';
    import { pkLabel } from '$lib/trackLayout.js';
    import { fetchStationTracks, hasStationTracks } from '$lib/stationTracks.js';

    /**
     * Section « Voies de la gare » de la fiche (état actuel seulement) : pour chaque ligne dont les voies sont décrites
     * autour de la gare, le nombre de voies et d'aiguillages ; les quais (voies bordées, longueur, hauteur) ; le lien
     * vers le plan des voies (/stations/{slug}/voies), où le schéma a toute la largeur de l'écran. Lue à la demande,
     * quand la section approche de l'écran, par un aperçu léger de l'API (`kinds=operational_points` : nombres et
     * quais, sans les voies) : la fiche ne fait aucune requête de plus. Rien n'est affiché quand la gare n'a ni voie
     * décrite ni quai connu.
     *
     * @type {{ slug: string, name: string, fetchImpl?: typeof fetch }}
     */
    let { slug, name, fetchImpl = (url, init) => fetch(url, init) } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const nameLang = $derived(lang === 'fr' ? undefined : 'fr');

    /** @type {any} */
    let data = $state.raw(null);
    let asked = $state(false);
    /** @type {HTMLElement|undefined} */
    let sentinel = $state();

    async function load() {
        asked = true;
        try {
            data = await fetchStationTracks(slug, fetchImpl, { kinds: ['operational_points'] });
        } catch {
            // Section facultative : sans réponse, la fiche reste complète.
        }
    }

    // Lu quand la section approche de l'écran (600 px d'avance), une seule fois.
    $effect(() => {
        if (!sentinel || asked) return;
        if (typeof IntersectionObserver === 'undefined') {
            load();
            return;
        }
        const io = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    io.disconnect();
                    load();
                }
            },
            { rootMargin: '600px 0px' },
        );
        io.observe(sentinel);
        return () => io.disconnect();
    });

    const plural = (key, n) =>
        t(`${key}_${n === 1 ? 'one' : 'other'}`, { count: n.toLocaleString(lang) });
    const href = $derived(`/stations/${encodeURIComponent(slug)}/voies`);
</script>

{#if hasStationTracks(data)}
    <SheetSection
        id="tracks"
        title={t('tracks.station.title')}
        intro={t('tracks.station.section_intro')}
    >
        {#if data.lines.length}
            <ul class="lines">
                {#each data.lines as b (b.line.slug)}
                    {@const c = b.track_layout.counts}
                    <li>
                        <p class="line">
                            {#if b.line.code}<span class="vff-plate">{b.line.code}</span>{/if}
                            <a class="vff-link" href="/lines/{b.line.slug}" lang={nameLang}
                                >{b.line.name}</a
                            >
                        </p>
                        <p class="what">
                            {t('tracks.station.line_summary', {
                                tracks: plural('tracks.station.tracks', c.tracks),
                                switches: plural('tracks.count.switches', c.switches),
                                from: pkLabel(b.track_layout.pk.start_m),
                                to: pkLabel(b.track_layout.pk.end_m),
                            })}
                        </p>
                    </li>
                {/each}
            </ul>
        {/if}
        {#if data.lines.length}
            <p class="open">
                <a class="vff-btn secondary" {href}
                    >{t('tracks.station.open')}<span class="vff-sr-only"> ({name})</span></a
                >
            </p>
        {/if}
        {#if data.platforms.length}
            <h3 class="sub">{t('tracks.station.platforms.title')}</h3>
            <StationPlatformsTable platforms={data.platforms} {lang} {t} />
            <p class="note">{t('tracks.station.platforms.note')}</p>
        {/if}
    </SheetSection>
{:else if !data}
    <div bind:this={sentinel} class="sentinel" aria-hidden="true"></div>
{/if}

<style>
    .sentinel {
        height: 1px;
    }
    .lines {
        display: grid;
        gap: var(--vff-space-3);
        margin: 0 0 var(--vff-space-5);
        padding: 0;
        list-style: none;
    }
    .line,
    .what {
        margin: 0;
    }
    .line {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-weight: 700;
    }
    .what {
        color: var(--vff-text-muted);
    }
    .sub {
        margin: 0 0 var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-3) 0 0;
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .open {
        margin: 0 0 var(--vff-space-6);
    }
</style>
