<?php

namespace App\Services\StationService;

use App\Enums\TransitMode;

/**
 * Rôle d'un flux dans le classement : desserte du jour, référence de l'été ou du
 * printemps (versions archivées du GTFS SNCF), flux périmé ou train touristique.
 */
enum FeedRole: string
{
    case Current = 'current';
    case Summer = 'summer';
    case Spring = 'spring';
    case Stale = 'stale';
    case Tourist = 'tourist';

    public static function of(FeedSnapshot $snapshot, int $today): self
    {
        return match (true) {
            $snapshot->feed->reference !== null => $snapshot->feed->reference,
            $snapshot->isStaleOn($today) => self::Stale,
            $snapshot->feed->tourist => self::Tourist,
            default => self::Current,
        };
    }

    /** Propriété de StationEvidence alimentée par ce mode, ou null s'il ne compte pas pour ce rôle. */
    public function slot(TransitMode $mode): ?string
    {
        return match ([$this, $mode]) {
            [self::Current, TransitMode::Rail] => 'rail',
            [self::Current, TransitMode::Tram] => 'tram',
            [self::Current, TransitMode::Bus] => 'bus',
            [self::Summer, TransitMode::Rail] => 'summer',
            [self::Spring, TransitMode::Rail] => 'spring',
            [self::Stale, TransitMode::Rail] => 'stale',
            [self::Tourist, TransitMode::Rail] => 'touristTrains',
            default => null,
        };
    }
}
