<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Profil en long d'une ligne (table `line_profiles`), écrit par App\Services\Profiles\LineProfileBuilder
 * et lu par App\Services\Profiles\LineProfiles (fiche, API REST, GraphQL, MCP).
 *
 * @property int $id
 * @property int $line_id
 * @property int $era_id
 * @property string $source
 * @property string $resource
 * @property int $step_m
 * @property int $length_m
 * @property int $trace_length_m
 * @property list<array{0: int, 1: float|null}> $points
 * @property list<array{from_m: int, to_m: int, kind: string}> $structures
 * @property array{bdtopo: int, rge_alti: int, interpolated: int, missing: int} $altitude_sources
 * @property list<array{from_m: int, to_m: int}> $gaps
 * @property list<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null}> $stations
 * @property float $min_altitude_m
 * @property float $max_altitude_m
 * @property float $ascent_m
 * @property float $descent_m
 * @property float $max_grade_permil
 * @property int $max_grade_from_m
 * @property int $max_grade_to_m
 * @property string $max_grade_direction
 * @property Carbon $computed_at
 * @property-read Era $era
 * @property-read Line $line
 */
class LineProfile extends Model
{
    protected $fillable = [
        'line_id', 'era_id', 'source', 'resource', 'step_m', 'length_m', 'trace_length_m', 'points',
        'structures', 'altitude_sources', 'gaps', 'stations', 'min_altitude_m', 'max_altitude_m', 'ascent_m', 'descent_m',
        'max_grade_permil', 'max_grade_from_m', 'max_grade_to_m', 'max_grade_direction', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'array',
            'structures' => 'array',
            'altitude_sources' => 'array',
            'gaps' => 'array',
            'stations' => 'array',
            'step_m' => 'integer',
            'length_m' => 'integer',
            'trace_length_m' => 'integer',
            'min_altitude_m' => 'float',
            'max_altitude_m' => 'float',
            'ascent_m' => 'float',
            'descent_m' => 'float',
            'max_grade_permil' => 'float',
            'max_grade_from_m' => 'integer',
            'max_grade_to_m' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** @return BelongsTo<Era, $this> */
    public function era(): BelongsTo
    {
        return $this->belongsTo(Era::class);
    }
}
