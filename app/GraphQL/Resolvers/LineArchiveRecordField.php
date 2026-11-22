<?php

namespace App\GraphQL\Resolvers;

use App\Services\Archives\LineArchives;

/**
 * Champs calculés d'une notice de fonds d'archives en GraphQL (`LineArchiveRecord.attribution` et `provenance`),
 * mêmes valeurs que l'API REST (`GET /api/v1/lines/{line}/archive-records`). La liste `Line.archive_records` est
 * une relation chargée par lots (@hasMany).
 */
final class LineArchiveRecordField
{
    public function __construct(private readonly LineArchives $archives) {}

    public function attribution(): string
    {
        return LineArchives::RECORDS_ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->archives->recordsProvenance();
    }
}
