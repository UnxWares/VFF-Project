<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatLocale, withUnit } from '$lib/typo.js';
    import { cleanUrl, linkLabel } from '$lib/linkLabel.js';
    import { formatEventDate } from '$lib/linePhases.js';
    import GlossaryText from '$components/GlossaryText.svelte';
    import {
        agreement,
        gapText,
        kmText,
        linkFirst,
        protectionTokens,
        rinfText,
        ROW_TERMS,
        ROWS,
        showRow,
        summarize,
    } from '$lib/lineRinf.js';

    /**
     * Contenu de la section « Registre de l'infrastructure (RINF) » de la fiche ligne (LineRinf) : ce que le
     * gestionnaire déclare au registre de l'ERA pour les voies de la ligne (électrification, vitesse, contrôle de
     * vitesse et ETCS, GSM-R, gabarit, catégorie de charge, écartement s'il n'est pas standard), lisible d'un coup
     * d'œil avec les PK quand la valeur change, puis l'accord avec SNCF Réseau et ses écarts par PK, les deux valeurs
     * et leur source, et les sources avec leur licence (lib/lineRinf.js). Liste de définitions, sans tableau : lisible
     * de 320 à 2 560 px.
     *
     * @type {{ data: any }} réponse de GET /api/v1/lines/{line}/rinf-parameters
     */
    let { data } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (k, o) => String($i18n.t(k, o));

    /** Paramètres montrés : résumé du registre et accord avec SNCF Réseau. */
    const rows = $derived.by(() => {
        const out = [];
        for (const key of ROWS) {
            const parameter = data.parameters.find((p) => p.key === key);
            if (!parameter) continue;
            const summary = summarize(parameter);
            if (!showRow(key, summary)) continue;
            out.push({ key, summary, agreement: agreement(parameter) });
        }
        return out;
    });

    /**
     * Systèmes de contrôle de vitesse du résumé, chaque liste avec son terme du glossaire ; seule la première mention
     * d'un terme dans la section est un lien (les suivantes restent du texte).
     */
    const protection = $derived.by(() => {
        const row = rows.find((r) => r.key === 'train_protection');
        if (!row) return [];
        const s = row.summary;
        const lists =
            s.kind === 'single'
                ? [s.value.values]
                : s.kind === 'shares'
                  ? s.shares.map((x) => x.value.values)
                  : s.runs.map((r) => r.value.values);
        return linkFirst(lists.map((values) => protectionTokens(values, t, lang)));
    });

    const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
    const pkRange = (from, to) => t('rinf.pk_range', { from, to });
    const km = (m) => kmText(m, lang);
    const kmh = (v) => withUnit(v, 'km/h', lang);
    const value = (key, v) => rinfText(key, v, t, lang);
    const dateOf = (iso) => formatEventDate(iso, 'day', formatLocale(lang));

    /** Phrase d'accord avec SNCF Réseau. */
    function agreementText(a) {
        return t(`rinf.agreement.${a.status}`, { pct: a.pct });
    }
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

{#snippet systems(tokens)}{#each tokens as token (token.system)}{token.before}{#if token.link}<Link
                class="term"
                href={`/glossary#${token.term}`}>{token.label}</Link
            >{:else}{token.label}{/if}{:else}{cap(t('rinf.value.none_declared'))}{/each}{/snippet}

{#snippet valueOf(key, v, index)}{#if key === 'train_protection'}{@render systems(
            protection[index] ?? [],
        )}{:else}{cap(value(key, v))}{/if}{/snippet}

{#snippet gapList(key, gaps)}
    <ul class="gaps" aria-label={t('rinf.gaps_label', { param: t(`rinf.param.${key}`) })}>
        {#each gaps as g (`${g.rg_troncon}-${g.pk_start_m}-${g.pk_end_m}`)}
            <li>
                <p class="where">
                    {pkRange(g.pk_start_label, g.pk_end_label)}<span class="len"
                        >{km(g.length_m)}&nbsp;km</span
                    >
                </p>
                <p class="both">
                    <span
                        >{t('rinf.gap_rinf', {
                            value: gapText(key, 'rinf', g.rinf, t, lang),
                        })}</span
                    >
                    <span>{t('rinf.gap_sncf', { value: gapText(key, 'vff', g.vff, t, lang) })}</span
                    >
                </p>
            </li>
        {/each}
    </ul>
{/snippet}

<p class="intro"><GlossaryText text={t('rinf.intro')} /></p>
<dl class="facts">
    {#each rows as row (row.key)}
        {@const s = row.summary}
        {@const a = row.agreement}
        <div class="fact">
            <dt>
                <Link class="term" href={`/glossary#${ROW_TERMS[row.key]}`}
                    >{t(`rinf.param.${row.key}`)}</Link
                >
            </dt>
            <dd>
                {#if s.kind === 'single'}
                    <p class="value">{@render valueOf(row.key, s.value, 0)}</p>
                {:else if s.kind === 'range'}
                    <p class="value">
                        {cap(
                            t('rinf.value.speed_range', {
                                min: kmh(s.min),
                                max: kmh(s.max),
                            }),
                        )}
                    </p>
                {:else if s.kind === 'runs'}
                    <ul class="runs">
                        {#each s.runs as r, i (`${r.part}-${r.from_m}-${r.to_m}`)}
                            <li>
                                <span class="where">{pkRange(r.from, r.to)}</span>
                                <span class="what">{@render valueOf(row.key, r.value, i)}</span>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <ul class="runs">
                        {#each s.shares as share, i (i)}
                            <li>
                                <span class="where">{km(share.length_m)}&nbsp;km</span>
                                <span class="what">{@render valueOf(row.key, share.value, i)}</span>
                            </li>
                        {/each}
                    </ul>
                    {#if s.more}<p class="note">
                            {t('rinf.value.more', { count: s.more })}
                        </p>{/if}
                {/if}

                {#if a}
                    <p class={['agree', { differs: a.gaps.length > 0 }]}>
                        {agreementText(a)}
                    </p>
                    {#if a.kvb_only_m > 0}
                        <p class="note">
                            <GlossaryText
                                text={t('rinf.agreement.kvb_only', { km: km(a.kvb_only_m) })}
                            />
                        </p>
                    {/if}
                    {#if a.gaps.length}
                        {#if row.key === 'max_speed'}
                            <p class="note">{t('rinf.agreement.speed_rule')}</p>
                        {/if}
                        {#if a.gaps.length <= 3}
                            {@render gapList(row.key, a.gaps)}
                        {:else}
                            <details class="fold">
                                <summary>{t('rinf.gaps', { count: a.gaps.length })}</summary>
                                {@render gapList(row.key, a.gaps)}
                            </details>
                        {/if}
                    {/if}
                {/if}
            </dd>
        </div>
    {/each}
</dl>

<div class="sources">
    <h3>{t('rinf.sources')}</h3>
    <ul>
        {#each data.sources as src (src.key)}
            <li>
                <strong>{src.label}</strong>{#if src.fetched_at},
                    {t('rinf.fetched', {
                        date: dateOf(src.fetched_at),
                    })}{/if}{#if src.licence && src.licence_url},
                    <a href={cleanUrl(src.licence_url)} target="_blank" rel="noopener noreferrer"
                        >{t('rinf.licence', { licence: src.licence })}{@render ext()}</a
                    >{:else if src.licence},
                    {t('rinf.licence', {
                        licence: src.licence,
                    })}{/if}{#if src.key === 'rinf' && src.attribution}{` (${src.attribution})`}{/if}{#if src.url},
                    <a href={cleanUrl(src.url)} target="_blank" rel="noopener noreferrer"
                        >{linkLabel(src.url, 48, lang)}{@render ext()}</a
                    >{/if}
            </li>
        {/each}
    </ul>
</div>

<style>
    .intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-5);
        font-size: var(--vff-fs-md);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .facts {
        margin: 0;
        border-top: 1px solid var(--vff-border);
        container-type: inline-size;
    }
    .fact {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 6px var(--vff-space-5);
        padding: var(--vff-space-4) 0;
        border-bottom: 1px solid var(--vff-border);
    }
    @container (min-width: 44em) {
        .fact {
            grid-template-columns: minmax(0, 15em) minmax(0, 1fr);
        }
    }
    dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        color: var(--vff-text);
    }
    dd {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        min-width: 0;
        margin: 0;
        font-size: var(--vff-fs-md);
        line-height: var(--vff-lh-body);
    }
    dd p {
        max-width: var(--vff-measure);
        margin: 0;
    }
    /* Liens vers le glossaire : discrets, comme les sigles des autres sections (GlossaryText). */
    .facts :global(a.term) {
        color: inherit;
        font: inherit;
        text-decoration: underline dotted;
        text-underline-offset: 0.2em;
    }
    .facts :global(a.term:hover) {
        color: var(--vff-link);
        text-decoration-style: solid;
    }
    .facts :global(a.term:focus-visible) {
        outline: 2px solid var(--vff-accent-strong);
        outline-offset: 2px;
        border-radius: var(--vff-radius-xs);
    }
    .runs {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .runs li {
        display: flex;
        flex-wrap: wrap;
        column-gap: var(--vff-space-4);
    }
    .runs .where {
        flex: 0 0 auto;
        min-width: 13.5em;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .runs .what {
        flex: 1 1 14em;
        min-width: 0;
    }
    .agree {
        color: var(--vff-text-muted);
    }
    .agree.differs {
        color: var(--vff-text);
    }
    .note {
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
    }
    .gaps {
        display: flex;
        flex-direction: column;
        max-width: 48em;
        margin: 0;
        padding: 0;
        list-style: none;
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-subtle);
    }
    .gaps li {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: var(--vff-space-3) var(--vff-space-4);
    }
    .gaps li + li {
        border-top: 1px solid var(--vff-border);
    }
    .gaps .where {
        display: flex;
        flex-wrap: wrap;
        column-gap: var(--vff-space-3);
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }
    .gaps .len {
        color: var(--vff-text-muted);
        font-weight: 400;
    }
    /* Les deux valeurs l'une sous l'autre ; côte à côte, en deux colonnes égales, quand la place le permet. */
    .gaps .both {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        column-gap: var(--vff-space-5);
    }
    @container (min-width: 44em) {
        .gaps .both {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .fold summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 8px 16px;
        border: 1px solid var(--vff-border-control);
        border-radius: var(--vff-radius-sm);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        cursor: pointer;
    }
    .fold summary:focus-visible {
        outline: var(--vff-focus-width) solid var(--vff-focus);
        outline-offset: var(--vff-focus-offset);
    }
    .fold[open] summary {
        margin-bottom: var(--vff-space-3);
    }
    .sources {
        margin-top: var(--vff-space-5);
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-body);
    }
    .sources h3 {
        margin: 0 0 var(--vff-space-2);
        color: var(--vff-text);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
    }
    .sources ul {
        max-width: var(--vff-measure);
        margin: 0;
        padding-left: 1.2em;
        /* Licence et adresses longues : coupées plutôt que d'élargir la page à 320 px. */
        overflow-wrap: anywhere;
    }
    .sources strong {
        color: var(--vff-text);
    }
</style>
