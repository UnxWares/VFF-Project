<script>
    /**
     * Index des compagnies de chemins de fer : un tableau par genre (grandes compagnies, administrations de l'État,
     * SNCF, gestionnaires d'infrastructure, réseaux secondaires) puis la couverture, décennie par décennie. Les
     * chiffres viennent de CompanyFigures (cache, vff:compute-company-figures) ; la page ne calcule rien.
     */
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import SeoHead from '$components/SeoHead.svelte';
    import EmptyState from '$components/ui/EmptyState.svelte';
    import { i18n } from '$lib/i18n.js';
    import { formatNumber, formatLocale } from '$lib/typo.js';
    import { companiesOfKind, KINDS, yearOf } from '$lib/companySheet.js';
    import { percentOf } from '$lib/networkFigures.js';

    /** @type {{ figures: null | { computed_at: string, current_year: number, companies: Array<any>, coverage: Array<any> } }} */
    let { figures } = $props();

    const t = (key, vars) => String($i18n.t(`companies.index.${key}`, vars));
    const lang = $derived($i18n.language ?? 'fr');
    const n = (v) => formatNumber(v, lang);
    const NBSP = ' ';
    const computedOn = $derived(
        figures
            ? new Date(figures.computed_at).toLocaleDateString(formatLocale(lang), {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : '',
    );

    const groups = $derived(
        KINDS.map((kind) => ({
            kind,
            rows: companiesOfKind(figures?.companies ?? [], kind),
        })).filter((g) => g.rows.length > 0),
    );
    const period = (c) =>
        c.ended
            ? t('period', { from: yearOf(c.founded.date), to: yearOf(c.ended.date) })
            : t('period_open', { from: yearOf(c.founded.date) });
    const peak = (c) =>
        c.km_peak > 0 ? t('peak_value', { km: n(c.km_peak), year: c.peak_year }) : t('no_peak');
    const share = (v) => (v === null || v === undefined ? '-' : `${percentOf(v)}${NBSP}%`);
    const coverageLabel = (r) => (r.current ? t('today') : String(r.year));
    const coverage = $derived((figures?.coverage ?? []).filter((r) => r.lines_open > 0));
</script>

<SeoHead title={t('meta_title')} description={t('meta_description')} path="/companies" />
<svelte:head>
    <title>{t('meta_title')} - VFF</title>
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={t('title')}
            subtitle={t('subtitle')}
            status={figures ? t('computed', { date: computedOn }) : null}
        />

        {#if figures}
            {#each groups as g (g.kind)}
                <SectionBlock
                    kind={g.kind === 'etat' || g.kind === 'infrastructure' ? 'alt' : undefined}
                    title={t(`groups.${g.kind}.title`)}
                    maxWidth="1180px"
                >
                    <p class="intro">{t(`groups.${g.kind}.intro`)}</p>
                    <!-- Tableau large : le bloc défile au clavier (tabindex, consigne de .vff-table-wrap). -->
                    <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                    <div
                        class="vff-table-wrap"
                        tabindex="0"
                        role="region"
                        aria-label={t('table_caption', { group: t(`groups.${g.kind}.title`) })}
                    >
                        <table class="vff-table">
                            <caption class="vff-sr-only"
                                >{t('table_caption', {
                                    group: t(`groups.${g.kind}.title`),
                                })}</caption
                            >
                            <thead>
                                <tr>
                                    <th scope="col">{t('col_company')}</th>
                                    <th scope="col">{t('col_period')}</th>
                                    <th scope="col">{t('col_seat')}</th>
                                    <th scope="col" class="num">{t('col_lines')}</th>
                                    <th scope="col" class="num">{t('col_peak')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each g.rows as c (c.slug)}
                                    <tr>
                                        <th scope="row">
                                            <Link href="/companies/{c.slug}" class="vff-link"
                                                >{c.short}</Link
                                            >
                                        </th>
                                        <td>{period(c)}</td>
                                        <td>{c.seat ?? t('seat_none')}</td>
                                        <td class="num">{n(c.lines)}</td>
                                        <td class="num">{peak(c)}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </SectionBlock>
            {/each}

            <SectionBlock title={t('coverage_title')} maxWidth="1180px">
                <p class="intro">{t('coverage_intro')}</p>
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <div
                    class="vff-table-wrap"
                    tabindex="0"
                    role="region"
                    aria-label={t('coverage_caption')}
                >
                    <table class="vff-table">
                        <caption class="vff-sr-only">{t('coverage_caption')}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{t('coverage_date')}</th>
                                <th scope="col" class="num">{t('coverage_lines')}</th>
                                <th scope="col" class="num">{t('coverage_lines_attached')}</th>
                                <th scope="col" class="num">{t('coverage_km')}</th>
                                <th scope="col" class="num">{t('coverage_km_attached')}</th>
                                <th scope="col" class="num">{t('coverage_share')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each coverage as r (r.year)}
                                <tr>
                                    <th scope="row">{coverageLabel(r)}</th>
                                    <td class="num">{n(r.lines_open)}</td>
                                    <td class="num">{n(r.lines_attached)}</td>
                                    <td class="num">{n(r.km_open)}</td>
                                    <td class="num">{n(r.km_attached)}</td>
                                    <td class="num">{share(r.share)}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </SectionBlock>

            <SectionBlock kind="alt" title={t('notes_title')} maxWidth="1180px">
                <ul class="notes">
                    <li>{t('note_kinds')}</li>
                    <li>{t('note_km')}</li>
                </ul>
                <p class="link">
                    <Link href="/network" class="vff-link">{t('network_link')}</Link>
                </p>
                <p class="link">
                    <Link href="/glossary#grandes-compagnies" class="vff-link"
                        >{t('glossary_link')}</Link
                    >
                </p>
            </SectionBlock>
        {:else}
            <SectionBlock maxWidth="1180px">
                <EmptyState icon="bi-hourglass-split" title={t('not_computed')} />
            </SectionBlock>
        {/if}
    </main>
</Layout>

<style>
    .intro {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    th[scope='row'] {
        font-weight: 600;
    }
    .notes {
        max-width: var(--vff-measure);
        margin: 0;
        padding-left: 1.2em;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.6;
    }
    .notes li {
        margin-bottom: var(--vff-space-2);
    }
    .link {
        margin: var(--vff-space-4) 0 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
    }
</style>
