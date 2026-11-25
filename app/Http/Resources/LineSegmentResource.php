<?php

namespace App\Http\Resources;

use App\Models\LineSegment;
use App\Services\Api\Provenance;
use App\Services\TraceExport\LineTraceExport;
use App\Support\RedistributableDates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tronçon d'une ligne à une ère (portion homogène du tracé : même état, même trafic, mêmes
 * caractéristiques techniques), avec la provenance de son tracé. La géométrie GeoJSON n'est jointe
 * que si la requête la demande (`geometry=true`, colonne calculée `geojson`), et seulement pour un tronçon
 * dont la source autorise la redistribution (LineTraceExport::redistributable) : sinon `geometry` vaut
 * null, `geometry_withheld` true et `geometry_withheld_reason` donne la raison.
 *
 * @mixin LineSegment
 */
class LineSegmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $withGeometry = $this->resource->getAttribute('geojson') !== null;
        $withheld = app(LineTraceExport::class)->withheldReason($this->source);
        $gauge = app(RedistributableDates::class)->servesGauge($this->gauge_source);

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
            // Écartement lu dans une source non commerciale : nul hors du site (RedistributableDates::servesGauge).
            'gauge_mm' => $gauge ? $this->gauge_mm : null,
            'gauge_class' => $gauge ? $this->gauge_class : null,
            'signalling' => $this->signalling,
            'train_protection' => $this->train_protection,
            'operator' => $this->operator,
            'dismantled_at' => $this->dismantled_at !== null ? substr((string) $this->getRawOriginal('dismantled_at'), 0, 10) : null,
            'reuse' => $this->reuse,
            'source' => $this->source,
            'provenance' => app(Provenance::class)->for('line_segments', $this->source),
            'geometry' => $this->when($withGeometry, fn () => $withheld === null ? json_decode((string) $this->resource->getAttribute('geojson'), true) : null),
            'geometry_withheld' => $this->when($withGeometry, $withheld !== null),
            // `licence_not_open` ou `unknown_source` (LineTrace), null si la géométrie est jointe.
            'geometry_withheld_reason' => $this->when($withGeometry, $withheld),
        ];
    }
}
