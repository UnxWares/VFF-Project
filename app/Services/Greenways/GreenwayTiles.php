<?php

namespace App\Services\Greenways;

use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tuiles vectorielles des voies vertes sur d'anciennes lignes (calque « Voies vertes » de /maps),
 * couche `vff-greenways` : la partie de chaque way OSM qui suit l'ancienne emprise (`line_greenways.geom`).
 * La propriété `name` est le nom affiché (`display_name`, GreenwayNames::displayName), le même que
 * celui de la fiche et de l'API : jamais un lieu, un type de voie ou un nom de rue tiré d'OSM.
 * Les ways qui reprennent l'emprise de deux lignes superposées sont dessinés une fois par ligne
 * (propriétés `line_slug` différentes). Servies à partir du zoom MIN_ZOOM ; sous le zoom 11 les
 * tracés sont simplifiés à la résolution de la tuile (quelques milliers de ways sur la France).
 * Tuiles en cache un jour, avec un numéro de version incrémenté par `vff:link-greenways`.
 * Données © contributeurs OpenStreetMap, ODbL.
 */
final class GreenwayTiles
{
    public const MIN_ZOOM = 4;

    private const TTL = 86_400;

    private const VERSION_KEY = 'greenways:version';

    /** Au-delà, la tuile est calculée à la demande (quelques tracés chacune). */
    private const CACHED_MAX_ZOOM = 11;

    /** Vide le cache des tuiles (après `vff:link-greenways`) en changeant le numéro de version. */
    public function invalidate(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    public function tile(int $z, int $x, int $y): string
    {
        if ($z < self::MIN_ZOOM) {
            return '';
        }
        if ($z > self::CACHED_MAX_ZOOM) {
            return $this->compute($z, $x, $y);
        }
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return Cache::remember("greenways:tile:{$version}:{$z}:{$x}:{$y}", self::TTL, fn (): string => $this->compute($z, $x, $y));
    }

    private function compute(int $z, int $x, int $y): string
    {
        // Avant le zoom 11, simplification à la résolution d'un pixel de tuile (m).
        $geom = $z < 11
            ? sprintf('ST_Simplify(ST_Transform(g.geom, 3857), %.4F)', 156_543.03 / (2 ** $z))
            : 'ST_Transform(g.geom, 3857)';
        $result = DB::selectOne(<<<SQL
            WITH bounds AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS env_3857,
                       ST_Transform(ST_TileEnvelope(:z2, :x2, :y2), 4326) AS env_4326
            ),
            mvt_layer AS (
                SELECT g.osm_way_id AS way_id,
                       g.display_name AS name,
                       g.highway,
                       g.shared_length_m,
                       g.opened_year,
                       l.slug AS line_slug,
                       l.name AS line_name,
                       ST_AsMVTGeom({$geom}, bounds.env_3857, 4096, 64, true) AS geom
                FROM line_greenways g
                CROSS JOIN bounds
                JOIN lines l ON l.id = g.line_id
                WHERE g.geom && bounds.env_4326
            )
            SELECT ST_AsMVT(mvt_layer.*, 'vff-greenways') AS tile FROM mvt_layer WHERE geom IS NOT NULL
            SQL, ['z' => $z, 'x' => $x, 'y' => $y, 'z2' => $z, 'x2' => $x, 'y2' => $y]);

        return MvtBytes::from($result->tile ?? '');
    }
}
