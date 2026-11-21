<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import IconCard from '$components/IconCard.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';
    import { Link } from '@inertiajs/svelte';

    // Rangées de l'équipe après celle de Baptiste (citation) : `role` absent pour le collectif.
    const people = [
        { key: 'david', role: true },
        { key: 'unxwares', role: true },
        { key: 'contributors', role: false },
    ];

    const values = [
        {
            titleKey: 'whoarewe.values.open_title',
            textKey: 'whoarewe.values.open_text',
        },
        {
            titleKey: 'whoarewe.values.rigorous_title',
            textKey: 'whoarewe.values.rigorous_text',
        },
        {
            titleKey: 'whoarewe.values.collective_title',
            textKey: 'whoarewe.values.collective_text',
        },
        {
            titleKey: 'whoarewe.values.durable_title',
            textKey: 'whoarewe.values.durable_text',
        },
    ];

    // `now` : jalon atteint le plus récent (point plein) ; `goal` : objectif, pas encore atteint (point creux).
    const milestones = [
        { dateKey: 'whoarewe.milestones.item1_date', textKey: 'whoarewe.milestones.item1_text' },
        { dateKey: 'whoarewe.milestones.item2_date', textKey: 'whoarewe.milestones.item2_text' },
        {
            dateKey: 'whoarewe.milestones.item3_date',
            textKey: 'whoarewe.milestones.item3_text',
            now: true,
        },
        {
            dateKey: 'whoarewe.milestones.item4_date',
            textKey: 'whoarewe.milestones.item4_text',
            goal: true,
        },
    ];
</script>

<svelte:head>
    <title>{$i18n.t('whoarewe.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('whoarewe.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('whoarewe.hero.title')}
            subtitle={$i18n.t('whoarewe.hero.subtitle')}
        />

        <SectionBlock
            title={$i18n.t('whoarewe.team.title')}
            intro={$i18n.t('whoarewe.team.intro')}
            maxWidth="760px"
        >
            <ul class="team">
                <li>
                    <h3 class="who">
                        <span class="name">{$i18n.t('whoarewe.team.baptiste.name')}</span>
                        <span class="vff-sr-only">, </span>
                        <span class="role">{$i18n.t('whoarewe.team.baptiste.role')}</span>
                    </h3>
                    <figure class="quote">
                        <blockquote>
                            <p>{$i18n.t('whoarewe.team.baptiste.quote')}</p>
                        </blockquote>
                        <figcaption class="vff-sr-only">
                            {$i18n.t('whoarewe.team.baptiste.name')}, {$i18n.t(
                                'whoarewe.team.baptiste.role',
                            )}
                        </figcaption>
                    </figure>
                </li>
                {#each people as person (person.key)}
                    <li>
                        <h3 class="who">
                            <span class="name">{$i18n.t(`whoarewe.team.${person.key}.name`)}</span>
                            {#if person.role}
                                <span class="vff-sr-only">, </span>
                                <span class="role"
                                    >{$i18n.t(`whoarewe.team.${person.key}.role`)}</span
                                >
                            {/if}
                        </h3>
                        <p class="text">{$i18n.t(`whoarewe.team.${person.key}.text`)}</p>
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('whoarewe.vision.title')} maxWidth="760px">
            <div class="vff-prose">
                <p>{$i18n.t('whoarewe.vision.paragraph')}</p>
            </div>
        </SectionBlock>

        <SectionBlock title={$i18n.t('whoarewe.values.title')} maxWidth="1180px">
            <ul class="vff-points">
                {#each values as v (v.titleKey)}
                    <li><IconCard title={$i18n.t(v.titleKey)} text={$i18n.t(v.textKey)} /></li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('whoarewe.milestones.title')} maxWidth="760px">
            <ol class="timeline">
                {#each milestones as m (m.dateKey)}
                    <li class={{ now: m.now, goal: m.goal }}>
                        <span class="date">{$i18n.t(m.dateKey)}</span>
                        <span class="rail" aria-hidden="true"></span>
                        <span class="text">{$i18n.t(m.textKey)}</span>
                    </li>
                {/each}
            </ol>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('whoarewe.cta.title')}
            intro={$i18n.t('whoarewe.cta.text')}
        >
            {#snippet actions()}
                <Link href="/contribute" class="vff-btn primary">
                    {$i18n.t('whoarewe.cta.contribute')}
                </Link>
                <Link href="/donate" class="vff-btn secondary">
                    {$i18n.t('whoarewe.cta.donate')}
                </Link>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    /* Équipe : une rangée par personne ou groupe, filet au-dessus, nom et rôle sur une ligne,
       texte dessous à la largeur de lecture. Pas de carte. */
    .team {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .team li {
        padding: var(--vff-space-5) 0;
        border-top: 1px solid var(--vff-border);
    }
    .team li:last-child {
        padding-bottom: 0;
    }
    .who {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        column-gap: var(--vff-space-3);
        margin: 0 0 var(--vff-space-3);
        font-size: var(--vff-fs-h3);
        line-height: 1.35;
    }
    .name {
        font-family: var(--vff-font-heading);
        font-weight: 700;
        color: var(--vff-text);
    }
    .role {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-md);
        font-weight: 400;
        color: var(--vff-text-muted);
    }
    .text,
    .quote blockquote {
        max-width: var(--vff-measure);
        margin: 0;
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.65;
        color: var(--vff-text);
    }
    .quote {
        margin: 0;
    }
    /* Citation : même corps que le texte courant, un cran de plus, sans ornement. */
    .quote blockquote {
        max-width: 62ch;
        font-size: var(--vff-fs-lead);
        line-height: 1.6;
        text-wrap: pretty;
    }
    .quote p {
        margin: 0;
    }

    /* Frise des jalons : date, rail, texte. Le trait relie chaque point au suivant et s'arrête au dernier. */
    .timeline {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .timeline li {
        display: grid;
        grid-template-columns: 4.25rem 20px minmax(0, 1fr);
        column-gap: 16px;
        padding-bottom: var(--vff-space-5);
    }
    .timeline li:last-child {
        padding-bottom: 0;
    }
    .date {
        font-family: var(--vff-font-display);
        font-size: var(--vff-fs-h3);
        font-weight: 700;
        line-height: 1.35;
        text-align: right;
        font-variant-numeric: tabular-nums;
        color: var(--vff-text);
    }
    .rail {
        position: relative;
    }
    .rail::before {
        content: '';
        position: absolute;
        top: 7px;
        left: 3px;
        width: 14px;
        height: 14px;
        box-sizing: border-box;
        border: 3px solid var(--vff-border-control);
        border-radius: 50%;
        background-color: var(--vff-border-control);
    }
    .rail::after {
        content: '';
        position: absolute;
        top: 24px;
        /* 3 px avant le point suivant (posé à 7 px du haut), comme l'écart sous le point. */
        bottom: calc(-1 * (var(--vff-space-5) + 4px));
        left: calc(50% - 1px);
        width: 2px;
        background-color: var(--vff-border-strong);
    }
    li:last-child .rail::after {
        display: none;
    }
    li.now .rail::before {
        border-color: var(--vff-accent);
        background-color: var(--vff-accent);
    }
    li.goal .rail::before {
        background-color: var(--vff-bg);
    }
    .text {
        max-width: var(--vff-measure);
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        line-height: 1.55;
        color: var(--vff-text);
    }

    @media (max-width: 600px) {
        .timeline li {
            grid-template-columns: 3.25rem 20px minmax(0, 1fr);
            column-gap: 12px;
        }
        .date {
            font-size: var(--vff-fs-md);
            line-height: 1.6;
        }
        .rail::before {
            top: 8px;
        }
        .rail::after {
            top: 25px;
            bottom: calc(-1 * (var(--vff-space-5) + 5px));
        }
    }
</style>
