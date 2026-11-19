<?php

namespace App\GraphQL\Resolvers;

use App\Models\LevelCrossing;
use App\Models\Line;
use App\Services\Api\Provenance;
use App\Services\LevelCrossings\LineLevelCrossings;
use Illuminate\Support\Collection;

/**
 * Passages à niveau en GraphQL : champ `Line.level_crossings` et champs calculés du type
 * `LevelCrossing`, mêmes valeurs que l'API REST (LevelCrossingResource).
 */
final class LevelCrossingField
{
    /**
     * PN déjà lus, par objet ligne : `level_crossings` et `level_crossing_summary` d'une même ligne
     * dans une requête ne lisent la base qu'une fois. La clé est l'instance du modèle (faible),
     * donc rien ne survit à la requête et aucune donnée périmée n'est servie à la suivante.
     *
     * @var \WeakMap<Line, Collection<int, LevelCrossing>>|null
     */
    private static ?\WeakMap $perLine = null;

    public function __construct(
        private readonly LineLevelCrossings $crossings,
        private readonly Provenance $provenance,
    ) {}

    /** @return Collection<int, LevelCrossing> */
    public function forLine(Line $line): Collection
    {
        return $this->crossingsOf($line);
    }

    /** @return list<array{group: string, count: int}> */
    public function summary(Line $line): array
    {
        return $this->crossings->summary($this->crossingsOf($line));
    }

    /** @return Collection<int, LevelCrossing> */
    private function crossingsOf(Line $line): Collection
    {
        self::$perLine ??= new \WeakMap;

        return self::$perLine[$line] ??= $this->crossings->list($line);
    }

    public function classNumber(LevelCrossing $crossing): ?int
    {
        return $crossing->class;
    }

    /** @param array{lang?: string} $args */
    public function classLabel(LevelCrossing $crossing, array $args): ?string
    {
        $lang = in_array($args['lang'] ?? 'fr', ['fr', 'en', 'de'], true) ? ($args['lang'] ?? 'fr') : 'fr';

        return $crossing->classLabel($lang);
    }

    public function group(LevelCrossing $crossing): ?string
    {
        return $crossing->group?->value;
    }

    public function pkLabel(LevelCrossing $crossing): ?string
    {
        return LevelCrossing::pkLabel($crossing->pk_m);
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('sncf_level_crossings', 'sncf');
    }
}
