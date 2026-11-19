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
            'points' => $maxPoints === null ? $profile->points : self::downsample($profile->points, $maxPoints),
            'structures' => $profile->structures,
            'altitude_sources' => $profile->altitude_sources,
            'gaps' => $profile->gaps,
            'stations' => $profile->stations,
            'computed_at' => $profile->computed_at->toIso8601String(),
            'provenance' => $this->provenance->for('line_profiles', $profile->source),
        ];
    }

    /**
     * Profil allégé à `$max` points environ : points régulièrement espacés, plus les deux bouts,
     * le point le plus bas, le plus haut et les deux bords de chaque série sans altitude (les
     * extrêmes et les trous restent visibles). Ordre du parcours conservé.
     *
     * @param  list<array{0: int, 1: float|null}>  $points
     * @return list<array{0: int, 1: float|null}>
     */
    public static function downsample(array $points, int $max): array
    {
        $n = count($points);
        if ($n <= $max || $max < 2) {
            return $points;
        }
        $keep = [0 => true, $n - 1 => true];
        $step = ($n - 1) / ($max - 1);
        for ($k = 1; $k < $max - 1; $k++) {
            $keep[(int) round($k * $step)] = true;
        }
        $min = null;
        $maxIdx = null;
        foreach ($points as $i => [, $z]) {
            if ($z !== null) {
                $min = $min === null || $z < $points[$min][1] ? $i : $min;
                $maxIdx = $maxIdx === null || $z > $points[$maxIdx][1] ? $i : $maxIdx;
            }
            $prevNull = $i > 0 && $points[$i - 1][1] === null;
            if (($z === null) !== $prevNull && $i > 0) {
                $keep[$i - 1] = true;
                $keep[$i] = true;
            }
        }
        foreach ([$min, $maxIdx] as $i) {
            if ($i !== null) {
                $keep[$i] = true;
            }
        }
        ksort($keep);

        return array_map(fn (int $i): array => $points[$i], array_keys($keep));
    }
}
