<?php

namespace App\Services\Archives;

/**
 * Notice d'un document numérisé, lue dans l'API SRU du catalogue général de la BnF (format UNIMARC) :
 * seulement les métadonnées reprises par VFF (Licence Ouverte), jamais le document lui-même.
 *
 * `matchText` : texte sur lequel le rattachement est jugé (titre propre et compléments du titre de la
 * zone 200), alors que l'API cherche aussi dans les titres de collection ou de recueil.
 */
final readonly class ArchiveRecord
{
    /**
     * @param  list<string>  $authors
     */
    public function __construct(
        public string $gallicaArk,
        public ?string $catalogueArk,
        public string $title,
        public string $matchText,
        public ?string $docType,
        public array $authors,
        public ArchiveDate $date,
        public ?string $shelfmark,
        public ?string $recordUpdatedOn,
    ) {}
}
