<script>
    /**
     * Texte courant dont la première occurrence de chaque sigle ferroviaire connu du glossaire (BAL, KVB, TVM,
     * PK…) devient un <abbr> relié à sa définition (`/glossary#bal`) : le développé du sigle est dans `title`,
     * le lien est discret (même couleur et même corps que le texte, pointillé sous le sigle) et ne change
     * pas la mise en page. Le reste du texte est rendu tel quel, sans HTML. « Première occurrence » : dans ce
     * texte (un paragraphe de la fiche), pas dans toute la page.
     *
     *   <p><GlossaryText text={signallingIntro} /></p>
     */
    import { Link } from '@inertiajs/svelte';
    import { i18n } from '$lib/i18n.js';
    import { abbrSlug, splitAbbreviations } from '$lib/glossary.js';

    /** @type {{ text: string }} */
    let { text } = $props();

    // Les sigles et leur développé viennent de lang/*/glossary.php (`glossary.abbr`).
    const known = $derived(
        /** @type {Record<string, string>} */ (
            $i18n.getResourceBundle?.($i18n.language ?? 'fr', 'translation') ?? {}
        ),
    );
    const sigles = $derived(
        Object.keys(known)
            .filter((key) => key.startsWith('glossary.abbr.'))
            .map((key) => key.slice('glossary.abbr.'.length)),
    );
    const parts = $derived(splitAbbreviations(text, sigles));
</script>

{#each parts as part, i (i)}{#if 'abbr' in part}<abbr
            class="glossary-abbr"
            title={String(known[`glossary.abbr.${part.abbr}`] ?? '')}
            ><Link
                href={`/glossary#${abbrSlug(part.abbr)}`}
                aria-label={String($i18n.t('glossary.abbr_link', { term: part.abbr }))}
                >{part.abbr}</Link
            ></abbr
        >{:else}{part.text}{/if}{/each}

<style>
    .glossary-abbr {
        text-decoration: none;
    }
    .glossary-abbr :global(a) {
        color: inherit;
        font: inherit;
        text-decoration: underline dotted;
        text-underline-offset: 0.2em;
    }
    .glossary-abbr :global(a:hover) {
        color: var(--vff-link);
        text-decoration-style: solid;
    }
    .glossary-abbr :global(a:focus-visible) {
        outline: 2px solid var(--vff-accent-strong);
        outline-offset: 2px;
        border-radius: var(--vff-radius-xs);
    }
</style>
