<?php

namespace App\Models;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Enums\AccidentLocationPrecision;
use App\Enums\AccidentReviewStatus;
use App\Models\Scopes\PublicAccidents;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\AccidentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
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
 * @property array<string, mixed>|null $verification
 * @property Carbon|null $verified_at
 * @property int|null $alias_of_id
 * @property list<array<string, mixed>>|null $context_corrections
 * @property Carbon|null $context_checked_on
 * @property Point|null $geom
 * @property float|null $lat
 * @property float|null $lng
 */
#[ScopedBy([PublicAccidents::class])]
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
            'verification' => 'array',
            'verified_at' => 'datetime',
            'alias_of_id' => 'integer',
            'context_corrections' => 'array',
            'context_checked_on' => 'date',
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

    /**
     * Fiches alias : elles décrivent le même accident (deux éléments Wikidata pour un seul accident),
     * gardées avec leurs sources et jamais publiées.
     *
     * @return HasMany<Accident, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(self::class, 'alias_of_id')->withoutGlobalScopes();
    }

    /**
     * Slug de la fiche publiée qui remplace un ancien slug : celui d'une fiche alias (doublon fusionné,
     * voir `aliases` des fichiers de contexte) dont la fiche retenue est publiée. Nul si ce n'est pas un alias.
     * Sert aux redirections 301 : l'adresse d'une fiche fusionnée ne renvoie jamais 404.
     */
    public static function redirectSlugFor(string $slug): ?string
    {
        $kept = self::withoutGlobalScopes()->where('slug', $slug)->whereNotNull('alias_of_id')->value('alias_of_id');
        // Un alias d'alias est suivi jusqu'à la fiche retenue (au plus 5 pas : pas de boucle possible).
        for ($i = 0; $i < 5 && $kept !== null; $i++) {
            $target = self::withoutGlobalScopes()->whereKey($kept)->first(['id', 'slug', 'alias_of_id']);
            if ($target === null) {
                return null;
            }
            if ($target->alias_of_id === null) {
                return self::query()->whereKey($target->id)->exists() ? $target->slug : null;
            }
            $kept = $target->alias_of_id;
        }

        return null;
    }

    /** @return HasMany<AccidentSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(AccidentSource::class);
    }

    /**
     * Documents consultés pour le contexte (vff:enrich-accidents), dans l'ordre du fichier de données.
     *
     * @return HasMany<AccidentReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(AccidentReference::class)->orderBy('position');
    }

    /**
     * Énoncés du contexte, par section puis dans l'ordre du fichier de données.
     *
     * @return HasMany<AccidentContextEntry, $this>
     */
    public function contextEntries(): HasMany
    {
        return $this->hasMany(AccidentContextEntry::class)->orderBy('position');
    }

    /**
     * Images de Wikimedia Commons (auteur et licence relus sur Commons).
     *
     * @return HasMany<AccidentImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(AccidentImage::class)->orderBy('position');
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
