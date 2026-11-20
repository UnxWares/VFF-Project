<?php

namespace App\Models;

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

    /** @return BelongsTo<Station, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
