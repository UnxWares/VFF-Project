<?php

namespace App\Services\StationService;

use App\Enums\TransitMode;
use InvalidArgumentException;

/**
 * Un flux GTFS lu par vff:import-station-service (database/data/station_service_feeds.php).
 */
final readonly class FeedDefinition
{
    /** Code UIC français à 8 chiffres (87 + 5 chiffres + clé) isolé dans un identifiant. */
    public const FRENCH_UIC = '/(?<![0-9])(87[0-9]{6})(?![0-9])/';

    /**
     * @param  list<TransitMode>  $modes  modes gardés (les autres lignes sont ignorées)
     * @param  list<string>  $uicFields  colonnes de stops.txt où chercher un code UIC
     * @param  array{dataset: int, resource: int, day: string}|null  $history  version archivée par transport.data.gouv.fr
     *                                                                         (la plus proche du jour « MM-JJ » passé le plus récent)
     * @param  array{string, string}|null  $window  fenêtre lue (« MM-JJ », année de la version archivée) ; défaut : tout l'horizon
     * @param  list<string>  $departments  départements que seul ce flux couvre (Corse : CFC)
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $provider,
        public string $licence,
        public string $page,
        public ?string $url,
        public array $modes,
        public bool $tramIsRail = false,
        public ?string $busStopPattern = null,
        public ?string $routePattern = null,
        public ?string $busTripPattern = null,
        public array $uicFields = ['stop_id', 'parent_station', 'stop_code', 'zone_id'],
        public string $uicPattern = self::FRENCH_UIC,
        public bool $tourist = false,
        public ?FeedRole $reference = null,
        public bool $optIn = false,
        public ?array $history = null,
        public ?array $window = null,
        public array $departments = [],
    ) {
        if ($url === null && $history === null) {
            throw new InvalidArgumentException("Flux {$key} : ni url ni history");
        }
    }

    /** @param  array<string, mixed>  $row  entrée de database/data/station_service_feeds.php */
    public static function fromArray(array $row): self
    {
        return new self(
            key: (string) $row['key'],
            name: (string) $row['name'],
            provider: (string) $row['provider'],
            licence: (string) $row['licence'],
            page: (string) $row['page'],
            url: isset($row['url']) ? (string) $row['url'] : null,
            modes: array_map(fn (string $m): TransitMode => TransitMode::from($m), $row['modes']),
            tramIsRail: (bool) ($row['tram_is_rail'] ?? false),
            busStopPattern: $row['bus_stop_pattern'] ?? null,
            routePattern: $row['route_pattern'] ?? null,
            busTripPattern: $row['bus_trip_pattern'] ?? null,
            uicFields: $row['uic_fields'] ?? ['stop_id', 'parent_station', 'stop_code', 'zone_id'],
            uicPattern: $row['uic_pattern'] ?? self::FRENCH_UIC,
            tourist: (bool) ($row['tourist'] ?? false),
            reference: isset($row['reference']) ? FeedRole::from($row['reference']) : null,
            optIn: (bool) ($row['opt_in'] ?? false),
            history: $row['history'] ?? null,
            window: $row['window'] ?? null,
            departments: $row['departments'] ?? [],
        );
    }

    public function keeps(TransitMode $mode): bool
    {
        return in_array($mode, $this->modes, true);
    }

    /** Jour visé par une version archivée : « MM-JJ » de cette année s'il est passé, sinon de l'année précédente. */
    public function targetDay(int $today): ?int
    {
        if ($this->history === null) {
            return null;
        }
        $year = intdiv($today, 10000);
        $target = Ymd::parse($year.'-'.$this->history['day']);

        return $target <= $today ? $target : Ymd::parse(($year - 1).'-'.$this->history['day']);
    }

    /**
     * Fenêtre de dates à lire : la fenêtre déclarée (année du jour visé), sinon à partir
     * d'aujourd'hui pour la desserte du jour, sinon tout l'horizon (null).
     *
     * @return array{?int, ?int}
     */
    public function readWindow(int $today): array
    {
        if ($this->window !== null) {
            $year = intdiv($this->targetDay($today) ?? $today, 10000);

            return [Ymd::parse("{$year}-{$this->window[0]}"), Ymd::parse("{$year}-{$this->window[1]}")];
        }

        return [$this->reference === null ? $today : null, null];
    }
}
