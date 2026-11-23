<script>
    import { i18n } from '$lib/i18n.js';
    import { formatNumber, NBSP } from '$lib/typo.js';
    import { voltageText } from '$lib/lineElectrification.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import {
        anchors,
        BAND_KEYS,
        defaultEra,
        labelled,
        legendKeys,
        mergeRuns,
        pkLabel,
        pointsIn,
        rows as rowList,
        slices,
    } from '$lib/lineSchematic.js';

    /**
     * Section « Schéma de ligne » de la fiche ligne (App\Services\Lines\LineSchematic) : la ligne déroulée
     * par PK, comme un schéma d'exploitation. Chargée à la demande (quand la section approche de l'écran,
     * ou par son bouton), par GET /api/v1/lines/{line}/schematic, gardée par ère pendant la visite : la fiche
     * elle-même ne fait aucune requête de plus.
     *
     * Rendu : une liste ordonnée de rangées dans le flux de la page (une par gare, bifurcation ou bout de
     * ligne), chacune de la hauteur de son texte plus un écart qui suit la distance ; les bandes (voies,
     * courant, signalisation, vitesse), ouvrages et passages à niveau sont posés en pourcentage de
     * l'intervalle de PK de la rangée (lib/lineSchematic.js). Les bandes sont décoratives pour les lecteurs
     * d'écran : leur contenu est repris en tableaux repliés sous le schéma. Vertical sur téléphone et
     * tablette, horizontal (défilement dans le cadre) au choix sur grand écran.
     *
     * @type {{ slug: string }}
     */
    let { slug } = $props();

    const BANDS = ['tracks', 'electrification', 'signalling', 'speed'];
    const ORIENTS = /** @type {const} */ (['horizontal', 'vertical']);

    const t = (k, o) => String($i18n.t(k, o));
    const lang = $derived($i18n.language ?? 'fr');
    const fmt = (n) => formatNumber(n, lang);

    /** @type {'idle'|'loading'|'ready'|'error'} */
    let status = $state('idle');
    /** @type {any} */
    let data = $state(null);
    /** @type {number|null} */
    let era = $state(null);
    /** Schémas déjà lus pendant la visite, par année d'ère (pas un état affiché : objet simple). */
    /** @type {Record<number, any>} */
    const cache = {};
    let token = 0;

    let layers = $state({
        closed: true,
        junctions: true,
        crossings: false,
        structures: true,
        tracks: true,
        electrification: true,
        signalling: true,
        speed: true,
    });
    /** @type {'vertical'|'horizontal'|null} */
    let orient = $state(null);
    let width = $state(0);
    /** @type {HTMLElement|undefined} */
    let sectionEl = $state();

    const wide = $derived(width >= 1000);
    const horizontal = $derived(wide && (orient ?? 'horizontal') === 'horizontal');
    // Étiquettes de valeur dans les bandes (« 160 », « BAL ») : seulement quand la place le permet.
    const withLabels = $derived(!horizontal && width >= 720);
    // Téléphone : le PK passe sous le nom et les correspondances se replient.
    const compact = $derived(width > 0 && width < 560);

    function url(year) {
        const base = `/api/v1/lines/${encodeURIComponent(slug)}/schematic`;
        return year === null ? base : `${base}?era=${year}`;
    }

    /** @param {number|null} year null : ère par défaut (l'ère courante, ou l'âge d'or d'une ligne fermée) */
    async function load(year) {
        const mine = ++token;
        if (year !== null && cache[year]) {
            data = cache[year];
            era = year;
            status = 'ready';
            return;
        }
        status = data ? 'ready' : 'loading';
        try {
            const res = await fetch(url(year), { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(String(res.status));
            const json = await res.json();
            if (mine !== token) return;
            cache[json.era_year] = json;
            const preferred = year === null ? defaultEra(json.eras) : null;
            if (preferred !== null && preferred !== json.era_year) {
                await load(preferred);
                return;
            }
            data = json;
            era = json.era_year;
            status = 'ready';
        } catch {
            if (mine === token) status = 'error';
        }
    }

    function start() {
        if (status === 'idle') load(null);
    }

    // Chargé quand la section approche de l'écran (600 px d'avance), une seule fois.
    $effect(() => {
        if (!sectionEl || status !== 'idle') return;
        if (typeof IntersectionObserver === 'undefined') {
            start();
            return;
        }
        const io = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    io.disconnect();
                    start();
                }
            },
            { rootMargin: '600px 0px' },
        );
        io.observe(sectionEl);
        return () => io.disconnect();
    });

    const current = $derived(data?.is_current_era ?? true);
    // Couche vide : aujourd'hui, la source ne publie rien pour la ligne ; à une ère passée, rien n'est connu.
    const naText = $derived(t(current ? 'schematic.no_data' : 'schematic.unavailable'));
    /** @type {Record<string, boolean>} */
    const available = $derived(
        data
            ? {
                  closed: data.stations.some((s) => !s.open),
                  junctions: data.junctions.length > 0,
                  crossings: data.level_crossings.items.length > 0,
                  structures: data.structures.items.length > 0,
                  ...Object.fromEntries(BANDS.map((b) => [b, data.bands[b].available])),
              }
            : {},
    );
    // Aucune gare ouverte ni aucune bande à l'ère : la voie est dessinée en pointillés gris.
    const dormant = $derived(
        data ? !data.stations.some((s) => s.open) && !BANDS.some((b) => available[b]) : false,
    );
    const bandsOn = $derived(data ? BANDS.filter((b) => layers[b] && available[b]) : []);
    const showStructures = $derived(layers.structures && available.structures);
    const showCrossings = $derived(layers.crossings && available.crossings);
    const list = $derived(
        data
            ? rowList(
                  anchors(data, {
                      closed: layers.closed || !data.stations.some((s) => s.open),
                      junctions: layers.junctions,
                  }),
              )
            : [],
    );
    const crossings = $derived(data?.level_crossings.items ?? []);
    const structures = $derived(data?.structures.items ?? []);

    // Largeur des colonnes (vertical) ou hauteur des rangées de bandes (horizontal).
    const BAND_W = { tracks: '6.2em', electrification: '5.8em', signalling: '5.6em', speed: '4em' };
    const cols = $derived(
        [
            ...(compact ? [] : ['5.2em']),
            ...bandsOn.map((b) => (withLabels ? BAND_W[b] : '16px')),
            ...(showStructures ? ['10px'] : []),
            '30px',
            'minmax(0, 1fr)',
        ].join(' '),
    );

    /** Valeur courte d'une tranche, pour l'étiquette de la bande. */
    function short(band, r) {
        if (band === 'tracks') {
            const k = BAND_KEYS.tracks(r);
            return t(`schematic.tracks.short.${k}`, { count: r.tracks ?? '' });
        }
        if (band === 'electrification') return voltageText(r.voltage_v, fmt) ?? '?';
        if (band === 'signalling') {
            return r.signalling === 'NONE'
                ? t('schematic.signalling_none_short')
                : r.signalling.replace('_L', ' N').replace('-', ' ');
        }
        return String(r.max_speed_kmh);
    }

    /** Valeur complète d'une tranche (tableaux, infobulles). */
    function full(band, r) {
        if (band === 'tracks') return r.regime;
        if (band === 'electrification') {
            const v = voltageText(r.voltage_v, fmt);
            return v
                ? `${v} ${t(`electrification.system.${r.system}`)}`
                : t('schematic.electrification.unknown');
        }
        if (band === 'signalling') {
            const code = t(`lines.signalling.codes.${r.signalling}`, {
                defaultValue: r.signalling,
            });
            return r.train_protection.length ? `${code} (${r.train_protection.join(', ')})` : code;
        }
        return `${fmt(r.max_speed_kmh)}${NBSP}km/h`;
    }

    const detail = (band) => (r) => full(band, r);
    const runs = $derived(
        data
            ? Object.fromEntries(BANDS.map((b) => [b, mergeRuns(data.bands[b].ranges, detail(b))]))
            : {},
    );

    function stationMeta(s) {
        return [
            s.type === 'halt' ? t('schematic.type.halt') : null,
            s.status !== 'active' ? t(`schematic.status.${s.status}`) : null,
            s.is_terminal ? t('schematic.terminus') : null,
        ]
            .filter(Boolean)
            .join(' · ');
    }

    function structureTitle(s) {
        const kind = t(`schematic.structure_kind.${s.kind}`);
        const len = s.length_m ? `, ${fmt(s.length_m)}${NBSP}m` : '';
        return s.name ? `${kind} ${s.name}${len}` : `${kind}${len}`;
    }

    const eraOptions = $derived(
        data
            ? data.eras.map((e) => ({
                  year: e.year,
                  label: e.is_current
                      ? t('schematic.era_today')
                      : t('schematic.era_option', { year: e.year }),
              }))
            : [],
    );

    function onEra(event) {
        const year = Number(event.currentTarget.value);
        if (Number.isFinite(year)) load(year);
    }

    const listLabel = $derived(
        data?.extent
            ? t('schematic.list_label', {
                  from: data.extent.pk_start_label,
                  to: data.extent.pk_end_label,
              })
            : t('schematic.title'),
    );
    const LAYERS = [
        'closed',
        'junctions',
        'crossings',
        'structures',
        'tracks',
        'electrification',
        'signalling',
        'speed',
    ];
    // Couches proposées : celles qui ont des données ; aujourd'hui, les bandes vides restent visibles (désactivées,
    // « aucune tranche publiée ») ; à une ère passée, la note sous le schéma dit pourquoi elles manquent.
    const shownLayers = $derived(
        LAYERS.filter((k) => available[k] || (current && BANDS.includes(k))),
    );
</script>

{#snippet pk(row)}
    <span class="pk"
        >{#if row.item?.pk_approx}<span aria-hidden="true">≈{NBSP}</span><span class="vff-sr-only"
                >{t('schematic.pk_approx')}</span
            >{/if}{pkLabel(row.pk_m)}</span
    >
{/snippet}

{#snippet connList(lines)}
    <ul class="conn" aria-label={t('schematic.connections')}>
        {#each lines as l (l.slug)}
            <li><a class="vff-link" href="/lines/{l.slug}">{l.name}</a></li>
        {/each}
    </ul>
{/snippet}

{#snippet bandCell(band, row)}
    <span class="band b-{band}" aria-hidden="true">
        {#each slices(data.bands[band].ranges, row.from, row.to) as sl, i (i)}
            <span
                class="seg k-{BAND_KEYS[band](sl.range)}"
                style:--a={sl.a}
                style:--b={sl.b}
                title={full(band, sl.range)}
            ></span>
        {/each}
        {#if withLabels}
            {#each labelled(slices(data.bands[band].ranges, row.from, row.to, row.first)) as sl, i (i)}
                <span class="val" style:--a={sl.a}>{short(band, sl.range)}</span>
            {/each}
        {/if}
    </span>
{/snippet}

{#snippet axis(row)}
    <span class={['axis', row.last && 'last', row.kind]} aria-hidden="true">
        {#if showCrossings}
            {#each pointsIn(crossings, row.from, row.to) as p (p.item.pk_m + (p.item.name ?? ''))}
                <span
                    class="pn"
                    style:--a={p.a}
                    title="{t('schematic.crossing')} {p.item.name ?? ''} ({p.item.pk_label})"
                ></span>
            {/each}
        {/if}
        {#if row.kind === 'station'}
            <span
                class={[
                    'dot',
                    row.item.type,
                    !row.item.open && 'closed',
                    row.item.status === 'uncertain' && 'uncertain',
                    row.item.is_terminal && 'terminal',
                ]}
            ></span>
        {:else if row.kind === 'junction'}
            <span class="branch"></span>
        {:else}
            <span class="stop"></span>
        {/if}
    </span>
{/snippet}

<SheetSection
    id="schematic"
    title={t('schematic.title')}
    intro={t('schematic.intro')}
    bind:sectionEl
>
    <div class="schematic" bind:clientWidth={width}>
        {#if status === 'idle' || status === 'loading'}
            <p class="state" role="status">
                {#if status === 'loading'}{t('schematic.loading')}{:else}<button
                        type="button"
                        class="vff-btn secondary"
                        onclick={start}>{t('schematic.load')}</button
                    >{/if}
            </p>
        {:else if status === 'error' && !data}
            <p class="state" role="alert">
                {t('schematic.error')}
                <button type="button" class="vff-btn secondary" onclick={() => load(era)}
                    >{t('schematic.retry')}</button
                >
            </p>
        {/if}

        {#if data}
            <div class="controls">
                <div class="vff-field era">
                    <label class="vff-label" for="schematic-era">{t('schematic.era_label')}</label>
                    <select id="schematic-era" class="vff-select" value={era} onchange={onEra}>
                        {#each eraOptions as o (o.year)}
                            <option value={o.year}>{o.label}</option>
                        {/each}
                    </select>
                </div>
                {#if shownLayers.length}
                    <fieldset class="layers">
                        <legend class="vff-label">{t('schematic.layers')}</legend>
                        <div class="checks">
                            {#each shownLayers as key (key)}
                                <label class="vff-check" class:off={!available[key]}>
                                    {#if available[key]}
                                        <input type="checkbox" bind:checked={layers[key]} />
                                    {:else}
                                        <input
                                            type="checkbox"
                                            checked={false}
                                            disabled
                                            aria-describedby="schematic-unavailable"
                                        />
                                    {/if}
                                    <span
                                        >{t(`schematic.layer.${key}`)}{#if !available[key]}<span
                                                class="na"
                                            >
                                                ({naText})</span
                                            >{/if}</span
                                    >
                                </label>
                            {/each}
                        </div>
                        <span id="schematic-unavailable" class="vff-sr-only">{naText}</span>
                    </fieldset>
                {/if}
                {#if wide}
                    <fieldset class="orient">
                        <legend class="vff-label">{t('schematic.orientation')}</legend>
                        <div class="checks">
                            {#each ORIENTS as o (o)}
                                <label class="vff-check">
                                    <input
                                        type="radio"
                                        name="schematic-orient"
                                        value={o}
                                        checked={(orient ?? 'horizontal') === o}
                                        onchange={() => (orient = o)}
                                    />
                                    {t(`schematic.${o}`)}
                                </label>
                            {/each}
                        </div>
                    </fieldset>
                {/if}
            </div>

            {#if bandsOn.length}
                <div class="legend" aria-label={t('schematic.legend')} role="group">
                    {#each bandsOn as band (band)}
                        <div class="legend-band">
                            <p class="legend-title">{t(`schematic.layer.${band}`)}</p>
                            <ul>
                                {#each legendKeys(band, data.bands[band].ranges) as k (k)}
                                    <li>
                                        <span class="sw b-{band} k-{k}" aria-hidden="true"></span>
                                        {t(`schematic.${band}.${k}`)}
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/each}
                </div>
            {/if}

            {#if list.length === 0}
                <p class="state">{t('schematic.empty')}</p>
            {:else}
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (région défilante, atteinte au clavier) -->
                <div
                    class={[
                        'frame',
                        horizontal ? 'h' : 'v',
                        status === 'loading' && 'busy',
                        dormant && 'dormant',
                    ]}
                    role={horizontal ? 'region' : undefined}
                    aria-label={horizontal ? t('schematic.scroll_label') : undefined}
                    tabindex={horizontal ? 0 : undefined}
                >
                    {#if !horizontal && bandsOn.length}
                        <div class="heads" style:--cols={cols} aria-hidden="true">
                            {#if !compact}<span></span>{/if}
                            {#each bandsOn as band (band)}
                                <span class="head">{t(`schematic.band_head.${band}`)}</span>
                            {/each}
                        </div>
                    {/if}
                    <ol class="rows" style:--cols={cols} aria-label={listLabel}>
                        {#each list as row (row.key)}
                            <li
                                class={['row', row.kind, row.item && !row.item.open && 'is-closed']}
                                style:--gap="{row.gap}px"
                            >
                                {#if !compact}{@render pk(row)}{/if}
                                {#each bandsOn as band (band)}
                                    {@render bandCell(band, row)}
                                {/each}
                                {#if showStructures}
                                    <span class="struct" aria-hidden="true">
                                        {#each slices(structures, row.from, row.to) as sl (sl.range.id)}
                                            <span
                                                class="ouv k-{sl.range.kind}"
                                                style:--a={sl.a}
                                                style:--b={sl.b}
                                                title={structureTitle(sl.range)}
                                            ></span>
                                        {/each}
                                    </span>
                                {/if}
                                {@render axis(row)}
                                <div class="lab">
                                    {#if row.kind === 'station'}
                                        <p class="name">
                                            <a class="vff-link" href="/stations/{row.item.slug}"
                                                >{row.item.name}</a
                                            >
                                        </p>
                                        {#if compact}
                                            <p class="meta">
                                                {@render pk(row)}{#if stationMeta(row.item)}<span
                                                        class="sep"
                                                        aria-hidden="true">·</span
                                                    >{stationMeta(row.item)}{/if}
                                            </p>
                                        {:else if stationMeta(row.item)}<p class="meta">
                                                {stationMeta(row.item)}
                                            </p>{/if}
                                        {#if row.item.connections.length && compact}
                                            <details class="conn-fold">
                                                <summary
                                                    >{t('schematic.connections_count', {
                                                        count: row.item.connections.length,
                                                    })}</summary
                                                >
                                                {@render connList(row.item.connections)}
                                            </details>
                                        {:else if row.item.connections.length}
                                            {@render connList(row.item.connections)}
                                        {/if}
                                    {:else if row.kind === 'junction'}
                                        <p class="meta jn">
                                            {t('schematic.junction')}{#if compact}<span
                                                    class="sep"
                                                    aria-hidden="true">·</span
                                                >{@render pk(row)}{/if}
                                        </p>
                                        {@render connList(row.item.lines)}
                                    {:else}
                                        <p class="meta">
                                            {#if compact}{@render pk(row)}<span
                                                    class="sep"
                                                    aria-hidden="true">·</span
                                                >{/if}
                                            {t(
                                                row.key === 'end-start'
                                                    ? 'schematic.data_start'
                                                    : 'schematic.data_end',
                                            )}
                                        </p>
                                    {/if}
                                </div>
                            </li>
                        {/each}
                    </ol>
                </div>
            {/if}

            <div class="notes">
                {#if showCrossings}
                    <p>{t('schematic.crossings_count', { count: crossings.length })}</p>
                {/if}
                <p>{current ? t('schematic.note_today') : t('schematic.note_past')}</p>
                <p>{t('schematic.note_pk')} {t('schematic.gaps')}</p>
            </div>

            {#if BANDS.some((b) => runs[b].length) || structures.length}
                <details class="tables">
                    <summary>{t('schematic.tables')}</summary>
                    {#each BANDS as band (band)}
                        {#if runs[band].length}
                            <!-- svelte-ignore a11y_no_noninteractive_tabindex (région défilante, atteinte au clavier) -->
                            <div
                                class="vff-table-wrap"
                                role="region"
                                tabindex="0"
                                aria-label={t(`schematic.layer.${band}`)}
                            >
                                <table class="vff-table">
                                    <caption
                                        >{t('schematic.table_caption', {
                                            band: t(`schematic.layer.${band}`),
                                        })}</caption
                                    >
                                    <thead>
                                        <tr>
                                            <th scope="col">{t('schematic.col_from')}</th>
                                            <th scope="col">{t('schematic.col_to')}</th>
                                            <th scope="col">{t('schematic.col_value')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each runs[band] as r, i (i)}
                                            <tr>
                                                <td class="num">{pkLabel(r.pk_start_m)}</td>
                                                <td class="num">{pkLabel(r.pk_end_m)}</td>
                                                <td>{full(band, r)}</td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        {/if}
                    {/each}
                    {#if structures.length}
                        <!-- svelte-ignore a11y_no_noninteractive_tabindex (région défilante, atteinte au clavier) -->
                        <div
                            class="vff-table-wrap"
                            role="region"
                            tabindex="0"
                            aria-label={t('schematic.layer.structures')}
                        >
                            <table class="vff-table">
                                <caption
                                    >{t('schematic.table_caption', {
                                        band: t('schematic.layer.structures'),
                                    })}</caption
                                >
                                <thead>
                                    <tr>
                                        <th scope="col">{t('schematic.col_from')}</th>
                                        <th scope="col">{t('schematic.col_to')}</th>
                                        <th scope="col">{t('schematic.col_name')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each structures as s (s.id)}
                                        <tr>
                                            <td class="num">{pkLabel(s.pk_start_m)}</td>
                                            <td class="num">{pkLabel(s.pk_end_m)}</td>
                                            <td>{structureTitle(s)}</td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </details>
            {/if}
        {/if}
    </div>
</SheetSection>

<style>
    /* Teintes des bandes : cinq teintes catégorielles des calques de la carte (lib/overlayPalette.js)
       pour le courant et la signalisation, une rampe orange pour la vitesse. La couleur n'est jamais
       seule : étiquettes de valeur, infobulles, légende et tableaux. */
    .schematic {
        --sch-blue: #2a78d6;
        --sch-green: #1baf7a;
        --sch-amber: #eda100;
        --sch-pink: #e87ba4;
        --sch-violet: #6250d6;
        --sch-s0: #f2c094;
        --sch-s1: #eb9a55;
        --sch-s2: #dc7020;
        --sch-s3: #b04800;
        --sch-s4: #6e2c00;
        --sch-edge: rgba(37, 34, 30, 0.28);
        --sch-rail: var(--vff-accent);
        --sch-ink: var(--vff-text);
        --dot: 0.8em;
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-5);
        min-width: 0;
    }
    :global([data-theme='dark']) .schematic {
        --sch-s0: #8f5a33;
        --sch-s1: #b8662a;
        --sch-s2: #e07a30;
        --sch-s3: #f5a060;
        --sch-s4: #ffd0a8;
        --sch-edge: rgba(255, 255, 255, 0.22);
    }
    .state {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--vff-space-3);
        margin: 0;
        color: var(--vff-text-muted);
    }

    /* Réglages */
    .controls {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: var(--vff-space-4) var(--vff-space-6);
    }
    .era {
        width: 14rem;
        max-width: 100%;
    }
    fieldset {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }
    fieldset legend {
        margin-bottom: 6px;
        padding: 0;
    }
    .layers {
        flex: 1 1 24rem;
    }
    .checks {
        display: flex;
        flex-wrap: wrap;
        gap: 4px var(--vff-space-5);
    }
    .vff-check.off {
        color: var(--vff-text-muted);
        cursor: not-allowed;
    }
    .na {
        margin-left: 0.3em;
        font-size: var(--vff-fs-xs);
    }

    /* Légende */
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-4) var(--vff-space-6);
        padding: var(--vff-space-4) var(--vff-space-5);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
    }
    .legend-title {
        margin: 0 0 4px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
    }
    .legend ul {
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-sm);
    }
    .legend li {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sw {
        position: relative;
        flex-shrink: 0;
        width: 22px;
        height: 14px;
        border-radius: var(--vff-radius-xs);
    }

    /* Teintes communes aux bandes et à la légende */
    .k-dc1500 {
        background-color: var(--sch-amber);
    }
    .k-ac25 {
        background-color: var(--sch-blue);
    }
    .b-electrification.k-other,
    .b-electrification .k-other {
        background-color: var(--sch-violet);
    }
    .k-third_rail {
        background-color: var(--sch-pink);
    }
    .b-electrification.k-unknown,
    .b-electrification .k-unknown {
        background: repeating-linear-gradient(
            45deg,
            var(--vff-border-control) 0 3px,
            transparent 3px 6px
        );
    }
    .k-automatic_block {
        background-color: var(--sch-green);
    }
    .k-restricted_block {
        background-color: var(--sch-blue);
    }
    .k-manual_block {
        background-color: var(--sch-amber);
    }
    .k-telephone {
        background-color: var(--sch-pink);
    }
    .k-cab {
        background-color: var(--sch-violet);
    }
    .b-signalling.k-none {
        background-color: transparent;
        box-shadow: inset 0 0 0 2px var(--vff-border-control);
    }
    .v .b-signalling .k-none {
        box-shadow:
            inset 2px 0 0 var(--vff-border-control),
            inset -2px 0 0 var(--vff-border-control);
    }
    .h .b-signalling .k-none {
        box-shadow:
            inset 0 2px 0 var(--vff-border-control),
            inset 0 -2px 0 var(--vff-border-control);
    }
    .k-s0 {
        background-color: var(--sch-s0);
    }
    .k-s1 {
        background-color: var(--sch-s1);
    }
    .k-s2 {
        background-color: var(--sch-s2);
    }
    .k-s3 {
        background-color: var(--sch-s3);
    }
    .k-s4 {
        background-color: var(--sch-s4);
    }
    /* Voies : un trait par voie (double voie : deux traits), voie banalisée et autres en pointillés. */
    .b-tracks.k-single,
    .b-tracks .k-single {
        background: linear-gradient(
            90deg,
            transparent 40%,
            var(--sch-ink) 40% 60%,
            transparent 60%
        );
    }
    .b-tracks.k-double,
    .b-tracks .k-double,
    .b-tracks.k-multi,
    .b-tracks .k-multi {
        background: linear-gradient(
            90deg,
            transparent 15%,
            var(--sch-ink) 15% 35%,
            transparent 35% 65%,
            var(--sch-ink) 65% 85%,
            transparent 85%
        );
    }
    .b-tracks.k-banalised,
    .b-tracks .k-banalised,
    .b-tracks.k-other,
    .b-tracks .k-other {
        background: repeating-linear-gradient(0deg, var(--sch-ink) 0 4px, transparent 4px 8px);
        background-size: 30% 100%;
        background-position: center;
        background-repeat: repeat-y;
    }

    /* Cadre du schéma */
    .frame {
        min-width: 0;
    }
    .frame.busy {
        opacity: 0.6;
    }
    .rows {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .heads {
        display: grid;
        grid-template-columns: var(--cols);
        align-items: end;
        padding-bottom: var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .head {
        overflow: hidden;
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        max-height: 8em;
        margin-left: 2px;
        line-height: 1.15;
        white-space: nowrap;
        justify-self: start;
    }

    /* Vertical : une rangée = une grille PK | bandes | ouvrages | voie | texte. */
    .v .row {
        display: grid;
        grid-template-columns: var(--cols);
        min-height: 1.6em;
    }
    .v .row > :global(*) {
        min-width: 0;
    }
    .pk {
        padding-top: 0.05em;
        padding-right: var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        font-variant-numeric: tabular-nums;
        line-height: 1.5;
        color: var(--vff-text-muted);
        white-space: nowrap;
        text-align: right;
    }
    .band,
    .struct,
    .axis {
        position: relative;
    }
    .band {
        margin-inline: 2px;
    }
    .v .seg,
    .v .ouv {
        position: absolute;
        inset-inline: 0;
        top: calc(var(--dot) + var(--a));
        height: var(--b);
    }
    .sw:not(.b-tracks):not(.k-none) {
        box-shadow: inset 0 0 0 1px var(--sch-edge);
    }
    .v :is(.b-electrification, .b-speed) .seg,
    .v .b-signalling .seg:not(.k-none) {
        box-shadow:
            inset 1px 0 0 var(--sch-edge),
            inset -1px 0 0 var(--sch-edge);
    }
    .h :is(.b-electrification, .b-speed) .seg,
    .h .b-signalling .seg:not(.k-none) {
        box-shadow:
            inset 0 1px 0 var(--sch-edge),
            inset 0 -1px 0 var(--sch-edge);
    }
    .v .b-tracks .seg,
    .v .b-electrification .seg,
    .v .b-signalling .seg,
    .v .b-speed .seg {
        width: 12px;
    }
    .val {
        position: absolute;
        top: calc(var(--dot) + var(--a));
        left: 16px;
        right: 0;
        overflow: hidden;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text-muted);
        white-space: nowrap;
        text-overflow: ellipsis;
    }
    .ouv {
        border-radius: 3px;
    }
    .ouv.k-tunnel {
        background-color: var(--vff-text-muted);
    }
    .ouv.k-viaduct,
    .ouv.k-bridge {
        box-shadow: inset 0 0 0 2px var(--vff-text-muted);
    }
    .v .struct .ouv {
        width: 6px;
        left: 2px;
    }

    /* Voie : trait continu orange d'un repère au suivant, repères posés sur la première ligne du texte. */
    .v .axis::before {
        content: '';
        position: absolute;
        top: var(--dot);
        bottom: calc(-1 * var(--dot));
        left: 50%;
        width: 4px;
        margin-left: -2px;
        background-color: var(--sch-rail);
    }
    .v .axis.last::before {
        display: none;
    }
    .v.dormant .axis::before {
        background: repeating-linear-gradient(
            180deg,
            var(--vff-text-muted) 0 8px,
            transparent 8px 14px
        );
    }
    .h.dormant .axis::before {
        background: repeating-linear-gradient(
            90deg,
            var(--vff-text-muted) 0 8px,
            transparent 8px 14px
        );
    }
    .dot,
    .branch,
    .stop,
    .pn {
        position: absolute;
        z-index: 1;
    }
    .v .dot {
        top: var(--dot);
        left: 50%;
        width: 16px;
        height: 16px;
        transform: translate(-50%, -50%);
        border: 3px solid var(--sch-ink);
        border-radius: 50%;
        background-color: var(--vff-bg);
        box-sizing: border-box;
    }
    .v .dot.halt {
        width: 12px;
        height: 12px;
        border-width: 2px;
    }
    .v .dot.terminal {
        width: 22px;
        height: 8px;
        border-radius: 3px;
        background-color: var(--sch-ink);
    }
    .dot.closed {
        border-color: var(--vff-text-muted);
        border-style: dashed;
    }
    .dot.closed.terminal {
        background-color: var(--vff-bg);
    }
    .dot.uncertain {
        border-style: dotted;
    }
    .v .branch {
        top: var(--dot);
        left: 50%;
        width: 18px;
        height: 4px;
        margin-top: -2px;
        background-color: var(--sch-rail);
        border-radius: 2px;
        transform-origin: 0 50%;
        transform: rotate(-35deg);
    }
    .v .stop {
        top: var(--dot);
        left: 50%;
        width: 14px;
        height: 4px;
        transform: translate(-50%, -50%);
        background-color: var(--vff-text-muted);
    }
    .v .pn {
        top: calc(var(--dot) + var(--a));
        left: 50%;
        width: 14px;
        height: 2px;
        transform: translate(-50%, -50%);
        background-color: var(--sch-ink);
    }
    .lab {
        padding-bottom: calc(var(--gap) + var(--vff-space-2));
        padding-left: var(--vff-space-2);
        line-height: 1.5;
    }
    .lab p {
        margin: 0;
    }
    .name {
        font-family: var(--vff-font-heading);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .name a {
        color: var(--vff-text);
    }
    .is-closed .name a {
        color: var(--vff-text-muted);
        font-weight: 400;
    }
    .meta {
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
    }
    .sep {
        margin-inline: 0.35em;
    }
    .jn {
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .conn {
        margin: 2px 0 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-xs);
        overflow-wrap: anywhere;
    }
    .conn li {
        position: relative;
        padding-left: 1.1em;
    }
    .conn li::before {
        content: '↳';
        position: absolute;
        left: 0;
        color: var(--vff-text-muted);
    }

    .lab .pk {
        padding: 0;
        font-size: inherit;
        text-align: left;
    }
    .conn-fold summary {
        width: fit-content;
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        cursor: pointer;
    }
    .conn-fold summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }

    /* Horizontal (grand écran) : les rangées deviennent des colonnes, le cadre défile en largeur. */
    .h {
        --dot: 0.75em;
        overflow-x: auto;
        padding-bottom: var(--vff-space-3);
        scrollbar-color: var(--vff-border-control) transparent;
        border-radius: var(--vff-radius-md);
    }
    .h:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .h .rows {
        display: flex;
        width: max-content;
    }
    .h .row {
        display: flex;
        flex-direction: column;
        min-width: 1.6em;
    }
    .h .row > .lab {
        order: 1;
        height: 15em;
        padding: var(--vff-space-2) 0 0 var(--gap);
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        overflow: hidden;
        line-height: 1.5;
    }
    .h .lab .name,
    .h .lab .meta,
    .h .lab .conn li {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .h .conn li {
        padding-left: 0;
    }
    .h .conn li::before {
        content: none;
    }
    .h .conn a {
        color: var(--vff-text-muted);
    }
    .h .conn li:nth-child(n + 3) {
        display: none;
    }
    .h .row > .axis {
        order: 2;
    }
    .h .row > .struct {
        order: 3;
    }
    .h .row > .b-tracks {
        order: 4;
    }
    .h .row > .b-electrification {
        order: 5;
    }
    .h .row > .b-signalling {
        order: 6;
    }
    .h .row > .b-speed {
        order: 7;
    }
    .h .row > .pk {
        order: 8;
        height: 5.6em;
        padding: 0 0 var(--vff-space-2);
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        text-align: end;
        line-height: 1.5;
    }
    .h .axis {
        height: 30px;
    }
    .h .axis::before {
        content: '';
        position: absolute;
        left: var(--dot);
        right: calc(-1 * var(--dot));
        top: 50%;
        height: 4px;
        margin-top: -2px;
        background-color: var(--sch-rail);
    }
    .h .axis.last::before {
        display: none;
    }
    .h .dot {
        left: var(--dot);
        top: 50%;
        width: 16px;
        height: 16px;
        transform: translate(-50%, -50%);
        border: 3px solid var(--sch-ink);
        border-radius: 50%;
        background-color: var(--vff-bg);
        box-sizing: border-box;
    }
    .h .dot.halt {
        width: 12px;
        height: 12px;
        border-width: 2px;
    }
    .h .dot.terminal {
        width: 8px;
        height: 22px;
        border-radius: 3px;
        background-color: var(--sch-ink);
    }
    .h .dot.closed {
        border-color: var(--vff-text-muted);
    }
    .h .dot.closed.terminal {
        background-color: var(--vff-bg);
    }
    .h .branch {
        left: var(--dot);
        top: 50%;
        width: 18px;
        height: 4px;
        margin-top: -2px;
        background-color: var(--sch-rail);
        border-radius: 2px;
        transform-origin: 0 50%;
        transform: rotate(-55deg);
    }
    .h .stop {
        left: var(--dot);
        top: 50%;
        width: 4px;
        height: 14px;
        transform: translate(-50%, -50%);
        background-color: var(--vff-text-muted);
    }
    .h .pn {
        left: calc(var(--dot) + var(--a));
        top: 50%;
        width: 2px;
        height: 14px;
        transform: translate(-50%, -50%);
        background-color: var(--sch-ink);
    }
    .h .band {
        height: 12px;
        margin: 3px 0;
    }
    .h .struct {
        height: 10px;
    }
    .h .seg,
    .h .ouv {
        position: absolute;
        inset-block: 0;
        left: calc(var(--dot) + var(--a));
        width: var(--b);
    }
    .h .struct .ouv {
        height: 6px;
        top: 2px;
    }
    .h .b-tracks .k-single {
        background: linear-gradient(0deg, transparent 40%, var(--sch-ink) 40% 60%, transparent 60%);
    }
    .h .b-tracks .k-double,
    .h .b-tracks .k-multi {
        background: linear-gradient(
            0deg,
            transparent 15%,
            var(--sch-ink) 15% 35%,
            transparent 35% 65%,
            var(--sch-ink) 65% 85%,
            transparent 85%
        );
    }
    .h .b-tracks .k-banalised,
    .h .b-tracks .k-other {
        background: repeating-linear-gradient(90deg, var(--sch-ink) 0 4px, transparent 4px 8px);
        background-size: 100% 30%;
        background-position: center;
        background-repeat: repeat-x;
    }

    /* Notes et tableaux */
    .notes {
        display: flex;
        flex-direction: column;
        gap: 4px;
        max-width: var(--vff-measure);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .notes p {
        margin: 0;
        text-wrap: pretty;
    }
    .tables {
        display: flex;
        flex-direction: column;
    }
    .tables summary {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-elevated);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        cursor: pointer;
    }
    .tables summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .tables[open] summary {
        margin-bottom: var(--vff-space-4);
    }
    .tables .vff-table-wrap {
        max-width: 44rem;
        margin-bottom: var(--vff-space-4);
    }
    .tables caption {
        padding: var(--vff-space-3) var(--vff-space-4);
        text-align: left;
        font-family: var(--vff-font-heading);
        font-weight: 600;
    }
    .num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    @media print {
        .controls,
        .tables {
            display: none;
        }
    }
</style>
