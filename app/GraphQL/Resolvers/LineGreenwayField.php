<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\Greenways\LineGreenways;

/**
 * Voies vertes d'une ligne en GraphQL : champs `Line.greenways` et `Line.greenway_length_m`, mêmes
 * valeurs que l'API REST (`GET /api/v1/lines/{line}/greenways`). Les voies vertes d'une même ligne
 * sont lues une fois par requête (clé faible : rien ne survit à la requête).
 */
final class LineGreenwayField
{
    /** @var \WeakMap<Line, list<array<string, mixed>>>|null */
    private static ?\WeakMap $perLine = null;

    public function __construct(private readonly LineGreenways $greenways) {}

    /** @return list<array<string, mixed>> */
    public function forLine(Line $line): array
    {
        return $this->groupsOf($line);
    }

    public function length(Line $line): int
    {
        return $this->greenways->totalLength($this->groupsOf($line));
    }

    public function attribution(): string
    {
        return LineGreenways::ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->greenways->provenance();
    }

    /** @return list<array<string, mixed>> */
    private function groupsOf(Line $line): array
    {
        self::$perLine ??= new \WeakMap;

        return self::$perLine[$line] ??= $this->greenways->groups($line);
    }
}
