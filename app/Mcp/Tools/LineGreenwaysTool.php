<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Greenways\GreenwayLinker;
use App\Services\Greenways\LineGreenways;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_greenways')]
#[Title("Voies vertes d'une ancienne ligne")]
#[Description(<<<'TXT'
Voies vertes et chemins cyclables d'OpenStreetMap qui reprennent l'emprise d'une ligne disparue ou fermée, même contenu que l'API REST GET /api/v1/lines/{line}/greenways. Le rattachement est mesuré, pas déclaré : un way OSM est rattaché quand au moins 50 m de sa longueur (et la moitié) suivent l'axe de la ligne à 8 m près, là où aucune voie n'est encore en service ou en place ; une ligne n'a de voie verte qu'à partir de 300 m communs. Les tramways, métros et funiculaires n'en reçoivent jamais ; un tram-train du réseau ferré national est une ligne ferroviaire.
Réponse : `total_shared_length_m` (mètres de voie verte sur l'ancienne emprise), `osm_date` (lecture d'OpenStreetMap), `greenways` (une voie verte par nom, la plus longue d'abord) : `name` (itinéraire cyclable OSM, sinon nom du way ; nul si aucun nom ne désigne un itinéraire : ni lieu, ni type de voie, ni rue), `shared_length_m` (longueur qui suit l'ancienne emprise, pas la longueur de la voie verte entière), `way_count`, `opened_year` (nulle si OSM ne la donne pas : jamais devinée), `osm_url` (relation, sinon way). Avec `include_ways` : `ways[]` (osm_way_id, highway, way_length_m, shared_length_m, shared_percent), 50 par voie verte au plus (`ways_not_shown` donne le reste). Une ligne sans voie verte rattachée renvoie une liste vide, pas une erreur.
Source et licence (champ `provenance` et bloc `attribution`) : OpenStreetMap, ODbL 1.0 ; la mention « © contributeurs OpenStreetMap » est à reproduire et toute base dérivée diffusée se partage sous la même licence.
Exemples : {"line": "rfn-436000"} ; {"line": "436000", "include_ways": true, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineGreenwaysTool extends VffTool
{
    /** Ways renvoyés au plus par voie verte (une voie verte de 200 ways ferait dépasser le plafond de réponse). */
    public const MAX_WAYS_PER_GREENWAY = 50;

    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'include_ways' => ['sometimes', 'boolean'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-436000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "436000").'),
            'include_ways' => $schema->boolean()->default(false)
                ->description('Ajoute le détail par way OpenStreetMap (identifiant, type de chemin, longueurs), 50 par voie verte au plus. Faux par défaut.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $greenways = app(LineGreenways::class);
        $groups = $greenways->groups($line);
        $withWays = (bool) ($input['include_ways'] ?? false);

        $items = array_map(function (array $group) use ($withWays): array {
            $ways = $group['ways'];
            unset($group['ways']);
            if (! $withWays) {
                return $group;
            }

            return $group + [
                'ways' => array_slice($ways, 0, self::MAX_WAYS_PER_GREENWAY),
                'ways_not_shown' => max(0, count($ways) - self::MAX_WAYS_PER_GREENWAY),
            ];
        }, $groups);

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                'total_shared_length_m' => $greenways->totalLength($groups),
                'osm_date' => $groups === [] ? null : $greenways->osmDate($line),
                'greenways' => $items,
                'provenance' => $greenways->provenance() + ['mention' => LineGreenways::ATTRIBUTION],
                'method' => [
                    'axis_tolerance_m' => GreenwayLinker::AXIS_TOLERANCE_M,
                    'service_clearance_m' => GreenwayLinker::CLEARANCE_M,
                    'min_way_shared_m' => GreenwayLinker::MIN_SHARED_M,
                    'min_way_share_percent' => GreenwayLinker::MIN_SHARE_PERCENT,
                    'min_line_total_m' => GreenwayLinker::MIN_LINE_TOTAL_M,
                ],
            ],
            ['osm'],
            $lang,
            self::url('/lines/'.$line->slug.'#greenways'),
            ['greenways'],
        );
    }
}
