<?php

namespace App\Models;

use App\Support\RedistributableDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement daté de la vie d'une gare (ouverture, fermeture, démolition…), avec sa source.
 *
 * `type` reste une chaîne : les sources en apportent d'autres que l'ouverture et la fermeture
 * (ex. `demolition`). `is_inferred` : événement déduit par VFF des lignes ou du tracé, non
 * relevé tel quel dans une source.
 */
class StationEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'event_date' => 'date',
        'is_inferred' => 'boolean',
    ];

    /**
     * Événements servis hors du site (API, GraphQL, MCP) : jamais un événement sans source redistribuable, ni une
     * date déduite d'un tel événement de ligne (App\Support\RedistributableDates).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRedistributable(Builder $query): Builder
    {
        return $query->whereRaw(app(RedistributableDates::class)->stationEventKept($this->getTable()));
    }

    /** @return BelongsTo<Station, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
