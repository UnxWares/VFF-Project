<?php

namespace App\Services;

use App\Enums\EraChange;
use App\Support\MapTiles;
use App\Support\MvtBytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Comparaison de deux ères de la carte : ce qui a ouvert, fermé ou disparu entre les deux.
 *
 * Calcul à la volée sur `line_segments`, sans table ni pipeline : les tronçons d'une ère
 * sont appariés à ceux de l'autre par ligne et par géométrie identique (empreinte md5 du
 * WKB), puis classés par EraChange. Les deux ères arrivent dans l'ordre chronologique
 * (la plus ancienne d'abord) : c'est à l'appelant de les remettre dans l'ordre.
 *
 * compare() rend le contenu commun à l'API REST (`GET /api/v1/eras/{year}/compare/{other}`), à
 * GraphQL (`eraComparison`) et à l'outil MCP `compare_eras`, liens compris.
 */
class EraComparison
{
    /** Durée de cache du résumé (s) : il ne change qu'après un recalcul de l'historique. */
    private const SUMMARY_TTL = 600;

    /**
     * Comparaison prête à servir : années comparées, changements, longueurs en service et liens
     * (même contenu pour REST, GraphQL et MCP).
     *
     * @param  string  $baseUrl  adresse du site devant les liens (`url('/')`, ou l'adresse publique pour MCP)
     * @return array{from: int, to: int, changes: array<string, array{km: int, lines: int}>, open_km: array{from: int, to: int}, links: array{self: string, map: string, tiles: string, sources: string}}
     */
    public function compare(int $fromEraId, int $fromYear, int $toEraId, int $toYear, string $baseUrl): array
    {
        $summary = $this->summary($fromEraId, $toEraId);

        return [
            'from' => $fromYear,
            'to' => $toYear,
            'changes' => $summary['changes'],
            'open_km' => $summary['open_km'],
            'links' => self::links($fromYear, $toYear, $baseUrl),
        ];
    }

    /**
     * Liens d'une comparaison : elle-même dans l'API, la vue « Comparer » de la carte, le gabarit
     * MapLibre de ses tuiles et la liste des sources.
     *
     * @return array{self: string, map: string, tiles: string, sources: string}
     */
    public static function links(int $fromYear, int $toYear, string $baseUrl): array
    {
        $base = rtrim($baseUrl, '/');
        $self = $base.route('api.eras.compare', [$fromYear, $toYear], absolute: false);

        return [
            'self' => $self,
            'map' => "{$base}/maps?era={$fromYear}&compare={$toYear}",
            // Gabarit MapLibre : route() refuse « {z} » (paramètre numérique).
            'tiles' => "{$self}/tiles/{z}/{x}/{y}.mvt",
            'sources' => $base.route('api.sources.index', absolute: false),
        ];
    }

    /**
     * Fournisseurs (`line_segments.source`) des tronçons des deux ères, pour l'attribution ; gardés
     * en cache avec le résumé.
     *
     * @return list<string|null>
     */
    public function sources(int $fromEraId, int $toEraId): array
    {
        return $this->summary($fromEraId, $toEraId)['sources'];
    }

    /**
     * Longueur (km arrondis) et nombre de lignes touchées pour chaque changement (clé =
     * EraChange::value), longueur en service à chacune des deux ères et fournisseurs des tronçons.
     *
     * @return array{changes: array<string, array{km: int, lines: int}>, open_km: array{from: int, to: int}, sources: list<string|null>}
     */
    private function summary(int $fromEraId, int $toEraId): array
    {
        return Cache::remember(
            "era-compare:summary:v2:{$fromEraId}:{$toEraId}",
            self::SUMMARY_TTL,
            fn (): array => $this->computeSummary($fromEraId, $toEraId),
        );
    }

    /** Tuile vectorielle (couche `vff-compare`) des tronçons qui ont changé, ou restés en service. */
    public function tile(int $fromEraId, int $toEraId, int $z, int $x, int $y): string
    {
        $pairs = $this->pairsSql('AND ls.geom && bounds.env_4326');
        $geom = MapTiles::displaySql('c.geom', $z);
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
                       ST_AsMVTGeom({$geom}, bounds.env_3857, 4096, 256, true) AS geom
                FROM changes c
                JOIN lines l ON l.id = c.line_id
                CROSS JOIN bounds
                WHERE c.change IS NOT NULL
            )
            SELECT ST_AsMVT(mvt_layer.*, 'vff-compare') AS tile FROM mvt_layer WHERE geom IS NOT NULL
        SQL, ['from' => $fromEraId, 'to' => $toEraId, 'z' => $z, 'x' => $x, 'y' => $y]);

        return MvtBytes::from($result->tile ?? '');
    }

    /** @return array{changes: array<string, array{km: int, lines: int}>, open_km: array{from: int, to: int}, sources: list<string|null>} */
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

        // null compris : un tronçon sans source a aussi son fournisseur par défaut (attribution MCP).
        $sources = DB::table('line_segments')->whereIn('era_id', [$fromEraId, $toEraId])
            ->distinct()->orderBy('source')->pluck('source')
            ->map(fn ($source): ?string => $source === null ? null : (string) $source)->values()->all();

        return [
            'changes' => $changes,
            'open_km' => ['from' => (int) round($openBefore), 'to' => (int) round($openAfter)],
            'sources' => $sources,
        ];
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
