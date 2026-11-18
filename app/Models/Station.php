<?php

namespace App\Models;

use App\Enums\StationStatus;
use App\Enums\StationType;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\StationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gare ou halte (actuelle ou disparue), dédoublonnée depuis plusieurs sources
 * (`station_sources` : SNCF, Wikidata, OSM, OHM, listes par ligne).
 *
 *   - status        : statut affiché aujourd'hui, plafonné par l'état des lignes ;
 *   - source_status : statut selon les sources (avant plafonnement) ;
 *   - provenance    : attribut → source retenue ;
 *   - chronologie   : `station_events`, présence par décennie : `station_eras`.
 */
class Station extends Model
{
    /** @use HasFactory<StationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'geom' => Point::class,
        'type' => StationType::class,
        'status' => StationStatus::class,
        'alt_names' => 'array',
        'provenance' => 'array',
        'opened_at' => 'date',
        'closed_at' => 'date',
        'position_flagged' => 'boolean',
    ];

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Lignes rattachées (station_lines : méthode, PK, période de desserte).
     *
     * @return BelongsToMany<Line, $this>
     */
    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'station_lines')
            ->withPivot(['method', 'pk_m', 'pk_source', 'distance_m', 'first_year', 'last_year', 'years_source'])
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
