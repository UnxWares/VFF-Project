<script>
    /**
     * « Autour de moi » : les lignes et les gares autour de la position de l'internaute ou d'une commune, avec
     * leur état aujourd'hui et leur histoire en une phrase, les plus proches d'abord. Aucune carte n'est chargée.
     *
     * Vie privée : rien n'est demandé au navigateur avant le geste sur « Utiliser ma position ». La position est
     * arrondie à 0,001 degré dès qu'elle est reçue (lib/nearby.js), reste en mémoire de la page (ni adresse, ni
     * stockage du navigateur) et ne part que dans l'appel de l'API (`what-was-here?summary=1`, déjà arrondie).
     * Une commune choisie vient de l'adresse (`?commune=`) : son centre est une donnée publique.
     */
    import { untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber } from '$lib/typo.js';
    import {
        DEFAULT_RADIUS,
        RADII,
        formatDistance,
        historyFacts,
        historySentence,
        lineStateKey,
        mapHref,
        nearbyUrl,
        roundCoord,
        stationFacts,
    } from '$lib/nearby.js';

    /** @type {{ place: { name: string, slug: string, lat: number, lng: number }|null, query: string, matches: { slug: string, name: string, department_code: string|null, department_name: string|null }[] }} */
    let { place, query, matches } = $props();

    /** @param {string} key @param {Record<string, string|number>} [vars] */
    const t = (key, vars) => String($i18n.t(`nearby.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');

    /** Position arrondie (mémoire de la page seulement). @type {{ lat: number, lng: number }|null} */
    let position = $state(null);
    // idle | locating | loading | done | error
    let phase = $state('idle');
    let errorKey = $state('');
    let radius = $state(DEFAULT_RADIUS);
    let retry = $state(0);
    /** @type {any} */
    let data = $state.raw(null);
    let typed = $derived(query);
    // Choisir une commune remplace la position.
    $effect.pre(() => {
        place;
        position = null;
    });

    const target = $derived(
        position
            ? { kind: 'position', lat: position.lat, lng: position.lng }
            : place
              ? { kind: 'commune', lat: place.lat, lng: place.lng }
              : null,
    );

    $effect(() => {
        const current = target;
        const r = radius;
        const l = lang;
        retry;
        if (!current) {
            data = null;
            untrack(() => {
                if (phase === 'loading' || phase === 'done') phase = 'idle';
            });
            return;
        }
        const controller = new AbortController();
        phase = 'loading';
        errorKey = '';
        fetch(nearbyUrl(current.lat, current.lng, r, l), {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(String(res.status));
                return res.json();
            })
            .then((json) => {
                data = json;
                phase = 'done';
            })
            .catch((e) => {
                if (e?.name === 'AbortError') return;
                data = null;
                phase = 'error';
                errorKey = 'error_fetch';
            });

        return () => controller.abort();
    });

    function locate() {
        if (!('geolocation' in navigator)) {
            phase = 'error';
            errorKey = 'error_unsupported';
            return;
        }
        phase = 'locating';
        errorKey = '';
        navigator.geolocation.getCurrentPosition(
            (p) => {
                // Arrondie tout de suite : la position précise n'est jamais gardée.
                position = {
                    lat: roundCoord(p.coords.latitude),
                    lng: roundCoord(p.coords.longitude),
                };
            },
            (err) => {
                phase = 'error';
                errorKey =
                    err.code === 1
                        ? 'error_denied'
                        : err.code === 3
                          ? 'error_timeout'
                          : 'error_unavailable';
            },
            { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 },
        );
    }

    /** @param {SubmitEvent} event */
    function searchCommune(event) {
        event.preventDefault();
        router.get('/nearby', { q: typed.trim() }, { preserveScroll: true, preserveState: true });
    }

    const n = (v) => formatNumber(v, lang);
    const count = (key, value) => t(key, { value: n(value) });
    const km = $derived(n(radius / 1000));

    const stations = $derived(data?.stations ?? []);
    const lines = $derived(data?.lines ?? []);
    const commune = $derived(
        place
            ? { name: place.name, slug: place.slug }
            : data?.commune?.slug
              ? { name: data.commune.name, slug: data.commune.slug }
              : null,
    );
    const heading = $derived(
        place
            ? t('around_commune', { name: place.name })
            : data?.commune
              ? t('around_position_in', { name: data.commune.name })
              : t('around_position'),
    );

    /** Texte d'un fait d'histoire. */
    const write = (fact) => {
        const text = t(`history.${fact.key}`, { year: String(fact.year) });
        if (!fact.section) return text;
        const part = fact.section.from
            ? t('section_named', { from: fact.section.from, to: fact.section.to })
            : t('section_unnamed');

        return `${part} ${text}`;
    };
    const lineHistory = (line) =>
        historySentence(historyFacts(line.history), write, t('history_unknown'));
    const stationHistory = (station) =>
        historySentence(stationFacts(station), write, t('history_unknown'));
    const stationState = (status) => {
        const text = String($i18n.t(`stations.statuses.${status}`));
        return text.charAt(0).toLowerCase() + text.slice(1);
    };

    const status = $derived(
        phase === 'locating'
            ? t('locating')
            : phase === 'loading'
              ? t('loading')
              : phase === 'done'
                ? t('counts', {
                      stations: count(
                          stations.length === 1 ? 'stations_count_one' : 'stations_count_other',
                          stations.length,
                      ),
                      lines: count(
                          lines.length === 1 ? 'lines_count_one' : 'lines_count_other',
                          lines.length,
                      ),
                      km,
                  })
                : '',
    );
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/nearby" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={t('hero_title')} subtitle={t('hero_subtitle')} />

        <SectionBlock maxWidth="1180px">
            <div class="choose">
                <div class="locate">
                    <button
                        type="button"
                        class="vff-btn primary"
                        onclick={locate}
                        disabled={phase === 'locating'}
                    >
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        {phase === 'locating' ? t('locating') : t('locate')}
                    </button>
                    <p class="privacy">{t('privacy')}</p>
                </div>

                <p class="or" aria-hidden="true">{t('or')}</p>

                <form class="commune" method="get" action="/nearby" onsubmit={searchCommune}>
                    <div class="vff-field">
                        <label class="vff-label" for="nearby-q">{t('commune_label')}</label>
                        <div class="row">
                            <input
                                id="nearby-q"
                                name="q"
                                type="search"
                                class="vff-input"
                                autocomplete="off"
                                enterkeyhint="search"
                                aria-describedby="nearby-q-hint"
                                bind:value={typed}
                            />
                            <button type="submit" class="vff-btn secondary"
                                >{t('commune_search')}</button
                            >
                        </div>
                        <p class="vff-hint" id="nearby-q-hint">{t('commune_hint')}</p>
                    </div>
                    {#if query.trim().length > 0}
                        {#if matches.length > 0}
                            <nav aria-label={t('commune_results')}>
                                <ul class="matches">
                                    {#each matches as m (m.slug)}
                                        <li>
                                            <Link
                                                href={`/nearby?commune=${m.slug}`}
                                                class="vff-link"
                                                >{m.name}{m.department_name
                                                    ? ` (${m.department_name})`
                                                    : ''}</Link
                                            >
                                        </li>
                                    {/each}
                                </ul>
                            </nav>
                        {:else}
                            <p class="note">
                                {query.trim().length < 2 ? t('commune_short') : t('commune_none')}
                            </p>
                        {/if}
                    {/if}
                </form>
            </div>

            <p class="vff-sr-only" role="status" aria-live="polite">{status}</p>

            {#if phase === 'error'}
                <div class="vff-notice danger" role="alert">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <div class="vff-notice-body">
                        <p>{t(errorKey)}</p>
                        {#if errorKey === 'error_fetch'}
                            <p>
                                <button
                                    type="button"
                                    class="vff-btn secondary small"
                                    onclick={() => retry++}>{t('retry')}</button
                                >
                            </p>
                        {/if}
                    </div>
                </div>
            {/if}
        </SectionBlock>

        {#if target}
            <SectionBlock kind="alt" title={heading} maxWidth="1180px" id="results">
                <fieldset class="radius">
                    <legend>{t('radius_legend')}</legend>
                    <div class="radii">
                        {#each RADII as r (r)}
                            <label class="vff-check">
                                <input type="radio" name="radius" value={r} bind:group={radius} />
                                {t('radius_option', { km: n(r / 1000) })}
                            </label>
                        {/each}
                    </div>
                </fieldset>

                {#if phase === 'loading' && !data}
                    <p class="note" aria-hidden="true">{t('loading')}</p>
                {/if}

                {#if data}
                    <div class:stale={phase === 'loading'}>
                        {#if stations.length === 0 && lines.length === 0}
                            <p class="empty">{t('empty')}</p>
                        {/if}

                        {#if stations.length > 0}
                            <h3 class="group">
                                {t('stations_title')}
                                <span class="n">{n(stations.length)}</span>
                            </h3>
                            <ol class="items">
                                {#each stations as s (s.slug)}
                                    <li class="item">
                                        <p class="name">
                                            <Link href={`/stations/${s.slug}`} class="vff-link"
                                                >{s.name}</Link
                                            >
                                        </p>
                                        <p class="meta">
                                            <span class="dist"
                                                >{t('distance', {
                                                    distance: formatDistance(s.distance_m, lang),
                                                })}</span
                                            >
                                            <span>
                                                {t('state_label')}
                                                {stationState(s.status)}</span
                                            >
                                        </p>
                                        <p class="story">{stationHistory(s)}</p>
                                    </li>
                                {/each}
                            </ol>
                        {/if}

                        {#if lines.length > 0}
                            <h3 class="group">
                                {t('lines_title')}
                                <span class="n">{n(lines.length)}</span>
                            </h3>
                            <ol class="items">
                                {#each lines as l (l.slug)}
                                    <li class="item">
                                        <p class="name">
                                            <Link href={`/lines/${l.slug}`} class="vff-link"
                                                >{l.name}</Link
                                            >
                                        </p>
                                        <p class="meta">
                                            <span class="dist">
                                                {l.distance_m === null
                                                    ? t('distance_withheld')
                                                    : t('distance', {
                                                          distance: formatDistance(
                                                              l.distance_m,
                                                              lang,
                                                          ),
                                                      })}</span
                                            >
                                            <span>
                                                {t('state_label')}
                                                {t(`line_states.${lineStateKey(l)}`)}</span
                                            >
                                        </p>
                                        <p class="story">{lineHistory(l)}</p>
                                    </li>
                                {/each}
                            </ol>
                        {/if}

                        <p class="note source">{t('sources_note')}</p>
                    </div>

                    <h3 class="group">{t('links_title')}</h3>
                    <ul class="links">
                        {#if commune}
                            <li>
                                <Link href={`/communes/${commune.slug}`} class="vff-link"
                                    >{t('commune_sheet', { name: commune.name })}</Link
                                >
                            </li>
                        {/if}
                        <li>
                            <Link
                                href={mapHref(target.lat, target.lng, radius)}
                                class="vff-link"
                                aria-label={t('on_map_label')}>{t('on_map')}</Link
                            >
                        </li>
                    </ul>
                {/if}
            </SectionBlock>
        {/if}
    </main>
</Layout>

<style>
    .choose {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        gap: var(--vff-space-5);
        align-items: start;
        margin-bottom: var(--vff-space-5);
    }
    .locate {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--vff-space-3);
    }
    .privacy,
    .note,
    .empty {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .empty {
        margin-bottom: var(--vff-space-4);
        color: var(--vff-text);
    }
    .or {
        align-self: center;
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .commune {
        min-width: 0;
    }
    .row {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-3);
    }
    .row .vff-input {
        flex: 1 1 12em;
        min-width: 0;
    }
    .matches {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        margin: var(--vff-space-3) 0 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
    }
    .matches :global(a) {
        display: inline-block;
        padding-block: 6px;
    }
    .commune .note {
        margin-top: var(--vff-space-3);
    }
    .radii {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-5);
    }
    .radius {
        min-width: 0;
        margin: 0 0 var(--vff-space-5);
        padding: 0;
        border: 0;
    }
    .radius legend {
        margin: 0 0 var(--vff-space-2);
        padding: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-text);
    }
    .radius .vff-check {
        font-size: var(--vff-fs-md);
    }
    .stale {
        opacity: 0.55;
    }
    .group {
        margin: var(--vff-space-6) 0 var(--vff-space-3);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        color: var(--vff-text);
    }
    .group .n {
        margin-inline-start: 0.4em;
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .items {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 22em), 1fr));
        gap: var(--vff-space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .item {
        min-width: 0;
        padding: var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
    }
    .item p {
        margin: 0 0 6px;
        font-family: var(--vff-font-body);
        line-height: 1.5;
    }
    .item p:last-child {
        margin-bottom: 0;
    }
    .name {
        font-size: var(--vff-fs-body);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 2px var(--vff-space-4);
        font-size: var(--vff-fs-md);
        color: var(--vff-text-muted);
    }
    .dist {
        font-weight: 600;
        color: var(--vff-text);
    }
    .story {
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }
    .source {
        margin-top: var(--vff-space-5);
    }
    .links {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        margin: 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
    }
    .links :global(a) {
        display: inline-block;
        padding-block: 6px;
    }
    @media (max-width: 800px) {
        .choose {
            grid-template-columns: minmax(0, 1fr);
            gap: var(--vff-space-4);
        }
        .or {
            align-self: start;
        }
        .locate .vff-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
