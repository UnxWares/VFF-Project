<?php

namespace App\Models;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Enums\AccidentLocationPrecision;
use App\Enums\AccidentReviewStatus;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\AccidentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Accident ou événement grave du rail français (vff:import-accidents).
 *
 * La position (colonne PostGIS `geom`) se lit avec le scope withCoordinates() (attributs `lat`,
 * `lng`). Le nom et le résumé sont par langue (fr, en, de) ; le résumé est rédigé par VFF à
 * partir des faits (App\Services\Accidents\AccidentSummary), jamais recopié d'une source.
 *
 * @property int $id
 * @property string $slug
 * @property string|null $wikidata_id
 * @property Carbon $occurred_on
 * @property AccidentDatePrecision $date_precision
 * @property string|null $occurred_at_time
 * @property AccidentKind $kind
 * @property array<string, string> $name
 * @property string|null $commune_insee
 * @property string|null $commune_name
 * @property string|null $department_code
 * @property AccidentLocationPrecision|null $location_precision
 * @property string|null $location_source
 * @property int|null $nearest_station_id
 * @property int|null $nearest_station_distance_m
 * @property int|null $deaths
 * @property int|null $injuries
 * @property string|null $toll_source
 * @property string|null $toll_source_url
 * @property list<array<string, string|null>>|null $trains
 * @property array<string, array{title: string, url: string, revid: int|null}>|null $wikipedia
 * @property array<string, string> $summary
 * @property AccidentReviewStatus $review_status
 * @property list<array{code: string, detail: string}>|null $review_notes
 * @property array<string, string>|null $provenance
 * @property Carbon $imported_at
 * @property Point|null $geom
 * @property float|null $lat
 * @property float|null $lng
 */
class Accident extends Model
{
    /** @use HasFactory<AccidentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return [
            'geom' => Point::class,
            'occurred_on' => 'date',
            'date_precision' => AccidentDatePrecision::class,
            'kind' => AccidentKind::class,
            'location_precision' => AccidentLocationPrecision::class,
            'review_status' => AccidentReviewStatus::class,
            'name' => 'array',
            'trains' => 'array',
            'wikipedia' => 'array',
            'summary' => 'array',
            'review_notes' => 'array',
            'provenance' => 'array',
            'deaths' => 'integer',
            'injuries' => 'integer',
            'nearest_station_distance_m' => 'integer',
            'imported_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Lignes rattachées, avec le détail du rattachement (portion, confiance).
     *
     * @return HasMany<AccidentLine, $this>
     */
    public function lineLinks(): HasMany
    {
        return $this->hasMany(AccidentLine::class);
    }

    /** @return BelongsToMany<Line, $this> */
    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'accident_lines')
            ->withPivot(['is_primary', 'era_year', 'distance_m', 'pk_m', 'section_from_station_id', 'section_to_station_id', 'method', 'confidence', 'score'])
            ->withTimestamps();
    }

    /** @return HasMany<AccidentSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(AccidentSource::class);
    }

    /** @return BelongsTo<Station, $this> */
    public function nearestStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'nearest_station_id');
    }

    /** Nom dans la langue demandée, en français à défaut. */
    public function nameIn(string $locale): string
    {
        return $this->name[$locale] ?? $this->name['fr'] ?? $this->slug;
    }

    /**
     * Ajoute `lat` et `lng` (WGS 84) aux colonnes lues.
     *
     * @param  Builder<Accident>  $query
     */
    #[Scope]
    protected function withCoordinates(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('accidents.*');
        }
        $query->selectRaw('ST_Y(accidents.geom) AS lat, ST_X(accidents.geom) AS lng');
    }

    /**
     * Accidents survenus entre deux années (bornes incluses, chacune facultative).
     *
     * @param  Builder<Accident>  $query
     */
    #[Scope]
    protected function betweenYears(Builder $query, ?int $from, ?int $to): void
    {
        if ($from !== null) {
            $query->where('accidents.occurred_on', '>=', sprintf('%04d-01-01', $from));
        }
        if ($to !== null) {
            $query->where('accidents.occurred_on', '<=', sprintf('%04d-12-31', $to));
        }
    }

    /**
     * Accidents rattachés à une ligne (rattachement principal ou secondaire).
     *
     * @param  Builder<Accident>  $query
     */
    #[Scope]
    protected function onLine(Builder $query, int $lineId): void
    {
        $query->whereExists(fn ($q) => $q->selectRaw('1')->from('accident_lines')
            ->whereColumn('accident_lines.accident_id', 'accidents.id')
            ->where('accident_lines.line_id', $lineId));
    }
}
