<?php

namespace App\Services;

use App\Models\Accident;
use App\Support\FrenchDepartments;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Liste de référence des départements et des régions (Code officiel géographique de l'INSEE,
 * Licence Ouverte 2.0, fournisseur `insee` du registre des droits) : les valeurs acceptées par les
 * filtres `department` et `region` de l'API, avec ce que VFF recense dans chacun.
 *
 * Compteurs, avec les mêmes règles que les filtres :
 *   - `lines` : lignes dont la fiche cite le département, ou qui y desservent une gare
 *     (LineDirectory::idsInDepartment) ; une ligne compte dans chacun de ses départements, et une
 *     seule fois par région ;
 *   - `stations` : gares de l'annuaire situées dans le département (ni point technique ni fiche
 *     retirée, comme StationDirectory) ;
 *   - `accidents` : accidents publiés dont le lieu est dans le département (département actuel).
 */
final class Territories
{
    /** Durée du cache des compteurs (secondes). */
    private const TTL = 600;

    /**
     * Départements, dans l'ordre du code officiel, éventuellement ceux d'une seule région.
     *
     * @return list<array{code: string, name: string, region: array{code: string, name: string}, counts: array{lines: int, stations: int, accidents: int}}>
     */
    public function departments(?string $region = null): array
    {
        $counts = $this->departmentCounts();
        $codes = $region !== null ? FrenchDepartments::inRegion($region) : array_map('strval', array_keys(FrenchDepartments::ALL));

        return array_map(function (string $code) use ($counts): array {
            [$name, $regionName] = FrenchDepartments::ALL[$code];
            $regionCode = (string) array_search($regionName, FrenchDepartments::REGIONS, true);

            return [
                'code' => $code,
                'name' => $name,
                'region' => ['code' => $regionCode, 'name' => $regionName],
                'counts' => [
                    'lines' => $counts['lines'][$code] ?? 0,
                    'stations' => $counts['stations'][$code] ?? 0,
                    'accidents' => $counts['accidents'][$code] ?? 0,
                ],
            ];
        }, $codes);
    }

    /**
     * Régions, dans l'ordre de leur nom (ordre alphabétique français), avec leurs départements.
     *
     * @return list<array{code: string, name: string, departments: list<array{code: string, name: string}>, counts: array{lines: int, stations: int, accidents: int}}>
     */
    public function regions(): array
    {
        $counts = Cache::remember('api_territories:regions:v1', self::TTL, fn (): array => $this->computeRegionCounts());
        $regions = [];
        foreach (FrenchDepartments::REGIONS as $code => $name) {
            $code = (string) $code;
            $regions[] = [
                'code' => $code,
                'name' => $name,
                'departments' => array_map(fn (string $d): array => ['code' => $d, 'name' => FrenchDepartments::ALL[$d][0]], FrenchDepartments::inRegion($code)),
                'counts' => [
                    'lines' => $counts['lines'][$code] ?? 0,
                    'stations' => $counts['stations'][$code] ?? 0,
                    'accidents' => $counts['accidents'][$code] ?? 0,
                ],
            ];
        }
        $collator = new \Collator('fr_FR');
        usort($regions, fn (array $a, array $b): int => (int) $collator->compare($a['name'], $b['name']));

        return $regions;
    }

    /**
     * Nombre de lignes par département (codes sans ligne absents), éventuellement parmi les lignes
     * d'une sous-requête (`SELECT id` sur `lines`) : sert aussi aux compteurs de l'annuaire des lignes.
     *
     * @return array<string, int>
     */
    public function lineCounts(?Builder $lineIds = null): array
    {
        $query = DB::query()->fromSub($this->lineDepartments(), 'x')
            ->selectRaw('x.code, COUNT(DISTINCT x.line_id) AS n')
            ->groupBy('x.code')
            ->orderBy('x.code');
        if ($lineIds !== null) {
            $query->whereIn('x.line_id', $lineIds);
        }

        return array_map('intval', $query->pluck('n', 'code')->all());
    }

    /**
     * Nombre de gares de l'annuaire par département (mêmes exclusions que StationDirectory), parmi
     * les gares d'une requête sur `stations as s` quand elle est donnée.
     *
     * @return array<string, int>
     */
    public function stationCounts(?Builder $stations = null): array
    {
        $query = ($stations !== null ? clone $stations : DB::table('stations as s')
            ->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')"))
            ->whereNotNull('s.department_code')
            ->selectRaw('s.department_code AS code, COUNT(*) AS n')
            ->groupBy('s.department_code')
            ->orderBy('s.department_code');

        return array_map('intval', $query->pluck('n', 'code')->all());
    }

    /** @return array{lines: array<string, int>, stations: array<string, int>, accidents: array<string, int>} */
    private function departmentCounts(): array
    {
        return Cache::remember('api_territories:departments:v1', self::TTL, fn (): array => [
            'lines' => $this->lineCounts(),
            'stations' => $this->stationCounts(),
            'accidents' => array_map('intval', Accident::query()->toBase()
                ->whereNotNull('department_code')
                ->selectRaw('department_code AS code, COUNT(*) AS n')
                ->groupBy('department_code')
                ->pluck('n', 'code')->all()),
        ]);
    }

    /** @return array{lines: array<string, int>, stations: array<string, int>, accidents: array<string, int>} */
    private function computeRegionCounts(): array
    {
        $departments = $this->departmentCounts();
        $sum = function (array $byDepartment): array {
            $out = [];
            foreach ($byDepartment as $code => $n) {
                $region = FrenchDepartments::regionCode((string) $code);
                if ($region !== null) {
                    $out[$region] = ($out[$region] ?? 0) + $n;
                }
            }

            return $out;
        };

        // Une ligne qui traverse deux départements d'une même région n'y compte qu'une fois.
        $lines = DB::query()->fromSub($this->lineDepartments(), 'x')
            ->joinSub($this->regionTable(), 'r', 'r.code', '=', 'x.code')
            ->selectRaw('r.region, COUNT(DISTINCT x.line_id) AS n')
            ->groupBy('r.region')
            ->pluck('n', 'region')->all();

        return [
            'lines' => array_map('intval', $lines),
            'stations' => $sum($departments['stations']),
            'accidents' => $sum($departments['accidents']),
        ];
    }

    /**
     * Couples (département, ligne) : départements cités par la fiche de la ligne (par leur nom), et
     * départements des gares qu'elle dessert. Même règle que LineDirectory::idsInDepartment.
     */
    private function lineDepartments(): Builder
    {
        $byName = DB::table('lines as l')
            ->crossJoin(DB::raw("LATERAL jsonb_array_elements_text(CASE WHEN jsonb_typeof(l.departements) = 'array' THEN l.departements ELSE '[]'::jsonb END) AS n(name)"))
            ->joinSub($this->regionTable(), 'd', 'd.name', '=', 'n.name')
            ->select(['d.code', 'l.id as line_id']);
        $byStation = DB::table('station_lines as sl')
            ->join('stations as s', 's.id', '=', 'sl.station_id')
            ->whereNotNull('s.department_code')
            ->select(['s.department_code as code', 'sl.line_id']);

        return $byName->union($byStation);
    }

    /** Table (code, nom, région) des départements, en VALUES liées. */
    private function regionTable(): Builder
    {
        $rows = [];
        $bindings = [];
        foreach (FrenchDepartments::ALL as $code => [$name]) {
            $rows[] = '(?, ?, ?)';
            array_push($bindings, (string) $code, $name, (string) FrenchDepartments::regionCode((string) $code));
        }

        return DB::query()->fromRaw('(VALUES '.implode(', ', $rows).') AS t(code, name, region)', $bindings)
            ->select(['t.code', 't.name', 't.region']);
    }
}
