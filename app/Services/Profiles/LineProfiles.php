<?php

namespace App\Services\Profiles;

use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Api\Provenance;
use App\Services\TraceExport\LineTraceExport;
use Illuminate\Support\Facades\DB;

/**
 * Lecture des profils en long déjà calculés (tables `line_profiles` et `line_profile_points`) :
 * section « Profil en long »
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

    public function __construct(private Provenance $provenance, private LineTraceExport $rights) {}

    public function find(Line $line): ?LineProfile
    {
        return LineProfile::query()->with(['era:id,year', 'line:id,slug'])->where('line_id', $line->id)->first();
    }

    /**
     * Méthode des dénivelés cumulés, rendue avec chaque profil : hystérésis (une variation de moins
     * de `threshold_m` n'est pas comptée), après une moyenne glissante de `terrain_smoothing_m`
     * appliquée aux seules altitudes du terrain (ProfileStats::TERRAIN_SMOOTHING_M).
     *
     * @return array{method: string, threshold_m: float, terrain_smoothing_m: float}
     */
    public static function climbMethod(): array
    {
        return [
            'method' => 'hysteresis',
            'threshold_m' => ProfileStats::CLIMB_THRESHOLD_M,
            'terrain_smoothing_m' => ProfileStats::TERRAIN_SMOOTHING_M,
        ];
    }

    /**
     * Profil complet, même forme pour la fiche, l'API REST et l'outil MCP.
     *
     * @return array{
     *     basis: string, era_year: int|null, step_m: int, length_m: int, trace_length_m: int, covered_m: int|null, off_path_m: int|null, pk_anchors: int,
     *     summary: array{min_altitude_m: float, max_altitude_m: float, ascent_m: float, descent_m: float,
     *         max_grade_permil: float|null, max_grade_from_m: int|null, max_grade_to_m: int|null, max_grade_direction: string|null,
     *         max_grade_withheld: string|null,
     *         climb_method: array{method: string, threshold_m: float, terrain_smoothing_m: float}},
     *     rack: array{system: string, extent: string|null, max_grade_permil: int, braking_only: bool,
     *         source: array{provider: string, title: string, url: string, license: string}}|null,
     *     points: list<array{0: int, 1: float|null, 2: int|null}>,
     *     structures: list<array{from_m: int, to_m: int, kind: string}>,
     *     altitude_sources: array{bdtopo: int, rge_alti: int, interpolated: int, missing: int},
     *     gaps: list<array{from_m: int, to_m: int}>,
     *     branches: list<array{from_m: int, to_m: int}>,
     *     terrain_ranges: list<array{from_m: int, to_m: int}>,
     *     stations: list<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null, status?: string, offset_m?: int}>,
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
     *     basis: string, era_year: int|null, step_m: int, length_m: int, trace_length_m: int, covered_m: int|null, off_path_m: int|null, pk_anchors: int,
     *     summary: array{min_altitude_m: float, max_altitude_m: float, ascent_m: float, descent_m: float,
     *         max_grade_permil: float|null, max_grade_from_m: int|null, max_grade_to_m: int|null, max_grade_direction: string|null,
     *         max_grade_withheld: string|null,
     *         climb_method: array{method: string, threshold_m: float, terrain_smoothing_m: float}},
     *     rack: array{system: string, extent: string|null, max_grade_permil: int, braking_only: bool,
     *         source: array{provider: string, title: string, url: string, license: string}}|null,
     *     points: list<array{0: int, 1: float|null, 2: int|null}>,
     *     structures: list<array{from_m: int, to_m: int, kind: string}>,
     *     altitude_sources: array{bdtopo: int, rge_alti: int, interpolated: int, missing: int},
     *     gaps: list<array{from_m: int, to_m: int}>,
     *     branches: list<array{from_m: int, to_m: int}>,
     *     terrain_ranges: list<array{from_m: int, to_m: int}>,
     *     stations: list<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null, status?: string, offset_m?: int}>,
     *     computed_at: string,
     *     provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     * }
     */
    public function present(LineProfile $profile, ?int $maxPoints = null): array
    {
        $points = self::compactPoints($profile);

        return [
            'basis' => $profile->era_id === null ? 'reference' : 'era',
            'era_year' => $profile->era === null ? null : (int) $profile->era->year,
            'step_m' => $profile->step_m,
            'length_m' => $profile->length_m,
            'trace_length_m' => $profile->trace_length_m,
            'covered_m' => $profile->covered_m,
            'off_path_m' => $profile->off_path_m,
            'pk_anchors' => $profile->pk_anchors,
            'summary' => [
                'min_altitude_m' => $profile->min_altitude_m,
                'max_altitude_m' => $profile->max_altitude_m,
                'ascent_m' => $profile->ascent_m,
                'descent_m' => $profile->descent_m,
                'max_grade_permil' => $profile->max_grade_permil,
                'max_grade_from_m' => $profile->max_grade_from_m,
                'max_grade_to_m' => $profile->max_grade_to_m,
                'max_grade_direction' => $profile->max_grade_direction,
                'max_grade_withheld' => $profile->max_grade_withheld,
                'climb_method' => self::climbMethod(),
            ],
            'rack' => RackRailways::present($profile->line->slug),
            'points' => $maxPoints === null ? $points : self::downsample($points, $maxPoints, $profile->gaps),
            'structures' => $profile->structures,
            'altitude_sources' => $profile->altitude_sources,
            'gaps' => $profile->gaps,
            'branches' => $profile->branches ?? [],
            'terrain_ranges' => $this->terrainRanges($profile),
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
     * @template P of array{0: int, 1: float|null}
     *
     * @param  list<P>  $points
     * @param  list<array{from_m: int, to_m: int}>  $gaps  lacunes du parcours (`line_profiles.gaps`)
     * @return list<P>
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

    /**
     * Points du profil sous leur forme compacte [distance_m, altitude_m|null, pk_m|null].
     *
     * @return list<array{0: int, 1: float|null, 2: int|null}>
     */
    public static function compactPoints(LineProfile $profile): array
    {
        return array_map(fn (object $r): array => [
            (int) $r->distance_m,
            $r->altitude_m === null ? null : (float) $r->altitude_m,
            $r->pk_m === null ? null : (int) $r->pk_m,
        ], DB::select(
            'SELECT distance_m, altitude_m, pk_m FROM line_profile_points WHERE line_profile_id = ? ORDER BY seq',
            [$profile->id],
        ));
    }

    /**
     * Parties du parcours dont l'altitude vient du terrain (RGE ALTI) et non de la voie : suites de
     * points consécutifs `rge_alti` dans le même morceau, en distances.
     *
     * @return list<array{from_m: int, to_m: int}>
     */
    public function terrainRanges(LineProfile $profile): array
    {
        $rows = DB::select(
            'SELECT distance_m, source, piece FROM line_profile_points WHERE line_profile_id = ? ORDER BY seq',
            [$profile->id],
        );
        $ranges = [];
        $open = null;
        $prev = null;
        foreach ($rows as $r) {
            $terrain = $r->source === 'rge_alti';
            if ($open !== null && (! $terrain || $r->piece !== $prev->piece)) {
                $ranges[] = ['from_m' => $open, 'to_m' => (int) $prev->distance_m];
                $open = null;
            }
            if ($terrain && $open === null) {
                $open = (int) $r->distance_m;
            }
            $prev = $r;
        }
        if ($open !== null && $prev !== null) {
            $ranges[] = ['from_m' => $open, 'to_m' => (int) $prev->distance_m];
        }

        return $ranges;
    }

    /**
     * Points détaillés du référentiel d'altitude (API `GET /api/v1/lines/{line}/profile/points`,
     * GraphQL `LineProfile.samples`) : distance, PK estimé, altitude, provenance, interpolation,
     * ouvrage, morceau, embranchement et position (retenue, `lat` et `lng` nuls, quand le tracé sous
     * le point vient d'une source non redistribuable : règle de LineTraceExport, motif dans
     * `position_withheld`), filtrés sur un intervalle de PK (bornes incluses) puis allégés
     * à `$max` points au plus (même règle que downsample : bouts, extrêmes et bords des trous gardés).
     *
     * @return list<array{seq: int, distance_m: int, pk_m: int|null, altitude_m: float|null, source: string|null,
     *     interpolated: bool, structure: string|null, piece: int, branch: int|null, lat: float|null, lng: float|null,
     *     position_withheld: string|null}>
     */
    public function detailedPoints(LineProfile $profile, ?int $pkFrom = null, ?int $pkTo = null, ?int $max = null): array
    {
        $rows = DB::select(
            'SELECT seq, distance_m, pk_m, altitude_m, source, interpolated, structure, piece, branch, trace_source,
                    ST_Y(geom) AS lat, ST_X(geom) AS lng
             FROM line_profile_points
             WHERE line_profile_id = ? AND (?::int IS NULL OR pk_m >= ?) AND (?::int IS NULL OR pk_m <= ?)
             ORDER BY seq',
            [$profile->id, $pkFrom, $pkFrom, $pkTo, $pkTo],
        );
        $points = array_map(fn (object $r): array => [
            'seq' => (int) $r->seq,
            'distance_m' => (int) $r->distance_m,
            'pk_m' => $r->pk_m === null ? null : (int) $r->pk_m,
            'altitude_m' => $r->altitude_m === null ? null : (float) $r->altitude_m,
            'source' => $r->source,
            'interpolated' => (bool) $r->interpolated,
            'structure' => $r->structure,
            'piece' => (int) $r->piece,
            'branch' => $r->branch === null ? null : (int) $r->branch,
            ...$this->position($r),
        ], $rows);
        if ($max === null || count($points) <= $max) {
            return $points;
        }
        $kept = self::downsample(array_map(fn (array $p): array => [$p['distance_m'], $p['altitude_m'], $p['seq']], $points), $max, $profile->gaps);
        $bySeq = array_column($points, null, 'seq');

        return array_map(fn (array $k): array => $bySeq[$k[2]], $kept);
    }

    /**
     * Altitude du référentiel à un PK : interpolée entre les deux points qui l'encadrent dans le
     * même morceau (position retenue, motif dans `position_withheld`, si le tracé de l'un des deux
     * points n'est pas redistribuable) ; `interpolated` vrai si l'un des deux l'est, `sources` = provenance de leurs mesures. Null hors du profil, dans un trou ou sans PK estimé (ligne sans gare de calage).
     *
     * @return array{pk_m: int, altitude_m: float, distance_m: int, interpolated: bool, sources: list<string>,
     *     structure: string|null, lat: float|null, lng: float|null, position_withheld: string|null}|null
     */
    public function altitudeAtPk(LineProfile $profile, int $pk): ?array
    {
        $below = DB::selectOne(
            'SELECT seq, distance_m, pk_m, altitude_m, source, interpolated, structure, piece, trace_source, ST_Y(geom) AS lat, ST_X(geom) AS lng
             FROM line_profile_points WHERE line_profile_id = ? AND pk_m <= ? ORDER BY pk_m DESC, seq DESC LIMIT 1',
            [$profile->id, $pk],
        );
        $above = DB::selectOne(
            'SELECT seq, distance_m, pk_m, altitude_m, source, interpolated, structure, piece, trace_source, ST_Y(geom) AS lat, ST_X(geom) AS lng
             FROM line_profile_points WHERE line_profile_id = ? AND pk_m >= ? ORDER BY pk_m, seq LIMIT 1',
            [$profile->id, $pk],
        );
        if ($below === null || $above === null || $below->altitude_m === null || $above->altitude_m === null
            || $below->piece !== $above->piece || $below->lat === null || $above->lat === null) {
            return null;
        }
        $span = (int) $above->pk_m - (int) $below->pk_m;
        $t = $span > 0 ? ($pk - (int) $below->pk_m) / $span : 0.0;
        $lerp = fn (float $a, float $b): float => $a + $t * ($b - $a);
        $sources = array_values(array_unique(array_filter([$below->source, $above->source])));
        $withheld = $this->rights->withheldReasonForSources([$below->trace_source, $above->trace_source]);

        return [
            'pk_m' => $pk,
            'altitude_m' => round($lerp((float) $below->altitude_m, (float) $above->altitude_m), 1),
            'distance_m' => (int) round($lerp((float) $below->distance_m, (float) $above->distance_m)),
            'interpolated' => (bool) $below->interpolated || (bool) $above->interpolated,
            'sources' => $sources,
            'structure' => $below->structure ?? $above->structure,
            'lat' => $withheld === null ? round($lerp((float) $below->lat, (float) $above->lat), 6) : null,
            'lng' => $withheld === null ? round($lerp((float) $below->lng, (float) $above->lng), 6) : null,
            'position_withheld' => $withheld,
        ];
    }

    /**
     * Position d'un point pour l'API : nulle, avec le motif (`licence_not_open`, `unknown_source`),
     * quand le tracé sous le point vient d'une source non redistribuable (règle de LineTraceExport,
     * `API_WITHHOLD_UNLICENSED_GEOMETRY`). Distance, PK et altitude restent donnés.
     *
     * @return array{lat: float|null, lng: float|null, position_withheld: string|null}
     */
    private function position(object $row): array
    {
        $withheld = $this->rights->withheldReasonForSources([$row->trace_source]);
        if ($withheld !== null || $row->lat === null) {
            return ['lat' => null, 'lng' => null, 'position_withheld' => $withheld];
        }

        return ['lat' => round((float) $row->lat, 6), 'lng' => round((float) $row->lng, 6), 'position_withheld' => null];
    }
}
