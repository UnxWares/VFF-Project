<script>
    import { Link } from '@inertiajs/svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import Layout from '$components/Layout.svelte';
    import SheetHero from '$components/Sheet/SheetHero.svelte';
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import SheetGallery from '$components/Sheet/SheetGallery.svelte';
    import MaterialLinesMap from '$components/Materials/MaterialLinesMap.svelte';
    import NotableUnits from '$components/Sheet/NotableUnits.svelte';
    import PreservedUnits from '$components/Materials/PreservedUnits.svelte';
    import ReportError from '$components/Sheet/ReportError.svelte';
    import StatStrip from '$components/Sheet/StatStrip.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import {
        confidenceKey,
        provenanceOf,
        servicePeriod,
        servicePeriodText,
        serviceState,
        splitArrows,
    } from '$lib/materialFacts.js';
    import { periodText, relationNotes, relationsList } from '$lib/materialPeriods.js';
    import { formatLocale } from '$lib/typo.js';

    // Images : `media_count` seul, la galerie les charge par l'API.
    let {
        material,
        lines = [],
        lines_total = 0,
        map_url = null,
        media_count = 0,
        notable_units = [],
        preserved = [],
    } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));
    // Séparateur des sources d'une note : Svelte retire l'espace qui borde un bloc {#if}.
    const LIST_SEPARATOR = ', ';
    const num = (n) => Number(n).toLocaleString(lang);

    // Lignes visibles d'emblée ; la suite se déplie (la série la plus citée en compte 43).
    const LINES_SHOWN = 8;
    // Badge de fiabilité : le texte dit le niveau, la couleur ne fait que le souligner.
    const CONFIDENCE_TONE = { high: 'success', medium: 'muted', low: 'warning' };
    const CC_BY_SA = 'https://creativecommons.org/licenses/by-sa/4.0/';

    const period = $derived(servicePeriod(material.in_service_from, material.in_service_to));
    // État d'utilisation : seulement si une source le dit (Wikidata, effectif d'une infobox Wikipédia).
    const state = $derived(serviceState(material.service, t, lang));
    const image = $derived(material.image ?? null);
    const prov = $derived(provenanceOf(material.field_sources));
    const wikipedia = $derived(
        [
            material.wikipedia?.fr && { href: material.wikipedia.fr.url, key: 'wikipedia_fr' },
            material.wikipedia?.en && { href: material.wikipedia.en.url, key: 'wikipedia_en' },
            material.wikidata_url && { href: material.wikidata_url, key: 'wikidata' },
        ].filter(Boolean),
    );

    // Chiffres clés : seulement ce que la source donne (une année absente n'est jamais « aujourd'hui »).
    const statItems = $derived(
        [
            period && {
                value:
                    period.kind === 'range'
                        ? servicePeriodText(period.from, period.to, t)
                        : String(period.from ?? period.to),
                label: t(`materials.stat.${period.kind === 'range' ? 'period' : period.kind}`),
            },
            state && { value: state.label, label: t('materials.stat.status') },
            material.units_built != null && {
                value: num(material.units_built),
                label: t('materials.stat.units'),
            },
            material.max_speed_kmh != null && {
                value: t('materials.unit.kmh', { value: num(material.max_speed_kmh) }),
                label: t('materials.stat.speed'),
            },
            lines_total > 0 && { value: num(lines_total), label: t('materials.stat.lines') },
        ].filter(Boolean),
    );

    /**
     * Rangées de la fiche technique, dans l'ordre de lecture ; `field` relie la rangée à sa
     * provenance (`field_sources`). Une rangée sans valeur n'est pas affichée.
     * Les libellés Wikidata (alimentation, écartement) sont en français : `lang` le signale.
     */
    const facts = $derived(
        [
            {
                id: 'category',
                field: 'type',
                items: [{ text: t(`materials.type.${material.type}`) }],
            },
            {
                id: 'designations',
                field: 'designations',
                items: (material.designations ?? []).map((d) => ({ text: d })),
            },
            {
                id: 'builders',
                field: 'builders',
                items: (material.builders ?? []).map((b) => ({
                    text: b.name,
                    meta:
                        b.units != null
                            ? t('materials.facts.builder_units', { count: b.units })
                            : null,
                })),
            },
            {
                id: 'operators',
                field: 'operators',
                items: (material.operators ?? []).map((o) => ({
                    text: o.name,
                    meta: operatorPeriod(o),
                })),
            },
            {
                id: 'in_service_from',
                field: 'in_service_from',
                items: material.in_service_from ? [{ text: String(material.in_service_from) }] : [],
            },
            {
                id: 'in_service_to',
                field: 'in_service_to',
                items: material.in_service_to ? [{ text: String(material.in_service_to) }] : [],
            },
            {
                id: 'service',
                field: 'service_status',
                items: state ? [{ text: state.label, meta: state.detail }] : [],
            },
            {
                id: 'units_built',
                field: 'units_built',
                items: material.units_built != null ? [{ text: num(material.units_built) }] : [],
            },
            {
                id: 'fleet_numbers',
                field: 'fleet_numbers',
                items: material.fleet_numbers ? [{ text: material.fleet_numbers }] : [],
            },
            {
                id: 'max_speed',
                field: 'max_speed_kmh',
                items:
                    material.max_speed_kmh != null
                        ? [
                              {
                                  text: t('materials.unit.kmh', {
                                      value: num(material.max_speed_kmh),
                                  }),
                              },
                          ]
                        : [],
            },
            {
                id: 'power',
                field: 'power_kw',
                items:
                    material.power_kw != null
                        ? [{ text: t('materials.unit.kw', { value: num(material.power_kw) }) }]
                        : [],
            },
            {
                id: 'electrification',
                field: 'electrification',
                items: (material.electrification ?? []).map((e) => ({
                    // « électrification ferroviaire 25 kV 50 Hz » : le libellé de la rangée dit déjà le reste.
                    text: e.label.replace(/^électrification ferroviaire\s+/i, ''),
                    lang: 'fr',
                })),
            },
            {
                id: 'gauge',
                field: material.gauges?.length ? 'gauges' : 'gauge_mm',
                items: material.gauges?.length
                    ? material.gauges.map((g) => ({
                          text: g.mm ? t('materials.unit.mm', { value: num(g.mm) }) : g.label,
                          meta: g.mm ? g.label : null,
                          metaLang: 'fr',
                      }))
                    : material.gauge_mm
                      ? [{ text: t('materials.unit.mm', { value: num(material.gauge_mm) }) }]
                      : [],
            },
            {
                id: 'wheel_arrangement',
                field: 'wheel_arrangement',
                items: material.wheel_arrangement ? [{ text: material.wheel_arrangement }] : [],
            },
        ].filter((row) => row.items.length),
    );

    const shownLines = $derived(lines.slice(0, LINES_SHOWN));
    const moreLines = $derived(lines.slice(LINES_SHOWN));

    /** Période d'exploitation : « 1869-1873 », « à partir de 1869 », « jusqu'en 1873 », ou rien. */
    function operatorPeriod(o) {
        const p = servicePeriod(o.from_year, o.to_year);
        if (!p) return null;
        if (p.kind === 'range') return servicePeriodText(p.from, p.to, t);
        return p.kind === 'from'
            ? t('materials.facts.operator_from', { year: p.from })
            : t('materials.facts.operator_until', { year: p.to });
    }

    /** Texte de remplacement : le nom du fichier Commons, qui décrit d'ordinaire la photo. */
    function altOf(img) {
        return String(img.file ?? '')
            .replace(/^File:/, '')
            .replace(/\.[a-z0-9]+$/i, '')
            .replaceAll('_', ' ');
    }
    const licenseLabel = (img) =>
        img.license ?? t(`media.rights.${img.rights_status ?? 'unknown'}`);
    const sourceName = (src) => src.name ?? src.source;

    /** Référence d'une preuve Wikipédia : article, section, version citée (lien), licence du texte. */
    function citeParts(l) {
        return [
            l.article && { text: t('materials.section_lines.article', { article: l.article }) },
            l.section && { text: t('materials.section_lines.section', { section: l.section }) },
            {
                text: t('materials.section_lines.revision'),
                href: l.source_url,
                rel: 'noopener noreferrer',
            },
            {
                text: t('materials.section_lines.license'),
                href: CC_BY_SA,
                rel: 'license noopener noreferrer',
            },
        ].filter(Boolean);
    }

    /** Les descriptions Wikidata commencent par une minuscule (« série de 65 locomotives… »). */
    const upperFirst = (text) => text.charAt(0).toLocaleUpperCase('fr') + text.slice(1);
</script>

{#snippet arrowed(text)}{#each splitArrows(text) as part, i (i)}{#if i > 0}<span aria-hidden="true"
                >&nbsp;→&nbsp;</span
            ><span class="vff-sr-only">{` ${t('materials.renumbered')} `}</span
            >{/if}{part}{/each}{/snippet}

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

{#snippet lineItem(l)}
    {@const key = confidenceKey(l.confidence, l.kind)}
    {@const relations = relationsList(l.relations, l.periods, t)}
    {@const notes = relationNotes(l.relations, t, formatLocale(lang))}
    <li class="line-item">
        <div class="line-head">
            <Link class="line-name" href="/lines/{l.slug}"
                >{#if l.code}<span class="vff-plate">{l.code}</span>{/if}<span>{l.name}</span></Link
            >
            <span class={['vff-badge', CONFIDENCE_TONE[l.confidence] ?? 'muted']}
                >{t(`${key}.label`)}</span
            >
        </div>
        <p class="confidence">{t(`${key}.text`)}</p>
        {#if relations.length}
            <div class="periods">
                <p class="periods-label">{t('materials.relations.label')}</p>
                <ul>
                    {#each relations as r (r)}<li>{r}</li>{/each}
                </ul>
                {#each notes as n (n.label + n.text)}
                    <p class="relation-fact">
                        {t('materials.relations.notes.line', { label: n.label, text: n.text })}
                        ({t('materials.relations.notes.sources')}
                        {#each n.sources as s, j (s.url)}{#if j > 0}{LIST_SEPARATOR}{/if}<a
                                href={s.url}
                                target="_blank"
                                rel="noopener noreferrer">{s.publisher}{@render ext()}</a
                            >{/each})
                    </p>
                {/each}
            </div>
        {/if}
        {#if l.periods?.length}
            <div class="periods">
                <p class="periods-label">{t('materials.periods.label')}</p>
                <ul>
                    {#each l.periods as p, i (i)}
                        <li>
                            {periodText(p, t, formatLocale(lang))}{#if p.source_url}
                                (<a href={p.source_url} target="_blank" rel="noopener noreferrer"
                                    >{t('materials.periods.source')}{@render ext()}</a
                                >){/if}
                        </li>
                    {/each}
                </ul>
            </div>
        {/if}
        {#if l.quote}
            <blockquote class="quote" lang="fr" cite={l.source_url}>
                <p>{l.quote}</p>
            </blockquote>
        {/if}
        <p class="cite">
            {#if l.kind === 'wikidata'}
                <a href={l.source_url} target="_blank" rel="noopener noreferrer"
                    >{t('materials.section_lines.wikidata')}{@render ext()}</a
                >
            {:else if l.kind === 'contribution'}
                {#if l.source_url}<a
                        href={l.source_url}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        >{t('materials.section_lines.contribution', {
                            title: l.source_title ?? '',
                        })}{@render ext()}</a
                    >{:else}{t('materials.section_lines.contribution', {
                        title: l.source_title ?? '',
                    })}{/if}
            {:else}
                {#each citeParts(l) as part, i (i)}{i > 0 ? ', ' : ''}{#if part.href}<a
                            href={part.href}
                            target="_blank"
                            rel={part.rel}>{part.text}{@render ext()}</a
                        >{:else}<span lang="fr">{part.text}</span>{/if}{/each}
            {/if}
        </p>
    </li>
{/snippet}

<svelte:head>
    <title>{material.name} - VFF</title>
</svelte:head>

<!-- Description et aperçu de partage (photo créditée de la série) : écrits d'abord par le serveur. -->
<SeoHead
    title={material.name}
    description={t('materials.meta_show', { name: material.name })}
    path={`/materials/${material.slug}`}
    type="article"
    image={image ? { url: image.display_url ?? image.thumb_url, alt: material.name } : null}
/>

<Layout compactHeader>
    <main>
        <SheetHero
            crumbs={[{ href: '/materials', label: t('materials.all'), icon: 'bi-arrow-left' }]}
            crumbsLabel={t('materials.breadcrumbs')}
            plate={t(`materials.type.${material.type}`)}
            title={material.name}
            stats={heroStats}
        />

        {#snippet heroStats()}
            <StatStrip items={statItems} label={t('materials.key_figures')} />
        {/snippet}

        <SheetSection id="about" label={t('materials.about.title')}>
            <div class={['about-grid', { compact: !image && !material.description }]}>
                <div class="about">
                    {#if image}
                        <figure class="vff-figure photo" style:--ratio="3 / 2">
                            <img
                                src={commonsSrc(image.display_url ?? image.thumb_url)}
                                alt={altOf(image)}
                                width={image.width ?? 1280}
                                height={image.height ?? 853}
                                fetchpriority="high"
                            />
                            <figcaption>
                                {image.author ?? t('media.author_unknown')},
                                <a
                                    href={image.license_url ?? image.source_url}
                                    target="_blank"
                                    rel="license noopener noreferrer"
                                    >{licenseLabel(image)}{@render ext()}</a
                                >,
                                <a href={image.source_url} target="_blank" rel="noopener noreferrer"
                                    >{t('materials.image.via')}{@render ext()}</a
                                >
                            </figcaption>
                        </figure>
                    {/if}

                    {#if material.description}
                        <p class="description" lang="fr">{upperFirst(material.description)}</p>
                    {/if}

                    {#if material.scope === 'attested'}
                        <p class="scope-note">{t('materials.scope.attested')}</p>
                    {/if}

                    {#if wikipedia.length || material.commons_category_url}
                        <h2 class="sub">{t('materials.about.links')}</h2>
                        <ul class="links">
                            {#each wikipedia as w (w.key)}
                                <li>
                                    <a href={w.href} target="_blank" rel="noopener noreferrer"
                                        >{t(`materials.about.${w.key}`)}{@render ext()}</a
                                    >
                                </li>
                            {/each}
                            {#if material.commons_category_url}
                                <li>
                                    <a
                                        href={material.commons_category_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{t('materials.image.more')}{@render ext()}</a
                                    >
                                </li>
                            {/if}
                        </ul>
                    {/if}

                    <p style="margin: var(--vff-space-4) 0 0">
                        <Link href="/materials/timeline?serie={material.slug}" class="vff-link"
                            >{t('materials_timeline.see_in_timeline')}</Link
                        >
                    </p>
                </div>

                <section class="infobox" aria-labelledby="fiche-title">
                    <h2 id="fiche-title">{t('materials.facts.title')}</h2>
                    <dl>
                        {#each facts as row (row.id)}
                            {@const own = prov.byField[row.field]}
                            <div class="row">
                                <dt>{t(`materials.facts.${row.id}`)}</dt>
                                <dd>
                                    <ul>
                                        {#each row.items as item, i (i)}
                                            <li>
                                                <span lang={item.lang}
                                                    >{@render arrowed(item.text)}</span
                                                >
                                                {#if item.meta}<span
                                                        class="meta"
                                                        lang={item.metaLang}>{item.meta}</span
                                                    >{/if}
                                            </li>
                                        {/each}
                                    </ul>
                                    {#if own}
                                        <span class="meta src"
                                            >{#if own.url}<a
                                                    href={own.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    >{t('materials.facts.from_source', {
                                                        source: sourceName(own),
                                                    })}{@render ext()}</a
                                                >{:else}{t('materials.facts.from_source', {
                                                    source: sourceName(own),
                                                })}{/if}</span
                                        >
                                    {/if}
                                </dd>
                            </div>
                        {/each}
                    </dl>
                    {#if prov.main}
                        <p class="provenance">
                            {t('materials.provenance.data')}{lang === 'fr' ? ' : ' : ': '}
                            {#if prov.main.url}<a
                                    href={prov.main.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{sourceName(prov.main)}{@render ext()}</a
                                >{:else}{sourceName(prov.main)}{/if}{prov.main.license
                                ? ` (${prov.main.license})`
                                : ''}
                        </p>
                    {/if}
                </section>
            </div>
        </SheetSection>

        {#if notable_units.length}
            <NotableUnits units={notable_units} series={material.slug} />
        {/if}

        {#if preserved.length}
            <PreservedUnits units={preserved} />
        {/if}

        {#if map_url && lines.length}
            <SheetSection
                id="lines-map"
                title={t('materials.map.section')}
                intro={t('materials.map.intro')}
            >
                <MaterialLinesMap url={map_url} name={material.name} />
            </SheetSection>
        {/if}

        <SheetSection
            id="lines"
            title={t('materials.lines')}
            count={lines_total || null}
            intro={lines.length ? t('materials.section_lines.intro') : null}
        >
            {#if lines.length}
                <ul class="line-list">
                    {#each shownLines as l (l.slug)}{@render lineItem(l)}{/each}
                </ul>
                {#if moreLines.length}
                    <details class="fold">
                        <summary
                            >{t('materials.section_lines.more', {
                                count: moreLines.length,
                            })}</summary
                        >
                        <ul class="line-list">
                            {#each moreLines as l (l.slug)}{@render lineItem(l)}{/each}
                        </ul>
                    </details>
                {/if}
                {#if lines_total > lines.length}
                    <p class="partial">
                        {t('materials.section_lines.partial', {
                            shown: num(lines.length),
                            total: num(lines_total),
                        })}
                    </p>
                {/if}
            {:else}
                <EmptyState icon="bi-bezier2" title={t('materials.no_lines')}>
                    <Link
                        class="vff-btn secondary small"
                        href="/contribute/material?material={material.slug}"
                        >{t('materials.contribute')}</Link
                    >
                </EmptyState>
            {/if}
        </SheetSection>

        {#if media_count > 0}
            <SheetGallery
                endpoint="/api/v1/materials/{encodeURIComponent(material.slug)}/media"
                intro={t('media.subtitle_material')}
                count={media_count}
            />
        {/if}

        <ReportError
            type="material"
            slug={material.slug}
            name={material.name}
            proposals={[
                {
                    href: `/contribute/material?material=${material.slug}`,
                    label: t('proposals.sheet.material_line'),
                },
                {
                    href: `/contribute/series?material=${material.slug}`,
                    label: t('proposals.sheet.series'),
                },
                {
                    href: `/contribute/media?material=${material.slug}`,
                    label: t('proposals.sheet.media'),
                },
            ]}
        />
    </main>
</Layout>

<style>
    main {
        background-color: var(--vff-bg);
        padding-bottom: var(--vff-section-space);
    }

    /* Présentation : photo et liens à gauche, fiche technique à droite (pleine largeur sur téléphone). */
    .about-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(300px, 420px);
        gap: var(--vff-space-6) 48px;
        align-items: start;
    }
    .about {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        min-width: 0;
    }
    /* Ni photo ni description : une seule colonne (liens puis fiche technique), pas de moitié vide. */
    .compact {
        grid-template-columns: minmax(0, 640px);
    }
    .photo img {
        max-height: 560px;
    }
    .description {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text);
    }
    .scope-note {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text-muted);
    }
    .sub {
        margin: var(--vff-space-3) 0 0;
        font-size: var(--vff-fs-h3);
    }
    .links {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 24px;
        margin: 0;
        padding: 0;
        list-style: none;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    /* Cible de 24 px au moins sans changer l'interligne. */
    .links a,
    .infobox a {
        display: inline-block;
        padding-block: 3px;
        margin-block: -1px;
    }
    /* Liens de la référence en ligne (pas en bloc) : la virgule reste collée au lien qui la précède. */
    .cite a {
        padding-block: 4px;
    }

    .infobox {
        padding: var(--vff-pad-box) var(--vff-pad-box) calc(var(--vff-pad-box) - 10px);
        border: 1px solid var(--vff-border);
        border-radius: var(--vff-radius-md);
        background: var(--vff-bg-elevated);
    }
    .infobox h2 {
        margin: 0 0 10px;
        font-size: var(--vff-fs-h3);
    }
    .infobox dl {
        margin: 0;
    }
    .infobox .row {
        display: grid;
        grid-template-columns: minmax(0, 9.5em) minmax(0, 1fr);
        gap: 4px 14px;
        padding: 10px 0;
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
    }
    .infobox dt {
        font-weight: 600;
        color: var(--vff-text-muted);
        overflow-wrap: break-word;
    }
    .infobox dd {
        min-width: 0;
        margin: 0;
        color: var(--vff-text);
        overflow-wrap: break-word;
    }
    .infobox ul {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .infobox li {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        column-gap: 8px;
    }
    .meta {
        color: var(--vff-text-muted);
    }
    .src {
        display: block;
        margin-top: 4px;
    }
    .provenance {
        margin: 0;
        padding: 10px 0;
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }

    /* Lignes parcourues : une rangée par ligne, filets entre les rangées. */
    .line-list {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border);
    }
    .line-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-width: 860px;
        padding: 20px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .line-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 16px;
    }
    .line-head :global(.line-name) {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 12px;
        min-height: 24px;
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-md);
        font-weight: 700;
        line-height: 1.35;
        color: var(--vff-text);
        text-decoration: none;
    }
    .line-head :global(.line-name:hover > span:last-child) {
        color: var(--vff-accent-ink);
        text-decoration: underline;
        text-decoration-color: var(--vff-accent);
        text-underline-offset: 3px;
    }
    .relation-fact {
        margin: 4px 0 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        overflow-wrap: anywhere;
    }
    .periods-label {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        line-height: var(--vff-lh-ui);
    }
    .periods ul {
        margin: 4px 0 0;
        padding-left: 1.2em;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: 1.6;
    }
    .periods a {
        padding-block: 4px;
        overflow-wrap: anywhere;
    }
    .confidence,
    .cite,
    .partial {
        margin: 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .quote {
        margin: 0;
        padding: 12px 16px;
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-subtle);
    }
    .quote p {
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: var(--vff-lh-body);
        color: var(--vff-text);
        text-wrap: pretty;
    }
    .quote:lang(fr) p {
        quotes: '«\00a0' '\00a0»';
    }
    .quote p::before {
        content: open-quote;
    }
    .quote p::after {
        content: close-quote;
    }
    .fold summary {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-link);
        text-decoration: underline;
        text-underline-offset: 0.18em;
        cursor: pointer;
    }
    .fold[open] summary {
        margin-bottom: 4px;
    }
    .fold .line-list {
        border-top: 0;
    }
    .partial {
        margin-top: var(--vff-space-4);
    }

    @media (max-width: 900px) {
        .about-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        .infobox .row {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
