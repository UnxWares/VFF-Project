<script>
    /**
     * Balises de partage (Open Graph, Twitter) et données structurées schema.org (JSON-LD) d'une
     * page publique, posées dans <head>. Le <title> et la meta description restent dans la page.
     *
     *   <SeoHead
     *       title={line.name}
     *       description={metaDescription}
     *       path={`/lines/${line.slug}`}
     *       image={{ url: m.display_url, alt: m.caption }}
     *       jsonLd={(origin) => [lineSchema({ origin, line }), breadcrumbSchema(origin, crumbs)]}
     *   />
     *
     * `jsonLd` reçoit l'origine du site (adresse absolue exigée par schema.org) et rend un objet
     * ou une liste d'objets (lib/structuredData.js). `type` : « website » (défaut) ou « article ».
     * Le texte JSON est échappé par serializeJsonLd : aucune balise fermante possible.
     */
    import { i18n } from '$lib/i18n.js';
    import { absoluteUrl, ogLocale, serializeJsonLd, shorten } from '$lib/structuredData.js';

    /** @type {{ title: string, description?: string|null, path: string, type?: 'website'|'article', image?: { url: string, alt?: string|null }|null, jsonLd?: ((origin: string) => object|object[])|null }} */
    let {
        title,
        description = null,
        path,
        type = 'website',
        image = null,
        jsonLd = null,
    } = $props();

    // Pas de rendu serveur : la page tourne dans le navigateur, son origine est l'adresse du site.
    const origin = typeof location === 'undefined' ? 'https://vff-project.org' : location.origin;
    // Balise dynamique : un <script> écrit tel quel dans le gabarit serait pris pour celui du composant.
    const SCRIPT = 'script';

    const lang = $derived($i18n.language ?? 'fr');
    const desc = $derived(shorten(description, 200));
    const blocks = $derived.by(() => {
        if (!jsonLd) return [];
        const data = jsonLd(origin);
        return (Array.isArray(data) ? data : [data]).map((d) => serializeJsonLd(d));
    });
</script>

<svelte:head>
    <meta property="og:site_name" content={$i18n.t('common.app_full')} />
    <meta property="og:type" content={type} />
    <meta property="og:title" content={title} />
    {#if desc}
        <meta property="og:description" content={desc} />
        <meta name="twitter:description" content={desc} />
    {/if}
    <meta property="og:url" content={absoluteUrl(origin, path)} />
    <meta property="og:locale" content={ogLocale(lang)} />
    <meta name="twitter:card" content={image ? 'summary_large_image' : 'summary'} />
    <meta name="twitter:title" content={title} />
    {#if image}
        <meta property="og:image" content={absoluteUrl(origin, image.url)} />
        <meta name="twitter:image" content={absoluteUrl(origin, image.url)} />
        {#if image.alt}
            <meta property="og:image:alt" content={image.alt} />
            <meta name="twitter:image:alt" content={image.alt} />
        {/if}
    {/if}
    {#each blocks as json (json)}
        <svelte:element this={SCRIPT} type="application/ld+json">{json}</svelte:element>
    {/each}
</svelte:head>
