<?php

namespace App\Services\Profiles;

use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Api\Provenance;

/**
 * Lecture des profils en long déjà calculés (table `line_profiles`) : section « Profil en long »
 * de la fiche ligne, `GET /api/v1/lines/{line}/profile`, champ GraphQL `Line.profile` et outil
 * MCP `line-profile`. Aucune requête vers l'IGN ici : un profil absent reste absent tant que
 * vff:compute-line-profiles ne l'a pas calculé.
 */
final readonly class LineProfiles
{
    /**
     * Points du profil passés à la fiche ligne : environ un par pixel et demi de la courbe à
     * 1 280 px, au lieu de 2 500 au plus (le profil complet reste dans l'API).
     */
    public const SHEET_MAX_POINTS = 600;

    public function __construct(private Provenance $provenance) {}

    public function find(Line $line): ?LineProfile
    {
        return LineProfile::query()->with('era:id,year')->where('line_id', $line->id)->first();
    }

    /**
     * Profil complet, même forme pour la fiche, l'API REST et l'outil MCP.
     *
     * @return array{
     *     era_year: int, step_m: int, length_m: int, trace_length_m: int,
     *     summary: array{min_altitude_m: float, max_altitude_m: float, ascent_m: float, descent_m: float,
     *         max_grade_permil: float, max_grade_from_m: int, max_grade_to_m: int, max_grade_direction: string},
     *     points: list<array{0: int, 1: float|null}>,
     *     structures: list<array{from_m: int, to_m: int, kind: string}>,
     *     altitude_sources: array{bdtopo: int, rge_alti: int, interpolated: int, missing: int},
     *     gaps: list<array{from_m: int, to_m: int}>,
     *     stations: list<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null}>,
     *     computed_at: string,
     *     provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     * }|null
     */
    public function forLine(Line $line, ?int $maxPoints = null): ?array
    {
        $profile = $this->find($line);

        return $profile === null ? null : $this->present($profile, $maxPoints);
    }

    /**
     * @return array{
     *     era_year: int, step_m: int, length_m: int, trace_length_m: int,
     *     summary: array{min_altitude_m: float, max_altitude_m: float, ascent_m: float, descent_m: float,
     *         max_grade_permil: float, max_grade_from_m: int, max_grade_to_m: int, max_grade_direction: string},
     *     points: list<array{0: int, 1: float|null}>,
     *     structures: list<array{from_m: int, to_m: int, kind: string}>,
     *     altitude_sources: array{bdtopo: int, rge_alti: int, interpolated: int, missing: int},
     *     gaps: list<array{from_m: int, to_m: int}>,
     *     stations: list<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null}>,
     *     computed_at: string,
     *     provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     * }
     */
    public function present(LineProfile $profile, ?int $maxPoints = null): array
    {
        return [
            'era_year' => (int) $profile->era->year,
            'step_m' => $profile->step_m,
            'length_m' => $profile->length_m,
            'trace_length_m' => $profile->trace_length_m,
            'summary' => [
                'min_altitude_m' => $profile->min_altitude_m,
                'max_altitude_m' => $profile->max_altitude_m,
                'ascent_m' => $profile->ascent_m,
                'descent_m' => $profile->descent_m,
                'max_grade_permil' => $profile->max_grade_permil,
                'max_grade_from_m' => $profile->max_grade_from_m,
                'max_grade_to_m' => $profile->max_grade_to_m,
                'max_grade_direction' => $profile->max_grade_direction,
            ],
            'points' => $maxPoints === null ? $profile->points : self::downsample($profile->points, $maxPoints, $profile->gaps),
            'structures' => $profile->structures,
            'altitude_sources' => $profile->altitude_sources,
            'gaps' => $profile->gaps,
            'stations' => $profile->stations,
            'computed_at' => $profile->computed_at->toIso8601String(),
            'provenance' => $this->provenance->for('line_profiles', $profile->source),
        ];
    }

    /**
     * Profil allégé à `$max` points AU PLUS, ordre du parcours conservé. Points gardés d'abord :
     * les deux bouts, le point le plus bas, le plus haut, les deux bords de chaque lacune du
     * parcours et de chaque série sans altitude (les extrêmes et les trous restent visibles) ; le
     * reste du budget va à des points régulièrement espacés. Si ces points obligés dépassent le
     * budget, ils sont pris dans cet ordre de priorité jusqu'à `$max`.
     *
     * @param  list<array{0: int, 1: float|null}>  $points
     * @param  list<array{from_m: int, to_m: int}>  $gaps  lacunes du parcours (`line_profiles.gaps`)
     * @return list<array{0: int, 1: float|null}>
     */
    public static function downsample(array $points, int $max, array $gaps = []): array
    {
        $n = count($points);
        if ($n <= $max) {
            return $points;
        }
        if ($max <= 0) {
            return [];
        }

        $min = null;
        $top = null;
        $holeEdges = [];
        foreach ($points as $i => [, $z]) {
            if ($z !== null) {
                $min = $min === null || $z < $points[$min][1] ? $i : $min;
                $top = $top === null || $z > $points[$top][1] ? $i : $top;
            }
            if ($i > 0 && ($z === null) !== ($points[$i - 1][1] === null)) {
                array_push($holeEdges, $i - 1, $i);
            }
        }
        $gapEdges = [];
        $pieces = ProfileStats::pieces($points, array_map(fn (array $g): array => [(float) $g['from_m'], (float) $g['to_m']], $gaps));
        for ($k = 1, $c = count($pieces); $k < $c; $k++) {
            array_push($gapEdges, $pieces[$k - 1][1], $pieces[$k][0]);
        }

        // Points obligés, par ordre de priorité, dans la limite du budget.
        $keep = [];
        foreach ([0, $n - 1, $min, $top, ...$gapEdges, ...$holeEdges] as $i) {
            if ($i !== null && count($keep) < $max) {
                $keep[$i] = true;
            }
        }
        // Reste du budget : points régulièrement espacés entre les deux bouts (un point qui tombe
        // sur un point déjà gardé n'en ajoute pas : le total reste sous `$max`).
        $free = $max - count($keep);
        for ($k = 1; $k <= $free; $k++) {
            $keep[(int) round($k * ($n - 1) / ($free + 1))] = true;
        }
        ksort($keep);

        return array_map(fn (int $i): array => $points[$i], array_keys($keep));
    }
}
