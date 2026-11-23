<script>
    /**
     * Une image d'archives de Wikimedia Commons (prop `archives` de HomeController) : photo cadrée,
     * année (ou décennie), légende, ligne rattachée, auteur, licence et lien vers la page Commons.
     * Sert à la galerie et à l'image de la Genèse.
     */
    import { Link } from '@inertiajs/svelte';
    import { commonsSrc } from '$lib/commonsImage.js';
    import { i18n } from '$lib/i18n.js';
    import { decadeOf } from '$lib/typo.js';
    import { conciseAlt, figureLabel } from '$lib/altText.js';

    /** @type {{ media: { id: number, year: number, decade: boolean, caption: string, author: string|null, license: string, license_url: string|null, source_url: string, thumb_url: string, display_url: string|null, line: { slug: string, name: string } }, sizes?: string, loading?: 'lazy'|'eager' }} */
    let {
        media,
        sizes = '(max-width: 600px) calc(100vw - 32px), (max-width: 900px) 45vw, 380px',
        loading = 'lazy',
    } = $props();

    const yearText = $derived(
        media.decade
            ? $i18n.t('home.archives.decade', { decade: decadeOf(media.year, $i18n.language) })
            : String(media.year),
    );
    // RGAA 1.9 : la figure reprend sa légende (aria-label sur le figure) ; l'alt reste court (1.3.9).
    const legend = $derived(
        figureLabel([
            yearText,
            media.caption,
            media.line.name,
            media.author || $i18n.t('home.archives.author_unknown'),
            media.license,
            $i18n.t('home.archives.source'),
        ]),
    );
</script>

<figure class="vff-figure archive" aria-label={legend}>
    <img
        src={commonsSrc(media.thumb_url)}
        srcset={media.display_url
            ? `${commonsSrc(media.thumb_url)} 500w, ${commonsSrc(media.display_url)} 1280w`
            : undefined}
        {sizes}
        alt={conciseAlt(media.caption)}
        width="600"
        height="400"
        {loading}
        decoding="async"
    />
    <figcaption>
        <span class="year">{yearText}</span>
        <span class="caption">{media.caption}</span>
        <Link class="vff-link" href={`/lines/${media.line.slug}`}
            ><i class="bi bi-signpost-split" aria-hidden="true"></i>
            {media.line.name}</Link
        >
        <span class="credit">
            {media.author || $i18n.t('home.archives.author_unknown')},
            {#if media.license_url}<a
                    href={media.license_url}
                    target="_blank"
                    rel="noopener noreferrer license"
                    >{media.license}<span class="vff-sr-only">
                        ({$i18n.t('common.new_tab')})</span
                    ></a
                >{:else}{media.license}{/if},
            <a href={media.source_url} target="_blank" rel="noopener noreferrer"
                >{$i18n.t('home.archives.source')}<i
                    class="bi bi-box-arrow-up-right vff-ext"
                    aria-hidden="true"
                ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span></a
            >
        </span>
    </figcaption>
</figure>

<style>
    figcaption {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        margin-top: var(--vff-space-3);
    }
    .year {
        font-family: var(--vff-font-display);
        font-size: 1.25rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
        color: var(--vff-text);
    }
    .caption {
        font-family: var(--vff-font-body);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        overflow-wrap: anywhere;
        color: var(--vff-text);
    }
    /* La règle commune `.vff-figure figcaption a` grise les liens du crédit : le lien vers la ligne
       garde sa couleur de lien. */
    figcaption :global(.vff-link) {
        margin-top: 2px;
        color: var(--vff-link);
    }
    .credit {
        font-size: var(--vff-fs-xs);
        line-height: 1.5;
        overflow-wrap: anywhere;
        color: var(--vff-text-muted);
    }
    .credit a {
        color: inherit;
        text-decoration: underline;
        text-underline-offset: 0.15em;
    }
    .credit a:hover {
        color: var(--vff-link);
    }
</style>
