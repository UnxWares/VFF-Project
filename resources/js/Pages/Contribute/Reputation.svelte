<script>
    import { Link } from '@inertiajs/svelte';
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Page d'aide publique : ce que comptent les chiffres de réputation et les seuils de chaque
     * niveau. `levels` : { clé: { applied, reviews, acceptance } } du plus bas au plus haut, lu dans
     * config/reputation.php (la page montre toujours les seuils réellement appliqués).
     */
    /** @type {{ levels: Record<string, { applied: number, reviews: number, acceptance: number }> }} */
    let { levels } = $props();

    const figures = ['applied', 'rejected', 'reviews', 'rate'];
    const rules = ['who', 'suspended', 'deleted', 'recalc', 'export'];

    /** Conditions d'un niveau, en une phrase ; null pour le niveau de départ (aucun seuil). */
    function requirement(thresholds) {
        const t = (key, options) => String($i18n.t(key, options));
        /** @type {string[]} */
        const parts = [];
        if (thresholds.applied > 0)
            parts.push(t('reputation.thresholds.applied', { count: thresholds.applied }));
        if (thresholds.reviews > 0)
            parts.push(t('reputation.thresholds.reviews', { count: thresholds.reviews }));
        if (thresholds.acceptance > 0)
            parts.push(t('reputation.thresholds.acceptance', { count: thresholds.acceptance }));
        if (parts.length === 0) return null;
        const conditions = new Intl.ListFormat($i18n.language, {
            style: 'long',
            type: 'conjunction',
        }).format(parts);
        return t('reputation.thresholds.requires', { conditions });
    }
</script>

<svelte:head>
    <title>{$i18n.t('reputation.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('reputation.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero
            title={$i18n.t('reputation.hero.title')}
            subtitle={$i18n.t('reputation.hero.subtitle')}
        />

        <SectionBlock
            title={$i18n.t('reputation.figures.title')}
            intro={$i18n.t('reputation.figures.intro')}
            maxWidth="720px"
        >
            <dl class="entries">
                {#each figures as key (key)}
                    <div>
                        <dt>{$i18n.t(`reputation.figures.${key}.title`)}</dt>
                        <dd>{$i18n.t(`reputation.figures.${key}.text`)}</dd>
                    </div>
                {/each}
            </dl>
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={$i18n.t('reputation.thresholds.title')}
            intro={$i18n.t('reputation.thresholds.intro')}
            maxWidth="720px"
        >
            <ol class="levels">
                {#each Object.entries(levels) as [key, thresholds] (key)}
                    <li>
                        <h3>{$i18n.t(`reputation.levels.${key}`)}</h3>
                        <p>
                            {requirement(thresholds) ?? $i18n.t('reputation.thresholds.everyone')}
                        </p>
                    </li>
                {/each}
            </ol>
            <p class="note">{$i18n.t('reputation.thresholds.rate_note')}</p>
        </SectionBlock>

        <SectionBlock title={$i18n.t('reputation.rules.title')} maxWidth="720px">
            <dl class="entries">
                {#each rules as key (key)}
                    <div>
                        <dt>{$i18n.t(`reputation.rules.${key}_title`)}</dt>
                        <dd>{$i18n.t(`reputation.rules.${key}_text`)}</dd>
                    </div>
                {/each}
            </dl>
            <p class="more">
                <Link href="/privacy" class="vff-link"
                    >{$i18n.t('reputation.rules.privacy_link')}</Link
                >
            </p>
        </SectionBlock>
    </main>
</Layout>

<style>
    /* Définitions et niveaux : une suite de filets, sans cadre ni icône. */
    .entries,
    .levels {
        display: grid;
        gap: var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .entries > div,
    .levels > li {
        padding-top: var(--vff-space-3);
        border-top: 1px solid var(--vff-border-strong);
    }
    dt,
    h3 {
        margin: 0 0 var(--vff-space-1);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        font-weight: 600;
        line-height: 1.35;
    }
    dd,
    .levels p {
        max-width: var(--vff-measure);
        margin: 0;
        font-size: var(--vff-fs-body);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .more {
        margin: var(--vff-space-5) 0 0;
        font-size: var(--vff-fs-body);
    }
</style>
