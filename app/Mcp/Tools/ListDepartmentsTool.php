<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Territories;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_departments')]
#[Title('Départements et régions')]
#[Description(<<<'TXT'
Liste de référence des régions et de leurs départements (Code officiel géographique de l'INSEE, Licence Ouverte 2.0) : les codes à passer dans `department` et `region` de list_lines, list_stations, network_events et accidents. Mêmes données que l'API REST GET /api/v1/regions et /api/v1/departments.
Chaque région : `code` (INSEE, ex. "53"), `name`, `counts` et `departments` ; chaque département : `code` (ex. "29", "2A", "974"), `name` et `counts`. `counts` : `lines` (lignes dont la fiche cite le territoire ou qui y desservent une gare : ce que renvoie list_lines avec ce filtre), `stations` (gares de l'annuaire), `accidents` (accidents publiés dont le lieu est dans le territoire). Une ligne qui traverse plusieurs départements d'une région n'y compte qu'une fois.
Sans argument : toutes les régions (ordre alphabétique) avec leurs départements. `region` : une seule région.
Exemple : {} ; {"region": "53"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListDepartmentsTool extends VffTool
{
    protected function rules(): array
    {
        return ['region' => ['sometimes', 'string', 'in:'.implode(',', FrenchDepartments::regionCodes())]];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())
                ->description('Code INSEE d\'une région ("53" Bretagne, "84" Auvergne-Rhône-Alpes, "11" Île-de-France…). Par défaut : toutes.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $territories = app(Territories::class);
        $departments = [];
        foreach ($territories->departments() as $d) {
            $departments[$d['code']] = ['code' => $d['code'], 'name' => $d['name'], 'counts' => $d['counts']];
        }
        $regions = array_values(array_filter(
            $territories->regions(),
            fn (array $r): bool => ! isset($input['region']) || $r['code'] === $input['region'],
        ));

        return $this->respond([
            'source' => 'INSEE, Code officiel géographique (Licence Ouverte 2.0)',
            'regions' => array_map(fn (array $r): array => [
                'code' => $r['code'],
                'name' => $r['name'],
                'counts' => $r['counts'],
                'departments' => array_map(fn (array $d): array => $departments[$d['code']], $r['departments']),
            ], $regions),
        ], ['insee'], $lang, self::url('/stations'));
    }
}
