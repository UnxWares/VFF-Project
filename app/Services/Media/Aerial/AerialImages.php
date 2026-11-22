<?php

namespace App\Services\Media\Aerial;

use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Vues aériennes historiques d'une gare (IGN, BD ORTHO historique 1950-1965, 1965-1980, 1980-1995, Licence Ouverte
 * 2.0), lues sur le WMS raster de la Géoplateforme et gardées sur disque.
 *
 * Sûreté : l'emprise vient seulement de la position de la gare en base (aucun paramètre de la requête du visiteur
 * n'entre dans l'adresse), hôte et couches fixes (PERIODS). Le PNG reçu est décodé par GD puis réencodé en JPEG :
 * rien de ce que renvoie le serveur n'est servi tel quel. Une vue hors couverture (transparente ou uniforme) est
 * retenue comme absente (fichier `.none`), sans nouvelle requête pendant NEGATIVE_TTL.
 *
 * Cache : `storage/app/cache/aerial/<période>/<id de la gare>.jpg`, `.nobackup` et marqueur posés à la création,
 * écriture atomique. Lecture amont plafonnée (config `vff.media.aerial.upstream_per_minute`).
 */
final class AerialImages
{
    public const WMS = 'https://data.geopf.fr/wms-r';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    /** Période => couche du WMS. */
    public const PERIODS = [
        '1950-1965' => 'ORTHOIMAGERY.ORTHOPHOTOS.1950-1965',
        '1965-1980' => 'ORTHOIMAGERY.ORTHOPHOTOS.1965-1980',
        '1980-1995' => 'ORTHOIMAGERY.ORTHOPHOTOS.1980-1995',
    ];

    public const WIDTH = 640;

    public const HEIGHT = 384;

    /** Demi-largeur et demi-hauteur de la vue, en mètres (500 m sur 300 m autour de la gare). */
    public const HALF_WIDTH_M = 250;

    public const HALF_HEIGHT_M = 150;

    public const NEGATIVE_TTL = 30 * 86400;

    public const MARKER = '.vff-aerial-cache';

    /** Part minimale de pixels couverts, et écart type minimal des gris (une vue unie est un trou de couverture). */
    private const MIN_OPAQUE = 0.6;

    private const MIN_STDDEV = 6.0;

    public function __construct(private readonly ?string $directory = null) {}

    /** Licence et crédit communs aux trois périodes. */
    public const LICENSE = 'Licence Ouverte 2.0';

    public const LICENSE_URL = 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/';

    /**
     * Vues proposées pour une gare, une par période : adresse du relais, couche, crédit, licence, lien vers
     * « Remonter le temps » de l'IGN. Une vue peut manquer (période hors couverture) : le relais répond alors 404.
     *
     * @return list<array{period: string, from: int, to: int, url: string, layer: string, attribution: string, license: string, license_url: string, source_url: string, commercial_ok: bool, derivatives_ok: bool}>
     */
    public static function views(string $slug, float $lat, float $lng): array
    {
        if (! (bool) config('vff.media.aerial.enabled', true)) {
            return [];
        }
        $out = [];
        foreach (self::PERIODS as $period => $layer) {
            [$from, $to] = array_map('intval', explode('-', $period));
            $out[] = [
                'period' => $period,
                'from' => $from,
                'to' => $to,
                'url' => route('media.aerial', ['station' => $slug, 'period' => $period], false),
                'layer' => $layer,
                'attribution' => "IGN, photographies aériennes {$from}-{$to}, ".self::LICENSE,
                'license' => self::LICENSE,
                'license_url' => self::LICENSE_URL,
                'source_url' => sprintf('https://remonterletemps.ign.fr/comparer?lon=%.5F&lat=%.5F&z=17', $lng, $lat),
                'commercial_ok' => true,
                'derivatives_ok' => true,
            ];
        }

        return $out;
    }

    /**
     * Fichier JPEG de la vue, null si la période ne couvre pas la gare. `false` : lecture amont impossible
     * (erreur, plafond atteint), à retenter plus tard.
     */
    public function image(int $stationId, float $lat, float $lng, string $period): string|false|null
    {
        if (! isset(self::PERIODS[$period])) {
            return null;
        }
        $file = $this->dir($period).'/'.$stationId.'.jpg';
        if (is_file($file)) {
            return $file;
        }
        $none = $this->dir($period).'/'.$stationId.'.none';
        if (is_file($none) && time() - (int) filemtime($none) < self::NEGATIVE_TTL) {
            return null;
        }

        $limiter = 'aerial-upstream';
        if (RateLimiter::tooManyAttempts($limiter, (int) config('vff.media.aerial.upstream_per_minute', 30))) {
            return false;
        }
        RateLimiter::hit($limiter, 60);

        try {
            $res = Http::withHeaders(['User-Agent' => self::USER_AGENT])->connectTimeout(5)->timeout(20)
                ->get(self::WMS, self::query($lat, $lng, $period));
        } catch (Throwable) {
            return false;
        }
        if (! $res->successful() || ! str_starts_with((string) $res->header('Content-Type'), 'image/png')) {
            return false;
        }
        $jpeg = self::toJpeg($res->body());
        if ($jpeg === false) {
            return false;
        }
        if ($jpeg === null) {
            self::atomicWrite($none, '');

            return null;
        }
        self::atomicWrite($file, $jpeg);

        return $file;
    }

    /**
     * Paramètres GetMap (WMS 1.3.0, EPSG:4326 : axes latitude puis longitude).
     *
     * @return array<string, string>
     */
    public static function query(float $lat, float $lng, string $period): array
    {
        $dLat = self::HALF_HEIGHT_M / 111_320;
        $dLng = self::HALF_WIDTH_M / (111_320 * max(0.2, cos(deg2rad($lat))));

        return [
            'SERVICE' => 'WMS', 'VERSION' => '1.3.0', 'REQUEST' => 'GetMap',
            'LAYERS' => self::PERIODS[$period], 'STYLES' => '', 'CRS' => 'EPSG:4326',
            'BBOX' => sprintf('%.6F,%.6F,%.6F,%.6F', $lat - $dLat, $lng - $dLng, $lat + $dLat, $lng + $dLng),
            'WIDTH' => (string) self::WIDTH, 'HEIGHT' => (string) self::HEIGHT,
            'FORMAT' => 'image/png', 'TRANSPARENT' => 'true',
        ];
    }

    /**
     * PNG du WMS → JPEG ; null si la vue est hors couverture (transparente ou uniforme), false si l'image est illisible.
     */
    public static function toJpeg(string $png): string|false|null
    {
        $img = @imagecreatefromstring($png);
        if (! $img instanceof GdImage || imagesx($img) < 16 || imagesy($img) < 16) {
            return false;
        }
        [$opaque, $stddev] = self::coverage($img);
        if ($opaque < self::MIN_OPAQUE || $stddev < self::MIN_STDDEV) {
            return null;
        }
        $out = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        ob_start();
        imagejpeg($out, null, 78);

        return (string) ob_get_clean();
    }

    /**
     * Part de pixels couverts et écart type des gris, sur une grille de 32 x 20 points.
     *
     * @return array{0: float, 1: float}
     */
    private static function coverage(GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $grays = [];
        $opaque = 0;
        $n = 0;
        for ($y = 0; $y < 20; $y++) {
            for ($x = 0; $x < 32; $x++) {
                $c = imagecolorsforindex($img, imagecolorat($img, (int) (($x + 0.5) * $w / 32), (int) (($y + 0.5) * $h / 20)));
                $n++;
                if ($c['alpha'] < 100) {
                    $opaque++;
                    $grays[] = 0.299 * $c['red'] + 0.587 * $c['green'] + 0.114 * $c['blue'];
                }
            }
        }
        if ($grays === []) {
            return [0.0, 0.0];
        }
        $mean = array_sum($grays) / count($grays);
        $var = array_sum(array_map(fn (float $g): float => ($g - $mean) ** 2, $grays)) / count($grays);

        return [$opaque / $n, sqrt($var)];
    }

    private function dir(string $period): string
    {
        $root = $this->directory ?? (string) config('vff.media.aerial.cache_path', storage_path('app/cache/aerial'));
        if (! is_dir($root)) {
            mkdir($root, 0775, true);
        }
        foreach (['.nobackup', self::MARKER] as $marker) {
            if (! is_file($root.'/'.$marker)) {
                touch($root.'/'.$marker);
            }
        }
        $dir = $root.'/'.$period;
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function atomicWrite(string $file, string $bytes): void
    {
        $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($tmp, $bytes);
        rename($tmp, $file);
    }
}
