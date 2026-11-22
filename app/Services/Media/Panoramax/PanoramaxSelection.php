<?php

namespace App\Services\Media\Panoramax;

/**
 * Choix des photos Panoramax d'une gare (règles pures, sans réseau ni base).
 *
 * Retenues : instances IGN (Licence Ouverte 2.0) et OpenStreetMap France (CC BY-SA 4.0) seulement, dont les
 * conditions disent que visages et plaques sont floutés à l'envoi ; photo publique et prête ; licence connue ;
 * point à moins de MAX_DISTANCE_M de la gare (recherche à SEARCH_RADIUS_M) ; prise de vue tournée vers la gare
 * (azimut à MAX_ANGLE degrés près du cap photo → gare), même tout près (une photo prise à 7 m de la gare, tournée
 * ailleurs, montrait une route). Au plus MAX_PER_STATION
 * photos, une par séquence, les mieux notées : photo plate avant panorama à 360°, mieux tournée, plus nette
 * (densité de pixels), plus récente, plus précise (position).
 */
final class PanoramaxSelection
{
    /** Rayon de recherche autour de la gare (boîte envoyée au catalogue). */
    public const SEARCH_RADIUS_M = 110;

    /**
     * Distance et écart d'azimut retenus : mesurés le 22/11 sur 15 photos vérifiées à la main, 110 m et 45° donnaient
     * une photo sur trois sans la gare (route, haie) ; 60 m et 15° en gardent 6, dont 5 montrent la gare.
     */
    public const MAX_DISTANCE_M = 60;

    public const MAX_ANGLE = 15;

    public const MAX_PER_STATION = 3;

    /** Instance (nom dans le catalogue fédéré) => [libellé, adresse]. */
    public const INSTANCES = [
        'ign' => ['IGN', 'https://panoramax.ign.fr'],
        'osm-fr' => ['OpenStreetMap France', 'https://panoramax.openstreetmap.fr'],
    ];

    /** Hôtes des images des deux instances (déclarés dans `img-src` de config/csp.php). */
    public const IMAGE_HOSTS = ['panoramax.ign.fr', 'panoramax-storage-public-fast.s3.gra.perf.cloud.ovh.net', 'panoramax.openstreetmap.fr'];

    /** Licence déclarée par l'élément => [libellé, adresse, partage à l'identique]. */
    public const LICENSES = [
        'etalab-2.0' => ['Licence Ouverte 2.0', 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/', false],
        'CC-BY-SA-4.0' => ['CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/', true],
        'CC-BY-4.0' => ['CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/', false],
    ];

    /**
     * Motif de refus d'une photo, null si elle peut être retenue.
     *
     * @param  array<string, mixed>  $f  élément GeoJSON du catalogue
     */
    public static function rejectReason(array $f, float $stationLat, float $stationLng): ?string
    {
        $p = (array) ($f['properties'] ?? []);
        [$lng, $lat] = self::position($f) ?? [null, null];

        return match (true) {
            $lat === null => 'no_position',
            ! isset(self::INSTANCES[self::instance($f) ?? '']) => 'instance',
            ($p['geovisio:status'] ?? 'ready') !== 'ready' || ($p['geovisio:visibility'] ?? 'anyone') !== 'anyone' => 'not_public',
            ! isset(self::LICENSES[(string) ($p['license'] ?? '')]) => 'license',
            self::thumb($f) === null || trim(self::producer($f)) === '' => 'incomplete',
            self::distanceM($lat, $lng, $stationLat, $stationLng) > self::MAX_DISTANCE_M => 'too_far',
            self::angle($f, $stationLat, $stationLng) > self::MAX_ANGLE => 'facing_away',
            default => null,
        };
    }

    /**
     * Photos retenues pour une gare, dans l'ordre de leur note.
     *
     * @param  list<array<string, mixed>>  $features
     * @param  array<string, int>  $rejected  compteurs des photos écartées, par motif (complétés)
     * @return list<array<string, mixed>>
     */
    public static function pick(array $features, float $stationLat, float $stationLng, array &$rejected = []): array
    {
        $ok = [];
        foreach ($features as $f) {
            $reason = self::rejectReason($f, $stationLat, $stationLng);
            if ($reason !== null) {
                $rejected[$reason] = ($rejected[$reason] ?? 0) + 1;

                continue;
            }
            $ok[] = [$f, self::score($f, $stationLat, $stationLng)];
        }
        usort($ok, fn (array $a, array $b): int => $b[1] <=> $a[1] ?: strcmp((string) $a[0]['id'], (string) $b[0]['id']));

        $out = [];
        $sequences = [];
        foreach ($ok as [$f]) {
            $seq = (string) ($f['collection'] ?? $f['id']);
            if (isset($sequences[$seq])) {
                continue;
            }
            $sequences[$seq] = true;
            $out[] = $f;
            if (count($out) >= self::MAX_PER_STATION) {
                break;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $f */
    public static function score(array $f, float $stationLat, float $stationLng): float
    {
        $p = (array) ($f['properties'] ?? []);
        [$lng, $lat] = self::position($f) ?? [0.0, 0.0];
        $fov = (int) ($p['pers:interior_orientation']['field_of_view'] ?? 0);
        $angle = self::angle($f, $stationLat, $stationLng);
        $density = (float) ($p['panoramax:horizontal_pixel_density'] ?? 0);
        $accuracy = (float) ($p['quality:horizontal_accuracy'] ?? 10);
        $year = (int) substr((string) ($p['datetime'] ?? '2015'), 0, 4);

        return ($fov > 0 && $fov < 360 ? 2.0 : 0.0)
            + 2.0 * (1 - min($angle, 180.0) / 180)
            + min($density, 40.0) / 40
            + max(0, min($year - 2015, 12)) / 12
            - ($accuracy > 5 ? 0.5 : 0.0)
            - 0.5 * self::distanceM($lat, $lng, $stationLat, $stationLng) / self::MAX_DISTANCE_M;
    }

    /**
     * Écart (degrés, 0 à 180) entre l'azimut de la prise de vue et le cap photo → gare ; 90 si l'azimut manque.
     *
     * @param  array<string, mixed>  $f
     */
    public static function angle(array $f, float $stationLat, float $stationLng): float
    {
        $az = $f['properties']['view:azimuth'] ?? null;
        $pos = self::position($f);
        if (! is_numeric($az) || $pos === null) {
            return 90.0;
        }
        $bearing = self::bearing($pos[1], $pos[0], $stationLat, $stationLng);
        $d = fmod(abs((float) $az - $bearing), 360.0);

        return $d > 180 ? 360 - $d : $d;
    }

    /** @param  array<string, mixed>  $f */
    public static function instance(array $f): ?string
    {
        foreach ((array) ($f['links'] ?? []) as $l) {
            if (($l['rel'] ?? null) === 'via' && is_string($l['instance_name'] ?? null)) {
                return $l['instance_name'];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $f */
    public static function producer(array $f): string
    {
        foreach ((array) ($f['providers'] ?? []) as $p) {
            if (in_array('producer', (array) ($p['roles'] ?? []), true) && is_string($p['name'] ?? null)) {
                return $p['name'];
            }
        }

        return (string) ($f['properties']['geovisio:producer'] ?? '');
    }

    /** @param  array<string, mixed>  $f */
    public static function thumb(array $f): ?string
    {
        return self::imageUrl($f['assets']['thumb']['href'] ?? null);
    }

    /** @param  array<string, mixed>  $f */
    public static function display(array $f): ?string
    {
        return self::imageUrl($f['assets']['sd']['href'] ?? null);
    }

    /** Adresse https d'une image hébergée par une des deux instances, null sinon. */
    private static function imageUrl(mixed $href): ?string
    {
        return is_string($href) && str_starts_with($href, 'https://')
            && in_array(parse_url($href, PHP_URL_HOST), self::IMAGE_HOSTS, true) ? $href : null;
    }

    /**
     * [lng, lat] de la photo.
     *
     * @param  array<string, mixed>  $f
     * @return ?array{0: float, 1: float}
     */
    public static function position(array $f): ?array
    {
        $c = $f['geometry']['coordinates'] ?? null;

        return is_array($c) && is_numeric($c[0] ?? null) && is_numeric($c[1] ?? null) ? [(float) $c[0], (float) $c[1]] : null;
    }

    public static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $a = sin(($p2 - $p1) / 2) ** 2 + cos($p1) * cos($p2) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * 6371008.8 * asin(min(1.0, sqrt($a)));
    }

    /** Cap initial (degrés, 0 = nord, sens horaire) d'un point vers un autre. */
    public static function bearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dl = deg2rad($lng2 - $lng1);
        $y = sin($dl) * cos($p2);
        $x = cos($p1) * sin($p2) - sin($p1) * cos($p2) * cos($dl);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }
}
