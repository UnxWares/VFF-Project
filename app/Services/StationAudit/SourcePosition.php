<?php

namespace App\Services\StationAudit;

/**
 * Une position de la gare donnée par une source, comparée à la position retenue par VFF.
 *
 * `derived` : position recopiée d'une autre source (gare d'une liste par PK posée sur une gare déjà
 * connue, properties.geocoding = station) ; elle n'est pas une confirmation indépendante.
 *
 * `distanceM` : écart à la position retenue (pour une zone BD TOPO, écart au contour : 0 si la
 * gare est dedans) ; `trackM` : écart à la voie la plus proche des lignes de la gare.
 */
final readonly class SourcePosition
{
    public function __construct(
        public string $source,
        public string $ref,
        public float $lon,
        public float $lat,
        public ?int $distanceM,
        public ?int $trackM = null,
        public ?string $name = null,
        public ?int $precisionM = null,
        /** Copie d'une autre position (gare d'une liste posée sur une gare connue) : montrée, jamais comptée. */
        public bool $derived = false,
    ) {}

    public function family(): string
    {
        return PlacementGrader::family($this->source);
    }

    public function precise(): bool
    {
        return PlacementGrader::isPrecise($this->source, $this->precisionM);
    }

    /** @return array<string, mixed> forme enregistrée dans station_audits.positions */
    public function toArray(bool $retained): array
    {
        return [
            'source' => $this->source,
            'family' => $this->family(),
            'ref' => $this->ref,
            'name' => $this->name,
            'lon' => round($this->lon, 7),
            'lat' => round($this->lat, 7),
            'distance_m' => $this->distanceM,
            'track_m' => $this->trackM,
            'precision_m' => $this->precisionM,
            'precise' => $this->precise(),
            'derived' => $this->derived,
            'retained' => $retained,
        ];
    }
}
