<?php

namespace App\Services\Media\Panoramax;

/**
 * Choix des photos Panoramax d'une gare (règles pures, sans réseau ni base).
 *
 * Retenues : instances IGN (Licence Ouverte 2.0) et OpenStreetMap France (CC BY-SA 4.0) seulement, dont les
 * conditions disent que visages et plaques sont floutés à l'envoi ; photo publique et prête ; licence connue.
 *
 * Règle des abords (avec une StationScene, règle du 22/11 au soir) : la photo doit voir un bâtiment de la gare
 * (BD TOPO, dans l'emprise de la gare) sur MIN_TARGET_DEG degrés au moins, entre MIN_TARGET_M et MAX_TARGET_M,
 * dans le cœur de son champ (VIEW_SHARE du champ de l'appareil, VIEW_HALF_PANO de part et d'autre du cap pour un
 * panorama, dont la vignette ne montre que l'avant), sans autre bâtiment devant. Les quais (SNCF, OSM) vus en plus
 * améliorent la note, sans suffire seuls. Une gare sans
 * bâtiment connu ne reçoit rien (le plus souvent : bâtiment détruit, photo d'une route). Photo penchée
 * (roulis ou tangage hors bornes) écartée, comme le panorama dont la position
 * (donc le cap) est incertaine (PANO_MAX_ACCURACY_M).
 *
 * Règle ancienne (sans StationScene, gardée pour la mesure « avant ») : point à moins de MAX_DISTANCE_M de la gare,
 * azimut à MAX_ANGLE degrés près du cap photo → gare.
 *
 * Au plus MAX_PER_STATION photos, une par séquence, les mieux notées : photo plate avant panorama à 360°, gare
 * plus largement vue, plus nette (densité de pixels), plus récente, plus précise (position).
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

    /** Règle des abords : distances au bâtiment de la gare vu (mètres). */
    public const MIN_TARGET_M = 6;

    public const MAX_TARGET_M = 50;

    /** Largeur vue (degrés) d'un bâtiment de la gare. */
    public const MIN_TARGET_DEG = 20;

    /**
     * Quais vus à MAX_PLATFORM_M au plus : ils comptent dans la note, jamais seuls (mesure du 22/11 : une photo qui ne
     * voit qu'un quai montre le plus souvent un passage à niveau, un pont ou un parking).
     */
    public const MAX_PLATFORM_M = 40;

    /** Part du champ de l'appareil où le bâtiment doit paraître (le cœur de l'image, pas ses bords). */
    public const VIEW_SHARE = 0.8;

    /** Champ supposé quand l'appareil ne le donne pas, et demi-champ retenu pour un panorama (vignette de l'avant). */
    public const DEFAULT_FOV = 70;

    public const VIEW_HALF_PANO = 50;

    /**
     * Panorama à 360° retenu seulement si sa position est connue à PANO_MAX_ACCURACY_M près : mesure du 22/11, les
     * panoramas d'appareils tenus à la main ou sur un vélo (précision annoncée de 4 m ou inconnue) sont souvent mal
     * orientés, leur vignette montre une route ; ceux des véhicules équipés (2 m) visent juste.
     */
    public const PANO_MAX_ACCURACY_M = 3.0;

    /** Photo penchée : roulis ou tangage (degrés) au-delà. */
    public const MAX_ROLL = 15;

    public const MAX_PITCH = 30;

    /**
     * Tri par vision (PanoramaxVision), premier filtre : distance à la gare (mètres), écart d'azimut (degrés) d'une
     * photo plate et d'un panorama, photos gardées par séquence, séquences gardées par gare (borne les vignettes à
     * télécharger : 4 photos par gare en moyenne).
     */
    public const VISION_MAX_M = 100;

    public const VISION_MAX_ANGLE = 35;

    public const VISION_MAX_ANGLE_PANO = 60;

    public const VISION_PER_SEQUENCE = 2;

    public const VISION_MAX_SEQUENCES = 6;

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
    public static function rejectReason(array $f, float $stationLat, float $stationLng, ?StationScene $scene = null): ?string
    {
        return self::assess($f, $stationLat, $stationLng, $scene)[0];
    }

    /**
     * Motif de refus (null si retenue) et ce que la photo voit de la gare (règle des abords).
     *
     * @param  array<string, mixed>  $f
     * @return array{0: ?string, 1: ?array{target_deg: float, platform_deg: float, hidden_deg: float, nearest_m: ?float}}
     */
    public static function assess(array $f, float $stationLat, float $stationLng, ?StationScene $scene = null): array
    {
        $p = (array) ($f['properties'] ?? []);
        [$lng, $lat] = self::position($f) ?? [null, null];
        $reason = self::basicReason($f);
        if ($reason !== null || $lat === null || $lng === null) {
            return [$reason, null];
        }
        if ($scene === null) {
            return [match (true) {
                self::distanceM($lat, $lng, $stationLat, $stationLng) > self::MAX_DISTANCE_M => 'too_far',
                self::angle($f, $stationLat, $stationLng) > self::MAX_ANGLE => 'facing_away',
                default => null,
            }, null];
        }

        $az = $p['view:azimuth'] ?? null;
        if ($scene->isEmpty()) {
            return ['no_station_building', null];
        }
        if (self::distanceM($lat, $lng, $stationLat, $stationLng) > self::SEARCH_RADIUS_M) {
            return ['too_far', null];
        }
        if (! is_numeric($az)) {
            return ['no_azimuth', null];
        }
        if (abs((float) ($p['pers:roll'] ?? 0)) > self::MAX_ROLL || abs((float) ($p['pers:pitch'] ?? 0)) > self::MAX_PITCH) {
            return ['tilted', null];
        }
        $fov = (int) ($p['pers:interior_orientation']['field_of_view'] ?? 0);
        $accuracy = $p['quality:horizontal_accuracy'] ?? null;
        if ($fov >= 360 && (! is_numeric($accuracy) || (float) $accuracy > self::PANO_MAX_ACCURACY_M)) {
            return ['pano_unsure_heading', null];
        }
        [$x, $y] = $scene->project($lat, $lng);
        $view = $scene->view($x, $y, (float) $az, self::halfFov($f), self::MIN_TARGET_M, self::MAX_TARGET_M, self::MAX_PLATFORM_M);
        if ($view['target_deg'] >= self::MIN_TARGET_DEG) {
            return [null, $view];
        }

        return [$view['hidden_deg'] >= self::MIN_TARGET_DEG ? 'hidden' : 'not_in_view', $view];
    }

    /**
     * Motif de refus qui ne dépend pas des abords (position, instance, publication, licence, crédit), null sinon.
     *
     * @param  array<string, mixed>  $f
     */
    public static function basicReason(array $f): ?string
    {
        $p = (array) ($f['properties'] ?? []);

        return match (true) {
            self::position($f) === null => 'no_position',
            ! isset(self::INSTANCES[self::instance($f) ?? '']) => 'instance',
            ($p['geovisio:status'] ?? 'ready') !== 'ready' || ($p['geovisio:visibility'] ?? 'anyone') !== 'anyone' => 'not_public',
            ! isset(self::LICENSES[(string) ($p['license'] ?? '')]) => 'license',
            self::thumb($f) === null || trim(self::producer($f)) === '' => 'incomplete',
            default => null,
        };
    }

    /**
     * Demi-champ (degrés) où la gare doit paraître : cœur du champ de l'appareil, avant d'un panorama.
     *
     * @param  array<string, mixed>  $f
     */
    public static function halfFov(array $f): float
    {
        $fov = (int) ($f['properties']['pers:interior_orientation']['field_of_view'] ?? 0);
        if ($fov >= 360) {
            return self::VIEW_HALF_PANO;
        }

        return self::VIEW_SHARE * ($fov > 0 ? min($fov, 180) : self::DEFAULT_FOV) / 2;
    }

    /**
     * Photos retenues pour une gare, dans l'ordre de leur note (règle des abords si `$scene` est donnée ; ce que
     * la photo voit est alors rangé dans `vff:view`).
     *
     * @param  list<array<string, mixed>>  $features
     * @param  array<string, int>  $rejected  compteurs des photos écartées, par motif (complétés)
     * @return list<array<string, mixed>>
     */
    public static function pick(array $features, float $stationLat, float $stationLng, array &$rejected = [], ?StationScene $scene = null): array
    {
        $ok = [];
        foreach ($features as $f) {
            [$reason, $view] = self::assess($f, $stationLat, $stationLng, $scene);
            if ($reason !== null) {
                $rejected[$reason] = ($rejected[$reason] ?? 0) + 1;

                continue;
            }
            if ($view !== null) {
                $f['vff:view'] = $view;
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

    /**
     * Tri par vision, premier filtre (géométrique, large) : photos d'une instance lisible par le tri
     * (PanoramaxVision::INSTANCES), à VISION_MAX_M au plus de la gare, tournées vers elle à VISION_MAX_ANGLE
     * près (VISION_MAX_ANGLE_PANO pour un panorama, dont la vignette montre l'avant), ni sans cap ni penchées.
     * Par séquence, les VISION_PER_SEQUENCE plus proches et mieux tournées ; par gare, les VISION_MAX_SEQUENCES
     * meilleures séquences. Ce sont les vignettes à télécharger et à noter.
     *
     * @param  list<array<string, mixed>>  $features
     * @param  array<string, int>  $rejected  compteurs des photos écartées, par motif (complétés)
     * @return list<array<string, mixed>>
     */
    public static function visionCandidates(array $features, float $stationLat, float $stationLng, array &$rejected = []): array
    {
        $sequences = [];
        foreach ($features as $f) {
            $p = (array) ($f['properties'] ?? []);
            $reason = self::basicReason($f);
            if ($reason === null) {
                [$lng, $lat] = (array) self::position($f);
                $pano = (int) ($p['pers:interior_orientation']['field_of_view'] ?? 0) >= 360;
                $reason = match (true) {
                    ! in_array(self::instance($f), PanoramaxVision::INSTANCES, true) => 'vision_instance',
                    ! is_numeric($p['view:azimuth'] ?? null) => 'no_azimuth',
                    abs((float) ($p['pers:roll'] ?? 0)) > self::MAX_ROLL || abs((float) ($p['pers:pitch'] ?? 0)) > self::MAX_PITCH => 'tilted',
                    self::distanceM((float) $lat, (float) $lng, $stationLat, $stationLng) > self::VISION_MAX_M => 'too_far',
                    self::angle($f, $stationLat, $stationLng) > ($pano ? self::VISION_MAX_ANGLE_PANO : self::VISION_MAX_ANGLE) => 'facing_away',
                    default => null,
                };
            }
            if ($reason !== null) {
                $rejected[$reason] = ($rejected[$reason] ?? 0) + 1;

                continue;
            }
            [$lng, $lat] = (array) self::position($f);
            $g = self::distanceM((float) $lat, (float) $lng, $stationLat, $stationLng) / self::VISION_MAX_M + self::angle($f, $stationLat, $stationLng) / 90;
            $sequences[(string) ($f['collection'] ?? $f['id'])][] = [$g, (string) $f['id'], $f];
        }
        $order = fn (array $a, array $b): int => $a[0] <=> $b[0] ?: strcmp($a[1], $b[1]);
        foreach ($sequences as &$list) {
            usort($list, $order);
        }
        unset($list);
        uasort($sequences, fn (array $a, array $b): int => $order($a[0], $b[0]));

        $out = [];
        foreach (array_slice($sequences, 0, self::VISION_MAX_SEQUENCES) as $list) {
            foreach (array_slice($list, 0, self::VISION_PER_SEQUENCE) as [, , $f]) {
                $out[] = $f;
            }
        }

        return $out;
    }

    /**
     * Photos retenues par le tri par vision, dans l'ordre de leur note : candidates (visionCandidates) dont la
     * note de vision atteint PanoramaxVision::MIN_SCORE (MIN_SCORE_SCENE si la photo passe aussi la règle des
     * abords) ; une par séquence, MAX_PER_STATION au plus. La note et ce que voit la photo sont rangés dans
     * `vff:vision` et `vff:view`.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  callable(string): ?float  $score  note de vision d'une photo (null si absente)
     * @param  array<string, int>  $rejected
     * @param  ?float  $minScore  seuil imposé (relève le seuil par défaut, jamais en dessous de lui)
     * @return list<array<string, mixed>>
     */
    public static function pickVision(array $candidates, float $stationLat, float $stationLng, callable $score, array &$rejected = [], ?StationScene $scene = null, ?float $minScore = null): array
    {
        $ok = [];
        foreach ($candidates as $f) {
            $s = $score((string) $f['id']);
            if ($s === null) {
                $rejected['vision_unscored'] = ($rejected['vision_unscored'] ?? 0) + 1;

                continue;
            }
            [$sceneReason, $view] = $scene !== null && ! $scene->isEmpty() ? self::assess($f, $stationLat, $stationLng, $scene) : ['no_scene', null];
            $min = $minScore ?? ($sceneReason === null ? PanoramaxVision::MIN_SCORE_SCENE : PanoramaxVision::MIN_SCORE);
            if ($s < $min) {
                $rejected['vision_low'] = ($rejected['vision_low'] ?? 0) + 1;

                continue;
            }
            $f['vff:vision'] = ['score' => $s, 'scene' => $sceneReason === null];
            if ($sceneReason === null && $view !== null) {
                $f['vff:view'] = $view;
            }
            $ok[] = [$f, 4.0 * $s + self::score($f, $stationLat, $stationLng)];
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
        $view = $f['vff:view'] ?? null;
        $aim = is_array($view)
            ? 2.0 * min(40.0, (float) $view['target_deg']) / 40 + ((float) $view['platform_deg'] >= 15 ? 0.5 : 0.0)
            : 2.0 * (1 - min($angle, 180.0) / 180);

        return ($fov > 0 && $fov < 360 ? 2.0 : 0.0)
            + $aim
            + min($density, 40.0) / 40
            + max(0, min($year - 2015, 12)) / 12
            - ($accuracy > 5 ? 0.5 : 0.0)
            - 0.5 * self::distanceM($lat, $lng, $stationLat, $stationLng) / (is_array($view) ? self::SEARCH_RADIUS_M : self::MAX_DISTANCE_M);
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
