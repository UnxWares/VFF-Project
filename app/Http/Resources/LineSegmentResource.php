<?php

namespace App\Http\Resources;

use App\Models\LineSegment;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tronçon d'une ligne à une ère (portion homogène du tracé : même état, même trafic, mêmes
 * caractéristiques techniques), avec la provenance de son tracé. La géométrie GeoJSON n'est jointe
 * que si la requête la demande (`geometry=true`, colonne calculée `geojson`).
 *
 * @mixin LineSegment
 */
class LineSegmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pk_start_m' => $this->pk_start_m,
            'pk_end_m' => $this->pk_end_m,
            'length_m' => $this->length_meters,
            // Valeur brute : un statut ajouté en base avant l'énumération PHP ne fait pas échouer la réponse.
            'status' => $this->resource->getRawOriginal('status'),
            'traffic_type' => $this->traffic_type,
            'is_heritage' => $this->is_heritage,
            'heritage_kind' => $this->heritage_kind,
            'electrified' => $this->electrified,
            'voltage_v' => $this->voltage_v,
            'electrified_year' => $this->electrified_year,
            'electrification_source' => $this->electrification_source,
            'max_speed_kmh' => $this->max_speed_kmh,
            'max_speed_source' => $this->max_speed_source,
            'gauge_mm' => $this->gauge_mm,
            'gauge_class' => $this->gauge_class,
            'signalling' => $this->signalling,
            'train_protection' => $this->train_protection,
            'operator' => $this->operator,
            'dismantled_at' => $this->dismantled_at !== null ? substr((string) $this->getRawOriginal('dismantled_at'), 0, 10) : null,
            'reuse' => $this->reuse,
            'source' => $this->source,
            'provenance' => app(Provenance::class)->for('line_segments', $this->source),
            'geometry' => $this->when($this->resource->getAttribute('geojson') !== null, fn () => json_decode((string) $this->resource->getAttribute('geojson'), true)),
        ];
    }
}
