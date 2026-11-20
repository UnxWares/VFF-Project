<?php

namespace App\Services\Archives;

/**
 * Jugement d'un document d'archives pour une ligne : score, raisons point par point, lieux de la ligne
 * nommés dans le titre, et motif d'exclusion quand une condition obligatoire manque (`rejected`).
 */
final readonly class ArchiveMatch
{
    /**
     * @param  list<array{code: string, points: int}>  $reasons
     * @param  list<string>  $places  noms lisibles des lieux reconnus
     * @param  list<string>  $keys  clés de ces lieux (TitlePlaces::key), pour comparer les lignes entre elles
     * @param  ?int  $lineSince  première année connue de la ligne (départage des documents sans date)
     */
    public function __construct(
        public int $lineId,
        public int $score,
        public array $reasons,
        public array $places,
        public ?string $rejected = null,
        public array $keys = [],
        public ?int $lineSince = null,
    ) {}

    public function has(string $code): bool
    {
        return in_array($code, array_column($this->reasons, 'code'), true);
    }

    public function passes(int $threshold): bool
    {
        return $this->rejected === null && $this->score >= $threshold;
    }
}
