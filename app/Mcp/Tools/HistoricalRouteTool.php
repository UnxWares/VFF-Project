<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Attribution;
use App\Mcp\Support\VffTool;
use App\Services\NetworkGraph\RouteException;
use App\Services\PathfindingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('historical_route')]
#[Title('Itinéraire historique entre deux gares')]
#[Description(<<<'TXT'
Plus court chemin ferroviaire entre deux gares sur le réseau en service à une époque (« comment aller de Rennes à Brest en 1930 ? »), calculé sur le graphe gare à gare de l'ère VFF (gares ouvertes et voies exploitées au 31/12 de l'année de l'ère ; une année quelconque est ramenée à l'ère qui la contient ou la précède).
Renvoie : ère utilisée, distance en km, durée indicative en heures (moyenne de 100 km/h, pas un horaire historique), nombre de sections gare à gare, lignes empruntées dans l'ordre (slug, nom, code, km parcourus, gares de début et de fin), gares traversées (slug, nom, statut à l'ère), km sur des voies sans service voyageurs et sur des voies associatives, changements d'écartement (mm) et avertissements (gap_joins = raccords entre tracés non jointifs).
Gares : slug (de préférence, via search), identifiant, nom exact ou nom de ville (« Paris » = toutes les gares parisiennes ouvertes à l'époque). Erreurs à code stable : era_not_found, graph_not_built, station_not_found, ambiguous_station (avec `candidates` à proposer), same_station, station_not_in_network, no_path.
Exemple : {"from": "rennes", "to": "brest", "year": 1930}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class HistoricalRouteTool extends VffTool
{
    /** Gares traversées renvoyées au plus (les extrémités sont toujours gardées). */
    public const MAX_STATIONS = 120;

    public function __construct(Attribution $attribution, private readonly PathfindingService $pathfinding)
    {
        parent::__construct($attribution);
    }

    protected function rules(): array
    {
        return [
            'from' => ['required', 'string', 'min:1', 'max:200'],
            'to' => ['required', 'string', 'min:1', 'max:200'],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
            'heritage' => ['sometimes', 'boolean'],
            'traffic' => ['sometimes', 'string', 'in:passenger,all'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->min(1)->max(200)->required()
                ->description('Gare de départ : slug (ex. "rennes"), nom exact ou nom de ville.'),
            'to' => $schema->string()->min(1)->max(200)->required()
                ->description('Gare d\'arrivée, mêmes formes que from (ex. "brest").'),
            'year' => $schema->integer()->min(1800)->max(2100)
                ->description('Année de 1800 à 2100 (ex. 1930) ; par défaut : réseau actuel.'),
            'heritage' => $schema->boolean()->default(false)
                ->description('Autoriser les voies exploitées par une association (train touristique). false par défaut.'),
            'traffic' => $schema->string()->enum(['passenger', 'all'])->default('passenger')
                ->description('passenger (par défaut) : les voies sans service voyageurs comptent 4 fois leur longueur ; all : toutes les voies au même coût.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        try {
            $r = $this->pathfinding->route($input['from'], $input['to'], $input['year'] ?? null, [
                'heritage' => (bool) ($input['heritage'] ?? false),
                'traffic' => $input['traffic'] ?? 'passenger',
            ]);
        } catch (RouteException $e) {
            return $this->fail($e->errorCode, $e->details + ['from' => $input['from'], 'to' => $input['to'], 'year' => $input['year'] ?? ''],
                array_intersect_key($e->details, array_flip(['candidates', 'side', 'station', 'status', 'era_year', 'first_year'])));
        }

        $stations = array_map(fn (array $s) => ['slug' => $s['slug'], 'name' => $s['name'], 'status' => $s['status']], $r['stations']);
        $omitted = 0;
        if (count($stations) > self::MAX_STATIONS) {
            $omitted = count($stations) - self::MAX_STATIONS;
            $stations = [...array_slice($stations, 0, self::MAX_STATIONS - 1), end($stations)];
        }
        $lineSlugs = array_values(array_filter(array_column($r['lines'], 'line_slug')));
        $providers = DB::table('line_segments')->join('lines', 'lines.id', '=', 'line_segments.line_id')
            ->whereIn('lines.slug', $lineSlugs)->distinct()->pluck('line_segments.source')
            ->map(fn ($s) => $this->attribution->providerFor('line_segments', $s))->all();
        $year = $r['era_year'];

        return $this->respond([
            'from' => $r['from'],
            'to' => $r['to'],
            'requested_year' => $r['requested_year'],
            'era_year' => $year,
            'distance_km' => $r['distance_km'],
            'duration_h' => $r['duration_h'],
            'duration_note' => (string) trans('mcp.route.duration_note', [], $lang),
            'hops' => $r['hops'],
            'lines' => array_map(fn (array $l) => [
                'slug' => $l['line_slug'], 'name' => $l['line_name'], 'code' => $l['line_code'], 'km' => $l['length_km'],
                'from' => $l['from_name'], 'to' => $l['to_name'],
            ], $r['lines']),
            'stations' => $stations,
            'stations_omitted' => $omitted,
            'non_passenger_km' => round($r['non_passenger_m'] / 1000, 1),
            'heritage_km' => round($r['heritage_m'] / 1000, 1),
            'gauge_changes' => $r['gauge_changes'],
            'warnings' => $r['warnings'],
        ], $providers, $lang, self::url('/maps?'.http_build_query(['from' => $r['from']['slug'], 'to' => $r['to']['slug'], 'year' => $year])),
            ['stations']);
    }
}
