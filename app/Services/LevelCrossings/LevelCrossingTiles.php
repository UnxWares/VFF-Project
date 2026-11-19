<?php

namespace App\Services\LevelCrossings;

use App\Enums\LevelCrossingGroup;
use App\Models\LevelCrossing;
use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tuiles vectorielles des passages à niveau (calque « Passages à niveau » de /maps), couche
 * `vff-level-crossings`. Servies à partir du zoom MIN_ZOOM seulement : 16 800 points d'un coup
 * n'auraient pas de sens à l'échelle de la France. Les PN secondaires (classe 00, déjà dessinés
 * sur la ligne qui porte leur classe) sont omis. Tuiles en cache 1 jour (données publiées une fois
 * par an environ par SNCF Réseau) ; la clé de cache porte un numéro de version, incrémenté par
 * l'import (invalidate), comme pour la signalisation.
 */
final class LevelCrossingTiles
{
    public const MIN_ZOOM = 9;

    private const TTL = 86_400;

    private const VERSION_KEY = 'level-crossings:version';

    /** Au-delà, les tuiles sont calculées à la demande (quelques points chacune). */
    private const CACHED_MAX_ZOOM = 13;

    /** Vide le cache des tuiles (après `vff:import-sncf-level-crossings`) en changeant le numéro de version. */
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

        return Cache::remember("level-crossings:tile:{$version}:{$z}:{$x}:{$y}", self::TTL, fn (): string => $this->compute($z, $x, $y));
    }

    private function compute(int $z, int $x, int $y): string
    {
        $class = LevelCrossingGroup::classSql('pn.mnemo');
        $group = LevelCrossingGroup::groupSql($class);
        // Une seule fiche par code SNCF (`lines.code` n'est pas unique) : pas de PN dupliqué.
        $owner = LevelCrossing::ownerLineIdSql('pn.code_ligne');
        $result = DB::selectOne(<<<SQL
            WITH bounds AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS env_3857,
                       ST_Transform(ST_TileEnvelope(:z, :x, :y), 4326) AS env_4326
            ),
            mvt_layer AS (
                SELECT pn.id_if AS id,
                       pn.libelle AS label,
                       {$class} AS class,
                       {$group} AS "group",
                       pn.pk_m,
                       pn.commune,
                       pn.obstacle AS crossed_way,
                       pn.code_ligne AS line_code,
                       l.slug AS line_slug,
                       l.name AS line_name,
                       ST_AsMVTGeom(ST_Transform(pn.geom, 3857), bounds.env_3857, 4096, 64, true) AS geom
                FROM sncf_level_crossings pn
                CROSS JOIN bounds
                LEFT JOIN lines l ON l.id = {$owner}
                WHERE pn.geom && bounds.env_4326 AND pn.mnemo IS DISTINCT FROM 'CLASSE 00'
            )
            SELECT ST_AsMVT(mvt_layer.*, 'vff-level-crossings') AS tile FROM mvt_layer WHERE geom IS NOT NULL
        SQL, ['z' => $z, 'x' => $x, 'y' => $y]);

        return MvtBytes::from($result->tile ?? '');
    }
}
