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
    public function __construct(
        private readonly LineLevelCrossings $crossings,
        private readonly Provenance $provenance,
    ) {}

    /** @return Collection<int, LevelCrossing> */
    public function forLine(Line $line): Collection
    {
        return $this->crossings->list($line);
    }

    /** @return list<array{group: string, count: int}> */
    public function summary(Line $line): array
    {
        return $this->crossings->summary($this->crossings->list($line));
    }

    public function classNumber(LevelCrossing $crossing): ?int
    {
        return $crossing->class;
    }

    /** @param array{lang?: string} $args */
    public function classLabel(LevelCrossing $crossing, array $args): ?string
    {
        $lang = in_array($args['lang'] ?? 'fr', ['fr', 'en', 'de'], true) ? ($args['lang'] ?? 'fr') : 'fr';

        return $crossing->class === null ? null : __("level_crossings.classes.{$crossing->class}", [], $lang);
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
