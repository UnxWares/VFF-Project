<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\Rinf\RinfParameterComparison;

/**
 * Paramètres RINF d'une ligne comparés à l'autre source de VFF, en GraphQL (`Line.rinf_parameters`) : même contenu
 * que l'API REST (`GET /api/v1/lines/{line}/rinf-parameters`), sans le bloc `line`.
 */
final class LineRinfParametersField
{
    public function __construct(private readonly RinfParameterComparison $comparison) {}

    /** @return array<string, mixed> */
    public function __invoke(Line $line): array
    {
        return $this->comparison->forLine($line);
    }
}
