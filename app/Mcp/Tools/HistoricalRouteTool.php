<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Attribution;
use App\Mcp\Support\VffTool;
use App\Services\NetworkGraph\RouteException;
use App\Services\PathfindingService;
use App\Services\RouteComparison;
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
Comparaison entre deux époques : ajoutez compare_year. La réponse range les deux époques dans l'ordre chronologique (earlier, later) ; pour chacune, available, l'itinéraire résumé ou unavailable (no_path, station_not_in_network) ; puis differences : outcome (both, lost = trajet devenu impossible, gained, neither), distance_change_km et distance_change_percent, same_path, shared_lines, lines_only_earlier et lines_only_later (avec other_era : open, partly_open, closed ou none à l'autre époque), shared_stations. Erreur supplémentaire : same_era.
Exemples : {"from": "rennes", "to": "brest", "year": 1930} ; {"from": "angouleme", "to": "limoges-benedictins", "year": 1930, "compare_year": 2026}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class HistoricalRouteTool extends VffTool
{
    /** Gares traversées renvoyées au plus (les extrémités sont toujours gardées). */
    public const MAX_STATIONS = 120;

    public function __construct(
        Attribution $attribution,
        private readonly PathfindingService $pathfinding,
        private readonly RouteComparison $comparison,
    ) {
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
            'compare_year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
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
            'compare_year' => $schema->integer()->min(1800)->max(2100)
                ->description('Seconde année (ex. 2026) pour comparer le même trajet à deux époques : la réponse donne alors earlier et later (itinéraire ou raison de son absence) et differences. Doit tomber dans une autre ère que year.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $options = ['heritage' => (bool) ($input['heritage'] ?? false), 'traffic' => $input['traffic'] ?? 'passenger'];
        if (isset($input['compare_year'])) {
            return $this->compare($input, $options, $lang);
        }
        try {
            $r = $this->pathfinding->route($input['from'], $input['to'], $input['year'] ?? null, $options);
        } catch (RouteException $e) {
            return $this->failRoute($e, $input);
        }

        return $this->respond([
            'from' => $r['from'],
            'to' => $r['to'],
            'requested_year' => $r['requested_year'],
            ...$this->summary($r, $lang),
        ], $this->providers([$r]), $lang, self::mapUrl($r['from']['slug'], $r['to']['slug'], $r['era_year']), ['stations']);
    }

    /**
     * Même trajet à deux époques (RouteComparison) : itinéraire résumé de chacune, ou la raison
     * de son absence (code et message dans la langue demandée), puis les différences.
     *
     * @param  array<string, mixed>  $input
     * @param  array{heritage: bool, traffic: string}  $options
     */
    private function compare(array $input, array $options, string $lang): Response
    {
        try {
            $c = $this->comparison->compare($input['from'], $input['to'], $input['year'] ?? null, (int) $input['compare_year'], $options);
        } catch (RouteException $e) {
            return $this->failRoute($e, $input);
        }

        $era = function (array $side) use ($input, $lang): array {
            $base = ['requested_year' => $side['requested_year'], 'era_year' => $side['era_year'], 'available' => $side['route'] !== null];
            if ($side['route'] !== null) {
                return $base + array_diff_key($this->summary($side['route'], $lang), ['era_year' => true]);
            }
            $reason = $side['unavailable'];

            return $base + ['unavailable' => [
                'error' => $reason['error'],
                'message' => (string) trans('mcp.errors.'.$reason['error'], ['from' => $input['from'], 'to' => $input['to']], $lang),
            ] + array_intersect_key($reason, array_flip(['side', 'station', 'status']))];
        };
        $d = $c['differences'];
        $line = fn (array $l): array => ['slug' => $l['line_slug'], 'name' => $l['line_name'], 'code' => $l['line_code'], 'km' => $l['length_km']]
            + (array_key_exists('other_era', $l) ? ['other_era' => $l['other_era']] : []);
        $url = $c['from'] && $c['to']
            ? self::mapUrl($c['from']['slug'], $c['to']['slug'], $c['earlier']['era_year'], $c['later']['era_year'])
            : null;

        return $this->respond([
            'from' => $c['from'],
            'to' => $c['to'],
            'earlier' => $era($c['earlier']),
            'later' => $era($c['later']),
            'differences' => [
                'outcome' => $d['outcome'],
                'distance_change_km' => $d['distance_change_km'],
                'distance_change_percent' => $d['distance_change_percent'],
                'same_path' => $d['same_path'],
                'shared_lines' => array_map($line, $d['shared_lines']),
                'lines_only_earlier' => array_map($line, $d['lines_only_earlier']),
                'lines_only_later' => array_map($line, $d['lines_only_later']),
                'shared_stations' => $d['shared_stations'],
            ],
        ], $this->providers(array_filter([$c['earlier']['route'], $c['later']['route']])), $lang, $url,
            ['earlier.stations', 'later.stations']);
    }

    /**
     * Itinéraire résumé pour un assistant : chiffres, lignes, gares (au plus MAX_STATIONS).
     *
     * @param  array<string, mixed>  $r  réponse de PathfindingService::route()
     * @return array<string, mixed>
     */
    private function summary(array $r, string $lang): array
    {
        $stations = array_map(fn (array $s) => ['slug' => $s['slug'], 'name' => $s['name'], 'status' => $s['status']], $r['stations']);
        $omitted = 0;
        if (count($stations) > self::MAX_STATIONS) {
            $omitted = count($stations) - self::MAX_STATIONS;
            $stations = [...array_slice($stations, 0, self::MAX_STATIONS - 1), end($stations)];
        }

        return [
            'era_year' => $r['era_year'],
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
        ];
    }

    /**
     * Fournisseurs des tracés des lignes empruntées.
     *
     * @param  array<array<string, mixed>>  $routes
     * @return list<?string>
     */
    private function providers(array $routes): array
    {
        $lineSlugs = array_values(array_unique(array_filter(array_merge(...array_map(
            fn (array $r): array => array_column($r['lines'], 'line_slug'), array_values($routes),
        )))));
        if ($lineSlugs === []) {
            return [];
        }

        return DB::table('line_segments')->join('lines', 'lines.id', '=', 'line_segments.line_id')
            ->whereIn('lines.slug', $lineSlugs)->distinct()->pluck('line_segments.source')
            ->map(fn ($s) => $this->attribution->providerFor('line_segments', $s))->values()->all();
    }

    /** @param  array<string, mixed>  $input */
    private function failRoute(RouteException $e, array $input): Response
    {
        return $this->fail($e->errorCode, $e->details + ['from' => $input['from'], 'to' => $input['to'], 'year' => $e->details['era_year'] ?? $input['year'] ?? ''],
            array_intersect_key($e->details, array_flip(['candidates', 'side', 'station', 'status', 'era_year', 'first_year'])));
    }

    /** Lien vers l'itinéraire sur la carte de VFF (avec la seconde époque pour une comparaison). */
    private static function mapUrl(string $from, string $to, int $year, ?int $compare = null): string
    {
        return self::url('/maps?'.http_build_query(array_filter(['from' => $from, 'to' => $to, 'year' => $year, 'compare_year' => $compare])));
    }
}
