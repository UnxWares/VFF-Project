<?php

namespace App\Services\Geometry;

use InvalidArgumentException;

/**
 * Morceaux de tracé fusionnés (chantier fusion-sources, `ml/fusion-sources/scripts/proto.py`), au format des
 * tracés sources : source `fusion`, couche `precis` ou `estime`.
 *
 * Le fichier est une FeatureCollection GeoJSON (lon/lat) ; chaque Feature est un LineString avec les propriétés
 * `ligne` (id de la ligne), `chaine`, `classe` (`precis` ou `estime`), `erreur_estimee_max_m` (erreur estimée à
 * 90 % la plus forte du morceau), `debut_m` et `fin_m` (abscisses sur la chaîne).
 *
 * Règle de l'encyclopédie : un morceau n'est « précis » que si son erreur estimée est sous 3 m. Un morceau
 * annoncé précis au-delà (ou sans erreur lisible) est lu comme « estimé » : jamais présenté comme précis.
 */
final class FusedTrace
{
    public const SOURCE = 'fusion';

    public const PRECISE = 'precis';

    public const ESTIMATED = 'estime';

    /** Erreur estimée (m, 90 %) au-delà de laquelle un morceau n'est jamais « précis ». */
    public const PRECISE_MAX_ERROR_M = 3.0;

    /**
     * @param  list<array{line_id: int, source_ref: string, layer: string, error_p90_m: float|null, coordinates: list<array{0: float, 1: float}>}>  $pieces
     */
    private function __construct(public readonly array $pieces) {}

    public static function fromFile(string $path): self
    {
        $json = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new InvalidArgumentException("Fichier illisible : {$path}");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new InvalidArgumentException('FeatureCollection attendue');
        }

        return self::fromArray($data);
    }

    /**
     * @param  array<mixed>  $collection
     */
    public static function fromArray(array $collection): self
    {
        if (($collection['type'] ?? null) !== 'FeatureCollection' || ! is_array($collection['features'] ?? null)) {
            throw new InvalidArgumentException('FeatureCollection attendue');
        }

        $pieces = [];
        foreach ($collection['features'] as $i => $feature) {
            if (! is_array($feature) || ($feature['geometry']['type'] ?? null) !== 'LineString') {
                throw new InvalidArgumentException("Morceau {$i} : LineString attendu");
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $line = $props['ligne'] ?? null;
            if (! is_int($line) || $line <= 0) {
                throw new InvalidArgumentException("Morceau {$i} : identifiant de ligne attendu");
            }
            $coordinates = self::coordinates($feature['geometry']['coordinates'] ?? null, $i);
            $error = is_int($props['erreur_estimee_max_m'] ?? null) || is_float($props['erreur_estimee_max_m'] ?? null)
                ? (float) $props['erreur_estimee_max_m'] : null;
            $pieces[] = [
                'line_id' => $line,
                'source_ref' => sprintf('%d-%d-%d', $line, (int) ($props['chaine'] ?? 0), (int) round((float) ($props['debut_m'] ?? 0))),
                'layer' => self::layer($props['classe'] ?? null, $error),
                'error_p90_m' => $error,
                'coordinates' => $coordinates,
            ];
        }

        return new self($pieces);
    }

    /**
     * Couche retenue : `precis` seulement si annoncé précis ET erreur estimée connue, finie et sous 3 m.
     */
    public static function layer(mixed $announced, ?float $error): string
    {
        $precise = $announced === self::PRECISE && $error !== null && is_finite($error) && $error >= 0.0
            && $error < self::PRECISE_MAX_ERROR_M;

        return $precise ? self::PRECISE : self::ESTIMATED;
    }

    /**
     * Lignes de la table `source_traces` (géométrie en GeoJSON MultiLineString, lon/lat).
     *
     * @return list<array{source: string, source_ref: string, layer: string, line_id: int, properties: string, geojson: string}>
     */
    public function rows(): array
    {
        return array_map(fn (array $p): array => [
            'source' => self::SOURCE,
            'source_ref' => $p['source_ref'],
            'layer' => $p['layer'],
            'line_id' => $p['line_id'],
            'properties' => json_encode([
                'erreur_p90_m' => $p['error_p90_m'],
                'methode' => 'fusion robuste des sources, recalage local, géométrie ferroviaire',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'geojson' => json_encode(['type' => 'MultiLineString', 'coordinates' => [$p['coordinates']]], JSON_THROW_ON_ERROR),
        ], $this->pieces);
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private static function coordinates(mixed $raw, int|string $i): array
    {
        if (! is_array($raw) || count($raw) < 2) {
            throw new InvalidArgumentException("Morceau {$i} : au moins deux points");
        }
        $out = [];
        foreach ($raw as $pt) {
            if (! is_array($pt) || ! is_numeric($pt[0] ?? null) || ! is_numeric($pt[1] ?? null)) {
                throw new InvalidArgumentException("Morceau {$i} : coordonnée illisible");
            }
            $lon = (float) $pt[0];
            $lat = (float) $pt[1];
            if ($lon < -180.0 || $lon > 180.0 || $lat < -90.0 || $lat > 90.0) {
                throw new InvalidArgumentException("Morceau {$i} : coordonnée hors bornes");
            }
            $out[] = [$lon, $lat];
        }

        return $out;
    }
}
