<?php

namespace App\Services\Archives\Sncf;

use App\Services\Archives\ArchiveDate;
use App\Services\Archives\ArchiveRecord;

/**
 * Notice du jeu de données « Archives SNCF » (SARDO, ressources.data.sncf.com, ODbL), réduite à ce que VFF
 * reprend : titre, type, classement, service versant, boîte, titulaire des droits, date et lien vers
 * archives.sncf.com. La description n'est ni gardée ni servie (elle peut nommer des agents) : `descriptionText` n'en est que
 * le texte préparé pour juger le rattachement, en mémoire.
 *
 * `matchText` : titre sans les noms de compagnies (SardoParser::withoutCompanies), le mot « ligne » répété
 * dans les listes de lignes (SardoParser::listedLines) ; le rattachement est jugé sur ce texte.
 */
final readonly class SardoRecord
{
    public function __construct(
        public string $url,
        public ?string $reference,
        public string $title,
        public string $matchText,
        public ?string $descriptionText,
        public string $docType,
        public ?string $theme,
        public ?string $subTheme,
        public ?string $holder,
        public ?string $box,
        public ?string $rights,
        public ArchiveDate $date,
    ) {}

    /**
     * Forme lue par ArchiveMatcher, qui ne juge que `matchText`, la date et le type ; l'identifiant (champ
     * `gallicaArk`, propre à Gallica) reçoit le lien de la notice, que le comparateur ne lit pas.
     */
    public function toArchiveRecord(bool $description = false): ArchiveRecord
    {
        return new ArchiveRecord($this->url, null, $this->title, $description ? (string) $this->descriptionText : $this->matchText, $this->docType, [], $this->date, $this->reference, null);
    }
}
