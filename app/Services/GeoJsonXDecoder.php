<?php

namespace App\Services;

/**
 * Décodeur du format GeoJSONX d'ol-ext (ol/format/GeoJSONX), utilisé par les
 * cartes IGN « Ma carte » pour stocker leurs calques vectoriels.
 *
 * Format (cf. ol-ext/format/GeoJSONX.js) :
 *   - chaque feature = [géométrie, propriétés?, extensions?] ;
 *   - géométrie = [typeIndex, coordonnéesEncodées] (ou chaîne seule pour un Point) ;
 *   - un nombre est arrondi à 10^decimals, zigzag-encodé puis écrit en base 91 ;
 *   - une suite de points « x,y;x,y;… » est encodée en DELTAS depuis le point précédent ;
 *   - les propriétés sont un tableau [indexDansHashProperties, valeur, …].
 *
 * Les coordonnées sont dans la projection de la carte (EPSG:3857 pour Ma carte) :
 * `toWgs84()` les convertit.
 */
class GeoJsonXDecoder
{
    private const RADIX = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/ !#$%&\'()*-.:<=>?@[]^_`{|}~';

    private const TYPES = ['Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon'];

    private const EARTH_RADIUS = 6378137.0;

    /** @var array<string, int> */
    private array $index;

    private int $size;

    public function __construct()
    {
        $this->size = strlen(self::RADIX);
        $this->index = array_flip(str_split(self::RADIX));
    }

    /**
     * Décode une FeatureCollection GeoJSONX en features GeoJSON (coordonnées
     * dans la projection d'origine).
     *
     * @param  array{decimals: int|string, hashProperties?: array<int, string>, features: array<int, array<int, mixed>>}  $collection
     * @return array<int, array{type: string, geometry: array{type: string, coordinates: mixed}, properties: array<string, mixed>}>
     */
    public function decodeCollection(array $collection): array
    {
        $decimals = (int) $collection['decimals'];
        $hash = $collection['hashProperties'] ?? [];
        $features = [];

        foreach ($collection['features'] as $raw) {
            if (is_string($raw[0])) {
                $geometry = ['type' => 'Point', 'coordinates' => $this->decodeCoordinates($raw[0], $decimals)];
            } else {
                $type = self::TYPES[$raw[0][0]] ?? null;
                if ($type === null) {
                    continue;
                }
                $coordinates = $this->decodeCoordinates($raw[0][1], $decimals);
                // MultiPoint d'un seul point : encodé « x,y » (sans « ; »), décodé comme un point.
                if ($type === 'MultiPoint' && is_float($coordinates[0] ?? null)) {
                    $coordinates = [$coordinates];
                }
                $geometry = ['type' => $type, 'coordinates' => $coordinates];
            }

            $properties = [];
            if (! empty($raw[1]) && is_array($raw[1])) {
                for ($i = 0; $i + 1 < count($raw[1]); $i += 2) {
                    $key = $hash[$raw[1][$i]] ?? (string) $raw[1][$i];
                    $properties[$key] = $raw[1][$i + 1];
                }
            }

            $features[] = ['type' => 'Feature', 'geometry' => $geometry, 'properties' => $properties];
        }

        return $features;
    }

    /** Décode un nombre base 91 zigzag-encodé. */
    public function decodeNumber(string $encoded, int $decimals): float
    {
        $n = 0;
        foreach (str_split($encoded) as $char) {
            $n = $n * $this->size + $this->index[$char];
        }
        $value = $n % 2 === 0 ? intdiv($n, 2) : -1 - intdiv($n, 2);

        return $value / (10 ** $decimals);
    }

    /**
     * Décode récursivement des coordonnées encodées : chaîne « x,y;x,y » (suite
     * de points en deltas), chaîne « x,y » (un point) ou tableau de chaînes.
     */
    public function decodeCoordinates(string|array $encoded, int $decimals): array
    {
        if (is_array($encoded)) {
            return array_map(fn ($part) => $this->decodeCoordinates($part, $decimals), $encoded);
        }

        $points = explode(';', $encoded);
        if (count($points) === 1) {
            [$x, $y] = explode(',', $points[0]);

            return [$this->decodeNumber($x, $decimals), $this->decodeNumber($y, $decimals)];
        }

        $out = [];
        $prevX = 0.0;
        $prevY = 0.0;
        foreach ($points as $point) {
            $parts = explode(',', $point);
            $prevX = round($this->decodeNumber($parts[0], $decimals) + $prevX, $decimals);
            $prevY = round($this->decodeNumber($parts[1], $decimals) + $prevY, $decimals);
            $out[] = [$prevX, $prevY];
        }

        return $out;
    }

    /** Convertit récursivement des coordonnées EPSG:3857 en EPSG:4326 (lon, lat). */
    public static function toWgs84(array $coordinates): array
    {
        if (is_numeric($coordinates[0] ?? null)) {
            $lon = $coordinates[0] / self::EARTH_RADIUS * 180 / M_PI;
            $lat = (2 * atan(exp($coordinates[1] / self::EARTH_RADIUS)) - M_PI / 2) * 180 / M_PI;

            return [round($lon, 7), round($lat, 7)];
        }

        return array_map(fn ($c) => self::toWgs84($c), $coordinates);
    }
}
