<?php

namespace App\Services\Signalling;

use App\Enums\SignallingFamily;
use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Signalisation sur la carte (calque « Signalisation » de /maps) : tuiles vectorielles des tranches
 * de `line_signalling` placées sur les formes SNCF Réseau (vue matérialisée `line_signalling_map`,
 * voir sa migration pour le calage des PK). Seule l'ère courante est renseignée aujourd'hui.
 * Propriété `signalling_systems` : tous les systèmes de cantonnement de la tranche, séparés par
 * des virgules (« ETCS_L2,TVM-300 »), lus dans `line_signalling` (la vue n'en garde que le code
 * principal).
 */
final class SignallingMap
{
    private const TTL = 86_400;

    private const CACHED_MAX_ZOOM = 10;

    /**
     * Recalcule la vue matérialisée (après un import de la signalisation) et vide le cache des tuiles.
     * Sans bloquer les lectures (CONCURRENTLY, grâce à l'index unique de la migration
     * 2026_11_20_030000), sauf si la vue n'a encore jamais été remplie : PostgreSQL refuse alors le
     * mode concurrent, et personne ne lit une vue vide.
     */
    public function refresh(): int
    {
        $populated = (bool) DB::scalar("SELECT ispopulated FROM pg_matviews WHERE matviewname = 'line_signalling_map'");
        DB::statement('REFRESH MATERIALIZED VIEW '.($populated ? 'CONCURRENTLY ' : '').'line_signalling_map');
        Cache::increment('signalling-map:version');

        return (int) DB::scalar('SELECT COUNT(*) FROM line_signalling_map');
    }

    public function tile(int $eraId, int $z, int $x, int $y): string
    {
        if ($z > self::CACHED_MAX_ZOOM) {
            return $this->compute($eraId, $z, $x, $y);
        }
        $version = (int) Cache::get('signalling-map:version', 0);

        return Cache::remember("signalling-map:{$version}:{$eraId}:{$z}:{$x}:{$y}", self::TTL, fn (): string => $this->compute($eraId, $z, $x, $y));
    }

    private function compute(int $eraId, int $z, int $x, int $y): string
    {
        $family = SignallingFamily::sql('m.signalling');
        $result = DB::selectOne(<<<SQL
            WITH bounds AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS env_3857,
                       ST_Transform(ST_TileEnvelope(:z, :x, :y), 4326) AS env_4326
            ),
            mvt_layer AS (
                SELECT m.signalling,
                       {$family} AS family,
                       COALESCE((SELECT string_agg(c, ',') FROM jsonb_array_elements_text(ls.signalling_systems) AS c), m.signalling) AS signalling_systems,
                       COALESCE((SELECT string_agg(p, ',') FROM json_array_elements_text(m.train_protection) AS p), '') AS train_protection,
                       m.kvb_equipped,
                       m.pk_start_m,
                       m.pk_end_m,
                       l.slug AS line_slug,
                       l.name AS line_name,
                       l.code AS line_code,
                       ST_AsMVTGeom(ST_Transform(m.geom, 3857), bounds.env_3857, 4096, 256, true) AS geom
                FROM line_signalling_map m
                CROSS JOIN bounds
                JOIN lines l ON l.id = m.line_id
                LEFT JOIN line_signalling ls ON ls.id = m.range_id
                WHERE m.era_id = :era AND m.geom && bounds.env_4326
            )
            SELECT ST_AsMVT(mvt_layer.*, 'vff-signalling') AS tile FROM mvt_layer WHERE geom IS NOT NULL
        SQL, ['z' => $z, 'x' => $x, 'y' => $y, 'era' => $eraId]);

        return MvtBytes::from($result->tile ?? '');
    }
}
