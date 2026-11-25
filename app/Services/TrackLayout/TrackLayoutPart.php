<?php

namespace App\Services\TrackLayout;

/**
 * Ce qu'une source du schéma des voies sait d'une ligne (voir TrackLayoutSource), au format du contrat
 * `GET /api/v1/lines/{line}/track-layout` :
 *
 * - operational_points : {id, name, kind, pk_m, uopid, station_slug, source, precision}
 *   (kind : station, halt, junction, passing_loop, siding, border, other) ;
 * - tracks : {id, ref, kind, pk_from_m, pk_to_m, op_id, length_m, electrified, platform, source, precision}
 *   (kind : running, siding, loop, yard, crossover, spur ; `lane` est calculée par TrackLayout ; clés facultatives
 *   `parent_ref` : référence de la voie principale d'où part une voie de service, pour la placer de son côté,
 *   `platform_keys` : quais que la voie borde, `aliases` : ses autres numéros, voir RinfTrackLayoutSource) ;
 * - switches : {id, ref, pk_m, kind, from_track, to_track, facing, source, precision}
 *   (kind : simple, single_slip, double_slip, three_way, diamond ; facing : up, down ou null) ;
 * - signals : {id, ref, pk_m, kind, track, direction, source, precision} ;
 * - crossings : {id, pk_m, kind, ref, source} (kind : level_crossing, railway_crossing) ;
 * - buffer_stops : {id, pk_m, track, source, precision} (heurtoirs) ;
 * - milestones : {id, pk_m, label, source, precision} (bornes kilométriques, `label` tel qu'écrit sur la borne) ;
 * - speeds : {pk_from_m, pk_to_m, kmh, tracks?, source} ; gradients : {pk_from_m, pk_to_m, permille, tracks?, source,
 *   precision} (`tracks` : références courtes des voies concernées, quand la source les dit).
 *
 * `precision` : exact (position déclarée par la source), derived (calculée par VFF à partir de la source),
 * approximate (placée à peu près : voie d'une gare centrée sur le PK de la gare, par exemple). « up » = sens
 * des PK croissants.
 */
final readonly class TrackLayoutPart
{
    /** Familles d'objets du schéma, dans l'ordre de la réponse. */
    public const FAMILIES = ['operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones', 'speeds', 'gradients'];

    /**
     * @param  array{key: string, label: string, licence: string, dataset: ?string, fetched_at: ?string, url: ?string}|null  $source
     * @param  list<array<string, mixed>>  $operationalPoints
     * @param  list<array<string, mixed>>  $tracks
     * @param  list<array<string, mixed>>  $switches
     * @param  list<array<string, mixed>>  $signals
     * @param  list<array<string, mixed>>  $crossings
     * @param  list<array<string, mixed>>  $speeds
     * @param  list<array<string, mixed>>  $gradients
     * @param  array<string, list<array{0: int, 1: int}>>  $covered  famille => portions [début, fin] décrites par la source
     * @param  list<array<string, mixed>>  $bufferStops
     * @param  list<array<string, mixed>>  $milestones
     */
    public function __construct(
        public ?array $source = null,
        public array $operationalPoints = [],
        public array $tracks = [],
        public array $switches = [],
        public array $signals = [],
        public array $crossings = [],
        public array $speeds = [],
        public array $gradients = [],
        public array $covered = [],
        public array $bufferStops = [],
        public array $milestones = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->operationalPoints === [] && $this->tracks === [] && $this->switches === [] && $this->signals === []
            && $this->crossings === [] && $this->bufferStops === [] && $this->milestones === [] && $this->speeds === [] && $this->gradients === [];
    }

    /**
     * Objets d'une famille.
     *
     * @return list<array<string, mixed>>
     */
    public function family(string $family): array
    {
        return match ($family) {
            'operational_points' => $this->operationalPoints,
            'tracks' => $this->tracks,
            'switches' => $this->switches,
            'signals' => $this->signals,
            'crossings' => $this->crossings,
            'buffer_stops' => $this->bufferStops,
            'milestones' => $this->milestones,
            'speeds' => $this->speeds,
            'gradients' => $this->gradients,
            default => [],
        };
    }
}
