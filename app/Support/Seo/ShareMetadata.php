<?php

namespace App\Support\Seo;

/**
 * Ce que le serveur écrit dans `<head>` d'une page publique pour les aperçus de partage : titre de
 * l'onglet, descriptions (`metaDescription` pour les moteurs de recherche, `description` abrégée à
 * 200 caractères pour les aperçus), adresse canonique, image et données structurées déjà sérialisées.
 */
final readonly class ShareMetadata
{
    /**
     * @param  'website'|'article'  $type
     * @param  array{url: string, alt: ?string}|null  $image  adresse absolue
     * @param  list<string>  $jsonLd  objets JSON-LD sérialisés (voir StructuredData::serialize)
     */
    public function __construct(
        public string $documentTitle,
        public string $title,
        public string $metaDescription,
        public string $description,
        public string $url,
        public string $type,
        public string $locale,
        public string $siteName,
        public ?array $image,
        public array $jsonLd,
    ) {}
}
