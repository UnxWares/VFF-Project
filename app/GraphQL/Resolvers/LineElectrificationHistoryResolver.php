<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\Traction\LineElectrificationHistory;

class LineElectrificationHistoryResolver
{
    /**
     * Histoire de l'électrification d'une ligne (même lecture que l'API REST `/lines/{line}/electrification-history`).
     *
     * @return array<string, mixed>
     */
    public function __invoke(Line $line): array
    {
        return app(LineElectrificationHistory::class)->forLine($line);
    }
}
