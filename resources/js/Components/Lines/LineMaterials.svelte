<script>
    import { Link } from '@inertiajs/svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import { i18n } from '$lib/i18n.js';
    import { buildersText, servicePeriodText } from '$lib/materialFacts.js';
    import { mediaLicenseHref, mediaLicenseLabel, mediaSourceHref } from '$lib/mediaCredits.js';
    import { endText, periodsSummary, relationNotes, relationsList } from '$lib/materialPeriods.js';
    import { formatLocale } from '$lib/typo.js';
    import SheetSection from '$components/Sheet/SheetSection.svelte';

    /**
     * Section « Matériel roulant » de la fiche ligne (LineSheet::build, clé `materials`) : une tuile par
     * série, avec sa photo (vignette Commons de la fiche de la série, Material::thumbnail, crédit sous
     * l'image ; vue de profil très large montrée entière, `fit` = `contain`), sa catégorie, son
     * constructeur, ses années de service, ses années de circulation sur la ligne quand une source les date (regroupées, cf. lib/materialPeriods.js), les relations qu'elle
     * assurait (datées ou non), les faits sourcés sur ces relations (carburant…) et la fin de circulation
     * telle que les sources la connaissent (`end`, LineMaterialEnd : sur la ligne, sinon retrait de la série,
     * sinon « fin inconnue »). Ordre du serveur : la plus récente d'abord. Montrée seulement s'il y a au
     * moins une série.
     *
     * La photo et le nom mènent à la fiche de la série ; le crédit et les sources sont des liens à part
     * (pas de lien dans un lien).
     *
     * Constructeurs : trois noms au plus, puis « et N autres » (buildersText) ; la liste complète est
     * sur la fiche de la série.
     *
     * @type {{ materials: Array<{ slug: string, name: string, type: string, builder: string|null,
     *   builders?: string[], in_service_from: number|null, in_service_to: number|null, periods?: Array<any>,
     *   relations?: Array<any>, end?: { kind: string, year: ?number, source_url: ?string }, image?: ?{ url: string, width: number, height: number, file: ?string,
     *   author: ?string, license: ?string, license_url: ?string, rights_status: ?string, source_url: ?string, fit?: string } }> }}
     */
    let { materials } = $props();

    const t = (k, o) => String($i18n.t(k, o));
    // Espaces entre un texte et un lien : Svelte retire ceux qui bordent un bloc {#if}.
    const SPACE = ' ';
    const LIST_SEPARATOR = ', ';
    const lang = $derived(formatLocale($i18n.language ?? 'fr'));
    const dated = $derived(materials.some((m) => m.periods?.length));

    /** Texte de remplacement : le nom du fichier Commons, qui décrit d'ordinaire la photo, sinon la série. */
    function altOf(m) {
        const file = String(m.image?.file ?? '')
            .replace(/^File:/, '')
            .replace(/\.[a-z0-9]+$/i, '')
            .replaceAll('_', ' ')
            .trim();
        return file || t('materials.image.alt', { name: m.name });
    }
</script>

{#snippet ext()}<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span
        class="vff-sr-only"
    >
        ({t('common.new_tab')})</span
    >{/snippet}

<SheetSection id="materials" title={t('lines.section.materials')}>
    <ul class="material-grid">
        {#each materials as m, i (m.slug)}
            {@const service = servicePeriodText(m.in_service_from, m.in_service_to, t)}
            {@const builders = buildersText(m.builders, m.builder, t)}
            {@const onLine = m.periods?.length ? periodsSummary(m.periods, t) : null}
            {@const relations = relationsList(m.relations, m.periods, t)}
            {@const notes = relationNotes(m.relations, t, lang)}
            <li class="vff-tile material-card">
                <Link class="card-link" href="/materials/{m.slug}">
                    <span class={['photo', { empty: !m.image }]}>
                        {#if m.image}
                            <img
                                class={{ contain: m.image.fit === 'contain' }}
                                src={commonsSrc(m.image.url)}
                                alt={altOf(m)}
                                width={m.image.width}
                                height={m.image.height}
                                loading={i < 3 ? undefined : 'lazy'}
                                decoding="async"
                            />
                        {:else}
                            <i class="bi bi-train-front" aria-hidden="true"></i>
                        {/if}
                    </span>
                    <strong>{m.name}</strong>
                </Link>
                {#if m.image}
                    <p class="credit">
                        {m.image.author ?? t('media.author_unknown')},
                        {#if mediaLicenseHref(m.image)}<a
                                href={mediaLicenseHref(m.image)}
                                target="_blank"
                                rel="noopener noreferrer license"
                                >{mediaLicenseLabel(m.image, t)}{@render ext()}</a
                            >{:else}{mediaLicenseLabel(
                                m.image,
                                t,
                            )}{/if}{#if mediaSourceHref(m.image)},
                            <a
                                href={mediaSourceHref(m.image)}
                                target="_blank"
                                rel="noopener noreferrer"
                                >{t('materials.image.via')}{@render ext()}</a
                            >{/if}
                    </p>
                {/if}
                <span class="meta">{t(`materials.type.${m.type}`)}</span>
                {#if builders}<span class="meta">{builders}</span>{/if}
                {#if service}<span class="meta">{service}</span>{/if}
                {#if m.end}<span class="meta end"
                        >{endText(m.end, t)}{#if m.end.source_url}{SPACE}(<a
                                href={m.end.source_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                >{t('materials.end.source')}{@render ext()}</a
                            >){/if}</span
                    >{/if}
                {#if onLine}<span class="on-line"
                        >{t('materials.periods.on_line', { periods: onLine })}</span
                    >{/if}
                {#if relations.length}<span class="on-line"
                        >{t('materials.relations.on_line', {
                            relations: relations.join(' ; '),
                        })}</span
                    >{/if}
                {#each notes as n (n.label + n.text)}
                    <p class="fact">
                        {t('materials.relations.notes.line', { label: n.label, text: n.text })}
                        {#if n.sources.length}<span class="sources"
                                >({t('materials.relations.notes.sources')}
                                {#each n.sources as s, j (s.url)}{#if j > 0}{LIST_SEPARATOR}{/if}<a
                                        href={s.url}
                                        target="_blank"
                                        rel="noopener noreferrer">{s.publisher}{@render ext()}</a
                                    >{/each})</span
                            >{/if}
                    </p>
                {/each}
            </li>
        {/each}
    </ul>
    {#if dated}
        <p class="note">{t('materials.periods.note')}</p>
    {/if}
</SheetSection>

<style>
    .material-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: var(--vff-space-4);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .material-card {
        display: flex;
        flex-direction: column;
        gap: 2px;
        height: 100%;
    }
    .material-card :global(.card-link) {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        color: inherit;
        text-decoration: none;
    }
    .material-card :global(.card-link:hover strong),
    .material-card :global(.card-link:focus-visible strong) {
        text-decoration: underline;
        text-decoration-color: var(--vff-accent-ink);
    }
    .photo {
        display: block;
        aspect-ratio: 3 / 2;
        overflow: hidden;
        border-radius: var(--vff-radius-sm);
        background-color: var(--vff-bg-subtle);
    }
    .photo img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    /* Vue de profil très large (Material::WIDE_PHOTO_RATIO) : l'engin entier, sur le fond de la vignette. */
    .photo img.contain {
        object-fit: contain;
    }
    .photo.empty {
        display: grid;
        place-items: center;
        font-size: 2rem;
        color: var(--vff-text-muted);
    }
    .credit {
        margin: 2px 0 var(--vff-space-2);
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-xs);
        line-height: 1.45;
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }
    .credit a,
    .fact a,
    .end a {
        color: inherit;
        text-decoration-color: var(--vff-border-strong);
    }
    .credit a:hover,
    .fact a:hover,
    .end a:hover {
        color: var(--vff-accent-ink);
    }
    .meta,
    .on-line,
    .fact {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        line-height: var(--vff-lh-ui);
        color: var(--vff-text-muted);
    }
    .on-line {
        margin-top: var(--vff-space-2);
        font-weight: 600;
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .fact {
        margin: var(--vff-space-2) 0 0;
        color: var(--vff-text);
        overflow-wrap: anywhere;
    }
    .fact .sources {
        color: var(--vff-text-muted);
    }
    .note {
        max-width: var(--vff-measure);
        margin: var(--vff-space-4) 0 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.6;
        color: var(--vff-text-muted);
    }
</style>
