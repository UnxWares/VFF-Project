<?php

namespace App\Http\Resources;

use App\Models\LineEvent;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement daté de la vie d'une ligne (liste). Garde sa provenance et la licence de sa source.
 *
 * @mixin LineEvent
 */
class LineEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Valeur brute : un type ajouté en base avant l'énumération PHP ne doit pas faire échouer la réponse.
            'type' => $this->resource->getRawOriginal('type'),
            'date' => $this->event_date->toDateString(),
            'date_precision' => $this->date_precision ?? 'day',
            'section_from' => $this->section_from,
            'section_to' => $this->section_to,
            'description' => $this->description,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'provenance' => app(Provenance::class)->for('line_events', $this->source, $this->source_url),
            'line' => $this->whenLoaded('line', fn (): array => [
                'slug' => $this->line->slug,
                'code' => $this->line->code,
                'name' => $this->line->name,
            ]),
        ];
    }
}
