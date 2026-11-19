<script>
    /**
     * Description, adresse canonique, balises de partage (Open Graph, Twitter) et données structurées
     * schema.org (JSON-LD) d'une page publique, posées dans <head>. Le <title> reste dans la page.
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
     *
     * Au premier affichage d'une page, le serveur a déjà écrit les mêmes balises (partials/share-tags,
     * attribut data-vff-seo : les robots qui n'exécutent pas le JavaScript ne lisent que celles-là) :
     * le composant n'en ajoute alors aucune et laisse celles du serveur. Après une navigation interne
     * ou un changement de langue, il prend le relais et retire les balises du serveur devenues fausses
     * (lib/serverSeo.js).
     */
    import { i18n } from '$lib/i18n.js';
    import { dropStaleServerSeo, serverSeoCovers } from '$lib/serverSeo.js';
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
    // Le serveur a-t-il déjà écrit les balises de cette page, dans cette langue ?
    const servedByServer = $derived(serverSeoCovers(path, ogLocale(lang)));
    const desc = $derived(shorten(description, 200));
    const blocks = $derived.by(() => {
        if (!jsonLd) return [];
        const data = jsonLd(origin);
        return (Array.isArray(data) ? data : [data]).map((d) => serializeJsonLd(d));
    });

    // Navigation interne ou changement de langue : les balises du serveur ne sont plus celles de la page.
    $effect(() => {
        dropStaleServerSeo(path, ogLocale(lang));
    });
</script>

<svelte:head>
    {#if !servedByServer}
        {#if description}
            <meta name="description" content={description} />
        {/if}
        <link rel="canonical" href={absoluteUrl(origin, path)} />
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
    {/if}
</svelte:head>
