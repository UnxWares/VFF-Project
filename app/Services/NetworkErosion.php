<?php

namespace App\Services;

use App\Enums\EraChange;
use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Toutes les ères empilées : chaque tronçon qui a été en service à au moins une décennie, avec
 * la première et la dernière décennie où la carte le montre en service. La carte colore le
 * tracé selon la dernière (érosion du réseau).
 *
 * Les tronçons sont regroupés par ligne et géométrie identique (empreinte md5 du WKB) : un même
 * tronçon recopié de décennie en décennie n'est envoyé qu'une fois.
 */
class NetworkErosion
{
    /**
     * Tuiles de petite échelle (France entière, une région) mises en cache : ce sont les plus
     * coûteuses (toutes les ères à la fois) et les mêmes pour tous. Clés bornées par la grille
     * XYZ jusqu'à ce zoom.
     */
    private const CACHED_MAX_ZOOM = 7;

    private const TILE_TTL = 600;

    public function tile(int $z, int $x, int $y): string
    {
        if ($z > self::CACHED_MAX_ZOOM) {
            return $this->computeTile($z, $x, $y);
        }

        return Cache::remember("era-stack:tile:{$z}:{$x}:{$y}", self::TILE_TTL, fn (): string => $this->computeTile($z, $x, $y));
    }

    private function computeTile(int $z, int $x, int $y): string
    {
        $open = EraChange::openStatusesSql();
        $result = DB::selectOne(<<<SQL
            WITH bounds AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS env_3857,
                       ST_Transform(ST_TileEnvelope(:z, :x, :y), 4326) AS env_4326
            ),
            spans AS (
                SELECT ls.line_id,
                       (array_agg(ls.geom))[1] AS geom,
                       MIN(e.year) AS first_year,
                       MAX(e.year) AS last_year
                FROM line_segments ls
                JOIN eras e ON e.id = ls.era_id
                CROSS JOIN bounds
                WHERE ls.geom && bounds.env_4326 AND ls.status IN ({$open})
                GROUP BY ls.line_id, md5(ST_AsBinary(ls.geom))
            ),
            mvt_layer AS (
                SELECT s.first_year,
                       s.last_year,
                       l.slug AS line_slug,
                       l.name AS line_name,
                       l.code AS line_code,
                       ST_AsMVTGeom(ST_Transform(s.geom, 3857), bounds.env_3857, 4096, 256, true) AS geom
                FROM spans s
                JOIN lines l ON l.id = s.line_id
                CROSS JOIN bounds
            )
            SELECT ST_AsMVT(mvt_layer.*, 'vff-stack') AS tile FROM mvt_layer WHERE geom IS NOT NULL
        SQL, ['z' => $z, 'x' => $x, 'y' => $y]);

        return MvtBytes::from($result->tile ?? '');
    }
}
