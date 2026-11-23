<?php

namespace App\Services\Profiles;

use Illuminate\Support\Facades\DB;

/**
 * Déclivité mesurée par le profil en base, pour contrôler une valeur de source écrite
 * (vff:import-sncf-grades, vff:import-wikipedia-grades) : la valeur publiée (sur la voie), sinon
 * celle recalculée sur l'altitude du terrain (RGE ALTI lissé sur 1 km, fenêtre de 1 km, comme
 * ProfileStats), qui la surestime en général sous un tracé ancien. Lecture seule.
 */
final class MeasuredGrade
{
    /**
     * @param  int|null  $profileId  `line_profiles.id`, null sans profil
     * @param  float|null  $published  `line_profiles.max_grade_permil`
     * @param  string|null  $gaps  `line_profiles.gaps`, en JSON
     * @return array{value: float, kind: string}|null `kind` : `track` (publiée) ou `terrain` (recalculée)
     */
    public static function of(?int $profileId, ?float $published, ?string $gaps): ?array
    {
        if ($profileId === null) {
            return null;
        }
        if ($published !== null) {
            return ['value' => $published, 'kind' => 'track'];
        }
        $rows = DB::select('SELECT distance_m, altitude_m, source, structure FROM line_profile_points WHERE line_profile_id = ? ORDER BY seq', [$profileId]);
        $points = array_map(fn (object $r): array => [(float) $r->distance_m, $r->altitude_m === null ? null : (float) $r->altitude_m], $rows);
        $holes = array_map(fn (array $g): array => [(float) $g['from_m'], (float) $g['to_m']], (array) json_decode($gaps ?? '[]', true));
        $flags = LineProfileBuilder::gradeFlags($points, array_map(fn (object $r): ?string => $r->source, $rows), array_map(fn (object $r): ?string => $r->structure, $rows));
        $stats = ProfileStats::summarize(ProfileStats::smoothTerrain($points, $holes, $flags), $holes);
        $value = $stats['max_grade_permil'] ?? null;

        return $value === null ? null : ['value' => (float) $value, 'kind' => 'terrain'];
    }
}
