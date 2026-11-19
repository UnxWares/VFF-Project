<?php

namespace App\GraphQL\Resolvers;

use App\Models\LineEvent;
use App\Models\LineSegment;
use App\Models\StationEvent;
use App\Services\Api\Provenance;

/**
 * Champ `provenance` d'un événement ou d'un tronçon : fournisseur de la donnée et sa licence (registre des droits).
 */
final class ProvenanceResolver
{
    public function __construct(private readonly Provenance $provenance) {}

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function __invoke(LineEvent|StationEvent|LineSegment $item): array
    {
        return match (true) {
            $item instanceof LineEvent => $this->provenance->for('line_events', $item->source, $item->source_url),
            $item instanceof StationEvent => $this->provenance->for('station_events', $item->source, $item->source_url),
            default => $this->provenance->for('line_segments', $item->source),
        };
    }
}
