<?php

namespace App\Services\Archives;

/**
 * Ce qu'il faut savoir d'une ligne pour lui rattacher un document d'archives : ses lieux (communes et
 * noms des gares, extrémités de son nom actuel et de ses anciens noms), ses couples d'extrémités et les
 * années de sa vie (première date connue, dernière fermeture, en service aujourd'hui ou non).
 */
final readonly class LinePlaces
{
    /**
     * @param  array<string, string>  $places  clé (TitlePlaces::key) → nom lisible
     * @param  list<array{0: list<string>, 1: list<string>}>  $endPairs  couples d'extrémités (clés de chaque bout)
     * @param  list<array{0: string, 1: string}>  $queryPairs  couples de termes de recherche (noms d'extrémités)
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public array $places,
        public array $endPairs,
        public array $queryPairs,
        public ?int $firstYear,
        public ?int $lastYear,
        public bool $openToday,
    ) {}

    /** Les deux lieux sont-ils les deux bouts d'un même couple d'extrémités de la ligne ? */
    public function areEnds(string $a, string $b): bool
    {
        foreach ($this->endPairs as [$x, $y]) {
            if ((in_array($a, $x, true) && in_array($b, $y, true)) || (in_array($a, $y, true) && in_array($b, $x, true))) {
                return true;
            }
        }

        return false;
    }
}
