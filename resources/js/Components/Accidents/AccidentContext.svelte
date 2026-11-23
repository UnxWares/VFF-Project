<script>
    /**
     * Contexte sourcé d'un accident (vff:enrich-accidents) : énoncés par section avec les numéros des
     * documents qui les appuient, corrections faites d'après ces documents, images de Commons (auteur et
     * licence), puis la liste numérotée des documents et archives. Rien n'est affiché sans contexte.
     */
    import SheetSection from '$components/Sheet/SheetSection.svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import Notice from '$components/ui/Notice.svelte';
    import { i18n } from '$lib/i18n.js';
    import { documentDate, correctionValue, referenceLang } from '$lib/accidentContext.js';
    import { formatLocale } from '$lib/typo.js';

    /**
     * `part` : `text` (contexte, corrections, images) ou `references` (liste numérotée des documents), pour placer
     * les documents plus bas dans la fiche, près des sources.
     * @type {{ context: { checked_on: string|null, sections: Array<any>, references: Array<any>, images: Array<any>, corrections: Array<any> }, part?: 'text'|'references' }}
     */
    let { context, part = 'text' } = $props();

    const lang = $derived($i18n.language ?? 'fr');
    /** Numéro d'appel de chaque document (ordre de la liste). */
    const numbers = $derived(new Map(context.references.map((r, i) => [r.key, i + 1])));
    const byKey = $derived(new Map(context.references.map((r) => [r.key, r])));
    const checkedText = $derived(
        context.checked_on
            ? new Date(`${context.checked_on}T12:00:00`).toLocaleDateString(formatLocale(lang), {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : null,
    );

    function pick(texts) {
        return texts?.[lang] ?? texts?.fr ?? '';
    }

    function refLabel(key) {
        const r = byKey.get(key);
        return r ? r.title : key;
    }
</script>

{#snippet calls(keys)}
    <span class="calls">
        {#each keys as key (key)}
            <a
                class="call"
                href="#ref-{key}"
                aria-label={$i18n.t('accidents.context.call_label', {
                    n: numbers.get(key),
                    title: refLabel(key),
                })}>{numbers.get(key)}</a
            >
        {/each}
    </span>
{/snippet}

{#if part === 'text' && context.sections.length}
    <SheetSection
        id="context"
        title={$i18n.t('accidents.context.title')}
        intro={$i18n.t('accidents.context.intro')}
    >
        {#if context.corrections.length}
            <div class="corrections">
                <Notice
                    tone="info"
                    live="off"
                    title={$i18n.t('accidents.context.corrections_title')}
                >
                    <ul>
                        {#each context.corrections as c (c.field)}
                            <li>
                                {c.before == null
                                    ? $i18n.t('accidents.context.correction_new', {
                                          field: $i18n.t(`accidents.context.fields.${c.field}`),
                                          value: correctionValue(c.field, c.value, lang, $i18n.t),
                                      })
                                    : $i18n.t('accidents.context.correction', {
                                          field: $i18n.t(`accidents.context.fields.${c.field}`),
                                          before: correctionValue(c.field, c.before, lang, $i18n.t),
                                          value: correctionValue(c.field, c.value, lang, $i18n.t),
                                      })}
                                {pick(c.reason)}
                                {@render calls(c.references)}
                                {#if c.single_source}
                                    <span class="vff-badge warning"
                                        >{$i18n.t('accidents.context.single_source')}</span
                                    >
                                {/if}
                            </li>
                        {/each}
                    </ul>
                </Notice>
            </div>
        {/if}

        <div class="vff-prose context">
            {#each context.sections as s (s.section)}
                <h3>{$i18n.t(`accidents.context.sections.${s.section}`)}</h3>
                {#each s.entries as e, i (i)}
                    <p>
                        {pick(e.text)}
                        {@render calls(e.references)}
                    </p>
                    {#if e.quote}
                        <blockquote lang={e.quote.lang}>
                            <p>« {e.quote.text} »</p>
                            <footer>
                                <a href="#ref-{e.quote.reference}">{refLabel(e.quote.reference)}</a>
                            </footer>
                        </blockquote>
                    {/if}
                {/each}
            {/each}
        </div>

        {#if context.images.length}
            <h3 class="sub">{$i18n.t('accidents.context.images_title')}</h3>
            <ul class="images">
                {#each context.images as img (img.file)}
                    <li>
                        <figure>
                            <a href={img.page_url} target="_blank" rel="noopener noreferrer">
                                <img
                                    src={commonsSrc(img.thumb_url ?? img.image_url)}
                                    alt={pick(img.caption)}
                                    width={img.width && img.height ? 500 : undefined}
                                    height={img.width && img.height
                                        ? Math.round((500 * img.height) / img.width)
                                        : undefined}
                                    loading="lazy"
                                    decoding="async"
                                /><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span>
                            </a>
                            <figcaption>
                                <span class="caption">{pick(img.caption)}</span>
                                <span class="credit">
                                    {img.author},
                                    {#if img.license_url}<a
                                            href={img.license_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            >{img.license}<span class="vff-sr-only">
                                                ({$i18n.t('common.new_tab')})</span
                                            ></a
                                        >{:else}{img.license}{/if}, {$i18n.t(
                                        'accidents.context.via_commons',
                                    )}
                                </span>
                            </figcaption>
                        </figure>
                    </li>
                {/each}
            </ul>
        {/if}
    </SheetSection>
{/if}

{#if part === 'references' && context.references.length}
    <SheetSection
        id="archives"
        title={$i18n.t('accidents.context.references_title')}
        count={context.references.length}
        intro={$i18n.t('accidents.context.references_intro')}
    >
        <ol class="refs">
            {#each context.references as r (r.key)}
                <li id="ref-{r.key}">
                    <p class="ref-head">
                        <span class="kind">{$i18n.t(`accidents.context.kinds.${r.kind}`)}</span>
                        <span class="vff-badge muted"
                            >{$i18n.t(`accidents.context.reliability.${r.reliability}`)}</span
                        >
                        {#if r.access !== 'open'}
                            <span class="vff-badge warning"
                                >{$i18n.t(`accidents.context.access.${r.access}`)}</span
                            >
                        {/if}
                    </p>
                    <a class="ref-title" href={r.url} target="_blank" rel="noopener noreferrer"
                        ><span lang={referenceLang(r)}>{r.title}</span><i
                            class="bi bi-box-arrow-up-right vff-ext"
                            aria-hidden="true"
                        ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
                    >
                    <p class="ref-meta">
                        {[r.author, r.publisher, documentDate(r.published, lang), r.locator]
                            .filter(Boolean)
                            .join(', ')}
                    </p>
                    {#if r.note}
                        <p class="ref-note" lang="fr">{r.note}</p>
                    {/if}
                </li>
            {/each}
        </ol>
        {#if checkedText}
            <p class="checked">
                {$i18n.t('accidents.context.checked_on', { date: checkedText })}
            </p>
        {/if}
    </SheetSection>
{/if}

<style>
    .context h3 {
        margin: var(--vff-block-space) 0 var(--vff-space-3);
    }
    .context h3:first-child {
        margin-top: 0;
    }
    .context p {
        max-width: var(--vff-measure);
        hyphens: manual;
    }
    .calls {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-left: 4px;
        vertical-align: baseline;
    }
    /* Appel de référence lisible (texte de 17 px) et cible de 24 px. */
    .call {
        display: inline-grid;
        place-items: center;
        min-width: 24px;
        min-height: 24px;
        padding: 0 6px;
        border-radius: 999px;
        background-color: var(--vff-surface-2, var(--vff-surface));
        border: 1px solid var(--vff-border);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-variant-numeric: tabular-nums;
        line-height: 1;
        color: var(--vff-text);
        text-decoration: none;
    }
    .call:hover {
        color: var(--vff-accent-ink);
        border-color: currentColor;
    }
    blockquote {
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-space-4);
        padding: var(--vff-space-3) var(--vff-space-4);
        border-radius: var(--vff-radius, 12px);
        background-color: var(--vff-surface);
        border: 1px solid var(--vff-border);
    }
    blockquote p {
        margin: 0 0 var(--vff-space-2);
        font-style: italic;
    }
    blockquote footer {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
    }
    .corrections {
        max-width: var(--vff-measure);
        margin-bottom: var(--vff-block-space);
    }
    .corrections ul {
        margin: var(--vff-space-2) 0 0;
        padding-left: 1.2em;
    }
    .sub {
        margin: var(--vff-block-space) 0 var(--vff-space-3);
    }
    .images {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
        gap: var(--vff-space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    figure {
        display: grid;
        gap: var(--vff-space-2);
        margin: 0;
    }
    figure img {
        display: block;
        width: 100%;
        height: auto;
        border-radius: var(--vff-radius, 12px);
        background-color: var(--vff-surface);
    }
    figcaption {
        display: grid;
        gap: 2px;
        font-family: var(--vff-font-heading);
    }
    .caption {
        font-size: var(--vff-fs-md);
        color: var(--vff-text);
    }
    .credit {
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .refs {
        display: grid;
        gap: var(--vff-space-3);
        max-width: 820px;
        margin: 0;
        padding-left: 2em;
    }
    .refs li {
        padding-bottom: var(--vff-space-4);
        border-bottom: 1px solid var(--vff-border);
        scroll-margin-top: 6rem;
    }
    .refs li:last-child {
        border-bottom: 0;
    }
    .refs li::marker {
        font-family: var(--vff-font-heading);
        color: var(--vff-text-muted);
    }
    .refs li:target {
        outline: 2px solid var(--vff-accent);
        outline-offset: 4px;
        border-radius: 8px;
    }
    .ref-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--vff-space-2) var(--vff-space-3);
        margin: 0 0 var(--vff-space-1);
        font-family: var(--vff-font-heading);
    }
    .kind {
        font-weight: 600;
        color: var(--vff-text);
    }
    .ref-title {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-body);
        overflow-wrap: anywhere;
    }
    .ref-meta,
    .ref-note,
    .checked {
        margin: var(--vff-space-1) 0 0;
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        color: var(--vff-text-muted);
    }
    .checked {
        margin-top: var(--vff-space-4);
    }
</style>
