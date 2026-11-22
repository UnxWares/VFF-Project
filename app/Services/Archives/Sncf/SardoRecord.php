<?php

namespace App\Services\Archives\Sncf;

use App\Services\Archives\ArchiveDate;
use App\Services\Archives\ArchiveRecord;

/**
 * Notice du jeu de données « Archives SNCF » (SARDO, ressources.data.sncf.com, ODbL), réduite à ce que VFF
 * reprend : titre, type, classement, service versant, boîte, titulaire des droits, date et lien vers
 * archives.sncf.com. La description n'est pas gardée : elle peut nommer des agents.
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
    public function toArchiveRecord(): ArchiveRecord
    {
        return new ArchiveRecord($this->url, null, $this->title, $this->matchText, $this->docType, [], $this->date, $this->reference, null);
    }
}
