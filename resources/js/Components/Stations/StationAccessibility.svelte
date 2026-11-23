<script>
    import { onMount } from 'svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import {
        AMENITIES,
        dayName,
        datasetDate,
        elevatorSummary,
        highlights,
        hoursGroups,
        readingTime,
        sentence,
        sortedItems,
    } from '$lib/stationAccessibility.js';
    import { i18n } from '$lib/i18n.js';

    /**
     * Accessibilité et services de la gare d'aujourd'hui (SNCF Gares & Connexions, ODbL) : une ligne compacte de
     * faits (icône et texte), puis un détail repliable (accès, ascenseurs et leur état daté, assistance et horaires,
     * équipements, vente, vélos) avec les jeux lus. Les données sont lues par l'API quand la fiche s'affiche, pour ne
     * pas alourdir le chargement de la page ; rien n'est affiché quand les jeux n'ont rien pour la gare.
     */
    /** @type {{ slug: string, name: string, data?: object|null }} */
    let { slug, name, data: initial = null } = $props();

    // svelte-ignore state_referenced_locally
    let data = $state(initial);

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));

    onMount(async () => {
        if (data) return;
        try {
            const res = await fetch(`/api/v1/stations/${encodeURIComponent(slug)}/accessibility`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) data = (await res.json()).accessibility ?? null;
        } catch {
            // Section facultative : sans réponse, la fiche reste complète.
        }
    });

    const facts = $derived(highlights(data));
    const lifts = $derived(elevatorSummary(data?.elevators));
    const access = $derived(data?.access ?? {});
    const assistance = $derived(data?.assistance ?? null);
    const hours = $derived(assistance ? hoursGroups(assistance.hours) : []);
    const amenities = $derived(AMENITIES.filter((a) => (data?.amenities ?? []).includes(a)));
    const sales = $derived(data?.sales ?? null);
    const salesItems = $derived(
        sales
            ? [
                  ...(sales.counter ? ['counter'] : []),
                  ...(sales.machines ?? []),
                  ...(sales.assisted ? ['assisted'] : []),
              ]
            : [],
    );
    const bikes = $derived(data?.bike_parking ?? null);
    const items = $derived(sortedItems(data?.elevators?.status?.items));
    const stateAt = $derived(readingTime(data?.elevators?.status?.state_at, lang));
</script>

{#if data && facts.length > 0}
    <SheetSection id="accessibility" title={t('stations.accessibility.title')}>
        <div class="accessibility">
            <ul class="facts" aria-label={t('stations.accessibility.list_label')}>
                {#each facts as f (f.id)}
                    <li class={{ alert: f.alert }}>
                        <i class="bi {f.icon}" aria-hidden="true"></i>
                        <span>{t(`stations.accessibility.line.${f.key}`, f.params)}</span>
                    </li>
                {/each}
            </ul>

            <details class="more">
                <summary>{t('stations.accessibility.detail')}</summary>

                {#if access.mobility}
                    <h3>{t('stations.accessibility.access.title')}</h3>
                    <ul class="plain">
                        <li>{t(`stations.accessibility.access.${access.mobility}`)}</li>
                        {#if access.step_free !== null && access.step_free !== undefined}
                            <li>
                                {t(
                                    access.step_free
                                        ? 'stations.accessibility.access.step_free_yes'
                                        : 'stations.accessibility.access.step_free_no',
                                )}
                            </li>
                        {/if}
                        {#if access.quays}
                            <li>
                                {t('stations.accessibility.access.quays', {
                                    count: access.quays.total,
                                    step: access.quays.step_free,
                                    total: access.quays.total,
                                })}
                            </li>
                        {/if}
                        {#if access.tactile_guidance !== null && access.tactile_guidance !== undefined}
                            <li>
                                {t(
                                    access.tactile_guidance
                                        ? 'stations.accessibility.access.tactile_yes'
                                        : 'stations.accessibility.access.tactile_no',
                                )}
                            </li>
                        {/if}
                        {#if access.comment}<li>{sentence(access.comment)}</li>{/if}
                    </ul>
                {/if}

                {#if lifts || data.elevators?.escalators}
                    <h3>{t('stations.accessibility.elevators.title')}</h3>
                    <ul class="plain">
                        {#if lifts}
                            <li>
                                {t('stations.accessibility.elevators.count', {
                                    count: lifts.count,
                                })}
                            </li>
                        {/if}
                        {#if data.elevators.escalators}
                            <li>
                                {t('stations.accessibility.elevators.escalators', {
                                    count: data.elevators.escalators,
                                })}
                            </li>
                        {/if}
                    </ul>
                    {#if lifts?.known}
                        <p class="note">
                            {t('stations.accessibility.elevators.status', {
                                date: stateAt,
                                ok: lifts.ok,
                                ko: lifts.ko,
                                unknown: lifts.unknown,
                            })}
                        </p>
                        <ul class="plain states">
                            {#each items as item, i (i)}
                                <li class={{ alert: item.state === 'ko' }}>
                                    {item.label}{#if item.location}
                                        ({item.location}){/if}
                                    :
                                    <strong
                                        >{t(
                                            `stations.accessibility.elevators.state.${item.state}`,
                                        )}</strong
                                    >
                                </li>
                            {/each}
                        </ul>
                    {/if}
                {/if}

                {#if assistance}
                    <h3>{t('stations.accessibility.assistance.title')}</h3>
                    <ul class="plain">
                        {#if assistance.mode}<li>
                                {t(`stations.accessibility.assistance.mode.${assistance.mode}`)}
                            </li>{/if}
                        {#if assistance.free}<li>
                                {t('stations.accessibility.assistance.free')}
                            </li>{/if}
                        {#if assistance.meeting_point}
                            <li>
                                {t('stations.accessibility.assistance.meeting_point', {
                                    place: assistance.meeting_point,
                                })}
                            </li>
                        {/if}
                        {#if assistance.first_to_last_train}
                            <li>{t('stations.accessibility.assistance.first_to_last_train')}</li>
                        {/if}
                        {#if assistance.since}
                            <li>
                                {t('stations.accessibility.assistance.since', {
                                    date: datasetDate(assistance.since, lang),
                                })}
                            </li>
                        {/if}
                    </ul>
                    {#if hours.some((g) => g.value)}
                        <table>
                            <caption class="vff-sr-only"
                                >{t('stations.accessibility.assistance.hours')} - {name}</caption
                            >
                            <tbody>
                                {#each hours as g (g.from)}
                                    <tr>
                                        <th scope="row">
                                            {g.from === g.to
                                                ? dayName(g.from, lang)
                                                : t('stations.accessibility.assistance.range', {
                                                      from: dayName(g.from, lang),
                                                      to: dayName(g.to, lang),
                                                  })}
                                        </th>
                                        <td
                                            >{g.value ??
                                                t('stations.accessibility.assistance.closed')}</td
                                        >
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    {/if}
                {/if}

                {#if amenities.length > 0}
                    <h3>{t('stations.accessibility.amenities.title')}</h3>
                    <ul class="plain">
                        {#each amenities as a (a)}<li>
                                {t(`stations.accessibility.amenities.${a}`)}
                            </li>{/each}
                    </ul>
                {/if}

                {#if salesItems.length > 0}
                    <h3>{t('stations.accessibility.sales.title')}</h3>
                    <ul class="plain">
                        {#each salesItems as s (s)}<li>
                                {t(`stations.accessibility.sales.${s}`)}
                            </li>{/each}
                    </ul>
                {/if}

                {#if bikes}
                    <h3>{t('stations.accessibility.bikes.title')}</h3>
                    <ul class="plain">
                        <li>
                            {t('stations.accessibility.bikes.places', {
                                count: bikes.places,
                                date: datasetDate(bikes.as_of, lang),
                            })}
                        </li>
                        {#if bikes.lom_target}
                            <li>
                                {t('stations.accessibility.bikes.target', {
                                    count: bikes.lom_target,
                                })}
                            </li>
                        {/if}
                    </ul>
                {/if}

                <p class="note">{t('stations.accessibility.note')}</p>
                <p class="source">
                    {t('stations.accessibility.sources')}
                    {#each data.datasets ?? [] as d, i (d.dataset)}
                        <a href={d.page} target="_blank" rel="noopener noreferrer"
                            >{d.modified
                                ? t('stations.accessibility.dataset', {
                                      title: d.title,
                                      date: datasetDate(d.modified, lang),
                                  })
                                : t('stations.accessibility.dataset_undated', {
                                      title: d.title,
                                  })}</a
                        >{i < (data.datasets?.length ?? 0) - 1 ? ', ' : '.'}
                    {/each}
                </p>
            </details>
        </div>
    </SheetSection>
{/if}

<style>
    .accessibility {
        max-width: 720px;
    }
    .facts {
        display: flex;
        flex-wrap: wrap;
        gap: var(--vff-space-2) var(--vff-space-3);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .facts li {
        display: inline-flex;
        align-items: center;
        gap: var(--vff-space-2);
        padding: var(--vff-space-2) var(--vff-space-4);
        border: 1px solid var(--vff-border);
        border-radius: 999px;
        font-size: var(--vff-fs-body);
        color: var(--vff-text);
    }
    .facts i {
        color: var(--vff-text-muted);
    }
    .facts li.alert,
    .states li.alert {
        color: var(--vff-text);
        border-color: var(--vff-accent-ink);
    }
    .facts li.alert i {
        color: var(--vff-accent-ink);
    }
    .more {
        margin-top: var(--vff-space-4);
    }
    summary {
        cursor: pointer;
        color: var(--vff-accent-ink);
        font-size: var(--vff-fs-body);
    }
    h3 {
        margin: var(--vff-space-5) 0 var(--vff-space-2);
        font-size: var(--vff-fs-body);
        font-weight: 600;
        color: var(--vff-text);
    }
    .plain {
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: var(--vff-fs-body);
        color: var(--vff-text);
    }
    .plain li {
        margin: 0 0 var(--vff-space-1);
    }
    .states {
        margin-top: var(--vff-space-2);
    }
    table {
        border-collapse: collapse;
        margin-top: var(--vff-space-2);
        font-size: var(--vff-fs-body);
    }
    th,
    td {
        padding: var(--vff-space-1) var(--vff-space-4) var(--vff-space-1) 0;
        text-align: left;
        font-weight: 400;
        vertical-align: top;
    }
    th {
        color: var(--vff-text-muted);
    }
    .note,
    .source {
        margin: var(--vff-space-3) 0 0;
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .source a {
        color: var(--vff-accent-ink);
    }
</style>
