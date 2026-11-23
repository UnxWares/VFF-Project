<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\LineStations;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_stops')]
#[Title("Gares d'une ligne à une époque (GTFS)")]
#[Description(<<<'TXT'
Gares et haltes d'une ligne à une époque de la carte, dans l'ordre des points kilométriques, avec l'état de chacune à cette époque : mêmes données que l'API REST GET /api/v1/lines/{line}/stops, sur le modèle de GTFS `stop_times.txt` (sans horaires : VFF décrit l'infrastructure, pas les services). Répond à « quelles gares desservait la ligne en 1930 ? ».
Une année quelconque est ramenée à l'ère qui la contient ou la précède (1937 donne 1930), comme network_at_era ; sans `year` : le réseau d'aujourd'hui. `era` donne l'ère retenue (`snapshot` : 31/12 de l'année de l'ère, ou aujourd'hui).
Chaque arrêt (`stops`) : `position` (rang sur la ligne), `pk_m` (point kilométrique en mètres : PK officiel de SNCF Réseau quand il est connu, sinon estimé sur le tracé et calé sur les PK voisins ; équivaut au `shape_dist_traveled` de GTFS) et `pk_label` (km+mmm), `status` à cette ère (active = desservie par des trains de voyageurs, tourist = train touristique, no_commercial = fret seul, declassified = fermée ; les gares fermées sont comprises) et `status_label`, `is_terminal`, `station` (slug, name, type, city, lat, lng en WGS 84, `url` de la fiche). `counts` : nombre d'arrêts par état.
`gtfs` : lien de l'archive GTFS statique de toute l'ère (agency, routes, stops, trips, stop_times aux horaires synthétiques à 60 km/h, shapes des seules lignes à tracé redistribuable, attributions ; ODbL 1.0) à citer comme « VFF - Voies Ferrées de France ».
Liste vide (`stops: []`) quand l'ordre des gares de la ligne n'est pas calculé pour cette ère : la fiche (get_line, `stations`) reste la référence. Réponse plafonnée en taille : `truncated` le signale.
Exemples : {"line": "rfn-420000"} ; {"line": "610000", "year": 1930, "only_open": true, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineStopsTool extends VffTool
{
    /** États où une gare est ouverte à cette ère (voyageurs, train touristique, fret seul). */
    public const OPEN_STATUSES = ['active', 'tourist', 'no_commercial'];

    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'year' => ['sometimes', 'integer', 'min:1800', 'max:2100'],
            'only_open' => ['sometimes', 'boolean'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-420000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "420000").'),
            'year' => $schema->integer()->min(1800)->max(2100)
                ->description('Année quelconque de 1800 à 2100 (ex. 1930), ramenée à l\'ère de la carte ; par défaut le réseau d\'aujourd\'hui.'),
            'only_open' => $schema->boolean()->default(false)
                ->description('true : seulement les gares ouvertes à cette ère (active, tourist, no_commercial) ; false (par défaut) : toutes.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $era = Lookup::era(isset($input['year']) ? (int) $input['year'] : null);
        if ($era === null) {
            return $this->fail('era_not_found', ['year' => $input['year'] ?? ''], ['available_eras' => Lookup::eraYears()]);
        }
        $onlyOpen = filter_var($input['only_open'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $rows = DB::table('line_stops as ls')
            ->join('stations as s', 's.id', '=', 'ls.station_id')
            ->where('ls.line_id', $line->id)->where('ls.era_id', $era->id)
            ->when($onlyOpen, fn ($q) => $q->whereIn('ls.effective_status', self::OPEN_STATUSES))
            ->orderBy('ls.position')
            ->get(['ls.position', 'ls.pk_m', 'ls.effective_status', 'ls.is_terminal', 's.slug', 's.name', 's.type', 's.city', 's.position_source',
                DB::raw('ST_X(s.geom) AS lng'), DB::raw('ST_Y(s.geom) AS lat')]);

        $counts = [];
        $stops = [];
        foreach ($rows as $r) {
            $status = (string) $r->effective_status;
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $stops[] = [
                'position' => (int) $r->position,
                'pk_m' => $r->pk_m === null ? null : (int) $r->pk_m,
                'pk_label' => $r->pk_m === null ? null : LineStations::pkLabel((int) $r->pk_m),
                'status' => $status,
                'status_label' => (string) trans('stations.statuses.'.$status, [], $lang),
                'is_terminal' => (bool) $r->is_terminal,
                'station' => [
                    'slug' => $r->slug,
                    'name' => $r->name,
                    'type' => $r->type,
                    'city' => $r->city,
                    'lat' => $r->lat === null ? null : round((float) $r->lat, 6),
                    'lng' => $r->lng === null ? null : round((float) $r->lng, 6),
                    'url' => self::url('/stations/'.$r->slug),
                ],
            ];
        }

        $providers = $rows->pluck('position_source')->unique()
            ->map(fn ($s) => $this->attribution->providerFor('station_sources', $s === null ? null : (string) $s))->all();

        return $this->respond([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era' => [
                'requested_year' => isset($input['year']) ? (int) $input['year'] : null,
                'year' => $era->year,
                'is_current' => $era->is_current,
                'snapshot' => $era->is_current ? (string) trans('mcp.today', [], $lang) : sprintf('%d-12-31', $era->year),
            ],
            'count' => count($stops),
            'counts' => $counts,
            'stops' => $stops,
            'gtfs' => [
                'url' => self::url('/api/v1/eras/'.$era->year.'/gtfs.zip'),
                'license' => 'ODbL 1.0',
                'credit' => 'VFF - Voies Ferrées de France (vff-project.org)',
            ],
            'available_eras' => Lookup::eraYears(),
        ], $providers, $lang, self::url('/lines/'.$line->slug.'#stations'), ['stops']);
    }
}
