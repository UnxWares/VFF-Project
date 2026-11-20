<?php

namespace App\GraphQL\Resolvers;

use App\Services\Archives\LineArchives;

/**
 * Champs calculés d'un document d'archives en GraphQL (`LineArchiveDocument.attribution` et `provenance`),
 * mêmes valeurs que l'API REST (`GET /api/v1/lines/{line}/archives`). La liste `Line.archives` est une
 * relation chargée par lots (@hasMany).
 */
final class LineArchiveField
{
    public function __construct(private readonly LineArchives $archives) {}

    public function attribution(): string
    {
        return LineArchives::ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->archives->provenance();
    }
}
