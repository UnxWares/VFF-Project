<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Territories;
use App\Support\FrenchDepartments;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Territoires', "Les départements et les régions : valeurs acceptées par les filtres `department` et `region` des autres points d'accès, avec leur nom et ce que VFF recense dans chacun.", weight: 3)]
class TerritoryController extends Controller
{
    /**
     * Départements.
     *
     * La liste de référence des départements : les codes acceptés par le
     * paramètre `department` de `/lines`, `/stations`, `/events` et
     * `/accidents` (et des requêtes GraphQL et outils MCP équivalents), dans
     * l'ordre du code officiel (`01` à `95`, `2A`, `2B`, puis `971` à `976`).
     *
     * Chaque département : `code` (code INSEE), `name` (nom officiel),
     * `region` (`code` et `name` de sa région depuis le 1er janvier 2016),
     * `counts` et `links`.
     *
     * **Compteurs** (`counts`), avec les règles des filtres : `lines` (lignes
     * dont la fiche cite le département ou qui y desservent une gare, soit le
     * total de `/lines?department=…`), `stations` (gares de l'annuaire situées
     * dans le département, points techniques exclus), `accidents` (accidents
     * publiés dont le lieu est dans le département actuel). Recalculés toutes
     * les dix minutes au plus.
     *
     * **Liens** (`links`) : la fiche du département (`self`) et les listes
     * filtrées sur lui (`lines`, `stations`, `events`, `accidents`).
     *
     * **Source** : codes et noms du Code officiel géographique de l'INSEE
     * (Licence Ouverte 2.0, fournisseur `insee` de `/sources`) ; compteurs
     * calculés par VFF sur sa base (ODbL).
     */
    #[QueryParameter('region', 'Code INSEE d\'une région (`11`, `24`, `27`, `28`, `32`, `44`, `52`, `53`, `75`, `76`, `84`, `93`, `94`, outre-mer `01` à `06`) : seulement ses départements. Un autre code donne HTTP 422.', type: 'string', example: '53')]
    #[ApiResponse(200, 'Les départements de Bretagne (`region=53`), liste raccourcie.', examples: [[
        'data' => [[
            'code' => '29', 'name' => 'Finistère', 'region' => ['code' => '53', 'name' => 'Bretagne'],
            'counts' => ['lines' => 31, 'stations' => 66, 'accidents' => 9],
            'links' => [
                'self' => 'https://vff-project.org/api/v1/departments/29',
                'lines' => 'https://vff-project.org/api/v1/lines?department=29',
                'stations' => 'https://vff-project.org/api/v1/stations?department=29',
                'events' => 'https://vff-project.org/api/v1/events?department=29',
                'accidents' => 'https://vff-project.org/api/v1/accidents?department=29',
            ],
        ]],
        'meta' => ['total' => 4, 'source' => ['provider' => 'insee', 'name' => 'Code officiel géographique (INSEE)', 'license' => 'Licence Ouverte 2.0 (Etalab)']],
    ]])]
    #[ApiResponse(422, 'Code de région inconnu.', examples: [['message' => 'The selected region is invalid.', 'errors' => ['region' => ['The selected region is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function departments(Request $request, Territories $territories): JsonResponse
    {
        $input = $request->validate(['region' => ['nullable', Rule::in(array_keys(FrenchDepartments::REGIONS))]]);
        $departments = array_map(fn (array $d): array => $d + ['links' => self::links('department', $d['code'])],
            $territories->departments(isset($input['region']) ? (string) $input['region'] : null));

        return self::json($departments);
    }

    /**
     * Un département.
     *
     * Les mêmes champs qu'un élément de `/departments` : `code`, `name`, `region`
     * (`code` et `name`), `counts` (lignes, gares et accidents recensés dans le
     * département) et `links`, avec en plus le lien `region` vers sa région. Le
     * code s'écrit comme dans le code officiel (`29`, `2A`, `971`) ; les
     * minuscules sont acceptées (`2a`). Code inconnu : 404.
     */
    #[PathParameter('department', 'Code INSEE du département.', example: '29')]
    #[ApiResponse(200, 'Le Finistère.', examples: [[
        'data' => [
            'code' => '29', 'name' => 'Finistère', 'region' => ['code' => '53', 'name' => 'Bretagne'],
            'counts' => ['lines' => 31, 'stations' => 66, 'accidents' => 9],
            'links' => [
                'self' => 'https://vff-project.org/api/v1/departments/29',
                'lines' => 'https://vff-project.org/api/v1/lines?department=29',
                'stations' => 'https://vff-project.org/api/v1/stations?department=29',
                'events' => 'https://vff-project.org/api/v1/events?department=29',
                'accidents' => 'https://vff-project.org/api/v1/accidents?department=29',
                'region' => 'https://vff-project.org/api/v1/regions/53',
            ],
        ],
    ]])]
    #[ApiResponse(404, 'Département inconnu.', examples: [['message' => 'Unknown department.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function department(string $department, Territories $territories): JsonResponse
    {
        $code = strtoupper($department);
        $found = array_values(array_filter($territories->departments(), fn (array $d): bool => $d['code'] === $code));
        abort_if($found === [], 404, 'Unknown department.');
        $item = $found[0];
        $item['links'] = self::links('department', $code) + ['region' => route('api.regions.show', $item['region']['code'])];

        return response()->json(['data' => $item])->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Régions.
     *
     * La liste de référence des régions (découpage en vigueur depuis le
     * 1er janvier 2016, plus les cinq régions d'outre-mer) : les codes
     * acceptés par le paramètre `region` de `/lines`, `/stations`, `/events`
     * et `/accidents`, dans l'ordre alphabétique français des noms.
     *
     * Chaque région : `code` (code INSEE), `name`, `departments` (`code` et
     * `name` de chacun, dans l'ordre du code officiel), `counts` (mêmes
     * règles que `/departments` ; une ligne qui traverse plusieurs
     * départements de la région n'y compte qu'une fois) et `links`.
     *
     * **Source** : Code officiel géographique de l'INSEE (Licence Ouverte 2.0).
     */
    #[ApiResponse(200, 'Les régions (liste raccourcie).', examples: [[
        'data' => [[
            'code' => '53', 'name' => 'Bretagne',
            'departments' => [['code' => '22', 'name' => 'Côtes-d\'Armor'], ['code' => '29', 'name' => 'Finistère'], ['code' => '35', 'name' => 'Ille-et-Vilaine'], ['code' => '56', 'name' => 'Morbihan']],
            'counts' => ['lines' => 91, 'stations' => 271, 'accidents' => 26],
            'links' => [
                'self' => 'https://vff-project.org/api/v1/regions/53',
                'lines' => 'https://vff-project.org/api/v1/lines?region=53',
                'stations' => 'https://vff-project.org/api/v1/stations?region=53',
                'events' => 'https://vff-project.org/api/v1/events?region=53',
                'accidents' => 'https://vff-project.org/api/v1/accidents?region=53',
                'departments' => 'https://vff-project.org/api/v1/departments?region=53',
            ],
        ]],
        'meta' => ['total' => 18, 'source' => ['provider' => 'insee', 'name' => 'Code officiel géographique (INSEE)', 'license' => 'Licence Ouverte 2.0 (Etalab)']],
    ]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function regions(Territories $territories): JsonResponse
    {
        return self::json(array_map(fn (array $r): array => $r + ['links' => self::regionLinks($r['code'])], $territories->regions()));
    }

    /**
     * Une région.
     *
     * Les mêmes champs qu'un élément de `/regions` : `code`, `name`, `departments`
     * (`code` et `name` de chacun), `counts` (lignes, gares et accidents recensés
     * dans la région, une ligne qui traverse plusieurs de ses départements n'y
     * compte qu'une fois) et `links`. Le code est celui de l'INSEE (`53` pour la
     * Bretagne) ; un code inconnu donne 404.
     */
    #[PathParameter('region', 'Code INSEE de la région.', example: '53')]
    #[ApiResponse(200, 'La Bretagne.', examples: [[
        'data' => [
            'code' => '53', 'name' => 'Bretagne',
            'departments' => [['code' => '22', 'name' => 'Côtes-d\'Armor'], ['code' => '29', 'name' => 'Finistère'], ['code' => '35', 'name' => 'Ille-et-Vilaine'], ['code' => '56', 'name' => 'Morbihan']],
            'counts' => ['lines' => 91, 'stations' => 271, 'accidents' => 26],
            'links' => [
                'self' => 'https://vff-project.org/api/v1/regions/53',
                'lines' => 'https://vff-project.org/api/v1/lines?region=53',
                'stations' => 'https://vff-project.org/api/v1/stations?region=53',
                'events' => 'https://vff-project.org/api/v1/events?region=53',
                'accidents' => 'https://vff-project.org/api/v1/accidents?region=53',
                'departments' => 'https://vff-project.org/api/v1/departments?region=53',
            ],
        ],
    ]])]
    #[ApiResponse(404, 'Région inconnue.', examples: [['message' => 'Unknown region.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function region(string $region, Territories $territories): JsonResponse
    {
        $found = array_values(array_filter($territories->regions(), fn (array $r): bool => $r['code'] === $region));
        abort_if($found === [], 404, 'Unknown region.');

        return response()->json(['data' => $found[0] + ['links' => self::regionLinks($region)]])->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Liste, avec le total et la source des codes et des noms.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private static function json(array $items): JsonResponse
    {
        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => count($items),
                'source' => ['provider' => 'insee', 'name' => 'Code officiel géographique (INSEE)', 'license' => 'Licence Ouverte 2.0 (Etalab)'],
            ],
        ])->header('Cache-Control', 'public, max-age=600');
    }

    /** @return array<string, string> */
    private static function links(string $filter, string $code): array
    {
        return [
            'self' => route($filter === 'department' ? 'api.departments.show' : 'api.regions.show', $code),
            'lines' => route('api.lines.index', [$filter => $code]),
            'stations' => route('api.stations.index', [$filter => $code]),
            'events' => route('api.events.index', [$filter => $code]),
            'accidents' => route('api.accidents.index', [$filter => $code]),
        ];
    }

    /** @return array<string, string> */
    private static function regionLinks(string $code): array
    {
        return self::links('region', $code) + ['departments' => route('api.departments.index', ['region' => $code])];
    }
}
