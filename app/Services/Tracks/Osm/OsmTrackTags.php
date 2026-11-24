<?php

declare(strict_types=1);

namespace App\Services\Tracks\Osm;

/**
 * How the OpenStreetMap tags of the track layout are read (pure, tests: tests/Unit/Tracks/OsmTrackTagsTest.php).
 * Nothing is guessed: a missing track number stays null, an unknown value stays unknown.
 */
final class OsmTrackTags
{
    /** Signal categories, in the order the kind of a signal is chosen when it carries several. */
    public const SIGNAL_CATEGORIES = [
        'main', 'combined', 'distant', 'route', 'route_distant', 'shunting', 'shunting_route', 'minor', 'crossing',
        'crossing_distant', 'speed_limit', 'speed_limit_distant', 'speed_limit_reminder', 'stop', 'stop_demand',
        'train_protection', 'electricity', 'wrong_road', 'departure', 'whistle', 'station_distant', 'facility_distant',
        'ring', 'brake_test', 'snowplow', 'resetting_switch', 'switch', 'catenary_mast',
    ];

    /** Kind of a track: running, siding (loops are told apart later, by topology), yard, crossover or spur. */
    public static function kind(array $tags): string
    {
        return match ($tags['service'] ?? null) {
            null, '', 'main' => 'running',
            'yard' => 'yard',
            'spur' => 'spur',
            'crossover' => 'crossover',
            default => 'siding',
        };
    }

    /**
     * Track number from `railway:track_ref`: « V1 » → « 1 », « 2bis » → « 2bis », « UNIQUE » or « VU » → « U »;
     * null when absent or when the value is a line code (a six digit number put there by mistake).
     */
    public static function trackRef(array $tags): ?string
    {
        $raw = trim((string) ($tags['railway:track_ref'] ?? ''));
        if ($raw === '' || preg_match('/^\d{5,6}$/', $raw) === 1 || mb_strlen($raw) > 12) {
            return null;
        }
        $upper = mb_strtoupper($raw);
        if (in_array($upper, ['U', 'UNIQUE', 'VU', 'VOIE UNIQUE'], true)) {
            return 'U';
        }
        if (preg_match('/^V\s?(\d+)(.*)$/i', $raw, $m) === 1) {
            $raw = $m[1].$m[2];
        }

        return preg_replace_callback('/bis$/i', fn (): string => 'bis', $raw) ?? $raw;
    }

    /** PK in metres from an OSM position (« 546.707 », « 22 », « 22+500 », « 12,3 »); null otherwise (miles, text). */
    public static function position(?string $value): ?int
    {
        $v = trim((string) $value);
        if (preg_match('/^(-?\d{1,4})\+(\d{1,3})$/', $v, $m) === 1) {
            $sign = str_starts_with($m[1], '-') ? -1 : 1;

            return (int) $m[1] * 1000 + $sign * (int) str_pad($m[2], 3, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^-?\d{1,4}(?:[.,]\d{1,4})?$/', $v) === 1) {
            return (int) round((float) str_replace(',', '.', $v) * 1000);
        }

        return null;
    }

    /** True / false from `electrified`, null when absent or unknown. */
    public static function electrified(array $tags): ?bool
    {
        return match ($tags['electrified'] ?? null) {
            'contact_line', 'rail', 'yes', '4th_rail', 'ground-level_power_supply' => true,
            'no' => false,
            default => null,
        };
    }

    /** First integer of a tag (« 1500 », « 25000;1500 », « 160 », « 90 mph » → null), or null. */
    public static function integer(array $tags, string $key, int $max): ?int
    {
        $v = trim(explode(';', (string) ($tags[$key] ?? ''))[0]);

        return preg_match('/^\d+$/', $v) === 1 && (int) $v > 0 && (int) $v <= $max ? (int) $v : null;
    }

    /** Switch type: simple (default, wye, abt...), single_slip, double_slip or three_way. */
    public static function switchKind(array $tags): string
    {
        return match ($tags['railway:switch'] ?? null) {
            'single_slip' => 'single_slip',
            'double_slip' => 'double_slip',
            'three_way' => 'three_way',
            default => 'simple',
        };
    }

    /**
     * Category of a signal (`railway:signal:<category>=<form>`), its form and its number.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    public static function signal(array $tags): array
    {
        foreach (self::SIGNAL_CATEGORIES as $cat) {
            $form = $tags["railway:signal:{$cat}"] ?? null;
            if ($form !== null && $form !== 'no') {
                $ref = $tags["railway:signal:{$cat}:ref"] ?? $tags['ref'] ?? null;

                return [$cat, mb_substr($form, 0, 60), $ref === null ? null : mb_substr($ref, 0, 40)];
            }
        }

        return [null, null, isset($tags['ref']) ? mb_substr($tags['ref'], 0, 40) : null];
    }
}
