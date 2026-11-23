<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Departments\DepartmentFigures;
use App\Support\FrenchDepartments;
use App\Support\RedistributableDates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('department_history')]
#[Title("Le rail d'un département, décennie par décennie")]
#[Description(<<<'TXT'
L'histoire du rail d'un département décennie par décennie, ou le tableau de tous les départements. Mêmes données que l'API REST GET /api/v1/departments/{department}/history et GET /api/v1/stats/departments, et les pages « Le rail dans… » (/departments, service DepartmentFigures, chiffres calculés puis gardés en cache : `computed_at`).
Sans `department` : une entrée par département qui a du rail, dans l'ordre du code : `code`, `slug`, `name`, `region`, `km_reference` (longueur ouverte à l'ère de référence 1930) et `km_today`, `km_peak` et `peak_year`, `closed_share` (part de la longueur ouverte en 1930, ligne par ligne, qui ne l'est plus aujourd'hui, de 0 à 1), `stations_reference`, `stations_today`, `lines`, `lines_open`, `accidents`.
Avec `department` (code INSEE "16", "2A", "971", ou nom "Charente") : `department`, `reference_year`, `current_year`, `closed_share`, `decades` (une entrée par carte décennale de 1830 à aujourd'hui : `open_km` en service, `closed_km` fermée voie en place, `service_km` voie de service, `dismantled_km` déposée, `razed_km` emprise effacée, `removed_km` et `added_km` par rapport à la carte précédente, `stations` par statut, `accidents`), `milestones` (`first_opening`, `peak`, `closure_waves` : plus fortes vagues de retrait du service et les lignes qui y perdent le plus, `reopenings`), `lines` (lignes dont 1 km de voie au moins est dans le département à l'une des ères : `state` open, closed ou service, `track` in_place, dismantled, razed ou none pour une ligne fermée, `km` dans le département et `km_year`, `first_year`, `last_year`, `opened`, `closed` avec `scope`, `electrified` ; paginées par `limit` et `offset`, `lines_total`), `communes` (communes qui ont du rail, `slug` pour get_commune ; les 60 premières, `communes_total`) et `accidents` (total, morts, les plus lourds).
Mesures : kilomètres de voie, voies parallèles comprises, donc un peu au-dessus des longueurs d'axe publiées par l'exploitant ; un tronçon est réparti entre départements par points tous les 500 m d'après les contours des communes simplifiés à 100 m (250 m près par limite). Dates à lire avec leur `precision` (day, month, year). Une date dont la seule source est à usage non commercial n'est pas servie (nulle, ou prise d'une source servie de la même année). Aucun tracé n'est servi.
Sources et licences (bloc `attribution`) : tracés et gares de VFF (ODbL, dont OpenStreetMap et SNCF Open Data), contours des communes (Etalab, ODbL ; IGN, Licence Ouverte 2.0), événements datés de line_events avec leur source, accidents (Wikidata CC0). Chiffres pas encore calculés : erreur `figures_not_computed`.
Exemples : {} ; {"department": "23"} ; {"department": "Charente", "limit": 20} ; {"department": "2A", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class DepartmentHistoryTool extends VffTool
{
    private const MAX_LIMIT = 200;

    /** Communes renvoyées au plus (celles qui ont le plus de gares ouvertes d'abord). */
    private const COMMUNES_SHOWN = 60;

    protected function rules(): array
    {
        return [
            'department' => ['sometimes', 'string', 'min:1', 'max:60'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'department' => $schema->string()->min(1)->max(60)
                ->description('Département : code INSEE (ex. "16", "2A", "971", voir list_departments) ou nom ("Charente"). Sans lui : le tableau de tous les départements.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(50)
                ->description('Avec `department` : nombre de lignes renvoyées, 1 à 200 (50 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)
                ->description('Avec `department` : nombre de lignes à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $figures = app(DepartmentFigures::class);
        $index = $figures->index();
        if ($index === null) {
            return $this->fail('figures_not_computed');
        }
        $providers = ['communes', 'ign', 'wikidata'];
        if (! isset($input['department'])) {
            return $this->respond([
                'computed_at' => $index['computed_at'],
                'reference_year' => $index['reference_year'],
                'current_year' => $index['current_year'],
                'departments' => $index['departments'],
            ], $providers, $lang, self::url('/departments'), ['departments']);
        }

        $code = self::code($input['department']);
        $sheet = $code === null ? null : $figures->sheet($code, $lang);
        if ($sheet === null) {
            return $this->fail('department_not_found', ['query' => $input['department']]);
        }
        unset($sheet['map'], $sheet['summary']);
        // Dates sans source servie : remplacées ou nulles (RedistributableDates::departmentSheet).
        $sheet = app(RedistributableDates::class)->departmentSheet($sheet);

        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 50);
        $lines = $sheet['lines'];
        $sheet['lines'] = array_slice($lines, $offset, $limit);
        $sheet['lines_total'] = count($lines);
        $sheet['lines_offset'] = $offset;
        $sheet['lines_has_more'] = $offset + $limit < count($lines);
        $sheet['communes_total'] = count($sheet['communes']);
        $sheet['communes'] = array_slice($sheet['communes'], 0, self::COMMUNES_SHOWN);

        return $this->respond($sheet, $providers, $lang, self::url('/departments/'.$sheet['department']['slug']), ['lines', 'communes', 'accidents.list', 'decades']);
    }

    /** Code du département : code INSEE (`16`, `2a`, `971`, `1` pour l'Ain) ou nom (« Côtes-d'Armor », casse et accents libres). */
    private static function code(string $query): ?string
    {
        $q = strtoupper(trim($query));
        if (preg_match('/^([0-9]{1,3}|2[AB])$/', $q) === 1) {
            $code = isset(FrenchDepartments::ALL[$q]) ? $q : str_pad($q, 2, '0', STR_PAD_LEFT);

            return isset(FrenchDepartments::ALL[$code]) ? (string) $code : null;
        }
        $wanted = Str::slug($query);
        foreach (FrenchDepartments::ALL as $code => [$name]) {
            if (Str::slug($name) === $wanted) {
                return (string) $code;
            }
        }

        return null;
    }
}
