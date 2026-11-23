<?php

namespace App\Mcp\Support;

/**
 * Zone géographique d'une question MCP (network_in_area) : un rectangle (sud, ouest, nord, est en degrés WGS 84)
 * ou un cercle (centre et rayon en km), traduit en géométrie PostGIS (SRID 4326) avec ses paramètres liés.
 * La taille est bornée (MAX_HEIGHT_DEG, MAX_WIDTH_DEG, MAX_RADIUS_KM) : une zone trop grande est refusée, la
 * réponse reste compacte et la requête rapide (index GIST des tronçons et des gares). Elle a aussi un minimum
 * (MIN_SIDE_DEG, MIN_RADIUS_KM), pour qu'aucune suite de petites zones ne redessine un tracé non redistribuable.
 */
final class MapArea
{
    /** Hauteur et largeur maximales d'un rectangle, en degrés (environ 110 km sur 110 km en France). */
    public const MAX_HEIGHT_DEG = 1.0;

    public const MAX_WIDTH_DEG = 1.5;

    /** Rayon maximal d'un cercle, en km. */
    public const MAX_RADIUS_KM = 50;

    /**
     * Taille minimale (côté d'un rectangle en degrés, rayon d'un cercle en km) : une suite de zones minuscules
     * redonnerait, par les longueurs mesurées dedans, le dessin d'un tracé que sa source interdit de redistribuer.
     */
    public const MIN_SIDE_DEG = 0.01;

    public const MIN_RADIUS_KM = 1;

    /**
     * @param  'bbox'|'circle'  $kind
     * @param  list<float>  $values  bbox : sud, ouest, nord, est ; cercle : lat, lng, rayon en km
     */
    private function __construct(public readonly string $kind, public readonly array $values) {}

    /**
     * Zone lue dans les arguments validés ; null si ni un rectangle complet ni un cercle complet n'est donné, ou
     * si les deux le sont.
     *
     * @param  array<string, mixed>  $input
     */
    public static function from(array $input): ?self
    {
        $bbox = array_map(fn (string $k) => $input[$k] ?? null, ['south', 'west', 'north', 'east']);
        $circle = array_map(fn (string $k) => $input[$k] ?? null, ['lat', 'lng', 'radius_km']);
        $hasBbox = ! in_array(null, $bbox, true);
        $hasCircle = ! in_array(null, $circle, true);
        $anyBbox = array_filter($bbox, fn ($v) => $v !== null) !== [];
        $anyCircle = array_filter($circle, fn ($v) => $v !== null) !== [];
        if ($hasBbox && ! $anyCircle) {
            return new self('bbox', array_map('floatval', $bbox));
        }
        if ($hasCircle && ! $anyBbox) {
            return new self('circle', array_map('floatval', $circle));
        }

        return null;
    }

    /** Vrai si la zone dépasse les tailles maximales, ou si le rectangle est vide ou à l'envers. */
    public function tooLarge(): bool
    {
        if ($this->kind === 'circle') {
            return $this->values[2] > self::MAX_RADIUS_KM;
        }
        [$s, $w, $n, $e] = $this->values;

        return $n - $s > self::MAX_HEIGHT_DEG || $e - $w > self::MAX_WIDTH_DEG;
    }

    /** Vrai si la zone est plus petite que les tailles minimales. */
    public function tooSmall(): bool
    {
        if ($this->kind === 'circle') {
            return $this->values[2] < self::MIN_RADIUS_KM;
        }
        [$s, $w, $n, $e] = $this->values;

        return $n - $s < self::MIN_SIDE_DEG || $e - $w < self::MIN_SIDE_DEG;
    }

    public function inverted(): bool
    {
        return $this->kind === 'bbox' && ($this->values[2] <= $this->values[0] || $this->values[3] <= $this->values[1]);
    }

    /**
     * Géométrie SQL de la zone et ses paramètres liés, dans l'ordre.
     *
     * @return array{0: string, 1: list<float>}
     */
    public function sql(): array
    {
        if ($this->kind === 'bbox') {
            [$s, $w, $n, $e] = $this->values;

            return ['ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$w, $s, $e, $n]];
        }
        [$lat, $lng, $km] = $this->values;

        return ['ST_Buffer(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)::geometry', [$lng, $lat, $km * 1000]];
    }

    /**
     * Centre de la zone [lng, lat] (lien de la carte).
     *
     * @return array{0: float, 1: float}
     */
    public function center(): array
    {
        if ($this->kind === 'circle') {
            return [$this->values[1], $this->values[0]];
        }
        [$s, $w, $n, $e] = $this->values;

        return [($w + $e) / 2, ($s + $n) / 2];
    }

    /** Zoom de la carte qui montre à peu près la zone. */
    public function zoom(): int
    {
        $span = $this->kind === 'circle' ? $this->values[2] * 2 / 111 : max($this->values[2] - $this->values[0], ($this->values[3] - $this->values[1]) * 0.7);

        return (int) max(6, min(14, round(log(360 / max($span, 0.01), 2) - 0.5)));
    }

    /** @return array<string, float|string> */
    public function describe(): array
    {
        return $this->kind === 'bbox'
            ? ['kind' => 'bbox', 'south' => $this->values[0], 'west' => $this->values[1], 'north' => $this->values[2], 'east' => $this->values[3]]
            : ['kind' => 'circle', 'lat' => $this->values[0], 'lng' => $this->values[1], 'radius_km' => $this->values[2]];
    }
}
