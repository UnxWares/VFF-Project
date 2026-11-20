<?php

namespace App\GraphQL\Builders;

use App\Models\StationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Ordre des événements d'une gare en GraphQL (`Station.events`) : chronologique.
 */
final class StationEventBuilder
{
    /**
     * @param  Builder<StationEvent>|Relation<StationEvent, *, *>  $builder
     * @return Builder<StationEvent>|Relation<StationEvent, *, *>
     */
    public function chronological(Builder|Relation $builder): Builder|Relation
    {
        return $builder->orderBy('station_events.event_date')->orderBy('station_events.id');
    }
}
