<?php

namespace App\Services\Journal;

/**
 * Un article du journal de bord, dans une langue, tel que son fichier le donne
 * (`resources/content/journal/<slug>.<langue>.md`) : métadonnées de l'en-tête et texte Markdown.
 *
 * `locale` est la langue du texte servi ; `locales` les langues dans lesquelles l'article existe
 * (le français toujours, langue de référence). Quand la langue demandée manque, JournalRepository
 * sert le français : `locale` diffère alors de la langue de la page et l'interface le dit.
 */
final readonly class JournalArticle
{
    /**
     * @param  list<string>  $locales  langues disponibles, le français d'abord
     * @param  list<string>  $lines  codes RFN des lignes dont parle l'article (`878000`)
     * @param  list<string>  $stations  slugs des gares dont parle l'article
     * @param  array{src: string, alt: string, author: string, licence: string, licence_url: ?string, source_url: string, width: int, height: int, caption: ?string}|null  $image
     */
    public function __construct(
        public string $slug,
        public string $locale,
        public array $locales,
        public string $title,
        public string $date,
        public ?string $updated,
        public string $summary,
        public array $lines,
        public array $stations,
        public ?array $image,
        public ?string $disclosure,
        public string $body,
    ) {}

    /** Date de la dernière révision : celle de la mise à jour, sinon de la publication (AAAA-MM-JJ). */
    public function modified(): string
    {
        return $this->updated ?? $this->date;
    }
}
