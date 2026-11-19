<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Services\EraComparison;
use App\Services\NetworkErosion;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Cartographie par ère', "Le réseau tel qu'il était à la fin d'une décennie : tracés et gares en GeoJSON, tuiles vectorielles MVT pour MapLibre.", weight: 1)]
class EraComparisonController extends Controller
{
    /** Zoom maximal servi (comme les tuiles d'une ère). */
    private const MAX_ZOOM = 22;

    /** En-têtes des tuiles : mêmes règles que les tuiles d'une ère (cache court, CORS ouvert). */
    private const TILE_HEADERS = [
        'Content-Type' => 'application/x-protobuf',
        'Cache-Control' => 'public, max-age=60',
        'Access-Control-Allow-Origin' => '*',
    ];

    public function __construct(
        private readonly EraComparison $comparison,
        private readonly NetworkErosion $erosion,
    ) {}

    /**
     * Comparer deux ères.
     *
     * Ce qui a changé sur le réseau entre deux décennies de la carte, en kilomètres et en
     * nombre de lignes. Les deux années peuvent être données dans n'importe quel ordre : la
     * comparaison va toujours de la plus ancienne (`from`) à la plus récente (`to`).
     *
     * Pour chaque changement (`changes`) :
     *
     * - `opened` : en service à `to`, pas à `from` (ouverte, ou rouverte, entre les deux) ;
     * - `closed` : fermée à `to`, voie en place (y compris une voie de service), alors qu'elle
     *   ne l'était pas à `from` ;
     * - `removed` : en service ou fermée à `from`, déposée ou disparue à `to` ;
     * - `kept` : en service aux deux dates.
     *
     * `km` est la somme des tronçons concernés (voies parallèles d'une même ligne comprises,
     * arrondie au kilomètre : un ordre de grandeur, pas le kilométrage officiel) ; `lines`
     * compte les lignes dont au moins un tronçon est concerné (une ligne fermée en partie compte
     * dans `closed` et dans `kept`). `open_km` donne la longueur en service à chacune des deux
     * dates (`from`, `to`), mêmes règles de calcul.
     *
     * Les tronçons sont appariés d'une ère à l'autre par ligne et par géométrie identique.
     * Chaque ère est un instantané au 31/12 de son année ; `2030` est le réseau d'aujourd'hui.
     * Provenance des tracés : voir `/sources` (OpenStreetMap et SNCF sous ODbL, IGN sous
     * Licence Ouverte 2.0, OpenHistoricalMap sous CC0).
     */
    #[PathParameter('year', 'Première année comparée : une décennie de 1830 à 2030.', example: 1930)]
    #[PathParameter('other', 'Seconde année comparée : une autre décennie de 1830 à 2030.', example: 1980)]
    #[ApiResponse(200, 'Changements entre 1930 et 1980.', examples: [[
        'data' => [
            'from' => 1930, 'to' => 1980,
            'changes' => [
                'opened' => ['km' => 479, 'lines' => 46],
                'closed' => ['km' => 3030, 'lines' => 462],
                'removed' => ['km' => 22404, 'lines' => 901],
                'kept' => ['km' => 33722, 'lines' => 673],
            ],
            'open_km' => ['from' => 58678, 'to' => 34201],
            'links' => [
                'self' => 'https://vff-project.org/api/v1/eras/1930/compare/1980',
                'map' => 'https://vff-project.org/maps?era=1930&compare=1980',
                'tiles' => 'https://vff-project.org/api/v1/eras/1930/compare/1980/tiles/{z}/{x}/{y}.mvt',
                'sources' => 'https://vff-project.org/api/v1/sources',
            ],
        ],
    ]])]
    #[ApiResponse(404, "Ère inconnue (année qui n'est pas une décennie de la carte).", examples: [ErrorExamples::ERA_NOT_FOUND])]
    #[ApiResponse(422, 'Les deux années sont identiques.', examples: [['message' => 'Choose two different eras to compare.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(int $year, int $other): JsonResponse
    {
        $pair = $this->resolve($year, $other);
        if ($pair instanceof JsonResponse) {
            return $pair;
        }
        [$from, $to] = $pair;

        return response()->json([
            'data' => $this->comparison->compare($from->id, $from->year, $to->id, $to->year, url('/')),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Tuile de comparaison de deux ères (MVT).
     *
     * Tuile vectorielle Mapbox Vector Tile (couche `vff-compare`) des tronçons qui ont changé
     * entre les deux ères, et de ceux restés en service. Propriétés de chaque entité :
     * `change` (`opened`, `closed`, `removed` ou `kept`, voir « Comparer deux ères »),
     * `line_slug`, `line_name`, `line_code`. Années dans n'importe quel ordre, comme pour la
     * comparaison. Une tuile sans changement est vide (corps de 0 octet).
     *
     * Affichage, pas export : comme les tuiles de l'ère, elle sert à la carte du site et dessine
     * aussi les tracés d'une source dont la licence n'autorise pas la redistribution. Pour
     * réutiliser des tracés, servez-vous des exports qui suivent la règle des droits.
     */
    #[PathParameter('year', 'Première année comparée (décennie).', example: 1930)]
    #[PathParameter('other', 'Seconde année comparée (décennie).', example: 1980)]
    #[PathParameter('z', 'Niveau de zoom (0 à 22 ; la carte VFF demande au plus 14).', example: 6)]
    #[PathParameter('x', 'Colonne de la tuile dans la grille XYZ.', example: 32)]
    #[PathParameter('y', 'Rangée de la tuile dans la grille XYZ.', example: 22)]
    #[ApiResponse(200, 'Tuile MVT (protobuf binaire).')]
    #[ApiResponse(404, 'Ère inconnue, ou tuile hors de la grille XYZ.', examples: [ErrorExamples::ERA_NOT_FOUND])]
    #[ApiResponse(422, 'Les deux années sont identiques.', examples: [['message' => 'Choose two different eras to compare.']])]
    public function tile(int $year, int $other, int $z, int $x, int $y): Response|JsonResponse
    {
        abort_unless($this->onGrid($z, $x, $y), 404);
        $pair = $this->resolve($year, $other);
        if ($pair instanceof JsonResponse) {
            return $pair;
        }
        [$from, $to] = $pair;

        return response($this->comparison->tile($from->id, $to->id, $z, $x, $y), 200, self::TILE_HEADERS);
    }

    /**
     * Toutes les ères empilées (MVT).
     *
     * Tuile vectorielle (couche `vff-stack`) de tous les tronçons qui ont été en service à au
     * moins une décennie de la carte, de 1830 à aujourd'hui, chacun une seule fois. Propriétés :
     * `first_year` et `last_year`, première et dernière décennie où la carte le montre en
     * service (`2030` : encore en service aujourd'hui), `line_slug`, `line_name`, `line_code`.
     * La carte VFF colore le tracé selon `last_year` pour montrer l'érosion du réseau.
     *
     * Les décennies sont des instantanés au 31/12 : `last_year` = 1950 signifie en service au
     * 31/12/1950 mais plus au 31/12/1960.
     *
     * Affichage, pas export : comme les tuiles de l'ère, elle sert à la carte du site et dessine
     * aussi les tracés d'une source dont la licence n'autorise pas la redistribution. Pour
     * réutiliser des tracés, servez-vous des exports qui suivent la règle des droits.
     */
    #[PathParameter('z', 'Niveau de zoom (0 à 22 ; la carte VFF demande au plus 14).', example: 6)]
    #[PathParameter('x', 'Colonne de la tuile dans la grille XYZ.', example: 32)]
    #[PathParameter('y', 'Rangée de la tuile dans la grille XYZ.', example: 22)]
    #[ApiResponse(200, 'Tuile MVT (protobuf binaire).')]
    #[ApiResponse(404, 'Tuile hors de la grille XYZ.')]
    public function stackedTile(int $z, int $x, int $y): Response
    {
        abort_unless($this->onGrid($z, $x, $y), 404);

        return response($this->erosion->tile($z, $x, $y), 200, self::TILE_HEADERS);
    }

    private function onGrid(int $z, int $x, int $y): bool
    {
        return $z <= self::MAX_ZOOM && $x < (1 << $z) && $y < (1 << $z);
    }

    /**
     * Les deux ères, dans l'ordre chronologique, ou la réponse d'erreur (404, 422).
     *
     * @return array{0: Era, 1: Era}|JsonResponse
     */
    private function resolve(int $year, int $other): array|JsonResponse
    {
        if ($year === $other) {
            return response()->json(['message' => 'Choose two different eras to compare.'], 422);
        }
        $eras = Era::whereIn('year', [$year, $other])->orderBy('year')->get();
        foreach ([$year, $other] as $wanted) {
            if (! $eras->contains('year', $wanted)) {
                return response()->json(ErrorExamples::eraNotFound($wanted), 404);
            }
        }

        return [$eras[0], $eras[1]];
    }
}
