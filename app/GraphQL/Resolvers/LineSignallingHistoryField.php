<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\Signalling\LineSignallingHistory;

/**
 * Historique de la signalisation d'une ligne en GraphQL : champ `Line.signalling_history`, mêmes faits que
 * l'API REST (`GET /api/v1/lines/{line}/signalling-history`). Les faits d'une même ligne sont lus une fois
 * par requête (clé faible : rien ne survit à la requête).
 */
final class LineSignallingHistoryField
{
    /** @var \WeakMap<Line, list<array<string, mixed>>>|null */
    private static ?\WeakMap $perLine = null;

    public function __construct(private readonly LineSignallingHistory $history) {}

    /** @return list<array<string, mixed>> */
    public function forLine(Line $line): array
    {
        self::$perLine ??= new \WeakMap;

        return self::$perLine[$line] ??= $this->history->facts($line);
    }

    public function attribution(): string
    {
        return LineSignallingHistory::ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->history->provenance();
    }
}
