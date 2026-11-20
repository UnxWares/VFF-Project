<script>
    /**
     * Rames remarquables d'une série (fiche matériel) : pour chaque rame, ses faits datés dans l'ordre du
     * récit, chacun avec le lien de la page qui le rapporte, puis ce que les sources ne disent pas.
     * Données : prop `notable_units` de MaterialController::show (database/data/material_notable_units.php).
     */
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { i18n } from '$lib/i18n.js';
    import { notableDate, notableFactDate } from '$lib/notableUnits.js';
    import { colon, quoted } from '$lib/typo.js';

    /** @type {{ units: Array<{ number: string, nickname: string|null, facts: Array<any>, unknown: string|null }> }} */
    let { units } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    const t = (key, opts) => String($i18n.t(key, opts));

    /** Langue de la citation : celle de la page citée (Wikipédia en anglais, presse en français). */
    const quoteLang = (fact) => (fact.source.provider === 'wikipedia-en' ? 'en' : 'fr');

    /** Faits sans date (surnom, livrée…), posés avant la chronologie. */
    const traitsOf = (unit) => unit.facts.filter((f) => !f.date);
    const datedOf = (unit) => unit.facts.filter((f) => f.date);

    function sourceLabel(source) {
        const dated = source.date
            ? `${source.publisher}, ${notableDate(source.date, 'day', lang)}`
            : source.publisher;
        return source.reported_by
            ? t('materials.notable.reported', { publisher: dated, reported: source.reported_by })
            : dated;
    }
</script>

{#snippet factBody(fact)}
    <p class="what">{fact.text}</p>
    <p class="src">
        <a href={fact.source.url} target="_blank" rel="noopener noreferrer"
            >{sourceLabel(fact.source)}<span class="vff-sr-only"> ({t('common.new_tab')})</span></a
        >{#if fact.quote}{colon(lang)}<span lang={quoteLang(fact)}
                >{quoted(fact.quote, quoteLang(fact))}</span
            >{/if}
    </p>
{/snippet}

<SheetSection
    id="notable-units"
    title={t('materials.notable.title')}
    intro={t('materials.notable.intro')}
>
    {#each units as unit (unit.number)}
        <article class="unit" aria-labelledby="unit-{unit.number}">
            <h3 id="unit-{unit.number}">
                {unit.nickname
                    ? t('materials.notable.unit', { number: unit.number, nickname: unit.nickname })
                    : t('materials.notable.unit_plain', { number: unit.number })}
            </h3>
            {#if traitsOf(unit).length}
                <ul class="facts traits">
                    {#each traitsOf(unit) as fact (fact.kind)}
                        <li>{@render factBody(fact)}</li>
                    {/each}
                </ul>
            {/if}
            <ol class="facts dated">
                {#each datedOf(unit) as fact (fact.kind)}
                    <li>
                        <span class="when">{notableFactDate(fact, t, lang)}</span>
                        {@render factBody(fact)}
                    </li>
                {/each}
            </ol>
            {#if unit.unknown}
                <p class="unknown">{unit.unknown}</p>
            {/if}
        </article>
    {/each}
</SheetSection>

<style>
    .unit {
        max-width: var(--vff-measure);
    }
    .unit + .unit {
        margin-top: var(--vff-space-6);
    }
    h3 {
        margin: 0 0 var(--vff-space-4);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-h3);
        line-height: 1.3;
        color: var(--vff-text);
    }
    .facts {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .traits {
        margin-bottom: var(--vff-space-5);
    }
    .dated li {
        display: grid;
        grid-template-columns: minmax(0, 11rem) minmax(0, 1fr);
        column-gap: var(--vff-space-4);
    }
    .when {
        grid-column: 1;
        color: var(--vff-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .what,
    .src {
        grid-column: 2;
        margin: 0;
    }
    .what,
    .when {
        font-size: var(--vff-fs-body);
    }
    .what {
        color: var(--vff-text);
        line-height: var(--vff-lh-body);
    }
    .src {
        color: var(--vff-text-muted);
        font-size: var(--vff-fs-body);
    }
    .unknown {
        margin: var(--vff-space-5) 0 0;
        color: var(--vff-text-muted);
        font-style: italic;
    }
    @media (max-width: 600px) {
        .dated li {
            grid-template-columns: minmax(0, 1fr);
        }
        .when,
        .what,
        .src {
            grid-column: 1;
        }
    }
</style>
