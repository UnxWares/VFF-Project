<script>
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { nextLevelSentence } from '$lib/reputation.js';

    /**
     * Réputation du membre dans son profil : niveau (absent d'un compte suspendu), trois chiffres,
     * taux d'acceptation, ce qu'il manque pour le niveau suivant et le lien vers la page d'aide.
     * `reputation` : { applied, rejected, reviews, acceptance_rate, level, next } (ProfileController).
     */
    /** @type {{ reputation: { applied: number, rejected: number, reviews: number, acceptance_rate: number|null, level: string|null, next: { level: string, missing: { applied: number, reviews: number, acceptance: number|null } }|null } }} */
    let { reputation } = $props();

    const number = (value) => value.toLocaleString($i18n.language);
    const nextSentence = $derived(
        nextLevelSentence(
            (key, options) => String($i18n.t(key, options)),
            $i18n.language,
            reputation.next,
        ),
    );
</script>

<section class="reputation" aria-labelledby="reputation-title">
    <h3 id="reputation-title">{$i18n.t('reputation.profile.title')}</h3>
    <dl>
        <div>
            <dt>{$i18n.t('reputation.profile.level')}</dt>
            <dd>
                {#if reputation.level}
                    <span class="vff-badge muted">
                        {$i18n.t(`reputation.levels.${reputation.level}`)}
                    </span>
                {:else}
                    -
                {/if}
            </dd>
        </div>
        <div>
            <dt>{$i18n.t('reputation.profile.applied')}</dt>
            <dd>{number(reputation.applied)}</dd>
        </div>
        <div>
            <dt>{$i18n.t('reputation.profile.rejected')}</dt>
            <dd>{number(reputation.rejected)}</dd>
        </div>
        <div>
            <dt>{$i18n.t('reputation.profile.reviews')}</dt>
            <dd>{number(reputation.reviews)}</dd>
        </div>
        {#if reputation.acceptance_rate !== null}
            <div>
                <dt>{$i18n.t('reputation.profile.acceptance')}</dt>
                <dd>
                    {$i18n.t('reputation.profile.percent', {
                        count: number(reputation.acceptance_rate),
                    })}
                </dd>
            </div>
        {/if}
    </dl>

    {#if reputation.level === null}
        <p class="note">{$i18n.t('reputation.profile.level_hidden')}</p>
    {:else if nextSentence}
        <p class="note">{nextSentence}</p>
    {/if}

    <Link class="vff-link" href="/contribute/reputation">
        {$i18n.t('reputation.profile.help_link')}
    </Link>
</section>

<style>
    .reputation {
        margin: 0 0 20px;
    }
    h3 {
        margin: 0 0 var(--vff-space-2);
    }
    dl {
        margin: 0 0 var(--vff-space-3);
        border-top: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    dl > div {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    dt {
        color: var(--vff-text-muted);
    }
    dd {
        margin: 0;
        font-weight: 600;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .note {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-3);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
</style>
