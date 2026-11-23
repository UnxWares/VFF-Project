<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\LineSheetResource;
use App\Http\Resources\StationSheetResource;
use App\Models\Line;
use App\Models\Station;
use App\Services\LineSheet;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingDating;
use App\Services\StationSheet;
use Illuminate\Http\Request;

/**
 * Champs `Line.sheet` et `Station.sheet` : la fiche complète, au même contrat que l'API REST
 * (`GET /api/v1/lines/{line}`, `GET /api/v1/stations/{station}`), construite par les mêmes services
 * que les pages web. Calcul lourd : à demander pour une ligne ou une gare à la fois.
 */
final class SheetResolver
{
    public function __construct(
        private readonly LineSheet $lines,
        private readonly StationSheet $stations,
        private readonly LineSignallingHistory $signallingHistory,
        private readonly SignallingDating $dating,
    ) {}

    /**
     * @param  array{geometry?: bool}  $args
     * @return array<string, mixed>
     */
    public function line(Line $line, array $args): array
    {
        // Même contenu que GET /api/v1/lines/{line} : l'historique daté de la signalisation compris.
        return LineSheetResource::make($this->lines->build($line) + ['signalling_history' => $this->signallingHistory->facts($line), 'signalling_dating' => $this->dating->forLine($line)])
            ->resolve(new Request(['geometry' => (bool) ($args['geometry'] ?? false)]));
    }

    /** @return array<string, mixed> */
    public function station(Station $station): array
    {
        return StationSheetResource::make($this->stations->page($station, withRaw: true, offSite: true))->resolve(new Request);
    }
}
