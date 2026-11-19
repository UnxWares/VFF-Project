<?php

namespace App\Services;

use App\Enums\EraChange;
use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Comparaison de deux ères de la carte : ce qui a ouvert, fermé ou disparu entre les deux.
 *
 * Calcul à la volée sur `line_segments`, sans table ni pipeline : les tronçons d'une ère
 * sont appariés à ceux de l'autre par ligne et par géométrie identique (empreinte md5 du
 * WKB), puis classés par EraChange. Les deux années arrivent dans l'ordre chronologique
 * (EraPair).
 */
class EraComparison
{
    /** Durée de cache du résumé (s) : il ne change qu'après un recalcul de l'historique. */
    private const SUMMARY_TTL = 600;

    /**
     * Longueur (km arrondis) et nombre de lignes touchées pour chaque changement (clé =
     * EraChange::value), et longueur en service à chacune des deux ères.
     *
     * @return array{changes: array<string, array{km: int, lines: int}>, open_km: array{from: int, to: int}}
     */
    public function summary(int $fromEraId, int $toEraId): array
    {
        return Cache::remember(
            "era-compare:summary:{$fromEraId}:{$toEraId}",
            self::SUMMARY_TTL,
            fn (): array => $this->computeSummary($fromEraId, $toEraId),
        );
    }

    /** Tuile vectorielle (couche `vff-compare`) des tronçons qui ont changé, ou restés en service. */
    public function tile(int $fromEraId, int $toEraId, int $z, int $x, int $y): string
    {
        $pairs = $this->pairsSql('AND ls.geom && bounds.env_4326');
        $result = DB::selectOne(<<<SQL
            WITH bounds AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS env_3857,
                       ST_Transform(ST_TileEnvelope(:z, :x, :y), 4326) AS env_4326
            ),
            {$pairs},
            mvt_layer AS (
                SELECT c.change,
                       l.slug AS line_slug,
                       l.name AS line_name,
                       l.code AS line_code,
                       ST_AsMVTGeom(ST_Transform(c.geom, 3857), bounds.env_3857, 4096, 256, true) AS geom
                FROM changes c
                JOIN lines l ON l.id = c.line_id
                CROSS JOIN bounds
                WHERE c.change IS NOT NULL
            )
            SELECT ST_AsMVT(mvt_layer.*, 'vff-compare') AS tile FROM mvt_layer WHERE geom IS NOT NULL
        SQL, ['from' => $fromEraId, 'to' => $toEraId, 'z' => $z, 'x' => $x, 'y' => $y]);

        return MvtBytes::from($result->tile ?? '');
    }

    /** @return array{changes: array<string, array{km: int, lines: int}>, open_km: array{from: int, to: int}} */
    private function computeSummary(int $fromEraId, int $toEraId): array
    {
        $pairs = $this->pairsSql('');
        $rows = DB::select(<<<SQL
            WITH {$pairs}
            SELECT change, SUM(meters) / 1000 AS km, COUNT(DISTINCT line_id) AS lines,
                   SUM(meters) FILTER (WHERE before = 'open') / 1000 AS open_before_km,
                   SUM(meters) FILTER (WHERE after = 'open') / 1000 AS open_after_km
            FROM changes GROUP BY change
        SQL, ['from' => $fromEraId, 'to' => $toEraId]);

        $changes = [];
        foreach (EraChange::cases() as $change) {
            $changes[$change->value] = ['km' => 0, 'lines' => 0];
        }
        $openBefore = 0.0;
        $openAfter = 0.0;
        foreach ($rows as $row) {
            $openBefore += (float) $row->open_before_km;
            $openAfter += (float) $row->open_after_km;
            if ($row->change !== null) {
                $changes[(string) $row->change] = ['km' => (int) round((float) $row->km), 'lines' => (int) $row->lines];
            }
        }

        return ['changes' => $changes, 'open_km' => ['from' => (int) round($openBefore), 'to' => (int) round($openAfter)]];
    }

    /**
     * CTE `before`, `after` et `changes` (line_id, geom, meters, change) : appariement des
     * tronçons des deux ères par ligne et géométrie identique.
     */
    private function pairsSql(string $spatialFilter): string
    {
        $from = $spatialFilter === '' ? 'line_segments ls' : 'line_segments ls CROSS JOIN bounds';
        $presenceBefore = EraChange::presenceSql('b.status');
        $presenceAfter = EraChange::presenceSql('a.status');
        $change = EraChange::changeSql('j.before', 'j.after');

        return <<<SQL
            before_era AS (
                SELECT ls.line_id, ls.status, ls.geom, ls.length_meters, md5(ST_AsBinary(ls.geom)) AS h
                FROM {$from} WHERE ls.era_id = :from {$spatialFilter}
            ),
            after_era AS (
                SELECT ls.line_id, ls.status, ls.geom, ls.length_meters, md5(ST_AsBinary(ls.geom)) AS h
                FROM {$from} WHERE ls.era_id = :to {$spatialFilter}
            ),
            joined AS (
                SELECT COALESCE(a.line_id, b.line_id) AS line_id,
                       COALESCE(a.geom, b.geom) AS geom,
                       COALESCE(a.length_meters, b.length_meters, ST_Length(COALESCE(a.geom, b.geom)::geography)) AS meters,
                       {$presenceBefore} AS before,
                       {$presenceAfter} AS after
                FROM before_era b
                FULL JOIN after_era a ON a.line_id = b.line_id AND a.h = b.h
            ),
            changes AS (
                SELECT j.line_id, j.geom, j.meters, j.before, j.after, {$change} AS change FROM joined j
            )
        SQL;
    }
}
