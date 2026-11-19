<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\LineSheetResource;
use App\Http\Resources\StationSheetResource;
use App\Models\Line;
use App\Models\Station;
use App\Services\LineSheet;
use App\Services\StationSheet;
use Illuminate\Http\Request;

/**
 * Champs `Line.sheet` et `Station.sheet` : la fiche complète, au même contrat que l'API REST
 * (`GET /api/v1/lines/{line}`, `GET /api/v1/stations/{station}`), construite par les mêmes services
 * que les pages web. Calcul lourd : à demander pour une ligne ou une gare à la fois.
 */
final class SheetResolver
{
    public function __construct(private readonly LineSheet $lines, private readonly StationSheet $stations) {}

    /**
     * @param  array{geometry?: bool}  $args
     * @return array<string, mixed>
     */
    public function line(Line $line, array $args): array
    {
        return LineSheetResource::make($this->lines->build($line))
            ->resolve(new Request(['geometry' => (bool) ($args['geometry'] ?? false)]));
    }

    /** @return array<string, mixed> */
    public function station(Station $station): array
    {
        return StationSheetResource::make($this->stations->page($station))->resolve(new Request);
    }
}
