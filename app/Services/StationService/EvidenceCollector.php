<?php

namespace App\Services\StationService;

use App\Enums\TransitMode;

/**
 * Réunit, gare par gare, la meilleure desserte de chaque sorte parmi les arrêts
 * rapprochés de tous les flux, et la trace de chaque rapprochement.
 */
final class EvidenceCollector
{
    public const SOON_DAYS = 28;

    /** @var array<int, array<string, ServiceProfile>> gare => sorte (propriété de StationEvidence) => desserte */
    private array $best = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $trace = [];

    public function __construct(private readonly ServicePatternDetector $detector) {}

    /** Ajoute la desserte d'un arrêt rapproché, selon le rôle de son flux. */
    public function add(StopMatch $match, GtfsStop $stop, FeedSnapshot $snapshot, FeedRole $role): void
    {
        $entry = ['feed' => $snapshot->feed->key, 'role' => $role->value, 'stop' => $stop->key, 'stop_name' => $stop->name,
            'method' => $match->method->value, 'distance_m' => $match->distanceM, 'flags' => $match->flags];
        $found = [];
        foreach ($stop->services as $mode => $service) {
            $profile = $this->detector->profile($service->days, $snapshot->windowStart, $snapshot->windowEnd);
            $slot = $profile === null ? null : $role->slot(TransitMode::from($mode));
            if ($slot === null) {
                continue;
            }
            $entry[$mode] = $profile->toArray() + ['routes' => array_slice($service->routes, 0, 8)];
            $found[$slot] = $profile;
            if ($slot === 'rail') {
                $soon = $this->detector->profile($service->days, $snapshot->windowStart,
                    min($snapshot->windowEnd, Ymd::addDays($snapshot->windowStart, self::SOON_DAYS - 1)));
                if ($soon !== null) {
                    $found['railSoon'] = $soon;
                }
            }
        }
        foreach ($match->stationIds as $id) {
            foreach ($found as $slot => $profile) {
                if ($profile->isBetterThan($this->best[$id][$slot] ?? null)) {
                    $this->best[$id][$slot] = $profile;
                }
            }
            $this->trace[$id][] = $entry;
        }
    }

    /** Indices réunis pour une gare. */
    public function evidence(int $stationId, HeritageHints $heritage, bool $covered): StationEvidence
    {
        $b = $this->best[$stationId] ?? [];

        return new StationEvidence(rail: $b['rail'] ?? null, railSoon: $b['railSoon'] ?? null, tram: $b['tram'] ?? null,
            bus: $b['bus'] ?? null, summer: $b['summer'] ?? null, spring: $b['spring'] ?? null, stale: $b['stale'] ?? null,
            touristTrains: $b['touristTrains'] ?? null, heritage: $heritage, covered: $covered);
    }

    /** @return list<array<string, mixed>> */
    public function trace(int $stationId): array
    {
        return $this->trace[$stationId] ?? [];
    }
}
