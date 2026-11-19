<?php

namespace App\Services\Greenways;

use App\Models\Line;
use App\Services\Api\Provenance;
use Illuminate\Support\Facades\DB;

/**
 * Voies vertes d'une ligne (rattachées par `vff:link-greenways`), regroupées par voie verte
 * nommée : section « Voie verte » de la fiche ligne, `GET /api/v1/lines/{line}/greenways` et champ
 * GraphQL `Line.greenways`. Lecture seule. Données © contributeurs OpenStreetMap, ODbL.
 *
 * Une voie verte (groupe) rassemble les ways OSM de la ligne qui portent le même nom : celui de leur
 * itinéraire cyclable OSM (relation route=bicycle|mtb) s'il existe, sinon celui du way, à condition
 * qu'il désigne un itinéraire (GreenwayNames : ni lieu, ni type de voie, ni nom de rue). Les ways sans
 * nom retenu forment un seul groupe sans nom. Le lien OSM d'un groupe pointe vers l'itinéraire s'il
 * existe, sinon vers le way qui suit le plus longtemps l'ancienne emprise.
 */
final class LineGreenways
{
    public const OSM_WAY_URL = 'https://www.openstreetmap.org/way/';

    public const OSM_RELATION_URL = 'https://www.openstreetmap.org/relation/';

    /** Mention à reproduire (ODbL 1.0, article 4.3 : crédit des contributeurs et lien vers la licence). */
    public const ATTRIBUTION = '© contributeurs OpenStreetMap, ODbL 1.0 (https://www.openstreetmap.org/copyright)';

    public function __construct(private readonly Provenance $provenance) {}

    /**
     * Voies vertes de la ligne, la plus longue sur l'emprise d'abord.
     *
     * @return list<array{
     *     name: ?string, shared_length_m: int, way_count: int, opened_year: ?int,
     *     osm_relation_id: ?int, osm_url: string,
     *     ways: list<array{osm_way_id: int, osm_url: string, name: ?string, highway: ?string,
     *         shared_length_m: int, way_length_m: int, shared_percent: int, opened_year: ?int}>
     * }>
     */
    public function groups(Line $line): array
    {
        $rows = DB::table('line_greenways')->where('line_id', $line->id)
            ->orderByDesc('shared_length_m')->orderBy('osm_way_id')
            ->get(['osm_way_id', 'osm_relation_id', 'name', 'relation_name', 'highway', 'shared_length_m', 'way_length_m', 'shared_percent', 'opened_year']);

        $groups = [];
        foreach ($rows as $row) {
            $relationName = GreenwayNames::relationName($row->relation_name);
            $name = $relationName ?? GreenwayNames::wayName($row->name);
            $key = $name === null ? '' : mb_strtolower($name);
            $groups[$key] ??= ['name' => $name, 'relations' => [], 'ways' => []];
            // Le lien d'itinéraire n'est gardé que si c'est le nom de la relation qui nomme le groupe.
            if ($row->osm_relation_id !== null && $relationName !== null) {
                $groups[$key]['relations'][] = (int) $row->osm_relation_id;
            }
            $groups[$key]['ways'][] = [
                'osm_way_id' => (int) $row->osm_way_id,
                'osm_url' => self::OSM_WAY_URL.$row->osm_way_id,
                'name' => $this->clean($row->name),
                'highway' => $row->highway,
                'shared_length_m' => (int) $row->shared_length_m,
                'way_length_m' => (int) $row->way_length_m,
                'shared_percent' => (int) $row->shared_percent,
                'opened_year' => $row->opened_year === null ? null : (int) $row->opened_year,
            ];
        }

        $out = [];
        foreach ($groups as $group) {
            $ways = $group['ways'];
            $relation = $this->commonest($group['relations']);
            $years = array_filter(array_column($ways, 'opened_year'));
            $out[] = [
                'name' => $group['name'],
                'shared_length_m' => array_sum(array_column($ways, 'shared_length_m')),
                'way_count' => count($ways),
                'opened_year' => $years === [] ? null : min($years),
                'osm_relation_id' => $relation,
                // Les ways arrivent triés : le premier est celui qui suit le plus longtemps l'emprise.
                'osm_url' => $relation === null ? $ways[0]['osm_url'] : self::OSM_RELATION_URL.$relation,
                'ways' => $ways,
            ];
        }
        usort($out, fn (array $a, array $b): int => $b['shared_length_m'] <=> $a['shared_length_m']);

        return $out;
    }

    /**
     * Longueur totale (m) des voies vertes sur l'ancienne emprise de la ligne.
     *
     * @param  list<array{shared_length_m: int}>  $groups
     */
    public function totalLength(array $groups): int
    {
        return array_sum(array_column($groups, 'shared_length_m'));
    }

    /** Date (AAAA-MM-JJ) de la lecture d'OSM la plus récente parmi les voies vertes de la ligne. */
    public function osmDate(Line $line): ?string
    {
        $date = DB::table('line_greenways')->where('line_id', $line->id)->max('osm_seen_at');

        return $date === null ? null : substr((string) $date, 0, 10);
    }

    /**
     * Section de la fiche ligne : voies vertes sans le détail des ways.
     *
     * @return array{total_length_m: int, osm_date: ?string, items: list<array{name: ?string,
     *     shared_length_m: int, way_count: int, opened_year: ?int, osm_url: string}>}
     */
    public function forSheet(Line $line): array
    {
        $groups = $this->groups($line);

        return [
            'total_length_m' => $this->totalLength($groups),
            'osm_date' => $groups === [] ? null : $this->osmDate($line),
            'items' => array_map(fn (array $g): array => [
                'name' => $g['name'],
                'shared_length_m' => $g['shared_length_m'],
                'way_count' => $g['way_count'],
                'opened_year' => $g['opened_year'],
                'osm_url' => $g['osm_url'],
            ], $groups),
        ];
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('line_greenways', 'osm');
    }

    /** @param  list<int>  $ids */
    private function commonest(array $ids): ?int
    {
        if ($ids === []) {
            return null;
        }
        $counts = array_count_values($ids);
        arsort($counts);

        return array_key_first($counts);
    }

    private function clean(?string $name): ?string
    {
        $name = $name === null ? null : trim($name);

        return $name === '' ? null : $name;
    }
}
