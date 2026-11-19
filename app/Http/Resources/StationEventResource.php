<?php

namespace App\Http\Resources;

use App\Models\StationEvent;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement daté de la vie d'une gare. Garde sa provenance et la licence de sa source.
 *
 * @mixin StationEvent
 */
class StationEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'date' => $this->event_date->toDateString(),
            'date_precision' => $this->date_precision ?? 'day',
            'is_inferred' => $this->is_inferred,
            'note' => $this->note,
            'source' => $this->source,
            'source_ref' => $this->source_ref,
            'source_url' => $this->source_url,
            'provenance' => app(Provenance::class)->for('station_events', $this->source, $this->source_url),
            'station' => $this->whenLoaded('station', fn (): array => [
                'slug' => $this->station->slug,
                'name' => $this->station->name,
            ]),
        ];
    }
}
